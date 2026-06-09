<?php
/**
 * Template Name: 資料請求ページ (Document Request)
 *
 * Assign to the "document" page. 資料請求＋LINE誘導。
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
			<p><?php esc_html_e( 'APPREX のサービス内容・料金・導入事例をまとめた資料を無料でダウンロードいただけます。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>
			<?php apprex_render_form( 'document' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
