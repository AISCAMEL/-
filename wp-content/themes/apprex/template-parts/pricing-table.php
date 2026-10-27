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
	// 金額は、いちばん安いプランの「〜」だけ見せる。ほかはお見積り。
	$apprex_first = true;
	foreach ( $svc['plans'] as $key => $p ) {
		$apprex_show  = $apprex_first;
		$apprex_first = false;
		$is_feat   = ( $key === $featured );
		$initial_r = (int) ( $p['initial'] ?? 0 );
		$initial   = apprex_campaign_initial( $initial_r );
		?>
		<div class="price-card<?php echo $is_feat ? ' price-card--featured' : ''; ?> is-reveal">
			<?php if ( $is_feat ) : ?><span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span><?php endif; ?>
			<h3><?php echo esc_html( $p['label'] ); ?></h3>
			<div class="price">
				<?php if ( $apprex_show ) : ?>
					<small><?php esc_html_e( '月額', 'apprex' ); ?></small><?php echo esc_html( number_format( $p['monthly'] ) ); ?><small><?php esc_html_e( '円〜（税抜）', 'apprex' ); ?></small>
				<?php else : ?>
					<?php echo apprex_ask_pill( 'このプランを無料で見積り' ); // phpcs:ignore ?>
				<?php endif; ?>
			</div>
			<ul class="feats">
				<?php if ( empty( $svc['setup'] ) ) : ?><li><?php esc_html_e( '開発費用 0円', 'apprex' ); ?></li><?php endif; ?>
				<?php if ( ! empty( $svc['setup'] ) ) : ?>
					<li><?php esc_html_e( 'デザイン・仕様書づくりはAIスタジオで無料', 'apprex' ); ?></li>
					<li><?php esc_html_e( '初期費用はご要望を伺ってお見積り', 'apprex' ); ?></li>
				<?php else : ?>
					<li><?php esc_html_e( '初期費用', 'apprex' ); ?> <strong><?php echo esc_html( number_format( $initial ) ); ?>円</strong></li>
				<?php endif; ?>
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
			$opts[] = esc_html( $o['label'] );
		}
		echo wp_kses_post( implode( '／', $opts ) ) . '（お見積り）</p>';
	}
	if ( ! empty( $svc['quote_options'] ) ) {
		echo '<p class="plan-note"><strong>追加項目（すべて別途見積もり）</strong>：' . esc_html( implode( '／', $svc['quote_options'] ) ) . '</p>';
	}
};
?>

<h3 class="plan-group-title"><?php esc_html_e( 'アプリ開発プラン（月額制・AIスタジオから制作）', 'apprex' ); ?></h3>
<p class="plan-note"><?php esc_html_e( 'AIスタジオ（デザイン・試作・仕様定義書）は無料。月額19,800円〜。初期費用は、AIスタジオで作った内容をもとにお見積りします。ダウンロード数課金なし・プッシュ通知無制限。', 'apprex' ); ?></p>
<?php $apprex_render_service( $apprex_cfg['services']['app'], 'start' ); ?>
<div class="setup-compare">
	<div class="setup-card is-feat">
		<span class="setup-badge"><?php esc_html_e( 'おすすめ・初期費用がおトク', 'apprex' ); ?></span>
		<h4><?php esc_html_e( 'AIスタジオで作った内容から制作', 'apprex' ); ?></h4>
		<ul>
			<li><?php esc_html_e( 'デザイン・仕様書づくりは0円（AIスタジオ）', 'apprex' ); ?></li>
			<li><?php esc_html_e( '画面を見ながら決めるので、打ち合わせが少なく済む', 'apprex' ); ?></li>
			<li><?php esc_html_e( 'そのまま見積り依頼まで、ボタンひとつ', 'apprex' ); ?></li>
		</ul>
		<a class="btn btn--cta btn--block" href="<?php echo esc_url( function_exists( 'apprex_studio_url' ) ? apprex_studio_url() : $apprex_est ); ?>"><?php esc_html_e( 'AIスタジオで無料で作る', 'apprex' ); ?></a>
	</div>
	<div class="setup-card">
		<h4><?php esc_html_e( '1からヒアリングして制作', 'apprex' ); ?></h4>
		<ul>
			<li><?php esc_html_e( '打ち合わせで、画面と機能を1から決めます', 'apprex' ); ?></li>
			<li><?php esc_html_e( 'アイデアがまとまっていなくても大丈夫', 'apprex' ); ?></li>
			<li><?php esc_html_e( '初期設定・サーバー設定・ストア申請まで対応', 'apprex' ); ?></li>
		</ul>
		<a class="btn btn--ghost btn--block" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( 'オンラインで相談する', 'apprex' ); ?></a>
	</div>
</div>
<p class="plan-note"><?php esc_html_e( '※ 初期費用・アプリストアの登録費用は、作り方と内容に合わせて無料でお見積りします。', 'apprex' ); ?></p>

<h3 class="plan-group-title"><?php esc_html_e( 'ホームページ制作（初期費用0円・月額制）', 'apprex' ); ?></h3>
<?php $apprex_render_service( $apprex_cfg['services']['hp'], 'standard' ); ?>

<h3 class="plan-group-title"><?php esc_html_e( 'ホームページ制作 比較表', 'apprex' ); ?></h3>
<div class="compare-wrap">
	<table class="compare-table">
		<thead>
			<tr>
				<th><?php esc_html_e( '項目', 'apprex' ); ?></th>
				<th><?php esc_html_e( 'Light', 'apprex' ); ?></th>
				<th class="is-feat"><?php esc_html_e( 'Standard', 'apprex' ); ?></th>
				<th><?php esc_html_e( 'Premium', 'apprex' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$apprex_hp_rows = array(
				array( '初期費用', '0円', '0円', '0円' ),
				array( 'ページ数', '〜5ページ', '〜10ページ＋LP1本', '〜20ページ' ),
				array( '更新サポート', '月1回', '月2回', '月3回' ),
				array( 'LP制作', '—', '◯（1本込み）', '◯（複数対応）' ),
				array( 'お問い合わせフォーム', '◯', '◯', '◯' ),
				array( '予約・問い合わせ強化', '—', '◯', '◯' ),
				array( 'EC・会員機能', '—', '—', '◯' ),
				array( '構造化データ（SEO）', '基本', '◯', '◯' ),
				array( '優先サポート', '—', '—', '◯' ),
				array( '最低利用期間', '1年契約', '1年契約', '1年契約' ),
			);
			foreach ( $apprex_hp_rows as $r ) :
				?>
				<tr>
					<th><?php echo esc_html( $r[0] ); ?></th>
					<td><?php echo esc_html( $r[1] ); ?></td>
					<td class="is-feat"><?php echo esc_html( $r[2] ); ?></td>
					<td><?php echo esc_html( $r[3] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<p class="plan-note"><?php esc_html_e( '※ 価格は税抜です。上位プラン・マッチングアプリなどは、ご要望を伺ってお見積りします。', 'apprex' ); ?>
	<a href="<?php echo esc_url( $apprex_contact ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a>
</p>
