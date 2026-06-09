<?php
/**
 * Template Name: ミーティング予約ページ (Meeting)
 *
 * Assign to the "meeting" page. オンライン相談予約＋リマインダー。
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
			<p><?php esc_html_e( 'オンラインでの無料相談をご予約いただけます。ご希望日時をお選びください。前日・直前にリマインダーをお送りします。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>
			<?php apprex_render_form( 'meeting' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
