<?php
/**
 * 10. Final CTA — shared closing call to action, spec §6/§9.
 *
 * Placed at the end of the front page and every lower page.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section final-cta" id="final-cta">
	<div class="container">
		<h2 class="is-reveal"><?php esc_html_e( 'まずは30日間、無料でお試しください', 'apprex' ); ?></h2>
		<p class="is-reveal"><?php esc_html_e( 'クレジットカード登録不要。導入実績8,000社以上のノウハウで、あなたのビジネスアプリを最短2週間で実現します。', 'apprex' ); ?></p>
		<div class="final-cta__actions is-reveal">
			<?php apprex_cta_buttons( 'light' ); ?>
		</div>
	</div>
</section>
