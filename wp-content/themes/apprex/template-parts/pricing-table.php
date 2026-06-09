<?php
/**
 * Reusable pricing tables — アプリ開発 / 制作代行 / 自社制作。
 * 料金は README（権威データ）＋トップページに準拠。stale な pricing.html は不採用。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_app_plans = array(
	array(
		'name'     => __( 'Trial', 'apprex' ),
		'price'    => '19,800',
		'unit'     => __( '円 / 月（税抜）', 'apprex' ),
		'featured' => false,
		'feats'    => array(
			__( '初期費用0円', 'apprex' ),
			__( 'ノーコードでアプリ作成', 'apprex' ),
			__( 'iOS / Android 対応', 'apprex' ),
			__( 'メール・チャットサポート', 'apprex' ),
		),
		'cta'      => __( '無料体験を始める', 'apprex' ),
		'cta_url'  => apprex_page_url( 'free-trial' ),
	),
	array(
		'name'     => __( 'Start', 'apprex' ),
		'price'    => '39,800',
		'unit'     => __( '円 / 月（税抜）', 'apprex' ),
		'featured' => true,
		'feats'    => array(
			__( '初期費用0円', 'apprex' ),
			__( 'Trial の全機能', 'apprex' ),
			__( 'プッシュ通知・会員管理', 'apprex' ),
			__( '分析機能', 'apprex' ),
			__( '優先サポート', 'apprex' ),
		),
		'cta'      => __( 'このプランで始める', 'apprex' ),
		'cta_url'  => apprex_page_url( 'contact' ),
	),
	array(
		'name'     => __( 'Business', 'apprex' ),
		'price'    => '59,800',
		'unit'     => __( '円 / 月（税抜）', 'apprex' ),
		'featured' => false,
		'feats'    => array(
			__( '初期費用0円', 'apprex' ),
			__( 'Start の全機能', 'apprex' ),
			__( 'EC・決済・外部連携', 'apprex' ),
			__( 'カスタマイズ開発対応', 'apprex' ),
			__( '専任担当による運用支援', 'apprex' ),
		),
		'cta'      => __( '相談する', 'apprex' ),
		'cta_url'  => apprex_page_url( 'contact' ),
	),
);

$apprex_agency_plans = array(
	array( 'name' => __( '制作代行 Trial', 'apprex' ), 'price' => '100,000', 'unit' => __( '円〜（買い切り）', 'apprex' ) ),
	array( 'name' => __( '制作代行 Start', 'apprex' ), 'price' => '150,000', 'unit' => __( '円〜（買い切り）', 'apprex' ) ),
	array( 'name' => __( '制作代行 Business', 'apprex' ), 'price' => '200,000', 'unit' => __( '円〜（買い切り）', 'apprex' ) ),
);
?>

<h3 class="plan-group-title"><?php esc_html_e( 'アプリ開発プラン（月額制・初期費用0円）', 'apprex' ); ?></h3>
<div class="pricing">
	<?php foreach ( $apprex_app_plans as $plan ) : ?>
		<div class="price-card<?php echo $plan['featured'] ? ' price-card--featured' : ''; ?> is-reveal">
			<?php if ( $plan['featured'] ) : ?>
				<span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span>
			<?php endif; ?>
			<h3><?php echo esc_html( $plan['name'] ); ?></h3>
			<div class="price"><?php echo esc_html( $plan['price'] ); ?><small><?php echo esc_html( $plan['unit'] ); ?></small></div>
			<ul class="feats">
				<?php foreach ( $plan['feats'] as $feat ) : ?>
					<li><?php echo esc_html( $feat ); ?></li>
				<?php endforeach; ?>
			</ul>
			<a class="btn <?php echo $plan['featured'] ? 'btn--primary' : 'btn--ghost'; ?>" href="<?php echo esc_url( $plan['cta_url'] ); ?>">
				<?php echo esc_html( $plan['cta'] ); ?>
			</a>
		</div>
	<?php endforeach; ?>
</div>
<p class="plan-note"><?php esc_html_e( '※ 最低契約期間は12ヶ月です。30日間無料体験あり。初期費用0円キャンペーン実施中（通常30万円）。', 'apprex' ); ?></p>

<h3 class="plan-group-title"><?php esc_html_e( '制作代行プラン（買い切り）', 'apprex' ); ?></h3>
<div class="grid grid--3">
	<?php foreach ( $apprex_agency_plans as $plan ) : ?>
		<div class="price-card is-reveal">
			<h3><?php echo esc_html( $plan['name'] ); ?></h3>
			<div class="price"><?php echo esc_html( $plan['price'] ); ?><small><?php echo esc_html( $plan['unit'] ); ?></small></div>
			<a class="btn btn--ghost" href="<?php echo esc_url( apprex_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a>
		</div>
	<?php endforeach; ?>
</div>
<p class="plan-note"><?php esc_html_e( '※ 自社制作（お客様ご自身での制作）は無料でご利用いただけます。', 'apprex' ); ?></p>
