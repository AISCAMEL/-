<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * チャット内フォーム（かんたん審査 / お問い合わせ）の受付。
 * - お客様がチャットを離れずに連絡先を送信 → DB保存＋担当者へ通知（メール / Slack / LINE WORKS）。
 * - 通知は有人対応(handoff.php)と同じ経路を再利用する。
 */

/** フォーム種別ごとのラベル。 */
function carmel_cb_lead_labels( $type ) {
	if ( $type === 'apply' ) {
		return array(
			'title'   => 'かんたん審査申し込み',
			'subject' => '[カーメル相談AI] かんたん審査のお申し込み',
			'thanks'  => 'お申し込みありがとうございます！内容を担当者に送信しました。折り返しご連絡し、無理のないお支払いプランをご案内します😊',
		);
	}
	return array(
		'title'   => 'お問い合わせ',
		'subject' => '[カーメル相談AI] お問い合わせ',
		'thanks'  => 'お問い合わせありがとうございます！担当者に送信しました。順次ご連絡いたします😊',
	);
}

/** 担当者へ通知（メール / Slack Webhook / LINE WORKS）。 */
function carmel_cb_lead_notify( $s, $p ) {
	$labels = carmel_cb_lead_labels( $p['type'] );
	$within = carmel_cb_within_hours( $s );
	$status = $within ? '【営業時間内】お早めにご対応ください' : '【時間外】翌営業日にご連絡ください';

	$to      = ! empty( $s['notify_email'] ) ? $s['notify_email'] : get_option( 'admin_email' );
	$subject = $labels['subject'] . ' ' . $status;

	$body  = $labels['title'] . "のお客様です。\n" . $status . "\n\n";
	$body .= "お名前　 : " . $p['name'] . "\n";
	$body .= "電話　　 : " . $p['tel'] . "\n";
	$body .= "メール　 : " . $p['email'] . "\n";
	if ( $p['wish'] !== '' ) { $body .= "ご希望　 : " . $p['wish'] . "\n"; }
	if ( $p['note'] !== '' ) { $body .= "ご相談　 : " . $p['note'] . "\n"; }
	$body .= "受付日時 : " . current_time( 'Y-m-d H:i' ) . "\n";
	$body .= "ページ　 : " . $p['page'] . "\n\n";
	$body .= "--- 直近の会話 ---\n" . $p['transcript'] . "\n";

	wp_mail( $to, $subject, $body );

	if ( function_exists( 'carmel_cb_slack_notify_text' ) ) {
		carmel_cb_slack_notify_text( $s, "*{$subject}*\n```{$body}```" );
	}
	if ( function_exists( 'carmel_cb_lineworks_notify' ) ) {
		carmel_cb_lineworks_notify( $s, $subject . "\n\n" . $body );
	}
}

/* ============================ REST ============================ */

add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/lead', array(
		'methods'             => 'POST',
		'callback'            => 'carmel_cb_handle_lead',
		'permission_callback' => '__return_true',
	) );
} );

function carmel_cb_handle_lead( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();
	$b = $request->get_json_params();

	$type = ( ( $b['type'] ?? '' ) === 'apply' ) ? 'apply' : 'contact';
	$p = array(
		'type'       => $type,
		'name'       => sanitize_text_field( $b['name'] ?? '' ),
		'tel'        => sanitize_text_field( $b['tel'] ?? '' ),
		'email'      => sanitize_email( $b['email'] ?? '' ),
		'wish'       => sanitize_textarea_field( $b['wish'] ?? '' ),
		'note'       => sanitize_textarea_field( $b['note'] ?? '' ),
		'page'       => esc_url_raw( $b['page'] ?? '' ),
		'transcript' => carmel_cb_transcript( $b['messages'] ?? array() ),
	);

	// 最低限のバリデーション：お名前＋（電話 or メール）
	if ( $p['name'] === '' || ( $p['tel'] === '' && $p['email'] === '' ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'need_fields' ), 200 );
	}

	carmel_cb_insert_lead( $p );
	carmel_cb_lead_notify( $s, $p );

	$labels = carmel_cb_lead_labels( $type );
	return new WP_REST_Response( array(
		'ok'     => true,
		'thanks' => $labels['thanks'],
		'within' => carmel_cb_within_hours( $s ),
	), 200 );
}
