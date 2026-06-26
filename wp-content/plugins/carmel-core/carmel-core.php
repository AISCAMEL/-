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
require_once CARMEL_CORE_DIR . 'includes/class-carmel-assets.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-login.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-application-intake.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-application-form.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-liff.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-franchise.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-hq-stores.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-deal-status.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-hq-screening.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-mypage.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-store.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-store-content.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-hq-content.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-content-seeder.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-cron.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-gas-client.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-transport.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-payments.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-reports.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-hq-dashboard.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-mf-contract.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-documents.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-billing.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-sales-support.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-inventory.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-commission.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-acf-fields.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-hq-board.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-membership.php';
require_once CARMEL_CORE_DIR . 'includes/class-carmel-community.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/interface-carmel-channel-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/class-carmel-notification-log.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-proline-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-lineworks-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-slack-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-mail-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/adapters/class-carmel-line-adapter.php';
require_once CARMEL_CORE_DIR . 'includes/notifications/class-carmel-notifier.php';

/**
 * Boot the plugin: register all runtime hooks.
 */
function carmel_core_init() {
	Carmel_Post_Types::instance()->register_hooks();
	Carmel_Access_Control::instance()->register_hooks();
	Carmel_Assets::instance()->register_hooks();
	Carmel_Login::instance()->register_hooks();
	Carmel_Application_Intake::instance()->register_hooks();
	Carmel_Application_Form::instance()->register_hooks();
	Carmel_LIFF::instance()->register_hooks();
	Carmel_Franchise::instance()->register_hooks();
	Carmel_HQ_Stores::instance()->register_hooks();
	Carmel_Deal_Status::instance()->register_hooks();
	Carmel_HQ_Screening::instance()->register_hooks();
	Carmel_MyPage::instance()->register_hooks();
	Carmel_Store::instance()->register_hooks();
	Carmel_Store_Content::instance()->register_hooks();
	Carmel_HQ_Content::instance()->register_hooks();
	Carmel_Cron::instance()->register_hooks();
	Carmel_GAS_Client::instance()->register_hooks();
	Carmel_Transport::instance()->register_hooks();
	Carmel_Payments::instance()->register_hooks();
	Carmel_Reports::instance()->register_hooks();
	Carmel_HQ_Dashboard::instance()->register_hooks();
	Carmel_MF_Contract::instance()->register_hooks();
	Carmel_Documents::instance()->register_hooks();
	Carmel_Billing::instance()->register_hooks();
	Carmel_Sales_Support::instance()->register_hooks();
	Carmel_Inventory::instance()->register_hooks();
	Carmel_Commission::instance()->register_hooks();
	Carmel_ACF_Fields::instance()->register_hooks();
	Carmel_HQ_Board::instance()->register_hooks();
	Carmel_Membership::instance()->register_hooks();
	Carmel_Community::instance()->register_hooks();
	Carmel_Notifier::instance()->register_hooks();

	// LINE 公式（Messaging API）アダプタを登録し、モードに応じて配信を切替（プロライン→LINE）。
	add_filter( 'carmel_notification_adapters', array( 'Carmel_LINE_Adapter', 'register_adapter' ) );
	add_filter( 'carmel_routing_table', array( 'Carmel_LINE_Adapter', 'rewrite_routing' ), 99 );
}
add_action( 'plugins_loaded', 'carmel_core_init' );

/**
 * Activation: register CPTs + roles, then flush rewrite rules once.
 */
function carmel_core_activate() {
	Carmel_Post_Types::instance()->register_post_types();
	Carmel_Roles::add_roles_and_caps();
	Carmel_Cron::schedule();
	Carmel_Content_Seeder::seed();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'carmel_core_activate' );

/**
 * Deactivation: only flush rewrite rules. Roles/data are preserved
 * (full removal happens in uninstall.php).
 */
function carmel_core_deactivate() {
	Carmel_Cron::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'carmel_core_deactivate' );
