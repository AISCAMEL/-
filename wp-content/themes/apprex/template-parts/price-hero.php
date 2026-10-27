<?php
/**
 * 料金の目安（見やすく・問い合わせにつながる形）。
 *
 * 主な料金をひと目でわかるように並べ、あなたの場合の金額は見積り依頼・AIスタジオ・LINEへ。
 * 料金ページの先頭などで get_template_part( 'template-parts/price-hero' ) で使う。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_ph_est  = apprex_page_url( 'estimate' );
$apprex_ph_meet = apprex_page_url( 'meeting' );
$apprex_ph_line = function_exists( 'apprex_line_url' ) ? apprex_line_url() : '';
$apprex_ph_app  = function_exists( 'apprex_studio_url' ) ? apprex_studio_url() : $apprex_ph_est;
$apprex_ph_hp   = function_exists( 'apprex_studio_url' ) ? apprex_studio_url( 'hp' ) : $apprex_ph_est;
$apprex_ph_tiles = array(
	array(
		'tag'   => 'アプリ（ノーコード）',
		'num'   => '19,800',
		'unit'  => '円〜／月',
		'badge' => '初期費用 15万円〜（AIスタジオから）',
		'pts'   => array( 'デザイン・仕様書はAIスタジオで0円', 'プッシュ通知は無制限・最短2週間で公開', '1からヒアリングして作る場合は初期費用30万円〜' ),
		'plans' => array( array( 'トライアル', '19,800円' ), array( 'スタート', '39,800円' ), array( 'ビジネス', '59,800円' ) ),
		'btn'   => array( 'AIスタジオで無料で作る', $apprex_ph_app ),
		'feat'  => true,
	),
	array(
		'tag'   => 'ホームページ',
		'num'   => '9,800',
		'unit'  => '円〜／月',
		'badge' => '初期費用0円（AIプランナーで制作費0円）',
		'pts'   => array( 'ページ構成と文章をAIが提案', 'スマホ対応・更新サポートつき', '月々の費用だけで持てる' ),
		'plans' => array( array( 'Light', '9,800円' ), array( 'Standard', '19,800円' ), array( 'Premium', '39,800円' ) ),
		'btn'   => array( 'AIプランナーで作る', $apprex_ph_hp ),
		'feat'  => false,
	),
	array(
		'tag'   => 'マッチングアプリ（オリジナル開発）',
		'num'   => '300',
		'unit'  => '万円〜（開発費）',
		'badge' => '月額保守 7万円〜・3ヶ月〜',
		'pts'   => array( 'iOS・Android同時開発・管理画面つき', 'APPREXの開発基盤で費用と期間を抑える', 'その他のオリジナル開発は個別にお見積り' ),
		'plans' => array(),
		'btn'   => array( '見積りを依頼する', $apprex_ph_est ),
		'feat'  => false,
	),
);
?>
<section class="section ph-sec" id="price-summary">
	<div class="container">
		<?php apprex_section_head( 'Price', __( '料金の目安', 'apprex' ), __( '主な料金をまとめました（税抜）。デザインと仕様書はAIスタジオで無料。あなたの場合の金額は、無料でお見積りします。', 'apprex' ) ); ?>
		<div class="ph-tiles">
			<?php foreach ( $apprex_ph_tiles as $t ) : ?>
				<div class="ph-tile<?php echo $t['feat'] ? ' is-feat' : ''; ?> is-reveal">
					<span class="ph-tag"><?php echo esc_html( $t['tag'] ); ?></span>
					<div class="ph-price"><b><?php echo esc_html( $t['num'] ); ?></b><span><?php echo esc_html( $t['unit'] ); ?></span></div>
					<span class="ph-badge"><?php echo esc_html( $t['badge'] ); ?></span>
					<?php if ( $t['plans'] ) : ?>
						<dl class="ph-plans">
							<?php foreach ( $t['plans'] as $pl ) : ?>
								<div><dt><?php echo esc_html( $pl[0] ); ?></dt><dd><?php echo esc_html( $pl[1] ); ?><small>／月</small></dd></div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
					<ul>
						<?php foreach ( $t['pts'] as $pt ) : ?>
							<li><?php echo esc_html( $pt ); ?></li>
						<?php endforeach; ?>
					</ul>
					<a class="btn <?php echo $t['feat'] ? 'btn--cta' : 'btn--ghost'; ?> btn--block" href="<?php echo esc_url( $t['btn'][1] ); ?>"><?php echo esc_html( $t['btn'][0] ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
		<ul class="ph-trust">
			<li><?php esc_html_e( 'お見積り・ご相談は無料', 'apprex' ); ?></li>
			<li><?php esc_html_e( '担当者から2営業日以内にお返事', 'apprex' ); ?></li>
			<li><?php esc_html_e( '必要なものだけをご提案', 'apprex' ); ?></li>
			<li><?php esc_html_e( '補助金のご相談もOK', 'apprex' ); ?></li>
		</ul>
		<div class="ph-ask">
			<div class="ph-ask-t">
				<b><?php esc_html_e( '「うちの場合はいくら？」', 'apprex' ); ?></b>
				<span><?php esc_html_e( '業種とやりたいことを送るだけ。担当者が、あなたに合うプランと金額をお送りします。', 'apprex' ); ?></span>
			</div>
			<div class="ph-ask-b">
				<a class="btn btn--cta" href="<?php echo esc_url( $apprex_ph_est ); ?>"><?php esc_html_e( '無料で見積りを受け取る', 'apprex' ); ?></a>
				<?php if ( $apprex_ph_line ) : ?>
					<a class="btn btn--line" href="<?php echo esc_url( $apprex_ph_line ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LINEで聞いてみる', 'apprex' ); ?></a>
				<?php endif; ?>
				<a class="btn ph-ghost" href="<?php echo esc_url( $apprex_ph_meet ); ?>"><?php esc_html_e( 'オンラインで相談', 'apprex' ); ?></a>
			</div>
		</div>
	</div>
</section>
