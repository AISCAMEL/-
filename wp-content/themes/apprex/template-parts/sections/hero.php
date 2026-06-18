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
<section class="hero hero--dark" id="hero">
	<div class="container hero__inner">
		<div class="hero__content is-reveal">
			<span class="eyebrow"><?php esc_html_e( 'APPREX（アプリックス）', 'apprex' ); ?></span>
			<h1 class="hero__title"><?php echo wp_kses_post( __( 'プログラミング不要！<br><em>誰でも簡単にアプリ開発</em>', 'apprex' ) ); ?></h1>
			<p class="hero__lead"><?php esc_html_e( 'クラウド型ノーコードアプリ開発プラットフォーム。高性能・低価格・スピード開発で、自社アプリを今すぐ。制作代行も承ります。', 'apprex' ); ?></p>

			<div class="hero__kpis">
				<div class="hero__kpi"><strong>8,000<small>+</small></strong><span><?php esc_html_e( '導入実績', 'apprex' ); ?></span></div>
				<div class="hero__kpi"><strong>1/10</strong><span><?php esc_html_e( '従来コスト', 'apprex' ); ?></span></div>
				<div class="hero__kpi"><strong><?php esc_html_e( '最短2', 'apprex' ); ?><small><?php esc_html_e( '週間', 'apprex' ); ?></small></strong><span><?php esc_html_e( 'スピード公開', 'apprex' ); ?></span></div>
			</div>

			<div class="hero__badges">
				<span class="hero__badge"><?php esc_html_e( 'iOS / Android 対応', 'apprex' ); ?></span>
				<span class="hero__badge"><?php esc_html_e( '初期費用0円キャンペーン中', 'apprex' ); ?></span>
			</div>

			<div class="hero__cta">
				<?php apprex_cta_buttons( 'accent' ); ?>
			</div>

			<p class="hero__sub">
				<?php esc_html_e( '安心の実績｜専任サポート｜', 'apprex' ); ?><strong><?php esc_html_e( '無料アップデート対応', 'apprex' ); ?></strong>
			</p>
		</div>

		<?php
		// ヒーロー画像：固定ページ「ホーム」のアイキャッチ画像があればそれを使用。
		// 無ければ同梱のサンプル画像。→ 管理画面でバナーを差し替え可能。
		$apprex_hero_img = '';
		$apprex_front_id = (int) get_option( 'page_on_front' );
		if ( $apprex_front_id && has_post_thumbnail( $apprex_front_id ) ) {
			$apprex_hero_img = get_the_post_thumbnail_url( $apprex_front_id, 'large' );
		}
		if ( ! $apprex_hero_img ) {
			$apprex_hero_img = APPREX_URI . '/assets/images/app-sample-taka.jpg';
		}
		?>
		<div class="hero__visual is-reveal">
			<div class="hero__phone">
				<span class="hero__phone-notch" aria-hidden="true"></span>
				<img
					src="<?php echo esc_url( $apprex_hero_img ); ?>"
					alt="<?php esc_attr_e( 'APPREX で作成したアプリのイメージ', 'apprex' ); ?>"
					class="hero__phone-screen"
					width="300" height="620" loading="eager" decoding="async">
			</div>
		</div>
	</div>
</section>
