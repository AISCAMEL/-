<?php
/**
 * 外部連携：GAS（Google Apps Script）Webhook へのイベント送出。
 *
 * お問い合わせ／発注の発生時に、GAS の Web アプリ URL へ JSON を POST する。
 * GAS 側で スプレッドシート記録 → Asana タスク作成 → Slack 通知 を行う想定。
 * （自動返信・ステップメールは WordPress 側で実施）
 *
 * 設定：設定 > APPREX 連携（apprex_gas_webhook_url / apprex_gas_token）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GAS Web アプリ URL。
 *
 * @return string
 */
function apprex_gas_webhook_url() {
	return (string) get_option( 'apprex_gas_webhook_url', '' );
}

/**
 * 改ざん防止用の共有トークン（GAS 側で照合）。
 *
 * @return string
 */
function apprex_gas_token() {
	return (string) get_option( 'apprex_gas_token', '' );
}

/**
 * イベントを GAS に送出（ノンブロッキング）。
 *
 * @param string $event 'inquiry' | 'order'.
 * @param array  $data  ペイロード。
 */
function apprex_dispatch_event( $event, $data ) {
	$url = apprex_gas_webhook_url();
	if ( ! $url ) {
		return;
	}
	$payload = array(
		'event' => $event,
		'token' => apprex_gas_token(),
		'site'  => home_url( '/' ),
		'time'  => current_time( 'mysql' ),
		'data'  => $data,
	);
	$payload = apply_filters( 'apprex_dispatch_payload', $payload, $event, $data );

	wp_remote_post(
		$url,
		array(
			'timeout'  => 8,
			'blocking' => false, // 画面表示をブロックしない。
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $payload ),
		)
	);
}
