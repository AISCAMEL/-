<?php
/**
 * フロントページ（トップ）。Next.js 版 app/page.tsx の構成を移植。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

// 構造化データ（Organization + FAQ）
$ais_site = ais_site();
$ld = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type'         => 'Organization',
			'name'          => $ais_site['name'],
			'alternateName' => $ais_site['name_en'],
			'url'           => $ais_site['url'],
			'description'   => $ais_site['description'],
			'slogan'        => $ais_site['tagline'],
		),
		array(
			'@type'      => 'FAQPage',
			'mainEntity' => array_map( function ( $f ) {
				return array(
					'@type'          => 'Question',
					'name'           => $f['q'],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
				);
			}, ais_faqs() ),
		),
	),
);
echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';

get_template_part( 'template-parts/home/hero' );
get_template_part( 'template-parts/home/problems' );
get_template_part( 'template-parts/home/solutions' );
get_template_part( 'template-parts/home/business-map' );
get_template_part( 'template-parts/home/brand-slider' );
get_template_part( 'template-parts/home/strengths' );
get_template_part( 'template-parts/home/workflow' );
get_template_part( 'template-parts/home/case-studies' );
get_template_part( 'template-parts/home/message' );
get_template_part( 'template-parts/home/faq' );

echo ais_cta_banner(); // phpcs:ignore

get_footer();
