<?php
/**
 * 社内への通知をまとめる（Slack・LINE WORKS）。
 *
 * 記事の公開、お問い合わせ・予約、チャット・LINEのやりとり、お客様の行動、請求・お支払い、契約を、
 * 種類ごとに Slack と LINE WORKS のどちらに送るか選べる。
 *
 * - Slack：Incoming Webhook（これまでの「APPREX 連携」の Slack 通知URL と同じもの）。
 * - LINE WORKS：トークルームの「Incoming Webhook」アプリで発行したURL（https://webhook.worksmobile.com/message/…）。
 *   受け取り専用のため、LINE WORKS からお客様へ返信はできない（返信は Slack のチャット連携・LINE公式アカウントで）。
 *
 * これまでの apprex_slack_notify( $text, $blocks, $cat ) はここを通る。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 通知の種類。key => [名前, 説明]。 */
function apprex_nh_cats() {
	return array(
		'post'     => array( '記事の公開', 'ブログ記事が公開されたとき（毎日の自動投稿を含む）。自動投稿に失敗したときも。' ),
		'inquiry'  => array( 'お問い合わせ・予約', 'お問い合わせ・無料相談の予約・資料請求・説明会の申し込みがあったとき。' ),
		'chat'     => array( 'チャット・LINEのやりとり', 'サイトのチャットやLINEで、お客様がメッセージを送ったとき。担当者を呼ばれたとき・LINEで依頼を受けたとき。' ),
		'action'   => array( 'お客様の行動', 'LINEの友だち追加・ブロック、LABOでアプリ画面を作った、契約書に署名した、など。' ),
		'billing'  => array( '請求・お支払い', '請求書の送信・失敗、入金の確認、お支払いの遅れのお知らせを送ったとき。' ),
		'contract' => array( '契約', '契約の更新・解約の申し込み・契約期限。' ),
		'report'   => array( '成果のまとめ', '毎週月曜に、先週のSNSの成果（クリック・フォロワー・LINEに来た人・予約）をまとめて送ります。' ),
		'other'    => array( 'そのほか', '上のどれにも入らないお知らせ。' ),
	);
}

/** 送り先の設定（種類ごとに s=Slack, l=LINE WORKS）。最初はすべてオン。 */
function apprex_nh_route() {
	$r   = get_option( 'apprex_nh_route', null );
	$out = array();
	foreach ( array_keys( apprex_nh_cats() ) as $k ) {
		$out[ $k ] = array(
			's' => is_array( $r ) ? ! empty( $r[ $k ]['s'] ) : true,
			'l' => is_array( $r ) ? ! empty( $r[ $k ]['l'] ) : true,
		);
	}
	return $out;
}

/** その種類を、その送り先に送るか。 */
function apprex_nh_on( $cat, $to ) {
	$r = apprex_nh_route();
	$c = isset( $r[ $cat ] ) ? $cat : 'other';
	return ! empty( $r[ $c ][ $to ] );
}

/** LINE WORKS の Incoming Webhook URL。 */
function apprex_lw_webhook_url() {
	return trim( (string) get_option( 'apprex_lw_webhook', '' ) );
}

/** Slack の書き方（*太字*・<URL|文字>・:絵文字:）を、ふつうの文字にする（LINE WORKS用）。 */
function apprex_nh_plain( $text ) {
	$emoji = array(
		':x:'                 => '❌',
		':white_check_mark:'  => '✅',
		':warning:'           => '⚠️',
		':rotating_light:'    => '🚨',
		':hourglass:'         => '⏳',
		':money_with_wings:'  => '💸',
		':speech_balloon:'    => '💬',
		':no_entry:'          => '⛔',
		':fire:'              => '🔥',
		':moneybag:'          => '💰',
		':bust_in_silhouette:' => '👤',
		':tada:'              => '🎉',
	);
	$t = strtr( (string) $text, $emoji );
	$t = preg_replace( '/<!(here|channel|everyone)>/', '', $t );
	$t = preg_replace( '/<(https?:[^|>]+)\|([^>]+)>/', "$2\n$1", $t );
	$t = preg_replace( '/<(https?:[^>]+)>/', '$1', $t );
	$t = preg_replace( '/\*([^*\n]+)\*/u', '$1', $t );
	$t = str_replace( '`', '', $t );
	return trim( $t );
}

/**
 * LINE WORKS に送る（ノンブロッキング）。
 *
 * @param string $text 本文（ふつうの文字）。
 * @param bool   $wait 結果を待つ（テスト送信用）。
 * @return bool|WP_Error
 */
function apprex_lw_send( $text, $wait = false ) {
	$url = apprex_lw_webhook_url();
	if ( '' === $url ) {
		return false;
	}
	$res = wp_remote_post(
		$url,
		array(
			'timeout'  => $wait ? 10 : 5,
			'blocking' => $wait,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( array( 'body' => array( 'text' => mb_substr( (string) $text, 0, 2000 ) ) ) ),
		)
	);
	if ( ! $wait ) {
		return true;
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'lw_http', 'LINE WORKS から ' . $code . ' が返りました：' . mb_substr( wp_strip_all_tags( (string) wp_remote_retrieve_body( $res ) ), 0, 200 ) );
	}
	return true;
}

/** Slack に送る（Incoming Webhook・ノンブロッキング）。 */
function apprex_slack_send_raw( $text, $blocks = array(), $wait = false ) {
	$url = function_exists( 'apprex_slack_webhook_url' ) ? apprex_slack_webhook_url() : '';
	if ( '' === $url ) {
		return false;
	}
	$body = array( 'text' => $text );
	if ( ! empty( $blocks ) ) {
		$body['blocks'] = $blocks;
	}
	$res = wp_remote_post(
		$url,
		array(
			'timeout'  => 8,
			'blocking' => $wait,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
		)
	);
	if ( ! $wait ) {
		return true;
	}
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	return ( $code >= 200 && $code < 300 ) ? true : new WP_Error( 'slack_http', 'Slack から ' . $code . ' が返りました：' . mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 200 ) );
}

/**
 * 社内に通知する（種類ごとの設定に合わせて Slack・LINE WORKS へ）。
 *
 * @param string $cat    種類（apprex_nh_cats のキー）。
 * @param string $text   本文（Slack の書き方でよい）。
 * @param array  $blocks Slack 用のブロック（任意）。
 * @param array  $opt    no_slack => true で Slack に送らない（Slack側で別に届いている時）。
 * @return bool どこかに送ったか。
 */
function apprex_notify( $cat, $text, $blocks = array(), $opt = array() ) {
	$sent = false;
	if ( apprex_nh_on( $cat, 's' ) && empty( $opt['no_slack'] ) ) {
		$sent = apprex_slack_send_raw( $text, $blocks ) || $sent;
	}
	if ( apprex_nh_on( $cat, 'l' ) && empty( $opt['no_lw'] ) ) {
		$sent = apprex_lw_send( apprex_nh_plain( $text ) ) || $sent;
	}
	if ( $sent ) {
		$log   = get_option( 'apprex_nh_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( time(), $cat, mb_substr( apprex_nh_plain( $text ), 0, 120 ) );
		update_option( 'apprex_nh_log', array_slice( $log, -40 ), false );
	}
	return $sent;
}

/** 管理画面のURL（通知に入れる）。 */
function apprex_nh_edit( $id ) {
	return admin_url( 'post.php?post=' . (int) $id . '&action=edit' );
}

/* =========================================================================
 * お問い合わせ・予約（すべて。見込みの高いものは🔥）
 * ====================================================================== */
add_action(
	'init',
	function () {
		// これまでの「見込みの高い問い合わせだけ Slack」は、ここでまとめて送るので止める。
		remove_action( 'apprex_inquiry_submitted', 'apprex_hotlead_notify', 20 );
	}
);
add_action(
	'apprex_inquiry_submitted',
	function ( $post_id, $type, $fields = array() ) {
		$f     = function ( $k ) use ( $fields ) {
			return isset( $fields[ $k ] ) ? trim( (string) $fields[ $k ] ) : '';
		};
		$label = function_exists( 'apprex_type_label' ) ? apprex_type_label( $type ) : $type;
		$score = function_exists( 'apprex_lead_score' ) ? (int) apprex_lead_score( $type, $f( 'phone' ), $f( 'company' ), $f( 'message' ), 0 ) : 0;
		$hot   = $score >= (int) get_option( 'apprex_hotlead_threshold', 60 );
		$lines = array( ( $hot ? ':fire: ' : '📩 ' ) . '*' . $label . '*' . ( $hot ? '（見込み高め ' . $score . '/100）' : '' ) );
		$lines[] = $f( 'name' ) . ( $f( 'company' ) ? '（' . $f( 'company' ) . '）' : '' ) . ' 様';
		if ( $f( 'meeting_at' ) ) {
			$lines[] = '🗓 ' . $f( 'meeting_at' );
		}
		$lines[] = ( $f( 'email' ) ? $f( 'email' ) : 'メールなし' ) . ( $f( 'phone' ) ? ' ／ ☎ ' . $f( 'phone' ) : '' );
		if ( $f( 'message' ) ) {
			$lines[] = '「' . mb_substr( wp_strip_all_tags( $f( 'message' ) ), 0, 120 ) . '」';
		}
		$lines[] = apprex_nh_edit( $post_id );
		apprex_notify( 'inquiry', implode( "\n", $lines ) );
	},
	30,
	3
);

/* =========================================================================
 * チャット（サイト）・LINE のやりとり
 * ====================================================================== */

/** LINE WORKS の個別ルームを使っているか。 */
function apprex_nh_lw_rooms() {
	return function_exists( 'apprex_lwb_ready' ) && apprex_lwb_ready();
}

/** 1つの会話から送る数の上限（いたずら対策）。 */
function apprex_nh_chat_ok( $key ) {
	$k = 'apprex_nh_c_' . md5( $key );
	$n = (int) get_transient( $k );
	if ( $n >= 30 ) {
		return false;
	}
	set_transient( $k, $n + 1, DAY_IN_SECONDS );
	return true;
}

/* サイトのチャット：お客様のメッセージ（openrouter-chat.php から）。 */
add_action(
	'apprex_chat_user_message',
	function ( $session, $text, $count ) {
		if ( '' === trim( (string) $text ) || ! apprex_nh_chat_ok( 'web' . $session ) ) {
			return;
		}
		$who = '';
		if ( is_user_logged_in() ) {
			$u   = wp_get_current_user();
			$who = '（会員：' . $u->display_name . ' 様）';
		}
		$head = 1 === (int) $count ? '💬 *サイトのチャット：新しい会話*' : '💬 サイトのチャット';
		// 有人対応の Slack 連携が動いていれば、Slack には別のスレッドで届いているので重ねない。
		$op = function_exists( 'apprex_chat_op_slack_enabled' ) && apprex_chat_op_slack_enabled();
		apprex_notify(
			'chat',
			$head . $who . "\n「" . mb_substr( (string) $text, 0, 300 ) . "」\n会話ID " . substr( (string) $session, 0, 10 ) . "\n" . admin_url( 'edit.php?post_type=apprex_chatlog' ),
			array(),
			array( 'no_slack' => $op, 'no_lw' => apprex_nh_lw_rooms() )
		);
	},
	10,
	3
);

/** LINE のお客様の名前（会員ならその名前、ちがえばLINEの表示名）。 */
function apprex_nh_line_name( $uid ) {
	$ck = 'apprex_nh_ln_' . md5( (string) $uid );
	$c  = get_transient( $ck );
	if ( is_string( $c ) && '' !== $c ) {
		return $c;
	}
	$n = apprex_nh_line_name_get( $uid );
	set_transient( $ck, $n, false === strpos( $n, 'LINEのお客様（' ) ? DAY_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
	return $n;
}

/** LINE のお客様の名前を調べる（apprex_nh_line_name から）。 */
function apprex_nh_line_name_get( $uid ) {
	if ( function_exists( 'apprex_line_member' ) ) {
		$u = apprex_line_member( $uid );
		if ( $u ) {
			return $u->display_name . ' 様（会員）';
		}
	}
	$fid = function_exists( 'apprex_line_friend_get' ) ? apprex_line_friend_get( $uid, false ) : 0;
	if ( $fid ) {
		$n = (string) get_post_meta( $fid, 'apprex_line_disp', true );
		if ( '' !== $n ) {
			return $n . ' さん';
		}
	}
	$tok = function_exists( 'apprex_line_channel_token' ) ? apprex_line_channel_token() : '';
	if ( '' !== $tok ) {
		$r = wp_remote_get( 'https://api.line.me/v2/bot/profile/' . rawurlencode( $uid ), array( 'timeout' => 4, 'headers' => array( 'Authorization' => 'Bearer ' . $tok ) ) );
		if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
			$b = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			if ( ! empty( $b['displayName'] ) ) {
				if ( $fid ) {
					update_post_meta( $fid, 'apprex_line_disp', sanitize_text_field( $b['displayName'] ) );
				}
				return sanitize_text_field( $b['displayName'] ) . ' さん';
			}
		}
	}
	return 'LINEのお客様（' . substr( $uid, 0, 8 ) . '…）';
}

/* LINE：メッセージ・友だち追加・ブロック。 */
add_action(
	'apprex_line_event',
	function ( $ev, $uid, $type ) {
		if ( 'message' === $type ) {
			$m = isset( $ev['message'] ) ? $ev['message'] : array();
			$t = isset( $m['type'] ) ? $m['type'] : '';
			if ( 'text' === $t ) {
				$body = (string) $m['text'];
			} else {
				$body = array( 'image' => '（画像）', 'sticker' => '（スタンプ）', 'video' => '（動画）', 'audio' => '（音声）', 'file' => '（ファイル）', 'location' => '（位置情報）' )[ $t ] ?? '（' . $t . '）';
			}
			if ( ! apprex_nh_chat_ok( 'line' . $uid ) ) {
				return;
			}
			// 個別ルームを使うときは、LINE WORKS にはお客様ごとのルームに届くので、通知のルームには送らない。
			apprex_notify( 'chat', '🟩 *LINE：' . apprex_nh_line_name( $uid ) . "*\n「" . mb_substr( $body, 0, 300 ) . '」', array(), array( 'no_lw' => apprex_nh_lw_rooms() ) );
		} elseif ( 'follow' === $type ) {
			apprex_notify( 'action', '🟩 LINEの友だちが増えました：' . apprex_nh_line_name( $uid ) );
		} elseif ( 'unfollow' === $type ) {
			apprex_notify( 'action', ':no_entry: LINEをブロックされました：' . apprex_nh_line_name( $uid ) );
		}
	},
	5,
	3
);

/* =========================================================================
 * お客様の行動
 * ====================================================================== */

/* LABO：アプリ画面を作った（1人1回目だけ）。 */
add_action(
	'apprex_labo_logged',
	function ( $uid, $type, $data ) {
		if ( 'make' !== $type ) {
			return;
		}
		$k = 'apprex_nh_make_' . md5( $uid );
		if ( get_transient( $k ) ) {
			return;
		}
		set_transient( $k, 1, DAY_IN_SECONDS );
		apprex_notify( 'action', '✨ LABOでアプリ画面を作りました：' . apprex_nh_line_name( $uid ) . ( '' !== $data ? "\n" . $data : '' ) );
	},
	10,
	3
);

/* 契約書の署名が終わった。 */
add_action(
	'updated_post_meta',
	function ( $mid, $post_id, $key, $value ) {
		if ( 'apprex_c_mf_status' === $key && 'signed' === $value ) {
			apprex_notify( 'action', ':tada: 契約書に署名がありました：' . get_post_meta( $post_id, 'apprex_c_name', true ) . "\n" . apprex_nh_edit( $post_id ) );
		}
	},
	10,
	4
);

/* =========================================================================
 * 請求・お支払い：入金の確認（自動の消し込み）
 * ====================================================================== */
add_action(
	'apprex_payment_confirmed',
	function ( $id, $via ) {
		$k = 'apprex_nh_paid_' . $id . '_' . wp_date( 'Ym' ) . $via;
		if ( get_transient( $k ) ) {
			return;
		}
		set_transient( $k, 1, 40 * DAY_IN_SECONDS );
		apprex_notify( 'billing', ':moneybag: 入金を確認しました（' . $via . '）：' . get_post_meta( $id, 'apprex_c_name', true ) . "\n" . apprex_nh_edit( $id ) );
	},
	10,
	2
);

/* =========================================================================
 * 設定画面：設定 → 社内への通知
 * ====================================================================== */
add_action(
	'admin_init',
	function () {
		register_setting( 'apprex_notify', 'apprex_slack_webhook', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting(
			'apprex_notify',
			'apprex_lw_webhook',
			array(
				'sanitize_callback' => function ( $v ) {
					return esc_url_raw( trim( (string) $v ) );
				},
			)
		);
		register_setting(
			'apprex_notify',
			'apprex_nh_route',
			array(
				'sanitize_callback' => function ( $v ) {
					$out = array();
					foreach ( array_keys( apprex_nh_cats() ) as $k ) {
						$out[ $k ] = array(
							's' => ! empty( $v[ $k ]['s'] ) ? 1 : 0,
							'l' => ! empty( $v[ $k ]['l'] ) ? 1 : 0,
						);
					}
					return $out;
				},
			)
		);
	}
);

add_action(
	'admin_menu',
	function () {
		add_options_page( '社内への通知（Slack・LINE WORKS）', 'APPREX 社内通知', 'manage_options', 'apprex-notify', 'apprex_nh_page' );
	}
);

add_action(
	'admin_post_apprex_nh_test',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_nh_test' );
		$msg = '✅ APPREXからのテスト通知です（' . wp_date( 'n/j G:i' ) . '）。このメッセージが届けば設定はOKです。';
		$res = array();
		foreach ( array( 's' => array( 'Slack', apprex_slack_send_raw( $msg, array(), true ) ), 'l' => array( 'LINE WORKS', apprex_lw_send( $msg, true ) ) ) as $k => $r ) {
			$res[ $k ] = false === $r[1] ? 'URL未設定' : ( is_wp_error( $r[1] ) ? $r[1]->get_error_message() : 'OK' );
		}
		set_transient( 'apprex_nh_test_res', $res, 300 );
		wp_safe_redirect( admin_url( 'options-general.php?page=apprex-notify#test' ) );
		exit;
	}
);

/** 設定画面。 */
function apprex_nh_page() {
	$route = apprex_nh_route();
	$res   = get_transient( 'apprex_nh_test_res' );
	delete_transient( 'apprex_nh_test_res' );
	?>
	<div class="wrap">
		<h1>社内への通知（Slack・LINE WORKS）</h1>
		<p>記事の公開や、お客様とのやりとり・請求などを、Slack と LINE WORKS にお知らせします。種類ごとに、どちらに送るかを選べます。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_notify' ); ?>
			<h2>送り先</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="apprex_lw_webhook">LINE WORKS の URL</label></th>
					<td>
						<input type="url" id="apprex_lw_webhook" name="apprex_lw_webhook" class="large-text" value="<?php echo esc_attr( apprex_lw_webhook_url() ); ?>" placeholder="https://webhook.worksmobile.com/message/...">
						<p class="description">LINE WORKS のトークルームで「Incoming Webhook」アプリを追加して発行したURLを入れます。<br>
						手順：LINE WORKS 管理者画面 →「アプリ」→「Incoming Webhook」を追加 → 通知を受けたいトークルームに追加 → Webhook URL を発行してコピー。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_slack_webhook">Slack の URL</label></th>
					<td>
						<input type="url" id="apprex_slack_webhook" name="apprex_slack_webhook" class="large-text" value="<?php echo esc_attr( get_option( 'apprex_slack_webhook', '' ) ); ?>" placeholder="https://hooks.slack.com/services/...">
						<p class="description">Slack の「Incoming Webhook」のURLです（「APPREX 連携」の Slack 通知URLと同じもの）。</p>
					</td>
				</tr>
			</table>
			<h2>何をどこに送るか</h2>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>お知らせの種類</th><th style="width:90px;text-align:center">Slack</th><th style="width:110px;text-align:center">LINE WORKS</th></tr></thead>
				<tbody>
				<?php foreach ( apprex_nh_cats() as $k => $c ) : ?>
					<tr>
						<td><b><?php echo esc_html( $c[0] ); ?></b><br><span class="description"><?php echo esc_html( $c[1] ); ?></span></td>
						<td style="text-align:center"><input type="checkbox" name="apprex_nh_route[<?php echo esc_attr( $k ); ?>][s]" value="1" <?php checked( $route[ $k ]['s'] ); ?> aria-label="<?php echo esc_attr( $c[0] . 'をSlackに送る' ); ?>"></td>
						<td style="text-align:center"><input type="checkbox" name="apprex_nh_route[<?php echo esc_attr( $k ); ?>][l]" value="1" <?php checked( $route[ $k ]['l'] ); ?> aria-label="<?php echo esc_attr( $c[0] . 'をLINE WORKSに送る' ); ?>"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">ここで設定する LINE WORKS のURLは、社内へのお知らせを1つのルームに送るためのものです。お客様とのやりとりは、下の「お客様ごとの個別ルーム」を設定すると、お客様ごとのルームに分かれます（そのルームから返事もできます）。</p>
			<?php submit_button( '保存する' ); ?>
		</form>

		<h2 id="test">テスト送信（通知のルーム）</h2>
		<?php if ( is_array( $res ) ) : ?>
			<div class="notice notice-info inline"><p>Slack：<?php echo esc_html( $res['s'] ); ?>　／　LINE WORKS：<?php echo esc_html( $res['l'] ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_nh_test">
			<?php wp_nonce_field( 'apprex_nh_test' ); ?>
			<?php submit_button( 'Slack と LINE WORKS にテストを送る', 'secondary', 'submit', false ); ?>
		</form>

		<hr>
		<?php
		if ( function_exists( 'apprex_lwb_settings_box' ) ) {
			apprex_lwb_settings_box();
		}
		?>
		<hr>
		<h2>最近送ったお知らせ</h2>
		<?php
		$log = array_reverse( (array) get_option( 'apprex_nh_log', array() ) );
		$cats = apprex_nh_cats();
		if ( ! $log ) {
			echo '<p>まだありません。</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:900px"><tbody>';
			foreach ( array_slice( $log, 0, 20 ) as $l ) {
				echo '<tr><td style="width:110px">' . esc_html( wp_date( 'n/j G:i', (int) $l[0] ) ) . '</td><td style="width:150px">' . esc_html( $cats[ $l[1] ][0] ?? $l[1] ) . '</td><td>' . esc_html( $l[2] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		?>
	</div>
	<?php
}

/* =========================================================================
 * 毎日の記事の自動投稿：いまの状態（業務ナビの「システムの状態」に出す）
 * ====================================================================== */

/** [正常か, 説明]。自動投稿がオフなら null。 */
function apprex_nh_autopost_state() {
	if ( ! get_option( 'apprex_autopost_enabled', 0 ) ) {
		return null;
	}
	$freq = get_option( 'apprex_autopost_freq', 'weekly' );
	$gap  = array( 'daily' => 1, 'weekly' => 7, 'biweekly' => 14 );
	$days = isset( $gap[ $freq ] ) ? $gap[ $freq ] : 7;
	$last = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_apprex_ai_generated', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$res  = (string) get_option( 'apprex_autopost_last_result', '' );
	$lbl  = array( 'daily' => '毎日', 'weekly' => '週1回', 'biweekly' => '2週に1回' )[ $freq ] ?? $freq;
	if ( ! $last ) {
		return array( false, '自動投稿（' . $lbl . '）はオンですが、まだAIの記事が公開されていません' . ( 0 === strpos( $res, '失敗' ) ? '（' . mb_substr( $res, 0, 60 ) . '）' : '' ) );
	}
	$ts   = (int) get_post_time( 'U', true, $last[0] );
	$late = time() - $ts > ( $days * DAY_IN_SECONDS + 12 * HOUR_IN_SECONDS );
	$txt  = '最後の記事 ' . wp_date( 'n/j G:i', $ts ) . '「' . mb_substr( get_the_title( $last[0] ), 0, 24 ) . '」';
	if ( $late ) {
		return array( false, '自動投稿（' . $lbl . '）が止まっているかもしれません。' . $txt . ( 0 === strpos( $res, '失敗' ) ? '／' . mb_substr( $res, 0, 60 ) : '' ) );
	}
	return array( true, '自動投稿（' . $lbl . '）は動いています。' . $txt );
}

add_filter(
	'apprex_nav_health',
	function ( $out ) {
		$a = apprex_nh_autopost_state();
		if ( $a ) {
			$out[] = array( '記事の自動投稿', $a[0], $a[1], 'admin.php?page=apprex-ai-blog' );
		}
		$lw    = '' !== apprex_lw_webhook_url();
		$sl    = function_exists( 'apprex_slack_webhook_url' ) && '' !== apprex_slack_webhook_url();
		$out[] = array( '社内への通知', $lw || $sl, ( $sl ? 'Slack：つながっています' : 'Slack：未設定' ) . '／' . ( $lw ? 'LINE WORKS：つながっています' : 'LINE WORKS：未設定' ), 'options-general.php?page=apprex-notify' );
		return $out;
	}
);
