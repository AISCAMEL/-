<?php
/** トップ: FAQ抜粋（FaqSection.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$faqs = array_slice( ais_faqs(), 0, 5 );
?>
<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php echo ais_section_heading( 'よくある質問', 'FAQ', 'お問い合わせ前に、よくいただくご質問をまとめました。', 'center' ); // phpcs:ignore ?>
			<div class="mt-10">
				<?php echo ais_accordion( $faqs ); // phpcs:ignore ?>
			</div>
			<div class="mt-8 text-center">
				<?php echo ais_button( '/faq', 'すべての質問を見る', 'secondary', 'md' ); // phpcs:ignore ?>
			</div>
		</div>
	</div>
</section>
