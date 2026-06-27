<?php
/**
 * Plugin Name: カーメル 月々シミュレーション補完
 * Description: 価格はあるのに月々シミュレーションが空の在庫へ、頭金0・実質年率9.8%・est_total=本体価格 を一括補完します（空欄のみ・上書きしない・プレビュー付き）。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：このファイルを wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「💴 月々補完」を開く → プレビュー → 実行。
 *         単体プラグインなので、不要になれば無効化するだけで安全に止められます。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Monthly_Backfill' ) ) {

class Carmel_Monthly_Backfill {

	/* 既定値 */
	const RATE_DEFAULT     = 9.8;  // 実質年率(%)
	const ATAMA_DEFAULT    = 0;    // 頭金(円)
	const HEADLINE_COUNT   = 60;   // 見出し「月々のお支払い」に入れる回数

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

	/* 数値化（円） */
	private function num( $v ) {
		return (float) preg_replace( '/[^0-9.]/', '', (string) $v );
	}

	/* メタ取得（生） */
	private function raw( $pid, $key ) {
		$v = get_post_meta( $pid, $key, true );
		return is_string( $v ) ? trim( $v ) : $v;
	}

	/* 空判定 */
	private function is_empty( $v ) {
		return ( null === $v || '' === $v || false === $v );
	}

	/* ACF優先で保存 */
	private function save( $pid, $key, $val ) {
		if ( function_exists( 'update_field' ) ) { update_field( $key, $val, $pid ); }
		else { update_post_meta( $pid, $key, $val ); }
	}

	/* 月々計算（プラグイン本体の関数があれば再利用、無ければ年金式） */
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
		return (int) ( ceil( $m / 100 ) * 100 ); // 100円単位
	}

	/* 1台の判定と「補完後の値」を返す（実行はしない） */
	private function plan_for( $pid, $rate, $atama, $hcount ) {
		$price_raw = $this->raw( $pid, 'price' );
		$total_raw = $this->raw( $pid, 'est_total' );
		$price = $this->num( $price_raw );
		$total = $this->num( $total_raw );

		$base = $total > 0 ? $total : $price; // 月々計算に使う総額

		// 価格情報が全く無い → スキップ
		if ( $base <= 0 ) {
			return array( 'status' => 'noprice', 'changes' => array() );
		}

		$changes = array();

		// est_total が空 → price を入れる
		if ( $this->is_empty( $total_raw ) && $price > 0 ) {
			$changes['est_total'] = (string) round( $price );
			$base = $price;
		}

		// 頭金が空 → 0
		if ( $this->is_empty( $this->raw( $pid, 'est_atamakin' ) ) ) {
			$changes['est_atamakin'] = (string) (int) $atama;
		}
		$atama_eff = $this->is_empty( $this->raw( $pid, 'est_atamakin' ) ) ? (int) $atama : $this->num( $this->raw( $pid, 'est_atamakin' ) );

		// 年率が空 → 既定
		if ( $this->is_empty( $this->raw( $pid, 'est_nenritsu' ) ) ) {
			$changes['est_nenritsu'] = (string) $rate;
		}
		$rate_eff = $this->is_empty( $this->raw( $pid, 'est_nenritsu' ) ) ? (float) $rate : $this->num( $this->raw( $pid, 'est_nenritsu' ) );

		// 見出し「月々のお支払い」(total / monthly) が空 → 代表回数で計算
		$head_empty = $this->is_empty( $this->raw( $pid, 'total' ) );
		if ( $head_empty ) {
			$principal = max( 0, $base - $atama_eff );
			$m = $this->monthly( $principal, $rate_eff, $hcount );
			if ( $m > 0 ) {
				$changes['total']   = number_format( $m );
				$changes['monthly'] = (string) $m;
			}
		}

		return array(
			'status'  => empty( $changes ) ? 'ok' : 'target',
			'changes' => $changes,
			'base'    => $base,
		);
	}

	private function all_ids() {
		return get_posts( array(
			'post_type'      => 'portfolio',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'suppress_filters' => true,
		) );
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$rate   = self::RATE_DEFAULT;
		$atama  = self::ATAMA_DEFAULT;
		$hcount = self::HEADLINE_COUNT;
		if ( isset( $_POST['cmb_rate'] ) )   { $rate   = (float) $_POST['cmb_rate']; }
		if ( isset( $_POST['cmb_atama'] ) )  { $atama  = (int) $_POST['cmb_atama']; }
		if ( isset( $_POST['cmb_hcount'] ) ) { $hcount = max( 1, (int) $_POST['cmb_hcount'] ); }

		$did_run = false; $applied = 0;
		if ( isset( $_POST['cmb_apply'] ) && check_admin_referer( 'cmb_apply' ) ) {
			$applied = $this->run( $rate, $atama, $hcount );
			$did_run = true;
		}

		// 集計
		$ids = $this->all_ids();
		$cnt = array( 'target' => 0, 'ok' => 0, 'noprice' => 0 );
		$samples = array();
		foreach ( $ids as $pid ) {
			$p = $this->plan_for( $pid, $rate, $atama, $hcount );
			$cnt[ $p['status'] ]++;
			if ( 'target' === $p['status'] && count( $samples ) < 30 ) {
				$samples[] = array( 'pid' => $pid, 'plan' => $p );
			}
		}
		?>
		<div class="wrap">
			<h1>💴 月々シミュレーション補完</h1>
			<p>価格はあるのに月々が空の在庫へ、<strong>頭金0・実質年率<?php echo esc_html( $rate ); ?>%・est_total=本体価格</strong>を補完します。<br>
			<strong>空欄のみ補完／入力済みは上書きしません。</strong>実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( $did_run ) : ?>
				<div class="notice notice-success"><p><?php echo (int) $applied; ?> 台に補完しました。</p></div>
			<?php endif; ?>

			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:640px;">
				<table class="form-table">
					<tr><th>実質年率(%)</th><td><input type="number" step="0.1" name="cmb_rate" value="<?php echo esc_attr( $rate ); ?>" style="width:100px;"></td></tr>
					<tr><th>頭金(円)</th><td><input type="number" step="1000" name="cmb_atama" value="<?php echo esc_attr( $atama ); ?>" style="width:140px;"></td></tr>
					<tr><th>見出し月々の回数</th><td><input type="number" name="cmb_hcount" value="<?php echo esc_attr( $hcount ); ?>" style="width:100px;"> 回（「月々のお支払い」に入れる回数）</td></tr>
				</table>
				<p>
					<button class="button" name="cmb_preview" value="1">プレビューを更新</button>
				</p>

				<h2>対象集計</h2>
				<ul style="font-size:14px;line-height:1.9;">
					<li>✅ 補完対象：<strong style="font-size:18px;color:#1f6feb;"><?php echo (int) $cnt['target']; ?></strong> 台</li>
					<li>― 既に入力済み（変更なし）：<?php echo (int) $cnt['ok']; ?> 台</li>
					<li>― 価格情報なし（スキップ）：<?php echo (int) $cnt['noprice']; ?> 台</li>
				</ul>

				<?php if ( $cnt['target'] > 0 ) : ?>
					<?php wp_nonce_field( 'cmb_apply' ); ?>
					<p><button class="button button-primary button-hero" name="cmb_apply" value="1"
						onclick="return confirm('<?php echo (int) $cnt['target']; ?> 台に補完します。よろしいですか？（空欄のみ・上書きなし）');">
						今すぐ <?php echo (int) $cnt['target']; ?> 台に補完する</button></p>
				<?php else : ?>
					<p><em>補完対象はありません。</em></p>
				<?php endif; ?>
			</form>

			<?php if ( ! empty( $samples ) ) : ?>
				<h2>プレビュー（先頭 <?php echo count( $samples ); ?> 台）</h2>
				<table class="widefat striped" style="max-width:1000px;">
					<thead><tr><th>車両</th><th>本体価格</th><th>補完する項目（→ 入れる値）</th></tr></thead>
					<tbody>
					<?php foreach ( $samples as $s ) :
						$pid = $s['pid']; $ch = $s['plan']['changes'];
						$labels = array(
							'est_total'    => 'est_total(支払総額)',
							'est_atamakin' => 'est_atamakin(頭金)',
							'est_nenritsu' => 'est_nenritsu(年率)',
							'total'        => 'total(月々表示)',
							'monthly'      => 'monthly',
						);
						$parts = array();
						foreach ( $ch as $k => $v ) {
							$lab = isset( $labels[ $k ] ) ? $labels[ $k ] : $k;
							$parts[] = esc_html( $lab . ' → ' . $v );
						}
					?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></td>
							<td><?php echo esc_html( number_format( $s['plan']['base'] ) ); ?> 円</td>
							<td><?php echo implode( '<br>', $parts ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/* 実行：空欄のみ補完 */
	private function run( $rate, $atama, $hcount ) {
		$ids = $this->all_ids();
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
