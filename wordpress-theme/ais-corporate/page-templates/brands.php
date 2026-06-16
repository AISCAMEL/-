<?php
/**
 * Template Name: ブランド一覧
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$branded = array_values( array_filter( ais_services(), function ( $s ) {
	return ! empty( $s['brand'] );
} ) );
?>

<?php echo ais_page_hero( 'Brands', 'ブランド一覧', '合同会社アイズは、事業ごとにブランドを展開しています。それぞれの専門性で、お客様のニーズにお応えします。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $branded as $s ) : ?>
				<a href="<?php echo esc_url( ais_url( '/services/' . $s['slug'] ) ); ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-card transition-all hover:-translate-y-1 hover:border-brand-200 hover:shadow-card-hover">
					<span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
						<?php echo ais_icon( $s['icon'], 'h-6 w-6' ); // phpcs:ignore ?>
					</span>
					<p class="mt-5 text-lg font-bold tracking-wide text-ink-900"><?php echo esc_html( $s['brand'] ); ?></p>
					<p class="mt-1 text-sm font-semibold text-brand-600"><?php echo esc_html( $s['name'] ); ?></p>
					<p class="mt-3 flex-1 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $s['tagline'] ); ?></p>
					<span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">
						詳しく見る
						<?php echo ais_icon( 'arrow-right', 'h-4 w-4 transition-transform group-hover:translate-x-1' ); // phpcs:ignore ?>
					</span>
				</a>
			<?php endforeach; ?>
		</div>

		<p class="mt-8 text-sm text-ink-500">※ このほか、レッカー事業（カーレスキュー）、FC事業（カーメル／BUYMO の加盟募集）も展開しています。詳しくは <a href="<?php echo esc_url( ais_url( '/services' ) ); ?>" class="font-semibold text-brand-700 hover:underline">事業紹介</a> をご覧ください。</p>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
