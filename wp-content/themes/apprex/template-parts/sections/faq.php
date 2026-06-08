<?php
/**
 * 09. FAQ — accordion, spec §6/§7/§9.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section" id="faq">
	<div class="container">
		<?php apprex_section_head( 'FAQ', __( 'よくあるご質問', 'apprex' ) ); ?>
		<?php get_template_part( 'template-parts/faq-list' ); ?>
	</div>
</section>
