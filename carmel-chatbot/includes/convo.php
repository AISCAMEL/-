<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 会話ミラー（LINE風）＋ 常時ライブ割り込み。
 * - Slack Bot（双方向）設定時、チャットの会話（お客様🙋 / AI🤖）を1つのスレッドに流す。
 * - スタッフはそのスレッドに返信するだけで、会話の途中でもお客様のチャットに割り込める。
 * - お客様側はスレッドの人間の返信を常時ポーリングして表示する。
 * ※ handoff.php の carmel_cb_slack_api / carmel_cb_slack_live_on / carmel_cb_within_hours を利用。
 */

function carmel_cb_convo_key( $sid ) { return 'carmel_cb_convo_' . md5( (string) $sid ); }
function carmel_cb_visitor_key( $sid ) { return 'carmel_cb_visitor_' . md5( (string) $sid ); }

/** セッションに紐づくお客様情報（名前・メール）を1行の文字列で返す。無ければ空。 */
function carmel_cb_visitor_line( $sid ) {
	$v = get_transient( carmel_cb_visitor_key( $sid ) );
	if ( ! is_array( $v ) ) { return ''; }
	$name  = trim( (string) ( $v['name'] ?? '' ) );
	$email = trim( (string) ( $v['email'] ?? '' ) );
	if ( $name === '' && $email === '' ) { return ''; }
	$parts = array();
	if ( $name !== '' )  { $parts[] = $name . ' 様'; }
	if ( $email !== '' ) { $parts[] = $email; }
	return '👤 お客様: ' . implode( ' / ', $parts );
}

/** スレッドを用意（無ければ作成）。 */
function carmel_cb_convo_ensure( $s, $sid, $page, $within_label ) {
	if ( ! carmel_cb_slack_live_on( $s ) || $sid === '' ) { return false; }
	$key  = carmel_cb_convo_key( $sid );
	$sess = get_transient( $key );
	if ( $sess ) { return $sess; }

	$vline = carmel_cb_visitor_line( $sid );
	$head = "🗨️ *新しいチャットが始まりました* {$within_label}\n"
		. ( $vline !== '' ? $vline . "\n" : '' )
		. 'ページ: ' . ( $page !== '' ? $page : '(不明)' ) . "\n"
		. '_このスレッドに返信すると、お客様のチャットに直接届きます（会話の途中でも割り込めます）。_';
	$res = carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array(
		'channel' => $s['slack_channel'],
		'text'    => $head,
	) );
	if ( empty( $res['ok'] ) ) { return false; }

	$sess = array( 'channel' => $res['channel'], 'thread' => $res['ts'], 'last' => $res['ts'] );
	set_transient( $key, $sess, 3 * HOUR_IN_SECONDS );
	return $sess;
}

/** スレッドへ1行投稿。 */
function carmel_cb_convo_post( $s, $sess, $text ) {
	carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array(
		'channel'   => $sess['channel'],
		'thread_ts' => $sess['thread'],
		'text'      => $text,
	) );
}

/** お客様の発話とAIの返答をスレッドに流す（会話ミラー）。 */
function carmel_cb_convo_mirror( $s, $sid, $user_msg, $ai_reply, $page, $within_label ) {
	$sess = carmel_cb_convo_ensure( $s, $sid, $page, $within_label );
	if ( ! $sess ) { return false; }
	if ( $user_msg !== '' ) { carmel_cb_convo_post( $s, $sess, '🙋 お客様: ' . mb_substr( (string) $user_msg, 0, 1500 ) ); }
	if ( $ai_reply !== '' )  { carmel_cb_convo_post( $s, $sess, '🤖 AI: ' . mb_substr( (string) $ai_reply, 0, 1500 ) ); }
	return true;
}

/** お客様の発話をスレッドへ中継（担当者対応中）。 */
function carmel_cb_convo_relay( $s, $sid, $text ) {
	$sess = get_transient( carmel_cb_convo_key( $sid ) );
	if ( ! $sess ) { return false; }
	carmel_cb_convo_post( $s, $sess, '🙋 お客様: ' . mb_substr( (string) $text, 0, 1500 ) );
	return true;
}

/** スレッドの「人間（担当者）」の新規返信を取得。 */
function carmel_cb_convo_poll( $s, $sid ) {
	$sess = get_transient( carmel_cb_convo_key( $sid ) );
	if ( ! $sess ) { return array(); }
	$res = carmel_cb_slack_api( 'conversations.replies', $s['slack_bot_token'], array(
		'channel' => $sess['channel'],
		'ts'      => $sess['thread'],
		'oldest'  => $sess['last'],
	), false );
	if ( empty( $res['ok'] ) || empty( $res['messages'] ) ) { return array(); }

	$out = array();
	$last = $sess['last'];
	foreach ( $res['messages'] as $m ) {
		if ( empty( $m['ts'] ) || (float) $m['ts'] <= (float) $sess['last'] ) { continue; }
		if ( ! empty( $m['bot_id'] ) || ( isset( $m['subtype'] ) && $m['subtype'] === 'bot_message' ) || empty( $m['user'] ) ) { continue; }
		$item = array( 'ts' => $m['ts'], 'text' => (string) ( $m['text'] ?? '' ), 'files' => array() );
		if ( ! empty( $m['files'] ) && is_array( $m['files'] ) && function_exists( 'carmel_cb_slack_download_files' ) ) {
			$item['files'] = carmel_cb_slack_download_files( $s, $m['files'] );
		}
		$out[] = $item;
		$last  = $m['ts'];
	}
	if ( $out ) {
		$sess['last'] = $last;
		set_transient( carmel_cb_convo_key( $sid ), $sess, 3 * HOUR_IN_SECONDS );
	}
	return $out;
}

/* ============================ REST ============================ */

add_action( 'rest_api_init', function () {
	$ns = 'carmel-cb/v1';
	register_rest_route( $ns, '/convo/poll',    array( 'methods' => 'GET',  'callback' => 'carmel_cb_handle_convo_poll',    'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/convo/send',    array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_convo_send',    'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/convo/handoff', array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_convo_handoff', 'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/visitor',       array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_visitor',       'permission_callback' => '__return_true' ) );
} );

/**
 * チャット開始時のお客様情報（名前・メール）を受け取り、セッションに保存＋通知する。
 * - Slack Bot（双方向）設定時：会話スレッドを用意し、冒頭にお客様情報を表示（誰から来たか分かる）。
 * - それ以外：通知先（Slack Webhook / LINE WORKS 等）へ「新しいお客様」をお知らせ。
 */
function carmel_cb_handle_visitor( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	$b = $request->get_json_params();
	$sid   = sanitize_text_field( $b['session_id'] ?? '' );
	$name  = sanitize_text_field( $b['name'] ?? '' );
	$email = sanitize_email( $b['email'] ?? '' );
	$page  = esc_url_raw( $b['page'] ?? '' );

	if ( $sid === '' || $name === '' ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => '入力が不足しています' ), 200 );
	}

	// セッションに保存（会話ミラー／担当者引き継ぎでお客様名を表示するために使う）
	set_transient( carmel_cb_visitor_key( $sid ), array( 'name' => $name, 'email' => $email, 'page' => $page ), 3 * HOUR_IN_SECONDS );

	// 通知：Bot（双方向）ならスレッド冒頭にお客様情報。未設定ならWebhook等へお知らせ。
	if ( carmel_cb_slack_live_on( $s ) ) {
		carmel_cb_convo_ensure( $s, $sid, $page, carmel_cb_within_hours( $s ) ? '（営業時間内）' : '（営業時間外）' );
	} elseif ( function_exists( 'carmel_cb_slack_notify_text' ) ) {
		$txt = "🆕 新しいお客様がチャットを開始しました\n"
			. 'お名前: ' . $name . "\n"
			. 'メール: ' . ( $email !== '' ? $email : '(未入力)' ) . "\n"
			. 'ページ: ' . ( $page !== '' ? $page : '(不明)' );
		carmel_cb_slack_notify_text( $s, $txt );
	}

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

function carmel_cb_handle_convo_poll( WP_REST_Request $request ) {
	$s   = carmel_cb_get_settings();
	$sid = sanitize_text_field( $request->get_param( 'session_id' ) );
	$msgs = carmel_cb_slack_live_on( $s ) ? carmel_cb_convo_poll( $s, $sid ) : array();
	return new WP_REST_Response( array( 'messages' => $msgs ), 200 );
}

function carmel_cb_handle_convo_send( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	$b = $request->get_json_params();
	$sid  = sanitize_text_field( $b['session_id'] ?? '' );
	$text = sanitize_textarea_field( $b['text'] ?? '' );
	$ok   = carmel_cb_slack_live_on( $s ) ? carmel_cb_convo_relay( $s, $sid, $text ) : false;
	return new WP_REST_Response( array( 'ok' => (bool) $ok ), 200 );
}

/** 「担当者に相談」：同じ会話スレッドに担当者希望マーカーを投稿（会話は常時ポーリング中）。 */
function carmel_cb_handle_convo_handoff( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['handoff_enabled'] ) ) { return new WP_REST_Response( array( 'mode' => 'disabled' ), 200 ); }

	$within = carmel_cb_within_hours( $s );
	if ( ! $within ) {
		return new WP_REST_Response( array( 'mode' => 'offhours', 'msg' => (string) ( $s['handoff_offhours_msg'] ?? '' ) ), 200 );
	}
	if ( ! carmel_cb_slack_live_on( $s ) ) { return new WP_REST_Response( array( 'mode' => 'notify' ), 200 ); }

	$b   = $request->get_json_params();
	$sid = sanitize_text_field( $b['session_id'] ?? '' );
	$q   = sanitize_textarea_field( $b['question'] ?? '' );
	$sess = carmel_cb_convo_ensure( $s, $sid, esc_url_raw( $b['page'] ?? '' ), '（営業時間内）' );
	if ( ! $sess ) { return new WP_REST_Response( array( 'mode' => 'notify' ), 200 ); }

	carmel_cb_convo_post( $s, $sess, "🆕🙋 *担当者希望！お客様がお待ちです*\n> " . mb_substr( (string) $q, 0, 500 ) . "\n*このスレッドに返信すると、お客様のチャットに届きます。*" );

	$busy_sec = max( 3, min( 120, (int) ( $s['handoff_busy_sec'] ?? 15 ) ) );
	$wait_sec = max( $busy_sec + 2, min( 300, (int) ( $s['handoff_wait_sec'] ?? 45 ) ) );
	return new WP_REST_Response( array(
		'mode'    => 'live',
		'waitMsg' => (string) ( $s['handoff_wait_msg'] ?? '担当者におつなぎしています。少々お待ちください…' ),
		'busyMs'  => $busy_sec * 1000,
		'busyMsg' => (string) ( $s['handoff_busy_msg'] ?? '' ),
		'timeout' => $wait_sec * 1000,
	), 200 );
}
