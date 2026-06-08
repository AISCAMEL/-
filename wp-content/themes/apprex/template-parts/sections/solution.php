<?php
/**
 * 04. Solution — APPREX answers the problems, spec §6.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section section--soft" id="solution">
	<div class="container solution">
		<?php apprex_section_head( 'Solution', __( 'APPREX なら、すべて解決。', 'apprex' ) ); ?>

		<div class="solution__formula is-reveal">
			<span><?php esc_html_e( 'ノーコード', 'apprex' ); ?></span>
			<span class="x">×</span>
			<span><?php esc_html_e( '月額', 'apprex' ); ?></span>
			<span class="x">×</span>
			<span><?php esc_html_e( '最短2週間', 'apprex' ); ?></span>
		</div>

		<p class="is-reveal"><?php esc_html_e( '高い開発費も、長い開発期間も、採用の難しさも、APPREX が一気に解決します。', 'apprex' ); ?></p>

		<div class="mt-32 is-reveal">
			<a class="btn btn--primary" href="<?php echo esc_url( apprex_page_url( 'features' ) ); ?>"><?php esc_html_e( '詳細を見る', 'apprex' ); ?></a>
		</div>
	</div>
</section>
