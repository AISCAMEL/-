<?php
/**
 * ミーティングに参加するボタン（予約ごとの参加リンク）。
 *
 * - メール・LINE・予約完了の画面に「ミーティングに参加する」ボタンを必ず入れる。
 * - ボタンのリンクは予約ごとの「参加リンク」（/?apx_join=ID.署名）。押すと、その時点の参加URL（Google Meet）へ移る。
 *   参加URLは「予約ごとのURL」→「ミーティング予約の設定の参加URL」の順に使う。あとから入れても、送ったメールのボタンで入れる。
 * - 参加URLがまだないときは「準備しています」のページを出して、すぐ社内に知らせる（お客様が入れないまま待たない）。
 * - 管理画面：予約ごとに参加URLを入れて「お客様に送る」（メール・LINE）。設定のURLが空なら大きな注意を出す。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_mj_sig( $id ) {
	return substr( hash_hmac( 'sha256', 'mj' . (int) $id, wp_salt( 'auth' ) ), 0, 12 );
}

/** 予約ごとの参加リンク（メール・LINEに入れる）。 */
function apprex_mj_link( $id ) {
	return add_query_arg( 'apx_join', (int) $id . '.' . apprex_mj_sig( $id ), home_url( '/' ) );
}

/** この予約の参加URL（Google Meet など）。なければ ''。 */
function apprex_mj_url( $id ) {
	$u = (string) get_post_meta( $id, 'apprex_meet_url', true );
	if ( '' === $u && function_exists( 'apprex_mb_settings' ) ) {
		$u = (string) apprex_mb_settings()['url'];
	}
	return esc_url_raw( $u );
}

/** 設定の参加URLが入っているか。 */
function apprex_mj_has_default() {
	return function_exists( 'apprex_mb_settings' ) && '' !== (string) apprex_mb_settings()['url'];
}

/** 予約ごとに Google Meet を自動で作る設定か。 */
function apprex_mj_auto_meet() {
	return function_exists( 'apprex_gcal_meet_on' ) && apprex_gcal_meet_on() && function_exists( 'apprex_gcal_ready' ) && apprex_gcal_ready();
}

// 参加リンクを開いたとき。
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['apx_join'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$v  = sanitize_text_field( wp_unslash( $_GET['apx_join'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$p  = explode( '.', $v, 2 );
		$id = (int) ( $p[0] ?? 0 );
		if ( ! $id || 'apprex_inquiry' !== get_post_type( $id ) || ! hash_equals( apprex_mj_sig( $id ), (string) ( $p[1] ?? '' ) ) ) {
			wp_die( 'リンクが正しくありません。お手数ですが、予約のメールにご返信ください。', 'ミーティング', array( 'response' => 400 ) );
		}
		nocache_headers();
		$url  = apprex_mj_url( $id );
		$name = (string) get_post_meta( $id, 'apprex_name', true );
		$at   = (int) get_post_meta( $id, 'apprex_meeting_at', true );
		$n    = (int) get_post_meta( $id, 'apprex_join_clicks', true ) + 1;
		update_post_meta( $id, 'apprex_join_clicks', $n );
		update_post_meta( $id, 'apprex_join_last', time() );
		if ( '' !== $url ) {
			if ( 1 === $n && function_exists( 'apprex_notify' ) && $at && abs( time() - $at ) < 2 * HOUR_IN_SECONDS ) {
				apprex_notify( 'inquiry', '🎥 ' . $name . ' 様がミーティングの参加ボタンを押しました（' . wp_date( 'n/j G:i', $at ) . '）' );
			}
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google Meet など外のURL。
			exit;
		}
		// 参加URLがまだない：社内に知らせて、待ってもらう（30秒ごとに自動で確かめる）。
		if ( ! get_transient( 'apprex_mj_alert_' . $id ) && function_exists( 'apprex_notify' ) ) {
			set_transient( 'apprex_mj_alert_' . $id, 1, 10 * MINUTE_IN_SECONDS );
			apprex_notify( 'inquiry', '🚨 ' . $name . ' 様がミーティングに入ろうとしていますが、参加URLが入っていません。すぐに参加URLを入れて「お客様に送る」を押してください。' . "\n" . admin_url( 'admin.php?page=apprex-meeting' ) );
		}
		$line = function_exists( 'apprex_line_url' ) ? apprex_line_url() : '';
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="30"><meta name="robots" content="noindex"><title>ミーティングの準備中</title>'
			. '<style>body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Hiragino Sans","Noto Sans JP",sans-serif;background:#f5f7fb;color:#0b1f44}main{max-width:520px;margin:12vh auto;padding:28px 24px;background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(11,31,68,.08);text-align:center}h1{font-size:22px;margin:0 0 12px}p{line-height:1.8;color:#334155}.b{display:inline-block;margin:8px 4px;padding:14px 22px;border-radius:10px;font-weight:700;text-decoration:none;color:#fff;background:#06c755}.s{display:inline-block;width:28px;height:28px;border:3px solid #dbe3f0;border-top-color:#1d4ed8;border-radius:50%;animation:r 1s linear infinite;margin:6px 0 10px}@keyframes r{to{transform:rotate(360deg)}}</style></head><body><main>'
			. '<div class="s"></div><h1>' . esc_html( $name ? $name . ' 様' : '' ) . '<br>ミーティングの準備をしています</h1>'
			. '<p>担当者にお知らせしました。参加の準備ができると、<b>このページが自動で切り替わります</b>（30秒ごとに確認しています）。このままお待ちください。</p>'
			. ( $at ? '<p>ご予約：' . esc_html( wp_date( 'Y年n月j日 H:i', $at ) ) . '</p>' : '' )
			. ( $line ? '<p><a class="b" href="' . esc_url( $line ) . '">LINEで連絡する</a></p>' : '' )
			. '<p style="font-size:13px">お急ぎの場合は、予約のメールにご返信ください。</p></main></body></html>';
		exit;
	},
	1
);

/** お客様に参加URLを送る（メール・LINE）。 */
function apprex_mj_send( $id ) {
	$email = (string) get_post_meta( $id, 'apprex_email', true );
	$name  = (string) get_post_meta( $id, 'apprex_name', true );
	$at    = (int) get_post_meta( $id, 'apprex_meeting_at', true );
	$when  = $at ? wp_date( 'Y年n月j日(D) H:i', $at ) : '';
	$link  = apprex_mj_link( $id );
	$ok    = false;
	if ( is_email( $email ) && function_exists( 'apprex_render_email' ) ) {
		$subject = '【APPREX】ミーティングの参加URLのご案内';
		$body    = "{$name} 様\n\nオンラインミーティング（Google Meet）の参加URLをご案内します。\n" . ( $when ? "日時：{$when}\n" : '' ) . "\nお時間になりましたら、下のボタンからご参加ください。\n{$link}\n\nボタンが押せない場合は、こちらのURLをブラウザで開いてください。\n" . apprex_mj_url( $id ) . "\n";
		$ok      = wp_mail( $email, $subject, apprex_render_email( $subject, $body ), function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
	$uid = (string) get_post_meta( $id, 'apprex_line_uid', true );
	if ( '' !== $uid && function_exists( 'apprex_line_push' ) ) {
		$r  = apprex_line_push( $uid, array( array( 'type' => 'text', 'text' => "{$name} 様\n\nミーティングの参加URLです。" . ( $when ? "\n■ {$when}" : '' ) . "\n▼ 参加する（Google Meet）\n{$link}" ) ) );
		$ok = $ok || ! is_wp_error( $r );
	}
	return $ok;
}

add_action(
	'admin_post_apprex_mj_set',
	function () {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_mj_' . $id ) || 'apprex_inquiry' !== get_post_type( $id ) ) {
			wp_die( '権限がありません。' );
		}
		$url = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
		update_post_meta( $id, 'apprex_meet_url', $url );
		$msg = '参加URLを保存しました。';
		if ( ! empty( $_POST['send'] ) ) {
			if ( '' === apprex_mj_url( $id ) ) {
				$msg = '参加URLが空なので送れませんでした。';
			} else {
				$msg = apprex_mj_send( $id ) ? 'お客様に参加URLを送りました（メール' . ( get_post_meta( $id, 'apprex_line_uid', true ) ? '・LINE' : '' ) . '）。' : '送れませんでした。メールの送信設定を確かめてください。';
			}
		}
		wp_safe_redirect( add_query_arg( 'mjmsg', rawurlencode( $msg ), admin_url( 'admin.php?page=apprex-meeting' ) ) );
		exit;
	}
);

/** 予約一覧の「参加URL」の欄。 */
function apprex_mj_cell( $id ) {
	$own   = (string) get_post_meta( $id, 'apprex_meet_url', true );
	$click = (int) get_post_meta( $id, 'apprex_join_clicks', true );
	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:4px;flex-wrap:wrap;align-items:center">
		<input type="hidden" name="action" value="apprex_mj_set"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_mj_' . $id ); ?>
		<input type="url" name="url" value="<?php echo esc_attr( $own ); ?>" placeholder="<?php echo esc_attr( apprex_mj_has_default() ? '空なら設定のURL' : 'https://meet.google.com/…' ); ?>" style="width:220px">
		<button class="button button-small">保存</button>
		<button class="button button-small button-primary" name="send" value="1">保存してお客様に送る</button>
	</form>
	<?php if ( '' === $own && apprex_mj_auto_meet() ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:4px">
			<input type="hidden" name="action" value="apprex_mj_meet"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_mj_meet_' . $id ); ?>
			<button class="button button-small">Google Meet を作ってお客様に送る</button>
		</form>
	<?php endif; ?>
	<small><a href="<?php echo esc_url( apprex_mj_link( $id ) ); ?>" target="_blank" rel="noopener">参加ボタンを試す</a><?php echo $click ? '・お客様が押した回数 ' . (int) $click : ''; ?><?php echo '' === apprex_mj_url( $id ) ? '　<b style="color:#d63638">参加URLなし</b>' : ''; ?></small>
	<?php
	return (string) ob_get_clean();
}

// 業務ナビの「いまの状態」：参加URLが空なら知らせる。
add_filter(
	'apprex_nav_health',
	function ( $out ) {
		if ( function_exists( 'apprex_mb_enabled' ) && apprex_mb_enabled() && ! apprex_mj_has_default() && ! apprex_mj_auto_meet() ) {
			$out[] = array( 'ミーティング', false, '参加URL（Google Meet）が入っていません。お客様がミーティングに入れません', 'admin.php?page=apprex-meeting' );
		}
		return $out;
	}
);

// すでに入っている予約に、あとから Google Meet を作る（Google カレンダーの予定に Meet を付ける）。
add_action(
	'admin_post_apprex_mj_meet',
	function () {
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_mj_meet_' . $id ) || 'apprex_inquiry' !== get_post_type( $id ) ) {
			wp_die( '権限がありません。' );
		}
		$msg = 'Google Meet を作れませんでした。';
		if ( function_exists( 'apprex_gcal_api' ) && function_exists( 'apprex_gcal_ids' ) ) {
			$cr = array(
				'conferenceData' => array(
					'createRequest' => array(
						'requestId'             => 'apprex-' . $id . '-' . wp_generate_password( 8, false ),
						'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
					),
				),
			);
			$ev = (string) get_post_meta( $id, 'apprex_gcal_event', true );
			list( $cal, $eid ) = array_pad( explode( '|', $ev, 2 ), 2, '' );
			if ( '' !== $eid ) {
				$r = apprex_gcal_api( 'PATCH', '/calendars/' . rawurlencode( $cal ) . '/events/' . rawurlencode( $eid ) . '?conferenceDataVersion=1', $cr );
			} else {
				$at  = (int) get_post_meta( $id, 'apprex_meeting_at', true );
				$min = function_exists( 'apprex_mb_settings' ) ? (int) apprex_mb_settings()['min'] : 60;
				$cal = apprex_gcal_ids()[0] ?? '';
				$r   = apprex_gcal_api(
					'POST',
					'/calendars/' . rawurlencode( $cal ) . '/events?conferenceDataVersion=1',
					array(
						'summary' => '【APPREX】オンライン相談：' . get_post_meta( $id, 'apprex_name', true ) . ' 様',
						'start'   => array( 'dateTime' => wp_date( 'c', $at ), 'timeZone' => wp_timezone_string() ),
						'end'     => array( 'dateTime' => wp_date( 'c', $at + $min * 60 ), 'timeZone' => wp_timezone_string() ),
					) + $cr
				);
				if ( ! is_wp_error( $r ) && ! empty( $r['id'] ) ) {
					update_post_meta( $id, 'apprex_gcal_event', $cal . '|' . $r['id'] );
				}
			}
			if ( is_wp_error( $r ) ) {
				$msg .= '（' . $r->get_error_message() . '）';
			} elseif ( ! empty( $r['hangoutLink'] ) ) {
				update_post_meta( $id, 'apprex_meet_url', esc_url_raw( $r['hangoutLink'] ) );
				update_post_meta( $id, 'apprex_meet_auto', 1 );
				$msg = 'Google Meet を作りました（' . $r['hangoutLink'] . '）。' . ( apprex_mj_send( $id ) ? 'お客様にも送りました。' : 'お客様への送信はできませんでした。' );
			}
		}
		wp_safe_redirect( add_query_arg( 'mjmsg', rawurlencode( $msg ), admin_url( 'admin.php?page=apprex-meeting' ) ) );
		exit;
	}
);
