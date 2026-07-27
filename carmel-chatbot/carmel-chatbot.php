<?php
/**
 * Plugin Name: カーメル AIチャットボット
 * Plugin URI:  https://carmelonline.jp/
 * Description: OpenRouter を使ったAI自動応答チャットボット。管理画面から人格・FAQ(学習データ)・モデル・見た目を編集でき、会話ログも確認できます。悩みを深掘りする会話設計、タップで進む候補、申込意欲が高まった場面でのCTA（仮審査/LINE/電話）、モデル自動フォールバック、有人対応(メール/Slack/LINE WORKS通知・Slack双方向)に対応。
 * Version:     1.56.0
 * Author:      Carmel
 * License:     GPL-2.0+
 * Text Domain: carmel-chatbot
 */

// 直接アクセス禁止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARMEL_CB_VERSION', '1.56.0' );
define( 'CARMEL_CB_PATH', plugin_dir_path( __FILE__ ) );
define( 'CARMEL_CB_URL', plugin_dir_url( __FILE__ ) );
define( 'CARMEL_CB_FAQ_TABLE', 'carmel_cb_faq' );
define( 'CARMEL_CB_LOG_TABLE', 'carmel_cb_logs' );

// 各機能ファイルを読み込み
require_once CARMEL_CB_PATH . 'includes/db.php';
require_once CARMEL_CB_PATH . 'includes/settings.php';
require_once CARMEL_CB_PATH . 'includes/inventory.php';
require_once CARMEL_CB_PATH . 'includes/handoff.php';
require_once CARMEL_CB_PATH . 'includes/convo.php';
require_once CARMEL_CB_PATH . 'includes/leads.php';
require_once CARMEL_CB_PATH . 'includes/api.php';
require_once CARMEL_CB_PATH . 'includes/frontend.php';
require_once CARMEL_CB_PATH . 'includes/embed.php';
require_once CARMEL_CB_PATH . 'admin/admin-menu.php';

// 有効化時：DBテーブル作成＆初期データ投入
register_activation_hook( __FILE__, 'carmel_cb_activate' );
function carmel_cb_activate() {
	carmel_cb_create_tables();
	carmel_cb_seed_defaults();
}

// 無効化時：定期タスク（Slackヘルスチェック）を解除
register_deactivation_hook( __FILE__, 'carmel_cb_deactivate' );
function carmel_cb_deactivate() {
	wp_clear_scheduled_hook( 'carmel_cb_slack_healthcheck' );
}
