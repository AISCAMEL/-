<?php
/**
 * Plugin Name: カーメル 月々シミュレーション補完
 * Description: ローン設定（頭金0・年率9.8%・60回）が未入力の在庫に一括補完。月々表示(total)には触らず、est_atamakin/est_nenritsu/est_kaisuuのみ補完。プレビューに回数別月額シミュレーション表示。
 * Version: 2.1.0
 * Author: CARMEL
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Monthly_Backfill' ) ) {

class Carmel_Monthly_Backfill {

	const RATE_DEFAULT  = 9.8;
	const ATAMA_DEFAULT = 0;

	/* 価格キー候補 */
	private $price_keys = array( 'price', 'est_honntai', 'honntai', 'hontai', 'kakaku', 'honbai', 'price_main' );

	/* シミュレーション表示用の回数 */
	private $kaisuu_list = array( 36, 48, 60, 84 );

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'月々シミュレーション補完',
			'💴 月々補完',
			'manage_options',
			'carmel-monthly-backfill',
			array( $this, 'render' )
		);
	}

	private function num( $v ) {
		return (float) preg_replace( '/[^0-9.]/', '', (string) $v );
	}

	private function raw( $pid, $key ) {
		$v = get_post_meta( $pid, $key, true );
		return is_string( $v ) ? trim( $v ) : ( is_null( $v ) ? '' : (string) $v );
	}

	private function is_empty( $v ) {
		return ( null === $v || '' === (string) $v || false === $v );
	}

	private function find_price( $pid ) {
		foreach ( $this->price_keys as $k ) {
			$v = $this->raw( $pid, $k );
			$n = $this->num( $v );
			if ( $n > 0 ) { return array( 'key' => $k, 'raw' => $v, 'val' => $n ); }
		}
		return null;
	}

	private function save( $pid, $key, $val ) {
		if ( function_exists( 'update_field' ) ) { update_field( $key, $val, $pid ); }
		update_post_meta( $pid, $key, $val );
	}

	/* 年金計算（100円単位切り上げ） */
	private function monthly_calc( $principal, $rate, $count ) {
		if ( function_exists( 'carmel_plan_monthly' ) ) {
			return (int) carmel_plan_monthly( $principal, $rate, $count );
		}
		$principal = max( 0, (float) $principal );
		$count     = max( 1, (int) $count );
		$r = ( (float) $rate / 100 ) / 12;
		if ( $r <= 0 ) { $m = $principal / $count; }
		else {
			$p = pow( 1 + $r, $count );
			$m = $principal * ( $r * $p ) / ( $p - 1 );
		}
		return (int) ( ceil( $m / 100 ) * 100 );
	}

	private function all_ids() {
		return get_posts( array(
			'post_type'        => 'portfolio',
			'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
		) );
	}

	/**
	 * 1台の補完プラン。
	 * 補完対象は est_atamakin / est_nenritsu / est_kaisuu のみ。
	 * total（月々表示）・monthly には触らない。
	 */
	private function plan_for( $pid, $rate, $atama ) {
		$pinfo = $this->find_price( $pid );
		if ( ! $pinfo ) {
			return array( 'status' => 'noprice', 'changes' => array(), 'price' => 0 );
		}

		$changes = array();

		if ( $this->is_empty( $this->raw( $pid, 'est_atamakin' ) ) ) {
			$changes['est_atamakin'] = (string) (int) $atama;
		}
		if ( $this->is_empty( $this->raw( $pid, 'est_nenritsu' ) ) ) {
			$changes['est_nenritsu'] = (string) $rate;
		}
		if ( $this->is_empty( $this->raw( $pid, 'est_kaisuu' ) ) ) {
			$changes['est_kaisuu'] = '60';
		}

		return array(
			'status'  => empty( $changes ) ? 'ok' : 'target',
			'changes' => $changes,
			'price'   => $pinfo['val'],
			'pkey'    => $pinfo['key'],
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$rate  = self::RATE_DEFAULT;
		$atama = self::ATAMA_DEFAULT;
		if ( isset( $_POST['cmb_rate'] ) )  { $rate  = (float) $_POST['cmb_rate']; }
		if ( isset( $_POST['cmb_atama'] ) ) { $atama = (float) $_POST['cmb_atama']; }

		$did_run = false; $applied = 0;
		if ( isset( $_POST['cmb_apply'] ) && check_admin_referer( 'cmb_apply' ) ) {
			$applied = $this->run( $rate, $atama );
			$did_run = true;
		}

		$ids  = $this->all_ids();
		$cnt  = array( 'target' => 0, 'ok' => 0, 'noprice' => 0 );
		$rows = array();
		$diag_noprice = array();

		foreach ( $ids as $pid ) {
			$p = $this->plan_for( $pid, $rate, $atama );
			$cnt[ $p['status'] ]++;
			if ( 'target' === $p['status'] && count( $rows ) < 50 ) {
				$rows[] = array( 'pid' => $pid, 'plan' => $p );
			}
			if ( 'noprice' === $p['status'] && count( $diag_noprice ) < 5 ) {
				$found = array();
				foreach ( array_merge( $this->price_keys, array( 'est_total', 'total' ) ) as $k ) {
					$v = $this->raw( $pid, $k );
					if ( '' !== $v ) { $found[ $k ] = $v; }
				}
				$diag_noprice[] = array( 'pid' => $pid, 'title' => get_the_title( $pid ), 'found' => $found );
			}
		}
		?>
		<div class="wrap">
			<h1>💴 月々シミュレーション補完</h1>
			<p>
				ローン設定（頭金・年率・回数）が未入力の在庫へ一括補完します。<br>
				<strong>月々表示（total）には触りません</strong>。 est_atamakin / est_nenritsu / est_kaisuu のみ補完。<br>
				実行前に UpdraftPlus でバックアップ推奨。
			</p>

			<?php if ( $did_run ) : ?>
				<div class="notice notice-success"><p><strong><?php echo (int) $applied; ?> 台</strong>に補完しました。</p></div>
			<?php endif; ?>

			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:640px;">
				<table class="form-table" style="margin:0;">
					<tr><th style="width:140px;">実質年率 (%)</th><td><input type="number" step="0.1" name="cmb_rate" value="<?php echo esc_attr( $rate ); ?>" style="width:90px;"> %</td></tr>
					<tr><th>頭金 (円)</th><td><input type="number" step="1000" name="cmb_atama" value="<?php echo esc_attr( $atama ); ?>" style="width:130px;"> 円</td></tr>
				</table>

				<h2 style="margin-top:16px;">集計</h2>
				<table style="border-collapse:collapse;font-size:14px;line-height:2.2;">
					<tr>
						<td style="padding-right:24px;">✅ <strong>補完対象</strong></td>
						<td><strong style="font-size:22px;color:#1f6feb;"><?php echo (int) $cnt['target']; ?></strong> 台</td>
						<td style="padding-left:12px;color:#555;">est_atamakin / est_nenritsu / est_kaisuu のいずれかが未入力</td>
					</tr>
					<tr>
						<td>― 設定済み（変更なし）</td>
						<td><strong><?php echo (int) $cnt['ok']; ?></strong> 台</td>
						<td></td>
					</tr>
					<tr>
						<td>― 価格情報なし（スキップ）</td>
						<td><strong><?php echo (int) $cnt['noprice']; ?></strong> 台</td>
						<td style="padding-left:12px;color:#555;font-size:12px;">検索キー: <?php echo esc_html( implode( ', ', $this->price_keys ) ); ?></td>
					</tr>
				</table>

				<?php if ( $cnt['target'] > 0 ) : ?>
					<?php wp_nonce_field( 'cmb_apply' ); ?>
					<p style="margin-top:14px;">
						<button class="button button-primary button-hero" name="cmb_apply" value="1"
							onclick="return confirm('<?php echo (int) $cnt['target']; ?> 台に補完します。よろしいですか？');">
							今すぐ <?php echo (int) $cnt['target']; ?> 台に補完する
						</button>
					</p>
				<?php else : ?>
					<p><em>補完対象はありません。</em></p>
				<?php endif; ?>

				<p style="margin-top:10px;">
					<button class="button" name="cmb_preview" value="1">プレビューを更新</button>
				</p>
			</form>

			<?php if ( ! empty( $diag_noprice ) ) : ?>
				<details style="max-width:900px;margin-bottom:18px;">
					<summary style="cursor:pointer;color:#1f6feb;font-size:14px;">▶ 価格情報なし車両サンプル（<?php echo count( $diag_noprice ); ?> 台）</summary>
					<table class="widefat striped" style="margin-top:8px;">
						<thead><tr><th>車両</th><th>価格系フィールド（実際に保存されているもの）</th></tr></thead>
						<tbody>
						<?php foreach ( $diag_noprice as $d ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $d['pid'] ) ); ?>">#<?php echo (int) $d['pid']; ?> <?php echo esc_html( $d['title'] ?: '(無題)' ); ?></a>
									<br><a style="font-size:11px;" href="<?php echo esc_url( admin_url( 'edit.php?post_type=portfolio&page=carmel-car-inspect&car=' . $d['pid'] ) ); ?>">→ 車両データ確認</a>
								</td>
								<td>
									<?php if ( empty( $d['found'] ) ) : ?>
										<span style="color:#888;">（空）</span>
									<?php else : ?>
										<?php foreach ( $d['found'] as $k => $v ) : ?>
											<code><?php echo esc_html( $k ); ?></code> = <?php echo esc_html( (string) $v ); ?><br>
										<?php endforeach; ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</details>
			<?php endif; ?>

			<?php if ( ! empty( $rows ) ) : ?>
				<!-- プレビュー：回数別月額シミュレーション -->
				<h2>プレビュー（先頭 <?php echo count( $rows ); ?> 台）— 回数別シミュレーション</h2>
				<p style="font-size:13px;color:#555;">
					計算式：本体価格 × 年率<?php echo esc_html( $rate ); ?>% / 各回数（頭金<?php echo number_format( $atama ); ?>円）<br>
					<span style="color:#888;">※ 実際の月額は諸費用・消費税を含む支払総額から計算されます。これは目安です。</span>
				</p>
				<table class="widefat striped" style="max-width:1100px;">
					<thead>
						<tr>
							<th>車両</th>
							<th>本体価格<br><span style="font-weight:normal;font-size:11px;">（キー）</span></th>
							<?php foreach ( $this->kaisuu_list as $k ) : ?>
								<th style="text-align:center;"><?php echo (int) $k; ?>回払い<br><span style="font-weight:normal;font-size:11px;">月額目安</span></th>
							<?php endforeach; ?>
							<th>補完する項目</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $r ) :
						$pid   = $r['pid'];
						$price = $r['plan']['price'];
						$pkey  = $r['plan']['pkey'];
						$ch    = $r['plan']['changes'];
						$principal = max( 0, $price - $atama );
						$labels = array(
							'est_atamakin' => '頭金(est_atamakin)',
							'est_nenritsu' => '年率(est_nenritsu)',
							'est_kaisuu'   => '回数(est_kaisuu)',
						);
						$parts = array();
						foreach ( $ch as $k => $v ) {
							$parts[] = esc_html( ( isset( $labels[ $k ] ) ? $labels[ $k ] : $k ) . ' → ' . $v );
						}
					?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: '#' . $pid ); ?></a></td>
							<td>
								<?php echo esc_html( number_format( $price ) ); ?> 円<br>
								<span style="font-size:11px;color:#888;"><?php echo esc_html( $pkey ); ?></span>
							</td>
							<?php foreach ( $this->kaisuu_list as $k ) :
								$m = $this->monthly_calc( $principal, $rate, $k );
							?>
								<td style="text-align:center;">
									<strong style="color:#1f6feb;"><?php echo number_format( $m ); ?></strong><br>
									<span style="font-size:11px;color:#888;">円/月</span>
								</td>
							<?php endforeach; ?>
							<td style="font-size:12px;"><?php echo $parts ? implode( '<br>', $parts ) : '―'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function run( $rate, $atama ) {
		$ids     = $this->all_ids();
		$applied = 0;
		foreach ( $ids as $pid ) {
			$p = $this->plan_for( $pid, $rate, $atama );
			if ( 'target' !== $p['status'] ) { continue; }
			foreach ( $p['changes'] as $key => $val ) {
				$this->save( $pid, $key, $val );
			}
			$applied++;
		}
		return $applied;
	}
}

new Carmel_Monthly_Backfill();

}
