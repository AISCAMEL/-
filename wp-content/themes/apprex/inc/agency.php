<?php
/**
 * 代理店（紹介・販売・OEM）。
 *
 * - 代理店コード：代理店専用URL（?ref=コード）で来た人に90日間のクッキーを付け、
 *   お問い合わせ・AIスタジオの会員登録・発注に紹介元として記録する。
 * - 「パートナー募集」の表記を「代理店募集」に（管理画面で作ったメニューも含む）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 今の訪問者の代理店コード（無ければ空）。 */
function apprex_agency_ref() {
	$ref = isset( $_COOKIE['apprex_ref'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['apprex_ref'] ) ) : '';
	return preg_match( '/^[A-Za-z0-9_-]{2,32}$/', $ref ) ? $ref : '';
}

/* ?ref= をクッキーに保存（ページキャッシュがあっても動くよう、ブラウザ側で保存）。 */
add_action( 'wp_head', function () {
	echo "<script>(function(){try{var r=new URLSearchParams(location.search).get('ref');if(r&&/^[A-Za-z0-9_-]{2,32}$/.test(r)){document.cookie='apprex_ref='+r+';max-age=7776000;path=/;SameSite=Lax'+(location.protocol==='https:'?';Secure':'');}}catch(e){}})();</script>\n";
}, 1 );

/* 管理画面で作ったメニューの「パートナー募集」も「代理店募集」に。 */
add_filter( 'wp_nav_menu_objects', function ( $items ) {
	foreach ( $items as $item ) {
		if ( in_array( $item->title, array( 'パートナー募集', 'パートナー' ), true ) ) {
			$item->title = '代理店募集';
		}
	}
	return $items;
} );

/* AIスタジオの会員登録：紹介元の代理店を会員に記録。 */
add_action( 'user_register', function ( $user_id ) {
	$ref = apprex_agency_ref();
	if ( $ref ) {
		update_user_meta( $user_id, 'apprex_ref', $ref );
	}
} );

/* 会員の編集画面に代理店コードを表示。 */
add_action( 'show_user_profile', 'apprex_agency_user_row', 20 );
add_action( 'edit_user_profile', 'apprex_agency_user_row', 20 );
function apprex_agency_user_row( $user ) {
	$ref = (string) get_user_meta( $user->ID, 'apprex_ref', true );
	if ( '' === $ref || ! current_user_can( 'edit_users' ) ) {
		return;
	}
	echo '<table class="form-table" role="presentation"><tr><th>紹介元の代理店コード</th><td><code>' . esc_html( $ref ) . '</code></td></tr></table>';
}

/* 固定ページのタイトルが「パートナー募集」のままでも、表示は「代理店募集」に。 */
add_filter( 'the_title', function ( $title, $id = 0 ) {
	return ( 'パートナー募集' === $title && $id && 'partner' === get_post_field( 'post_name', $id ) ) ? '代理店募集' : $title;
}, 10, 2 );
