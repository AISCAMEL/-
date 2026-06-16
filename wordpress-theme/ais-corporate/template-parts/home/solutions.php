<?php
/** トップ: 事業一覧（Solutions.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section id="services" class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( 'アイズの事業', 'Business', '自動車事業（販売・買取・オンライン販売・セキュリティ・レッカー）を主力に、IT・WEB事業、FC事業を展開。クルマのことからデジタルまで、ワンストップでお応えします。', 'center' ); // phpcs:ignore ?>

		<div class="mt-12 space-y-12">
			<?php foreach ( ais_service_groups() as $group ) : ?>
				<div>
					<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
						<h3 class="text-lg font-bold text-ink-900"><?php echo esc_html( $group['label'] ); ?></h3>
						<?php if ( ! empty( $group['is_primary'] ) ) : ?>
							<span class="rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-bold text-white">主力事業</span>
						<?php endif; ?>
						<p class="text-sm text-ink-500"><?php echo esc_html( $group['description'] ); ?></p>
					</div>

					<div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
						<?php foreach ( ais_services_by_group( $group['id'] ) as $s ) : ?>
							<a href="<?php echo esc_url( ais_url( '/services/' . $s['slug'] ) ); ?>" class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-card transition-all hover:-translate-y-1 hover:border-brand-200 hover:shadow-card-hover">
								<div class="flex items-center gap-3">
									<span class="grid h-11 w-11 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
										<?php echo ais_icon( $s['icon'], 'h-6 w-6' ); // phpcs:ignore ?>
									</span>
									<div>
										<?php if ( ! empty( $s['brand'] ) ) : ?>
											<p class="text-xs font-semibold tracking-wide text-brand-600"><?php echo esc_html( $s['brand'] ); ?></p>
										<?php endif; ?>
										<h4 class="font-bold text-ink-900"><?php echo esc_html( $s['name'] ); ?></h4>
									</div>
								</div>
								<p class="mt-3 text-sm font-medium text-ink-700"><?php echo esc_html( $s['tagline'] ); ?></p>
								<p class="mt-2 flex-1 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $s['summary'] ); ?></p>
								<span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700">
									詳しく見る
									<?php echo ais_icon( 'arrow-right', 'h-4 w-4 transition-transform group-hover:translate-x-1' ); // phpcs:ignore ?>
								</span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
