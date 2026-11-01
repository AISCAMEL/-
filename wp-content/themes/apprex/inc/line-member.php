<?php
/**
 * LINEと会員情報の連動（LINEの送信数を使わない仕組み）。
 *
 * LINEは「こちらから送る（push）」が月の送信数に数えられ、「お客様の話しかけに返す（reply）」は数えられない。
 * そこで、お知らせはためておき、お客様が次に話しかけたときの返信にまとめて届ける。
 *
 * - 会員（LINE連携済み）と、LINEで予約したお客様の情報（契約・お支払い・ミーティング・AIスタジオ）を
 *   LINEのAIに渡し、その人に合わせて答える（お支払いのリンクもその場で返す）
 * - 担当者が必要な依頼（変更・解約・請求書の再発行など）は、AIが受け付けて担当者へメールで知らせる
 * - お客様あてのメールを送ったら、LINEにも「メールをお送りしました」とためておく
 * - ミーティング・説明会の自動のお知らせ（push）は、設定で「ためておく」にできる（既定）
 * - マイページに「LINEと連携する」ボタン
 *
 * 設定：設定 > APPREX 配信(LINE) の「LINEと会員の連動」。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** LINEの送信数を使わない（お知らせは次の返信にまとめる）。 */
function apprex_line_save_mode() {
	return (bool) get_option( 'apprex_line_save_mode', 1 );
}

/* -------------------------------------------------------------------------
 * お知らせ（ためておいて、次の返信で届ける）
 * ---------------------------------------------------------------------- */

/**
 * お知らせをためる。
 *
 * @param string $uid  LINE userId。
 * @param string $text 本文。
 */
function apprex_line_queue( $uid, $text ) {
	if ( '' === $uid || '' === trim( (string) $text ) || ! function_exists( 'apprex_line_friend_get' ) ) {
		return;
	}
	$fid = apprex_line_friend_get( $uid, true );
	if ( ! $fid ) {
		return;
	}
	$q   = get_post_meta( $fid, 'apprex_line_notices', true );
	$q   = is_array( $q ) ? $q : array();
	$q[] = array( 't' => time(), 'text' => mb_substr( trim( (string) $text ), 0, 1200 ) );
	update_post_meta( $fid, 'apprex_line_notices', array_slice( $q, -8 ) );
}

/** たまっているお知らせ。 */
function apprex_line_queued( $uid ) {
	$fid = function_exists( 'apprex_line_friend_get' ) ? apprex_line_friend_get( $uid, false ) : 0;
	$q   = $fid ? get_post_meta( $fid, 'apprex_line_notices', true ) : array();
	return is_array( $q ) ? $q : array();
}

/** お知らせを消す（返信で届けたあと）。 */
function apprex_line_queue_clear( $uid ) {
	$fid = function_exists( 'apprex_line_friend_get' ) ? apprex_line_friend_get( $uid, false ) : 0;
	if ( $fid ) {
		delete_post_meta( $fid, 'apprex_line_notices' );
	}
}

/** たまっているお知らせを、返信用の1通にまとめる（無ければ null）。 */
function apprex_line_queue_message( $uid ) {
	$q = apprex_line_queued( $uid );
	if ( ! $q ) {
		return null;
	}
	$t = '🔔 お知らせ（' . count( $q ) . '件）';
	foreach ( $q as $n ) {
		$t .= "\n\n" . wp_date( 'n/j G:i', (int) $n['t'] ) . "\n" . $n['text'];
	}
	return array( 'type' => 'text', 'text' => mb_substr( $t, 0, 4900 ) );
}

/**
 * 自動のお知らせを送る。送信数を使わない設定なら、ためておく（WP_Error を返すので、呼び出し側はメールに切り替えられる）。
 *
 * @param string $uid  LINE userId。
 * @param string $text 本文。
 * @param string $kind 種類（mb_before_1h はミーティング1時間前）。
 * @return true|WP_Error
 */
function apprex_line_notice( $uid, $text, $kind = '' ) {
	$push = ! apprex_line_save_mode() || ( 'mb_before_1h' === $kind && get_option( 'apprex_line_save_keep_1h', 1 ) );
	if ( $push && function_exists( 'apprex_line_push' ) ) {
		return apprex_line_push( $uid, array( array( 'type' => 'text', 'text' => mb_substr( (string) $text, 0, 4900 ) ) ) );
	}
	apprex_line_queue( $uid, $text );
	return new WP_Error( 'apprex_line_queued', 'LINEの送信数を使わない設定のため、次にお客様が話しかけたときの返信でお知らせします。' );
}

/* お客様あてのメールを送ったら、LINEにも「メールをお送りしました」とためておく。 */
add_filter( 'wp_mail', function ( $args ) {
	if ( ! function_exists( 'apprex_line_friend_get' ) || empty( $args['to'] ) ) {
		return $args;
	}
	$subject = isset( $args['subject'] ) ? wp_strip_all_tags( (string) $args['subject'] ) : '';
	if ( preg_match( '/パスワード|password|ログイン用|認証コード/iu', $subject ) ) {
		return $args; // 安全のため、パスワード関係はLINEに出さない。
	}
	$to    = is_array( $args['to'] ) ? $args['to'] : explode( ',', (string) $args['to'] );
	$staff = strtolower( function_exists( 'apprex_notify_to' ) ? (string) apprex_notify_to() : '' );
	foreach ( $to as $addr ) {
		if ( preg_match( '/<([^>]+)>/', $addr, $m ) ) {
			$addr = $m[1];
		}
		$addr = strtolower( trim( $addr ) );
		if ( ! is_email( $addr ) || $addr === $staff || $addr === strtolower( (string) get_option( 'admin_email' ) ) ) {
			continue;
		}
		$uid = apprex_line_uid_by_email( $addr );
		if ( '' === $uid ) {
			continue;
		}
		// 直前（2分以内）に同じ件のお知らせをためていれば重ねない。
		$q    = apprex_line_queued( $uid );
		$last = $q ? end( $q ) : null;
		if ( $last && time() - (int) $last['t'] < 120 ) {
			continue;
		}
		apprex_line_queue( $uid, '📩 メールをお送りしました：「' . $subject . '」' . "\n" . '内容はメールをご確認ください。マイページでも確認できます。' );
	}
	return $args;
} );

/** メールアドレスから LINE userId（会員、またはLINEで予約したお客様）。 */
function apprex_line_uid_by_email( $email ) {
	$u = get_user_by( 'email', $email );
	if ( $u ) {
		$uid = (string) get_user_meta( $u->ID, 'apprex_line_uid', true );
		if ( '' !== $uid ) {
			return $uid;
		}
	}
	$p = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array( 'key' => 'apprex_email', 'value' => $email ),
				array( 'key' => 'apprex_line_uid', 'value' => '', 'compare' => '!=' ),
			),
		)
	);
	return $p ? (string) get_post_meta( $p[0], 'apprex_line_uid', true ) : '';
}

/* -------------------------------------------------------------------------
 * お客様の情報（LINEのAIに渡す）
 * ---------------------------------------------------------------------- */

/** LINE userId の会員。 */
function apprex_line_member( $uid ) {
	if ( '' === $uid ) {
		return null;
	}
	$u = get_users( array( 'meta_key' => 'apprex_line_uid', 'meta_value' => $uid, 'number' => 1 ) );
	return $u ? $u[0] : null;
}

/** LINEでマイページを開くURL（LIFF。未設定ならマイページ）。 */
function apprex_line_mypage_link() {
	if ( function_exists( 'apprex_liff_ready' ) && apprex_liff_ready() ) {
		return 'https://liff.line.me/' . rawurlencode( apprex_liff_id() );
	}
	return function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' );
}

/**
 * お客様の情報（AIへの説明文）。
 *
 * @param string $uid LINE userId。
 * @return string
 */
function apprex_line_member_context( $uid ) {
	$user  = apprex_line_member( $uid );
	$lines = array();
	$email = '';
	if ( $user ) {
		$email   = strtolower( (string) $user->user_email );
		$lines[] = '会員：' . $user->display_name . ' 様（LINE連携済み・メール ' . $user->user_email . '）';
		foreach ( function_exists( 'apprex_member_contracts' ) ? apprex_member_contracts( $user ) : array() as $cid ) {
			$m      = function ( $k ) use ( $cid ) {
				return (string) get_post_meta( $cid, $k, true );
			};
			$status = function_exists( 'apprex_contract_status_label' ) ? apprex_contract_status_label( $m( 'apprex_c_status' ) ) : $m( 'apprex_c_status' );
			$c      = '契約：' . trim( $m( 'apprex_c_service' ) . ' ' . $m( 'apprex_c_plan' ) ) . '（' . $status . '）';
			if ( $m( 'apprex_c_monthly' ) ) {
				$c .= '・月額 ' . number_format( (int) $m( 'apprex_c_monthly' ) ) . '円';
			}
			if ( $m( 'apprex_c_renewal' ) ) {
				$c .= '・次回更新 ' . $m( 'apprex_c_renewal' );
			}
			$c .= '・支払い方法 ' . ( 'invoice' === $m( 'apprex_c_payment_method' ) ? '請求書（振込）' : 'Square（カード）' );
			if ( $m( 'apprex_c_sq_invoice_id' ) ) {
				$st = function_exists( 'apprex_square_status_label' ) ? apprex_square_status_label( $m( 'apprex_c_sq_invoice_status' ) ) : array( $m( 'apprex_c_sq_invoice_status' ) );
				$c .= "\n  最新の請求書：" . $m( 'apprex_c_sq_invoice_month' ) . '分・' . $st[0];
				if ( $m( 'apprex_c_sq_invoice_url' ) && 'PAID' !== $m( 'apprex_c_sq_invoice_status' ) ) {
					$c .= '・お支払いページ ' . $m( 'apprex_c_sq_invoice_url' );
				}
				if ( $m( 'apprex_c_sq_paid_at' ) ) {
					$c .= '・入金確認 ' . $m( 'apprex_c_sq_paid_at' );
				}
			}
			$lines[] = $c;
		}
		$stage = (int) get_user_meta( $user->ID, 'apprex_studio_stage', true );
		$orig  = json_decode( (string) get_user_meta( $user->ID, 'apprex_orig_spec', true ), true );
		if ( $stage || ! empty( $orig['name'] ) ) {
			$lines[] = 'AIスタジオ：利用中' . ( ! empty( $orig['name'] ) ? '・試作「' . $orig['name'] . '」' : '' );
		}
	}
	// ミーティング（LINEで予約したもの、または会員のメール）。
	$or = array( array( 'key' => 'apprex_line_uid', 'value' => $uid ) );
	if ( $email ) {
		$or[] = array( 'key' => 'apprex_email', 'value' => $email );
	}
	$mt = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'any',
			'posts_per_page' => 5,
			'fields'         => 'ids',
			'meta_query'     => array_merge( array( 'relation' => 'OR' ), $or ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	foreach ( $mt as $id ) {
		$at = (int) get_post_meta( $id, 'apprex_meeting_at', true );
		if ( $at && $at > time() - DAY_IN_SECONDS && ! get_post_meta( $id, 'apprex_mb_cancel', true ) ) {
			$lines[] = 'オンライン相談の予約：' . ( function_exists( 'apprex_mb_when' ) ? apprex_mb_when( $at ) : wp_date( 'n月j日 G:i', $at ) );
		}
		if ( ! $user && ! $email ) {
			$nm = (string) get_post_meta( $id, 'apprex_name', true );
			if ( $nm ) {
				array_unshift( $lines, 'お名前：' . $nm . ' 様（LINEで予約したお客様・会員とはまだ連携していない）' );
				$email = '-';
			}
		}
	}
	if ( ! $lines ) {
		return "このお客様は、まだ会員とLINEがつながっていない（契約やお支払いの情報は分からない）。\n"
			. '契約・お支払いのことを聞かれたら、トーク画面下のメニューの「会員マイページ」を開くと会員情報とつながり、LINEで確認できるようになると案内する（' . apprex_line_mypage_link() . '）。';
	}
	$lines[] = 'マイページ（LINEで開く）：' . apprex_line_mypage_link();
	return implode( "\n", $lines );
}

/** AIへの指示（会員に合わせた対応と、担当者への依頼）。 */
function apprex_line_member_rules() {
	return "# このお客様への対応\n"
		. "- 下の「このお客様の情報」だけを根拠に、契約・お支払い・予約のことを答える。書かれていないことは推測しない。\n"
		. "- お支払いを聞かれたら、未払いの請求書があればそのお支払いページのURLを伝える（カードで支払える）。支払済みならそう伝える。\n"
		. "- プラン変更・解約・請求書の再発行・予約の変更・アプリの不具合など、担当者の対応が必要な依頼は「担当者に伝えました。平日10:00〜18:00に、このLINEかメールでご連絡します」と答え、"
		. "返答のいちばん最後の行に「##依頼：（依頼の内容を40字以内で）」と1行だけ書く（この行はお客様には表示されない）。\n"
		. "- 個人情報（メールアドレスなど）は、本人の情報でも必要なとき以外は書かない。";
}

/**
 * AIの返答から「##依頼：」を取り出して担当者へ知らせ、お客様に見せる本文を返す。
 *
 * @param string $reply AIの返答。
 * @param string $uid   LINE userId。
 * @param string $asked お客様のメッセージ。
 * @return string
 */
function apprex_line_take_request( $reply, $uid, $asked ) {
	if ( ! preg_match( '/^\s*##\s*依頼[：:]\s*(.+)$/mu', (string) $reply, $m ) ) {
		return $reply;
	}
	$what = trim( $m[1] );
	$body = trim( preg_replace( '/^\s*##\s*依頼[：:].*$/mu', '', (string) $reply ) );
	$user = apprex_line_member( $uid );
	$who  = $user ? $user->display_name . '（' . $user->user_email . '）' : 'LINEのお客様（userId ' . substr( $uid, 0, 10 ) . '…）';
	$log  = get_option( 'apprex_line_requests', array() );
	$log  = is_array( $log ) ? $log : array();
	array_unshift( $log, array( 't' => time(), 'uid' => $uid, 'user' => $user ? $user->ID : 0, 'who' => $who, 'what' => $what, 'msg' => mb_substr( $asked, 0, 300 ), 'done' => 0 ) );
	update_option( 'apprex_line_requests', array_slice( $log, 0, 100 ), false );
	$to = function_exists( 'apprex_notify_to' ) ? apprex_notify_to() : get_option( 'admin_email' );
	wp_mail(
		$to,
		'【APPREX】LINEでご依頼：' . $what,
		"LINEでお客様から依頼がありました。\n\nお客様：{$who}\n依頼：{$what}\n\nお客様のメッセージ：\n{$asked}\n\n一覧：" . admin_url( 'options-general.php?page=apprex-line#apprex-line-requests' )
	);
	if ( function_exists( 'apprex_slack_notify' ) ) {
		apprex_slack_notify( ':speech_balloon: LINEでご依頼：' . $what . '（' . $who . '）' );
	}
	return $body;
}

/* AIが返信しなかったとき（AIオフなど）も、お知らせがあれば返信で届ける。 */
add_action( 'apprex_line_event', function ( $ev, $uid, $type ) {
	if ( 'message' !== $type || ! empty( $GLOBALS['apprex_line_replied'] ) || ! function_exists( 'apprex_line_reply' ) ) {
		return;
	}
	$msg = apprex_line_queue_message( $uid );
	$rt  = isset( $ev['replyToken'] ) ? sanitize_text_field( $ev['replyToken'] ) : '';
	if ( $msg && '' !== $rt && true === apprex_line_reply( $rt, array( $msg ) ) ) {
		apprex_line_queue_clear( $uid );
		$GLOBALS['apprex_line_replied'] = true;
	}
}, 30, 3 );

/* -------------------------------------------------------------------------
 * マイページ：LINEと連携
 * ---------------------------------------------------------------------- */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_user_logged_in() || ! function_exists( 'apprex_mypage_url' ) || ! is_page( 'mypage' ) || ! in_the_loop() ) {
		return $content;
	}
	$linked = '' !== (string) get_user_meta( get_current_user_id(), 'apprex_line_uid', true );
	$box    = '<div class="apx-line-link" style="border:1px solid #c9ecd5;background:#f2fbf5;border-radius:12px;padding:16px 18px;margin:0 0 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">'
		. '<span style="background:#06c755;color:#fff;font-weight:700;border-radius:8px;padding:4px 10px;font-size:13px">LINE</span>';
	if ( $linked ) {
		$box .= '<div style="flex:1;min-width:220px"><b>LINEと連携しています</b><br><small style="color:#4b5563">契約・お支払い・予約のことは、公式LINEで聞くとAIがすぐお答えします。お知らせもLINEで確認できます。</small></div>';
	} elseif ( function_exists( 'apprex_liff_ready' ) && apprex_liff_ready() ) {
		$box .= '<div style="flex:1;min-width:220px"><b>LINEと連携しませんか？</b><br><small style="color:#4b5563">連携すると、契約・お支払い・予約の確認やご依頼をLINEでできます（スマホで押してください）。</small></div>'
			. '<a href="' . esc_url( apprex_line_mypage_link() ) . '" style="background:#06c755;color:#fff;text-decoration:none;font-weight:700;border-radius:999px;padding:10px 20px">LINEと連携する</a>';
	} else {
		return $content;
	}
	return $box . '</div>' . $content;
}, 5 );

/* -------------------------------------------------------------------------
 * 設定（APPREX 配信(LINE) に相乗り）
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	register_setting( 'apprex_line_dist', 'apprex_line_save_mode', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_line_dist', 'apprex_line_save_keep_1h', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_line_dist', 'apprex_line_member_ai', array( 'sanitize_callback' => 'absint' ) );
} );

/** 会員に合わせてAIが答えるか。 */
function apprex_line_member_ai() {
	return (bool) get_option( 'apprex_line_member_ai', 1 );
}

/* 依頼の「対応済み」 */
add_action( 'admin_post_apprex_line_req_done', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_line_req_done' );
	$t   = isset( $_GET['t'] ) ? (int) $_GET['t'] : 0;
	$log = get_option( 'apprex_line_requests', array() );
	foreach ( is_array( $log ) ? $log : array() as $i => $r ) {
		if ( (int) $r['t'] === $t ) {
			$log[ $i ]['done'] = 1;
		}
	}
	update_option( 'apprex_line_requests', $log, false );
	wp_safe_redirect( admin_url( 'options-general.php?page=apprex-line#apprex-line-requests' ) );
	exit;
} );

/** 設定画面の表示（inc/line-direct.php の設定ページから呼ぶ）。 */
function apprex_line_member_settings() {
	$log = get_option( 'apprex_line_requests', array() );
	$log = is_array( $log ) ? $log : array();
	?>
	<h2 id="apprex-line-member">LINEと会員の連動</h2>
	<table class="form-table">
		<tr>
			<th scope="row">LINEの送信数を使わない</th>
			<td>
				<label><input type="checkbox" name="apprex_line_save_mode" value="1" <?php checked( apprex_line_save_mode() ); ?>> ミーティング・説明会の自動のお知らせや、メールを送ったお知らせは、LINEに送らずにためておき、お客様が次に話しかけたときの返信でまとめて届ける</label>
				<p class="description">LINEは「こちらから送る」と月の送信数に数えられ、「お客様の話しかけに返す」は数えられません。メールのお知らせはこれまでどおり送ります。</p>
				<label style="display:block;margin-top:6px"><input type="checkbox" name="apprex_line_save_keep_1h" value="1" <?php checked( get_option( 'apprex_line_save_keep_1h', 1 ) ); ?>> ただし、ミーティングの1時間前のお知らせだけはLINEで送る（1回の予約で1通）</label>
			</td>
		</tr>
		<tr>
			<th scope="row">会員に合わせてAIが答える</th>
			<td>
				<label><input type="checkbox" name="apprex_line_member_ai" value="1" <?php checked( apprex_line_member_ai() ); ?>> LINE連携済みの会員には、契約・お支払い・予約の情報を見てAIが答える。変更・解約などの依頼は受け付けて、担当者へメールで知らせる</label>
				<p class="description">ステップ配信・記事の配信は、ここの設定とは別です（それぞれの設定のとおりLINEで送ります）。</p>
			</td>
		</tr>
	</table>
	<h3 id="apprex-line-requests">LINEで届いたご依頼</h3>
	<?php if ( ! $log ) : ?>
		<p class="description">まだありません。</p>
	<?php else : ?>
		<table class="widefat striped" style="max-width:980px">
			<thead><tr><th>日時</th><th>お客様</th><th>ご依頼</th><th>メッセージ</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( array_slice( $log, 0, 30 ) as $r ) : ?>
				<tr<?php echo $r['done'] ? ' style="opacity:.55"' : ''; ?>>
					<td><?php echo esc_html( wp_date( 'n/j G:i', (int) $r['t'] ) ); ?></td>
					<td><?php echo esc_html( $r['who'] ); ?></td>
					<td><b><?php echo esc_html( $r['what'] ); ?></b></td>
					<td><?php echo esc_html( $r['msg'] ); ?></td>
					<td><?php echo $r['done'] ? '対応済み' : '<a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apprex_line_req_done&t=' . (int) $r['t'] ), 'apprex_line_req_done' ) ) . '">対応済みにする</a>'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}
