<?php
/**
 * Auto-rotating campaign bar.
 *
 * - 月ごとに文言が自動で切り替わる（apprex_campaign_messages）
 * - 「今月末（M月D日）まで」の締切を自動計算
 * - 文言は option / filter で編集可能
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 今月末の日付ラベル（例：6月30日）。
 *
 * @return string
 */
function apprex_campaign_end_label() {
	$ts  = current_time( 'timestamp' );
	$end = mktime( 0, 0, 0, (int) wp_date( 'n', $ts ) + 1, 0, (int) wp_date( 'Y', $ts ) );
	return wp_date( 'n月j日', $end );
}

/**
 * 月（1-12）ごとのキャンペーン文言。option で上書き可。
 *
 * @return array<int,string>
 */
function apprex_campaign_messages() {
	$base    = '初期費用30万円 → 0円／月額19,800円〜 業界最安水準';
	// 新デザイン時は新しい料金体系（AIスタジオ経由でおまかせ制作が半額）に合わせる。
	if ( function_exists( 'apprex_rn_enabled' ) && apprex_rn_enabled() ) {
		$base = 'AIスタジオから相談で、おまかせ制作が半額（300,000円 → 150,000円）／月額19,800円〜';
	}
	$default = array(
		1  => '新春スタート応援キャンペーン｜' . $base,
		2  => '今だけ特典あり｜' . $base,
		3  => '新年度の準備に｜' . $base,
		4  => '新生活応援キャンペーン｜' . $base,
		5  => '初夏のキャンペーン｜' . $base,
		6  => '今月限定キャンペーン｜' . $base,
		7  => 'サマーキャンペーン｜' . $base,
		8  => '夏の特別キャンペーン｜' . $base,
		9  => '下半期スタート応援｜' . $base,
		10 => '秋のキャンペーン｜' . $base,
		11 => '年末準備キャンペーン｜' . $base,
		12 => '年末感謝キャンペーン｜' . $base,
	);
	$opt = (array) get_option( 'apprex_campaign_messages', array() );
	$opt = array_filter( $opt ); // 空は無視。
	return apply_filters( 'apprex_campaign_messages', array_replace( $default, $opt ) );
}

/**
 * 今月のキャンペーンバー文言（締切ラベル込み）。
 *
 * @return string
 */
function apprex_campaign_text() {
	$month = (int) wp_date( 'n' );
	$msgs  = apprex_campaign_messages();
	$body  = isset( $msgs[ $month ] ) ? $msgs[ $month ] : reset( $msgs );
	$end   = apprex_campaign_end_label();
	$slots = ( function_exists( 'apprex_rn_enabled' ) && apprex_rn_enabled() ) ? '先着10社' : '先着5名様';
	$text  = sprintf( '%sまで限定！%s ｜ %s', $end, $slots, $body );
	return apply_filters( 'apprex_campaign_text', $text, $end, $body, $month );
}
