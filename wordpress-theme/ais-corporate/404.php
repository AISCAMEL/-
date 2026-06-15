<?php
/** 404 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<?php echo ais_page_hero( '404', 'ページが見つかりません', 'お探しのページは移動または削除された可能性があります。' ); // phpcs:ignore ?>
<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl text-center">
			<p class="text-ink-600">URL をご確認のうえ、トップページからお探しください。</p>
			<div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
				<?php echo ais_button( '/', 'トップへ戻る', 'primary', 'lg' ); // phpcs:ignore ?>
				<?php echo ais_button( '/services', 'サービスを見る', 'secondary', 'lg' ); // phpcs:ignore ?>
			</div>
		</div>
	</div>
</section>
<?php get_footer(); ?>
