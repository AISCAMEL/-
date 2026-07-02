<?php
/**
 * カーメル：月々の下に出す「ローン概算目安＋シミュレーションスライダー」
 * ショートコード [carmel_loan_guide]
 * ---------------------------------------------------------------------------
 * v3.0 変更点
 *   ・CTA ボタン（審査申込・LINE）を削除
 *   ・シミュレーション表示を改善（月額を中央大きく・回数バッジ）
 *   ・スマホ／PC 両対応レイアウト
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

		$honntai   = $num( 'est_honntai' );
		$total     = $num( 'est_total' );
		$atama     = $num( 'est_atamakin' );
		$nen       = $num( 'est_nenritsu' );

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

		$slider_counts = array_values( $counts );
		$default_count = $slider_counts[ floor( count( $slider_counts ) / 2 ) ] ?? 60;

		// 審査申込・LINE（店舗ACFから取得）
		$line_url   = '';
		$shinsa_url = 'https://carmelonline.jp/?p=7348';
		$shop_slug  = get_post_meta( $pid, 'shop', true );
		if ( $shop_slug ) {
			$shop_posts = get_posts( array( 'post_type' => 'shop', 'name' => $shop_slug, 'posts_per_page' => 1, 'suppress_filters' => true ) );
			if ( $shop_posts ) {
				$sid      = $shop_posts[0]->ID;
				$line_url = get_post_meta( $sid, 'line_link', true ) ?: get_post_meta( $sid, 'line-link', true );
			}
		}

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

			<!-- シミュレーションスライダー -->
			<div class="carmel-lg__sim">
				<div class="carmel-lg__sim-title">返済回数シミュレーション</div>
				<div class="carmel-lg__sim-result">
					<span class="carmel-lg__sim-badge"><span id="<?php echo esc_attr( $uid ); ?>-count"><?php echo (int) $default_count; ?></span>回払い</span>
					<div class="carmel-lg__sim-amount">月々&nbsp;<b id="<?php echo esc_attr( $uid ); ?>-amount">—</b><span class="carmel-lg__sim-en">円</span></div>
				</div>
				<div class="carmel-lg__slider-wrap">
					<span class="carmel-lg__sim-lab">12回</span>
					<input type="range" class="carmel-lg__slider" id="<?php echo esc_attr( $uid ); ?>-slider"
						min="12" max="120" step="6" value="<?php echo (int) $default_count; ?>">
					<span class="carmel-lg__sim-lab">120回</span>
				</div>
			</div>
		</div>

		<!-- 審査申込 CTA -->
		<div class="carmel-lg__cta">
			<a href="<?php echo esc_url( $shinsa_url ); ?>" target="_blank" rel="noopener" class="carmel-lg__btn carmel-lg__btn--shinsa">
				かんたん審査申込（無料）
			</a>
			<?php if ( $line_url ) : ?>
			<a href="<?php echo esc_url( $line_url ); ?>" target="_blank" rel="noopener" class="carmel-lg__btn carmel-lg__btn--line">
				LINE で相談
			</a>
			<?php endif; ?>
			<p class="carmel-lg__cta-sub">最短即日審査・秘密厳守・在籍確認なし</p>
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
		.carmel-lg{margin:8px 0 4px;padding:14px 16px;background:#f3faf5;border:1px solid #cfe7d8;border-radius:12px;font-family:inherit;box-sizing:border-box;width:100%;}
		/* 車両本体価格 */
		.carmel-lg__price{display:flex;align-items:baseline;gap:8px;margin-bottom:10px;}
		.carmel-lg__price-label{font-size:12px;font-weight:700;color:#1c7a3a;background:#fff;border:1px solid #cfe7d8;border-radius:6px;padding:2px 8px;white-space:nowrap;}
		.carmel-lg__price-val{font-size:24px;font-weight:900;color:#333;line-height:1;}
		.carmel-lg__price-val small{font-size:14px;font-weight:700;margin-left:2px;color:#555;}
		/* 3パターン */
		.carmel-lg__t{font-size:12px;font-weight:700;color:#1c7a3a;margin-bottom:6px;}
		.carmel-lg__rows{display:flex;flex-direction:column;gap:4px;}
		.carmel-lg__row{display:flex;align-items:center;background:#fff;border:1px solid #e1efe6;border-radius:7px;padding:7px 12px;font-size:13px;color:#333;white-space:nowrap;box-sizing:border-box;}
		.carmel-lg__row b{color:#2cac44;font-weight:800;min-width:3.2em;flex-shrink:0;}
		.carmel-lg__note{margin-top:6px;font-size:11px;color:#8a8f96;}
		/* シミュレーション */
		.carmel-lg__sim{margin-top:14px;padding-top:12px;border-top:1px solid #d8eddf;}
		.carmel-lg__sim-title{font-size:12px;font-weight:700;color:#1c7a3a;margin-bottom:10px;letter-spacing:.03em;}
		.carmel-lg__sim-result{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;}
		.carmel-lg__sim-badge{display:inline-flex;align-items:center;background:#2cac44;color:#fff;font-size:13px;font-weight:800;border-radius:20px;padding:4px 12px;white-space:nowrap;line-height:1.4;}
		.carmel-lg__sim-badge span{font-size:22px;font-weight:900;margin-right:2px;line-height:1;}
		.carmel-lg__sim-amount{font-size:15px;font-weight:700;color:#333;text-align:right;white-space:nowrap;}
		.carmel-lg__sim-amount b{font-size:28px;font-weight:900;color:#2cac44;line-height:1;}
		.carmel-lg__sim-en{font-size:14px;font-weight:700;color:#555;margin-left:1px;}
		/* スライダー */
		.carmel-lg__slider-wrap{display:flex;align-items:center;gap:8px;}
		.carmel-lg__sim-lab{font-size:11px;color:#8a8f96;white-space:nowrap;flex-shrink:0;}
		.carmel-lg__slider{-webkit-appearance:none!important;appearance:none!important;flex:1;display:block!important;height:8px!important;border-radius:4px!important;background:#ddd!important;cursor:pointer!important;outline:none!important;border:none!important;padding:0!important;box-shadow:none!important;margin:0!important;}
		.carmel-lg__slider::-webkit-slider-runnable-track{height:8px;border-radius:4px;background:inherit;}
		.carmel-lg__slider::-moz-range-track{height:8px;border-radius:4px;background:#ddd;border:none;}
		.carmel-lg__slider::-webkit-slider-thumb{-webkit-appearance:none!important;width:26px!important;height:26px!important;border-radius:50%!important;background:#2cac44!important;cursor:pointer!important;border:3px solid #fff!important;box-shadow:0 2px 6px rgba(0,0,0,.25)!important;margin-top:-9px!important;}
		.carmel-lg__slider::-moz-range-thumb{width:26px;height:26px;border-radius:50%;background:#2cac44;cursor:pointer;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.25);}
		/* CTA */
		.carmel-lg__cta{margin-top:8px;padding-top:0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;width:100%;box-sizing:border-box;}
		.carmel-lg__btn{display:inline-block;font-size:14px;font-weight:800;text-decoration:none;padding:13px 0;border-radius:8px;text-align:center;flex:1 1 140px;cursor:pointer;transition:opacity .15s;white-space:nowrap;}
		.carmel-lg__btn:hover{opacity:.88;}
		.carmel-lg__btn--shinsa{background:#e8500a;color:#fff!important;}
		.carmel-lg__btn--line{background:#06c755;color:#fff!important;}
		.carmel-lg__cta-sub{width:100%;margin:4px 0 0;font-size:11px;color:#8a8f96;text-align:center;}
		@media(max-width:767px){
			.carmel-lg{padding-left:12px;padding-right:12px;}
			.carmel-lg__btn{flex:1 1 100%;}
		}
		@media(max-width:480px){
			.carmel-lg{padding:12px;}
			.carmel-lg__sim-result{flex-direction:column;align-items:flex-start;gap:4px;}
			.carmel-lg__sim-amount{text-align:left;}
			.carmel-lg__sim-amount b{font-size:20px;}
			.carmel-lg__sim-badge span{font-size:19px;}
		}
		</style>';
	} );
}
