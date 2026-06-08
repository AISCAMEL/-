<?php
/**
 * 08. Pricing — spec §6/§7.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section section--soft" id="pricing">
	<div class="container">
		<?php apprex_section_head( 'Pricing', __( 'シンプルで分かりやすい料金プラン', 'apprex' ), __( '業界最安水準。まずは30日間無料でお試しください。', 'apprex' ) ); ?>
		<?php get_template_part( 'template-parts/pricing-table' ); ?>
	</div>
</section>
