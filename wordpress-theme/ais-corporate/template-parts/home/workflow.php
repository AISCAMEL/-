<?php
/** トップ: 流れ（Workflow.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$w = ais_home_workflow();
?>
<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( $w['heading'], 'Flow', $w['lead'], 'center' ); // phpcs:ignore ?>
		<ol class="mt-12 grid gap-4 md:grid-cols-5">
			<?php foreach ( $w['steps'] as $i => $step ) : ?>
				<li class="relative">
					<div class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-card">
						<span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white"><?php echo (int) ( $i + 1 ); ?></span>
						<h3 class="mt-4 text-base font-bold text-ink-900"><?php echo esc_html( $step['title'] ); ?></h3>
						<p class="mt-2 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $step['body'] ); ?></p>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
