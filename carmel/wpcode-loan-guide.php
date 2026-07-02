<?php
/**
 * カーメル：月々の下に出す「ローン概算目安＋シミュレーションスライダー」
 * ショートコード [carmel_loan_guide]
 * ---------------------------------------------------------------------------
 * v2.0 変更点
 *   ・車両本体価格（est_honntai）表示を追加
 *   ・3パターン（36/60/84回）の静的表示はそのまま
 *   ・12〜120回のシミュレーションスライダーを追加（リアルタイム計算）
 *
 * 使い方 : ダイナミックテンプレートの月々の下に  [carmel_loan_guide]
 *          回数を変える : [carmel_loan_guide counts="36,60,84"]
 *
 * 導入 : WPCode → 既存スニペット「carmel_loan_guide」を本コードで上書き
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

		$honntai   = $num( 'est_honntai' );  // 車両本体価格
		$total     = $num( 'est_total' );    // 支払総額（ローン元金ベース）
		$atama     = $num( 'est_atamakin' ); // 頭金
		$nen       = $num( 'est_nenritsu' ); // 年率

		if ( $total <= 0 || ! function_exists( 'carmel_plan_monthly' ) ) { return ''; }

		$principal = max( 0, $total - $atama );

		$counts = array_filter( array_map( 'intval', explode( ',', $atts['counts'] ) ), function ( $c ) { return $c > 0; } );
		sort( $counts );

		// 3パターン静的表示
		$rows = '';
		foreach ( $counts as $c ) {
			$m = carmel_plan_monthly( $principal, $nen, $c );
			if ( $m <= 0 ) { continue; }
			$rows .= '<span class="carmel-lg__row"><b>' . (int) $c . '回</b>月々' . number_format( $m ) . '円</span>';
		}
		if ( '' === $rows ) { return ''; }

		$note = 'ボーナス払い無し';
		if ( $nen > 0 ) { $note .= '・実質年率' . rtrim( rtrim( number_format( $nen, 1 ), '0' ), '.' ) . '%'; }

		// スライダー初期値はパターンの中央回数
		$slider_counts = array_values( $counts );
		$default_count = $slider_counts[ floor( count( $slider_counts ) / 2 ) ] ?? 60;

		$uid = 'clg-' . (int) $pid;

		ob_start();
		?>
		<div class="carmel-lg" id="<?php echo esc_attr( $uid ); ?>">

			<?php if ( $honntai > 0 ) : ?>
			<div class="carmel-lg__price">
				<span class="carmel-lg__price-label">車両本体価格</span>
				<span class="carmel-lg__price-val"><?php echo number_format( $honntai / 10000, 1 ); ?><small>万円</small></span>
			</div>
			<?php endif; ?>

			<div class="carmel-lg__t">ローン概算目安（頭金<?php echo number_format( $atama ); ?>円）</div>
			<div class="carmel-lg__rows"><?php echo $rows; ?></div>
			<div class="carmel-lg__note">※<?php echo esc_html( $note ); ?>の目安です</div>

			<!-- シミュレーションスライダー (12〜120回) -->
			<div class="carmel-lg__sim">
				<div class="carmel-lg__sim-head">
					<span class="carmel-lg__sim-title">シミュレーション</span>
					<span class="carmel-lg__sim-count"><b id="<?php echo esc_attr( $uid ); ?>-count"><?php echo (int) $default_count; ?></b>回払い</span>
				</div>
				<input type="range" class="carmel-lg__slider" id="<?php echo esc_attr( $uid ); ?>-slider"
					min="12" max="120" step="6" value="<?php echo (int) $default_count; ?>">
				<div class="carmel-lg__sim-row">
					<span class="carmel-lg__sim-lab">12回</span>
					<span class="carmel-lg__sim-result">月々 <b id="<?php echo esc_attr( $uid ); ?>-amount">—</b>円</span>
					<span class="carmel-lg__sim-lab">120回</span>
				</div>
			</div>
		</div>
		<script>
		(function(){
			var uid  = <?php echo wp_json_encode( $uid ); ?>;
			var prin = <?php echo (float) $principal; ?>;
			var nen  = <?php echo (float) $nen; ?>;

			function calc(count) {
				if ( prin <= 0 || count <= 0 ) { return 0; }
				var m;
				if ( nen > 0 ) {
					var r = nen / 100 / 12;
					m = prin * r / ( 1 - Math.pow( 1 + r, -count ) );
				} else {
					m = prin / count;
				}
				return Math.ceil( m / 100 ) * 100;
			}

			var slider  = document.getElementById( uid + '-slider' );
			var countEl = document.getElementById( uid + '-count' );
			var amtEl   = document.getElementById( uid + '-amount' );

			function updateTrack() {
				var min = parseInt( slider.min, 10 );
				var max = parseInt( slider.max, 10 );
				var val = parseInt( slider.value, 10 );
				var pct = ( val - min ) / ( max - min ) * 100;
				slider.style.background = 'linear-gradient(to right,#2cac44 ' + pct + '%,#ddd ' + pct + '%)';
			}
			function update() {
				var c = parseInt( slider.value, 10 );
				countEl.textContent = c;
				var m = calc( c );
				amtEl.textContent = m > 0 ? m.toLocaleString( 'ja-JP' ) : '—';
				updateTrack();
			}
			slider.addEventListener( 'input', update );
			update();
		})();
		</script>
		<?php
		return ob_get_clean();
	}
	add_shortcode( 'carmel_loan_guide', 'carmelx_loan_guide_shortcode' );

	add_action( 'wp_head', function () {
		static $done = false;
		if ( $done ) { return; }
		$done = true;
		echo '<style>
		.carmel-lg{margin:8px 0 4px;padding:12px 14px;background:#f3faf5;border:1px solid #cfe7d8;border-radius:10px;font-family:inherit;}
		/* 車両本体価格 */
		.carmel-lg__price{display:flex;align-items:baseline;gap:8px;margin-bottom:10px;}
		.carmel-lg__price-label{font-size:12px;font-weight:700;color:#1c7a3a;background:#fff;border:1px solid #cfe7d8;border-radius:6px;padding:2px 8px;white-space:nowrap;}
		.carmel-lg__price-val{font-size:24px;font-weight:900;color:#333;line-height:1;}
		.carmel-lg__price-val small{font-size:14px;font-weight:700;margin-left:2px;color:#555;}
		/* 3パターン */
		.carmel-lg__t{font-size:12px;font-weight:700;color:#1c7a3a;margin-bottom:6px;}
		.carmel-lg__rows{display:flex;flex-wrap:wrap;gap:6px 8px;}
		.carmel-lg__row{display:inline-flex;align-items:baseline;gap:4px;background:#fff;border:1px solid #e1efe6;border-radius:7px;padding:5px 10px;font-size:13px;color:#333;}
		.carmel-lg__row b{color:#2cac44;font-weight:800;margin-right:2px;}
		.carmel-lg__note{margin-top:6px;font-size:11px;color:#8a8f96;}
		/* スライダー */
		.carmel-lg__sim{margin-top:12px;padding-top:10px;border-top:1px solid #d8eddf;}
		.carmel-lg__sim-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px;}
		.carmel-lg__sim-title{font-size:12px;font-weight:700;color:#1c7a3a;}
		.carmel-lg__sim-count{font-size:12px;color:#555;}
		.carmel-lg__sim-count b{font-size:17px;color:#333;font-weight:900;}
		.carmel-lg__slider{-webkit-appearance:none!important;appearance:none!important;display:block!important;width:100%!important;height:6px!important;border-radius:3px!important;background:#ddd!important;cursor:pointer!important;margin:6px 0 10px!important;outline:none!important;border:none!important;padding:0!important;box-shadow:none!important;}
		.carmel-lg__slider::-webkit-slider-runnable-track{height:6px;border-radius:3px;background:inherit;}
		.carmel-lg__slider::-moz-range-track{height:6px;border-radius:3px;background:#ddd;border:none;}
		.carmel-lg__slider::-webkit-slider-thumb{-webkit-appearance:none!important;width:22px!important;height:22px!important;border-radius:50%!important;background:#2cac44!important;cursor:pointer!important;border:2px solid #fff!important;box-shadow:0 1px 5px rgba(0,0,0,.3)!important;margin-top:-8px!important;}
		.carmel-lg__slider::-moz-range-thumb{width:22px;height:22px;border-radius:50%;background:#2cac44;cursor:pointer;border:2px solid #fff;box-shadow:0 1px 5px rgba(0,0,0,.3);}
		.carmel-lg__sim-row{display:flex;justify-content:space-between;align-items:center;margin-top:4px;}
		.carmel-lg__sim-lab{font-size:11px;color:#8a8f96;}
		.carmel-lg__sim-result{font-size:14px;font-weight:700;color:#333;text-align:center;}
		.carmel-lg__sim-result b{font-size:20px;color:#2cac44;font-weight:900;}
		</style>';
	} );
}
