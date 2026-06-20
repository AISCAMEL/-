<?php
/**
 * 契約ライフサイクル（フェーズB）。
 *
 * - 更新リマインダー：更新日の3ヶ月前→2ヶ月前→1ヶ月前（1ヶ月周期）→2週間前→1週間前→前日（最後は短め）
 * - メール内に「契約更新」「解約」の2ボタン（トークン付きリンク）
 * - 「更新」クリック → 自動で+契約年数 延長／「解約」クリック → 解約申請＋Slack通知
 * - 自動継続ONなら更新日到来で自動延長、OFFなら期限切れ→Slack通知
 * - 毎月のお支払い通知（Square自動課金 ／ 請求書）
 * - 延滞（最終入金確認日が一定期間更新されない＝消し込みなし）→ 管理者へSlack通知＋確認メール
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 更新リマインダーのスケジュール（更新日の何日前か）
 * ---------------------------------------------------------------------- */
function apprex_renewal_reminders() {
	return apply_filters(
		'apprex_renewal_reminders',
		array(
			'd90' => 90, // 3ヶ月前
			'd60' => 60, // 2ヶ月前
			'd30' => 30, // 1ヶ月前
			'd14' => 14, // 2週間前（ここから短め）
			'd7'  => 7,  // 1週間前
			'd1'  => 1,  // 前日
		)
	);
}

/* -------------------------------------------------------------------------
 * トークン付きアクションURL（更新 / 解約）
 * ---------------------------------------------------------------------- */
function apprex_contract_token( $id, $action ) {
	return wp_hash( $id . '|' . $action . '|' . get_post_meta( $id, 'apprex_c_email', true ) );
}

function apprex_contract_action_url( $id, $action ) {
	return add_query_arg(
		array(
			'apprex_contract' => $id,
			'do'              => $action,
			'token'           => apprex_contract_token( $id, $action ),
		),
		home_url( '/' )
	);
}

/** 顧客がクリックしたアクションを処理。 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['apprex_contract'] ) || empty( $_GET['do'] ) || empty( $_GET['token'] ) ) {
		return;
	}
	$id    = absint( $_GET['apprex_contract'] );
	$do    = sanitize_key( wp_unslash( $_GET['do'] ) );
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );

	if ( 'apprex_contract' !== get_post_type( $id ) ) {
		wp_die( 'リンクが無効です。', '契約', array( 'response' => 400 ) );
	}
	if ( ! hash_equals( apprex_contract_token( $id, $do ), $token ) ) {
		wp_die( 'リンクが無効か、期限切れです。', '契約', array( 'response' => 400 ) );
	}

	if ( 'renew' === $do ) {
		apprex_contract_do_renew( $id, 'お客様' );
		$new = get_post_meta( $id, 'apprex_c_renewal', true );
		wp_die( '契約を更新しました。次回更新日は ' . esc_html( $new ) . ' です。ありがとうございます。', '契約更新', array( 'response' => 200 ) );
	}
	if ( 'cancel' === $do ) {
		apprex_contract_do_cancel( $id, 'お客様' );
		wp_die( '解約のお申し出を受け付けました。担当者より確認のご連絡を差し上げます。', '解約申請', array( 'response' => 200 ) );
	}
	wp_die( '不明な操作です。', '契約', array( 'response' => 400 ) );
} );

/* -------------------------------------------------------------------------
 * 更新 / 解約の実処理
 * ---------------------------------------------------------------------- */
function apprex_contract_do_renew( $id, $by = 'system' ) {
	$renewal = get_post_meta( $id, 'apprex_c_renewal', true );
	$term    = max( 1, (int) get_post_meta( $id, 'apprex_c_term', true ) );
	$base    = $renewal ? strtotime( $renewal ) : current_time( 'timestamp' );
	if ( $base < current_time( 'timestamp' ) ) {
		$base = current_time( 'timestamp' ); // 過去日にならないよう今日基準で延長。
	}
	$new = wp_date( 'Y-m-d', strtotime( '+' . $term . ' year', $base ) );

	update_post_meta( $id, 'apprex_c_renewal', $new );
	update_post_meta( $id, 'apprex_c_status', 'active' );
	update_post_meta( $id, 'apprex_c_renewal_sent', array() ); // 次サイクルのリマインダーを再有効化。
	apprex_sync_contract_to_gas( $id );

	$name = get_post_meta( $id, 'apprex_c_name', true );
	if ( function_exists( 'apprex_slack_notify' ) ) {
		apprex_slack_notify( ":white_check_mark: 契約更新（{$by}）：{$name} → 次回更新 {$new}" );
	}
	$email = get_post_meta( $id, 'apprex_c_email', true );
	if ( is_email( $email ) ) {
		$body = "{$name} 様\n\nご契約を1年間更新いたしました。次回更新日は {$new} です。\n引き続きAPPREXをよろしくお願いいたします。\n";
		$html = apprex_render_email( '【APPREX】契約更新ありがとうございます', $body, array( 'heading' => '契約更新ありがとうございます' ) );
		wp_mail( $email, '【APPREX】契約更新ありがとうございます', $html, apprex_mail_headers() );
	}
}

function apprex_contract_do_cancel( $id, $by = 'system' ) {
	update_post_meta( $id, 'apprex_c_status', 'cancelled' );
	update_post_meta( $id, 'apprex_c_autorenew', 0 );
	apprex_sync_contract_to_gas( $id );

	$name = get_post_meta( $id, 'apprex_c_name', true );
	if ( function_exists( 'apprex_slack_notify' ) ) {
		apprex_slack_notify( ":warning: 解約申請（{$by}）：{$name} 対応をお願いします。 " . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	}
	$email = get_post_meta( $id, 'apprex_c_email', true );
	if ( is_email( $email ) ) {
		$body = "{$name} 様\n\n解約のお申し出を受け付けました。担当者より確認のご連絡を差し上げます。\nご利用ありがとうございました。\n";
		$html = apprex_render_email( '【APPREX】解約のお手続きを受け付けました', $body, array( 'heading' => '解約のお手続きを受け付けました' ) );
		wp_mail( $email, '【APPREX】解約のお手続きを受け付けました', $html, apprex_mail_headers() );
	}
}

/* -------------------------------------------------------------------------
 * 更新リマインダーメール（更新・解約の2ボタン）
 * ---------------------------------------------------------------------- */
function apprex_send_renewal_reminder( $id, $days_left ) {
	$email = get_post_meta( $id, 'apprex_c_email', true );
	if ( ! is_email( $email ) ) {
		return;
	}
	$name    = get_post_meta( $id, 'apprex_c_name', true );
	$renewal = get_post_meta( $id, 'apprex_c_renewal', true );
	$plan    = trim( get_post_meta( $id, 'apprex_c_service', true ) . ' ' . get_post_meta( $id, 'apprex_c_plan', true ) );

	$intro  = "{$name} 様\n\nいつもAPPREXをご利用いただきありがとうございます。\n";
	$intro .= "ご契約（{$plan}）の更新日が {$renewal}";
	$intro .= ( $days_left > 0 ) ? "（約{$days_left}日後）" : '';
	$intro .= " に近づいています。\n下記より「契約更新」または「解約」をお選びください。";

	$content  = apprex_text_to_html( $intro );
	$content .= apprex_email_button( apprex_contract_action_url( $id, 'renew' ), '契約を更新する（+1年）' );
	$content .= apprex_email_button( apprex_contract_action_url( $id, 'cancel' ), '解約する' );
	if ( (int) get_post_meta( $id, 'apprex_c_autorenew', true ) ) {
		$content .= '<p style="font-size:13px;color:#6b7280;">※ 自動継続が有効です。お手続きがなければ更新日に自動更新されます。解約をご希望の場合は上の「解約する」をお選びください。</p>';
	}

	$subject = "【APPREX】ご契約更新のご案内（更新日 {$renewal}）";
	$html    = apprex_email_wrap( $subject, $content, array( 'heading' => 'ご契約更新のご案内' ) );
	wp_mail( $email, $subject, $html, apprex_mail_headers() );
}

/* -------------------------------------------------------------------------
 * 毎月のお支払い通知（Square自動課金 / 請求書）
 * ---------------------------------------------------------------------- */
function apprex_send_payment_notice( $id ) {
	// Square 請求書自動発行が処理したら、通常の通知メールは送らない。
	if ( apply_filters( 'apprex_send_payment_via_square', false, $id ) ) {
		return;
	}
	// マネーフォワード クラウド請求書（振込）で発行したら、通常の通知メールは送らない。
	if ( apply_filters( 'apprex_send_payment_via_mf', false, $id ) ) {
		return;
	}
	$email = get_post_meta( $id, 'apprex_c_email', true );
	if ( ! is_email( $email ) ) {
		return;
	}
	$name    = get_post_meta( $id, 'apprex_c_name', true );
	$monthly = (int) get_post_meta( $id, 'apprex_c_monthly', true );
	$day     = (int) get_post_meta( $id, 'apprex_c_payment_day', true );
	$method  = get_post_meta( $id, 'apprex_c_payment_method', true );

	if ( 'invoice' === $method ) {
		$subject = '【APPREX】今月のご請求について';
		$body    = "{$name} 様\n\n今月分のご利用料金（月額 " . number_format( $monthly ) . "円・税抜）の請求書をお送りいたします。\nお手数ですが毎月{$day}日までにお振込をお願いいたします。\n行き違いでお振込済みの場合はご容赦ください。\n";
	} else {
		$subject = '【APPREX】今月のお支払い（自動課金）のご案内';
		$body    = "{$name} 様\n\n今月分のご利用料金（月額 " . number_format( $monthly ) . "円・税抜）は、ご登録のカードへ毎月{$day}日に Square で自動課金されます。\n明細のご確認をお願いいたします。\n";
	}
	$html = apprex_render_email( $subject, $body, array( 'heading' => apprex_email_heading_from_subject( $subject ) ) );
	wp_mail( $email, $subject, $html, apprex_mail_headers() );
}

/* -------------------------------------------------------------------------
 * 日次バッチ：更新リマインダー / 自動更新・期限 / 支払い通知 / 延滞チェック
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_contract_cron' ) ) {
		wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'daily', 'apprex_contract_cron' );
	}
} );
add_action( 'apprex_contract_cron', 'apprex_process_contracts' );

function apprex_process_contracts() {
	$ids = apprex_get_contracts();
	if ( empty( $ids ) ) {
		return;
	}
	$now = current_time( 'timestamp' );
	$ym  = current_time( 'Y-m' );
	$dom = (int) current_time( 'j' );

	foreach ( $ids as $id ) {
		$status = get_post_meta( $id, 'apprex_c_status', true );
		if ( 'cancelled' === $status ) {
			continue;
		}
		$email = get_post_meta( $id, 'apprex_c_email', true );
		if ( ! is_email( $email ) ) {
			continue;
		}

		/* --- 更新リマインダー --- */
		$renewal = get_post_meta( $id, 'apprex_c_renewal', true );
		if ( $renewal ) {
			$rts  = strtotime( $renewal );
			$sent = (array) get_post_meta( $id, 'apprex_c_renewal_sent', true );
			foreach ( apprex_renewal_reminders() as $key => $days ) {
				if ( in_array( $key, $sent, true ) ) {
					continue;
				}
				if ( $now >= $rts - $days * DAY_IN_SECONDS ) {
					if ( $now <= $rts + 7 * DAY_IN_SECONDS ) { // 大幅に過ぎた分は送らない。
						apprex_send_renewal_reminder( $id, max( 0, (int) ceil( ( $rts - $now ) / DAY_IN_SECONDS ) ) );
					}
					$sent[] = $key;
					update_post_meta( $id, 'apprex_c_renewal_sent', $sent );
				}
			}

			/* --- 更新日到来：自動更新 or 期限切れ --- */
			if ( $now >= $rts ) {
				if ( (int) get_post_meta( $id, 'apprex_c_autorenew', true ) ) {
					apprex_contract_do_renew( $id, '自動' );
					$status = 'active';
				} elseif ( 'pending' !== $status ) {
					update_post_meta( $id, 'apprex_c_status', 'pending' );
					apprex_sync_contract_to_gas( $id );
					if ( function_exists( 'apprex_slack_notify' ) ) {
						apprex_slack_notify( ':hourglass: 契約期限到来（自動継続OFF）：' . get_post_meta( $id, 'apprex_c_name', true ) . ' 対応要 ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
					}
					$status = 'pending';
				}
			}
		}

		/* --- 毎月のお支払い通知（支払い期日の3日前から） --- */
		if ( 'active' === $status ) {
			$pday = (int) get_post_meta( $id, 'apprex_c_payment_day', true );
			$pday = $pday ? $pday : 27;
			if ( $dom >= max( 1, $pday - 3 ) && get_post_meta( $id, 'apprex_c_payment_notice_month', true ) !== $ym ) {
				apprex_send_payment_notice( $id );
				update_post_meta( $id, 'apprex_c_payment_notice_month', $ym );
			}
		}

		/* --- 延滞（消し込みが無い） --- */
		$last_paid = get_post_meta( $id, 'apprex_c_last_paid', true );
		if ( 'active' === $status && $last_paid ) {
			$lts = strtotime( $last_paid );
			if ( $lts && $now > $lts + 38 * DAY_IN_SECONDS && get_post_meta( $id, 'apprex_c_overdue_month', true ) !== $ym ) {
				update_post_meta( $id, 'apprex_c_overdue_month', $ym );
				$name = get_post_meta( $id, 'apprex_c_name', true );
				if ( function_exists( 'apprex_slack_notify' ) ) {
					apprex_slack_notify( ":rotating_light: 入金未確認（延滞の可能性）：{$name} ／ 最終入金確認 {$last_paid}。消し込み確認をお願いします。 " . admin_url( 'post.php?post=' . $id . '&action=edit' ) );
				}
				$body = "{$name} 様\n\n恐れ入ります。今月分のご入金が確認できておりません。\nお手続きがお済みでない場合は、ご確認をお願いいたします。行き違いの場合は何卒ご容赦ください。\n";
				$html = apprex_render_email( '【APPREX】お支払い確認のお願い', $body, array( 'heading' => 'お支払い確認のお願い' ) );
				wp_mail( $email, '【APPREX】お支払い確認のお願い', $html, apprex_mail_headers() );
			}
		}
	}
}

/* -------------------------------------------------------------------------
 * 契約一覧に「今すぐ実行（テスト）」ボタン
 * ---------------------------------------------------------------------- */
add_action( 'admin_notices', function () {
	$s = get_current_screen();
	if ( ! $s || 'edit-apprex_contract' !== $s->id ) {
		return;
	}
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_contract_run_now' ), 'apprex_contract_run_now' );
	echo '<div class="notice notice-info"><p>契約の更新リマインダー・支払い通知・延滞チェックは毎日自動実行されます。 <a href="' . esc_url( $url ) . '" class="button">今すぐ実行（テスト）</a></p></div>';
} );

add_action( 'admin_post_apprex_contract_run_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_contract_run_now' );
	apprex_process_contracts();
	wp_safe_redirect( add_query_arg( 'apprex_cron', 'ran', admin_url( 'edit.php?post_type=apprex_contract' ) ) );
	exit;
} );

/** テーマ切替時に契約cronを解除。 */
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'apprex_contract_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'apprex_contract_cron' );
	}
} );
