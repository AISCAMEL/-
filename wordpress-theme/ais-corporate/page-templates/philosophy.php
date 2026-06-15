<?php
/**
 * Template Name: 理念
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$phil = ais_philosophy();
?>

<?php echo ais_page_hero( 'Philosophy', $phil['tagline'], $phil['brand'] ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php echo ais_section_heading( $phil['vision']['title'], 'Vision' ); // phpcs:ignore ?>
			<div class="mt-6 space-y-5">
				<?php foreach ( $phil['vision']['body'] as $para ) : ?>
					<p class="text-base leading-relaxed text-ink-700"><?php echo esc_html( $para ); ?></p>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-ink-900 text-white">
	<div class="container">
		<?php echo ais_section_heading( '革新・品質・信頼性', 'Values', '私たちの価値観は、日々の仕事のすべてに通底しています。', 'center', true ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 md:grid-cols-3">
			<?php foreach ( $phil['values'] as $i => $v ) : ?>
				<div class="reveal rounded-2xl border border-white/10 bg-white/[0.04] p-7" style="transition-delay:<?php echo (int) ( $i * 80 ); ?>ms">
					<span class="text-3xl font-bold text-accent-400"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="mt-2 text-lg font-bold text-white"><?php echo esc_html( $v['title'] ); ?></h3>
					<p class="mt-3 text-sm leading-relaxed text-slate-300"><?php echo esc_html( $v['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
