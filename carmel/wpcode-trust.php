<?php
/**
 * カーメル：安心ポイント帯 ショートコード [carmel_trust]
 * ---------------------------------------------------------------------------
 * 車両詳細(portfolio)ページのタイトル直下に、安心ポイントを4バッジで表示する。
 *   車検2年整備付 / 1年保証付き / 全国納車対応 / ローン審査サポート
 * 文言は属性で差し替え可：
 *   [carmel_trust items="車検2年整備付|🔧, 1年保証付き|🛡️, 全国納車対応|🚚, ローン審査サポート|💳"]
 *
 * 導入：WPCode → PHP Snippet → Run Everywhere → 有効化。
 *       詳細テンプレートのタイトル直下（画像の上）に [carmel_trust] を1行追加。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_trust_shortcode' ) ) {

	function carmelx_trust_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'items' => '車検2年整備付|🔧, 1年保証付き|🛡️, 全国納車対応|🚚, ローン審査サポート|💳',
		), $atts, 'carmel_trust' );

		$cells = '';
		foreach ( explode( ',', $atts['items'] ) as $item ) {
			$item = trim( $item );
			if ( '' === $item ) { continue; }
			$parts = array_map( 'trim', explode( '|', $item ) );
			$label = isset( $parts[0] ) ? $parts[0] : '';
			$icon  = isset( $parts[1] ) ? $parts[1] : '✔';
			if ( '' === $label ) { continue; }
			$cells .= '<div><span class="cx-trust__ic">' . esc_html( $icon ) . '</span>' . esc_html( $label ) . '</div>';
		}
		if ( '' === $cells ) { return ''; }

		return '<div class="cx-trust">' . $cells . '</div>';
	}
	add_shortcode( 'carmel_trust', 'carmelx_trust_shortcode' );

	add_action( 'wp_head', function () {
		echo '<style>
		.cx-trust{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:6px 0 16px;font-family:inherit;}
		.cx-trust div{background:#fff;border:1px solid #e3eee6;border-left:4px solid #2cac44;border-radius:8px;padding:11px 13px;font-size:13.5px;font-weight:700;color:#1b2935;display:flex;align-items:center;gap:8px;box-shadow:0 1px 4px rgba(0,0,0,.05);line-height:1.4;}
		.cx-trust__ic{font-size:19px;line-height:1;}
		@media(max-width:680px){.cx-trust{grid-template-columns:1fr 1fr;gap:8px;}.cx-trust div{font-size:12.5px;padding:9px 11px;}}
		</style>';
	} );
}
