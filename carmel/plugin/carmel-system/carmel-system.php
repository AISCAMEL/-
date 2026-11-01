<?php
/**
 * Plugin Name: カーメルシステム
 * Plugin URI:  https://carmelonline.jp/
 * Description: カーメルの在庫表示・管理用スニペットを一括で読み込むプラグイン。WPCodeへの手貼りを無くし、GitHubから1クリック更新できます。
 * Version:     1.0.0
 * Author:      CarMel / AIS
 * License:     GPL-2.0+
 * Text Domain: carmel-system
 * ---------------------------------------------------------------------------
 * 使い方：
 *   1. このプラグインを有効化する。
 *   2. 下の $modules に載っているスニペットと同じものを、WPCode 側で無効化する
 *      （二重読み込み防止）。対応表は README-plugin.md を参照。
 *   3. 以後、修正は GitHub への push → 管理画面「更新」ボタンで反映。
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CARMEL_SYS_VERSION', '1.0.0' );
define( 'CARMEL_SYS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARMEL_SYS_FILE', __FILE__ );

/* 更新チェック先（公開リポジトリの update.json）。公開配布リポジトリを用意したら URL を差し替える。 */
if ( ! defined( 'CARMEL_SYS_UPDATE_URL' ) ) {
	define( 'CARMEL_SYS_UPDATE_URL', 'https://raw.githubusercontent.com/AISCAMEL/carmel-system-dist/main/update.json' );
}

/**
 * 読み込むモジュール（＝旧WPCodeスニペット）。
 *   type: 'php'       … PHPスニペット（Run Everywhere 相当。管理/フロント両方で読む）
 *         'js-footer' … フロントのフッターに出す JavaScript スニペット
 * 新しいモジュールを足すときはファイルを modules/ に置き、ここに1行追加するだけ。
 */
function carmel_sys_modules() {
	return array(
		array( 'file' => 'wpcode-loan-guide.php',      'type' => 'php', 'label' => 'ローン概算＋シミュレーション' ),
		array( 'file' => 'wpcode-car-detail-ui.php',   'type' => 'php', 'label' => '詳細ページ 保証バッジ＋不要要素整理' ),
		array( 'file' => 'wpcode-staff-shop.php',      'type' => 'php', 'label' => '担当スタッフ・店舗表示' ),
		array( 'file' => 'wpcode-extra-basic.php',     'type' => 'php', 'label' => '追加の基本情報4項目（入力欄）' ),
		array( 'file' => 'wpcode-shop-checklist.php',  'type' => 'php', 'label' => '在庫→店舗ふりわけ（チェックリスト）' ),
		array( 'file' => 'wpcode-shop-bulk-assign.php','type' => 'php', 'label' => '在庫→店舗 一括割当（一覧）' ),
		array( 'file' => 'wpcode-staff-manual.php',    'type' => 'php', 'label' => 'スタッフ用 手順マニュアル' ),
		array( 'file' => 'wpcode-form-redirect.js',    'type' => 'js-footer', 'label' => '審査/お問い合わせURL 統一' ),
	);
}

/**
 * モジュールの読み込み。
 * PHP はそのまま require。各スニペットは function_exists ガードや
 * 無条件 add_shortcode を備えているので、読み込み順や重複に強い。
 */
function carmel_sys_boot() {
	foreach ( carmel_sys_modules() as $m ) {
		$path = CARMEL_SYS_DIR . 'modules/' . $m['file'];
		if ( ! file_exists( $path ) ) { continue; }

		if ( 'php' === $m['type'] ) {
			require_once $path;
		} elseif ( 'js-footer' === $m['type'] ) {
			// フロントのフッターに素の JS を出力（WPCode「サイト全体のフッター」相当）。
			add_action( 'wp_footer', function () use ( $path ) {
				if ( is_admin() ) { return; }
				$js = file_get_contents( $path );
				if ( false === $js || '' === trim( $js ) ) { return; }
				echo "\n<script data-carmel-system=\"" . esc_attr( basename( $path ) ) . "\">\n" . $js . "\n</script>\n";
			}, 99 );
		}
	}
}
carmel_sys_boot();

/* =========================================================================
 * 1クリック更新（GitHub の公開 update.json を見て、新しければ更新ボタンを出す）
 * ========================================================================= */
if ( ! class_exists( 'Carmel_Sys_Updater' ) ) {
	class Carmel_Sys_Updater {
		private $slug;   // プラグインフォルダ名
		private $plugin; // folder/file.php
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

		/* zip 内のフォルダ名が slug と一致するよう調整（GitHub zip 対策） */
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
