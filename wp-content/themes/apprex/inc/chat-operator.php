<?php
/**
 * オペレーター連携（Slack 双方向ブリッジ）。
 *
 * サイト内チャットの会話を Slack のスレッドにミラーし、担当者が Slack の
 * スレッドへ返信すると、その内容をお客様のチャット画面へ反映します。
 *
 * 仕組み：
 *  - お客様の発言 → Slack（chat.postMessage / スレッド）へ転送
 *  - 担当者が Slack スレッドで返信 → Events API でWPが受信 → セッションのキューへ
 *  - フロントが /chat/poll を数秒ごとに取得して担当者メッセージを表示
 *  - 担当者が一度返信するとそのセッションは「有人対応」になりAI自動応答を停止
 *    （Slackで「/ai」または「bot」と送ると自動応答に戻る）
 *
 * 設定：設定 > APPREX オペレーター（Bot Token / Channel ID / Signing Secret）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 設定値
 * ---------------------------------------------------------------------- */
function apprex_slack_bot_token() {
	if ( defined( 'APPREX_SLACK_BOT_TOKEN' ) && APPREX_SLACK_BOT_TOKEN ) {
		return (string) APPREX_SLACK_BOT_TOKEN;
	}
	return (string) get_option( 'apprex_slack_bot_token', '' );
}
function apprex_slack_channel() {
	return (string) get_option( 'apprex_slack_channel', '' );
}
function apprex_slack_signing_secret() {
	if ( defined( 'APPREX_SLACK_SIGNING_SECRET' ) && APPREX_SLACK_SIGNING_SECRET ) {
		return (string) APPREX_SLACK_SIGNING_SECRET;
	}
	return (string) get_option( 'apprex_slack_signing_secret', '' );
}

/** オペレーター連携が有効か（必要な設定が揃っているか）。 */
function apprex_chat_op_enabled() {
	return '' !== apprex_slack_bot_token() && '' !== apprex_slack_channel() && '' !== apprex_slack_signing_secret();
}

/* -------------------------------------------------------------------------
 * セッションストア（transient）
 * ---------------------------------------------------------------------- */
const APPREX_OP_TTL = 21600; // 6時間。

/** セッションキーを正規化。 */
function apprex_chat_op_key( $session ) {
	$session = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $session );
	return 'apprex_chat_op_' . $session;
}

/** セッション状態を取得。 */
function apprex_chat_op_get( $session ) {
	$data = get_transient( apprex_chat_op_key( $session ) );
	if ( ! is_array( $data ) ) {
		$data = array(
			'thread_ts' => '',
			'human'     => false,
			'requested' => false,
			'queue'     => array(),
			'seq'       => 0,
		);
	}
	return $data;
}

/** セッション状態を保存。 */
function apprex_chat_op_set( $session, $data ) {
	set_transient( apprex_chat_op_key( $session ), $data, APPREX_OP_TTL );
}

/** スレッド ts → セッション の逆引きを保存。 */
function apprex_chat_op_map_thread( $thread_ts, $session ) {
	set_transient( 'apprex_chat_th_' . md5( $thread_ts ), $session, APPREX_OP_TTL );
}
function apprex_chat_op_session_by_thread( $thread_ts ) {
	return (string) get_transient( 'apprex_chat_th_' . md5( $thread_ts ) );
}

/* -------------------------------------------------------------------------
 * Slack Web API
 * ---------------------------------------------------------------------- */

/**
 * Slack Web API を呼び出す。
 *
 * @param string $method   例: 'chat.postMessage'。
 * @param array  $args     パラメータ。
 * @param bool   $blocking レスポンスが必要なら true。
 * @return array|null      デコード済みレスポンス（非ブロッキング時 null）。
 */
function apprex_slack_api( $method, $args, $blocking = true ) {
	$token = apprex_slack_bot_token();
	if ( '' === $token ) {
		return null;
	}
	$res = wp_remote_post(
		'https://slack.com/api/' . $method,
		array(
			'timeout'  => $blocking ? 8 : 4,
			'blocking' => $blocking,
			'headers'  => array(
				'Content-Type'  => 'application/json; charset=utf-8',
				'Authorization' => 'Bearer ' . $token,
			),
			'body'     => wp_json_encode( $args ),
		)
	);
	if ( ! $blocking || is_wp_error( $res ) ) {
		return null;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	return is_array( $body ) ? $body : null;
}

/**
 * Slack スレッドへメッセージを投稿（必要なら最初にスレッド親を作成）。
 *
 * @param string $session セッションID。
 * @param string $text    本文。
 * @param array  $meta    親作成時の文脈（page / member 等）。
 * @return void
 */
function apprex_slack_post_to_thread( $session, $text, $meta = array() ) {
	$channel = apprex_slack_channel();
	if ( '' === $channel ) {
		return;
	}
	$data = apprex_chat_op_get( $session );

	// 親スレッドが無ければ作成（ブロッキングで ts を取得）。
	if ( '' === $data['thread_ts'] ) {
		$header = "🟢 *新しいチャット* （セッション " . substr( $session, 0, 10 ) . "）\n";
		if ( ! empty( $meta['page'] ) ) {
			$header .= "ページ: " . $meta['page'] . "\n";
		}
		if ( ! empty( $meta['member'] ) ) {
			$header .= "会員: " . $meta['member'] . "\n";
		}
		$header .= "↩️ このスレッドに返信するとお客様の画面に表示されます（`/ai` でAI応答に戻す）。";

		$root = apprex_slack_api( 'chat.postMessage', array( 'channel' => $channel, 'text' => $header ), true );
		if ( is_array( $root ) && ! empty( $root['ok'] ) && ! empty( $root['ts'] ) ) {
			$data['thread_ts'] = (string) $root['ts'];
			apprex_chat_op_map_thread( $data['thread_ts'], $session );
			apprex_chat_op_set( $session, $data );
		} else {
			return; // 親作成に失敗したらスレッド投稿は諦める。
		}
	}

	apprex_slack_api(
		'chat.postMessage',
		array( 'channel' => $channel, 'thread_ts' => $data['thread_ts'], 'text' => $text ),
		false
	);
}

/* -------------------------------------------------------------------------
 * 受信（チャット → Slack）。apprex_rest_chat から呼ばれる。
 * ---------------------------------------------------------------------- */

/**
 * 直近のお客様メッセージを Slack に転送し、AI応答とログ用の文脈を返す。
 *
 * @param string $session  セッションID。
 * @param array  $messages 正規化済み履歴。
 * @return bool 有人対応中なら true（呼び出し側はAI応答をスキップ）。
 */
function apprex_chat_op_ingest( $session, $messages ) {
	if ( ! apprex_chat_op_enabled() || '' === $session ) {
		return false;
	}
	// 直近のお客様発言。
	$last_user = '';
	for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
		if ( 'user' === $messages[ $i ]['role'] ) {
			$last_user = $messages[ $i ]['content'];
			break;
		}
	}
	if ( '' !== $last_user ) {
		$meta = array();
		$ref  = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		if ( $ref ) {
			$meta['page'] = $ref;
		}
		if ( function_exists( 'apprex_chat_member_info' ) ) {
			$mi = apprex_chat_member_info();
			if ( ! empty( $mi['loggedIn'] ) ) {
				$meta['member'] = ( isset( $mi['name'] ) ? $mi['name'] : '' ) . ' 様';
			}
		}
		apprex_slack_post_to_thread( $session, "👤 " . $last_user, $meta );
	}

	$data = apprex_chat_op_get( $session );
	return ! empty( $data['human'] );
}

/** AI応答もスレッドへ記録（担当者が文脈を把握できるように）。 */
function apprex_chat_op_log_ai_reply( $session, $reply ) {
	if ( ! apprex_chat_op_enabled() || '' === $session || '' === $reply ) {
		return;
	}
	$data = apprex_chat_op_get( $session );
	if ( '' === $data['thread_ts'] ) {
		return; // スレッド未作成なら記録しない。
	}
	apprex_slack_post_to_thread( $session, "🤖 " . $reply );
}

/* -------------------------------------------------------------------------
 * REST: お客様が「担当者に相談」
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/chat/operator',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_rest_chat_operator',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'apprex/v1',
		'/chat/poll',
		array(
			'methods'             => 'GET',
			'callback'            => 'apprex_rest_chat_poll',
			'permission_callback' => '__return_true',
		)
	);
	register_rest_route(
		'apprex/v1',
		'/slack/events',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_rest_slack_events',
			'permission_callback' => '__return_true',
		)
	);
} );

/** お客様が担当者対応を希望（Slackへ通知）。 */
function apprex_rest_chat_operator( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'forbidden', '不正なリクエストです。', array( 'status' => 403 ) );
	}
	if ( ! apprex_chat_op_enabled() ) {
		return rest_ensure_response( array( 'ok' => false, 'reason' => 'disabled' ) );
	}
	$session = sanitize_text_field( (string) $request->get_param( 'session' ) );
	if ( '' === $session ) {
		return new WP_Error( 'bad_request', 'セッションがありません。', array( 'status' => 400 ) );
	}

	$meta = array();
	$ref  = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	if ( $ref ) {
		$meta['page'] = $ref;
	}
	if ( function_exists( 'apprex_chat_member_info' ) ) {
		$mi = apprex_chat_member_info();
		if ( ! empty( $mi['loggedIn'] ) ) {
			$meta['member'] = ( isset( $mi['name'] ) ? $mi['name'] : '' ) . ' 様';
		}
	}
	apprex_slack_post_to_thread( $session, "🙋 *お客様が担当者との対応を希望しています* <!here>", $meta );

	$data              = apprex_chat_op_get( $session );
	$data['requested'] = true;
	apprex_chat_op_set( $session, $data );

	return rest_ensure_response( array( 'ok' => true ) );
}

/** フロントのポーリング：担当者メッセージと有人フラグを返す。 */
function apprex_rest_chat_poll( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'forbidden', '不正なリクエストです。', array( 'status' => 403 ) );
	}
	$session = sanitize_text_field( (string) $request->get_param( 'session' ) );
	$after   = (int) $request->get_param( 'after' );
	if ( '' === $session ) {
		return rest_ensure_response( array( 'messages' => array(), 'human' => false, 'cursor' => 0 ) );
	}
	$data = apprex_chat_op_get( $session );
	$out  = array();
	$max  = $after;
	foreach ( $data['queue'] as $m ) {
		if ( (int) $m['id'] > $after ) {
			$out[] = array( 'id' => (int) $m['id'], 'text' => (string) $m['text'], 'who' => (string) $m['who'] );
			if ( (int) $m['id'] > $max ) {
				$max = (int) $m['id'];
			}
		}
	}
	return rest_ensure_response(
		array(
			'messages' => $out,
			'human'    => ! empty( $data['human'] ),
			'cursor'   => $max,
		)
	);
}

/* -------------------------------------------------------------------------
 * REST: Slack Events API（担当者の返信を受信）
 * ---------------------------------------------------------------------- */
function apprex_rest_slack_events( WP_REST_Request $request ) {
	$raw  = $request->get_body();
	$json = json_decode( $raw, true );
	if ( ! is_array( $json ) ) {
		$json = $request->get_json_params(); // WP がパース済みの本文を復元。
	}

	// URL 検証チャレンジ（パース済みパラメータを優先＝最も確実）。
	$type = $request->get_param( 'type' );
	if ( ! $type && is_array( $json ) && isset( $json['type'] ) ) {
		$type = $json['type'];
	}
	if ( 'url_verification' === $type ) {
		$challenge = $request->get_param( 'challenge' );
		if ( ( null === $challenge || '' === $challenge ) && is_array( $json ) && isset( $json['challenge'] ) ) {
			$challenge = $json['challenge'];
		}
		return new WP_REST_Response( array( 'challenge' => (string) $challenge ), 200 );
	}

	// 署名検証。
	if ( ! apprex_slack_verify_signature( $request, $raw ) ) {
		return new WP_Error( 'forbidden', 'invalid signature', array( 'status' => 403 ) );
	}

	if ( ! is_array( $json ) ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}

	// リトライ重複の排除。
	$event_id = isset( $json['event_id'] ) ? sanitize_text_field( $json['event_id'] ) : '';
	if ( $event_id ) {
		$dk = 'apprex_slack_evt_' . md5( $event_id );
		if ( get_transient( $dk ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		set_transient( $dk, 1, 600 );
	}

	$event = isset( $json['event'] ) ? $json['event'] : array();
	if ( ! is_array( $event ) || 'message' !== ( $event['type'] ?? '' ) ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	// Bot 自身の発言・編集/削除などは無視（エコー防止）。
	if ( ! empty( $event['bot_id'] ) || isset( $event['subtype'] ) || empty( $event['user'] ) ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	// スレッド返信のみ対象（親メッセージそのものは除外）。
	$thread_ts = isset( $event['thread_ts'] ) ? (string) $event['thread_ts'] : '';
	if ( '' === $thread_ts || $thread_ts === ( $event['ts'] ?? '' ) ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	$session = apprex_chat_op_session_by_thread( $thread_ts );
	if ( '' === $session ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}

	$text = isset( $event['text'] ) ? trim( (string) $event['text'] ) : '';
	$data = apprex_chat_op_get( $session );

	// コマンド：AI応答へ戻す。
	$lc = strtolower( $text );
	if ( in_array( $lc, array( '/ai', '/bot', 'bot', 'ai' ), true ) || 'AI復帰' === $text || 'bot復帰' === $text ) {
		$data['human'] = false;
		$data['seq']   = (int) $data['seq'] + 1;
		$data['queue'][] = array( 'id' => $data['seq'], 'text' => 'AIによる自動応答に戻りました。', 'who' => 'system' );
	} else {
		if ( '' === $text ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}
		$data['human'] = true; // 担当者が応答 → 有人対応に切替。
		$data['seq']   = (int) $data['seq'] + 1;
		$data['queue'][] = array( 'id' => $data['seq'], 'text' => $text, 'who' => 'operator' );
	}
	// キューは直近50件に制限。
	if ( count( $data['queue'] ) > 50 ) {
		$data['queue'] = array_slice( $data['queue'], -50 );
	}
	apprex_chat_op_set( $session, $data );

	return rest_ensure_response( array( 'ok' => true ) );
}

/** Slack の署名（v0）を検証。 */
function apprex_slack_verify_signature( WP_REST_Request $request, $raw ) {
	$secret = apprex_slack_signing_secret();
	if ( '' === $secret ) {
		return false;
	}
	$ts  = $request->get_header( 'x_slack_request_timestamp' );
	$sig = $request->get_header( 'x_slack_signature' );
	if ( ! $ts || ! $sig ) {
		return false;
	}
	if ( abs( time() - (int) $ts ) > 300 ) {
		return false; // リプレイ防止（5分）。
	}
	$computed = 'v0=' . hash_hmac( 'sha256', 'v0:' . $ts . ':' . $raw, $secret );
	return hash_equals( $computed, $sig );
}

/* -------------------------------------------------------------------------
 * 設定ページ：設定 > APPREX オペレーター
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX オペレーター連携', 'APPREX オペレーター', 'manage_options', 'apprex-operator', 'apprex_operator_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_operator', 'apprex_slack_bot_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_operator', 'apprex_slack_channel', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_operator', 'apprex_slack_signing_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
} );

function apprex_operator_settings_page() {
	$events_url = rest_url( 'apprex/v1/slack/events' );
	?>
	<div class="wrap">
		<h1>APPREX オペレーター連携（Slack）</h1>
		<p>サイト内チャットを Slack に転送し、担当者が Slack スレッドに返信するとお客様の画面に反映されます。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_operator' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="apprex_slack_bot_token">Bot User OAuth Token</label></th>
					<td>
						<?php if ( defined( 'APPREX_SLACK_BOT_TOKEN' ) && APPREX_SLACK_BOT_TOKEN ) : ?>
							<p><em>wp-config.php の APPREX_SLACK_BOT_TOKEN で設定済みです。</em></p>
						<?php else : ?>
							<input type="password" id="apprex_slack_bot_token" name="apprex_slack_bot_token" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_slack_bot_token', '' ) ); ?>" autocomplete="off" placeholder="xoxb-…">
						<?php endif; ?>
						<p class="description">Slack アプリの「OAuth &amp; Permissions」で発行（<code>chat:write</code> 権限が必要）。Bot を対象チャンネルに招待してください。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_slack_channel">Channel ID</label></th>
					<td>
						<input type="text" id="apprex_slack_channel" name="apprex_slack_channel" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_slack_channel', '' ) ); ?>" placeholder="C0123456789">
						<p class="description">転送先チャンネルのID（チャンネル名を右クリック →「リンクをコピー」の末尾、または詳細画面の下部に表示）。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_slack_signing_secret">Signing Secret</label></th>
					<td>
						<?php if ( defined( 'APPREX_SLACK_SIGNING_SECRET' ) && APPREX_SLACK_SIGNING_SECRET ) : ?>
							<p><em>wp-config.php の APPREX_SLACK_SIGNING_SECRET で設定済みです。</em></p>
						<?php else : ?>
							<input type="password" id="apprex_slack_signing_secret" name="apprex_slack_signing_secret" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_slack_signing_secret', '' ) ); ?>" autocomplete="off">
						<?php endif; ?>
						<p class="description">Slack アプリの「Basic Information」→「App Credentials」の Signing Secret。</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>Slack アプリ側の設定</h2>
		<ol>
			<li>api.slack.com/apps でアプリを作成 → <strong>OAuth &amp; Permissions</strong> で <code>chat:write</code> を付与しワークスペースにインストール。</li>
			<li>Bot を転送先チャンネルに招待（<code>/invite @アプリ名</code>）。</li>
			<li><strong>Event Subscriptions</strong> を ON にして Request URL に以下を設定：<br>
				<code><?php echo esc_html( $events_url ); ?></code></li>
			<li>「Subscribe to bot events」で <code>message.channels</code>（公開チャンネル）または <code>message.groups</code>（非公開）を追加して再インストール。</li>
		</ol>
		<p>現在の状態：<strong><?php echo apprex_chat_op_enabled() ? '✅ 有効' : '⛔ 未設定（3項目すべて入力で有効になります）'; ?></strong></p>
	</div>
	<?php
}
