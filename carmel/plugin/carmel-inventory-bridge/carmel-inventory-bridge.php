<?php
/**
 * Plugin Name: カーメル 在庫連携
 * Plugin URI:  https://carmelonline.jp/
 * Description: 外部のカーメルシステム（業務ポータル）と在庫をやり取りする「受け口」だけの軽量プラグイン。ホームページの掲載車両をシステムへ取り込み／システムの在庫を車両ページへ反映します。サイトの見た目・表示は一切変えません。「カーメルシステム」プラグインを有効にしている場合は、そちらに同じ機能が入っているので不要です（両方有効でも衝突しません）。
 * Version:     1.23.0
 * Author:      CarMel / AIS
 * License:     GPL-2.0+
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 「カーメルシステム」（plugins_loaded 優先度5）が先に同じ受け口を読み込んでいれば、こちらは何もしない。
 */
add_action( 'plugins_loaded', function () {
	if ( function_exists( 'carmelx_invrecv_routes' ) ) { return; }
	$path = plugin_dir_path( __FILE__ ) . 'wpcode-inventory-receiver.php';
	if ( file_exists( $path ) ) { require_once $path; }
}, 6 );
