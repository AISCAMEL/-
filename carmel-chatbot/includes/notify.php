<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 管理者向け即時通知：重要イベントを Slack / メールへ通知
 * イベント：
 *  - lead_apply      : 審査申込みが送信された
 *  - lead_contact    : お問い合わせが送信された
 *  - apply_click     : 「仮審査を申し込む」ボタンが押された（潜在リード）
 *  - convo_started   : お名前・メール入力完了（新規会話開始）
 *  - handoff_request : 担当者相談ボタンが押された（営業時間内）
 *  - offhours_notify : 営業時間外の担当者希望
 *  - followup_cv     : 後追いメール送信後に本コンバージョン（担当追跡用ログ）
 *
 * 設定は wp_options → carmel_cb_settings → notify_events （array）
 */

/* ===== 汎用送信ヘルパ ===== */

function carmel_cb_notify_event( $event, $subject, $lines = array(), $meta = array() ) {
	$s = carmel_cb_get_settings();
	$enabled = is_array( $s['notify_events'] ?? null ) ? $s['notify_events'] : array();
	// デフォルト: 全ON（未設定でも通知を漏らさない）
	$is_on = ! isset( $enabled[ $event ] ) || ! empty( $enabled[ $event ] );
	if ( ! $is_on ) { return false; }

	// 冪等性：同一(event,sid) を 60秒以内に連投しない
	$sid = (string) ( $meta['sid'] ?? '' );
	if ( $sid !== '' ) {
		$key = 'ccb_notified_' . md5( $event . '|' . $sid );
		if ( get_transient( $key ) ) { return false; }
		set_transient( $key, 1, 60 );
	}

	$body_lines = $lines;
	$body_lines[] = '';
	$body_lines[] = 'サイト: ' . home_url( '/' );
	$body_lines[] = '時刻: ' . current_time( 'Y-m-d H:i' );
	$body = implode( "\n", array_map( 'strval', $body_lines ) );

	// Slack（Botまたは Webhook）
	if ( function_exists( 'carmel_cb_slack_notify_text' ) ) {
		$emoji = carmel_cb_notify_emoji( $event );
		carmel_cb_slack_notify_text( $s, "{$emoji} *{$subject}*\n```{$body}```" );
	}

	// 管理者メール（複数宛先対応：カンマ区切り）
	$to = trim( (string) ( $s['admin_notify_email'] ?? '' ) );
	if ( $to === '' ) { $to = (string) ( $s['notify_email'] ?? '' ); }
	if ( $to === '' ) { $to = (string) get_option( 'admin_email' ); }
	if ( $to !== '' ) {
		wp_mail(
			array_map( 'trim', explode( ',', $to ) ),
			'[カーメル通知] ' . $subject,
			$body,
			array( 'Content-Type: text/plain; charset=UTF-8' )
		);
	}

	return true;
}

function carmel_cb_notify_emoji( $event ) {
	$map = array(
		'lead_apply'      => '🎯',
		'lead_contact'    => '✉️',
		'apply_click'     => '📝',
		'convo_started'   => '👤',
		'handoff_request' => '🙋',
		'offhours_notify' => '🌙',
		'followup_cv'     => '🏆',
	);
	return $map[ $event ] ?? '🔔';
}

/* ===== イベントフック ===== */

// ① リード送信（審査/問い合わせ）
add_action( 'carmel_cb_lead_submitted', function ( $type, $data ) {
	$name  = (string) ( $data['name'] ?? '' );
	$email = (string) ( $data['email'] ?? '' );
	$tel   = (string) ( $data['tel'] ?? '' );
	$note  = (string) ( $data['note'] ?? '' );
	if ( $type === 'apply' ) {
		carmel_cb_notify_event( 'lead_apply', '審査申込みが届きました', array(
			'お名前: ' . $name,
			'メール: ' . $email,
			'電話  : ' . $tel,
			'希望  : ' . ( $data['wish'] ?? '' ),
			'ご相談: ' . mb_substr( $note, 0, 300 ),
		), array( 'sid' => 'lead-apply-' . $email . '-' . time() ) );

		// 後追いメールが既に送信されている顧客 → CV通知（担当者に「これは追跡していた顧客だ」を知らせる）
		if ( function_exists( 'carmel_cb_apply_table_exists' ) && carmel_cb_apply_table_exists() ) {
			global $wpdb;
			$t = carmel_cb_apply_table();
			$was_tracked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT stage FROM $t WHERE email=%s AND stage >= 1", strtolower( $email ) ) );
			if ( $was_tracked > 0 ) {
				carmel_cb_notify_event( 'followup_cv', '🏆 後追いメール経由でCV！', array(
					'お名前: ' . $name,
					'メール: ' . $email,
					'種別  : 審査申込み',
					'追跡ステージ: ' . $was_tracked . '/3',
					'→ 過去に離脱 → 後追いメール → 今回申込 の流れです',
				), array( 'sid' => 'cv-' . $email . '-' . time() ) );
			}
		}
	} else {
		carmel_cb_notify_event( 'lead_contact', 'お問い合わせが届きました', array(
			'お名前: ' . $name,
			'メール: ' . $email,
			'電話  : ' . $tel,
			'内容  : ' . mb_substr( $note, 0, 300 ),
		), array( 'sid' => 'lead-contact-' . $email . '-' . time() ) );
	}
}, 30, 2 );

// ② 審査ボタン押下（潜在リード）— apply-click エンドポイントから呼ばれる
add_action( 'carmel_cb_apply_click_recorded', function ( $email, $name, $sid, $page ) {
	carmel_cb_notify_event( 'apply_click', '審査ボタンが押されました（フォーム離脱の可能性）', array(
		'お名前: ' . $name,
		'メール: ' . $email,
		'ページ: ' . $page,
		'※ 30分以内にフォーム送信が無ければ後追いメールが起動します',
	), array( 'sid' => 'apply-click-' . $sid ) );
}, 10, 4 );

// ③ 会話開始（お名前・メール入力完了）
add_action( 'carmel_cb_convo_started', function ( $email, $name, $sid, $page ) {
	carmel_cb_notify_event( 'convo_started', '新しいお客様がチャットを開始', array(
		'お名前: ' . $name,
		'メール: ' . $email,
		'ページ: ' . $page,
	), array( 'sid' => 'convo-start-' . $sid ) );
}, 10, 4 );

// ④ 担当者相談ボタン
add_action( 'carmel_cb_handoff_requested', function ( $mode, $sid, $q, $email = '' ) {
	$evt = ( $mode === 'offhours' ) ? 'offhours_notify' : 'handoff_request';
	$title = ( $mode === 'offhours' ) ? '営業時間外の担当者希望' : '担当者相談ボタンが押されました';
	carmel_cb_notify_event( $evt, $title, array(
		'メール: ' . $email,
		'ご相談: ' . mb_substr( (string) $q, 0, 300 ),
		'モード: ' . $mode,
	), array( 'sid' => 'handoff-' . $sid ) );
}, 10, 4 );
