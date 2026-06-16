<?php
/**
 * Template Name: 理念
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$phil = ais_philosophy();
$system = array(
	array( 'en' => 'PURPOSE', 'ja' => 'パーパス（存在意義）', 'text' => $phil['purpose'] ),
	array( 'en' => 'MISSION', 'ja' => 'ミッション（使命）', 'text' => $phil['mission'] ),
	array( 'en' => 'VISION', 'ja' => 'ビジョン（目指す姿）', 'text' => $phil['vision'] ),
);
?>

<?php echo ais_page_hero( 'Philosophy', $phil['tagline'], $phil['brand'] ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '理念体系', 'Our Philosophy', '「なぜ存在し（パーパス）」「何をし（ミッション）」「どこを目指すか（ビジョン）」。私たちの判断の軸です。', 'center' ); // phpcs:ignore ?>
		<div class="mx-auto mt-12 max-w-3xl space-y-4">
			<?php foreach ( $system as $i => $s ) : ?>
				<div class="reveal relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-7 shadow-card sm:p-8" style="transition-delay:<?php echo (int) ( $i * 90 ); ?>ms">
					<span class="pointer-events-none absolute -right-2 -top-3 text-6xl font-bold tracking-tight text-brand-50 sm:text-7xl"><?php echo esc_html( $s['en'] ); ?></span>
					<div class="relative">
						<p class="text-xs font-bold tracking-widest text-brand-600"><?php echo esc_html( $s['en'] ); ?> <span class="ml-1 text-ink-400"><?php echo esc_html( $s['ja'] ); ?></span></p>
						<p class="mt-3 text-xl font-bold leading-relaxed text-ink-900 sm:text-2xl"><?php echo esc_html( $s['text'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php echo ais_section_heading( $phil['belief']['title'], 'Our Belief' ); // phpcs:ignore ?>
			<div class="mt-6 space-y-5">
				<?php foreach ( $phil['belief']['body'] as $para ) : ?>
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
