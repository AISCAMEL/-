<?php
/**
 * Plugin Name: カーメル 車両チャット相談
 * Plugin URI:  https://carmelonline.jp/
 * Description: 車両ページの「LINEで相談」ボタンを「チャットで相談」に切り替えます。押すとサイトのAIチャットが開き、入力欄にその車の名前と管理番号が入ります。ヘッダー・フッターの LINE はそのまま残します。チャット（カーメル AIチャットボット）が動いていない時は LINE のままです。無効化すれば元の LINE ボタンに戻ります。「カーメルシステム」を有効にしている場合は同じ機能が入っているので不要です（両方有効でも衝突しません）。
 * Version:     1.32.1
 * Author:      CarMel / AIS
 * License:     GPL-2.0+
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'plugins_loaded', function () {
	$f = plugin_dir_path( __FILE__ ) . 'wpcode-car-chat.php';
	if ( ! function_exists( 'carmelx_carchat_footer' ) && file_exists( $f ) ) { require_once $f; }
}, 6 );
