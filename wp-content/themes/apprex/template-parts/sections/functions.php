<?php
/**
 * 06. Functions — 6 categories with tab-switch UI, spec §6/§7.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_functions = array(
	array( 'title' => __( '基本プラットフォーム', 'apprex' ), 'desc' => __( 'プッシュ通知、会員管理、ニュース配信などアプリの土台となる機能群。', 'apprex' ) ),
	array( 'title' => __( 'コミュニケーション', 'apprex' ), 'desc' => __( 'チャット、トーク、お問い合わせなど顧客との接点を強化する機能。', 'apprex' ) ),
	array( 'title' => __( 'マーケティング・販促', 'apprex' ), 'desc' => __( 'クーポン、スタンプカード、セグメント配信で再来店を促進。', 'apprex' ) ),
	array( 'title' => __( 'EC・決済機能', 'apprex' ), 'desc' => __( 'オンライン注文、モバイルオーダー、各種決済に対応。', 'apprex' ) ),
	array( 'title' => __( '管理・運営', 'apprex' ), 'desc' => __( '予約管理、在庫確認、レポートなど日々の運営を効率化。', 'apprex' ) ),
	array( 'title' => __( '外部連携', 'apprex' ), 'desc' => __( '既存システムや外部サービスとAPI連携し、業務を一気通貫に。', 'apprex' ) ),
);
?>
<section class="section section--soft" id="functions">
	<div class="container">
		<?php apprex_section_head( 'Functions', __( '豊富な機能で、あらゆる業種に対応', 'apprex' ), __( '必要な機能をタブから選んでご確認ください。', 'apprex' ) ); ?>

		<div class="tabs is-reveal" data-tabs>
			<div class="tabs__nav" role="tablist">
				<?php foreach ( $apprex_functions as $i => $fn ) : ?>
					<button
						class="tabs__btn"
						role="tab"
						id="tab-<?php echo esc_attr( $i ); ?>"
						aria-controls="panel-<?php echo esc_attr( $i ); ?>"
						aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"
					><?php echo esc_html( $fn['title'] ); ?></button>
				<?php endforeach; ?>
			</div>

			<?php foreach ( $apprex_functions as $i => $fn ) : ?>
				<div
					class="tabs__panel<?php echo 0 === $i ? ' is-active' : ''; ?>"
					role="tabpanel"
					id="panel-<?php echo esc_attr( $i ); ?>"
					aria-labelledby="tab-<?php echo esc_attr( $i ); ?>"
					<?php echo 0 === $i ? '' : 'hidden'; ?>
				>
					<div class="feature-card">
						<h3><?php echo esc_html( $fn['title'] ); ?></h3>
						<p><?php echo esc_html( $fn['desc'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="text-center mt-32 is-reveal">
			<a class="btn btn--ghost" href="<?php echo esc_url( apprex_page_url( 'functions' ) ); ?>"><?php esc_html_e( '機能の詳細へ', 'apprex' ); ?></a>
		</div>
	</div>
</section>
