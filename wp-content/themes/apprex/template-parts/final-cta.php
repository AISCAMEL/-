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
<?php if ( function_exists( 'apprex_rn_skin' ) && apprex_rn_skin() ) : ?>
<section class="section final-cta" id="final-cta">
	<div class="container">
		<h2 class="is-reveal">アプリづくりは、無料のAIスタジオから。</h2>
		<p class="is-reveal">会員登録だけで、AIがデザイン・試作・仕様書まで作ります。作った内容から制作する場合の初期費用は15万円〜（1からは30万円〜）。</p>
		<div class="final-cta__actions is-reveal">
			<a class="btn btn--primary btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>">AIスタジオを無料で使う</a>
			<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>">オンラインで相談する</a>
		</div>
	</div>
</section>
<?php return; endif; ?>
<section class="section final-cta" id="final-cta">
	<div class="container">
		<h2 class="is-reveal"><?php esc_html_e( 'まずは30日間、無料でお試しください', 'apprex' ); ?></h2>
		<p class="is-reveal"><?php esc_html_e( 'クレジットカード登録不要。デザインと仕様書はAIスタジオで無料。あなたのビジネスアプリを最短2週間で実現します。', 'apprex' ); ?></p>
		<div class="final-cta__actions is-reveal">
			<?php apprex_cta_buttons( 'light' ); ?>
		</div>
	</div>
</section>
