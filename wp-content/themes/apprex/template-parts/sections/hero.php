<?php
/**
 * 01. Hero — 現行サイト準拠のコピー。
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
			<span class="eyebrow"><?php esc_html_e( 'APPREX（アプリックス）', 'apprex' ); ?></span>
			<h1 class="hero__title"><?php echo wp_kses_post( __( 'プログラミング不要！<br><em>誰でも簡単にアプリ開発</em>', 'apprex' ) ); ?></h1>
			<p class="hero__lead"><?php esc_html_e( 'クラウド型ノーコードアプリ開発プラットフォーム。高性能・低価格・スピード開発で、自社アプリを今すぐ。制作代行も承ります。', 'apprex' ); ?></p>

			<div class="hero__badges">
				<span class="hero__badge"><?php esc_html_e( 'iOS / Android 対応', 'apprex' ); ?></span>
				<span class="hero__badge"><?php esc_html_e( 'スピード公開対応（最短2週間）', 'apprex' ); ?></span>
				<span class="hero__badge"><?php esc_html_e( '初期費用0円キャンペーン中', 'apprex' ); ?></span>
			</div>

			<div class="hero__cta">
				<?php apprex_cta_buttons( 'accent' ); ?>
			</div>

			<p class="hero__sub">
				<?php esc_html_e( '安心の実績｜専任サポート｜', 'apprex' ); ?><strong><?php esc_html_e( '無料アップデート対応', 'apprex' ); ?></strong>
			</p>
		</div>

		<div class="hero__visual is-reveal">
			<img
				src="<?php echo esc_url( APPREX_URI . '/assets/images/app-sample-taka.jpg' ); ?>"
				alt="<?php esc_attr_e( 'APPREX で開発したアプリの画面例', 'apprex' ); ?>"
				class="hero__mock"
				style="aspect-ratio:3/4;object-fit:cover;padding:0"
				width="320" height="427" loading="eager">
		</div>
	</div>
</section>
