<?php
/**
 * カーメル：月々の下に出す「ローン概算目安」ショートコード [carmel_loan_guide]
 * ---------------------------------------------------------------------------
 * 見積もり(est_*)から、回数別の月々をコンパクトに表示する小さな目安ボックス。
 * 月々のお支払い（total）のすぐ下に置く用途。
 *
 * 使い方 : ダイナミックテンプレートの月々の下に  [carmel_loan_guide]
 *          回数を変える : [carmel_loan_guide counts="36,60,84"]
 *
 * 導入 : WPCode →「+ スニペットを追加」→ PHP Snippet → <?php 以降を貼り付け
 *        → Auto Insert / Run Everywhere → 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_loan_guide_shortcode' ) ) {

	function carmelx_loan_guide_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'counts' => '36,60,84' ), $atts, 'carmel_loan_guide' );
		$pid  = $atts['id'] ? (int) $atts['id'] : get_the_ID();
		if ( ! $pid ) { return ''; }

		$num = function ( $k ) use ( $pid ) {
			$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : '';
			if ( null === $v || '' === $v || false === $v ) { $v = get_post_meta( $pid, $k, true ); }
			return (float) preg_replace( '/[^0-9.]/', '', (string) $v );
		};

		$total = $num( 'est_total' );
		$atama = $num( 'est_atamakin' );
		$nen   = $num( 'est_nenritsu' );
		if ( $total <= 0 || ! function_exists( 'carmel_plan_monthly' ) ) { return ''; }

		$principal = max( 0, $total - $atama );
		$counts = array_filter( array_map( 'intval', explode( ',', $atts['counts'] ) ), function ( $c ) { return $c > 0; } );
		sort( $counts );

		$rows = '';
		foreach ( $counts as $c ) {
			$m = carmel_plan_monthly( $principal, $nen, $c );
			if ( $m <= 0 ) { continue; }
			$rows .= '<span class="carmel-lg__row"><b>' . (int) $c . '回</b>月々' . number_format( $m ) . '円</span>';
		}
		if ( '' === $rows ) { return ''; }

		$note = 'ボーナス払い無し';
		if ( $nen > 0 ) { $note .= '・実質年率' . rtrim( rtrim( number_format( $nen, 1 ), '0' ), '.' ) . '%'; }

		return '<div class="carmel-lg">'
			. '<div class="carmel-lg__t">ローン概算目安（頭金' . number_format( $atama ) . '円）</div>'
			. '<div class="carmel-lg__rows">' . $rows . '</div>'
			. '<div class="carmel-lg__note">※' . esc_html( $note ) . 'の目安です</div>'
			. '</div>';
	}
	add_shortcode( 'carmel_loan_guide', 'carmelx_loan_guide_shortcode' );

	add_action( 'wp_head', function () {
		echo '<style>
		.carmel-lg{margin:8px 0 4px;padding:10px 12px;background:#f3faf5;border:1px solid #cfe7d8;border-radius:10px;font-family:inherit;}
		.carmel-lg__t{font-size:12px;font-weight:700;color:#1c7a3a;margin-bottom:6px;}
		.carmel-lg__rows{display:flex;flex-wrap:wrap;gap:6px 8px;}
		.carmel-lg__row{display:inline-flex;align-items:baseline;gap:4px;background:#fff;border:1px solid #e1efe6;border-radius:7px;padding:4px 9px;font-size:12.5px;color:#333;}
		.carmel-lg__row b{color:#2cac44;font-weight:800;}
		.carmel-lg__note{margin-top:6px;font-size:11px;color:#8a8f96;}
		</style>';
	} );
}
