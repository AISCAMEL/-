<?php
/**
 * Plugin Name: カーメル 月々シミュレーション補完
 * Description: 月々シミュレーションが未設定の在庫へ、頭金0・実質年率9.8%で自動計算した月額を補完します。価格キーを複数検索・プレビューで月額を確認してから実行。
 * Version: 2.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「💴 月々補完」を開く → プレビューで月額確認 → 実行。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Monthly_Backfill' ) ) {

class Carmel_Monthly_Backfill {

	const RATE_DEFAULT  = 9.8;
	const ATAMA_DEFAULT = 0;
	const COUNT_DEFAULT = 60;

	/* 価格が入っている可能性のあるキー（優先順） */
	private $price_keys = array( 'price', 'est_honntai', 'honntai', 'hontai', 'kakaku', 'honbai', 'price_main' );

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
		return is_string( $v ) ? trim( $v ) : ( is_null( $v ) ? '' : $v );
	}

	private function is_empty( $v ) {
		if ( is_array( $v ) ) { return true; }
		return ( null === $v || '' === (string) $v || false === $v );
	}

	/* 価格キーをすべて探して最初に見つかった値と使用キーを返す */
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

	/* 年金計算：100円単位切り上げ */
	private function monthly( $principal, $rate, $count ) {
		if ( function_exists( 'carmel_plan_monthly' ) ) {
			return (int) carmel_plan_monthly( $principal, $rate, $count );
		}
		$principal = max( 0, (float) $principal );
		$count     = max( 1, (int) $count );
		$r         = ( (float) $rate / 100 ) / 12;
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
	 * 1台の補完プランを返す
	 * status: 'target'(補完対象) / 'ok'(既に設定済) / 'noprice'(価格なし)
	 */
	private function plan_for( $pid, $rate, $atama, $hcount ) {
		$pinfo = $this->find_price( $pid );

		// 価格なし → スキップ
		if ( ! $pinfo ) {
			return array( 'status' => 'noprice', 'changes' => array(), 'debug' => array() );
		}

		$price    = $pinfo['val'];
		$changes  = array();
		$debug    = array( 'price_key' => $pinfo['key'], 'price_val' => $pinfo['raw'] );

		// est_total（支払総額）が空 → price を入れる
		$est_total_raw = $this->raw( $pid, 'est_total' );
		$debug['est_total'] = $est_total_raw;
		if ( $this->is_empty( $est_total_raw ) ) {
			$changes['est_total'] = (string) (int) $price;
		}
		$base = $this->num( $this->is_empty( $est_total_raw ) ? (string) $price : $est_total_raw );

		// est_atamakin（頭金）が空 → 0
		$atama_raw = $this->raw( $pid, 'est_atamakin' );
		$debug['est_atamakin'] = $atama_raw;
		if ( $this->is_empty( $atama_raw ) ) {
			$changes['est_atamakin'] = (string) (int) $atama;
		}
		$atama_eff = $this->is_empty( $atama_raw ) ? (int) $atama : $this->num( $atama_raw );

		// est_nenritsu（年率）が空 → 既定値
		$nenritsu_raw = $this->raw( $pid, 'est_nenritsu' );
		$debug['est_nenritsu'] = $nenritsu_raw;
		if ( $this->is_empty( $nenritsu_raw ) ) {
			$changes['est_nenritsu'] = (string) $rate;
		}
		$rate_eff = $this->is_empty( $nenritsu_raw ) ? (float) $rate : $this->num( $nenritsu_raw );

		// total（月々表示）と monthly が空 → 計算して入れる
		$total_raw   = $this->raw( $pid, 'total' );
		$monthly_raw = $this->raw( $pid, 'monthly' );
		$debug['total']   = $total_raw;
		$debug['monthly'] = $monthly_raw;
		$principal = max( 0, $base - $atama_eff );
		$m = $this->monthly( $principal, $rate_eff, $hcount );
		if ( $this->is_empty( $total_raw ) ) {
			$changes['total']   = $m > 0 ? number_format( $m ) : '';
		}
		if ( $this->is_empty( $monthly_raw ) ) {
			$changes['monthly'] = $m > 0 ? (string) $m : '';
		}

		$debug['principal']  = $principal;
		$debug['monthly_calc'] = $m;

		return array(
			'status'  => empty( $changes ) ? 'ok' : 'target',
			'changes' => $changes,
			'debug'   => $debug,
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$rate   = self::RATE_DEFAULT;
		$atama  = self::ATAMA_DEFAULT;
		$hcount = self::COUNT_DEFAULT;
		if ( isset( $_POST['cmb_rate'] ) )   { $rate   = (float) $_POST['cmb_rate']; }
		if ( isset( $_POST['cmb_atama'] ) )  { $atama  = (float) $_POST['cmb_atama']; }
		if ( isset( $_POST['cmb_hcount'] ) ) { $hcount = max( 1, (int) $_POST['cmb_hcount'] ); }

		$did_run = false; $applied = 0;
		if ( isset( $_POST['cmb_apply'] ) && check_admin_referer( 'cmb_apply' ) ) {
			$applied = $this->run( $rate, $atama, $hcount );
			$did_run = true;
		}

		// --- 集計 ---
		$ids  = $this->all_ids();
		$cnt  = array( 'target' => 0, 'ok' => 0, 'noprice' => 0 );
		$rows = array();
		// 詳細診断（先頭5台・どのキーに価格があるか）
		$diag_noprice = array(); // 価格なし車両サンプル
		foreach ( $ids as $pid ) {
			$p = $this->plan_for( $pid, $rate, $atama, $hcount );
			$cnt[ $p['status'] ]++;
			if ( 'target' === $p['status'] && count( $rows ) < 50 ) {
				$rows[] = array( 'pid' => $pid, 'plan' => $p );
			}
			if ( 'noprice' === $p['status'] && count( $diag_noprice ) < 5 ) {
				// 実際に保存されている価格系キーを調べる
				$found = array();
				foreach ( array_merge( $this->price_keys, array( 'est_total', 'total', 'monthly' ) ) as $k ) {
					$v = $this->raw( $pid, $k );
					if ( ! $this->is_empty( $v ) ) { $found[ $k ] = $v; }
				}
				$diag_noprice[] = array( 'pid' => $pid, 'title' => get_the_title( $pid ), 'found' => $found );
			}
		}
		?>
		<div class="wrap">
			<h1>💴 月々シミュレーション補完</h1>
			<p>価格から月々支払いを自動計算して補完します。<strong>空欄のみ補完／入力済みは上書きしません。</strong><br>実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( $did_run ) : ?>
				<div class="notice notice-success"><p><strong><?php echo (int) $applied; ?> 台</strong>に補完しました。</p></div>
			<?php endif; ?>

			<!-- 設定フォーム -->
			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:680px;">
				<table class="form-table" style="margin:0;">
					<tr>
						<th style="width:160px;">実質年率 (%)</th>
						<td><input type="number" step="0.1" name="cmb_rate" value="<?php echo esc_attr( $rate ); ?>" style="width:90px;"> %</td>
					</tr>
					<tr>
						<th>頭金 (円)</th>
						<td><input type="number" step="1000" name="cmb_atama" value="<?php echo esc_attr( $atama ); ?>" style="width:130px;"> 円</td>
					</tr>
					<tr>
						<th>回数 (total用)</th>
						<td><input type="number" name="cmb_hcount" value="<?php echo esc_attr( $hcount ); ?>" style="width:80px;"> 回</td>
					</tr>
				</table>
				<p style="margin-top:10px;">
					<button class="button" name="cmb_preview" value="1">プレビューを更新</button>
				</p>

				<!-- 集計サマリ -->
				<h2 style="margin-top:16px;">集計結果</h2>
				<table style="border-collapse:collapse;font-size:14px;line-height:2;">
					<tr>
						<td style="padding-right:24px;">✅ <strong>補完対象</strong></td>
						<td><strong style="font-size:22px;color:#1f6feb;"><?php echo (int) $cnt['target']; ?></strong> 台</td>
						<td style="padding-left:12px;color:#555;">→ 空欄のある在庫（月額自動計算・書き込み対象）</td>
					</tr>
					<tr>
						<td>― 設定済み（変更なし）</td>
						<td><strong><?php echo (int) $cnt['ok']; ?></strong> 台</td>
						<td style="padding-left:12px;color:#555;">est_total・total・monthly がすべて入力済み</td>
					</tr>
					<tr>
						<td>― 価格情報なし（スキップ）</td>
						<td><strong><?php echo (int) $cnt['noprice']; ?></strong> 台</td>
						<td style="padding-left:12px;color:#555;">
							price / est_honntai 等いずれも空
							<br><span style="font-size:12px;">検索キー: <?php echo esc_html( implode( ', ', $this->price_keys ) ); ?></span>
						</td>
					</tr>
				</table>

				<?php if ( $cnt['target'] > 0 ) : ?>
					<?php wp_nonce_field( 'cmb_apply' ); ?>
					<p style="margin-top:16px;">
						<button class="button button-primary button-hero" name="cmb_apply" value="1"
							onclick="return confirm('<?php echo (int) $cnt['target']; ?> 台に月々シミュレーションを補完します。よろしいですか？（空欄のみ・上書きなし）');">
							今すぐ <?php echo (int) $cnt['target']; ?> 台に補完する
						</button>
					</p>
				<?php elseif ( 0 === $cnt['target'] ) : ?>
					<p style="color:#888;"><em>補完対象はありません。</em></p>
				<?php endif; ?>
			</form>

			<?php if ( ! empty( $diag_noprice ) ) : ?>
				<!-- 価格なし診断 -->
				<details style="max-width:900px;margin-bottom:18px;">
					<summary style="cursor:pointer;color:#1f6feb;font-size:14px;">
						▶ 価格情報なしの車両サンプル（先頭 <?php echo count( $diag_noprice ); ?> 台）— 0台の原因を調べる場合はここを展開
					</summary>
					<table class="widefat striped" style="margin-top:8px;">
						<thead><tr><th>車両</th><th>保存されている価格系フィールド</th></tr></thead>
						<tbody>
						<?php foreach ( $diag_noprice as $d ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $d['pid'] ) ); ?>">#<?php echo (int) $d['pid']; ?> <?php echo esc_html( $d['title'] ?: '(無題)' ); ?></a>
									<br><a style="font-size:11px;" href="<?php echo esc_url( admin_url( 'edit.php?post_type=portfolio&page=carmel-car-inspect&car=' . $d['pid'] ) ); ?>">→ 車両データ確認で全項目を見る</a>
								</td>
								<td>
									<?php if ( empty( $d['found'] ) ) : ?>
										<span style="color:#888;">（価格系フィールドはすべて空）</span>
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
				<!-- プレビューテーブル -->
				<h2>プレビュー（先頭 <?php echo count( $rows ); ?> 台）— 月額計算結果</h2>
				<p style="font-size:13px;color:#555;">
					計算式：（<?php echo esc_html( $hcount ); ?>回払い・年率<?php echo esc_html( $rate ); ?>%・頭金<?php echo number_format( $atama ); ?>円）
				</p>
				<table class="widefat striped" style="max-width:1200px;">
					<thead>
						<tr>
							<th style="min-width:200px;">車両</th>
							<th>価格<br><span style="font-weight:normal;font-size:11px;">（使用キー）</span></th>
							<th style="color:#1f6feb;background:#eef4ff;">月額（<?php echo (int) $hcount; ?>回）<br><span style="font-weight:normal;font-size:11px;">計算結果</span></th>
							<th>補完する項目</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $r ) :
						$pid  = $r['pid'];
						$ch   = $r['plan']['changes'];
						$dbg  = $r['plan']['debug'];
						$monthly_display = isset( $dbg['monthly_calc'] ) ? (int) $dbg['monthly_calc'] : 0;
						$labels = array(
							'est_total'    => '支払総額(est_total)',
							'est_atamakin' => '頭金(est_atamakin)',
							'est_nenritsu' => '年率(est_nenritsu)',
							'total'        => '月々表示(total)',
							'monthly'      => 'monthly',
						);
						$parts = array();
						foreach ( $ch as $k => $v ) {
							$lab = isset( $labels[ $k ] ) ? $labels[ $k ] : $k;
							$parts[] = esc_html( $lab ) . ' → <strong>' . esc_html( $v ) . '</strong>';
						}
					?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: '#' . $pid ); ?></a>
							</td>
							<td>
								<?php if ( ! empty( $dbg['price_key'] ) ) : ?>
									<?php echo esc_html( number_format( (float) preg_replace( '/[^0-9.]/', '', (string) $dbg['price_val'] ) ) ); ?> 円<br>
									<span style="font-size:11px;color:#888;"><?php echo esc_html( $dbg['price_key'] ); ?></span>
								<?php endif; ?>
							</td>
							<td style="text-align:center;background:#f0f6ff;">
								<?php if ( $monthly_display > 0 ) : ?>
									<strong style="font-size:18px;color:#1f6feb;"><?php echo number_format( $monthly_display ); ?></strong> 円/月
								<?php else : ?>
									<span style="color:#888;">—</span>
								<?php endif; ?>
							</td>
							<td style="font-size:12px;">
								<?php echo $parts ? implode( '<br>', $parts ) : '<span style="color:#888;">なし</span>'; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function run( $rate, $atama, $hcount ) {
		$ids     = $this->all_ids();
		$applied = 0;
		foreach ( $ids as $pid ) {
			$p = $this->plan_for( $pid, $rate, $atama, $hcount );
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
