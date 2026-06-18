<?php
/**
 * Template Name: 無料体験申し込みページ (Free Trial)
 *
 * Assign to the "free-trial" page. Spec §12.
 *
 * Recommended: paste a Contact Form 7 / WPForms shortcode into the page editor.
 * When the editor content is empty a static placeholder form is shown so the
 * layout is complete during build.
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
			<p><?php esc_html_e( '30日間無料体験。クレジットカード登録不要で、すぐに始められます。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>
			<?php apprex_render_form( 'trial' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
