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
	return 'classic' !== get_theme_mod( 'apprex_front_design', 'renewal' );
}

/** 新デザイン用画像のURL。 */
function apprex_rn_img( $file ) {
	return APPREX_URI . '/assets/images/rn/' . ltrim( $file, '/' );
}

/** AIスタジオ（会員システム）のURL。 */
function apprex_studio_url() {
	return apprex_page_url( 'studio' );
}

/** 新デザインのCSSを読み込むページか。 */
function apprex_rn_is_page() {
	if ( is_front_page() && apprex_rn_enabled() ) {
		return true;
	}
	return is_page_template( array( 'page-templates/page-studio.php', 'page-templates/page-matching.php' ) );
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
			'default'           => 'renewal',
			'sanitize_callback' => function ( $v ) {
				return 'classic' === $v ? 'classic' : 'renewal';
			},
		)
	);
	$wp_customize->add_control(
		'apprex_front_design',
		array(
			'section'     => 'apprex_design',
			'label'       => 'トップページのデザイン',
			'description' => 'いつでも元のデザインに戻せます。フォームやチャットなどの機能はどちらでも同じです。',
			'type'        => 'radio',
			'choices'     => array(
				'renewal' => '新デザイン（2026リニューアル）',
				'classic' => '旧デザイン',
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
	return $classes;
} );

/* -------------------------------------------------------------------------
 * 固定ページの自動作成（AIスタジオ／マッチングアプリ開発）
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( get_option( 'apprex_rn_pages_created' ) ) {
		return;
	}
	$defs = array(
		'studio'       => array( 'AIスタジオ', 'page-templates/page-studio.php' ),
		'matching-app' => array( 'マッチングアプリ開発', 'page-templates/page-matching.php' ),
	);
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
	update_option( 'apprex_rn_pages_created', 1 );
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
			array( 'q' => 'セルフプランとおまかせプランの違いは？', 'a' => '月額は同じで、初期費用が違います。デザインと登録をご自身で進めるならセルフプラン（初期費用0円）、企画からストア申請まで任せるならおまかせプラン（制作代行300,000円〜、AIスタジオから相談した方は150,000円〜）です。' ),
			array( 'q' => 'オリジナル開発は他社と何が違いますか？', 'a' => 'ログインや会員管理など、どのアプリにも必要な部分はAPPREXの開発基盤を使い、独自の部分だけを開発します。Flutterで iOS・Android を同時に作るので、一般的なフルスクラッチ開発より費用と期間を抑えられます。' ),
			array( 'q' => 'オリジナルアプリはいくらから作れますか？', 'a' => '機能によって変わるため個別にお見積りします。マッチングアプリは開発費300万円〜・月額保守7万円〜が目安です。APPREXの基盤を使うので、一から作る場合より費用を抑えられます。' ),
			array( 'q' => '公開までどのくらいかかりますか？', 'a' => 'ノーコードは最短2週間、オリジナル開発は最短1ヶ月からです。ストアの審査期間も含めてスケジュールをご提示します。' ),
			array( 'q' => '公開後の保守費用はかかりますか？', 'a' => 'ノーコードは月額料金に含まれます。オリジナル開発は内容に応じた月額保守をご提案します。' ),
			array( 'q' => '作ったアプリのソースコードはもらえますか？', 'a' => 'ソースコード買取のプランがあります。自社で改修を続けたい場合にお選びください。' ),
			array( 'q' => 'LINE公式アカウントとアプリ、どちらがいいですか？', 'a' => '新しいお客様に広く知らせるならLINE、常連化やリピートを増やすならアプリが得意です。LINEで集めてアプリで常連化する併用がおすすめです。' ),
			array( 'q' => '地方の会社でも依頼できますか？', 'a' => 'はい。打ち合わせから公開まですべてオンラインで、全国47都道府県から依頼いただけます。' ),
			array( 'q' => '補助金は使えますか？', 'a' => 'IT導入補助金などが使える場合があります。条件を確認してご案内します。' ),
		)
	);
}
