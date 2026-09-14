<?php
/**
 * Plugin Name: カーメル メニュー整理
 * Description: 在庫メニューを整理します。①「諸経費設定」を在庫の中へ移動 ②各サブメニューの絵文字を除去 ③分かりやすい順に並べ替え。設定の中身・保存先は変わりません。無効化すれば元に戻ります。
 * Version: 1.1.0
 * Author: CARMEL
 *
 * 使い方：プラグインのアップロードで入れて有効化するだけ。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ① 諸経費設定を最上位から外して、在庫の下へ付け直す */
add_action( 'admin_menu', function () {
	remove_menu_page( 'carmel-fee-settings' );
	if ( function_exists( 'carmel_fee_settings_render' ) && post_type_exists( 'portfolio' ) ) {
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'諸経費設定',
			'諸経費設定',
			'manage_options',
			'carmel-fee-settings',
			'carmel_fee_settings_render'
		);
	}
}, 99 );

/* ②③ 絵文字を除去して、分かりやすい順に並べ替える（最後に実行） */
add_action( 'admin_menu', function () {
	global $submenu;
	$parent = 'edit.php?post_type=portfolio';
	if ( empty( $submenu[ $parent ] ) ) { return; }

	// ツール類のラベルを明示（絵文字なし・統一表記）
	$titles = array(
		'carmel-stock-audit'      => '在庫ページ診断',
		'carmel-backfill'         => '在庫データ一括反映',
		'carmel-monthly-backfill' => '月々シミュ補完',
		'carmel-fee-settings'     => '諸経費設定',
	);

	// 並び順の重み（小さいほど上）。スラッグ/種別で判定。
	$weight = function ( $slug ) {
		if ( 'edit.php?post_type=portfolio' === $slug ) { return 10; }          // 在庫一覧
		if ( false !== strpos( $slug, 'post-new.php' ) ) { return 20; }          // 新規追加
		if ( false !== strpos( $slug, 'taxonomy=' ) )    { return 30; }          // メーカー/タグ
		$order = array(
			'carmel-stock-audit'      => 50,
			'carmel-backfill'         => 60,
			'carmel-monthly-backfill' => 70,
			'carmel-fee-settings'     => 80,
		);
		if ( isset( $order[ $slug ] ) ) { return $order[ $slug ]; }
		return 90; // その他（Settings 等）は末尾
	};

	// ラベル整形（絵文字・記号の先頭装飾を除去）＋ 重み付与
	$items = $submenu[ $parent ];
	$i = 0;
	foreach ( $items as &$it ) {
		$slug = isset( $it[2] ) ? $it[2] : '';
		if ( isset( $titles[ $slug ] ) ) {
			$it[0] = $titles[ $slug ];
		} else {
			// 先頭の絵文字・記号・空白を削除（日本語/英数字が来るまで）
			$it[0] = preg_replace( '/^[^\p{L}\p{N}]+/u', '', (string) $it[0] );
		}
		$it['_w'] = $weight( $slug );
		$it['_i'] = $i++; // 安定ソート用
	}
	unset( $it );

	// 重み→元順で安定ソート
	usort( $items, function ( $a, $b ) {
		if ( $a['_w'] === $b['_w'] ) { return $a['_i'] <=> $b['_i']; }
		return $a['_w'] <=> $b['_w'];
	} );

	// 内部キーを除去して書き戻し
	foreach ( $items as &$it ) { unset( $it['_w'], $it['_i'] ); }
	unset( $it );

	$submenu[ $parent ] = array_values( $items );
}, 999 );
