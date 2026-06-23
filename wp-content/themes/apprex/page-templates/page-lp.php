<?php
/**
 * Template Name: LP（広告用・1カラム）
 *
 * ヘッダー/フッター無しの広告用ランディングページ。SNS広告の計測タグ・フォームは
 * inc/lp.php の apprex_lp_render_page() で自動的に組み込まれる。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

while ( have_posts() ) :
	the_post();
	apprex_lp_render_page( get_the_ID() );
endwhile;
