<?php
/**
 * 01. Hero — spec §6.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="hero" id="hero">
	<div class="container hero__inner">
		<div class="hero__content is-reveal">
			<h1 class="hero__title"><?php echo wp_kses_post( __( 'コードゼロで、<em>アプリを世界へ。</em>', 'apprex' ) ); ?></h1>
			<p class="hero__lead"><?php esc_html_e( 'ノーコード × 月額 × 最短2週間。エンジニア不在でも、ビジネスアプリを今すぐ世界へ届けられます。', 'apprex' ); ?></p>

			<div class="hero__badges">
				<span class="hero__badge"><?php esc_html_e( '初期費用0円キャンペーン', 'apprex' ); ?></span>
				<span class="hero__badge"><?php esc_html_e( '月額19,800円〜', 'apprex' ); ?></span>
				<span class="hero__badge"><?php esc_html_e( '導入実績8,000+', 'apprex' ); ?></span>
			</div>

			<div class="hero__cta">
				<?php apprex_cta_buttons( 'accent' ); ?>
			</div>
		</div>

		<div class="hero__visual is-reveal">
			<div class="hero__mock" role="img" aria-label="<?php esc_attr_e( 'APPREX で構築したアプリの動作イメージ', 'apprex' ); ?>">
				<?php esc_html_e( 'App Preview', 'apprex' ); ?>
			</div>
		</div>
	</div>
</section>
