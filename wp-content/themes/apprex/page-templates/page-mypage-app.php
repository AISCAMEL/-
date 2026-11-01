<?php
/**
 * マイページ（ログイン後）の画面。inc/mypage-app.php の template_include から使う。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
echo apprex_mypage_app(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部でエスケープ済み。
get_footer();
