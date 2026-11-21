<?php
/** フロント描画：[ais_home] / [ais_business_cta] */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** アイコン利用ヘルパー（spriteはテーマのヘッダーパーツが出力） */
function ais_icon( $id, $w = 18 ) {
	return '<svg class="ico" style="width:' . (int) $w . 'px;height:' . (int) $w . 'px" aria-hidden="true"><use href="#' . esc_attr( $id ) . '"></use></svg>';
}

/** 事業データを取得（CPT優先・無ければ既定） */
function ais_hub_get_businesses() {
	$posts = get_posts( array(
		'post_type'      => 'ais_business',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
		'post_status'    => 'publish',
	) );
	if ( empty( $posts ) ) {
		$out = array();
		foreach ( ais_hub_default_businesses() as $b ) {
			$b['url']   = home_url( '/business/' . $b['slug'] . '/' );
			$b['image'] = ais_hub_illustration_url( $b['slug'] );
			$out[]      = $b;
		}
		return $out;
	}
	$out = array();
	foreach ( $posts as $p ) {
		$illo  = get_post_meta( $p->ID, 'ais_illo', true );
		$illo  = $illo ? $illo : $p->post_name;
		$thumb = get_the_post_thumbnail_url( $p->ID, 'large' );
		$out[] = array(
			'slug'   => $p->post_name,
			'en'     => get_post_meta( $p->ID, 'ais_en', true ) ?: get_the_title( $p ),
			'ja'     => get_post_meta( $p->ID, 'ais_ja', true ),
			'cat'    => get_post_meta( $p->ID, 'ais_cat', true ),
			'desc'   => get_post_meta( $p->ID, 'ais_desc', true ),
			'layout' => get_post_meta( $p->ID, 'ais_layout', true ) ?: 'grid',
			'status' => get_post_meta( $p->ID, 'ais_status', true ),
			'cta'    => get_post_meta( $p->ID, 'ais_cta', true ),
			'url'    => get_permalink( $p ),
			'image'  => $thumb ? $thumb : ais_hub_illustration_url( $illo ),
		);
	}
	return $out;
}

function ais_hub_card_html( $b, $wide = false ) {
	$cls   = 'ais-card reveal' . ( $wide ? ' alt' : '' );
	$brandsize = ( mb_strlen( $b['en'] ) > 10 ) ? ' style="font-size:18px"' : '';
	$h  = '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $b['url'] ) . '">';
	$h .= '<div class="bg" style="background-image:url(' . esc_url( $b['image'] ) . ')"></div>';
	$h .= '<div class="c"><div class="brand"' . $brandsize . '>' . esc_html( $b['en'] );
	if ( ! empty( $b['ja'] ) && ! $wide ) { $h .= '<small>' . esc_html( $b['ja'] ) . '</small>'; }
	$h .= '</div>';
	if ( $wide && ! empty( $b['ja'] ) ) { $h .= '<div class="desc" style="margin-top:2px">' . esc_html( $b['ja'] ) . '</div>'; }
	if ( ! empty( $b['cat'] ) ) { $h .= '<div class="cat"' . ( $wide ? ' style="margin-top:12px"' : '' ) . '>' . esc_html( $b['cat'] ) . '</div>'; }
	if ( ! empty( $b['desc'] ) ) { $h .= '<div class="desc">' . esc_html( $b['desc'] ) . '</div>'; }
	$h .= '<span class="arw">' . ais_icon( 'i-arrow', 14 ) . '</span></div></a>';
	return $h;
}

/** [ais_home] ホームページ本文 */
function ais_hub_render_home() {
	$biz   = ais_hub_get_businesses();
	$grid  = array();
	$wide  = array();
	foreach ( $biz as $b ) {
		if ( 'hidden' === $b['layout'] ) { continue; }
		if ( 'wide' === $b['layout'] ) { $wide[] = $b; } else { $grid[] = $b; }
	}
	$o = '';

	/* HERO */
	$title_lines = array_filter( array_map( 'trim', explode( "\n", (string) ais_hub_get( 'hero_title' ) ) ) );
	$hero_style  = '';
	$hero_img    = ais_hub_get( 'hero_image' );
	if ( $hero_img ) { $hero_style = ' style="background-image:url(' . esc_url( $hero_img ) . ')"'; }
	$o .= '<section class="ais-hero"><div class="media"' . $hero_style . '></div><div class="inner">';
	$o .= '<h1 class="reveal">' . implode( '<br>', array_map( 'esc_html', $title_lines ) ) . '</h1>';
	$o .= '<p class="lead reveal">' . esc_html( ais_hub_get( 'hero_lead' ) ) . '</p>';
	$o .= '<div class="cta reveal"><a class="ais-btn ais-btn-teal" href="' . esc_url( ais_hub_get( 'hero_cta1_url' ) ) . '">' . esc_html( ais_hub_get( 'hero_cta1_label' ) ) . ais_icon( 'i-arrow' ) . '</a>';
	$o .= '<a class="ais-btn ais-btn-ghost" href="' . esc_url( ais_hub_get( 'hero_cta2_url' ) ) . '">' . ais_icon( 'i-play' ) . esc_html( ais_hub_get( 'hero_cta2_label' ) ) . '</a></div>';
	$o .= '<div class="laurel">次世代モビリティ<br>プラットフォーム<b>AIS Premium</b></div>';
	$o .= '</div></section>';

	/* STATS */
	if ( ais_hub_get( 'show_stats' ) ) {
		$stats = ais_hub_get( 'stats' );
		if ( ! is_array( $stats ) || ! $stats ) { $stats = ais_hub_default_stats(); }
		$o .= '<section class="ais-stats"><div class="container"><div class="grid">';
		foreach ( $stats as $st ) {
			$o .= '<div class="ais-stat">' . ais_icon( isset( $st['icon'] ) ? $st['icon'] : 'i-chart', 30 );
			$o .= '<div><div class="l">' . esc_html( $st['label'] ) . '</div><div class="v">' . esc_html( $st['value'] ) . '</div></div></div>';
		}
		$o .= '</div></div>';
		$note = ais_hub_get( 'stats_note' );
		if ( $note ) { $o .= '<div class="ais-note">' . esc_html( $note ) . '</div>'; }
		$o .= '</section>';
	}

	/* SERVICES */
	$o .= '<section class="ais-section ais-services"><div class="container">';
	$o .= '<div class="ais-sechead reveal"><div><p class="ais-eyebrow">Our Service</p><h2>主要事業・プラットフォーム</h2></div>';
	$o .= '<a class="ais-more" href="' . esc_url( home_url( '/business/' ) ) . '">すべての事業を見る' . ais_icon( 'i-arrow', 16 ) . '</a></div>';
	$o .= '<div class="ais-biz-grid">';
	foreach ( $grid as $b ) { $o .= ais_hub_card_html( $b, false ); }
	$o .= '</div>';
	if ( $wide ) {
		$o .= '<div class="ais-biz-row2">';
		foreach ( $wide as $b ) { $o .= ais_hub_card_html( $b, true ); }
		$o .= '</div>';
	}
	$o .= '</div></section>';

	/* PARTNER */
	$o .= '<section class="ais-section" style="padding-top:0"><div class="container"><div class="ais-partner reveal">';
	$o .= '<div class="ph"></div><div><h3>販売店・事業パートナー募集</h3><p>AISのプラットフォームで、あなたのビジネスを次のステージへ。</p>';
	$o .= '<div class="feats"><div class="feat">' . ais_icon( 'i-box', 26 ) . '<div><b>在庫無制限</b>全国ネットワーク</div></div>';
	$o .= '<div class="feat">' . ais_icon( 'i-shield', 26 ) . '<div><b>リース・信販</b>自社審査サポート</div></div>';
	$o .= '<div class="feat">' . ais_icon( 'i-pin', 26 ) . '<div><b>GPS・AI機能</b>システム提供</div></div></div></div>';
	$o .= '<a class="ais-btn ais-btn-teal" href="' . esc_url( home_url( '/partner/' ) ) . '">パートナー制度の詳細' . ais_icon( 'i-arrow' ) . '</a>';
	$o .= '</div></div></section>';

	/* NEWS */
	if ( ais_hub_get( 'show_news' ) ) {
		$o .= '<section class="ais-section ais-news"><div class="container">';
		$o .= '<div class="ais-sechead reveal"><div><p class="ais-eyebrow">News &amp; Topics</p><h2>ニュース・お知らせ</h2></div>';
		$o .= '<a class="ais-more" href="' . esc_url( home_url( '/news/' ) ) . '">一覧を見る' . ais_icon( 'i-arrow', 16 ) . '</a></div>';
		$o .= '<div class="ais-tabs reveal" style="margin-bottom:26px"><button class="on" data-cat="">すべて</button><button data-cat="お知らせ">お知らせ</button><button data-cat="事業情報">事業情報</button><button data-cat="業界ニュース">業界ニュース</button><button data-cat="コラム">コラム</button></div>';
		$o .= '<div class="ais-news-grid">';
		$news_posts = get_posts( array( 'posts_per_page' => 4, 'post_status' => 'publish' ) );
		$is_sample  = false;
		if ( $news_posts ) {
			foreach ( $news_posts as $p ) {
				$cats  = get_the_category( $p->ID );
				$cat   = $cats ? $cats[0]->name : 'お知らせ';
				$thumb = get_the_post_thumbnail_url( $p->ID, 'medium' );
				$thumb = $thumb ? $thumb : ais_hub_illustration_url( 'ai-digital' );
				$o .= '<a class="ais-ncard reveal" data-cat="' . esc_attr( $cat ) . '" href="' . esc_url( get_permalink( $p ) ) . '">';
				$o .= '<div class="thumb" style="background-image:url(' . esc_url( $thumb ) . ')"></div><div class="b"><div class="meta"><span class="tag">' . esc_html( $cat ) . '</span><span class="date">' . esc_html( get_the_date( 'Y.m.d', $p ) ) . '</span></div>';
				$o .= '<h4>' . esc_html( get_the_title( $p ) ) . '</h4><p>' . esc_html( wp_trim_words( wp_strip_all_tags( $p->post_content ), 40 ) ) . '</p></div></a>';
			}
		} else {
			$is_sample = true;
			foreach ( ais_hub_sample_news() as $n ) {
				$o .= '<a class="ais-ncard reveal" data-cat="' . esc_attr( $n['cat'] ) . '" href="' . esc_url( home_url( '/news/' ) ) . '">';
				$o .= '<div class="thumb" style="background-image:url(' . esc_url( ais_hub_illustration_url( $n['illo'] ) ) . ')"></div><div class="b"><div class="meta"><span class="tag">' . esc_html( $n['cat'] ) . '</span><span class="date">' . esc_html( $n['date'] ) . '</span></div>';
				$o .= '<h4>' . esc_html( $n['title'] ) . '</h4><p>' . esc_html( $n['excerpt'] ) . '</p></div></a>';
			}
		}
		$o .= '</div>';
		if ( $is_sample ) {
			$o .= '<div class="ais-note" style="color:var(--muted);text-align:left;padding-left:0">※掲載ニュースはサンプルです（管理画面で記事を追加／事実確認・承認後に公開）</div>';
		}
		$o .= '</div></section>';
	}

	/* PHILOSOPHY */
	$quote_lines = array_filter( array_map( 'trim', explode( "\n", (string) ais_hub_get( 'philo_quote' ) ) ) );
	$o .= '<section class="ais-philo"><div class="media"></div><div class="inner"><div class="reveal">';
	$o .= '<p class="quote">' . implode( '<br>', array_map( 'esc_html', $quote_lines ) ) . '</p>';
	$o .= '<p class="lead">' . esc_html( ais_hub_get( 'philo_lead' ) ) . '</p></div>';
	$o .= '<div class="ais-values reveal">';
	foreach ( ais_hub_default_values() as $v ) {
		$o .= '<div class="ais-value">' . ais_icon( $v['icon'], 30 ) . '<b>' . esc_html( $v['title'] ) . '</b><span>' . esc_html( $v['desc'] ) . '</span></div>';
	}
	$o .= '</div></div></section>';

	return $o;
}
add_shortcode( 'ais_home', 'ais_hub_render_home' );

/** [ais_business_cta] 事業ページ下部のCTA */
add_shortcode( 'ais_business_cta', function () {
	$cta   = get_post_meta( get_the_ID(), 'ais_cta', true );
	$label = $cta ? $cta : 'お問い合わせ';
	$h  = '<div class="ais-biz-cta"><h3>' . esc_html( get_the_title() ) . ' のご相談</h3>';
	$h .= '<p>お気軽にお問い合わせください。担当者が丁寧にご案内します。</p>';
	$h .= '<a class="ais-btn ais-btn-teal" href="' . esc_url( home_url( '/contact/' ) ) . '">' . esc_html( $label ) . '</a></div>';
	return $h;
} );
