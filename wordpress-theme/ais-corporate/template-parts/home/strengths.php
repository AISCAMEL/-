<?php
/** トップ: 選ばれる理由（Strengths.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$s = ais_home_strengths();
?>
<section class="py-16 sm:py-24 bg-ink-900 text-white">
	<div class="container">
		<?php echo ais_section_heading( $s['heading'], 'Why AIS', $s['lead'], 'center', true ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 sm:grid-cols-2">
			<?php foreach ( $s['items'] as $i => $item ) : ?>
				<div class="reveal rounded-2xl border border-white/10 bg-white/[0.04] p-7 backdrop-blur" style="transition-delay:<?php echo (int) ( $i * 80 ); ?>ms">
					<div class="flex items-baseline gap-3">
						<span class="text-3xl font-bold text-accent-400"><?php echo esc_html( $item['no'] ); ?></span>
						<h3 class="text-lg font-bold text-white"><?php echo esc_html( $item['title'] ); ?></h3>
					</div>
					<p class="mt-3 text-sm leading-relaxed text-slate-300"><?php echo esc_html( $item['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
