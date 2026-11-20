<?php
/**
 * Plugin Name: カーメル 在庫連携
 * Plugin URI:  https://carmelonline.jp/
 * Description: 外部のカーメルシステム（業務ポータル）と在庫をやり取りする「受け口」と、旧プラグイン（CARMEL統合管理・CARMEL自動生成）を止めるための受け皿だけの軽量プラグイン。ホームページの掲載車両をシステムへ取り込み／システムの在庫を車両ページへ反映します。旧プラグインが持っていた記事の入れ物（店舗分類など）と左メニューの整理を引き継ぎます。お客様が見るページの見た目・表示は一切変えません。「カーメルシステム」プラグインを有効にしている場合は、そちらに同じ機能が入っているので不要です（両方有効でも衝突しません）。
 * Version:     1.33.0
 * Author:      CarMel / AIS
 * License:     GPL-2.0+
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 「カーメルシステム」（plugins_loaded 優先度5）が先に同じ受け口を読み込んでいれば、こちらは何もしない。
 */
if ( ! defined( 'CARMEL_INV_BRIDGE_VERSION' ) ) { define( 'CARMEL_INV_BRIDGE_VERSION', '1.33.0' ); }

add_action( 'plugins_loaded', function () {
	$dir = plugin_dir_path( __FILE__ );
	if ( ! function_exists( 'carmelx_invrecv_routes' ) && file_exists( $dir . 'wpcode-inventory-receiver.php' ) ) { require_once $dir . 'wpcode-inventory-receiver.php'; }
	// 診断ページ（ツール → 在庫連携 診断）
	if ( ! function_exists( 'carmelx_invtest_run' ) && file_exists( $dir . 'wpcode-inventory-selftest.php' ) ) { require_once $dir . 'wpcode-inventory-selftest.php'; }
	// 構成調査（ツール → カーメル 構成調査・読み取りのみ）
	if ( ! function_exists( 'carmelx_survey_render' ) && file_exists( $dir . 'wpcode-site-survey.php' ) ) { require_once $dir . 'wpcode-site-survey.php'; }
	// 記事の入れ物を保持（旧プラグインを止めても投稿タイプ・店舗分類を残す。止めた後の定期実行の後片付け）
	if ( ! function_exists( 'carmelx_ctypes_takeover' ) && file_exists( $dir . 'wpcode-content-types.php' ) ) { require_once $dir . 'wpcode-content-types.php'; }
	// 左メニューの整理を引き継ぐ（旧プラグインが隠していた項目だけを隠す。それ以外は変えない）
	if ( ! function_exists( 'carmelx_adminmenu_apply' ) && file_exists( $dir . 'wpcode-admin-menu.php' ) ) {
		if ( ! defined( 'CARMEL_ADMINMENU_INHERIT_ONLY' ) ) { define( 'CARMEL_ADMINMENU_INHERIT_ONLY', true ); }
		require_once $dir . 'wpcode-admin-menu.php';
	}
}, 6 );
