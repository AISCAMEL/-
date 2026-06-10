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

/**
 * Slack Incoming Webhook URL。
 *
 * @return string
 */
function apprex_slack_webhook_url() {
	return (string) get_option( 'apprex_slack_webhook', '' );
}

/**
 * Slack に通知を送る（Incoming Webhook・ノンブロッキング）。
 *
 * @param string $text   フォールバックテキスト。
 * @param array  $blocks Block Kit（任意）。
 * @return bool 送信を試みたか。
 */
function apprex_slack_notify( $text, $blocks = array() ) {
	$url = apprex_slack_webhook_url();
	if ( ! $url ) {
		return false;
	}
	$body = array( 'text' => $text );
	if ( ! empty( $blocks ) ) {
		$body['blocks'] = $blocks;
	}
	wp_remote_post(
		$url,
		array(
			'timeout'  => 8,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
		)
	);
	return true;
}

/**
 * 記事公開を Slack に投稿（新着NEW通知）。
 *
 * @param array $data { title, url, excerpt, image, ai }.
 */
function apprex_slack_notify_post( $data ) {
	$title   = $data['title'] ?? '';
	$url     = $data['url'] ?? '';
	$excerpt = $data['excerpt'] ?? '';
	$tag     = ! empty( $data['ai'] ) ? ' 🤖AI生成' : '';

	$blocks = array(
		array(
			'type' => 'section',
			'text' => array(
				'type' => 'mrkdwn',
				'text' => "*🆕 新着記事を公開しました{$tag}*\n<{$url}|{$title}>\n{$excerpt}",
			),
		),
		array(
			'type'     => 'actions',
			'elements' => array(
				array(
					'type'  => 'button',
					'text'  => array( 'type' => 'plain_text', 'text' => '記事を見る' ),
					'url'   => $url,
					'style' => 'primary',
				),
			),
		),
	);
	apprex_slack_notify( "🆕 新着記事: {$title} {$url}", $blocks );
}
