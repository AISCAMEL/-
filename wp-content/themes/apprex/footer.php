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
				<img class="logo-image" src="<?php echo esc_url( APPREX_URI . '/assets/images/apprex-logo.png' ); ?>" alt="APPREX" width="160" height="40">
				<p><?php esc_html_e( 'ノーコードでアプリ開発。クラウド型アプリ開発プラットフォーム APPREX。プログラミング不要で、誰でも簡単に高性能アプリを制作・運営できます。', 'apprex' ); ?></p>
				<p><strong><?php esc_html_e( '合同会社アイズ', 'apprex' ); ?></strong><br>
				<?php esc_html_e( '受付時間：平日 10:00〜18:00（チャット・メール・オンライン相談）', 'apprex' ); ?></p>
			</div>

			<div class="site-footer__nav">
				<h4><?php esc_html_e( 'サービス', 'apprex' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( apprex_page_url( 'features' ) ); ?>"><?php esc_html_e( '特徴', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'functions' ) ); ?>"><?php esc_html_e( '機能説明', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'pricing' ) ); ?>"><?php esc_html_e( '利用料金', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/cases' ) ); ?>"><?php esc_html_e( 'アプリ事例', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'hp-creation' ) ); ?>"><?php esc_html_e( 'ホームページ制作', 'apprex' ); ?></a></li>
				</ul>
			</div>

			<div class="site-footer__nav">
				<h4><?php esc_html_e( 'サポート・会社', 'apprex' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( apprex_page_url( 'faq' ) ); ?>"><?php esc_html_e( 'よくある質問', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>"><?php esc_html_e( '見積もり・発注', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'free-trial' ) ); ?>"><?php esc_html_e( '無料体験', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'document' ) ); ?>"><?php esc_html_e( '資料請求', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( 'ミーティング予約', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a></li>
					<li><a href="<?php echo esc_url( apprex_page_url( 'company' ) ); ?>"><?php esc_html_e( '会社概要', 'apprex' ); ?></a></li>
					<li><a href="https://www.instagram.com/apprex1173/" target="_blank" rel="noopener"><?php esc_html_e( 'Instagram（@apprex1173）', 'apprex' ); ?></a></li>
					<?php $apprex_line = apprex_line_url(); ?>
					<?php if ( $apprex_line ) : ?>
						<li><a href="<?php echo esc_url( $apprex_line ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LINEで相談', 'apprex' ); ?></a></li>
					<?php endif; ?>
				</ul>
			</div>
		</div>

		<div class="site-footer__bottom">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> APPREX by 合同会社アイズ. All rights reserved.</p>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
