<?php
/**
 * カーメル：在庫を店舗へ「チェックリスト」で簡単割当（専用ページ）
 * ---------------------------------------------------------------------------
 * 在庫 → サブメニュー「店舗にふりわけ」を追加。
 *   ・在庫をサムネ付きで一覧表示（未設定のみ／店舗別／すべて で絞り込み）
 *   ・チェックで複数選択（全選択ボタンあり）
 *   ・上で店舗を選び → ボタン一発でまとめて割当
 *   ・割当時に電話/LINE/問い合わせリンクも店舗に合わせて上書き
 *
 * 導入 : WPCode → PHP Snippet → 自動挿入／あらゆる場所で実行 → 優先順位 1 → 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* 店舗一覧：slug => array( name, id )（他スニペットと共有・重複ガード） */
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
if ( ! function_exists( 'carmelx_bulk_shop_meta' ) ) {
	function carmelx_bulk_shop_meta( $sid, $keys ) {
		foreach ( (array) $keys as $k ) {
			$v = get_post_meta( $sid, $k, true );
			if ( '' !== $v && null !== $v && false !== $v ) { return $v; }
		}
		return '';
	}
}
/* 割当処理（IDリスト → 店舗slug） */
if ( ! function_exists( 'carmelx_assign_to_shop' ) ) {
	function carmelx_assign_to_shop( $ids, $slug ) {
		$shops = carmelx_bulk_shops();
		if ( ! isset( $shops[ $slug ] ) ) { return 0; }
		$sid     = $shops[ $slug ]['id'];
		$tel     = carmelx_bulk_shop_meta( $sid, array( 'tel', 'phone', 'denwa' ) );
		$line    = carmelx_bulk_shop_meta( $sid, array( 'line_link', 'line-link' ) );
		$contact = carmelx_bulk_shop_meta( $sid, array( 'contact-link', 'contact_link' ) );
		$n = 0;
		foreach ( (array) $ids as $pid ) {
			$pid = (int) $pid;
			if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) { continue; }
			update_post_meta( $pid, 'shop', $slug );
			if ( function_exists( 'update_field' ) ) { update_field( 'shop', $slug, $pid ); }
			if ( '' !== $tel )     { update_post_meta( $pid, 'tel', $tel ); }
			if ( '' !== $line )    { update_post_meta( $pid, 'line-link', $line ); }
			if ( '' !== $contact ) { update_post_meta( $pid, 'contact-link', $contact ); }
			$n++;
		}
		return $n;
	}
}

/* サブメニュー追加 */
add_action( 'admin_menu', function () {
	if ( ! post_type_exists( 'portfolio' ) ) { return; }
	add_submenu_page(
		'edit.php?post_type=portfolio',
		'店舗にふりわけ',
		'店舗にふりわけ',
		'manage_options',
		'carmel-shop-checklist',
		'carmelx_shop_checklist_page'
	);
} );

/* ページ本体 */
if ( ! function_exists( 'carmelx_shop_checklist_page' ) ) {
	function carmelx_shop_checklist_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$shops = carmelx_bulk_shops();
		$msg   = '';

		// 割当実行
		if ( ! empty( $_POST['carmel_cl_submit'] ) && check_admin_referer( 'carmel_cl', 'carmel_cl_nonce' ) ) {
			$slug = isset( $_POST['carmel_cl_shop'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_cl_shop'] ) ) : '';
			$ids  = isset( $_POST['carmel_cl_ids'] ) ? array_map( 'intval', (array) $_POST['carmel_cl_ids'] ) : array();
			if ( $slug && isset( $shops[ $slug ] ) && $ids ) {
				$n   = carmelx_assign_to_shop( $ids, $slug );
				$msg = $n . '台を「' . $shops[ $slug ]['name'] . '」に割り当てました。';
			} else {
				$msg = '※ 店舗と在庫（1台以上）を選んでから実行してください。';
			}
		}

		// 絞り込み
		$filter = isset( $_GET['cl_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['cl_filter'] ) ) : 'none';
		$args = array(
			'post_type'      => 'portfolio',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( 'none' === $filter ) {
			$args['meta_query'] = array( array( 'key' => 'shop', 'compare' => 'NOT EXISTS' ) );
		} elseif ( 'all' !== $filter && isset( $shops[ $filter ] ) ) {
			$args['meta_query'] = array( array( 'key' => 'shop', 'value' => $filter ) );
		}
		$q = new WP_Query( $args );

		echo '<div class="wrap"><h1>店舗にふりわけ</h1>';
		echo '<p>在庫をチェックで選び、上の店舗を選んで「割り当てる」を押すだけ。（電話／LINE／問い合わせも自動で店舗に合わせます）</p>';
		if ( $msg ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>'; }

		// 絞り込みフォーム（GET）
		echo '<form method="get" style="margin:12px 0;">';
		echo '<input type="hidden" name="post_type" value="portfolio"><input type="hidden" name="page" value="carmel-shop-checklist">';
		echo '<label>表示：<select name="cl_filter" onchange="this.form.submit()" style="min-width:160px;">';
		echo '<option value="none"' . selected( $filter, 'none', false ) . '>未設定のみ</option>';
		echo '<option value="all"' . selected( $filter, 'all', false ) . '>すべて</option>';
		foreach ( $shops as $slug => $s ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $filter, $slug, false ) . '>' . esc_html( $s['name'] ) . '</option>';
		}
		echo '</select></label></form>';

		// 割当フォーム（POST）
		echo '<form method="post">';
		wp_nonce_field( 'carmel_cl', 'carmel_cl_nonce' );

		// 上部：店舗選択＋実行（スクロール追従）
		echo '<div style="position:sticky;top:32px;z-index:10;margin:0 0 14px;padding:12px 14px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,.06);">';
		echo '<strong>割り当てる店舗：</strong> <select name="carmel_cl_shop" style="min-width:180px;height:32px;">';
		echo '<option value="">— 選択してください —</option>';
		foreach ( $shops as $slug => $s ) {
			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $s['name'] ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="submit" name="carmel_cl_submit" value="1" class="button button-primary">選択した在庫をこの店舗に割り当てる</button>';
		echo ' <label style="margin-left:14px;"><input type="checkbox" id="carmel_cl_all"> 全選択／全解除</label>';
		echo ' <span style="color:#666;margin-left:8px;">（' . (int) $q->post_count . '台）</span>';
		echo '</div>';

		// 一覧
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<td style="width:34px;"></td><th>車両</th><th style="width:150px;">現在の店舗</th></tr></thead><tbody>';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$pid   = get_the_ID();
				$cur   = get_post_meta( $pid, 'shop', true );
				$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
				echo '<tr>';
				echo '<td><input type="checkbox" class="carmel_cl_cb" name="carmel_cl_ids[]" value="' . (int) $pid . '"></td>';
				echo '<td>';
				if ( $thumb ) { echo '<img src="' . esc_url( $thumb ) . '" style="width:52px;height:38px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:10px;">'; }
				echo '<strong>' . esc_html( get_the_title() ) . '</strong>';
				echo ' <a href="' . esc_url( get_edit_post_link( $pid ) ) . '" target="_blank" style="margin-left:8px;font-size:12px;">編集</a>';
				echo '</td>';
				if ( $cur && isset( $shops[ $cur ] ) ) {
					echo '<td>' . esc_html( $shops[ $cur ]['name'] ) . '</td>';
				} elseif ( $cur ) {
					echo '<td>' . esc_html( $cur ) . '</td>';
				} else {
					echo '<td><span style="color:#c00;font-weight:600;">未設定</span></td>';
				}
				echo '</tr>';
			}
			wp_reset_postdata();
		} else {
			echo '<tr><td colspan="3">該当する在庫がありません。</td></tr>';
		}
		echo '</tbody></table>';

		echo '<p style="margin-top:14px;"><button type="submit" name="carmel_cl_submit" value="1" class="button button-primary button-hero">選択した在庫を上の店舗に割り当てる</button></p>';
		echo '</form>';

		// 全選択JS
		echo '<script>(function(){var a=document.getElementById("carmel_cl_all");if(!a){return;}a.addEventListener("change",function(){var c=document.querySelectorAll(".carmel_cl_cb");for(var i=0;i<c.length;i++){c[i].checked=a.checked;}});})();</script>';

		echo '</div>';
	}
}
