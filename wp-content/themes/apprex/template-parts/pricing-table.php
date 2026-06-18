<?php
/**
 * 料金表（APPREX仕様）。アプリ開発＋HP制作＋オプション。
 * 個別見積（マッチング等）と注記は page-pricing.php 側で追記。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_cfg     = apprex_pricing_config();
$apprex_contact = apprex_page_url( 'contact' );
$apprex_est     = apprex_page_url( 'estimate' );

/**
 * 1サービス分のプラン表を描画。
 *
 * @param array  $svc      サービス定義。
 * @param string $featured おすすめプランkey。
 */
$apprex_render_service = function ( $svc, $featured = '' ) use ( $apprex_est, $apprex_contact ) {
	echo '<div class="pricing">';
	foreach ( $svc['plans'] as $key => $p ) {
		$is_feat   = ( $key === $featured );
		$initial_r = (int) ( $p['initial'] ?? 0 );
		$initial   = apprex_campaign_initial( $initial_r );
		?>
		<div class="price-card<?php echo $is_feat ? ' price-card--featured' : ''; ?> is-reveal">
			<?php if ( $is_feat ) : ?><span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span><?php endif; ?>
			<h3><?php echo esc_html( $p['label'] ); ?></h3>
			<div class="price">
				<?php echo esc_html( number_format( $p['monthly'] ) ); ?><small><?php esc_html_e( '円 / 月（税抜）', 'apprex' ); ?></small>
			</div>
			<?php if ( ! empty( $p['monthly_regular'] ) && $p['monthly_regular'] > $p['monthly'] ) : ?>
				<p class="price-was">通常 <s><?php echo esc_html( number_format( $p['monthly_regular'] ) ); ?>円</s> → <b>キャンペーン</b></p>
			<?php endif; ?>
			<ul class="feats">
				<li><?php esc_html_e( '開発費用 0円', 'apprex' ); ?></li>
				<li>
					<?php esc_html_e( '初期設定費', 'apprex' ); ?>
					<?php if ( $initial_r > $initial ) : ?>
						<s><?php echo esc_html( number_format( $initial_r ) ); ?>円</s> → <strong><?php echo esc_html( number_format( $initial ) ); ?>円</strong>（今月）
					<?php else : ?>
						<?php echo esc_html( number_format( $initial ) ); ?>円
					<?php endif; ?>
				</li>
				<li><?php echo esc_html( $p['desc'] ); ?></li>
				<?php if ( ! empty( $p['support'] ) ) : ?>
					<?php foreach ( $p['support'] as $s ) : ?>
						<li><?php echo esc_html( $s ); ?></li>
					<?php endforeach; ?>
				<?php endif; ?>
				<li><?php esc_html_e( '最低利用期間 1年契約（12ヶ月）', 'apprex' ); ?></li>
			</ul>
			<?php if ( ! empty( $p['note'] ) ) : ?>
				<p class="price-note"><?php echo esc_html( $p['note'] ); ?></p>
			<?php endif; ?>
			<a class="btn <?php echo $is_feat ? 'btn--cta' : 'btn--ghost'; ?>" href="<?php echo esc_url( $apprex_est ); ?>"><?php esc_html_e( '見積もり・申込', 'apprex' ); ?></a>
		</div>
		<?php
	}
	echo '</div>';

	if ( ! empty( $svc['options'] ) ) {
		echo '<p class="plan-note"><strong>オプション（一回）</strong>：';
		$opts = array();
		foreach ( $svc['options'] as $o ) {
			$opts[] = esc_html( $o['label'] ) . ' ' . number_format( $o['price'] ) . '円';
		}
		echo wp_kses_post( implode( '／', $opts ) ) . '</p>';
	}
	if ( ! empty( $svc['quote_options'] ) ) {
		echo '<p class="plan-note"><strong>追加項目（すべて別途見積もり）</strong>：' . esc_html( implode( '／', $svc['quote_options'] ) ) . '</p>';
	}
};
?>

<h3 class="plan-group-title"><?php esc_html_e( 'アプリ開発プラン（月額制・開発費0円）', 'apprex' ); ?></h3>
<p class="plan-note"><?php esc_html_e( '初期設定費は今月末まで限定キャンペーン（先着10社）で0円。30日間の管理画面体験は無料。ダウンロード数課金なし・プッシュ通知無制限。', 'apprex' ); ?></p>
<?php $apprex_render_service( $apprex_cfg['services']['app'], 'start' ); ?>
<p class="plan-note"><?php esc_html_e( '※ アプリ登録時の費用（iOS・Android 一律 55,000円）が別途必要です。', 'apprex' ); ?></p>

<h3 class="plan-group-title"><?php esc_html_e( 'ホームページ制作（初期費用0円・月額制）', 'apprex' ); ?></h3>
<?php $apprex_render_service( $apprex_cfg['services']['hp'], 'standard' ); ?>

<p class="plan-note"><?php esc_html_e( '※ 価格はすべて税抜表示です。マッチングアプリ等の上位プランは個別見積（要相談）。', 'apprex' ); ?>
	<a href="<?php echo esc_url( $apprex_contact ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a>
</p>
