<?php
/**
 * Site footer.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main><!-- #main -->

<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div class="site-footer__brand">
				<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">APP<span>REX</span></a>
				<p><?php esc_html_e( 'コードゼロで、アプリを世界へ。ノーコード × 月額 × 最短2週間でビジネスアプリを実現するクラウド型アプリ開発プラットフォーム。', 'apprex' ); ?></p>
			</div>

			<div class="site-footer__nav">
				<h4><?php esc_html_e( 'サービス', 'apprex' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( apprex_page_url( 'features' ) ); ?>"><?php esc_html_e( '特徴', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'functions' ) ); ?>"><?php esc_html_e( '機能', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/cases' ) ); ?>"><?php esc_html_e( '導入事例', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'pricing' ) ); ?>"><?php esc_html_e( '料金プラン', 'apprex' ); ?></a></li>
				</ul>
			</div>

			<div class="site-footer__nav">
				<h4><?php esc_html_e( 'サポート', 'apprex' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( apprex_page_url( 'faq' ) ); ?>"><?php esc_html_e( 'よくある質問', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'free-trial' ) ); ?>"><?php esc_html_e( '無料体験申し込み', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a></li>
				</ul>
			</div>
		</div>

		<div class="site-footer__bottom">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> APPREX / AIS Company. All rights reserved.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
