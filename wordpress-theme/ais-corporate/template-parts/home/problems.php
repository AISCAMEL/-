<?php
/** トップ: 課題（Problems.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$p = ais_home_problems();
?>
<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( $p['heading'], 'Problem', $p['lead'], 'center' ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
			<?php foreach ( $p['items'] as $i => $item ) : ?>
				<div class="reveal rounded-2xl border border-slate-200 bg-white p-6 shadow-card" style="transition-delay:<?php echo (int) ( $i * 70 ); ?>ms">
					<p class="text-3xl font-bold text-brand-100">？</p>
					<h3 class="mt-2 text-base font-bold text-ink-900"><?php echo esc_html( $item['title'] ); ?></h3>
					<p class="mt-2 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $item['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="mt-10 text-center text-base font-semibold text-ink-700">──そのお悩み、アイズが<span class="text-brand-700">戦略から実行まで</span>まとめてご支援します。</p>
	</div>
</section>
