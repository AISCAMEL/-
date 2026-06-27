<?php
/**
 * Plugin Name: カーメル 数字整形
 * Description: 在庫の数字表記を整えます。月々/走行距離=全角→半角＋3桁カンマ、年式=全角→半角。計算用フィールド(price/est_*)は触りません。プレビュー付き・変化する値のみ更新。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「数字整形」を開く → プレビュー → 実行。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Number_Format' ) ) {

class Carmel_Number_Format {

	/* 対象フィールド：key => array(label, comma) */
	private function fields() {
		return array(
			'total'   => array( '月々', true ),
			'mileage' => array( '走行距離', true ),
			'year'    => array( '年式', false ),
		);
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'数字整形',
			'数字整形',
			'manage_options',
			'carmel-number-format',
			array( $this, 'render' )
		);
	}

	private function blank( $v ) { return ( null === $v || '' === $v || false === $v ); }

	/* 整形後の値を返す（変化なしなら null） */
	private function formatted( $pid, $key, $comma ) {
		$cur = get_post_meta( $pid, $key, true );
		if ( ! is_string( $cur ) || '' === trim( $cur ) ) { return null; }
		$cur = trim( $cur );

		// 全角英数字→半角
		$h = function_exists( 'mb_convert_kana' ) ? mb_convert_kana( $cur, 'n', 'UTF-8' ) : $cur;

		if ( $comma ) {
			$digits = preg_replace( '/[^0-9]/', '', $h );
			if ( '' === $digits ) { $new = $h; }
			else { $new = number_format( (int) $digits ); }
		} else {
			$new = $h; // 全角→半角のみ
		}

		return ( $new !== $cur ) ? $new : null;
	}

	private function save( $pid, $key, $val ) {
		if ( function_exists( 'update_field' ) ) { update_field( $key, $val, $pid ); }
		else { update_post_meta( $pid, $key, $val ); }
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
		$fields = $this->fields();

		// チェック対象（初期：month/year ON、mileage OFF）
		$posted = isset( $_POST['cnf_submit'] ) || isset( $_POST['cnf_apply'] );
		$sel = array();
		foreach ( $fields as $k => $f ) {
			if ( $posted ) { $sel[ $k ] = isset( $_POST['cnf_fields'][ $k ] ); }
			else { $sel[ $k ] = ( 'mileage' !== $k ); }
		}

		$did = false; $applied = 0;
		if ( isset( $_POST['cnf_apply'] ) && check_admin_referer( 'cnf_apply' ) ) {
			$applied = $this->run( $sel );
			$did = true;
		}

		// プレビュー集計
		$ids = $this->all_ids();
		$changes = array(); $count = 0;
		foreach ( $ids as $pid ) {
			$diffs = array();
			foreach ( $fields as $k => $f ) {
				if ( empty( $sel[ $k ] ) ) { continue; }
				$new = $this->formatted( $pid, $k, $f[1] );
				if ( null !== $new ) {
					$diffs[ $k ] = array( 'label' => $f[0], 'from' => trim( (string) get_post_meta( $pid, $k, true ) ), 'to' => $new );
				}
			}
			if ( $diffs ) { $count++; if ( count( $changes ) < 50 ) { $changes[ $pid ] = $diffs; } }
		}
		?>
		<div class="wrap">
			<h1>数字整形</h1>
			<p>全角数字→半角、月々/走行距離は3桁カンマも付与します。<strong>計算用の price / est_* は触りません。変化する値だけ更新。</strong>実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( $did ) : ?><div class="notice notice-success"><p><?php echo (int) $applied; ?> 台を整形しました。</p></div><?php endif; ?>

			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:640px;">
				<p><strong>対象フィールド：</strong></p>
				<?php foreach ( $fields as $k => $f ) : ?>
					<label style="display:inline-block;margin-right:18px;"><input type="checkbox" name="cnf_fields[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( ! empty( $sel[ $k ] ) ); ?>> <?php echo esc_html( $f[0] ); ?><?php echo $f[1] ? '（半角＋カンマ）' : '（半角のみ）'; ?></label>
				<?php endforeach; ?>
				<p><button class="button" name="cnf_submit" value="1">プレビューを更新</button></p>

				<h2>変更対象：<strong style="color:#1f6feb;"><?php echo (int) $count; ?></strong> 台</h2>
				<?php if ( $count > 0 ) : ?>
					<?php wp_nonce_field( 'cnf_apply' ); ?>
					<p><button class="button button-primary button-hero" name="cnf_apply" value="1"
						onclick="return confirm('<?php echo (int) $count; ?> 台を整形します。よろしいですか？（変化する値のみ）');">
						今すぐ <?php echo (int) $count; ?> 台を整形する</button></p>
				<?php else : ?>
					<p><em>整形が必要な値はありません。</em></p>
				<?php endif; ?>
			</form>

			<?php if ( ! empty( $changes ) ) : ?>
				<h2>プレビュー（先頭 <?php echo count( $changes ); ?> 台）</h2>
				<table class="widefat striped" style="max-width:1000px;">
					<thead><tr><th>車両</th><th>変更内容</th></tr></thead>
					<tbody>
					<?php foreach ( $changes as $pid => $diffs ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: '#' . $pid ); ?></a></td>
							<td><?php
								$parts = array();
								foreach ( $diffs as $d ) { $parts[] = esc_html( $d['label'] . '：' . $d['from'] . ' → ' . $d['to'] ); }
								echo implode( '<br>', $parts );
							?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private function run( $sel ) {
		$fields = $this->fields();
		$ids = $this->all_ids();
		$applied = 0;
		foreach ( $ids as $pid ) {
			$changed = false;
			foreach ( $fields as $k => $f ) {
				if ( empty( $sel[ $k ] ) ) { continue; }
				$new = $this->formatted( $pid, $k, $f[1] );
				if ( null !== $new ) { $this->save( $pid, $k, $new ); $changed = true; }
			}
			if ( $changed ) { $applied++; }
		}
		return $applied;
	}
}

new Carmel_Number_Format();

}
