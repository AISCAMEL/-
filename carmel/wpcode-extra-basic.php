<?php
/**
 * カーメル：基本情報の追加4項目（法定整備・寒冷地仕様・状態/付属品・保証内容）
 * ---------------------------------------------------------------------------
 * 目的 : 詳細テンプレートが [cf_value name="seibi|kanreichi|joutai|hoshou"] で
 *        参照しているのに入力欄が無く、常に空欄になっていた問題を解消する。
 *
 * 特徴 : プラグインの STEP UI（有効/無効やテーマフォームの違い）に依存しない。
 *        portfolio 編集画面に必ず表示される独立メタボックスとして実装。
 *        保存は post_meta に直接書き込むので、テンプレの [cf_value] がそのまま読む。
 *
 * 導入 : WPCode →「+ スニペットを追加」→「コードを追加（PHP Snippet）」→
 *        このファイルの <?php 以降を貼り付け → 挿入方法「自動挿入／管理画面のみ
 *        （Admin Only）」または「どこでも(Run Everywhere)」→ 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* 編集画面にメタボックスを追加 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'carmelx_extra_basic',
		'追加の基本情報（法定整備・寒冷地仕様・状態/付属品・保証内容）',
		'carmelx_extra_basic_box',
		'portfolio',
		'normal',
		'high'
	);
} );

/* メタボックスの中身（4項目の入力欄） */
function carmelx_extra_basic_box( $post ) {
	wp_nonce_field( 'carmelx_extra_basic_save', 'carmelx_extra_basic_nonce' );
	$fields = array(
		'seibi'     => '法定整備',
		'kanreichi' => '寒冷地仕様',
		'joutai'    => '状態・付属品',
		'hoshou'    => '保証内容',
	);
	echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 18px;">';
	foreach ( $fields as $k => $label ) {
		$v = get_post_meta( $post->ID, $k, true );
		if ( is_array( $v ) ) { $v = implode( '・', array_filter( $v ) ); }
		echo '<p style="margin:0;">';
		echo '<label for="carmelx_' . esc_attr( $k ) . '" style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html( $label ) . '</label>';
		echo '<input type="text" id="carmelx_' . esc_attr( $k ) . '" name="carmelx_extra[' . esc_attr( $k ) . ']" value="' . esc_attr( (string) $v ) . '" style="width:100%;">';
		echo '</p>';
	}
	echo '</div>';
	echo '<p style="color:#888;font-size:12px;margin:10px 0 0;">※ ここに入力して「更新」すると、車両詳細ページの基本情報表（法定整備／寒冷地仕様／状態・付属品／保証内容）に表示されます。空欄のままなら表に「—（空）」になります。</p>';
}

/* 保存：post_meta へ直接書き込み（テンプレの [cf_value] がそのまま読む） */
add_action( 'save_post_portfolio', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['carmelx_extra_basic_nonce'] ) || ! wp_verify_nonce( $_POST['carmelx_extra_basic_nonce'], 'carmelx_extra_basic_save' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	if ( ! isset( $_POST['carmelx_extra'] ) || ! is_array( $_POST['carmelx_extra'] ) ) { return; }

	$allow = array( 'seibi', 'kanreichi', 'joutai', 'hoshou' );
	$in    = wp_unslash( $_POST['carmelx_extra'] );
	foreach ( $allow as $k ) {
		if ( ! array_key_exists( $k, $in ) ) { continue; }
		$val = sanitize_text_field( (string) $in[ $k ] );
		update_post_meta( $post_id, $k, $val );
		if ( function_exists( 'update_field' ) ) { update_field( $k, $val, $post_id ); }
	}
} );
