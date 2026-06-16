<?php
/** 実績 一覧（works/page.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$works = ais_works();
$has_placeholder = (bool) array_filter( $works, function ( $w ) { return ! empty( $w['is_placeholder'] ); } );
?>

<?php echo ais_page_hero( 'Works', '実績・事例', 'お客様の事業フェーズに合わせた支援の取り組みをご紹介します。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php if ( $has_placeholder ) : ?>
			<p class="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">※ 現在掲載中の事例は構成確認用のサンプル（ダミー）です。実績が確定し次第、内容を差し替えてください。</p>
		<?php endif; ?>
		<div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
			<?php foreach ( $works as $w ) : ?>
				<a href="<?php echo esc_url( ais_url( '/works/' . $w['slug'] ) ); ?>" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card transition-all hover:-translate-y-1 hover:shadow-card-hover">
					<div class="relative aspect-[16/9] bg-gradient-to-br from-brand-600 to-ink-900">
						<span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-brand-700"><?php echo esc_html( $w['category_label'] ); ?></span>
						<?php if ( ! empty( $w['is_placeholder'] ) ) : ?>
							<span class="absolute right-4 top-4 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-bold text-amber-950">サンプル</span>
						<?php endif; ?>
					</div>
					<div class="flex flex-1 flex-col p-6">
						<h2 class="text-base font-bold text-ink-900"><?php echo esc_html( $w['title'] ); ?></h2>
						<p class="mt-2 flex-1 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $w['summary'] ); ?></p>
						<p class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-accent-600">
							<?php echo ais_icon( 'spark', 'h-4 w-4' ); // phpcs:ignore ?>
							<?php echo esc_html( $w['result'] ); ?>
						</p>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
