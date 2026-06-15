<?php
/** トップ: 事業構造マップ（BusinessMap.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<div class="reveal">
			<?php echo ais_section_heading( '事業の全体像', 'Business Map', '自動車事業を主軸に、IT・WEB事業、FC事業を展開。1社で複数の領域をつなぎ、総合的に課題解決をお手伝いします。', 'center' ); // phpcs:ignore ?>
		</div>

		<div class="reveal mt-12 flex flex-col items-center" style="transition-delay:80ms">
			<div class="inline-flex flex-col items-center rounded-2xl bg-ink-900 px-8 py-5 text-center text-white shadow-card">
				<span class="text-xs font-semibold tracking-widest text-accent-400">AIS</span>
				<span class="mt-1 text-lg font-bold">合同会社アイズ</span>
				<span class="mt-0.5 text-[11px] text-slate-400">Always Innovation Solutions</span>
			</div>
			<span class="h-8 w-px bg-slate-300" aria-hidden="true"></span>
		</div>

		<div class="grid gap-5 md:grid-cols-3">
			<?php foreach ( ais_service_groups() as $gi => $group ) : $items = ais_services_by_group( $group['id'] ); $primary = ! empty( $group['is_primary'] ); ?>
				<div class="reveal" style="transition-delay:<?php echo (int) ( 120 + $gi * 90 ); ?>ms">
					<div class="flex h-full flex-col rounded-2xl border bg-white p-6 shadow-card <?php echo $primary ? 'border-brand-200 ring-1 ring-inset ring-brand-100' : 'border-slate-200'; ?>">
						<div class="flex flex-wrap items-center gap-2">
							<h3 class="text-base font-bold text-ink-900"><?php echo esc_html( $group['label'] ); ?></h3>
							<?php if ( $primary ) : ?>
								<span class="rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">主力</span>
							<?php endif; ?>
						</div>
						<ul class="mt-4 space-y-2">
							<?php foreach ( $items as $s ) : ?>
								<li class="flex items-center gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
									<span class="grid h-8 w-8 flex-none place-items-center rounded-lg bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100">
										<?php echo ais_icon( $s['icon'], 'h-4 w-4' ); // phpcs:ignore ?>
									</span>
									<span class="min-w-0">
										<span class="block truncate text-sm font-semibold text-ink-900"><?php echo esc_html( ! empty( $s['brand'] ) ? $s['brand'] : $s['name'] ); ?></span>
										<span class="block truncate text-[11px] text-ink-500"><?php echo esc_html( $s['name'] ); ?></span>
									</span>
									<?php if ( ! empty( $s['coming_soon'] ) ) : ?>
										<span class="ml-auto flex-none rounded-full bg-accent-50 px-1.5 py-0.5 text-[9px] font-bold text-accent-700 ring-1 ring-inset ring-accent-200">準備中</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
