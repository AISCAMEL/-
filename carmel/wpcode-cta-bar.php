<?php
/**
 * カーメル：スマホ追従お問い合わせバー（電話／LINE）＋お得感の価格強調
 * ---------------------------------------------------------------------------
 * 競合中古車店風のコンバージョン導線。車両詳細(portfolio)ページの画面下に、
 * 「電話で問い合わせ」「LINEで相談」を固定表示（スマホ）。
 * 電話番号・LINEリンクは各車のメタ（tel / line-link）を自動使用。
 *
 * 導入 : WPCode → 新規スニペット → PHP Snippet → <?php 以降を貼り付け
 *        → Auto Insert / Run Everywhere → 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_cta_bar' ) ) {

	function carmelx_cta_bar() {
		if ( ! is_singular( 'portfolio' ) ) { return; }
		$pid  = get_the_ID();
		$tel  = trim( (string) get_post_meta( $pid, 'tel', true ) );
		$line = trim( (string) get_post_meta( $pid, 'line-link', true ) );

		// 店舗未設定時の予備（諸経費設定の店舗TEL）
		if ( '' === $tel && function_exists( 'carmel_get_fee_settings' ) ) {
			$fee = carmel_get_fee_settings();
			if ( ! empty( $fee['shop']['tel'] ) ) { $tel = $fee['shop']['tel']; }
		}
		if ( '' === $tel && '' === $line ) { return; }

		echo '<div class="carmel-cta-bar">';
		if ( '' !== $tel ) {
			$teln = preg_replace( '/[^0-9+]/', '', $tel );
			echo '<a class="carmel-cta-bar__btn carmel-cta-bar__tel" href="tel:' . esc_attr( $teln ) . '"><span class="carmel-cta-bar__ico">📞</span><span>電話</span></a>';
		}
		if ( '' !== $line ) {
			echo '<a class="carmel-cta-bar__btn carmel-cta-bar__line" href="' . esc_url( $line ) . '" target="_blank" rel="noopener"><span class="carmel-cta-bar__ico">💬</span><span>LINE相談</span></a>';
		}
		echo '<a class="carmel-cta-bar__btn carmel-cta-bar__shinsa" href="https://carmelonline.jp/?p=7348" target="_blank" rel="noopener"><span class="carmel-cta-bar__ico">📋</span><span>審査申込（無料）</span></a>';
		echo '</div>';
	}
	add_action( 'wp_footer', 'carmelx_cta_bar' );

	add_action( 'wp_head', function () {
		echo '<style>
		.carmel-cta-bar{position:fixed;left:0;right:0;bottom:0;z-index:99990;display:flex;box-shadow:0 -2px 14px rgba(0,0,0,.18);font-family:inherit;}
		.carmel-cta-bar__btn{flex:1;display:flex;align-items:center;justify-content:center;gap:7px;padding:13px 6px;font-size:15px;font-weight:800;color:#fff !important;text-decoration:none !important;letter-spacing:.02em;line-height:1.2;}
		.carmel-cta-bar__btn:active{filter:brightness(.95);}
		.carmel-cta-bar__tel{background:#f5a623;}
		.carmel-cta-bar__line{background:#06c755;}
		.carmel-cta-bar__shinsa{background:#e8500a;}
		.carmel-cta-bar__ico{font-size:18px;}
		@media(min-width:783px){.carmel-cta-bar{display:none;}}
		@media(max-width:782px){body.single-portfolio{padding-bottom:64px !important;}}
		</style>';
	} );
}
