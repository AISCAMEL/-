<?php
/**
 * カーメル：在庫を店舗へ手動一括割当（在庫一覧の「一括操作」に追加）
 * ---------------------------------------------------------------------------
 * できること（すべて 在庫一覧＝edit.php?post_type=portfolio 上）:
 *   1. 「一括操作」ドロップダウンに「店舗に割当：◯◯店」を店舗ごとに追加。
 *      → 車両をチェックで複数選択 → 選ぶ → 適用 で、選んだ車両の shop を一括設定。
 *      あわせて電話/LINE/問い合わせリンクも割当店舗に合わせて上書き。
 *   2. 一覧に「店舗」列を追加（現在の割当先が一目で分かる。未設定は赤字）。
 *   3. 上部に「店舗で絞り込み」ドロップダウン（未設定だけ表示なども可能）。
 *
 * 店舗データ : shop メタ = 店舗スラッグ。carmel_shop_post_map()（スラッグ→店舗投稿ID）
 *             を利用。無い場合は既定の4店舗にフォールバック。
 *
 * 導入 : WPCode → PHP Snippet → 自動挿入／あらゆる場所で実行 → 優先順位 1 → 有効化。
 *        （管理画面のみで動きますが、優先順位1で確実に実行させます）
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* 店舗一覧：slug => array( name, id ) */
if ( ! function_exists( 'carmelx_bulk_shops' ) ) {
	function carmelx_bulk_shops() {
		$map = function_exists( 'carmel_shop_post_map' )
			? carmel_shop_post_map()
			: array( 'fukushima' => 698, 'chiba' => 705, 'odawara' => 715, 'yamanashi' => 2450 );
		$out = array();
		foreach ( (array) $map as $slug => $sid ) {
			$name = get_the_title( (int) $sid );
			$out[ $slug ] = array( 'name' => ( $name ? $name : $slug ), 'id' => (int) $sid );
		}
		return $out;
	}
}

/* 店舗メタを候補キーで取得 */
if ( ! function_exists( 'carmelx_bulk_shop_meta' ) ) {
	function carmelx_bulk_shop_meta( $sid, $keys ) {
		foreach ( (array) $keys as $k ) {
			$v = get_post_meta( $sid, $k, true );
			if ( '' !== $v && null !== $v && false !== $v ) { return $v; }
		}
		return '';
	}
}

/* 1) 一括操作に店舗割当を追加 */
add_filter( 'bulk_actions-edit-portfolio', function ( $actions ) {
	foreach ( carmelx_bulk_shops() as $slug => $s ) {
		$actions[ 'carmel_assign_' . $slug ] = '店舗に割当：' . $s['name'];
	}
	return $actions;
} );

/* 1) 一括操作の処理 */
add_filter( 'handle_bulk_actions-edit-portfolio', function ( $redirect, $action, $ids ) {
	if ( 0 !== strpos( $action, 'carmel_assign_' ) ) { return $redirect; }
	$slug  = substr( $action, strlen( 'carmel_assign_' ) );
	$shops = carmelx_bulk_shops();
	if ( ! isset( $shops[ $slug ] ) ) { return $redirect; }

	$sid  = $shops[ $slug ]['id'];
	$name = $shops[ $slug ]['name'];

	$tel     = carmelx_bulk_shop_meta( $sid, array( 'tel', 'phone', 'denwa' ) );
	$line    = carmelx_bulk_shop_meta( $sid, array( 'line_link', 'line-link' ) );
	$contact = carmelx_bulk_shop_meta( $sid, array( 'contact-link', 'contact_link' ) );

	$done = 0;
	foreach ( (array) $ids as $pid ) {
		$pid = (int) $pid;
		if ( ! current_user_can( 'edit_post', $pid ) ) { continue; }
		update_post_meta( $pid, 'shop', $slug );
		if ( function_exists( 'update_field' ) ) { update_field( 'shop', $slug, $pid ); }
		if ( '' !== $tel )     { update_post_meta( $pid, 'tel', $tel ); }
		if ( '' !== $line )    { update_post_meta( $pid, 'line-link', $line ); }
		if ( '' !== $contact ) { update_post_meta( $pid, 'contact-link', $contact ); }
		$done++;
	}

	$redirect = remove_query_arg( array( 'carmel_assigned', 'carmel_shop' ), $redirect );
	$redirect = add_query_arg( array(
		'carmel_assigned' => $done,
		'carmel_shop'     => rawurlencode( $name ),
	), $redirect );
	return $redirect;
}, 10, 3 );

/* 1) 完了通知 */
add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['carmel_assigned'] ) ) { return; }
	$n    = (int) $_GET['carmel_assigned'];
	$name = isset( $_GET['carmel_shop'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['carmel_shop'] ) ) ) : '';
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html( $n ) . '台を「' . esc_html( $name ) . '」に割当しました。</p></div>';
} );

/* 2) 「店舗」列を追加 */
add_filter( 'manage_portfolio_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'title' === $k ) { $new['carmel_shop_col'] = '店舗'; }
	}
	if ( ! isset( $new['carmel_shop_col'] ) ) { $new['carmel_shop_col'] = '店舗'; }
	return $new;
} );
add_action( 'manage_portfolio_posts_custom_column', function ( $col, $pid ) {
	if ( 'carmel_shop_col' !== $col ) { return; }
	$slug = get_post_meta( $pid, 'shop', true );
	if ( ! $slug ) { echo '<span style="color:#c00;font-weight:600;">未設定</span>'; return; }
	$shops = carmelx_bulk_shops();
	echo esc_html( isset( $shops[ $slug ] ) ? $shops[ $slug ]['name'] : $slug );
}, 10, 2 );

/* 3) 「店舗で絞り込み」ドロップダウン */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'portfolio' !== $post_type ) { return; }
	$cur = isset( $_GET['carmel_shop_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['carmel_shop_filter'] ) ) : '';
	echo '<select name="carmel_shop_filter"><option value="">店舗で絞り込み（すべて）</option>';
	echo '<option value="__none__"' . selected( $cur, '__none__', false ) . '>未設定のみ</option>';
	foreach ( carmelx_bulk_shops() as $slug => $s ) {
		echo '<option value="' . esc_attr( $slug ) . '"' . selected( $cur, $slug, false ) . '>' . esc_html( $s['name'] ) . '</option>';
	}
	echo '</select>';
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() ) { return; }
	global $pagenow;
	if ( 'edit.php' !== $pagenow ) { return; }
	if ( empty( $_GET['post_type'] ) || 'portfolio' !== $_GET['post_type'] ) { return; }
	if ( empty( $_GET['carmel_shop_filter'] ) ) { return; }
	$val = sanitize_text_field( wp_unslash( $_GET['carmel_shop_filter'] ) );
	if ( '__none__' === $val ) {
		$q->set( 'meta_query', array( array( 'key' => 'shop', 'compare' => 'NOT EXISTS' ) ) );
	} else {
		$q->set( 'meta_query', array( array( 'key' => 'shop', 'value' => $val ) ) );
	}
} );
