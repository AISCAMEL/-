<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 有人対応（担当者へのエスカレーション）。
 * - 営業時間は WordPress のタイムゾーン設定に従って判定。
 * - 通知: メール / Slack Webhook / LINE WORKS Bot（いずれも管理画面で設定）。
 * - 双方向: Slack Bot を設定すると、営業時間内は「担当者がSlackで返信→チャットに表示」。
 *   未設定・時間外は通知＋フォーム（連絡先を残してもらう）にフォールバック。
 * すべての認証情報はWP管理画面（基本設定）から入力する。
 */

/* ============================ 営業時間 ============================ */

function carmel_cb_within_hours( $s = null ) {
	if ( ! $s ) { $s = carmel_cb_get_settings(); }
	$hour = (int) current_time( 'G' ) + ( (int) current_time( 'i' ) ) / 60; // WPローカル時刻
	$dow  = (int) current_time( 'w' );
	$days = array_filter( array_map( 'intval', explode( ',', (string) $s['biz_days'] ) ), function ( $d ) { return $d >= 0 && $d <= 6; } );
	if ( ! in_array( $dow, $days, true ) ) { return false; }
	return ( $hour >= (float) $s['biz_start'] && $hour < (float) $s['biz_end'] );
}

function carmel_cb_transcript( $history ) {
	$lines = array();
	$history = is_array( $history ) ? array_slice( $history, -10 ) : array();
	foreach ( $history as $m ) {
		if ( ! isset( $m['role'], $m['content'] ) ) { continue; }
		$who = ( $m['role'] === 'user' ) ? 'お客様' : 'AI';
		$lines[] = $who . ': ' . wp_strip_all_tags( (string) $m['content'] );
	}
	return implode( "\n", $lines );
}

/* ============================ 通知（メール / Webhook / LINE WORKS） ============================ */

function carmel_cb_handoff_notify( $s, $p ) {
	$to = ! empty( $s['notify_email'] ) ? $s['notify_email'] : get_option( 'admin_email' );

	$status_label = $p['within'] ? '【営業時間内】至急ご対応ください' : '【時間外】翌営業日にご連絡ください';
	$subject = '[カーメル相談AI] 担当者希望のお客様 ' . $status_label;
	$body  = "お客様から担当者対応のご希望がありました。\n" . $status_label . "\n\n";
	$body .= "お名前　 : " . $p['name'] . "\n";
	$body .= "ご連絡先 : " . $p['contact'] . "\n";
	$body .= "ご相談　 : " . $p['note'] . "\n";
	$body .= "受付日時 : " . current_time( 'Y-m-d H:i' ) . "\n";
	$body .= "ページ　 : " . $p['page'] . "\n\n";
	$body .= "--- 直近の会話 ---\n" . $p['transcript'] . "\n";

	wp_mail( $to, $subject, $body );

	carmel_cb_slack_notify_text( $s, "*{$subject}*\n```{$body}```" );

	carmel_cb_lineworks_notify( $s, $subject . "\n\n" . $body );
}

/* ---- LINE WORKS Bot（API 2.0 / JWT） ---- */

function carmel_cb_b64url( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

/** サービスアカウントのJWTでアクセストークンを取得（50分キャッシュ）。 */
function carmel_cb_lineworks_token( $s ) {
	if ( empty( $s['lw_client_id'] ) || empty( $s['lw_client_secret'] ) || empty( $s['lw_service_account'] ) || empty( $s['lw_private_key'] ) ) {
		return '';
	}
	$cached = get_transient( 'carmel_cb_lw_token' );
	if ( $cached ) { return $cached; }
	if ( ! function_exists( 'openssl_sign' ) ) { return ''; }

	$now    = time();
	$header = carmel_cb_b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claim  = carmel_cb_b64url( wp_json_encode( array(
		'iss' => $s['lw_client_id'],
		'sub' => $s['lw_service_account'],
		'iat' => $now,
		'exp' => $now + 3600,
	) ) );
	$sig = '';
	if ( ! openssl_sign( $header . '.' . $claim, $sig, $s['lw_private_key'], 'SHA256' ) ) { return ''; }
	$jwt = $header . '.' . $claim . '.' . carmel_cb_b64url( $sig );

	$res = wp_remote_post( 'https://auth.worksmobile.com/oauth2/v2.0/token', array(
		'timeout' => 20,
		'body'    => array(
			'assertion'     => $jwt,
			'grant_type'    => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'client_id'     => $s['lw_client_id'],
			'client_secret' => $s['lw_client_secret'],
			'scope'         => 'bot',
		),
	) );
	if ( is_wp_error( $res ) ) { return ''; }
	$d = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $d['access_token'] ) ) { return ''; }
	set_transient( 'carmel_cb_lw_token', $d['access_token'], 50 * MINUTE_IN_SECONDS );
	return $d['access_token'];
}

/** LINE WORKS のトーク（チャンネル）へテキスト通知。 */
function carmel_cb_lineworks_notify( $s, $text ) {
	if ( empty( $s['lw_bot_id'] ) || empty( $s['lw_channel_id'] ) ) { return; }
	$token = carmel_cb_lineworks_token( $s );
	if ( ! $token ) { return; }
	wp_remote_post(
		'https://www.worksapis.com/v1.0/bots/' . rawurlencode( $s['lw_bot_id'] ) . '/channels/' . rawurlencode( $s['lw_channel_id'] ) . '/messages',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'content' => array( 'type' => 'text', 'text' => mb_substr( $text, 0, 1900 ) ) ) ),
		)
	);
}

/* ============================ Slack 双方向（ライブ） ============================ */

function carmel_cb_slack_base() {
	return defined( 'CARMEL_CB_SLACK_BASE' ) ? CARMEL_CB_SLACK_BASE : 'https://slack.com/api';
}
function carmel_cb_slack_live_on( $s ) {
	return ! empty( $s['slack_bot_token'] ) && ! empty( $s['slack_channel'] );
}
/* ============================ Slack ヘルスチェック（毎日・トークン失効を検知して通知） ============================ */

add_action( 'init', 'carmel_cb_schedule_slack_healthcheck' );
function carmel_cb_schedule_slack_healthcheck() {
	if ( ! wp_next_scheduled( 'carmel_cb_slack_healthcheck' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'carmel_cb_slack_healthcheck' );
	}
}

add_action( 'carmel_cb_slack_healthcheck', 'carmel_cb_run_slack_healthcheck' );
function carmel_cb_run_slack_healthcheck() {
	$s = carmel_cb_get_settings();
	if ( empty( $s['slack_bot_token'] ) ) { return; }

	$res = carmel_cb_slack_api( 'auth.test', $s['slack_bot_token'], array(), false );

	if ( ! empty( $res['ok'] ) ) {
		// 正常：復旧フラグを消し、最終確認時刻を記録
		delete_transient( 'carmel_cb_slack_alerted' );
		update_option( 'carmel_cb_slack_last_ok', current_time( 'mysql' ) );
		return;
	}

	// 異常：多重通知を避けつつ管理者へ警告（1日1回まで）
	if ( get_transient( 'carmel_cb_slack_alerted' ) ) { return; }
	set_transient( 'carmel_cb_slack_alerted', 1, DAY_IN_SECONDS );

	$err     = isset( $res['error'] ) ? $res['error'] : 'unknown';
	$to      = ! empty( $s['notify_email'] ) ? $s['notify_email'] : get_option( 'admin_email' );
	$subject = '[カーメル相談AI] ⚠️ Slack連携が無効になっています';
	$body    = "Slackの担当者チャット連携が使えない状態です（エラー：{$err}）。\n\n"
		. "このままだと、お客様が「担当者に相談」を押しても、Slackに繋がらず連絡先フォームに切り替わります。\n\n"
		. "【対処】\n"
		. "1) https://api.slack.com/apps → 対象アプリ → OAuth & Permissions\n"
		. "2) Reinstall to Workspace（再インストール）\n"
		. "3) 新しい xoxb- トークンをコピー\n"
		. "4) チャットボット → 基本設定 → Slack Botトークン に貼り直して保存\n"
		. "5) 「Slack接続をテスト」で成功を確認\n";

	wp_mail( $to, $subject, $body );

	// Slack Bot自体はダメなので、別資格情報のWebhook / LINE WORKS があればそちらへも
	if ( ! empty( $s['slack_webhook'] ) ) {
		wp_remote_post( $s['slack_webhook'], array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'text' => "⚠️ {$subject}\n```{$body}```" ) ),
		) );
	}
	if ( function_exists( 'carmel_cb_lineworks_notify' ) ) {
		carmel_cb_lineworks_notify( $s, $subject . "\n\n" . $body );
	}
}

function carmel_cb_slack_api( $method, $token, $args, $post = true ) {
	$url = carmel_cb_slack_base() . '/' . $method;
	if ( $post ) {
		$res = wp_remote_post( $url, array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( $args ),
		) );
	} else {
		$res = wp_remote_get( $url . '?' . http_build_query( $args ), array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );
	}
	if ( is_wp_error( $res ) ) { return array( 'ok' => false, 'error' => $res->get_error_message() ); }
	$j = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $j ) ? $j : array( 'ok' => false, 'error' => 'bad json' );
}

function carmel_cb_ho_key( $sid ) { return 'carmel_cb_ho_' . md5( (string) $sid ); }

/**
 * Slackへテキスト通知（フォーム・担当者希望など）。
 * Bot(双方向)が設定済みなら そのチャンネルへ chat.postMessage。
 * 無ければ Incoming Webhook（一方向）を使う。両方の設定を1か所にまとめる。
 */
function carmel_cb_slack_notify_text( $s, $text ) {
	if ( ! empty( $s['slack_bot_token'] ) && ! empty( $s['slack_channel'] ) ) {
		return carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array(
			'channel' => $s['slack_channel'],
			'text'    => $text,
		) );
	}
	if ( ! empty( $s['slack_webhook'] ) ) {
		$r = wp_remote_post( $s['slack_webhook'], array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'text' => $text ) ),
		) );
		return array( 'ok' => ! is_wp_error( $r ), 'error' => is_wp_error( $r ) ? $r->get_error_message() : '' );
	}
	return array( 'ok' => false, 'error' => 'Slack未設定' );
}

/** Slackエラーコードを日本語の対処ヒントに変換。 */
function carmel_cb_slack_error_hint( $err ) {
	$map = array(
		'not_in_channel'    => 'Botがそのチャンネルに参加していません。Slackで対象チャンネルを開き「/invite @Bot名」でBotを招待してください。',
		'channel_not_found' => 'チャンネルIDが違います。チャンネル名をクリック→一番下の「チャンネルID（Cで始まる）」を正しく貼ってください。',
		'invalid_auth'      => 'トークンが無効です。OAuth & Permissions で再インストールし、新しい xoxb- トークンを貼り直してください。',
		'not_authed'        => 'トークンが未入力/無効です。xoxb- で始まるBotトークンを貼ってください。',
		'token_revoked'     => 'トークンが失効しています。アプリを再インストールして新しいトークンを取得してください。',
		'account_inactive'  => 'トークンのアカウントが無効です。アプリを再インストールしてください。',
		'missing_scope'     => '権限が不足しています。OAuth & Permissions の Bot Token Scopes に chat:write を追加し、再インストールしてください。',
		'is_archived'       => 'そのチャンネルはアーカイブ済みです。別のチャンネルをご利用ください。',
		'restricted_action' => 'ワークスペースの制限でBot投稿がブロックされています。管理者にご確認ください。',
	);
	return isset( $map[ $err ] ) ? $map[ $err ] : 'Slack設定（トークン・チャンネルID・Botの招待）をご確認ください。';
}

/** 管理画面の「Slack接続テスト」実行：実際にテスト投稿し、結果を通知で返す。 */
function carmel_cb_run_slack_test() {
	$s = carmel_cb_get_settings();
	if ( empty( $s['slack_bot_token'] ) || empty( $s['slack_channel'] ) ) {
		carmel_cb_notice( 'Slack接続テスト：BotトークンまたはチャンネルIDが未入力です。先に入力して「保存する」を押してください。', 'error' );
		return;
	}
	$res = carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array(
		'channel' => $s['slack_channel'],
		'text'    => '✅ カーメル：Slack接続テストです。この投稿が見えていれば、担当者チャットが使えます。',
	) );
	if ( empty( $res['ok'] ) ) {
		$err = isset( $res['error'] ) ? $res['error'] : '不明なエラー';
		carmel_cb_notice( 'Slack接続テスト：投稿に失敗（' . $err . '）。' . carmel_cb_slack_error_hint( $err ), 'error' );
		return;
	}

	// 返信を「読む」権限(channels:history/groups:history)も確認する。
	// これが無いと、担当者がSlackで返信してもチャットに戻せない。
	$read = carmel_cb_slack_api( 'conversations.replies', $s['slack_bot_token'], array(
		'channel' => isset( $res['channel'] ) ? $res['channel'] : $s['slack_channel'],
		'ts'      => $res['ts'],
		'limit'   => 1,
	), false );

	if ( ! empty( $read['ok'] ) ) {
		// files:read（担当者→お客様の画像受け取り）も確認
		$fl = carmel_cb_slack_api( 'files.list', $s['slack_bot_token'], array( 'count' => 1 ), false );
		if ( ! empty( $fl['ok'] ) ) {
			carmel_cb_notice( 'Slack接続テスト：成功しました！ 投稿・返信・ファイル受け取り(files:read)すべてOKです。担当者がSlackの「スレッドに返信」「画像添付」すると、お客様のチャットに表示されます。' );
		} else {
			$ferr = isset( $fl['error'] ) ? $fl['error'] : '不明';
			carmel_cb_notice( 'Slack接続テスト：テキストの往復はOKですが、担当者がSlackで送った画像をお客様に表示する権限が不足しています（' . $ferr . '）。OAuth & Permissions の Bot Token Scopes に files:read を追加 → 再インストール → 新しい xoxb- トークンを貼り直して保存してください。', 'warning' );
		}
	} else {
		$rerr = isset( $read['error'] ) ? $read['error'] : '不明なエラー';
		carmel_cb_notice(
			'Slack接続テスト：投稿はできましたが、返信の取得ができません（' . $rerr . '）。担当者の返信をチャットに戻すには、Slackアプリの権限(Bot Token Scopes)に channels:history（非公開チャンネルは groups:history）を追加 → アプリを再インストール → 新しい xoxb- トークンを貼り直して保存してください。',
			'error'
		);
	}
}

/** Slackに相談スレッドを作成してライブセッション開始。 */
function carmel_cb_slack_start( $s, $sid, $question ) {
	$text = "🆕 *【担当者希望】お客様がチャットでお待ちです*\n> " . mb_substr( (string) $question, 0, 500 )
		. "\n\n👉 *この投稿の「スレッド」に返信すると、お客様のチャットに届きます。*（チャンネルへの通常投稿・別の投稿への返信は届きません）";
	$res  = carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array( 'channel' => $s['slack_channel'], 'text' => $text ) );
	if ( empty( $res['ok'] ) ) { return false; }
	set_transient( carmel_cb_ho_key( $sid ), array(
		'channel' => $res['channel'],
		'thread'  => $res['ts'],
		'last'    => $res['ts'],
	), HOUR_IN_SECONDS );
	return true;
}

/** お客様メッセージをSlackスレッドへ中継。 */
function carmel_cb_slack_send( $s, $sid, $text ) {
	$sess = get_transient( carmel_cb_ho_key( $sid ) );
	if ( ! $sess ) { return false; }
	carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array(
		'channel'   => $sess['channel'],
		'thread_ts' => $sess['thread'],
		'text'      => '🙋 お客様: ' . mb_substr( (string) $text, 0, 1000 ),
	) );
	return true;
}

/** 担当者（人間）の新規返信を取得（テキスト＋添付ファイル）。 */
function carmel_cb_slack_poll( $s, $sid ) {
	$sess = get_transient( carmel_cb_ho_key( $sid ) );
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

		// 担当者がSlackに添付した画像・ファイルを、Bot権限でダウンロードして自サイトに保存し公開URL化
		if ( ! empty( $m['files'] ) && is_array( $m['files'] ) ) {
			foreach ( $m['files'] as $fm ) {
				$furl = ! empty( $fm['url_private_download'] ) ? $fm['url_private_download'] : ( $fm['url_private'] ?? '' );
				if ( ! $furl ) { continue; }
				$resp = wp_remote_get( $furl, array( 'timeout' => 30, 'redirection' => 5, 'headers' => array( 'Authorization' => 'Bearer ' . $s['slack_bot_token'] ) ) );
				if ( is_wp_error( $resp ) ) { error_log( 'Carmel CB file download error: ' . $resp->get_error_message() ); continue; }
				$code  = (int) wp_remote_retrieve_response_code( $resp );
				$ctype = (string) wp_remote_retrieve_header( $resp, 'content-type' );
				$body  = wp_remote_retrieve_body( $resp );
				if ( $code !== 200 || $body === '' || stripos( $ctype, 'text/html' ) !== false ) {
					error_log( 'Carmel CB file download rejected: code=' . $code . ' type=' . $ctype );
					continue;
				}
				$fname = ! empty( $fm['name'] ) ? $fm['name'] : ( 'file-' . ( $fm['id'] ?? '' ) );
				if ( ! preg_match( '/\.[a-z0-9]{2,5}$/i', $fname ) ) {
					$ext = carmel_cb_ext_from_ctype( $ctype );
					if ( $ext ) { $fname .= '.' . $ext; }
				}
				$saved = carmel_cb_store_upload_bits( $fname, $body );
				if ( $saved ) {
					$item['files'][] = array(
						'url'     => $saved['url'],
						'name'    => $saved['name'],
						'isImage' => carmel_cb_is_image_name( $saved['name'] ),
					);
				}
			}
		}

		$out[] = $item;
		$last  = $m['ts'];
	}
	if ( $out ) {
		$sess['last'] = $last;
		set_transient( carmel_cb_ho_key( $sid ), $sess, HOUR_IN_SECONDS );
	}
	return $out;
}

/* ============================ 添付ファイル（保存・アップロード） ============================ */

/** 画像拡張子か。 */
function carmel_cb_is_image_name( $name ) {
	return (bool) preg_match( '/\.(jpe?g|png|gif|webp)$/i', (string) $name );
}

/** Slackメッセージの files[] をBot権限でDLし、自サイトに保存して公開URL配列を返す。 */
function carmel_cb_slack_download_files( $s, $files ) {
	$out = array();
	if ( empty( $files ) || ! is_array( $files ) ) { return $out; }
	foreach ( $files as $fm ) {
		$furl = ! empty( $fm['url_private_download'] ) ? $fm['url_private_download'] : ( $fm['url_private'] ?? '' );
		if ( ! $furl ) { continue; }
		$resp = wp_remote_get( $furl, array( 'timeout' => 30, 'redirection' => 5, 'headers' => array( 'Authorization' => 'Bearer ' . $s['slack_bot_token'] ) ) );
		if ( is_wp_error( $resp ) ) { continue; }
		$code  = (int) wp_remote_retrieve_response_code( $resp );
		$ctype = (string) wp_remote_retrieve_header( $resp, 'content-type' );
		$body  = wp_remote_retrieve_body( $resp );
		if ( $code !== 200 || $body === '' || stripos( $ctype, 'text/html' ) !== false ) { continue; }
		$fname = ! empty( $fm['name'] ) ? $fm['name'] : ( 'file-' . ( $fm['id'] ?? '' ) );
		if ( ! preg_match( '/\.[a-z0-9]{2,5}$/i', $fname ) ) {
			$ext = carmel_cb_ext_from_ctype( $ctype );
			if ( $ext ) { $fname .= '.' . $ext; }
		}
		$saved = carmel_cb_store_upload_bits( $fname, $body );
		if ( $saved ) {
			$out[] = array( 'url' => $saved['url'], 'name' => $saved['name'], 'isImage' => carmel_cb_is_image_name( $saved['name'] ) );
		}
	}
	return $out;
}

/** content-type から拡張子を推定。 */
function carmel_cb_ext_from_ctype( $ctype ) {
	$ctype = strtolower( (string) $ctype );
	$map = array(
		'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
		'image/gif' => 'gif', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
	);
	foreach ( $map as $mime => $ext ) {
		if ( strpos( $ctype, $mime ) !== false ) { return $ext; }
	}
	return '';
}

/** バイナリを uploads/carmel-cb/ に保存し、公開URL等を返す。失敗なら null。 */
function carmel_cb_store_upload_bits( $name, $bits ) {
	$up  = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) { return null; }
	$dir = trailingslashit( $up['basedir'] ) . 'carmel-cb';
	if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }

	$name = sanitize_file_name( $name );
	if ( $name === '' ) { $name = 'file'; }
	$unique = wp_unique_filename( $dir, $name );
	$path   = trailingslashit( $dir ) . $unique;

	if ( false === file_put_contents( $path, $bits ) ) { return null; }
	return array(
		'path' => $path,
		'url'  => trailingslashit( $up['baseurl'] ) . 'carmel-cb/' . $unique,
		'name' => $name,
	);
}

/** お客様がチャットで送った画像/ファイルを保存し、Slackスレッドへ共有。 */
function carmel_cb_handle_handoff_upload( WP_REST_Request $request ) {
	$s   = carmel_cb_get_settings();
	$sid = sanitize_text_field( $request->get_param( 'session_id' ) );
	// 旧handoffスレッド or 会話ミラースレッドのどちらでも受け付ける
	$sess = $sid ? get_transient( carmel_cb_ho_key( $sid ) ) : false;
	if ( ! $sess && $sid && function_exists( 'carmel_cb_convo_key' ) ) { $sess = get_transient( carmel_cb_convo_key( $sid ) ); }
	if ( ! $sess ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'no_session' ), 200 ); }

	$files = $request->get_file_params();
	if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'no_file' ), 200 );
	}
	$f = $files['file'];
	if ( ! empty( $f['error'] ) || empty( $f['tmp_name'] ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'upload_error' ), 200 );
	}
	if ( (int) $f['size'] > 15 * 1024 * 1024 ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'too_big' ), 200 );
	}
	$name = (string) $f['name'];
	if ( ! preg_match( '/\.(jpe?g|png|gif|webp|pdf)$/i', $name ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_type' ), 200 );
	}

	$bits = file_get_contents( $f['tmp_name'] );
	if ( $bits === false ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'read_error' ), 200 ); }
	$saved = carmel_cb_store_upload_bits( $name, $bits );
	if ( ! $saved ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'save_error' ), 200 ); }

	// Slackスレッドへリンク共有（画像はSlackが自動プレビュー）
	if ( carmel_cb_slack_live_on( $s ) ) {
		carmel_cb_slack_api( 'chat.postMessage', $s['slack_bot_token'], array(
			'channel'   => $sess['channel'],
			'thread_ts' => $sess['thread'],
			'text'      => '🙋 お客様からの添付：' . $saved['name'] . "\n" . $saved['url'],
		) );
	}

	return new WP_REST_Response( array(
		'ok'      => true,
		'url'     => $saved['url'],
		'name'    => $saved['name'],
		'isImage' => carmel_cb_is_image_name( $saved['name'] ),
	), 200 );
}

/* ============================ REST ============================ */

add_action( 'rest_api_init', function () {
	$ns = 'carmel-cb/v1';
	register_rest_route( $ns, '/handoff',       array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_handoff',       'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/handoff/start', array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_handoff_start', 'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/handoff/send',  array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_handoff_send',  'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/handoff/poll',  array( 'methods' => 'GET',  'callback' => 'carmel_cb_handle_handoff_poll',  'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/handoff/upload', array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_handoff_upload', 'permission_callback' => '__return_true' ) );
} );

/** 通知フォーム送信（メール/Webhook/LINE WORKS）。 */
function carmel_cb_handle_handoff( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['handoff_enabled'] ) ) { return new WP_REST_Response( array( 'status' => 'disabled' ), 200 ); }

	$b = $request->get_json_params();
	$p = array(
		'name'       => sanitize_text_field( $b['name'] ?? '' ),
		'contact'    => sanitize_text_field( $b['contact'] ?? '' ),
		'note'       => sanitize_textarea_field( $b['note'] ?? '' ),
		'page'       => esc_url_raw( $b['page'] ?? '' ),
		'transcript' => carmel_cb_transcript( $b['messages'] ?? array() ),
		'within'     => carmel_cb_within_hours( $s ),
	);
	if ( $p['contact'] === '' && $p['name'] === '' ) {
		return new WP_REST_Response( array( 'status' => 'need_contact', 'within' => $p['within'] ), 200 );
	}
	carmel_cb_handoff_notify( $s, $p );
	return new WP_REST_Response( array( 'status' => $p['within'] ? 'notified' : 'offhours', 'within' => $p['within'] ), 200 );
}

/** ライブ開始判定：営業時間内＆Slack設定ありならライブ、なければ通知フォームへ。 */
function carmel_cb_handle_handoff_start( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['handoff_enabled'] ) ) { return new WP_REST_Response( array( 'mode' => 'disabled' ), 200 ); }

	$within = carmel_cb_within_hours( $s );
	if ( ! $within ) {
		return new WP_REST_Response( array(
			'mode' => 'offhours',
			'msg'  => (string) ( $s['handoff_offhours_msg'] ?? '' ),
		), 200 );
	}
	if ( ! carmel_cb_slack_live_on( $s ) ) { return new WP_REST_Response( array( 'mode' => 'notify' ), 200 ); }

	$b   = $request->get_json_params();
	$sid = sanitize_text_field( $b['session_id'] ?? wp_generate_uuid4() );
	$q   = sanitize_textarea_field( $b['question'] ?? '' );
	if ( ! carmel_cb_slack_start( $s, $sid, $q ) ) {
		return new WP_REST_Response( array( 'mode' => 'notify' ), 200 ); // Slack失敗時はフォームへ
	}
	$busy_sec = max( 3, min( 120, (int) ( $s['handoff_busy_sec'] ?? 15 ) ) );
	$wait_sec = max( $busy_sec + 2, min( 300, (int) ( $s['handoff_wait_sec'] ?? 45 ) ) );
	return new WP_REST_Response( array(
		'mode'       => 'live',
		'session_id' => $sid,
		'waitMsg'    => (string) ( $s['handoff_wait_msg'] ?? '担当者におつなぎしています。少々お待ちください…' ),
		'busyMs'     => $busy_sec * 1000,
		'busyMsg'    => (string) ( $s['handoff_busy_msg'] ?? '' ),
		'timeout'    => $wait_sec * 1000,
	), 200 );
}

function carmel_cb_handle_handoff_send( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	$b = $request->get_json_params();
	$sid  = sanitize_text_field( $b['session_id'] ?? '' );
	$text = sanitize_textarea_field( $b['text'] ?? '' );
	$okv  = carmel_cb_slack_live_on( $s ) ? carmel_cb_slack_send( $s, $sid, $text ) : false;
	return new WP_REST_Response( array( 'ok' => (bool) $okv ), 200 );
}

function carmel_cb_handle_handoff_poll( WP_REST_Request $request ) {
	$s   = carmel_cb_get_settings();
	$sid = sanitize_text_field( $request->get_param( 'session_id' ) );
	$msgs = carmel_cb_slack_live_on( $s ) ? carmel_cb_slack_poll( $s, $sid ) : array();
	return new WP_REST_Response( array( 'messages' => $msgs ), 200 );
}
