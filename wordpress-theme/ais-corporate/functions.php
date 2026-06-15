<?php
/**
 * AIS Corporate テーマ — 関数定義。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AIS_THEME_VERSION', '1.0.0' );

require get_template_directory() . '/inc/data.php';
require get_template_directory() . '/inc/helpers.php';
require get_template_directory() . '/inc/seo.php';
require get_template_directory() . '/inc/chat.php';

/** テーマサポート */
function ais_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	register_nav_menus( array(
		'primary' => 'グローバルナビ（任意・既定はテーマ内データを使用）',
	) );
}
add_action( 'after_setup_theme', 'ais_setup' );

/** スタイル・スクリプト・フォントの読み込み */
function ais_assets() {
	// Google Fonts: Zen Kaku Gothic New（preconnect は wp_resource_hints で付与）
	wp_enqueue_style(
		'ais-google-fonts',
		'https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700&display=swap',
		array(),
		null
	);
	// Tailwind ビルド済み CSS
	wp_enqueue_style( 'ais-theme', get_theme_file_uri( '/assets/css/theme.css' ), array(), AIS_THEME_VERSION );
	// メイン JS（モバイルメニュー・スライダー・アコーディオン・スクロール表示）
	wp_enqueue_script( 'ais-main', get_theme_file_uri( '/assets/js/main.js' ), array(), AIS_THEME_VERSION, true );

	// AIチャット（有効かつAPIキー設定時のみ）
	if ( function_exists( 'ais_chat_active' ) && ais_chat_active() ) {
		$opt = ais_chat_options();
		wp_enqueue_script( 'ais-chat', get_theme_file_uri( '/assets/js/chat.js' ), array(), AIS_THEME_VERSION, true );
		wp_localize_script( 'ais-chat', 'AIS_CHAT', array(
			'restUrl'  => esc_url_raw( rest_url( 'ais/v1/chat' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'greeting' => $opt['greeting'],
			'name'     => get_bloginfo( 'name' ),
			'avatar'   => ais_chat_avatar( 'h-full w-full' ),
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'ais_assets' );

/** AIチャットウィジェットをフッターに出力 */
function ais_render_chat_widget() {
	if ( function_exists( 'ais_chat_active' ) && ais_chat_active() ) {
		get_template_part( 'template-parts/chat-widget' );
	}
}
add_action( 'wp_footer', 'ais_render_chat_widget' );

/** Google Fonts 用の preconnect を付与 */
function ais_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'ais_resource_hints', 10, 2 );

/* -------------------------------------------------------------------------
 * カスタム投稿タイプ（事業・実績・お知らせ）
 * 一覧/詳細の URL を Next.js 版と一致させる（/services/<slug> など）。
 * 表示内容はテーマ内のデータ層（inc/data.php）を slug で参照して描画する。
 * ---------------------------------------------------------------------- */
function ais_register_cpts() {
	register_post_type( 'ais_service', array(
		'labels'      => array( 'name' => '事業・サービス', 'singular_name' => '事業・サービス' ),
		'public'      => true,
		'has_archive' => 'services',
		'rewrite'     => array( 'slug' => 'services', 'with_front' => false ),
		'menu_icon'   => 'dashicons-car',
		'supports'    => array( 'title' ),
		'show_in_rest'=> true,
	) );
	register_post_type( 'ais_work', array(
		'labels'      => array( 'name' => '実績', 'singular_name' => '実績' ),
		'public'      => true,
		'has_archive' => 'works',
		'rewrite'     => array( 'slug' => 'works', 'with_front' => false ),
		'menu_icon'   => 'dashicons-portfolio',
		'supports'    => array( 'title' ),
		'show_in_rest'=> true,
	) );
	register_post_type( 'ais_news', array(
		'labels'      => array( 'name' => 'お知らせ・コラム', 'singular_name' => 'お知らせ' ),
		'public'      => true,
		'has_archive' => 'news',
		'rewrite'     => array( 'slug' => 'news', 'with_front' => false ),
		'menu_icon'   => 'dashicons-megaphone',
		'supports'    => array( 'title', 'editor', 'excerpt' ),
		'show_in_rest'=> true,
	) );
}
add_action( 'init', 'ais_register_cpts' );

/* -------------------------------------------------------------------------
 * テーマ有効化時のセットアップ：固定ページ・フロントページ・サンプル投稿の生成
 * ---------------------------------------------------------------------- */
function ais_after_switch() {
	ais_register_cpts();
	ais_provision_pages();
	ais_seed_cpt_posts();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'ais_after_switch' );

/** 固定ページを作成し、テンプレートを割り当て、フロントページを設定 */
function ais_provision_pages() {
	// slug => array(title, template)
	$pages = array(
		'home'       => array( 'ホーム', '' ),
		'about'      => array( 'アイズについて', 'page-templates/about.php' ),
		'message'    => array( '代表メッセージ', 'page-templates/message.php' ),
		'philosophy' => array( '理念', 'page-templates/philosophy.php' ),
		'brands'     => array( 'ブランド一覧', 'page-templates/brands.php' ),
		'faq'        => array( 'よくある質問', 'page-templates/faq.php' ),
		'contact'    => array( 'お問い合わせ', 'page-templates/contact.php' ),
		'privacy'    => array( 'プライバシーポリシー', 'page-templates/privacy.php' ),
	);
	$ids = array();
	foreach ( $pages as $slug => $info ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$ids[ $slug ] = $existing->ID;
			if ( $info[1] ) { update_post_meta( $existing->ID, '_wp_page_template', $info[1] ); }
			continue;
		}
		$id = wp_insert_post( array(
			'post_title'   => $info[0],
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			$ids[ $slug ] = $id;
			if ( $info[1] ) { update_post_meta( $id, '_wp_page_template', $info[1] ); }
		}
	}
	// フロントページを「ホーム」に設定（front-page.php が描画を担当）
	if ( isset( $ids['home'] ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );
	}
}

/** 事業・実績・お知らせの投稿を生成（URL ルーティング用。本文はデータ層を参照） */
function ais_seed_cpt_posts() {
	$map = array(
		'ais_service' => array_map( function ( $s ) { return array( $s['slug'], $s['name'] ); }, ais_services() ),
		'ais_work'    => array_map( function ( $w ) { return array( $w['slug'], $w['title'] ); }, ais_works() ),
		'ais_news'    => array_map( function ( $n ) { return array( $n['slug'], $n['title'] ); }, ais_news() ),
	);
	foreach ( $map as $ptype => $items ) {
		foreach ( $items as $item ) {
			list( $slug, $title ) = $item;
			$found = get_posts( array(
				'post_type'   => $ptype,
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
			) );
			if ( $found ) { continue; }
			wp_insert_post( array(
				'post_title'  => $title,
				'post_name'   => $slug,
				'post_status' => 'publish',
				'post_type'   => $ptype,
			) );
		}
	}
}

/** タイトルタグの区切りとサフィックス */
function ais_document_title_separator() {
	return '｜';
}
add_filter( 'document_title_separator', 'ais_document_title_separator' );

/* -------------------------------------------------------------------------
 * お問い合わせフォーム送信処理（admin-post → wp_mail）
 * ---------------------------------------------------------------------- */
function ais_handle_contact() {
	$contact_url = home_url( '/contact/' );

	// nonce 検証
	if ( ! isset( $_POST['ais_contact_nonce'] ) || ! wp_verify_nonce( $_POST['ais_contact_nonce'], 'ais_contact' ) ) {
		wp_safe_redirect( add_query_arg( 'sent', 'error', $contact_url ) );
		exit;
	}
	// ハニーポット（bot 対策）。値が入っていたら成功偽装して破棄。
	if ( ! empty( $_POST['website'] ) ) {
		wp_safe_redirect( add_query_arg( 'sent', '1', $contact_url ) );
		exit;
	}

	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$company = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

	if ( '' === $name || ! is_email( $email ) || '' === $subject || '' === $message ) {
		wp_safe_redirect( add_query_arg( 'sent', 'error', $contact_url ) );
		exit;
	}

	$site  = ais_site();
	$to    = get_option( 'admin_email', $site['email'] );
	$subj  = '[お問い合わせ] ' . $subject . '（' . $name . '様）';
	$body  = "サイトのお問い合わせフォームから送信がありました。\n\n";
	$body .= "お名前：{$name}\n";
	$body .= "会社名・屋号：{$company}\n";
	$body .= "メール：{$email}\n";
	$body .= "ご相談の種類：{$subject}\n\n";
	$body .= "ご相談内容：\n{$message}\n";
	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );

	wp_mail( $to, $subj, $body, $headers );

	wp_safe_redirect( add_query_arg( 'sent', '1', $contact_url ) );
	exit;
}
add_action( 'admin_post_nopriv_ais_contact', 'ais_handle_contact' );
add_action( 'admin_post_ais_contact', 'ais_handle_contact' );
