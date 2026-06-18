<?php
/**
 * Template Name: よくある質問ページ (FAQ)
 *
 * Assign to the "faq" page. Spec §7/§9 — accordion.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'APPREX に関するよくあるご質問をまとめました。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php get_template_part( 'template-parts/faq-list' ); ?>

			<?php if ( trim( get_the_content() ) ) : ?>
				<div class="content-prose mt-32"><?php the_content(); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
