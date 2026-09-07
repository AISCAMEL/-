<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🎯 キャンペーン誘導
 * - 管理画面で有効期間・タイトル・本文・URLを設定
 * - 期間内なら AIプロンプトにキャンペーン情報を注入して会話中で自然に案内させる
 * - チャット冒頭にバナー表示するオプション
 */

/** キャンペーンが現在アクティブか（有効かつ期間内） */
function carmel_cb_campaign_is_active( $s = null ) {
	$s = $s ?: carmel_cb_get_settings();
	if ( empty( $s['campaign_on'] ) ) { return false; }
	if ( trim( (string) ( $s['campaign_title'] ?? '' ) ) === '' ) { return false; }

	$today = current_time( 'Y-m-d' );
	$start = trim( (string) ( $s['campaign_start'] ?? '' ) );
	$end   = trim( (string) ( $s['campaign_end'] ?? '' ) );
	if ( $start !== '' && $today < $start ) { return false; }
	if ( $end   !== '' && $today > $end   ) { return false; }
	return true;
}

/** キャンペーン情報を配列で返す。無ければ null。 */
function carmel_cb_campaign_data( $s = null ) {
	$s = $s ?: carmel_cb_get_settings();
	if ( ! carmel_cb_campaign_is_active( $s ) ) { return null; }
	return array(
		'title' => (string) ( $s['campaign_title'] ?? '' ),
		'body'  => (string) ( $s['campaign_body']  ?? '' ),
		'url'   => (string) ( $s['campaign_url']   ?? '' ),
		'start' => (string) ( $s['campaign_start'] ?? '' ),
		'end'   => (string) ( $s['campaign_end']   ?? '' ),
		'hint'  => (string) ( $s['campaign_ai_hint'] ?? '' ),
		'showBanner' => ! empty( $s['campaign_show_banner'] ),
	);
}

/** AIシステムプロンプトに追記する文字列（アクティブなら返す、無ければ空）。 */
function carmel_cb_campaign_prompt_block( $s = null ) {
	$c = carmel_cb_campaign_data( $s );
	if ( ! $c ) { return ''; }
	$period = '';
	if ( $c['start'] !== '' || $c['end'] !== '' ) {
		$period = "（期間: " . ( $c['start'] !== '' ? $c['start'] : '〜' ) . '〜' . ( $c['end'] !== '' ? $c['end'] : '無期限' ) . '）';
	}
	$url = $c['url'] !== '' ? "\n詳細URL（本文には貼らず、必要ならボタンで案内される）: {$c['url']}" : '';
	$hint = $c['hint'] !== '' ? "\n案内方針: {$c['hint']}" : '';
	return "\n\n【現在のキャンペーン】{$period}\nタイトル: {$c['title']}\n内容: {$c['body']}{$url}{$hint}\n※お客様の相談内容と関連しない場面では無理に案内しない。過度な連呼は禁止。";
}
