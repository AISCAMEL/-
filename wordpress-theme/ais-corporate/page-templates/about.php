<?php
/**
 * Template Name: アイズについて（会社概要）
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$phil = ais_philosophy();
?>

<?php echo ais_page_hero( 'About', 'アイズについて', 'AIS = Always Innovation Solutions。常に新しいことを取り入れ、課題を解決しながら、最後までお客様に寄り添う会社です。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '私たちが大切にする3つの価値観', 'Our Values', 'すべての仕事の判断基準となる、変わらない軸です。', 'center' ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 md:grid-cols-3">
			<?php foreach ( $phil['values'] as $v ) : ?>
				<div class="reveal rounded-2xl border border-slate-200 bg-slate-50 p-7">
					<h3 class="text-lg font-bold text-brand-700"><?php echo esc_html( $v['title'] ); ?></h3>
					<p class="mt-3 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $v['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '会社概要', 'Company' ); // phpcs:ignore ?>
		<dl class="mt-10 overflow-hidden rounded-2xl border border-slate-200 bg-white">
			<?php foreach ( ais_company_profile() as $i => $row ) : ?>
				<div class="grid grid-cols-1 gap-1 px-6 py-5 sm:grid-cols-4 sm:gap-4 <?php echo 0 !== $i ? 'border-t border-slate-100' : ''; ?>">
					<dt class="text-sm font-bold text-ink-900"><?php echo esc_html( $row['label'] ); ?></dt>
					<dd class="text-sm leading-relaxed text-ink-600 sm:col-span-3"><?php echo esc_html( $row['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
