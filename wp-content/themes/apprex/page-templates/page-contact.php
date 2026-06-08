<?php
/**
 * Template Name: お問い合わせページ (Contact)
 *
 * Assign to the "contact" page. Spec §12.
 *
 * Recommended: paste a Contact Form 7 / WPForms shortcode into the page editor.
 * A static placeholder form is shown when the editor content is empty.
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
				<span> / </span><?php the_title(); ?>
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'ご質問・オンライン相談のご予約はこちらから。担当者より折り返しご連絡します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php else : ?>
				<?php get_template_part( 'template-parts/placeholder-form', null, array( 'type' => 'contact' ) ); ?>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
