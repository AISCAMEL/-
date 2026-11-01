<?php
/**
 * お支払い・契約の自動案内（マイページ・LINE・メール・チャット）。
 *
 * 契約ごとに「お支払いの状態」を毎日見て、段階に合わせた案内を自動で送る。
 *
 * - 期日の3日前 → 期日当日 → 3日後 → 7日後（重要）→ 14日後（最終）／入金を確認したらお礼
 * - 契約の更新日の30日前・7日前
 * - 送り先：マイページの「お知らせ」（すべて）、LINE（連携済みの方）、メール（期日当日から）、担当者へSlack（7日後から）
 * - LINEは送信数を使わない設定なら、次の返信でまとめて届ける。期日を過ぎたものだけは送る（設定で切り替え）
 * - 「お支払いが遅れた場合の対応」を設定に書いておくと、案内の文面と、LINE・サイトのチャットのAIの答えに使う
 *
 * 設定：設定 > APPREX 配信(LINE) の「お支払い・契約の自動案内」。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 自動案内を送るか（入れた直後に急に届かないよう、既定はオフ）。 */
function apprex_bn_on() {
	return (bool) get_option( 'apprex_bn_on', 0 );
}

/** 期日を過ぎたお知らせはLINEで送る（送信数を使う）。 */
function apprex_bn_push() {
	return (bool) get_option( 'apprex_bn_push', 1 );
}

/** 「お支払いが遅れた場合の対応」の既定の文面。 */
function apprex_bn_policy_default() {
	return "・お支払い期日を過ぎると、マイページ・LINE・メールでお知らせします。\n"
		. "・期日から7日を過ぎてもお支払いが確認できない場合、サービスの一部（アプリの更新・サポートなど）を一時的に止めることがあります。\n"
		. "・期日から14日を過ぎた場合は、担当者からご連絡します。それでもお支払いが確認できない場合は、契約書にもとづき契約を解除し、残りの料金をまとめてご請求することがあります。\n"
		. "・ご事情でお支払いが難しい場合は、早めにご相談ください。支払日の変更などのご相談に応じます。";
}

/** 「お支払いが遅れた場合の対応」。 */
function apprex_bn_policy() {
	$p = trim( (string) get_option( 'apprex_bn_policy', '' ) );
	return '' !== $p ? $p : apprex_bn_policy_default();
}

/** 段階の順番（後ろほど重い）。 */
function apprex_bn_order() {
	return array( 'pre' => 1, 'due' => 2, 'od3' => 3, 'od7' => 4, 'od14' => 5 );
}

/* -------------------------------------------------------------------------
 * お支払いの状態
 * ---------------------------------------------------------------------- */

/**
 * 契約のお支払いの状態。
 *
 * @param int $id 契約ID。
 * @return array|null 対象外なら null。
 */
function apprex_bn_state( $id ) {
	$m = function ( $k ) use ( $id ) {
		return (string) get_post_meta( $id, $k, true );
	};
	$status = $m( 'apprex_c_status' ) ? $m( 'apprex_c_status' ) : 'active';
	if ( 'active' !== $status ) {
		return null;
	}
	$day    = min( 28, max( 1, (int) $m( 'apprex_c_payment_day' ) ? (int) $m( 'apprex_c_payment_day' ) : 27 ) );
	$method = 'invoice' === $m( 'apprex_c_payment_method' ) ? 'invoice' : 'square';
	$s      = array(
		'id'      => (int) $id,
		'method'  => $method,
		'title'   => trim( $m( 'apprex_c_service' ) . ' ' . $m( 'apprex_c_plan' ) ),
		'name'    => $m( 'apprex_c_name' ),
		'email'   => $m( 'apprex_c_email' ),
		'amount'  => (int) $m( 'apprex_c_monthly' ),
		'url'     => '',
		'raw'     => '',
	);
	if ( 'square' === $method ) {
		if ( ! $m( 'apprex_c_sq_invoice_id' ) ) {
			return null;
		}
		$st = $m( 'apprex_c_sq_invoice_status' );
		if ( in_array( $st, array( 'CANCELED', 'REFUNDED', 'PARTIALLY_REFUNDED', 'DRAFT', 'SCHEDULED' ), true ) ) {
			return null;
		}
		$s['month'] = $m( 'apprex_c_sq_invoice_month' );
		$s['due']   = $m( 'apprex_c_sq_invoice_due' ) ? $m( 'apprex_c_sq_invoice_due' ) : $s['month'] . '-' . sprintf( '%02d', $day );
		$s['paid']  = in_array( $st, array( 'PAID', 'PAYMENT_PENDING' ), true );
		$s['url']   = $m( 'apprex_c_sq_invoice_url' );
		$s['raw']   = $st;
	} else {
		// 振込：今月の支払い日。入金の確認（消し込み）が一度もない方は、期日を過ぎたかどうか分からないので、期日前の案内だけにする。
		$s['month'] = current_time( 'Y-m' );
		$s['due']   = $s['month'] . '-' . sprintf( '%02d', $day );
		$last       = $m( 'apprex_c_last_paid' );
		$s['paid']  = $last ? ( strtotime( $last ) >= strtotime( $s['due'] ) - 25 * DAY_IN_SECONDS ) : null;
	}
	$today     = strtotime( current_time( 'Y-m-d' ) );
	$s['days'] = (int) floor( ( $today - strtotime( $s['due'] ) ) / DAY_IN_SECONDS ); // 期日からの日数（前ならマイナス）。
	return $s;
}

/**
 * いまの段階。
 *
 * @param array $s apprex_bn_state()。
 * @return string pre / due / od3 / od7 / od14 / paid / ''。
 */
function apprex_bn_stage( $s ) {
	if ( true === $s['paid'] ) {
		return 'paid';
	}
	if ( $s['days'] < -3 ) {
		return '';
	}
	if ( $s['days'] < 0 ) {
		return 'pre';
	}
	if ( null === $s['paid'] || $s['days'] > 45 ) {
		return ''; // 分からない／古すぎるものは、担当者の確認に任せる。
	}
	if ( $s['days'] >= 14 ) {
		return 'od14';
	}
	if ( $s['days'] >= 7 ) {
		return 'od7';
	}
	return $s['days'] >= 3 ? 'od3' : 'due';
}

/** 状態の短い説明（AI・管理画面・マイページ用）。 */
function apprex_bn_state_text( $s ) {
	$due = wp_date( 'n月j日', strtotime( $s['due'] ) );
	if ( true === $s['paid'] ) {
		return $s['month'] . '分：お支払い済み';
	}
	if ( null === $s['paid'] ) {
		return $s['month'] . '分：期日 ' . $due . '（入金の確認はこれから）';
	}
	if ( $s['days'] < 0 ) {
		return $s['month'] . '分：未払い・期日 ' . $due . '（あと' . abs( $s['days'] ) . '日）';
	}
	if ( 0 === $s['days'] ) {
		return $s['month'] . '分：未払い・本日が期日';
	}
	return $s['month'] . '分：未払い・期日 ' . $due . 'から' . $s['days'] . '日過ぎている';
}

/* -------------------------------------------------------------------------
 * 文面
 * ---------------------------------------------------------------------- */

/**
 * 段階ごとの文面。
 *
 * @param array  $s     apprex_bn_state()（更新の案内では契約情報だけ）。
 * @param string $stage 段階。
 * @return array [件名, 本文, 色]
 */
function apprex_bn_message( $s, $stage ) {
	$who  = $s['name'] ? $s['name'] . ' 様' : 'お客様';
	$what = ( $s['title'] ? $s['title'] : 'ご利用料金' ) . ( isset( $s['month'] ) ? 'の' . $s['month'] . '分' : '' ) . ( $s['amount'] ? '（月額 ' . number_format( $s['amount'] ) . '円・税抜）' : '' );
	$due  = isset( $s['due'] ) ? wp_date( 'n月j日', strtotime( $s['due'] ) ) : '';
	$how  = ( 'square' === $s['method'] && $s['url'] )
		? "こちらからカードでお支払いいただけます。\n" . $s['url']
		: 'お振込をお願いいたします（請求書はメールでお送りしています）。';
	$help = "ご事情でお支払いが難しい場合は、LINEで「支払いの相談」と送るか、このメールにご返信ください。担当者がご相談に応じます。\n行き違いでお支払い済みの場合は、ご容赦ください。";
	$rule = "【お支払いが遅れた場合について】\n" . apprex_bn_policy();
	switch ( $stage ) {
		case 'pre':
			return array( 'お支払い期日のお知らせ', "{$who}\n\n{$what}のお支払い期日は {$due} です。\n{$how}", 'blue' );
		case 'due':
			return array( '本日がお支払い期日です', "{$who}\n\n{$what}のお支払い期日は本日（{$due}）です。\n{$how}\n\n{$help}", 'amber' );
		case 'od3':
			return array( 'お支払いが確認できていません', "{$who}\n\n{$what}のお支払いが、期日（{$due}）を過ぎても確認できておりません。\nお手数ですが、お手続きをお願いいたします。\n{$how}\n\n{$help}", 'red' );
		case 'od7':
			return array( '【重要】お支払いのお願い', "{$who}\n\n{$what}のお支払いが、期日（{$due}）から7日を過ぎても確認できておりません。\n至急、お手続きをお願いいたします。\n{$how}\n\n{$rule}\n\n{$help}", 'red' );
		case 'od14':
			return array( '【重要】最終のお支払いのお願い', "{$who}\n\n{$what}のお支払いが、期日（{$due}）から14日を過ぎても確認できておりません。\n担当者から改めてご連絡いたします。お早めにお手続きをお願いいたします。\n{$how}\n\n{$rule}\n\n{$help}", 'red' );
		case 'paid':
			return array( 'お支払いを確認しました', "{$who}\n\n{$what}のお支払いを確認しました。ありがとうございます。", 'green' );
		case 'rn30':
		case 'rn7':
			$left = isset( $s['left'] ) ? (int) $s['left'] : ( 'rn30' === $stage ? 30 : 7 );
			$auto = ! empty( $s['autorenew'] )
				? 'このまま自動で更新されます。プランの変更やご解約をご希望の場合は、更新日までにご連絡ください。'
				: '更新のお手続きが必要です。お送りしたメールの「契約を更新する」ボタン、またはLINE・お問い合わせからご連絡ください。';
			return array( 'ご契約の更新日が近づいています', "{$who}\n\n" . ( $s['title'] ? $s['title'] : 'ご契約' ) . 'の更新日（' . wp_date( 'Y年n月j日', strtotime( $s['renewal'] ) ) . '）' . ( $left > 0 ? 'まで、あと' . $left . '日です。' : 'は本日です。' ) . "\n{$auto}", 'blue' );
	}
	return array( '', '', 'gray' );
}

/* -------------------------------------------------------------------------
 * 送る
 * ---------------------------------------------------------------------- */

/** マイページのお知らせに残す（契約ごと、最新30件）。 */
function apprex_bn_mypage_add( $id, $stage, $title, $body, $tone, $url = '' ) {
	$n = get_post_meta( $id, 'apprex_c_notices', true );
	$n = is_array( $n ) ? $n : array();
	array_unshift( $n, array( 't' => time(), 'stage' => $stage, 'title' => $title, 'body' => $body, 'tone' => $tone, 'url' => $url ) );
	update_post_meta( $id, 'apprex_c_notices', array_slice( $n, 0, 30 ) );
}

/** 契約の LINE userId（会員がLINE連携済み、またはLINEで予約したお客様）。 */
function apprex_bn_line_uid( $id ) {
	$email = (string) get_post_meta( $id, 'apprex_c_email', true );
	return ( is_email( $email ) && function_exists( 'apprex_line_uid_by_email' ) ) ? (string) apprex_line_uid_by_email( $email ) : '';
}

/**
 * 1件の案内を、マイページ・LINE・メール・担当者へ送る。
 *
 * @param array  $s     状態。
 * @param string $stage 段階。
 * @return array 送った先。
 */
function apprex_bn_send( $s, $stage ) {
	list( $title, $body, $tone ) = apprex_bn_message( $s, $stage );
	if ( '' === $title ) {
		return array();
	}
	$id   = $s['id'];
	$sent = array( 'マイページ' );
	$pay  = ( 'square' === $s['method'] && $s['url'] && 'paid' !== $stage ) ? $s['url'] : '';
	apprex_bn_mypage_add( $id, $stage, $title, $body, $tone, $pay );

	$overdue = in_array( $stage, array( 'od3', 'od7', 'od14' ), true );

	// LINE。
	$uid = apprex_bn_line_uid( $id );
	if ( '' !== $uid ) {
		$icon = array( 'pre' => '📅', 'due' => '⏰', 'od3' => '⚠️', 'od7' => '⚠️', 'od14' => '🚨', 'paid' => '✅', 'rn30' => '📄', 'rn7' => '📄' );
		$text = ( isset( $icon[ $stage ] ) ? $icon[ $stage ] . ' ' : '' ) . $title . "\n\n" . preg_replace( '/^.*様\n\n/u', '', $body );
		$text = str_replace( 'このメールにご返信ください', 'このLINEでご返信ください', $text );
		$text .= "\n\nマイページ：" . ( function_exists( 'apprex_line_mypage_link' ) ? apprex_line_mypage_link() : home_url( '/mypage/' ) );
		if ( $overdue && apprex_bn_push() && function_exists( 'apprex_line_push' ) ) {
			$r      = apprex_line_push( $uid, array( array( 'type' => 'text', 'text' => mb_substr( $text, 0, 4900 ) ) ) );
			$sent[] = true === $r ? 'LINE（送信）' : 'LINE（失敗）';
		} elseif ( function_exists( 'apprex_line_notice' ) ) {
			$r      = apprex_line_notice( $uid, $text, 'bn_' . $stage );
			$sent[] = true === $r ? 'LINE（送信）' : 'LINE（次の返信で）';
		}
	}

	// メール（期日前・お礼・更新は、これまでのメールがあるので送らない）。
	if ( in_array( $stage, array( 'due', 'od3', 'od7', 'od14' ), true ) && is_email( $s['email'] ) && function_exists( 'apprex_render_email' ) ) {
		$subject = '【APPREX】' . $title;
		$opts    = array( 'heading' => $title );
		$GLOBALS['apprex_bn_mailing'] = true; // 「メールをお送りしました」をLINEに重ねない。
		$ok = wp_mail( $s['email'], $subject, apprex_render_email( $subject, $body, $opts ), function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array() );
		unset( $GLOBALS['apprex_bn_mailing'] );
		$sent[] = $ok ? 'メール' : 'メール（失敗）';
	}

	// 担当者へ。
	if ( in_array( $stage, array( 'od7', 'od14' ), true ) && function_exists( 'apprex_slack_notify' ) ) {
		apprex_slack_notify( ( 'od14' === $stage ? ':rotating_light:' : ':warning:' ) . ' お支払い' . $s['days'] . '日超過：' . $s['name'] . '（' . $s['title'] . '・' . $s['month'] . '分）お客様へ「' . $title . '」を送りました。 ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		$sent[] = '担当者';
	}

	$log = get_option( 'apprex_bn_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => time(), 'id' => $id, 'name' => $s['name'], 'title' => $title, 'to' => implode( '・', $sent ) ) );
	update_option( 'apprex_bn_log', array_slice( $log, 0, 150 ), false );
	return $sent;
}

/** 毎日の処理（契約の日次バッチから呼ぶ）。 */
function apprex_bn_run() {
	if ( ! apprex_bn_on() || ! function_exists( 'apprex_get_contracts' ) ) {
		return 0;
	}
	$n     = 0;
	$order = apprex_bn_order();
	$today = strtotime( current_time( 'Y-m-d' ) );
	foreach ( apprex_get_contracts() as $id ) {
		$s = apprex_bn_state( $id );
		if ( $s ) {
			$stage = apprex_bn_stage( $s );
			// 期日を過ぎた案内の前に、Squareの最新の状態を確かめる（今朝払った方に送らないため）。
			if ( isset( $order[ $stage ] ) && $order[ $stage ] >= 2 && 'square' === $s['method'] && function_exists( 'apprex_square_refresh_invoice' ) ) {
				apprex_square_refresh_invoice( $id );
				$s     = apprex_bn_state( $id );
				$stage = $s ? apprex_bn_stage( $s ) : '';
			}
			$done = get_post_meta( $id, 'apprex_bn_sent', true );
			$done = is_array( $done ) ? $done : array();
			$key  = $s ? $s['month'] : '';
			if ( 'paid' === $stage ) {
				// お礼は、その月に案内を送っていたときだけ。
				if ( ! empty( $done[ $key ] ) && empty( $done[ $key . ':paid' ] ) ) {
					apprex_bn_send( $s, 'paid' );
					$done[ $key . ':paid' ] = 1;
					$n++;
				}
			} elseif ( isset( $order[ $stage ] ) && ( empty( $done[ $key ] ) || (int) $done[ $key ] < $order[ $stage ] ) ) {
				apprex_bn_send( $s, $stage ); // 送りそびれた段階はまとめて、いまの段階だけ送る。
				$done[ $key ] = $order[ $stage ];
				$n++;
			}
			update_post_meta( $id, 'apprex_bn_sent', array_slice( $done, -24, null, true ) );
		}

		// 契約の更新日。
		$renewal = (string) get_post_meta( $id, 'apprex_c_renewal', true );
		$status  = (string) get_post_meta( $id, 'apprex_c_status', true );
		if ( $renewal && 'cancelled' !== $status && strtotime( $renewal ) ) {
			$left  = (int) floor( ( strtotime( $renewal ) - $today ) / DAY_IN_SECONDS );
			$stage = ( $left >= 0 && $left <= 7 ) ? 'rn7' : ( ( $left > 7 && $left <= 30 ) ? 'rn30' : '' );
			$rdone = get_post_meta( $id, 'apprex_bn_renewal', true );
			$rdone = is_array( $rdone ) ? $rdone : array();
			if ( $stage && empty( $rdone[ $renewal . ':' . $stage ] ) ) {
				$info = array(
					'id'        => (int) $id,
					'method'    => '',
					'name'      => (string) get_post_meta( $id, 'apprex_c_name', true ),
					'email'     => (string) get_post_meta( $id, 'apprex_c_email', true ),
					'title'     => trim( get_post_meta( $id, 'apprex_c_service', true ) . ' ' . get_post_meta( $id, 'apprex_c_plan', true ) ),
					'amount'    => 0,
					'url'       => '',
					'renewal'   => $renewal,
					'autorenew' => (int) get_post_meta( $id, 'apprex_c_autorenew', true ),
					'left'      => $left,
				);
				apprex_bn_send( $info, $stage );
				$rdone[ $renewal . ':' . $stage ] = 1;
				update_post_meta( $id, 'apprex_bn_renewal', array_slice( $rdone, -8, null, true ) );
				$n++;
			}
		}
	}
	return $n;
}

/* -------------------------------------------------------------------------
 * AIに渡す情報（LINE・サイトのチャット）
 * ---------------------------------------------------------------------- */

/**
 * 契約たちのお支払いの状態と、遅れた場合の対応（AI用）。
 *
 * @param int[] $ids 契約ID。
 * @return string
 */
function apprex_bn_ai_context( $ids ) {
	$rows = array();
	$late = false;
	foreach ( (array) $ids as $id ) {
		$s = apprex_bn_state( $id );
		if ( ! $s ) {
			continue;
		}
		$t = '- お支払いの状況（' . ( $s['title'] ? $s['title'] : '契約' ) . '）：' . apprex_bn_state_text( $s );
		if ( false === $s['paid'] && $s['url'] && 'square' === $s['method'] ) {
			$t .= '・お支払いページ ' . $s['url'];
		}
		if ( false === $s['paid'] && $s['days'] >= 0 ) {
			$late = true;
		}
		$rows[] = $t;
	}
	if ( ! $rows ) {
		return '';
	}
	$out = "# お支払いの状況（毎日自動で確認）\n" . implode( "\n", $rows ) . "\n\n# お支払いが遅れた場合の対応（聞かれたら、この内容のとおりに案内する）\n" . apprex_bn_policy();
	if ( $late ) {
		$out .= "\n\n# 期日を過ぎた未払いがある\n"
			. "- 話題が別のことでも、返答の最後に1〜2文で、やさしくお支払いのお願いとお支払いページを添える（責めない・おどさない）。\n"
			. "- 「払えない」「待ってほしい」などの相談には、責めずに受け止め、担当者が相談に応じると伝える（LINEでは依頼として受け付ける）。\n"
			. "- 停止・解除など、上の対応に書かれていないことは言わない。";
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * 設定（APPREX 配信(LINE) に相乗り）
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	register_setting( 'apprex_line_dist', 'apprex_bn_on', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_line_dist', 'apprex_bn_push', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_line_dist', 'apprex_bn_policy', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
} );

add_action( 'admin_post_apprex_bn_run', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_bn_run' );
	$n = apprex_bn_on() ? apprex_bn_run() : -1;
	wp_safe_redirect( add_query_arg( 'apprex_bn', $n, admin_url( 'options-general.php?page=apprex-line' ) ) . '#apprex-bn' );
	exit;
} );

/** 設定画面の表示（inc/line-direct.php の設定ページから呼ぶ）。 */
function apprex_bn_settings() {
	$log  = get_option( 'apprex_bn_log', array() );
	$log  = is_array( $log ) ? $log : array();
	$rows = array();
	foreach ( function_exists( 'apprex_get_contracts' ) ? apprex_get_contracts() : array() as $id ) {
		$s = apprex_bn_state( $id );
		if ( $s ) {
			$rows[] = $s;
		}
	}
	$names = array( 'pre' => '期日前のお知らせ', 'due' => '期日当日のお知らせ', 'od3' => '3日後：確認のお願い', 'od7' => '7日後：重要なお願い', 'od14' => '14日後：最終のお願い', 'paid' => 'お礼（入金確認）' );
	?>
	<h2 id="apprex-bn">お支払い・契約の自動案内</h2>
	<?php if ( isset( $_GET['apprex_bn'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success inline"><p><?php echo -1 === (int) $_GET['apprex_bn'] ? '自動案内がオフのため、送っていません。' : esc_html( (int) $_GET['apprex_bn'] . '件の案内を送りました。' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p></div>
	<?php endif; ?>
	<p class="description">ご契約ごとにお支払いの状態を毎日確認し、段階に合わせてお知らせします。期日の3日前 → 当日 → 3日後 → 7日後 → 14日後、入金を確認したらお礼。契約の更新日の30日前・7日前にもお知らせします。<br>送り先：マイページの「お知らせ」・LINE（連携済みの方）・メール（期日当日から）・担当者へSlack（7日後から）。</p>
	<table class="form-table">
		<tr>
			<th scope="row">自動案内</th>
			<td><label><input type="checkbox" name="apprex_bn_on" value="1" <?php checked( apprex_bn_on() ); ?>> お支払い・契約の更新のお知らせを自動で送る</label>
				<p class="description">オンにする前に、下の「いまの状況」で、誰にどの案内が届くかを確かめてください。</p></td>
		</tr>
		<tr>
			<th scope="row">期日を過ぎたお知らせ</th>
			<td><label><input type="checkbox" name="apprex_bn_push" value="1" <?php checked( apprex_bn_push() ); ?>> 期日を過ぎたお知らせ（3日後・7日後・14日後）は、LINEで送る</label>
				<p class="description">LINEの送信数を使います（1件の未払いで最大3通）。オフにすると、次にお客様が話しかけたときの返信でお届けします。</p></td>
		</tr>
		<tr>
			<th scope="row"><label for="apprex_bn_policy">お支払いが遅れた場合の対応</label></th>
			<td><textarea id="apprex_bn_policy" name="apprex_bn_policy" rows="6" class="large-text"><?php echo esc_textarea( apprex_bn_policy() ); ?></textarea>
				<p class="description">7日後・14日後のお知らせに入ります。LINEとサイトのチャットのAIも、この内容のとおりに答えます。<b>契約書の内容と合っているか、ご確認ください。</b></p></td>
		</tr>
	</table>

	<h3>いまの状況</h3>
	<?php if ( ! $rows ) : ?>
		<p class="description">対象のご契約はまだありません（契約中で、Squareの請求書があるか、振込のご契約が対象です）。</p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:980px">
			<thead><tr><th>お客様</th><th>支払い方法</th><th>状態</th><th>次の案内</th><th>LINE</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $s ) :
				$stage = apprex_bn_stage( $s );
				$done  = get_post_meta( $s['id'], 'apprex_bn_sent', true );
				$done  = is_array( $done ) ? $done : array();
				$order = apprex_bn_order();
				$next  = '—';
				if ( 'paid' === $stage ) {
					$next = ( ! empty( $done[ $s['month'] ] ) && empty( $done[ $s['month'] . ':paid' ] ) ) ? $names['paid'] : '—';
				} elseif ( isset( $order[ $stage ] ) ) {
					$next = ( empty( $done[ $s['month'] ] ) || (int) $done[ $s['month'] ] < $order[ $stage ] ) ? $names[ $stage ] : '送信済み';
				}
				list( $t, $b ) = '—' !== $next && '送信済み' !== $next ? apprex_bn_message( $s, $stage ) : array( '', '' );
				?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $s['id'] . '&action=edit' ) ); ?>"><?php echo esc_html( $s['name'] ? $s['name'] : '契約#' . $s['id'] ); ?></a></td>
					<td><?php echo 'square' === $s['method'] ? 'Square（カード）' : '振込'; ?></td>
					<td><?php echo esc_html( apprex_bn_state_text( $s ) ); ?></td>
					<td><?php echo esc_html( $next ); ?><?php if ( $b ) : ?><details><summary style="cursor:pointer;color:#2271b1">文面を見る</summary><pre style="white-space:pre-wrap;font-size:12px;background:#f6f7f7;padding:8px;max-width:520px"><?php echo esc_html( $t . "\n\n" . $b ); ?></pre></details><?php endif; ?></td>
					<td><?php echo '' !== apprex_bn_line_uid( $s['id'] ) ? '連携済み' : '—'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apprex_bn_run' ), 'apprex_bn_run' ) ); ?>">今すぐ確認して送る</a> <span class="description">毎日自動で行います。オンにして保存してから押してください。</span></p>

	<h3>送った案内</h3>
	<?php if ( ! $log ) : ?>
		<p class="description">まだありません。</p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:980px">
			<thead><tr><th>日時</th><th>お客様</th><th>案内</th><th>送り先</th></tr></thead>
			<tbody>
			<?php foreach ( array_slice( $log, 0, 30 ) as $r ) : ?>
				<tr><td><?php echo esc_html( wp_date( 'n/j G:i', (int) $r['t'] ) ); ?></td><td><?php echo esc_html( $r['name'] ); ?></td><td><?php echo esc_html( $r['title'] ); ?></td><td><?php echo esc_html( $r['to'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}
