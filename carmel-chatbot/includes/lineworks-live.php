<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * LINE WORKS 有人対応（双方向ライブ）。
 * 「担当者に繋ぐ」時だけ動作：お客様の会話をLINE WORKSのトークルームへ流し、
 * 担当者がLINE WORKSで返信 → コールバックで受信 → お客様のチャットに届ける。
 *
 * ルーティング：同じトークルームでも取り違えないよう、各お客様に短い合図【#XXXX】を付与。
 * 担当者は返信の先頭にその合図を付ける（対応中が1件だけなら合図なしでもOK）。
 */

/** この経路（LINE WORKS）で有人対応するか？ */
function carmel_cb_lw_live_on( $s ) {
	if ( ( $s['handoff_channel'] ?? 'slack' ) !== 'lineworks' ) { return false; }
	return ! empty( $s['lw_bot_id'] ) && ! empty( $s['lw_channel_id'] )
		&& ! empty( $s['lw_client_id'] ) && ! empty( $s['lw_client_secret'] )
		&& ! empty( $s['lw_service_account'] ) && ! empty( $s['lw_private_key'] );
}

function carmel_cb_lw_ho_key( $sid )  { return 'carmel_cb_lwho_' . md5( (string) $sid ); }

/** LINE WORKSトークルームへテキスト送信（成功で true）。 */
function carmel_cb_lineworks_send_text( $s, $text ) {
	if ( empty( $s['lw_bot_id'] ) || empty( $s['lw_channel_id'] ) ) { return false; }
	$token = carmel_cb_lineworks_token( $s );
	if ( ! $token ) { return false; }
	$res = wp_remote_post(
		'https://www.worksapis.com/v1.0/bots/' . rawurlencode( $s['lw_bot_id'] ) . '/channels/' . rawurlencode( $s['lw_channel_id'] ) . '/messages',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'content' => array( 'type' => 'text', 'text' => mb_substr( (string) $text, 0, 1900 ) ) ) ),
		)
	);
	if ( is_wp_error( $res ) ) { return false; }
	return (int) wp_remote_retrieve_response_code( $res ) < 300;
}

/** 対応中セッションの索引（合図コード→sid）を保持（取り違え防止＆合図なし返信のため）。 */
function carmel_cb_lw_active_get() {
	$m = get_option( 'carmel_cb_lw_active', array() );
	if ( ! is_array( $m ) ) { $m = array(); }
	$now = time();
	foreach ( $m as $code => $row ) {
		if ( empty( $row['exp'] ) || $row['exp'] < $now ) { unset( $m[ $code ] ); }
	}
	return $m;
}
function carmel_cb_lw_active_set( $m ) { update_option( 'carmel_cb_lw_active', $m, false ); }
function carmel_cb_lw_active_add( $code, $sid ) {
	$m = carmel_cb_lw_active_get();
	$m[ $code ] = array( 'sid' => $sid, 'exp' => time() + 2 * HOUR_IN_SECONDS );
	carmel_cb_lw_active_set( $m );
}
function carmel_cb_lw_active_remove( $code ) {
	$m = carmel_cb_lw_active_get();
	unset( $m[ $code ] );
	carmel_cb_lw_active_set( $m );
}

/** 有人対応の開始：トークルームへ打診を投稿し、合図を発行。 */
function carmel_cb_lw_start( $s, $sid, $question ) {
	$code = strtoupper( substr( md5( $sid . '|' . microtime() ), 0, 4 ) );
	$text = "🆕【担当者希望】お客様がチャットでお待ちです  合図 【#{$code}】\n"
		. '▼ご相談：' . mb_substr( (string) $question, 0, 500 ) . "\n\n"
		. "👉 返信するには、メッセージの先頭に 【#{$code}】 を付けて送ってください。\n"
		. '（対応中のお客様が1人だけなら、合図なしでも届きます）';
	if ( ! carmel_cb_lineworks_send_text( $s, $text ) ) { return false; }

	set_transient( carmel_cb_lw_ho_key( $sid ), array( 'code' => $code, 'msgs' => array() ), HOUR_IN_SECONDS );
	carmel_cb_lw_active_add( $code, $sid );
	return true;
}

/** お客様のライブ発言をトークルームへ中継。 */
function carmel_cb_lw_send( $s, $sid, $text ) {
	$sess = get_transient( carmel_cb_lw_ho_key( $sid ) );
	if ( ! $sess ) { return false; }
	return carmel_cb_lineworks_send_text( $s, '🙋 お客様【#' . $sess['code'] . '】: ' . mb_substr( (string) $text, 0, 1000 ) );
}

/** 担当者返信の取り出し（コールバックで貯まったものを返して空にする）。 */
function carmel_cb_lw_poll( $s, $sid ) {
	$sess = get_transient( carmel_cb_lw_ho_key( $sid ) );
	if ( ! $sess || empty( $sess['msgs'] ) ) { return array(); }
	$out = $sess['msgs'];
	$sess['msgs'] = array();
	set_transient( carmel_cb_lw_ho_key( $sid ), $sess, HOUR_IN_SECONDS );
	return $out;
}

/** コールバックで受け取った担当者メッセージを該当セッションへ格納。 */
function carmel_cb_lw_push_operator_msg( $sid, $text ) {
	$sess = get_transient( carmel_cb_lw_ho_key( $sid ) );
	if ( ! $sess ) { return; }
	$sess['msgs'][] = array( 'ts' => (string) microtime( true ), 'text' => (string) $text, 'files' => array() );
	set_transient( carmel_cb_lw_ho_key( $sid ), $sess, HOUR_IN_SECONDS );
	// 会話ログにも担当者発言として残す
	if ( function_exists( 'carmel_cb_log' ) ) { carmel_cb_log( $sid, 'operator', (string) $text ); }
}

/* ============================ コールバック（受信） ============================ */

add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/lw-callback', array(
		'methods'             => 'POST',
		'callback'            => 'carmel_cb_lw_callback',
		'permission_callback' => '__return_true',
	) );
} );

/** コールバックURL（管理画面に表示してLINE WORKSへ登録してもらう）。 */
function carmel_cb_lw_callback_url() {
	return esc_url_raw( rest_url( 'carmel-cb/v1/lw-callback' ) );
}

/** LINE WORKS Bot からのコールバック受信。 */
function carmel_cb_lw_callback( WP_REST_Request $request ) {
	$s   = carmel_cb_get_settings();
	$raw = $request->get_body();

	// 署名検証（Bot Secret で HMAC-SHA256 → Base64）
	$secret = (string) ( $s['lw_bot_secret'] ?? '' );
	$sig    = $request->get_header( 'x-works-signature' );
	if ( $secret === '' || empty( $sig ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 200 );
	}
	$calc = base64_encode( hash_hmac( 'sha256', $raw, $secret, true ) );
	if ( ! hash_equals( $calc, (string) $sig ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_signature' ), 200 );
	}

	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) { return new WP_REST_Response( array( 'ok' => true ), 200 ); }

	// 単一イベント or events[] の両方に対応
	$events = isset( $data['events'] ) && is_array( $data['events'] ) ? $data['events'] : array( $data );
	foreach ( $events as $ev ) {
		// トークルームID（channelId）を検出したら保存（管理画面でコピーできるように）
		$ch = $ev['source']['channelId'] ?? '';
		if ( $ch !== '' ) {
			update_option( 'carmel_cb_lw_seen_channel', array( 'id' => $ch, 'at' => current_time( 'mysql' ) ), false );
		}
		if ( ( $ev['type'] ?? '' ) !== 'message' ) { continue; }
		$ctype = $ev['content']['type'] ?? '';
		if ( $ctype !== 'text' ) { continue; } // v1はテキストのみ中継
		$text = trim( (string) ( $ev['content']['text'] ?? '' ) );
		if ( $text === '' ) { continue; }

		// 合図【#XXXX】でセッション特定。無ければ対応中が1件の時だけ自動判定。
		$sid = '';
		if ( preg_match( '/[【\[]\s*#?\s*([A-Za-z0-9]{4})\s*[】\]]/u', $text, $mm ) ) {
			$code = strtoupper( $mm[1] );
			$map  = carmel_cb_lw_active_get();
			if ( isset( $map[ $code ]['sid'] ) ) { $sid = $map[ $code ]['sid']; }
			$text = trim( preg_replace( '/[【\[]\s*#?\s*[A-Za-z0-9]{4}\s*[】\]]/u', '', $text, 1 ) );
		} else {
			$map = carmel_cb_lw_active_get();
			if ( count( $map ) === 1 ) { $row = reset( $map ); $sid = $row['sid']; }
		}
		if ( $sid !== '' && $text !== '' ) {
			carmel_cb_lw_push_operator_msg( $sid, $text );
		}
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* ============================ 接続テスト（診断） ============================ */

/** LINE WORKS連携を段階的に診断。各ステップ array(ok(bool), メッセージ) の配列を返す。 */
function carmel_cb_lw_diagnose() {
	$s   = carmel_cb_get_settings();
	$res = array();

	// 1) 設定の有無
	$need = array(
		'lw_client_id' => 'Client ID', 'lw_client_secret' => 'Client Secret',
		'lw_service_account' => 'Service Account', 'lw_private_key' => '秘密鍵(PEM)',
		'lw_bot_id' => 'Bot ID', 'lw_channel_id' => '送信先トークルームID', 'lw_bot_secret' => 'Bot Secret',
	);
	$miss = array();
	foreach ( $need as $k => $l ) { if ( trim( (string) ( $s[ $k ] ?? '' ) ) === '' ) { $miss[] = $l; } }
	$res[] = $miss ? array( false, '未入力の項目があります：' . implode( '・', $miss ) ) : array( true, 'LINE WORKS設定は全項目入力済み' );

	// 2) 経路
	$res[] = ( ( $s['handoff_channel'] ?? 'slack' ) === 'lineworks' )
		? array( true, '有人対応の経路 = LINE WORKS' )
		: array( false, '有人対応の経路がLINE WORKSになっていません（現在：' . esc_html( $s['handoff_channel'] ?? 'slack' ) . '）→ 基本設定で「LINE WORKS」を選択' );

	// 3) 営業時間
	$within = function_exists( 'carmel_cb_within_hours' ) ? carmel_cb_within_hours( $s ) : true;
	$res[] = $within
		? array( true, '現在は営業時間内' )
		: array( false, '現在は営業時間外です。実際の「担当者に相談」は営業時間外だと起動しません（※このテスト送信は時間に関係なく実行します）' );

	// 4) 秘密鍵の体裁
	$pk = (string) ( $s['lw_private_key'] ?? '' );
	if ( strpos( $pk, 'BEGIN' ) === false ) {
		$res[] = array( false, '秘密鍵の形式が不正の可能性（「-----BEGIN PRIVATE KEY-----」が見当たりません）。鍵の中身を貼り直してください' );
	}
	if ( empty( $s['lw_client_id'] ) || empty( $s['lw_service_account'] ) || $pk === '' ) {
		return $res; // これ以上は進めない
	}

	// 5) アクセストークン取得（キャッシュを消して実リクエスト）
	delete_transient( 'carmel_cb_lw_token' );
	if ( ! function_exists( 'openssl_sign' ) ) { $res[] = array( false, 'サーバに openssl 関数がありません（ホスティングに確認が必要）' ); return $res; }
	$now    = time();
	$header = carmel_cb_b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claim  = carmel_cb_b64url( wp_json_encode( array( 'iss' => $s['lw_client_id'], 'sub' => $s['lw_service_account'], 'iat' => $now, 'exp' => $now + 3600 ) ) );
	$sig    = '';
	if ( ! openssl_sign( $header . '.' . $claim, $sig, $pk, 'SHA256' ) ) {
		$res[] = array( false, 'JWT署名に失敗（秘密鍵が正しくない可能性）：' . esc_html( (string) openssl_error_string() ) );
		return $res;
	}
	$jwt = $header . '.' . $claim . '.' . carmel_cb_b64url( $sig );
	$tr  = wp_remote_post( 'https://auth.worksmobile.com/oauth2/v2.0/token', array(
		'timeout' => 20,
		'body'    => array(
			'assertion'     => $jwt,
			'grant_type'    => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'client_id'     => $s['lw_client_id'],
			'client_secret' => $s['lw_client_secret'],
			'scope'         => 'bot',
		),
	) );
	if ( is_wp_error( $tr ) ) { $res[] = array( false, 'トークン取得の通信エラー：' . esc_html( $tr->get_error_message() ) ); return $res; }
	$tcode = (int) wp_remote_retrieve_response_code( $tr );
	$tbody = (string) wp_remote_retrieve_body( $tr );
	$td    = json_decode( $tbody, true );
	if ( empty( $td['access_token'] ) ) {
		$hint = ( strpos( $tbody, 'scope' ) !== false ) ? '（スコープ「bot」がAppに付いているか確認）' : '';
		$res[] = array( false, 'アクセストークン取得に失敗 (HTTP ' . $tcode . ')：' . esc_html( mb_substr( $tbody, 0, 300 ) ) . $hint );
		return $res;
	}
	$res[] = array( true, 'アクセストークン取得 OK' );

	// 6) テスト送信
	$sr = wp_remote_post(
		'https://www.worksapis.com/v1.0/bots/' . rawurlencode( $s['lw_bot_id'] ) . '/channels/' . rawurlencode( $s['lw_channel_id'] ) . '/messages',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $td['access_token'], 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'content' => array( 'type' => 'text', 'text' => '✅ カーメル接続テスト：このトークルームに通知できています。' ) ) ),
		)
	);
	if ( is_wp_error( $sr ) ) { $res[] = array( false, '送信の通信エラー：' . esc_html( $sr->get_error_message() ) ); return $res; }
	$scode = (int) wp_remote_retrieve_response_code( $sr );
	$sbody = (string) wp_remote_retrieve_body( $sr );
	if ( $scode < 300 ) {
		$res[] = array( true, 'テスト送信 OK → LINE WORKSのトークルームに「✅カーメル接続テスト」が届いていれば成功です' );
	} else {
		$hint = '';
		if ( $scode === 403 || $scode === 404 ) { $hint = '（Bot IDかトークルームID、またはBotが部屋に居るかを確認）'; }
		$res[] = array( false, 'テスト送信に失敗 (HTTP ' . $scode . ')：' . esc_html( mb_substr( $sbody, 0, 300 ) ) . $hint );
	}
	return $res;
}

/* ============================ 共通ディスパッチ（Slack / LINE WORKS） ============================ */

/** 現在有効な有人対応の経路：'slack' | 'lineworks' | ''（なし）。 */
function carmel_cb_live_channel( $s ) {
	if ( ( $s['handoff_channel'] ?? 'slack' ) === 'lineworks' ) {
		return carmel_cb_lw_live_on( $s ) ? 'lineworks' : '';
	}
	return carmel_cb_slack_live_on( $s ) ? 'slack' : '';
}
function carmel_cb_live_on( $s )            { return carmel_cb_live_channel( $s ) !== ''; }
function carmel_cb_live_start( $s, $sid, $q ) {
	return carmel_cb_live_channel( $s ) === 'lineworks' ? carmel_cb_lw_start( $s, $sid, $q ) : carmel_cb_slack_start( $s, $sid, $q );
}
function carmel_cb_live_send( $s, $sid, $text ) {
	return carmel_cb_live_channel( $s ) === 'lineworks' ? carmel_cb_lw_send( $s, $sid, $text ) : carmel_cb_slack_send( $s, $sid, $text );
}
function carmel_cb_live_poll( $s, $sid ) {
	return carmel_cb_live_channel( $s ) === 'lineworks' ? carmel_cb_lw_poll( $s, $sid ) : carmel_cb_slack_poll( $s, $sid );
}
