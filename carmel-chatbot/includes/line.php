<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * LINE公式アカウント（Messaging API）連携。
 * お客様がご自分のLINEから「カーメル みほ」と会話できる。
 * - Webhookでメッセージを受信 → 署名検証 → 既存チャットエンジン(carmel_cb_handle_chat)で返答生成 → LINE返信
 * - 会話はSlack等へミラー＆会話ログにも残る（session_id = "line:<userId>"）
 */

/** LINE連携が使える状態か */
function carmel_cb_line_ready( $s = null ) {
	$s = $s ?: carmel_cb_get_settings();
	return ! empty( $s['line_oa_enabled'] ) && ! empty( $s['line_channel_token'] ) && ! empty( $s['line_channel_secret'] );
}

/** WebhookのURL（LINE Developersコンソールに登録する） */
function carmel_cb_line_webhook_url() {
	return esc_url_raw( rest_url( 'carmel-cb/v1/line-webhook' ) );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/line-webhook', array(
		'methods'             => 'POST',
		'callback'            => 'carmel_cb_line_webhook',
		'permission_callback' => '__return_true',
	) );
} );

/** LINEからのWebhook受信 */
function carmel_cb_line_webhook( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	if ( ! carmel_cb_line_ready( $s ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 200 ); // 常に200を返す（LINEの再送を避ける）
	}

	$raw = $request->get_body();
	$sig = $request->get_header( 'x-line-signature' );
	$calc = base64_encode( hash_hmac( 'sha256', $raw, $s['line_channel_secret'], true ) );
	if ( empty( $sig ) || ! hash_equals( $calc, (string) $sig ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_signature' ), 200 );
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) || empty( $data['events'] ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	foreach ( $data['events'] as $event ) {
		carmel_cb_line_handle_event( $s, $event );
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/** 1イベントを処理 */
function carmel_cb_line_handle_event( $s, $event ) {
	$type       = $event['type'] ?? '';
	$reply_tok  = $event['replyToken'] ?? '';
	$user_id    = $event['source']['userId'] ?? '';
	if ( $user_id === '' ) { return; }
	$session_id = 'line:' . $user_id;

	// 友だち追加 → あいさつ
	if ( $type === 'follow' ) {
		$intro = trim( (string) ( $s['talk_intro'] ?? '' ) );
		if ( $intro === '' ) { $intro = 'こんにちは、カーメルの みほ です。審査が不安な方もあきらめる必要はありません。いま一番気になっていることを、ひと言で教えてください。'; }
		if ( $reply_tok ) { carmel_cb_line_reply( $s, $reply_tok, $intro ); }
		return;
	}

	if ( $type !== 'message' ) { return; }

	$mtype    = $event['message']['type'] ?? '';
	$user_text = '';

	if ( $mtype === 'text' ) {
		$user_text = (string) ( $event['message']['text'] ?? '' );
	} elseif ( in_array( $mtype, array( 'image', 'file' ), true ) ) {
		// 画像・ファイルを取得して保存（担当者がSlack/ログで確認できるように）
		$saved = carmel_cb_line_download_content( $s, $event['message']['id'] ?? '', $mtype );
		if ( $saved && function_exists( 'carmel_cb_log_media' ) ) {
			carmel_cb_log_media( $session_id, 'user', $saved['url'], $saved['name'], $mtype === 'image' );
		}
		$user_text = $mtype === 'image' ? '（画像を送信しました）' : '（ファイルを送信しました）';
	} else {
		$user_text = '（スタンプなどのメッセージを送信しました）';
	}

	if ( trim( $user_text ) === '' ) { return; }

	// 会話履歴（LINEユーザーごと・一時保存）
	$history = carmel_cb_line_history_get( $user_id );
	$history[] = array( 'role' => 'user', 'content' => $user_text );

	// 既存チャットエンジンを内部呼び出し
	$result = carmel_cb_line_run_engine( $session_id, $history );
	$reply  = trim( (string) ( $result['reply'] ?? '' ) );
	if ( $reply === '' ) { $reply = 'うまくお答えできませんでした。恐れ入りますが、もう一度お聞かせください。'; }

	$history[] = array( 'role' => 'assistant', 'content' => $reply );
	carmel_cb_line_history_set( $user_id, $history );

	// 返信（AIの提案はクイックリプライに、審査系ならURLボタンも）
	$quick = array();
	if ( ! empty( $result['suggestions'] ) && is_array( $result['suggestions'] ) ) {
		$quick = array_slice( array_values( $result['suggestions'] ), 0, 10 );
	}
	$cta_url = '';
	if ( in_array( ( $result['action'] ?? '' ), array( 'apply', 'shinsa' ), true ) ) {
		$u = trim( (string) ( $s['apply_url'] ?? '' ) );
		if ( $u && preg_match( '#^https?://#', $u ) ) { $cta_url = $u; }
	}
	carmel_cb_line_reply( $s, $reply_tok, $reply, $quick, $cta_url );
}

/** 既存チャットエンジンを内部呼び出しして返答データを得る */
function carmel_cb_line_run_engine( $session_id, $history ) {
	if ( ! function_exists( 'carmel_cb_handle_chat' ) ) { return array( 'reply' => '' ); }
	$req = new WP_REST_Request( 'POST', '/carmel-cb/v1/chat' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body( wp_json_encode( array(
		'session_id' => $session_id,
		'messages'   => $history,
		'channel'    => 'line',
	) ) );
	$resp = carmel_cb_handle_chat( $req );
	if ( $resp instanceof WP_REST_Response ) { return (array) $resp->get_data(); }
	return array( 'reply' => '' );
}

/** 会話履歴の一時保存（LINEユーザー単位） */
function carmel_cb_line_history_get( $user_id ) {
	$v = get_transient( 'carmel_cb_line_' . md5( $user_id ) );
	return is_array( $v ) ? $v : array();
}
function carmel_cb_line_history_set( $user_id, $history ) {
	// 直近16メッセージに制限
	if ( count( $history ) > 16 ) { $history = array_slice( $history, -16 ); }
	$ttl = max( 3600, ( (int) ( carmel_cb_get_settings()['resume_ttl_min'] ?? 60 ) ) * 60 );
	set_transient( 'carmel_cb_line_' . md5( $user_id ), $history, $ttl );
}

/** LINEへ返信（replyToken使用）。$quickは文字列配列、$cta_urlがあればURLボタンを追加。 */
function carmel_cb_line_reply( $s, $reply_token, $text, $quick = array(), $cta_url = '' ) {
	if ( empty( $reply_token ) ) { return; }
	$text = mb_substr( (string) $text, 0, 4900 );

	$items = array();
	foreach ( (array) $quick as $q ) {
		$label = mb_substr( trim( (string) $q ), 0, 20 );
		$msg   = mb_substr( trim( (string) $q ), 0, 300 );
		if ( $label === '' ) { continue; }
		$items[] = array( 'type' => 'action', 'action' => array( 'type' => 'message', 'label' => $label, 'text' => $msg ) );
	}
	if ( $cta_url !== '' && count( $items ) < 13 ) {
		$items[] = array( 'type' => 'action', 'action' => array( 'type' => 'uri', 'label' => '▶ 仮審査に進む', 'uri' => $cta_url ) );
	}

	$message = array( 'type' => 'text', 'text' => $text );
	if ( $items ) { $message['quickReply'] = array( 'items' => array_slice( $items, 0, 13 ) ); }

	wp_remote_post( 'https://api.line.me/v2/bot/message/reply', array(
		'timeout' => 15,
		'headers' => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $s['line_channel_token'],
		),
		'body'    => wp_json_encode( array(
			'replyToken' => $reply_token,
			'messages'   => array( $message ),
		) ),
	) );
}

/** LINEのメッセージコンテンツ（画像等）を取得してアップロードフォルダに保存 */
function carmel_cb_line_download_content( $s, $message_id, $mtype ) {
	if ( empty( $message_id ) ) { return null; }
	$res = wp_remote_get( 'https://api-data.line.me/v2/bot/message/' . rawurlencode( $message_id ) . '/content', array(
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $s['line_channel_token'] ),
	) );
	if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) { return null; }
	$bytes = wp_remote_retrieve_body( $res );
	if ( $bytes === '' ) { return null; }

	$ct  = (string) wp_remote_retrieve_header( $res, 'content-type' );
	$ext = 'bin';
	if ( strpos( $ct, 'jpeg' ) !== false ) { $ext = 'jpg'; }
	elseif ( strpos( $ct, 'png' ) !== false ) { $ext = 'png'; }
	elseif ( strpos( $ct, 'gif' ) !== false ) { $ext = 'gif'; }
	elseif ( strpos( $ct, 'pdf' ) !== false ) { $ext = 'pdf'; }

	$name = 'line-' . $mtype . '-' . $message_id . '.' . $ext;
	$up   = wp_upload_bits( $name, null, $bytes );
	if ( ! empty( $up['error'] ) ) { return null; }
	return array( 'url' => $up['url'], 'name' => $name );
}
