<?php
/**
 * Front page (HOME).
 *
 * 新デザイン（2026リニューアル）と旧デザインを、外観 > カスタマイズ > トップページデザイン で切り替え。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( apprex_rn_enabled() ) :
	echo '<div class="rn">';
	foreach ( array( 'hero', 'marquee', 'questions', 'studio', 'ways', 'original', 'types', 'plans', 'campaign', 'cost', 'line', 'flow', 'cases', 'trust', 'lead', 'news', 'faq', 'final' ) as $apprex_part ) {
		get_template_part( 'template-parts/renewal/' . $apprex_part );
	}
	echo '</div>';
else :
	// 01–09 sections.
	get_template_part( 'template-parts/sections/hero' );
	get_template_part( 'template-parts/sections/app-marquee' );
	get_template_part( 'template-parts/sections/stats' );
	get_template_part( 'template-parts/sections/problem' );
	get_template_part( 'template-parts/sections/solution' );
	get_template_part( 'template-parts/sections/features' );
	get_template_part( 'template-parts/sections/functions' );
	get_template_part( 'template-parts/sections/cases' );
	get_template_part( 'template-parts/sections/instagram' );
	get_template_part( 'template-parts/sections/pricing' );
	get_template_part( 'template-parts/sections/hp-cta' );
	get_template_part( 'template-parts/sections/latest-posts' );
	get_template_part( 'template-parts/sections/faq' );

	// 10. Final CTA (shared partial).
	get_template_part( 'template-parts/final-cta' );
endif;

get_footer();
