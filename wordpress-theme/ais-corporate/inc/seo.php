<?php
/**
 * SEO メタ情報（Next.js 版 metadata を移植）。
 * - <title>：document_title_parts で各ページの文言を一致
 * - meta description / canonical / OGP / Twitter カード を wp_head に出力
 * プラグイン不要。SEO プラグイン導入時は重複しないよう調整してください。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** 固定ページ slug → タイトル（サイト名は自動付与） */
function ais_seo_page_titles() {
	return array(
		'about'      => 'アイズについて｜会社概要',
		'message'    => '代表メッセージ',
		'philosophy' => '理念｜Always Innovation Solutions',
		'brands'     => 'ブランド一覧',
		'franchise'  => 'FC加盟募集｜カーメル・BUYMO フランチャイズ',
		'recruit'    => '採用情報｜AISで働く',
		'faq'        => 'よくある質問（FAQ）',
		'contact'    => 'お問い合わせ・無料相談',
		'privacy'    => 'プライバシーポリシー',
	);
}

/** 固定ページ slug → メタ説明 */
function ais_seo_page_descriptions() {
	return array(
		'about'      => '合同会社アイズ（AIS LLC）の会社概要。AIS = Always Innovation Solutions。自動車業界の知見とITを掛け合わせ、戦略から実行まで顧客の成長に伴走します。',
		'message'    => '合同会社アイズ 代表メッセージ。変化を成長の機会に変え、構想から実行・成果まで最後まで伴走する——私たちの姿勢をお伝えします。',
		'philosophy' => '合同会社アイズの理念。革新・品質・信頼性を軸に、自動車業界の変革を成長の機会へ。常に新しい解決策で、お客様の事業に最後まで寄り添います。',
		'brands'     => '合同会社アイズが展開するブランド一覧。自動車販売「カーメル」、買取「BUYMO」、オンライン車販売「CARSHICO」、車両セキュリティ「天護 TENGO」、ノーコードアプリ開発「APPREX」、サブスクWeb制作「WEB crews」、AI電話応対「AIオペレーター24」。',
		'franchise'  => '自動車販売「カーメル」・買取「BUYMO」のフランチャイズ加盟募集。未経験でも開業から運営まで本部がサポート。自動車事業への新規参入・多角化をお考えの方へ、合同会社アイズが伴走します。',
		'recruit'    => '合同会社アイズの採用情報。車買取の業務委託パートナー（BUYMO）と、アプリ・Web制作のクリエイター（APPREX／WEB crews）を募集しています。正社員・業務委託、いわきから多角的に挑戦できます。当社との直接契約です。',
		'faq'        => '合同会社アイズへのお問い合わせ前によくいただくご質問をまとめました。費用、相談範囲、対応業種、進め方などについてお答えします。',
		'contact'    => '合同会社アイズへのお問い合わせ。自動車販売（カーメル）・買取（BUYMO）・オンライン車販売（CARSHICO）・車両セキュリティ（天護）・レッカー、IT事業（APPREX）・WEB制作（WEB crews）・FC事業のご相談を承ります。初回相談・お見積りは無料です。',
		'privacy'    => '合同会社アイズのプライバシーポリシー（個人情報の取り扱い）について。',
	);
}

/** 投稿タイプアーカイブ → タイトル・説明 */
function ais_seo_archive_meta() {
	return array(
		'ais_service' => array(
			'title' => '事業紹介｜自動車・IT/WEB・FC',
			'desc'  => '合同会社アイズの事業一覧。自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカー事業を主力に、IT事業「APPREX」、サブスクWeb制作「WEB crews」、FC事業を展開しています。',
		),
		'ais_work' => array(
			'title' => '実績・事例',
			'desc'  => '合同会社アイズの実績・事例。自動車（カーメル／BUYMO／CARSHICO）やIT事業（APPREX）などの取り組みをご紹介します。',
		),
		'ais_news' => array(
			'title' => 'お知らせ・コラム',
			'desc'  => '合同会社アイズからのお知らせと、クルマ（販売・買取・オンライン購入）やアプリ・Web制作に関するコラムをお届けします。',
		),
	);
}

/** 現在のコンテキストの SEO 情報を解決して返す */
function ais_seo_context() {
	$site = ais_site();
	$ctx  = array(
		'title'     => '',   // 完全なタイトル（指定時は site 名を付けない）
		'desc'      => $site['description'],
		'canonical' => '',
		'og_type'   => 'website',
	);

	if ( is_front_page() ) {
		$ctx['title']     = $site['name'] . '｜自動車（販売・買取・オンライン販売・セキュリティ・レッカー）／IT・WEB・FC事業';
		$ctx['desc']      = $site['description'];
		$ctx['canonical'] = home_url( '/' );
		return $ctx;
	}

	if ( is_singular( 'ais_service' ) ) {
		$obj = get_queried_object();
		$s   = $obj ? ais_get_service( $obj->post_name ) : null;
		if ( $s ) {
			$ctx['title']   = $s['seo']['title'];
			$ctx['desc']    = $s['seo']['description'];
			$ctx['og_type'] = 'article';
		}
		$ctx['canonical'] = get_permalink();
		return $ctx;
	}

	if ( is_singular( 'ais_work' ) ) {
		$obj = get_queried_object();
		$w   = $obj ? ais_get_work( $obj->post_name ) : null;
		if ( $w ) {
			$ctx['title'] = $w['title'] . '｜実績｜' . $site['name'];
			$ctx['desc']  = $w['summary'];
		}
		$ctx['og_type']   = 'article';
		$ctx['canonical'] = get_permalink();
		return $ctx;
	}

	if ( is_singular( 'ais_news' ) ) {
		$obj = get_queried_object();
		$n   = $obj ? ais_get_news( $obj->post_name ) : null;
		if ( $n ) {
			$ctx['title'] = $n['title'] . '｜' . $site['name'];
			$ctx['desc']  = $n['excerpt'];
		}
		$ctx['og_type']   = 'article';
		$ctx['canonical'] = get_permalink();
		return $ctx;
	}

	if ( is_post_type_archive() ) {
		$pt   = get_query_var( 'post_type' );
		$pt   = is_array( $pt ) ? reset( $pt ) : $pt;
		$meta = ais_seo_archive_meta();
		if ( isset( $meta[ $pt ] ) ) {
			$ctx['title'] = $meta[ $pt ]['title'] . '｜' . $site['name'];
			$ctx['desc']  = $meta[ $pt ]['desc'];
		}
		$ctx['canonical'] = get_post_type_archive_link( $pt );
		return $ctx;
	}

	if ( is_page() ) {
		$slug  = get_post_field( 'post_name', get_queried_object_id() );
		$descs = ais_seo_page_descriptions();
		$titles = ais_seo_page_titles();
		if ( isset( $titles[ $slug ] ) ) {
			$ctx['title'] = $titles[ $slug ] . '｜' . $site['name'];
		}
		if ( isset( $descs[ $slug ] ) ) {
			$ctx['desc'] = $descs[ $slug ];
		}
		$ctx['canonical'] = get_permalink();
		return $ctx;
	}

	return $ctx;
}

/** <title> を Next.js 版に合わせて上書き */
function ais_document_title_parts( $parts ) {
	$ctx = ais_seo_context();
	if ( ! empty( $ctx['title'] ) ) {
		$parts['title']   = $ctx['title'];
		$parts['tagline'] = '';
		$parts['site']    = '';
	}
	return $parts;
}
add_filter( 'document_title_parts', 'ais_document_title_parts', 20 );

/** meta description / canonical / OGP / Twitter を出力 */
function ais_seo_head() {
	$site = ais_site();
	$ctx  = ais_seo_context();
	$desc = trim( (string) $ctx['desc'] );
	$title = wp_get_document_title();
	$canonical = $ctx['canonical'];

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}

	// canonical（singular は WP コアの rel_canonical が出力するため、ここでは front/archive のみ）
	if ( $canonical && ( is_front_page() || is_post_type_archive() ) ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}

	// 個別ページの canonical を取りこぼさない場合の保険（コア未出力時）
	if ( $canonical && ! is_front_page() && ! is_post_type_archive() && ! is_singular() ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}

	// OGP
	echo '<meta property="og:type" content="' . esc_attr( $ctx['og_type'] ) . '">' . "\n";
	echo '<meta property="og:locale" content="ja_JP">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $site['name'] ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	if ( $canonical ) {
		echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
	}

	// 画像（アイキャッチがあれば使用）
	$img = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$img = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
	}

	// Twitter カード
	echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
	}

	// トップのみキーワード（Next.js 版を踏襲）
	if ( is_front_page() ) {
		$keywords = array(
			'カーメル', 'BUYMO', 'CARSHICO', '天護', 'TENGO', 'APPREX', 'WEB crews',
			'AIオペレーター24', '国産車 販売', '車 買取', '農機具 買取', 'アルミ 買取',
			'新車 オンライン 購入', 'GPS 遠隔停止', '車両セキュリティ', 'レッカー カーレスキュー 福島',
			'ノーコード アプリ開発', 'サブスク ホームページ制作', 'いわき市 自動車',
		);
		echo '<meta name="keywords" content="' . esc_attr( implode( ',', $keywords ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'ais_seo_head', 1 );
