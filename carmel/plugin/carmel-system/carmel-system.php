<?php
/**
 * Plugin Name: カーメルシステム
 * Plugin URI:  https://carmelonline.jp/
 * Description: カーメルのローン表示（固定13.6%・緑ボックス）を提供するプラグイン。GitHubから1クリック更新できます。他のカーメル用プラグインと衝突しないよう、最小構成＋二重定義ガード＋安全読み込みにしています。
 * Version:     1.17.1
 * Author:      CarMel / AIS
 * License:     GPL-2.0+
 * Text Domain: carmel-system
 * ---------------------------------------------------------------------------
 * v1.1.0 変更点（衝突対策）
 *   ・読み込むのを「ローン表示」1つだけに縮小（他は既存プラグイン/スニペットに任せる）。
 *   ・各モジュールは function_exists で二重定義をガード。既に同名があれば読み込まない。
 *   ・読み込みを plugins_loaded に遅延し、万一のエラーでも有効化が止まりにくいようにした。
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CARMEL_SYS_VERSION', '1.17.1' );
define( 'CARMEL_SYS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARMEL_SYS_FILE', __FILE__ );

if ( ! defined( 'CARMEL_SYS_UPDATE_URL' ) ) {
	define( 'CARMEL_SYS_UPDATE_URL', 'https://raw.githubusercontent.com/AISCAMEL/carmel-system-dist/main/update.json' );
}

/**
 * 読み込むモジュール。
 *   sentinel: その機能を表す関数名。既に定義済み（＝別プラグイン/スニペットが提供中）なら読み込まない。
 * ※ 衝突を避けるため、当面「ローン表示」だけを有効にしている。
 */
function carmel_sys_modules() {
	return array(
		array(
			'file'     => 'wpcode-loan-guide.php',
			'sentinel' => 'carmelx_loan_guide_render',
			'label'    => 'ローン概算＋シミュレーション',
		),
		array(
			'file'     => 'wpcode-car-page-polish.php',
			'sentinel' => 'carmelx_page_polish_css',
			'label'    => '車両ページ仕上げ（重複月々・空白ボックス除去）',
		),
		array(
			'file'     => 'wpcode-car-video.php',
			'sentinel' => 'carmelx_video_render',
			'label'    => '車両ページ 動画埋め込み',
		),
		array(
			'file'     => 'wpcode-car-spec-extra.php',
			'sentinel' => 'carmelx_specx_render',
			'label'    => '追加スペック（保証・定員・燃費・ワンオーナー等）',
		),
		array(
			'file'     => 'wpcode-car-favorite.php',
			'sentinel' => 'carmelx_favorite_render',
			'label'    => 'お気に入り（♡）＋一覧パネル',
		),
		array(
			'file'     => 'wpcode-car-inquiry.php',
			'sentinel' => 'carmelx_inquiry_render',
			'label'    => 'お問い合わせ車両連動（CF7隠し項目へ自動入力）',
		),
		array(
			'file'     => 'wpcode-car-breakdown.php',
			'sentinel' => 'carmelx_breakdown_render',
			'label'    => 'お支払総額の内訳（本体＋諸費用）',
		),
		array(
			'file'     => 'wpcode-car-faq.php',
			'sentinel' => 'carmelx_faq_render',
			'label'    => '不安解消ミニFAQ（信用回復向け）',
		),
		array(
			'file'     => 'wpcode-car-history-share.php',
			'sentinel' => 'carmelx_histshare_render',
			'label'    => '最近見た車＋この車を共有（LINE/メール）',
		),
		array(
			'file'     => 'wpcode-car-compare.php',
			'sentinel' => 'carmelx_compare_render',
			'label'    => '車両比較（最大3台）',
		),
		array(
			'file'     => 'wpcode-admin-menu.php',
			'sentinel' => 'carmelx_adminmenu_apply',
			'label'    => '管理画面メニュー整理（スタッフ用シンプル化）',
		),
		array(
			'file'     => 'wpcode-site-monitor.php',
			'sentinel' => 'carmelx_sm_render',
			'label'    => 'サイト健康モニター（重いページ検出＋安全な軽量化）',
		),
		array(
			'file'     => 'wpcode-image-optimize.php',
			'sentinel' => 'carmelx_io_render',
			'label'    => '画像最適化（新規自動＋既存一括・バックアップ付き）',
		),
		array(
			'file'     => 'wpcode-ai-diagnose.php',
			'sentinel' => 'carmelx_ai_render',
			'label'    => 'AI診断・デザイン（Claude API）',
		),
		// 以下は既存のカーメル用プラグインと重複し衝突するため、当面このプラグインには含めない：
		// car-detail-ui / staff-shop / extra-basic / shop-checklist / shop-bulk-assign / staff-manual / form-redirect
	);
}

/**
 * モジュール読み込み。plugins_loaded に遅延し、各モジュールは
 * 「同名関数が未定義のときだけ」読み込む（二重定義＝致命的エラーを根本から防ぐ）。
 */
function carmel_sys_boot() {
	foreach ( carmel_sys_modules() as $m ) {
		$sentinel = isset( $m['sentinel'] ) ? $m['sentinel'] : '';
		if ( $sentinel && function_exists( $sentinel ) ) {
			continue; // 既に別のプラグイン/スニペットが提供中 → 読み込まない（衝突回避）
		}
		$path = CARMEL_SYS_DIR . 'modules/' . $m['file'];
		if ( ! file_exists( $path ) ) { continue; }
		try {
			require_once $path;
		} catch ( \Throwable $e ) {
			// 1モジュールのエラーでサイト全体を止めない
			error_log( 'carmel-system: module ' . $m['file'] . ' failed: ' . $e->getMessage() );
		}
	}
}
add_action( 'plugins_loaded', 'carmel_sys_boot', 5 );

/* =========================================================================
 * 1クリック更新（GitHub の公開 update.json を見て、新しければ更新ボタンを出す）
 * ========================================================================= */
if ( ! class_exists( 'Carmel_Sys_Updater' ) ) {
	class Carmel_Sys_Updater {
		private $plugin;
		private $slug;
		private $url;

		public function __construct( $file, $url ) {
			$this->plugin = plugin_basename( $file );
			$this->slug   = dirname( $this->plugin );
			$this->url    = $url;
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check' ) );
			add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
			add_filter( 'upgrader_source_selection', array( $this, 'fix_dir' ), 10, 4 );
		}

		private function remote() {
			$res = wp_remote_get( $this->url, array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/json' ) ) );
			if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) { return false; }
			$data = json_decode( wp_remote_retrieve_body( $res ) );
			return ( $data && ! empty( $data->version ) && ! empty( $data->download_url ) ) ? $data : false;
		}

		public function check( $transient ) {
			if ( empty( $transient->checked ) ) { return $transient; }
			$r = $this->remote();
			if ( ! $r ) { return $transient; }
			if ( version_compare( CARMEL_SYS_VERSION, $r->version, '<' ) ) {
				$transient->response[ $this->plugin ] = (object) array(
					'slug'        => $this->slug,
					'plugin'      => $this->plugin,
					'new_version' => $r->version,
					'package'     => $r->download_url,
					'url'         => isset( $r->homepage ) ? $r->homepage : 'https://carmelonline.jp/',
					'tested'      => isset( $r->tested ) ? $r->tested : '',
				);
			}
			return $transient;
		}

		public function info( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) { return $result; }
			$r = $this->remote();
			if ( ! $r ) { return $result; }
			return (object) array(
				'name'          => isset( $r->name ) ? $r->name : 'カーメルシステム',
				'slug'          => $this->slug,
				'version'       => $r->version,
				'author'        => 'CarMel / AIS',
				'homepage'      => isset( $r->homepage ) ? $r->homepage : 'https://carmelonline.jp/',
				'download_link' => $r->download_url,
				'tested'        => isset( $r->tested ) ? $r->tested : '',
				'sections'      => array( 'changelog' => isset( $r->changelog ) ? $r->changelog : '最新の修正を反映します。' ),
			);
		}

		public function fix_dir( $source, $remote_source, $upgrader, $extra = array() ) {
			if ( empty( $extra['plugin'] ) || $extra['plugin'] !== $this->plugin ) { return $source; }
			global $wp_filesystem;
			$desired = trailingslashit( $remote_source ) . $this->slug;
			if ( $source === trailingslashit( $remote_source ) . basename( $source ) && basename( $source ) !== $this->slug ) {
				if ( $wp_filesystem && $wp_filesystem->move( $source, $desired ) ) {
					return trailingslashit( $desired );
				}
			}
			return $source;
		}
	}
	new Carmel_Sys_Updater( CARMEL_SYS_FILE, CARMEL_SYS_UPDATE_URL );
}
