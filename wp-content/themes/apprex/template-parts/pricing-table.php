<?php
/**
 * Reusable 3-plan pricing table (トライアル / スタート / ビジネス), spec §7/§11.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_plans = array(
	array(
		'name'     => __( 'トライアル', 'apprex' ),
		'price'    => '0',
		'unit'     => __( '円 / 30日間', 'apprex' ),
		'featured' => false,
		'feats'    => array(
			__( '主要機能をすべてお試し', 'apprex' ),
			__( 'クレジットカード登録不要', 'apprex' ),
			__( 'オンライン相談サポート', 'apprex' ),
		),
		'cta'      => __( '無料で試す', 'apprex' ),
		'cta_url'  => apprex_page_url( 'free-trial' ),
	),
	array(
		'name'     => __( 'スタート', 'apprex' ),
		'price'    => '19,800',
		'unit'     => __( '円 / 月（税抜）', 'apprex' ),
		'featured' => true,
		'feats'    => array(
			__( '基本プラットフォーム機能', 'apprex' ),
			__( 'iOS / Android 両対応', 'apprex' ),
			__( 'プッシュ通知・会員管理', 'apprex' ),
			__( 'メールサポート', 'apprex' ),
		),
		'cta'      => __( 'このプランで始める', 'apprex' ),
		'cta_url'  => apprex_page_url( 'contact' ),
	),
	array(
		'name'     => __( 'ビジネス', 'apprex' ),
		'price'    => __( '要問合せ', 'apprex' ),
		'unit'     => '',
		'featured' => false,
		'feats'    => array(
			__( 'EC・決済・外部連携機能', 'apprex' ),
			__( 'カスタマイズ開発対応', 'apprex' ),
			__( '優先サポート', 'apprex' ),
			__( '専任担当による運用支援', 'apprex' ),
		),
		'cta'      => __( '相談する', 'apprex' ),
		'cta_url'  => apprex_page_url( 'contact' ),
	),
);
?>
<div class="pricing">
	<?php foreach ( $apprex_plans as $plan ) : ?>
		<div class="price-card<?php echo $plan['featured'] ? ' price-card--featured' : ''; ?> is-reveal">
			<?php if ( $plan['featured'] ) : ?>
				<span class="ribbon"><?php esc_html_e( '一番人気', 'apprex' ); ?></span>
			<?php endif; ?>
			<h3><?php echo esc_html( $plan['name'] ); ?></h3>
			<div class="price">
				<?php echo esc_html( $plan['price'] ); ?>
				<?php if ( $plan['unit'] ) : ?><small><?php echo esc_html( $plan['unit'] ); ?></small><?php endif; ?>
			</div>
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
<p class="text-center" style="color:var(--color-muted);font-size:.85rem;margin-top:18px">
	<?php esc_html_e( '※ 最低契約期間は12ヶ月です。初期費用0円キャンペーン実施中（通常30万円）。', 'apprex' ); ?>
</p>
