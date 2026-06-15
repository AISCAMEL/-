<?php
/** トップ: メッセージ（Message.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$m = ais_home_message();
?>
<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl text-center">
			<span class="eyebrow mx-auto justify-center">Message</span>
			<h2 class="mt-4 text-3xl font-bold text-ink-900 sm:text-4xl"><?php echo esc_html( $m['heading'] ); ?></h2>
			<div class="mt-6 space-y-4">
				<?php foreach ( $m['body'] as $para ) : ?>
					<p class="text-base leading-relaxed text-ink-600"><?php echo esc_html( $para ); ?></p>
				<?php endforeach; ?>
			</div>
			<div class="mt-8">
				<?php echo ais_button( '/philosophy', '私たちの理念を見る', 'secondary', 'md' ); // phpcs:ignore ?>
			</div>
		</div>
	</div>
</section>
