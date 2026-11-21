<?php
/**
 * Plugin Name:       AIS Business Hub
 * Plugin URI:        https://aiscompany.jp/
 * Description:        合同会社アイズ（AIS）公式サイトの管理基盤。事業（CPT）・サイト設定・ホームページ動的描画・各種お問い合わせフォーム・AIコンシェルジュ導線・一括セットアップ（冪等インストーラ）を提供します。テーマ「AIS Premium」と併用。
 * Version:           1.3.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            合同会社アイズ（AIS Co., Ltd.）
 * License:           GPL-2.0-or-later
 * Text Domain:       ais-business-hub
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AIS_HUB_VER', '1.3.2' );
define( 'AIS_HUB_FILE', __FILE__ );
define( 'AIS_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'AIS_HUB_URL', plugin_dir_url( __FILE__ ) );

require_once AIS_HUB_DIR . 'includes/data.php';
require_once AIS_HUB_DIR . 'includes/cpt.php';
require_once AIS_HUB_DIR . 'includes/settings.php';
require_once AIS_HUB_DIR . 'includes/render.php';
require_once AIS_HUB_DIR . 'includes/forms.php';
require_once AIS_HUB_DIR . 'includes/installer.php';
require_once AIS_HUB_DIR . 'includes/ai-editorial.php';
require_once AIS_HUB_DIR . 'includes/ai-rewrite.php';
require_once AIS_HUB_DIR . 'includes/notify.php';
require_once AIS_HUB_DIR . 'includes/analytics.php';

/** イラスト素材のURL（スラッグ→ファイル） */
function ais_hub_illustration_url( $slug ) {
	foreach ( array( 'jpg', 'png', 'webp', 'svg' ) as $ext ) {
		if ( file_exists( AIS_HUB_DIR . 'assets/illustrations/' . $slug . '.' . $ext ) ) {
			return AIS_HUB_URL . 'assets/illustrations/' . $slug . '.' . $ext;
		}
	}
	return AIS_HUB_URL . 'assets/illustrations/platforms.svg';
}

/** 有効化：CPT登録を反映するためパーマリンクをフラッシュ（ページ生成は手動の一括セットアップで実施） */
register_activation_hook( __FILE__, function () {
	ais_hub_register_cpt();
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
