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

<?php do_action( 'apprex_footer_before' ); ?>

<?php
$apprex_rn    = function_exists( 'apprex_rn_enabled' ) && apprex_rn_enabled();
$apprex_line  = apprex_line_url();
$apprex_priv  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
$apprex_cols  = array(
	'サービス'           => array_filter(
		array(
			array( '特徴', apprex_page_url( 'features' ) ),
			array( '機能説明', apprex_page_url( 'functions' ) ),
			array( '料金プラン', apprex_page_url( 'pricing' ) ),
			array( '導入事例', home_url( '/cases/' ) ),
			$apprex_rn ? array( 'AIスタジオ（アプリ）', apprex_studio_url() ) : null,
			$apprex_rn ? array( 'AIプランナー（ホームページ）', apprex_studio_url( 'hp' ) ) : null,
			$apprex_rn ? array( 'AIスタジオ（マッチングアプリ）', apprex_studio_url( 'matching' ) ) : null,
			$apprex_rn ? array( 'マッチングアプリ開発', apprex_page_url( 'matching-app' ) ) : null,
			array( 'ホームページ制作', apprex_page_url( 'hp-creation' ) ),
			$apprex_rn ? array( '補助金で導入', apprex_page_url( 'subsidy' ) ) : null,
		)
	),
	'はじめる・ご相談'   => array(
		array( '無料体験', apprex_page_url( 'free-trial' ) ),
		array( '資料請求', apprex_page_url( 'document' ) ),
		array( '見積もり・発注', apprex_page_url( 'estimate' ) ),
		array( 'ミーティング予約', apprex_page_url( 'meeting' ) ),
		array( 'お問い合わせ', apprex_page_url( 'contact' ) ),
	),
	'サポート・会社'     => array_filter(
		array(
			array( 'よくある質問', apprex_page_url( 'faq' ) ),
			$apprex_rn ? array( 'アプリの作り方マニュアル', apprex_manual_url() ) : null,
			array( 'ブログ', home_url( '/blog/' ) ),
			array( '会社概要', apprex_page_url( 'company' ) ),
			array( '代理店募集', apprex_page_url( 'partner' ) ),
			array( '代理店ログイン', home_url( '/agency/' ) ),
		)
	),
);
?>
<footer class="site-footer">
	<div class="container">
		<div class="site-footer__grid">
			<div class="site-footer__brand">
				<a class="site-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img class="logo-image" src="<?php echo esc_url( APPREX_URI . '/assets/images/apprex-logo.png' ); ?>" alt="APPREX" width="160" height="40"></a>
				<p class="site-footer__lead"><?php esc_html_e( 'ノーコードアプリ、オリジナルアプリ、ホームページ制作まで。デザインと仕様書はAIで無料で作れて、そのまま形にできるアプリ開発のAPPREX（アプリックス）です。', 'apprex' ); ?></p>
				<dl class="site-footer__info">
					<dt><?php esc_html_e( '運営', 'apprex' ); ?></dt><dd><?php esc_html_e( '合同会社アイズ', 'apprex' ); ?></dd>
					<dt><?php esc_html_e( '受付', 'apprex' ); ?></dt><dd><?php esc_html_e( '平日 10:00〜18:00（チャット・メール・オンライン相談）', 'apprex' ); ?></dd>
					<dt><?php esc_html_e( 'メール', 'apprex' ); ?></dt><dd><a href="mailto:<?php echo esc_attr( apprex_contact_email() ); ?>"><?php echo esc_html( apprex_contact_email() ); ?></a></dd>
				</dl>
				<div class="site-footer__sns">
					<a href="https://www.instagram.com/apprex1173/" target="_blank" rel="noopener"><?php esc_html_e( 'Instagram', 'apprex' ); ?></a>
					<?php if ( $apprex_line ) : ?>
						<a href="<?php echo esc_url( $apprex_line ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LINEで相談', 'apprex' ); ?></a>
					<?php endif; ?>
					<a class="is-cta" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( '無料で相談する', 'apprex' ); ?></a>
				</div>
			</div>

			<?php foreach ( $apprex_cols as $apprex_h => $apprex_links ) : ?>
				<nav class="site-footer__nav" aria-label="<?php echo esc_attr( $apprex_h ); ?>">
					<h4><?php echo esc_html( $apprex_h ); ?></h4>
					<ul>
						<?php foreach ( $apprex_links as $apprex_l ) : ?>
							<li><a href="<?php echo esc_url( $apprex_l[1] ); ?>"><?php echo esc_html( $apprex_l[0] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
		</div>

		<div class="site-footer__bottom">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> APPREX by 合同会社アイズ. All rights reserved.</p>
			<?php if ( $apprex_priv ) : ?>
				<p class="site-footer__legal"><a href="<?php echo esc_url( $apprex_priv ); ?>"><?php esc_html_e( 'プライバシーポリシー', 'apprex' ); ?></a></p>
			<?php endif; ?>
		</div>
	</div>
</footer>

<!-- モバイル固定CTAバー（スマホのみ表示） -->
<div class="apprex-mobilebar" aria-label="<?php esc_attr_e( 'お問い合わせ導線', 'apprex' ); ?>">
	<a class="apprex-mobilebar__btn apprex-mobilebar__btn--cta" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( '無料で相談', 'apprex' ); ?></a>
	<?php if ( function_exists( 'apprex_rn_enabled' ) && apprex_rn_enabled() ) : ?>
		<a class="apprex-mobilebar__btn" href="<?php echo esc_url( apprex_studio_url() ); ?>"><?php esc_html_e( 'AIでデザイン', 'apprex' ); ?></a>
	<?php else : ?>
		<a class="apprex-mobilebar__btn" href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>"><?php esc_html_e( '見積もり', 'apprex' ); ?></a>
	<?php endif; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
