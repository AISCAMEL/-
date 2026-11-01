<?php
/**
 * テーマ独自のURL（/labo/ /line-mypage/ /line/webhook など）が消えていたら、自動で登録し直す。
 *
 * それぞれの機能は「最初の1回だけ」URLを登録し直す作りのため、
 * 先に別の機能が登録し直すと、あとの機能のURLが抜けたまま残ることがある（/labo/ が「ページが見つかりません」になる）。
 * すべての機能のURLを足し終えたあと（init の最後）に、抜けがないかを確かめる。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	if ( wp_installing() || ! get_option( 'permalink_structure' ) ) {
		return;
	}
	global $wp_rewrite;
	$saved = get_option( 'rewrite_rules' );
	$saved = is_array( $saved ) ? $saved : array();
	$need  = array_keys( (array) $wp_rewrite->extra_rules_top ); // テーマ・プラグインが足したURL。
	$miss  = array_diff( $need, array_keys( $saved ) );
	if ( ! $miss || get_transient( 'apprex_rw_fixed' ) ) {
		return;
	}
	set_transient( 'apprex_rw_fixed', 1, HOUR_IN_SECONDS ); // 直らない場合も、何度も繰り返さない。
	flush_rewrite_rules( false );
}, 9999 );
