<?php
/**
 * Plugin Name:       Carmel Core
 * Plugin URI:        https://carmelonline.jp/
 * Description:       カーメル統合管理システムのコア機能。カスタム投稿タイプ（9種）・権限ロール（4階層）・ページアクセス制御・通知オーケストレーターを提供する。
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            株式会社カーメル
 * Text Domain:       carmel-core
 * Domain Path:       /languages
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

define( 'CARMEL_CORE_VERSION', '0.1.0' );
define( 'CARMEL_CORE_FILE', __FILE__ );
define( 'CARMEL_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARMEL_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CARMEL_CORE_DIR . 'includes/class-carmel-post-types.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-roles.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-access-control.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-application-intake.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-deal-status.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-hq-screening.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-mypage.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-store.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/interface-carmel-channel-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/class-carmel-notification-log.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-proline-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-lineworks-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-slack-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-mail-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/class-carmel-notifier.php';

/**
 * Boot the plugin: register all runtime hooks.
 */
function carmel_core_init() {
	Carmel_Post_Types::instance()->register_hooks();
	Carmel_Access_Control::instance()->register_hooks();
	Carmel_Application_Intake::instance()->register_hooks();
	Carmel_Deal_Status::instance()->register_hooks();
	Carmel_HQ_Screening::instance()->register_hooks();
	Carmel_MyPage::instance()->register_hooks();
	Carmel_Store::instance()->register_hooks();
	Carmel_Notifier::instance()->register_hooks();
}
add_action( 'plugins_loaded', 'carmel_core_init' );

/**
 * Activation: register CPTs + roles, then flush rewrite rules once.
 */
function carmel_core_activate() {
	Carmel_Post_Types::instance()->register_post_types();
	Carmel_Roles::add_roles_and_caps();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'carmel_core_activate' );

/**
 * Deactivation: only flush rewrite rules. Roles/data are preserved
 * (full removal happens in uninstall.php).
 */
function carmel_core_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'carmel_core_deactivate' );
