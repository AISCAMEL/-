<?php
/**
 * カーメル：車両詳細ページ UI 改善
 * ---------------------------------------------------------------------------
 * 1) スティッキーモバイルバー（画面下に固定）: 電話 / LINE / 審査申込
 * 2) CSS整理: 空白ブロック非表示・余白調整
 *
 * 導入 : WPCode → 新規 PHP Snippet → Run Everywhere → 有効化
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---- スティッキーバー出力（フッター） ---- */
add_action( 'wp_footer', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }

	$pid       = get_the_ID();
	$line_url  = '';
	$tel_raw   = '';
	$shinsa_url = 'https://carmelonline.jp/?p=7348';

	$shop_slug = get_post_meta( $pid, 'shop', true );
	if ( $shop_slug ) {
		$shops = get_posts( array( 'post_type' => 'shop', 'name' => $shop_slug, 'posts_per_page' => 1, 'suppress_filters' => true ) );
		if ( $shops ) {
			$sid      = $shops[0]->ID;
			$line_url = get_post_meta( $sid, 'line_link', true ) ?: get_post_meta( $sid, 'line-link', true );
			$tel_raw  = get_post_meta( $sid, 'tel', true ) ?: get_post_meta( $sid, 'phone', true ) ?: get_post_meta( $sid, 'denwa', true );
		}
	}

	$tel_num = $tel_raw ? preg_replace( '/[^0-9]/', '', $tel_raw ) : '';
	?>
	<div class="cml-sticky" id="cml-sticky" aria-label="お問い合わせ">
		<?php if ( $tel_num ) : ?>
		<a href="tel:<?php echo esc_attr( $tel_num ); ?>" class="cml-sticky__btn cml-sticky__btn--tel">
			<span class="cml-sticky__icon">📞</span><span class="cml-sticky__label">電話</span>
		</a>
		<?php endif; ?>

		<?php if ( $line_url ) : ?>
		<a href="<?php echo esc_url( $line_url ); ?>" target="_blank" rel="noopener" class="cml-sticky__btn cml-sticky__btn--line">
			<span class="cml-sticky__icon">💬</span><span class="cml-sticky__label">LINE相談</span>
		</a>
		<?php endif; ?>

		<a href="<?php echo esc_url( $shinsa_url ); ?>" target="_blank" rel="noopener" class="cml-sticky__btn cml-sticky__btn--shinsa">
			<span class="cml-sticky__icon">📋</span><span class="cml-sticky__label">審査申込（無料）</span>
		</a>
	</div>
	<?php
} );

/* ---- CSS ---- */
add_action( 'wp_head', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }
	?>
	<style>
	/* ========== スティッキーバー ========== */
	.cml-sticky {
		display: none; /* JS で表示 */
		position: fixed;
		bottom: 0; left: 0; right: 0;
		z-index: 9999;
		display: flex;
		gap: 0;
		background: #fff;
		border-top: 2px solid #2cac44;
		box-shadow: 0 -2px 12px rgba(0,0,0,.13);
	}
	.cml-sticky__btn {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 10px 4px;
		font-size: 11px;
		font-weight: 800;
		text-decoration: none;
		gap: 3px;
		cursor: pointer;
		transition: opacity .15s;
	}
	.cml-sticky__btn:hover { opacity: .85; }
	.cml-sticky__icon { font-size: 18px; line-height: 1; }
	.cml-sticky__label { line-height: 1.2; }
	.cml-sticky__btn--tel   { background: #fff;    color: #2cac44 !important; border-right: 1px solid #e5f2e8; }
	.cml-sticky__btn--line  { background: #06c755; color: #fff    !important; }
	.cml-sticky__btn--shinsa{ background: #e8500a; color: #fff    !important; }

	/* スティッキーバー分だけページ下部に余白 */
	body.single-portfolio { padding-bottom: 72px !important; }

	/* ========== 空白ブロック非表示 ========== */
	/* タイトル直下の中身が空の枠 */
	.entry-content > p:empty,
	.entry-content > div:empty,
	.wp-block-group:empty,
	.elementor-widget-container:empty { display: none !important; }

	/* ========== 全体余白・タイポグラフィ調整 ========== */
	/* ローン概算ボックス上下の余白 */
	.carmel-lg { margin: 10px 0 6px !important; }

	/* 基本情報・装備セクションのヘッダーアイコン統一 */
	/* (テーマ側要素のため参考。実際のクラスに合わせて調整) */

	/* デスクトップ: スティッキーバーは非表示 */
	@media ( min-width: 769px ) {
		.cml-sticky { display: none !important; }
		body.single-portfolio { padding-bottom: 0 !important; }
	}
	</style>
	<?php
} );
