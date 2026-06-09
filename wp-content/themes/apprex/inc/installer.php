<?php
/**
 * One-step site seeder.
 *
 * On theme activation this creates the固定ページ (with templates), assigns the
 * static front page, builds the primary nav menu, and imports the bundled app
 * examples as "case" posts. Idempotent: existing pages/cases are left intact.
 *
 * Disable by defining APPREX_DISABLE_SEEDER as true before activation.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run the full seed on theme activation.
 */
function apprex_seed_site() {
	if ( defined( 'APPREX_DISABLE_SEEDER' ) && APPREX_DISABLE_SEEDER ) {
		return;
	}

	apprex_register_cases_cpt();
	apprex_register_industry_taxonomy();

	$pages = apprex_seed_pages();
	apprex_seed_front_page( $pages );
	apprex_seed_menu( $pages );
	apprex_seed_cases();

	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'apprex_seed_site' );

/**
 * Create the required pages and assign their templates.
 *
 * @return array<string,int> slug => post ID.
 */
function apprex_seed_pages() {
	$defs = array(
		'home'        => array( 'title' => 'ホーム', 'tpl' => '' ),
		'features'    => array( 'title' => '特徴', 'tpl' => 'page-templates/page-features.php' ),
		'functions'   => array( 'title' => '機能説明', 'tpl' => 'page-templates/page-functions.php' ),
		'pricing'     => array( 'title' => '料金プラン', 'tpl' => 'page-templates/page-pricing.php' ),
		'faq'         => array( 'title' => 'よくある質問', 'tpl' => 'page-templates/page-faq.php' ),
		'free-trial'  => array( 'title' => '無料体験申し込み', 'tpl' => 'page-templates/page-free-trial.php' ),
		'contact'     => array( 'title' => 'お問い合わせ', 'tpl' => 'page-templates/page-contact.php' ),
		'hp-creation' => array( 'title' => 'ホームページ制作', 'tpl' => 'page-templates/page-hp-creation.php' ),
		'company'     => array( 'title' => '会社概要', 'tpl' => 'page-templates/page-company.php' ),
	);

	$ids = array();
	foreach ( $defs as $slug => $def ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$ids[ $slug ] = $existing->ID;
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $def['title'],
				'post_name'    => $slug,
				'post_content' => '',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			if ( $def['tpl'] ) {
				update_post_meta( $id, '_wp_page_template', $def['tpl'] );
			}
			$ids[ $slug ] = $id;
		}
	}
	return $ids;
}

/**
 * Set the static front page.
 *
 * @param array<string,int> $pages slug => ID.
 */
function apprex_seed_front_page( $pages ) {
	if ( empty( $pages['home'] ) ) {
		return;
	}
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $pages['home'] );
}

/**
 * Build and assign the primary navigation menu.
 *
 * @param array<string,int> $pages slug => ID.
 */
function apprex_seed_menu( $pages ) {
	$menu_name = 'メインメニュー';
	if ( wp_get_nav_menu_object( $menu_name ) ) {
		return; // Menu already exists.
	}
	$menu_id = wp_create_nav_menu( $menu_name );
	if ( is_wp_error( $menu_id ) ) {
		return;
	}

	$order = array( 'features', 'functions', 'pricing', 'company', 'faq' );
	$pos   = 1;
	foreach ( $order as $slug ) {
		if ( empty( $pages[ $slug ] ) ) {
			continue;
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-object-id' => $pages[ $slug ],
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $pos++,
			)
		);
	}

	// 事例 archive link.
	wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-title'   => '事例',
			'menu-item-url'     => home_url( '/cases/' ),
			'menu-item-type'    => 'custom',
			'menu-item-status'  => 'publish',
			'menu-item-position' => $pos++,
		)
	);

	$locations            = (array) get_theme_mod( 'nav_menu_locations' );
	$locations['primary'] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );
}

/**
 * Import bundled app examples as "case" posts (once).
 */
function apprex_seed_cases() {
	$existing = get_posts(
		array(
			'post_type'      => 'case',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		)
	);
	if ( ! empty( $existing ) ) {
		return; // Already have cases.
	}

	$cases = array(
		array( 'TAKA — マッチングアプリ', 'マッチング', '会員登録+200%', '3週間', 'app-sample-taka.jpg', 'スキルマッチング型アプリ。チャット・プロフィール・検索機能をノーコードで実装し、短期間でリリースしました。' ),
		array( 'Golf One — ゴルフ予約アプリ', 'スポーツ・予約', '予約数+120%', '2週間', 'app-sample-golf-one.jpg', 'ゴルフ場予約・スコア管理アプリ。会員証・プッシュ通知でリピート率を向上させました。' ),
		array( 'Legal One — 法律相談アプリ', '士業・相談', '問合せ+90%', '3週間', 'app-sample-legal-one.jpg', 'オンライン法律相談アプリ。予約・チャット・決済を統合し、相談導線を最適化しました。' ),
		array( 'Lien — コミュニティアプリ', 'コミュニティ', '継続率+45%', '4週間', 'app-sample-lien-new.jpg', '会員コミュニティアプリ。トーク・イベント・通知機能で活発な交流を実現しました。' ),
		array( 'House Bank — 不動産アプリ', '不動産', '反響+70%', '3週間', 'app-sample-house-bank.jpg', '物件検索・問い合わせアプリ。条件検索とお気に入り機能で反響数を伸ばしました。' ),
	);

	foreach ( $cases as $c ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'case',
				'post_status'  => 'publish',
				'post_title'   => $c[0],
				'post_content' => $c[5],
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		update_post_meta( $post_id, 'case_industry', $c[1] );
		update_post_meta( $post_id, 'case_metric_1', $c[2] );
		update_post_meta( $post_id, 'case_duration', $c[3] );

		$attach_id = apprex_sideload_theme_image( $c[4], $post_id );
		if ( $attach_id ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
	}
}

/**
 * Copy a bundled theme image into the media library.
 *
 * @param string $filename File name under assets/images/.
 * @param int    $parent   Parent post ID.
 * @return int|false Attachment ID or false.
 */
function apprex_sideload_theme_image( $filename, $parent = 0 ) {
	$src = APPREX_DIR . '/assets/images/' . $filename;
	if ( ! file_exists( $src ) ) {
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload = wp_upload_bits( $filename, null, file_get_contents( $src ) ); // phpcs:ignore
	if ( ! empty( $upload['error'] ) ) {
		return false;
	}

	$filetype = wp_check_filetype( $upload['file'], null );
	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( $filename ),
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$parent
	);
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return false;
	}
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );
	return $attach_id;
}
