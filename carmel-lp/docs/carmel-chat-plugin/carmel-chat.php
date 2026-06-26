<?php
/**
 * Plugin Name: Carmel Chat Widget
 * Description: カーメル相談AIのチャットウィジェットをサイト全ページの右下に表示します。
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.0
 *
 * 設定:
 *   - 配信元ホスト(必須): CARMEL_CHAT_HOST 定数、または carmel_chat_options フィルタの 'host'。
 *   - LINE/電話の上書き(任意): 'line_url' / 'tel'。
 *
 * wp-config.php などで:
 *   define( 'CARMEL_CHAT_HOST', 'https://chat.example.com' );
 * もしくはテーマの functions.php で:
 *   add_filter( 'carmel_chat_options', function ( $o ) {
 *     $o['host']     = 'https://chat.example.com';
 *     $o['line_url'] = 'https://lin.ee/xxxx';
 *     $o['tel']      = '050-1793-5554';
 *     return $o;
 *   } );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセス禁止
}

function carmel_chat_get_options() {
	$defaults = array(
		'host'     => defined( 'CARMEL_CHAT_HOST' ) ? CARMEL_CHAT_HOST : '',
		'line_url' => '',
		'tel'      => '',
	);
	return apply_filters( 'carmel_chat_options', $defaults );
}

/**
 * フッターに embed.js を出力する。
 */
function carmel_chat_enqueue_footer() {
	$o    = carmel_chat_get_options();
	$host = untrailingslashit( trim( $o['host'] ) );
	if ( empty( $host ) ) {
		return; // 配信元ホスト未設定なら何もしない
	}

	$src   = esc_url( $host . '/assets/embed.js' );
	$attrs = ' data-api-base="' . esc_attr( $host ) . '"';
	if ( ! empty( $o['line_url'] ) ) {
		$attrs .= ' data-line-url="' . esc_attr( $o['line_url'] ) . '"';
	}
	if ( ! empty( $o['tel'] ) ) {
		$attrs .= ' data-tel="' . esc_attr( $o['tel'] ) . '"';
	}

	echo '<script src="' . $src . '"' . $attrs . "></script>\n";
}
add_action( 'wp_footer', 'carmel_chat_enqueue_footer', 100 );
