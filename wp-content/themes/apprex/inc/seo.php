<?php
/**
 * SEO / LLMO 強化。
 *
 * - タイトル整形（document_title_parts / separator）＋ページ別の最適タイトル
 * - meta description（ページ別に最適化）/ Open Graph / Twitter Card
 * - canonical（全ページタイプ対応）
 * - robots（検索結果・404 を noindex）
 * - 構造化データ JSON-LD（Organization, WebSite, Service, BreadcrumbList, Article, FAQPage）
 * - /llms.txt（LLM 向けサイト要約）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const APPREX_BRAND = 'APPREX（アプリックス）';

/**
 * ページ別の SEO タイトル・説明文（スラッグ基準）。
 * キー 'front' はトップページ。
 *
 * @return array<string,array{title:string,desc:string}>
 */
function apprex_seo_pages() {
	return array(
		'front'       => array(
			'title' => 'ノーコードアプリ開発プラットフォーム',
			'desc'  => 'ノーコードでiOS/Androidアプリを開発できるクラウド型プラットフォーム「APPREX（アプリックス）」。高性能・低価格（従来の1/10）・最短2週間で公開。アプリ制作代行・ホームページ制作も。合同会社アイズ運営。',
		),
		'features'    => array(
			'title' => 'APPREXの特徴',
			'desc'  => 'APPREXが選ばれる理由。高性能・低価格（従来の1/10）・最短2週間で公開・専任サポート・分析機能。8,000社以上の導入実績を持つノーコードアプリ開発プラットフォームの特徴をご紹介します。',
		),
		'functions'   => array(
			'title' => 'アプリ機能一覧',
			'desc'  => 'プッシュ通知・予約・EC・会員管理・電子スタンプ・分析など、APPREXで実装できる主要機能をご紹介。ノーコードで多彩なアプリ機能を低コストに実現できます。',
		),
		'pricing'     => array(
			'title' => '料金プラン',
			'desc'  => 'アプリ開発は月額19,800円〜・初期費用0円。明朗な料金プランとオプションをご案内します。見積もりフォームから概算の確認と、そのままオンライン発注も可能です。',
		),
		'estimate'    => array(
			'title' => '無料見積もり・オンライン発注',
			'desc'  => 'サービスとオプションを選ぶだけで、アプリ開発費用の概算を自動計算。お見積りからそのままオンラインで発注まで進められます。初期費用0円・月額19,800円〜。',
		),
		'hp-creation' => array(
			'title' => 'ホームページ制作',
			'desc'  => 'スマホ対応・SEO・集客に強いホームページ制作。アプリと連携した一貫した集客導線を、低価格・スピード納品でご提供します。',
		),
		'document'    => array(
			'title' => '資料請求（無料）',
			'desc'  => 'APPREXのサービス資料を無料でダウンロードいただけます。機能・料金・導入事例をまとめた資料で、アプリ導入の検討にお役立てください。',
		),
		'free-trial'  => array(
			'title' => '無料体験のお申し込み',
			'desc'  => 'APPREXを無料でお試しいただけます。ノーコードでのアプリ開発を、実際の管理画面で体験してみませんか。お気軽にお申し込みください。',
		),
		'contact'     => array(
			'title' => 'お問い合わせ',
			'desc'  => 'APPREX（アプリックス）へのお問い合わせはこちら。アプリ開発・ホームページ制作・料金・お見積りなど、お気軽にご相談ください。',
		),
		'meeting'     => array(
			'title' => 'オンライン相談のご予約',
			'desc'  => 'APPREXの担当者とオンラインで無料相談。アプリ開発の進め方・費用・機能など、ご希望の日時でお気軽にご相談いただけます（平日10:00〜18:00）。',
		),
		'faq'         => array(
			'title' => 'よくある質問（FAQ）',
			'desc'  => 'APPREX（アプリックス）に関するよくあるご質問と回答。料金・開発期間・対応OS・サポート体制など、導入前の疑問を解消できます。',
		),
		'company'     => array(
			'title' => '会社概要',
			'desc'  => 'APPREXを運営する「合同会社アイズ」の会社概要。所在地・事業内容・沿革をご紹介します。',
		),
		'partner'     => array(
			'title' => 'パートナー募集',
			'desc'  => 'APPREXのパートナー（代理店・紹介）を募集しています。アプリ開発・ホームページ制作で一緒にビジネスを広げませんか。',
		),
		'blog'        => array(
			'title' => 'お役立ちブログ',
			'desc'  => 'アプリ開発・ノーコード・集客・DXに関するお役立ち情報を発信。APPREX編集部によるコラムをお届けします。',
		),
	);
}

/**
 * 現在ページに対応する SEO 設定を返す（無ければ null）。
 *
 * @return array{title:string,desc:string}|null
 */
function apprex_current_seo() {
	$map = apprex_seo_pages();
	if ( is_front_page() ) {
		return $map['front'];
	}
	if ( is_page() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && isset( $map[ $post->post_name ] ) ) {
			return $map[ $post->post_name ];
		}
	}
	if ( is_post_type_archive( 'apprex_case' ) || is_singular( 'apprex_case' ) ) {
		return array(
			'title' => is_singular( 'apprex_case' ) ? get_the_title() : '導入事例',
			'desc'  => 'APPREXで開発されたアプリの導入事例をご紹介。業種別の活用方法や成果から、自社アプリのイメージを具体化できます。',
		);
	}
	return null;
}

/**
 * 現在ページの説明文（meta description / OG 用）。
 *
 * @return string
 */
function apprex_meta_description() {
	$seo = apprex_current_seo();
	if ( $seo && ! is_singular( 'post' ) ) {
		return $seo['desc'];
	}
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
	return apprex_seo_pages()['front']['desc'];
}

/* -------------------------------------------------------------------------
 * タイトル整形
 * ---------------------------------------------------------------------- */
add_filter( 'document_title_separator', function () {
	return '｜';
} );

add_filter( 'document_title_parts', function ( $parts ) {
	$seo = apprex_current_seo();
	if ( is_front_page() ) {
		$parts = array(
			'title' => apprex_seo_pages()['front']['title'],
			'site'  => APPREX_BRAND,
		);
		return $parts;
	}
	if ( $seo && empty( $parts['title'] ) === false ) {
		$parts['title'] = $seo['title'];
	}
	$parts['site'] = APPREX_BRAND;
	return $parts;
} );

/* -------------------------------------------------------------------------
 * canonical（コア標準は singular のみ → 全ページタイプに対応）
 * ---------------------------------------------------------------------- */
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', 'apprex_canonical', 1 );
function apprex_canonical() {
	$url = '';
	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_singular() ) {
		$url = get_permalink();
	} elseif ( is_home() ) {
		$pid = (int) get_option( 'page_for_posts' );
		$url = $pid ? get_permalink( $pid ) : home_url( '/' );
	} elseif ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( get_post_type() );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$link = get_term_link( $term );
			$url  = is_wp_error( $link ) ? '' : $link;
		}
	}
	if ( $url ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}

/* -------------------------------------------------------------------------
 * robots（検索結果・404 はインデックスさせない）
 * ---------------------------------------------------------------------- */
add_filter( 'wp_robots', function ( $robots ) {
	// 薄い/重複になりやすい自動生成ページもインデックス対象外（クロール済み-未登録の主因）。
	if ( is_search() || is_404() || apprex_is_noindex_lp()
		|| is_author() || is_date() || is_attachment() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
} );

/* 添付ファイル（画像）個別ページは薄いため、画像本体 or 親記事へ301。 */
add_action( 'template_redirect', function () {
	if ( ! is_attachment() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$dest = $post->post_parent ? get_permalink( $post->post_parent ) : wp_get_attachment_url( $post->ID );
	if ( $dest ) {
		wp_safe_redirect( $dest, 301 );
		exit;
	}
} );

/* REST API の自動探索リンクを <head>/Link ヘッダーから除去。
 * /wp-json/ はページではないため、検索ボットに辿らせない（403クロールの露出を防ぐ）。
 * フォーム等のREST機能は localize 済みURLを使うため影響しない。 */
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

/**
 * 広告LP（LPtools の clp / テンプレート）の検索インデックス可否。
 * 既定は noindex（広告専用）。ただし clp 個別の設定で「インデックスする」を
 * ONにしたLPは検索対象にする（検索でも集客したいLP向け）。
 *
 * @return bool noindex にすべきなら true。
 */
function apprex_is_noindex_lp() {
	if ( is_singular( 'clp' ) ) {
		// 個別LPで「インデックスする」がONなら検索対象（noindexにしない）。
		return '1' !== (string) get_post_meta( get_queried_object_id(), '_apprex_lp_index', true );
	}
	if ( is_post_type_archive( 'clp' ) ) {
		return true;
	}
	return is_singular( 'lptools_template' ) || is_post_type_archive( 'lptools_template' );
}

/* LPごとの「検索に表示する（インデックス）」スイッチ。 */
add_action( 'add_meta_boxes', function () {
	if ( post_type_exists( 'clp' ) ) {
		add_meta_box( 'apprex_lp_seo', '検索エンジン（SEO）', 'apprex_lp_seo_box', 'clp', 'side', 'high' );
	}
} );
function apprex_lp_seo_box( $post ) {
	wp_nonce_field( 'apprex_lp_seo', 'apprex_lp_seo_nonce' );
	$idx = (string) get_post_meta( $post->ID, '_apprex_lp_index', true );
	?>
	<p><label><input type="checkbox" name="apprex_lp_index" value="1" <?php checked( $idx, '1' ); ?>> <strong>このLPを検索に表示する（インデックス）</strong></label></p>
	<p class="description">広告LP（/clp/）は既定で検索に出しません（noindex）。<br>検索からの集客もしたいLPだけ、ここをONにしてください。</p>
	<?php
}
add_action( 'save_post_clp', function ( $post_id ) {
	if ( ! isset( $_POST['apprex_lp_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_lp_seo_nonce'] ) ), 'apprex_lp_seo' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_apprex_lp_index', isset( $_POST['apprex_lp_index'] ) ? '1' : '0' );
} );

/* -------------------------------------------------------------------------
 * 旧サイト（.html）URL → 新URL への 301 リダイレクト
 * 移行前の静的HTMLのリンク・SEO評価を新ページへ引き継ぐ。転送エラー/404を解消。
 * ---------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( '' === $path || '.html' !== substr( $path, -5 ) ) {
		return;
	}
	$slug = sanitize_title( basename( $path, '.html' ) );

	// 既知の旧→新マッピング。
	$map = array(
		'index'       => '/',
		'home'        => '/',
		'blog'        => '/blog/',
		'blog-detail' => '/blog/',
		'estimate'    => '/estimate/',
		'cases'       => '/cases/',
		'case'        => '/cases/',
		'faq'         => '/faq/',
		'features'    => '/features/',
		'feature'     => '/features/',
		'functions'   => '/functions/',
		'function'    => '/functions/',
		'hp-creation' => '/hp-creation/',
		'pricing'     => '/pricing/',
		'price'       => '/pricing/',
		'company'     => '/company/',
		'contact'     => '/contact/',
		'partner'     => '/partner/',
		'document'    => '/document/',
		'free-trial'  => '/free-trial/',
		'meeting'     => '/meeting/',
		'subsidy'     => '/pricing/',
	);
	if ( isset( $map[ $slug ] ) ) {
		wp_safe_redirect( home_url( $map[ $slug ] ), 301 );
		exit;
	}
	// 同名スラッグの固定ページ/投稿があればそこへ。
	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post ) {
		wp_safe_redirect( get_permalink( $page ), 301 );
		exit;
	}
	$posts = get_posts( array(
		'name'           => $slug,
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'publish',
		'numberposts'    => 1,
		'fields'         => 'ids',
	) );
	if ( ! empty( $posts ) ) {
		wp_safe_redirect( get_permalink( $posts[0] ), 301 );
		exit;
	}
	// 該当が無ければ何もしない（自然に404）。
}, 1 );

// wp_head が無効なLPでも確実に効くよう、HTTPヘッダーでも noindex を送る。
add_action( 'template_redirect', function () {
	if ( apprex_is_noindex_lp() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow', true );
	}
} );

/**
 * head にメタタグ・OGP・Twitter Card を出力。
 */
function apprex_head_meta() {
	$desc  = apprex_meta_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( null, null ) );

	// OG 画像（既定は専用の横長バナー 1200×630）。
	$img   = APPREX_URI . '/assets/images/apprex-og.jpg';
	$img_w = 1200;
	$img_h = 630;
	if ( is_singular() && has_post_thumbnail() ) {
		$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
		if ( $src ) {
			$img   = $src[0];
			$img_w = (int) $src[1];
			$img_h = (int) $src[2];
		}
	}

	$gsc     = get_option( 'apprex_gsc_verify', '' );
	$twitter = get_option( 'apprex_twitter', '' );
	?>
	<?php if ( $gsc ) : ?>
	<meta name="google-site-verification" content="<?php echo esc_attr( $gsc ); ?>">
	<?php endif; ?>
	<meta name="description" content="<?php echo esc_attr( $desc ); ?>">
	<meta property="og:type" content="<?php echo is_singular( 'post' ) ? 'article' : 'website'; ?>">
	<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>">
	<meta property="og:url" content="<?php echo esc_url( $url ); ?>">
	<meta property="og:site_name" content="<?php echo esc_attr( APPREX_BRAND ); ?>">
	<meta property="og:image" content="<?php echo esc_url( $img ); ?>">
	<meta property="og:image:secure_url" content="<?php echo esc_url( $img ); ?>">
	<meta property="og:image:width" content="<?php echo esc_attr( $img_w ); ?>">
	<meta property="og:image:height" content="<?php echo esc_attr( $img_h ); ?>">
	<meta property="og:image:alt" content="<?php echo esc_attr( APPREX_BRAND . '｜ノーコードアプリ開発・ホームページ制作' ); ?>">
	<meta property="og:locale" content="ja_JP">
	<?php if ( is_singular( 'post' ) ) : ?>
	<meta property="article:published_time" content="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
	<meta property="article:modified_time" content="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>">
	<?php endif; ?>
	<meta name="twitter:card" content="summary_large_image">
	<?php if ( $twitter ) : ?>
	<meta name="twitter:site" content="<?php echo esc_attr( '@' . ltrim( $twitter, '@' ) ); ?>">
	<?php endif; ?>
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
		'@type'        => array( 'Organization', 'ProfessionalService' ),
		'@id'          => home_url( '/#organization' ),
		'name'         => APPREX_BRAND,
		'areaServed'   => array( '@type' => 'Country', 'name' => '日本' ),
		'openingHoursSpecification' => array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ),
			'opens'     => '10:00',
			'closes'    => '18:00',
		),
		'legalName'    => '合同会社アイズ',
		'url'          => home_url( '/' ),
		'logo'         => array(
			'@type'  => 'ImageObject',
			'url'    => APPREX_URI . '/assets/images/apprex-logo.png',
			'width'  => 331,
			'height' => 106,
		),
		'image'        => APPREX_URI . '/assets/images/apprex-og.jpg',
		'sameAs'       => array( 'https://www.instagram.com/apprex1173/' ),
		'description'  => 'ノーコードアプリ開発プラットフォーム。アプリ制作代行・ホームページ制作も提供。',
		'email'        => function_exists( 'apprex_contact_email' ) ? apprex_contact_email() : '',
		'foundingDate' => '2018-10',
		'founder'      => array( '@type' => 'Person', 'name' => '吉田一平' ),
		'address'      => array(
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
		'name'            => APPREX_BRAND,
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
			'@type'            => 'Article',
			'headline'         => get_the_title( $post ),
			'description'      => apprex_meta_description(),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => array( '@type' => 'Organization', 'name' => 'APPREX編集部', '@id' => home_url( '/#organization' ) ),
			'publisher'        => array( '@id' => home_url( '/#organization' ) ),
			'mainEntityOfPage' => get_permalink( $post ),
			'image'            => has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'large' ) : ( APPREX_URI . '/assets/images/apprex-og.jpg' ),
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
