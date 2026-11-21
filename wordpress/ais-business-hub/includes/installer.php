<?php
/** 一括セットアップ（冪等）：ページ・事業・ニュース分類の作成とホーム設定、ロールバック */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_submenu_page( 'ais-hub', '一括セットアップ', '一括セットアップ', 'manage_options', 'ais-hub-setup', 'ais_hub_setup_page' );
}, 20 );

function ais_hub_setup_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$created = get_option( 'ais_hub_created', array() );
	$done    = (int) get_option( 'ais_hub_setup_done', 0 );
	if ( isset( $_GET['ran'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>一括セットアップを実行しました（冪等：既存は保持）。</p></div>'; }
	if ( isset( $_GET['rolled'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>ロールバックしました（フロント設定を復元）。</p></div>'; }
	echo '<div class="wrap"><h1>一括セットアップ</h1>';
	echo '<p>次の内容を作成・設定します。<strong>既存のページ・事業・設定は上書きしません（冪等）</strong>。実行前に本番ではバックアップを取得してください。</p>';
	echo '<ul style="list-style:disc;padding-left:22px">';
	echo '<li>固定ページ 11種（トップ／会社情報／企業理念／代表メッセージ／事業一覧／パートナー／ニュース／コラム／お問い合わせ／プライバシー／採用）</li>';
	echo '<li>事業データ 9件（カード・各事業ページの初期値）</li>';
	echo '<li>ニュース用カテゴリ（お知らせ／事業情報／業界ニュース／コラム）</li>';
	echo '<li>トップページを「AIS Premium」ホーム表示に設定（元の設定はロールバック用に保存）</li>';
	echo '</ul>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:18px 0">';
	echo '<input type="hidden" name="action" value="ais_hub_run_setup">';
	wp_nonce_field( 'ais_hub_setup', 'ais_hub_setup_nonce' );
	echo '<label><input type="checkbox" name="confirm" value="1" required> 内容を確認し、実行します（本番はバックアップ済み）</label><br>';
	echo '<label style="color:#b32d2e"><input type="checkbox" name="overwrite" value="1"> <strong>既存ページの内容も新デザインに上書きする</strong>（事業一覧・会社情報・パートナー・プライバシー・代表メッセージ等の旧コンテンツを置き換えます）</label><br><br>';
	submit_button( $done ? '再実行（不足分のみ作成）' : '一括セットアップを実行', 'primary', 'submit', false );
	echo '</form>';

	if ( $done && ! empty( $created ) ) {
		echo '<hr><h2>ロールバック</h2><p>フロントページ設定を元に戻します。作成したページ・事業を「ゴミ箱へ移動」も選べます（投稿内容は残り、復元可能）。</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'ロールバックを実行しますか？\')">';
		echo '<input type="hidden" name="action" value="ais_hub_rollback">';
		wp_nonce_field( 'ais_hub_rollback', 'ais_hub_rollback_nonce' );
		echo '<label><input type="checkbox" name="trash" value="1"> 作成したページ・事業もゴミ箱へ移動する</label><br><br>';
		submit_button( 'ロールバック', 'delete', 'submit', false );
		echo '</form>';
	}
	echo '</div>';
}

/** 固定ページを冪等に作成（存在すれば保持） */
function ais_hub_ensure_page( $path, $title, $content, $created ) {
	$existing = get_page_by_path( $path );
	if ( $existing ) { return array( $existing->ID, false ); }
	$parent_id = 0;
	if ( false !== strpos( $path, '/' ) ) {
		$parent_path = substr( $path, 0, strrpos( $path, '/' ) );
		$parent      = get_page_by_path( $parent_path );
		if ( $parent ) { $parent_id = $parent->ID; }
	}
	$slug = basename( $path );
	$id   = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_parent'  => $parent_id,
		'post_content' => $content,
	), true );
	return array( is_wp_error( $id ) ? 0 : $id, true );
}

add_action( 'admin_post_ais_hub_run_setup', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_setup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_setup_nonce'] ) ), 'ais_hub_setup' ) ) {
		wp_die( '不正なリクエストです', '', array( 'response' => 403 ) );
	}
	if ( empty( $_POST['confirm'] ) ) { wp_die( '確認にチェックしてください', '', array( 'response' => 400 ) ); }

	ais_hub_register_cpt();
	$created = get_option( 'ais_hub_created', array( 'pages' => array(), 'businesses' => array(), 'cats' => array() ) );

	// ニュース用カテゴリ
	foreach ( array( 'お知らせ', '事業情報', '業界ニュース', 'コラム' ) as $cname ) {
		if ( ! term_exists( $cname, 'category' ) ) {
			$t = wp_insert_term( $cname, 'category' );
			if ( ! is_wp_error( $t ) ) { $created['cats'][ $cname ] = (int) $t['term_id']; }
		}
	}

	// サンプル記事（ニュース／コラムの体裁用・冪等）
	foreach ( ais_hub_sample_posts() as $sp ) {
		$exist = get_posts( array( 'name' => $sp['slug'], 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ) );
		if ( $exist ) {
			if ( ! empty( $_POST['overwrite'] ) ) {
				wp_update_post( array( 'ID' => $exist[0]->ID, 'post_title' => $sp['title'], 'post_content' => $sp['content'] ) );
				$term = get_term_by( 'name', $sp['cat'], 'category' );
				if ( $term ) { wp_set_post_categories( $exist[0]->ID, array( (int) $term->term_id ) ); }
			}
			continue;
		}
		$pid = wp_insert_post( array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => $sp['title'],
			'post_name'    => $sp['slug'],
			'post_content' => $sp['content'],
		), true );
		if ( ! is_wp_error( $pid ) ) {
			$term = get_term_by( 'name', $sp['cat'], 'category' );
			if ( $term ) { wp_set_post_categories( $pid, array( (int) $term->term_id ) ); }
			$created['posts'][ $sp['slug'] ] = $pid;
		}
	}

	// 事業（冪等：slugで判定）
	foreach ( ais_hub_default_businesses() as $b ) {
		$exist = get_posts( array( 'post_type' => 'ais_business', 'name' => $b['slug'], 'post_status' => 'any', 'numberposts' => 1 ) );
		if ( $exist ) { continue; }
		$title = $b['ja'] ? ( $b['en'] . '（' . $b['ja'] . '）' ) : $b['en'];
		$pid   = wp_insert_post( array(
			'post_type'    => 'ais_business',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $b['slug'],
			'menu_order'   => $b['order'],
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html( $b['cat'] ) . '：' . esc_html( $b['desc'] ) . '</p><!-- /wp:paragraph -->',
		), true );
		if ( ! is_wp_error( $pid ) ) {
			foreach ( array( 'en', 'ja', 'cat', 'desc', 'layout', 'status', 'cta' ) as $mk ) {
				update_post_meta( $pid, 'ais_' . $mk, $b[ $mk ] );
			}
			update_post_meta( $pid, 'ais_illo', $b['slug'] );
			$created['businesses'][ $b['slug'] ] = $pid;
		}
	}

	// 固定ページ
	foreach ( ais_hub_sitemap() as $pg ) {
		if ( '' === $pg['path'] ) { $pg['path'] = 'home'; }
		$content = isset( $pg['content'] ) ? $pg['content'] : '';
		list( $pid, $is_new ) = ais_hub_ensure_page( $pg['path'], $pg['title'], $content, $created );
		if ( $pid ) {
			if ( ! $is_new && ! empty( $_POST['overwrite'] ) && '' !== $content ) {
				wp_update_post( array( 'ID' => $pid, 'post_content' => $content ) );
			}
			$created['pages'][ $pg['key'] ] = $pid;
			if ( $is_new ) { $created['pages_new'][] = $pid; }
		}
	}

	// 採用情報ページは廃止：当プラグインが作成したものがあればゴミ箱へ
	if ( ! empty( $created['pages']['recruit'] ) ) {
		wp_trash_post( (int) $created['pages']['recruit'] );
		unset( $created['pages']['recruit'] );
	}

	// フロントページ設定（元設定を保存）
	if ( ! isset( $created['prev_front'] ) ) {
		$created['prev_front'] = array(
			'show_on_front' => get_option( 'show_on_front' ),
			'page_on_front' => (int) get_option( 'page_on_front' ),
			'page_for_posts' => (int) get_option( 'page_for_posts' ),
		);
	}
	if ( ! empty( $created['pages']['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $created['pages']['home'] );
	}
	if ( ! empty( $created['pages']['news'] ) ) {
		update_option( 'page_for_posts', (int) $created['pages']['news'] );
	}

	update_option( 'ais_hub_created', $created );
	update_option( 'ais_hub_setup_done', 1 );
	flush_rewrite_rules();
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-setup&ran=1' ) );
	exit;
} );

add_action( 'admin_post_ais_hub_rollback', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_rollback_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_rollback_nonce'] ) ), 'ais_hub_rollback' ) ) {
		wp_die( '不正なリクエストです', '', array( 'response' => 403 ) );
	}
	$created = get_option( 'ais_hub_created', array() );
	if ( ! empty( $created['prev_front'] ) ) {
		update_option( 'show_on_front', $created['prev_front']['show_on_front'] );
		update_option( 'page_on_front', $created['prev_front']['page_on_front'] );
		update_option( 'page_for_posts', $created['prev_front']['page_for_posts'] );
	}
	if ( ! empty( $_POST['trash'] ) ) {
		$ids = array_merge(
			array_values( isset( $created['pages'] ) ? $created['pages'] : array() ),
			array_values( isset( $created['businesses'] ) ? $created['businesses'] : array() )
		);
		foreach ( $ids as $id ) { if ( $id ) { wp_trash_post( (int) $id ); } }
	}
	update_option( 'ais_hub_setup_done', 0 );
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-setup&rolled=1' ) );
	exit;
} );
