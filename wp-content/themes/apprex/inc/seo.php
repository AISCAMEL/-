<?php
/**
 * SEO / LLMO 強化。
 *
 * - meta description / Open Graph / Twitter Card
 * - 構造化データ JSON-LD（Organization, WebSite, BreadcrumbList, Article, FAQPage）
 * - /llms.txt（LLM 向けサイト要約）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 現在ページの説明文（meta description / OG 用）。
 *
 * @return string
 */
function apprex_meta_description() {
	$default = 'ノーコードでiOS/Androidアプリを開発できるクラウド型プラットフォーム「APPREX（アプリックス）」。高性能・低価格（従来の1/10）・スピード公開（最短2週間）。制作代行・ホームページ制作も。合同会社アイズ運営。';
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post && has_excerpt( $post ) ) {
			return wp_strip_all_tags( get_the_excerpt( $post ) );
		}
		if ( $post && ! empty( $post->post_content ) ) {
			$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$text = trim( preg_replace( '/\s+/', ' ', $text ) );
			if ( $text ) {
				return mb_substr( $text, 0, 120 );
			}
		}
	}
	return $default;
}

/**
 * head にメタタグ・OGP・Twitter Card を出力。
 */
function apprex_head_meta() {
	$desc  = apprex_meta_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( null, null ) );
	$img   = APPREX_URI . '/assets/images/app-sample-taka.jpg';
	if ( is_singular() && has_post_thumbnail() ) {
		$img = get_the_post_thumbnail_url( null, 'large' );
	}
	$gsc = get_option( 'apprex_gsc_verify', '' );
	?>
	<?php if ( $gsc ) : ?>
	<meta name="google-site-verification" content="<?php echo esc_attr( $gsc ); ?>">
	<?php endif; ?>
	<meta name="description" content="<?php echo esc_attr( $desc ); ?>">
	<meta property="og:type" content="<?php echo is_singular( 'post' ) ? 'article' : 'website'; ?>">
	<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>">
	<meta property="og:url" content="<?php echo esc_url( $url ); ?>">
	<meta property="og:site_name" content="APPREX（アプリックス）">
	<meta property="og:image" content="<?php echo esc_url( $img ); ?>">
	<meta property="og:locale" content="ja_JP">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
	<meta name="twitter:description" content="<?php echo esc_attr( $desc ); ?>">
	<meta name="twitter:image" content="<?php echo esc_url( $img ); ?>">
	<?php
}
add_action( 'wp_head', 'apprex_head_meta', 1 );

/**
 * 構造化データ（JSON-LD）を出力。
 */
function apprex_jsonld() {
	$graph = array();

	$org = array(
		'@type'       => 'Organization',
		'@id'         => home_url( '/#organization' ),
		'name'        => 'APPREX（アプリックス）',
		'legalName'   => '合同会社アイズ',
		'url'         => home_url( '/' ),
		'logo'        => APPREX_URI . '/assets/images/apprex-logo.png',
		'sameAs'      => array( 'https://www.instagram.com/apprex1173/' ),
		'description' => 'ノーコードアプリ開発プラットフォーム。制作代行・ホームページ制作も提供。',
		'email'       => function_exists( 'apprex_contact_email' ) ? apprex_contact_email() : '',
		'foundingDate' => '2018-10',
		'founder'     => array( '@type' => 'Person', 'name' => '吉田一平' ),
		'address'     => array(
			'@type'           => 'PostalAddress',
			'addressCountry'  => 'JP',
			'addressRegion'   => '福島県',
			'addressLocality' => 'いわき市',
			'streetAddress'   => '四倉町細谷字大町1番',
		),
	);
	$graph[] = $org;

	$graph[] = array(
		'@type'           => 'WebSite',
		'@id'             => home_url( '/#website' ),
		'url'             => home_url( '/' ),
		'name'            => 'APPREX（アプリックス）',
		'publisher'       => array( '@id' => home_url( '/#organization' ) ),
		'inLanguage'      => 'ja',
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		),
	);

	// サービス（ホーム）。
	if ( is_front_page() ) {
		$graph[] = array(
			'@type'       => 'Service',
			'serviceType' => 'ノーコードアプリ開発・アプリ制作代行・ホームページ制作',
			'provider'    => array( '@id' => home_url( '/#organization' ) ),
			'areaServed'  => 'JP',
			'offers'      => array(
				'@type'         => 'Offer',
				'priceCurrency' => 'JPY',
				'price'         => '19800',
				'description'   => 'アプリ開発 月額19,800円〜・初期費用0円',
			),
		);
		// FAQ（トップ）。
		$graph[] = apprex_faq_schema();
	}

	if ( is_page( 'faq' ) ) {
		$graph[] = apprex_faq_schema();
	}

	// パンくず。
	if ( is_singular() && ! is_front_page() ) {
		$graph[] = apprex_breadcrumb_schema();
	}

	// 記事。
	if ( is_singular( 'post' ) ) {
		$post    = get_queried_object();
		$graph[] = array(
			'@type'         => 'Article',
			'headline'      => get_the_title( $post ),
			'description'   => apprex_meta_description(),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'author'        => array( '@type' => 'Organization', 'name' => 'APPREX編集部' ),
			'publisher'     => array( '@id' => home_url( '/#organization' ) ),
			'mainEntityOfPage' => get_permalink( $post ),
			'image'         => has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'large' ) : ( APPREX_URI . '/assets/images/app-sample-taka.jpg' ),
		);
	}

	$data = array( '@context' => 'https://schema.org', '@graph' => $graph );
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'apprex_jsonld', 5 );

/**
 * FAQPage スキーマ（既定FAQから生成）。
 *
 * @return array
 */
function apprex_faq_schema() {
	$items = array();
	if ( function_exists( 'apprex_default_faqs' ) ) {
		foreach ( apprex_default_faqs() as $faq ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => $faq['q'],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $faq['a'] ),
			);
		}
	}
	return array( '@type' => 'FAQPage', 'mainEntity' => $items );
}

/**
 * BreadcrumbList スキーマ。
 *
 * @return array
 */
function apprex_breadcrumb_schema() {
	$items = array(
		array( '@type' => 'ListItem', 'position' => 1, 'name' => 'ホーム', 'item' => home_url( '/' ) ),
	);
	$obj = get_queried_object();
	if ( $obj instanceof WP_Post ) {
		$items[] = array( '@type' => 'ListItem', 'position' => 2, 'name' => get_the_title( $obj ), 'item' => get_permalink( $obj ) );
	}
	return array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

/* -------------------------------------------------------------------------
 * /llms.txt — LLM 向けのサイト要約（LLMO）
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?apprex_llms=1', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'apprex_llms';
	return $vars;
} );
// /llms.txt に末尾スラッシュを付ける正規化リダイレクトを抑止。
add_filter( 'redirect_canonical', function ( $redirect ) {
	return get_query_var( 'apprex_llms' ) ? false : $redirect;
} );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_llms' ) ) {
		return;
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	$pricing = function_exists( 'apprex_pricing_summary_text' ) ? apprex_pricing_summary_text() : '';
	$home    = home_url( '/' );
	echo "# APPREX（アプリックス）\n\n";
	echo "> ノーコードでiOS/Androidアプリを開発できるクラウド型プラットフォーム。制作代行・ホームページ制作も提供。運営：合同会社アイズ。\n\n";
	echo "## 概要\n";
	echo "- 強み：高性能・低価格（従来の1/10）・スピード公開（最短2週間）・専任サポート・分析機能\n";
	echo "- 導入実績：8,000社以上 / 対応：iOS・Android\n";
	echo "- 窓口：チャット・メール・オンライン相談（平日10:00〜18:00、電話窓口なし）\n\n";
	echo "## 料金\n{$pricing}\n\n";
	echo "## 主要ページ\n";
	echo "- トップ: {$home}\n";
	echo "- 特徴: {$home}features/\n";
	echo "- 機能: {$home}functions/\n";
	echo "- 料金: {$home}pricing/\n";
	echo "- 導入事例: {$home}cases/\n";
	echo "- 見積もり・発注: {$home}estimate/\n";
	echo "- ホームページ制作: {$home}hp-creation/\n";
	echo "- 資料請求: {$home}document/\n";
	echo "- 無料体験: {$home}free-trial/\n";
	echo "- お問い合わせ: {$home}contact/\n";
	exit;
} );
