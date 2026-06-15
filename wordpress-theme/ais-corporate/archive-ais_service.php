<?php
/** 事業紹介 一覧（services/page.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>

<?php echo ais_page_hero( 'Business', '事業紹介', '自動車事業（販売・買取・オンライン販売・セキュリティ・レッカー）を主力に、IT・WEB事業、FC事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="space-y-14">
			<?php foreach ( ais_service_groups() as $group ) : ?>
				<div>
					<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
						<h2 class="text-xl font-bold text-ink-900"><?php echo esc_html( $group['label'] ); ?></h2>
						<?php if ( ! empty( $group['is_primary'] ) ) : ?>
							<span class="rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-bold text-white">主力事業</span>
						<?php endif; ?>
					</div>
					<p class="mt-1 text-sm text-ink-500"><?php echo esc_html( $group['description'] ); ?></p>

					<div class="mt-6 grid gap-5 md:grid-cols-2">
						<?php foreach ( ais_services_by_group( $group['id'] ) as $s ) : ?>
							<div class="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:grid-cols-12 sm:p-7">
								<div class="sm:col-span-5">
									<span class="grid h-12 w-12 place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
										<?php echo ais_icon( $s['icon'], 'h-6 w-6' ); // phpcs:ignore ?>
									</span>
									<?php if ( ! empty( $s['brand'] ) ) : ?>
										<p class="mt-3 text-xs font-semibold tracking-wide text-brand-600"><?php echo esc_html( $s['brand'] ); ?></p>
									<?php endif; ?>
									<div class="mt-0.5 flex flex-wrap items-center gap-2">
										<h3 class="text-lg font-bold text-ink-900"><?php echo esc_html( $s['name'] ); ?></h3>
										<?php if ( ! empty( $s['coming_soon'] ) ) : ?>
											<span class="rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-bold text-accent-700 ring-1 ring-inset ring-accent-200">準備中</span>
										<?php endif; ?>
									</div>
									<p class="mt-1 text-sm font-medium text-ink-600"><?php echo esc_html( $s['tagline'] ); ?></p>
									<a href="<?php echo esc_url( ais_url( '/services/' . $s['slug'] ) ); ?>" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
										詳しく見る
										<?php echo ais_icon( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore ?>
									</a>
								</div>
								<div class="sm:col-span-7">
									<ul class="space-y-2">
										<?php foreach ( $s['highlights'] as $h ) : ?>
											<li class="flex items-start gap-2 text-sm text-ink-700">
												<?php echo ais_icon( 'check', 'mt-0.5 h-4 w-4 flex-none text-accent-600' ); // phpcs:ignore ?>
												<?php echo esc_html( $h ); ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
