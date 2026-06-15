<?php
/**
 * Template Name: 代表メッセージ
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$rep  = ais_representative();
$phil = ais_philosophy();
?>

<?php echo ais_page_hero( 'Message', '代表メッセージ', $phil['tagline'] ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<div class="reveal">
				<?php echo ais_section_heading( $rep['heading'], $rep['lead'] ); // phpcs:ignore ?>
			</div>
			<div class="reveal mt-8 space-y-5" style="transition-delay:80ms">
				<?php foreach ( $rep['body'] as $para ) : ?>
					<p class="text-base leading-relaxed text-ink-700"><?php echo esc_html( $para ); ?></p>
				<?php endforeach; ?>
			</div>
			<div class="reveal mt-10 flex items-center justify-end gap-4 border-t border-slate-200 pt-6" style="transition-delay:160ms">
				<div class="text-right">
					<p class="text-sm text-ink-500"><?php echo esc_html( $rep['title'] ); ?></p>
					<p class="mt-1 text-xl font-bold tracking-wide text-ink-900"><?php echo esc_html( $rep['name'] ); ?></p>
				</div>
			</div>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
