<?php
/** トップ: ヒーロー（Hero.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$trust = array(
	'販売・買取・オンライン納車・セキュリティ・レッカー',
	'ノーコードアプリ「APPREX」・サブスクWeb制作',
	'FC（カーメル／BUYMO）加盟募集',
);
$cards = array(
	array( 'icon' => 'car', 'label' => '自動車事業', 'desc' => '販売・買取・オンライン納車・セキュリティ・レッカー', 'primary' => true ),
	array( 'icon' => 'app', 'label' => 'IT・WEB事業', 'desc' => 'APPREX／WEB crews／AIオペレーター24', 'primary' => false ),
	array( 'icon' => 'store', 'label' => 'FC事業', 'desc' => 'カーメル／BUYMO 加盟募集', 'primary' => false ),
);
?>
<section class="relative overflow-hidden bg-ink-900 text-white">
	<div class="pointer-events-none absolute inset-0" aria-hidden="true"
		style="background:radial-gradient(70% 60% at 75% 10%, rgba(6,182,212,0.20), transparent 55%), radial-gradient(60% 70% at 10% 90%, rgba(37,99,235,0.28), transparent 55%)"></div>
	<div class="pointer-events-none absolute inset-0 opacity-[0.07]" aria-hidden="true"
		style="background-image:linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px);background-size:56px 56px;-webkit-mask-image:radial-gradient(80% 80% at 50% 30%, black, transparent);mask-image:radial-gradient(80% 80% at 50% 30%, black, transparent)"></div>

	<div class="container relative">
		<div class="grid items-center gap-12 py-20 sm:py-28 lg:grid-cols-12">
			<div class="lg:col-span-7">
				<span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-accent-400 ring-1 ring-inset ring-white/15">
					<?php echo ais_icon( 'spark', 'h-4 w-4' ); // phpcs:ignore ?>
					Always Innovation Solutions
				</span>

				<h1 class="mt-6 text-3xl font-bold leading-tight sm:text-4xl md:text-5xl lg:text-[3.25rem]">
					クルマのことを、<span class="bg-gradient-to-r from-accent-400 to-brand-400 bg-clip-text text-transparent">一社でまるごと。</span>
				</h1>

				<p class="mt-6 max-w-2xl text-base leading-relaxed text-slate-300 sm:text-lg">
					合同会社アイズは、自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカーまで、クルマに関わるすべてを手がける会社です。さらに、ノーコードアプリ開発「APPREX」やサブスクWeb制作「WEB crews」、FC事業まで。あなたのニーズにワンストップでお応えします。
				</p>

				<div class="mt-9 flex flex-col gap-3 sm:flex-row">
					<?php echo ais_button( '/contact', '無料で相談する' . ais_icon( 'arrow-right', 'h-4 w-4' ), 'primary', 'lg' ); // phpcs:ignore ?>
					<?php echo ais_button( '/services', 'サービスを見る', 'ghost', 'lg' ); // phpcs:ignore ?>
				</div>

				<ul class="mt-10 flex flex-wrap gap-x-6 gap-y-3">
					<?php foreach ( $trust as $t ) : ?>
						<li class="flex items-center gap-2 text-sm text-slate-300">
							<?php echo ais_icon( 'check', 'h-4 w-4 flex-none text-accent-400' ); // phpcs:ignore ?>
							<?php echo esc_html( $t ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="lg:col-span-5">
				<div class="grid gap-3">
					<?php foreach ( $cards as $c ) : ?>
						<div class="flex items-center gap-4 rounded-2xl border p-5 backdrop-blur <?php echo $c['primary'] ? 'border-accent-400/40 bg-white/[0.10] ring-1 ring-inset ring-accent-400/30' : 'border-white/10 bg-white/[0.06]'; ?>">
							<span class="grid h-12 w-12 flex-none place-items-center rounded-xl bg-brand-600/30 text-accent-400 ring-1 ring-inset ring-white/10">
								<?php echo ais_icon( $c['icon'], 'h-6 w-6' ); // phpcs:ignore ?>
							</span>
							<div>
								<p class="flex items-center gap-2 font-semibold text-white">
									<?php echo esc_html( $c['label'] ); ?>
									<?php if ( $c['primary'] ) : ?>
										<span class="rounded-full bg-accent-400/20 px-2 py-0.5 text-[10px] font-bold text-accent-300">主力事業</span>
									<?php endif; ?>
								</p>
								<p class="text-sm text-slate-400"><?php echo esc_html( $c['desc'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
