<?php
/**
 * Plugin Name: カーメル メニュー整理
 * Description: 最上位の「諸経費設定」メニューを、在庫一覧メニューの中（サブメニュー）へ移動します。設定の中身・保存先（carmel_fee_settings）は変わりません。無効化すれば元の最上位に戻ります。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：プラグインのアップロードで入れて有効化するだけ。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	// 元の最上位「諸経費設定」を外す（slug: carmel-fee-settings）
	remove_menu_page( 'carmel-fee-settings' );

	// 在庫（portfolio）の下にサブメニューとして付け直す
	if ( function_exists( 'carmel_fee_settings_render' ) && post_type_exists( 'portfolio' ) ) {
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'諸経費設定',
			'💴 諸経費設定',
			'manage_options',
			'carmel-fee-settings',
			'carmel_fee_settings_render'
		);
	}
}, 99 );
