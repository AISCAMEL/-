<?php
/**
 * HP制作 callout — 初期費用0円・月額9,800円〜（現行サイト準拠）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="section" id="hp-creation-cta">
	<div class="container">
		<div class="callout is-reveal">
			<span class="eyebrow" style="color:#fff;opacity:.85"><?php esc_html_e( '🌐 ホームページ作成にも対応', 'apprex' ); ?></span>
			<h2><?php esc_html_e( '初期費用0円、月額9,800円〜 で高品質サイト', 'apprex' ); ?></h2>
			<p><?php esc_html_e( 'AI技術を活用した効率的な制作プロセスで、コーポレートサイトから LP・EC まで対応します。', 'apprex' ); ?></p>
			<a class="btn btn--light" href="<?php echo esc_url( apprex_page_url( 'hp-creation' ) ); ?>"><?php esc_html_e( '詳しく見る', 'apprex' ); ?></a>
		</div>
	</div>
</section>
