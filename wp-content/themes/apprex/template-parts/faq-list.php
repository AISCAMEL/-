<?php
/**
 * Reusable FAQ accordion list, spec §7/§9.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_faqs = apprex_default_faqs();
?>
<div class="faq-list" data-accordion>
	<?php foreach ( $apprex_faqs as $i => $faq ) : ?>
		<div class="faq-item is-reveal">
			<button
				class="faq-item__q"
				aria-expanded="false"
				aria-controls="faq-a-<?php echo esc_attr( $i ); ?>"
				id="faq-q-<?php echo esc_attr( $i ); ?>"
			><?php echo esc_html( $faq['q'] ); ?></button>
			<div class="faq-item__a" id="faq-a-<?php echo esc_attr( $i ); ?>" role="region" aria-labelledby="faq-q-<?php echo esc_attr( $i ); ?>">
				<div class="faq-item__a-inner"><?php echo esc_html( $faq['a'] ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
