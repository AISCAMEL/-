<?php
/** トップ: ブランドスライダー（BrandSlider.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$slides = array_values( array_filter( ais_services(), function ( $s ) {
	return ! empty( $s['brand'] );
} ) );
?>
<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( 'アイズのブランド', 'Brands', 'それぞれの専門性で、お客様のニーズにお応えします。', 'center' ); // phpcs:ignore ?>

		<div class="relative mx-auto mt-12 max-w-4xl" data-ais-slider role="group" aria-roledescription="カルーセル" aria-label="ブランド紹介">
			<div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-card">
				<div class="flex transition-transform duration-500 ease-out" data-ais-track>
					<?php foreach ( $slides as $s ) : ?>
						<article class="grid w-full flex-none items-center gap-6 p-8 sm:grid-cols-[auto_1fr] sm:p-10">
							<span class="grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
								<?php echo ais_icon( $s['icon'], 'h-8 w-8' ); // phpcs:ignore ?>
							</span>
							<div>
								<div class="flex flex-wrap items-center gap-2">
									<p class="text-sm font-bold tracking-wide text-brand-600"><?php echo esc_html( $s['brand'] ); ?></p>
									<?php if ( ! empty( $s['coming_soon'] ) ) : ?>
										<span class="rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold text-accent-700 ring-1 ring-inset ring-accent-200">準備中</span>
									<?php endif; ?>
								</div>
								<h3 class="mt-1 text-xl font-bold text-ink-900"><?php echo esc_html( $s['name'] ); ?></h3>
								<p class="mt-3 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $s['summary'] ); ?></p>
								<a href="<?php echo esc_url( ais_url( '/services/' . $s['slug'] ) ); ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
									詳しく見る
									<?php echo ais_icon( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore ?>
								</a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<button type="button" data-ais-prev aria-label="前のブランド" class="absolute left-0 top-1/2 grid h-10 w-10 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-slate-200 bg-white text-ink-700 shadow-card transition hover:text-brand-700">
				<?php echo ais_icon( 'arrow-right', 'h-5 w-5 rotate-180' ); // phpcs:ignore ?>
			</button>
			<button type="button" data-ais-next aria-label="次のブランド" class="absolute right-0 top-1/2 grid h-10 w-10 -translate-y-1/2 translate-x-1/2 place-items-center rounded-full border border-slate-200 bg-white text-ink-700 shadow-card transition hover:text-brand-700">
				<?php echo ais_icon( 'arrow-right', 'h-5 w-5' ); // phpcs:ignore ?>
			</button>

			<div class="mt-6 flex justify-center gap-2" data-ais-dots>
				<?php foreach ( $slides as $i => $s ) : ?>
					<button type="button" data-ais-dot aria-label="<?php echo esc_attr( $s['brand'] . ' を表示' ); ?>" class="h-2 rounded-full transition-all <?php echo 0 === $i ? 'w-6 bg-brand-600' : 'w-2 bg-slate-300 hover:bg-slate-400'; ?>"></button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</section>
