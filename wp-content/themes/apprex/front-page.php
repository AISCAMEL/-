<?php
/**
 * Front page (HOME) — spec §5/§6 section composition 00–11.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// 01–09 sections.
get_template_part( 'template-parts/sections/hero' );
get_template_part( 'template-parts/sections/stats' );
get_template_part( 'template-parts/sections/problem' );
get_template_part( 'template-parts/sections/solution' );
get_template_part( 'template-parts/sections/features' );
get_template_part( 'template-parts/sections/functions' );
get_template_part( 'template-parts/sections/cases' );
get_template_part( 'template-parts/sections/pricing' );
get_template_part( 'template-parts/sections/faq' );

// 10. Final CTA (shared partial).
get_template_part( 'template-parts/final-cta' );

get_footer();
