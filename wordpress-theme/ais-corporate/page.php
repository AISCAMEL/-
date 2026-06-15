<?php
/** 汎用固定ページ（専用テンプレート未割り当て時のフォールバック） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) :
	the_post();
	?>
	<?php echo ais_page_hero( 'Page', get_the_title() ); // phpcs:ignore ?>
	<section class="py-16 sm:py-24 bg-white text-ink-800">
		<div class="container">
			<div class="prose mx-auto max-w-3xl text-ink-700">
				<?php the_content(); ?>
			</div>
		</div>
	</section>
	<?php
endwhile;
get_footer();
