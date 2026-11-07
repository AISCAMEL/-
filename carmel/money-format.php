<?php
/**
 * カーメル：金額入力のコンマ自動化
 * ---------------------------------------------------------------------------
 * 目的 : 在庫編集画面で金額を「50000」や全角「５００００」で打っても
 *        自動で「50,000」に整形する。全角数字→半角化＋3桁カンマ。
 *
 * 対象 : STEP UI 内の「円」が付く金額入力／会員ローン系の金額テキスト欄。
 *        ※ 計算用の数値フィールド（type=number）はそのまま（計算に影響させない）。
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_money_format' );
add_action( 'admin_footer-post-new.php', 'carmel_money_format' );

function carmel_money_format() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<script>
	(function ($) {
		'use strict';

		// コンマ整形する ACF 金額テキスト欄（data-name）
		var MONEY_FIELDS = [ 'kariire_gaku', 'monthly_pay', 'loan_amount', 'monthly_payment' ];

		// 全角→半角、全角カンマ→半角
		function z2h( s ) {
			return String( s == null ? '' : s )
				.replace( /[０-９]/g, function ( c ) { return String.fromCharCode( c.charCodeAt( 0 ) - 0xFEE0 ); } )
				.replace( /[，]/g, ',' );
		}
		function digits( s ) { return z2h( s ).replace( /[^0-9]/g, '' ); }
		function withCommas( s ) {
			var d = digits( s );
			return d ? Number( d ).toLocaleString( 'en-US' ) : '';
		}

		function attach( inp ) {
			var $i = $( inp );
			if ( ! $i.length || $i.data( 'cmf' ) ) { return; }
			$i.data( 'cmf', 1 );
			$i.on( 'input', function () { this.value = withCommas( this.value ); } );
			$i.on( 'blur',  function () { this.value = withCommas( this.value ); } );
			if ( $i.val() ) { $i.val( withCommas( $i.val() ) ); }
		}

		$( function () {
			// ACF 金額テキスト欄
			MONEY_FIELDS.forEach( function ( dn ) {
				$( '.acf-field[data-name="' + dn + '"]' ).find( 'input[type="text"]' ).each( function () { attach( this ); } );
			} );

			// STEP UI 内：「円」が近くにある金額入力（万円含む）
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ui ) {
				$( ui ).find( 'input[type="text"]' ).each( function () {
					if ( /円/.test( $( this ).parent().text() ) ) { attach( this ); }
				} );
			}
		} );

	})( jQuery );
	</script>
	<?php
}
