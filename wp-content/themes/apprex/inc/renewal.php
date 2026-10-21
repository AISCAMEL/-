<?php
/**
 * サイトリニューアル（v1.41.0）。
 *
 * - トップページの新デザイン（template-parts/renewal/*）と旧デザインの切り替え
 *   （外観 > カスタマイズ > トップページデザイン。既定は新デザイン）
 * - 新デザイン用CSS/JS・フォントの読み込み（対象ページのみ）
 * - 「AIスタジオ」「マッチングアプリ開発」固定ページの自動作成
 * - 新デザインのFAQ（画面表示・FAQPage構造化データ・AIチャットの知識で共通）
 *
 * フォーム送信・自動返信・CRM・広告計測・AIチャットなど既存の仕組みはそのまま利用する。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 新デザインのトップを使うか。 */
function apprex_rn_enabled() {
	$mode = apprex_rn_mode();
	if ( 'preview' === $mode ) {
		// 公開前プレビュー：ログイン中の管理者・編集者だけが新デザインを見られる。
		return current_user_can( 'edit_posts' );
	}
	return 'renewal' === $mode;
}

/**
 * 公開モード。preview（公開前・管理者だけ）／renewal（公開）／classic（旧デザイン）。
 * 既定は preview：テーマを入れ替えても、公開ボタンを押すまで訪問者の見た目は変わらない。
 */
function apprex_rn_mode() {
	$mode = get_theme_mod( 'apprex_front_design', 'preview' );
	return in_array( $mode, array( 'preview', 'renewal', 'classic' ), true ) ? $mode : 'preview';
}

/** AIスタジオ・マッチングページを訪問者に公開しているか。 */
function apprex_rn_public() {
	return 'renewal' === apprex_rn_mode();
}

/** 新デザイン用画像のURL。 */
function apprex_rn_img( $file ) {
	return APPREX_URI . '/assets/images/rn/' . ltrim( $file, '/' );
}

/** AIスタジオ（会員システム）のURL。 */
function apprex_studio_url() {
	return apprex_page_url( 'studio' );
}

/** リニューアルで追加した固定ページ（slug => [タイトル, テンプレート]）。 */
function apprex_rn_pages() {
	return array(
		'studio'       => array( 'AIスタジオ', 'page-templates/page-studio.php' ),
		'matching-app' => array( 'マッチングアプリ開発', 'page-templates/page-matching.php' ),
		'manual'       => array( 'アプリの作り方マニュアル', 'page-templates/page-manual.php' ),
	);
}

/** リニューアルで追加したテンプレート一覧。 */
function apprex_rn_templates() {
	return wp_list_pluck( array_values( apprex_rn_pages() ), 1 );
}

/** リニューアルで追加した、今までのページと同じ作りの固定ページ（新デザインの見た目は rn-skin で当てる）。 */
function apprex_rn_extra_pages() {
	return array(
		'subsidy' => array( '補助金で導入', 'page-templates/page-subsidy.php' ),
	);
}

/** 公開前は訪問者に見せないテンプレート。 */
function apprex_rn_hidden_templates() {
	return array_merge( apprex_rn_templates(), wp_list_pluck( array_values( apprex_rn_extra_pages() ), 1 ) );
}

/** 新デザインのCSSを読み込むページか。 */
function apprex_rn_is_page() {
	if ( is_front_page() && apprex_rn_enabled() ) {
		return true;
	}
	return is_page_template( apprex_rn_templates() );
}

/* -------------------------------------------------------------------------
 * カスタマイザー：トップページデザインの切り替え
 * ---------------------------------------------------------------------- */
add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section(
		'apprex_design',
		array(
			'title'    => 'トップページデザイン',
			'priority' => 30,
		)
	);
	$wp_customize->add_setting(
		'apprex_front_design',
		array(
			'default'           => 'preview',
			'sanitize_callback' => function ( $v ) {
				return in_array( $v, array( 'preview', 'renewal', 'classic' ), true ) ? $v : 'preview';
			},
		)
	);
	$wp_customize->add_control(
		'apprex_front_design',
		array(
			'section'     => 'apprex_design',
			'label'       => 'リニューアルの公開',
			'description' => '「公開前プレビュー」の間は、ログイン中の管理者だけが新デザイン・AIスタジオ・マッチングページを確認できます。訪問者には今までのサイトが表示されます。準備ができたら「公開する」に切り替えてください。いつでも戻せます。',
			'type'        => 'radio',
			'choices'     => array(
				'preview' => '公開前プレビュー（管理者だけに表示）',
				'renewal' => '公開する（全員に新デザインを表示）',
				'classic' => '旧デザインのまま',
			),
		)
	);
} );

/* -------------------------------------------------------------------------
 * アセット
 * ---------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! apprex_rn_is_page() ) {
		return;
	}
	wp_enqueue_style(
		'apprex-rn-fonts',
		'https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@500;700;900&family=Outfit:wght@600;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'apprex-renewal', APPREX_URI . '/assets/css/renewal.css', array( 'apprex-style' ), APPREX_VERSION );
	wp_enqueue_script( 'apprex-renewal', APPREX_URI . '/assets/js/renewal.js', array( 'apprex-forms' ), APPREX_VERSION, true );
}, 20 );

/* 公開前は、AIスタジオ・マッチングページを管理者以外に見せない（トップへ移動）。 */
add_action( 'template_redirect', function () {
	if ( apprex_rn_public() || current_user_can( 'edit_posts' ) || ! is_page() ) {
		return;
	}
	if ( is_page_template( apprex_rn_hidden_templates() ) ) {
		// 会員ログインの送信（POST）は通す。
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}
}, 5 );

/* 公開前は、新しいページを検索エンジンに登録させない。 */
add_filter( 'wp_robots', function ( $robots ) {
	if ( ! apprex_rn_public() && is_page_template( apprex_rn_hidden_templates() ) ) {
		$robots['noindex'] = true;
	}
	return $robots;
} );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' === $post_type && ! apprex_rn_public() ) {
		$ids = array();
		foreach ( array_keys( apprex_rn_pages() + apprex_rn_extra_pages() ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p ) {
				$ids[] = $p->ID;
			}
		}
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids );
	}
	return $args;
}, 10, 2 );

/* プレビュー中であることを管理者に知らせる帯。 */
add_action( 'wp_body_open', function () {
	if ( 'preview' === apprex_rn_mode() && current_user_can( 'edit_posts' ) ) {
		printf(
			'<div style="background:#0b1d3f;color:#fff;text-align:center;font-size:13px;padding:6px 12px">リニューアルの<strong>公開前プレビュー</strong>中です（管理者だけに表示）。訪問者には今までのサイトが表示されています。<a style="color:#ffcf6e;margin-left:8px" href="%s">公開の設定</a></div>',
			esc_url( admin_url( 'customize.php?autofocus[section]=apprex_design' ) )
		);
	}
}, 1 );

/* トップのヒーロー画像を先読み（LCP改善）。 */
add_action( 'wp_head', function () {
	if ( is_front_page() && apprex_rn_enabled() ) {
		printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( apprex_rn_img( '01_hero-phones.jpg' ) ) );
	}
}, 2 );

add_filter( 'body_class', function ( $classes ) {
	if ( apprex_rn_is_page() ) {
		$classes[] = 'is-renewal';
	}
	if ( apprex_rn_skin() ) {
		$classes[] = 'rn-skin';
		if ( apprex_rn_hero_image() ) {
			$classes[] = 'rn-hasimg';
		}
	}
	return $classes;
} );

/* -------------------------------------------------------------------------
 * トップ以外のページ（特徴・料金・会社概要・フォーム・ブログなど）も新デザインの見た目に
 * ---------------------------------------------------------------------- */

/** 新デザインの見た目を当てるページか（専用デザインのLPは除く）。 */
function apprex_rn_skin() {
	if ( ! apprex_rn_enabled() || is_front_page() || apprex_rn_is_page() ) {
		return false;
	}
	return ! is_page_template( array( 'page-templates/page-lp.php', 'page-templates/page-lp-canvas.php', 'page-templates/page-nationwide-lp.php' ) );
}

/** ページ上部に出す画像（固定ページのアイキャッチ画像）。 */
function apprex_rn_hero_image() {
	$id = is_home() ? (int) get_option( 'page_for_posts' ) : ( is_page() ? get_queried_object_id() : 0 );
	if ( ! $id ) {
		return '';
	}
	if ( has_post_thumbnail( $id ) ) {
		return (string) get_the_post_thumbnail_url( $id, 'large' );
	}
	// アイキャッチが無いときは、テーマ同梱の画像（assets/images/pages/{スラッグ}.webp）。
	$slug = is_home() ? 'blog' : (string) get_post_field( 'post_name', $id );
	$file = '/assets/images/pages/' . sanitize_file_name( $slug ) . '.webp';
	return ( '' !== $slug && file_exists( APPREX_DIR . $file ) ) ? APPREX_URI . $file . '?ver=' . APPREX_VERSION : '';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! apprex_rn_skin() ) {
		return;
	}
	wp_enqueue_style(
		'apprex-rn-fonts',
		'https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@500;700;900&family=Outfit:wght@600;800&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'apprex-renewal-pages', APPREX_URI . '/assets/css/renewal-pages.css', array( 'apprex-style' ), APPREX_VERSION );
	$img = apprex_rn_hero_image();
	if ( $img ) {
		wp_add_inline_style( 'apprex-renewal-pages', 'body.rn-skin{--rn-hero-img:url("' . esc_url_raw( $img ) . '")}' );
	}
}, 20 );

/* -------------------------------------------------------------------------
 * 固定ページの自動作成（AIスタジオ／マッチングアプリ開発／作り方マニュアル）
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( (int) get_option( 'apprex_rn_pages_created' ) >= 3 ) {
		return;
	}
	$defs = apprex_rn_pages() + apprex_rn_extra_pages();
	foreach ( $defs as $slug => $def ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => $def[0],
				'post_name'   => $slug,
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', $def[1] );
		}
	}
	// トップの「新着ニュース」用カテゴリー（slug: news）。
	if ( ! term_exists( 'news', 'category' ) ) {
		wp_insert_term( 'お知らせ', 'category', array( 'slug' => 'news' ) );
	}
	update_option( 'apprex_rn_pages_created', 3 );
} );

/* -------------------------------------------------------------------------
 * FAQ（新デザイン）
 * ---------------------------------------------------------------------- */

/**
 * 新デザインのFAQ。
 *
 * @return array<int, array{q:string, a:string}>
 */
function apprex_rn_faqs() {
	return apply_filters(
		'apprex_rn_faqs',
		array(
			array( 'q' => 'AIスタジオは本当に無料ですか？', 'a' => 'はい。会員登録、AIでのデザイン・仕様書づくり、開発の相談、オンラインミーティングまで無料です。お見積りに納得してご入金いただくまで費用はかかりません。' ),
			array( 'q' => '初期費用には何が含まれますか？', 'a' => 'AIスタジオ（無料）で作ったデザインと仕様書をもとにした制作、管理画面の設定、App Store・Google Play への申請、公開後3ヶ月の運用サポートが含まれます。AIスタジオで作った内容からなら150,000円〜、AIスタジオを使わず1からヒアリングして作る場合は300,000円〜です。' ),
			array( 'q' => 'ホームページも一緒に頼めますか？', 'a' => 'はい。AIスタジオで仕様書ができると、業種と目的に合わせてホームページ制作（月額9,800円〜・初期費用0円）やオプション、AIMEO（AIで運用するGoogleマップ対策）・LINE構築などの集客施策をご提案します。必要なものだけお選びいただけます。' ),
			array( 'q' => 'オリジナル開発は他社と何が違いますか？', 'a' => 'ログインや会員管理など、どのアプリにも必要な部分はAPPREXの開発基盤を使い、独自の部分だけを開発します。Flutterで iOS・Android を同時に作るので、一般的なフルスクラッチ開発より費用と期間を抑えられます。' ),
			array( 'q' => 'オリジナルアプリはいくらから作れますか？', 'a' => '機能によって変わるため個別にお見積りします。マッチングアプリは開発費300万円〜・月額保守7万円〜が目安です。APPREXの基盤を使うので、一から作る場合より費用を抑えられます。' ),
			array( 'q' => '公開までどのくらいかかりますか？', 'a' => 'ノーコードは最短2週間、オリジナル開発・マッチングアプリは3ヶ月〜が目安です。ストアの審査期間も含めてスケジュールをご提示します。' ),
			array( 'q' => '公開後の保守費用はかかりますか？', 'a' => 'ノーコードは月額料金に含まれます。オリジナル開発は内容に応じた月額保守をご提案します。' ),
			array( 'q' => '作ったアプリのソースコードはもらえますか？', 'a' => 'ソースコード買取のプランがあります。自社で改修を続けたい場合にお選びください。' ),
			array( 'q' => 'LINE公式アカウントとアプリ、どちらがいいですか？', 'a' => '新しいお客様に広く知らせるならLINE、常連化やリピートを増やすならアプリが得意です。LINEで集めてアプリで常連化する併用がおすすめです。' ),
			array( 'q' => '地方の会社でも依頼できますか？', 'a' => 'はい。打ち合わせから公開まですべてオンラインで、全国47都道府県から依頼いただけます。' ),
			array( 'q' => '補助金は使えますか？', 'a' => '小規模事業者持続化補助金や、都道府県・市区町村の補助金が使える場合があります。使える制度の確認、申請に使う企画書・見積書のご用意、交付決定に合わせた制作スケジュールまでサポートします。なお、APPREXはIT導入補助金の支援事業者ではないため、IT導入補助金は対象外です。' ),
		)
	);
}
