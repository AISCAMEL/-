<?php
/**
 * Plugin Name: カーメル 店舗一括割当
 * Description: 店舗未設定の在庫へ、4店舗をランダム均等（または指定店舗）に一括割当します。連絡先（電話/LINE/問い合わせ）も割当店舗に合わせて空欄補完。プレビュー付き・空欄優先。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「店舗一括割当」を開く → プレビュー → 実行。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Shop_Assign' ) ) {

class Carmel_Shop_Assign {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'店舗一括割当',
			'店舗一括割当',
			'manage_options',
			'carmel-shop-assign',
			array( $this, 'render' )
		);
	}

	private function blank( $v ) { return ( null === $v || '' === $v || false === $v ); }

	/* slug => shop投稿ID */
	private function shop_map() {
		return function_exists( 'carmel_shop_post_map' ) ? (array) carmel_shop_post_map() : array();
	}

	/* shop投稿から連絡先を取得 */
	private function shop_contact( $sid ) {
		$get = function ( $keys ) use ( $sid ) {
			foreach ( (array) $keys as $k ) {
				$v = function_exists( 'get_field' ) ? get_field( $k, $sid ) : '';
				if ( $this->blank( $v ) ) { $v = get_post_meta( $sid, $k, true ); }
				if ( ! $this->blank( $v ) ) { return is_string( $v ) ? trim( $v ) : $v; }
			}
			return '';
		};
		return array(
			'tel'          => $get( array( 'tel', 'phone', 'denwa' ) ),
			'line-link'    => $get( array( 'line_link', 'line-link' ) ),
			'contact-link' => $get( array( 'contact-link', 'contact_link' ) ),
		);
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

	/* 対象IDを取得（店舗未設定 or 全件） */
	private function target_ids( $scope ) {
		$ids = $this->all_ids();
		if ( 'all' === $scope ) { return $ids; }
		$out = array();
		foreach ( $ids as $pid ) {
			if ( $this->blank( get_post_meta( $pid, 'shop', true ) ) ) { $out[] = $pid; }
		}
		return $out;
	}

	/* 割当計画（pid => slug）。ランダム均等 or 指定 */
	private function plan( $targets, $mode, $fixed, $slugs ) {
		$plan = array();
		if ( 'fixed' === $mode && $fixed && in_array( $fixed, $slugs, true ) ) {
			foreach ( $targets as $pid ) { $plan[ $pid ] = $fixed; }
			return $plan;
		}
		// ランダム均等：シャッフルしてラウンドロビン
		$t = $targets;
		shuffle( $t );
		$n = count( $slugs );
		if ( $n < 1 ) { return $plan; }
		foreach ( $t as $i => $pid ) {
			$plan[ $pid ] = $slugs[ $i % $n ];
		}
		return $plan;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$map   = $this->shop_map();
		$slugs = array_keys( $map );

		$scope    = isset( $_POST['csa_scope'] ) ? sanitize_key( $_POST['csa_scope'] ) : 'empty';
		$mode     = isset( $_POST['csa_mode'] ) ? sanitize_key( $_POST['csa_mode'] ) : 'random';
		$fixed    = isset( $_POST['csa_fixed'] ) ? sanitize_key( $_POST['csa_fixed'] ) : '';
		$sync     = isset( $_POST['csa_sync'] ) ? 1 : ( isset( $_POST['csa_apply'] ) || isset( $_POST['csa_preview'] ) ? 0 : 1 );

		$did = false; $applied = 0;
		if ( isset( $_POST['csa_apply'] ) && check_admin_referer( 'csa_apply' ) ) {
			$applied = $this->run( $scope, $mode, $fixed, $slugs, $sync );
			$did = true;
		}

		$targets = $this->target_ids( $scope );
		$plan    = $this->plan( $targets, $mode, $fixed, $slugs );
		$dist    = array();
		foreach ( $plan as $slug ) { $dist[ $slug ] = isset( $dist[ $slug ] ) ? $dist[ $slug ] + 1 : 1; }
		?>
		<div class="wrap">
			<h1>店舗一括割当</h1>
			<p>店舗未設定の在庫へ、店舗を一括で割り当てます。<strong>既存の店舗は触りません（空欄優先）。</strong>実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( empty( $map ) ) : ?>
				<div class="notice notice-error"><p>店舗マップ（carmel_shop_post_map）が見つかりません。本体プラグインが有効か確認してください。</p></div>
				</div><?php return; endif; ?>

			<?php if ( $did ) : ?><div class="notice notice-success"><p><?php echo (int) $applied; ?> 台に店舗を割り当てました。</p></div><?php endif; ?>

			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:680px;">
				<table class="form-table">
					<tr><th>対象</th><td>
						<label><input type="radio" name="csa_scope" value="empty" <?php checked( $scope, 'empty' ); ?>> 店舗未設定の車だけ（おすすめ）</label><br>
						<label><input type="radio" name="csa_scope" value="all" <?php checked( $scope, 'all' ); ?>> 全在庫（既存の店舗も上書き）</label>
					</td></tr>
					<tr><th>割当方法</th><td>
						<label><input type="radio" name="csa_mode" value="random" <?php checked( $mode, 'random' ); ?>> ランダム均等（<?php echo count( $slugs ); ?>店舗へ均等にバラまく）</label><br>
						<label><input type="radio" name="csa_mode" value="fixed" <?php checked( $mode, 'fixed' ); ?>> 指定店舗に統一：
							<select name="csa_fixed">
								<option value="">―選択―</option>
								<?php foreach ( $map as $slug => $sid ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $fixed, $slug ); ?>><?php echo esc_html( get_the_title( $sid ) ?: $slug ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</td></tr>
					<tr><th>連絡先も補完</th><td><label><input type="checkbox" name="csa_sync" value="1" <?php checked( $sync, 1 ); ?>> 割当店舗の 電話/LINE/問い合わせ を空欄に補完する</label></td></tr>
				</table>
				<p><button class="button" name="csa_preview" value="1">プレビューを更新</button></p>

				<h2>割当プレビュー</h2>
				<p style="font-size:14px;">対象：<strong style="font-size:18px;color:#1f6feb;"><?php echo count( $targets ); ?></strong> 台</p>
				<p style="font-size:13px;line-height:2;">
				<?php foreach ( $map as $slug => $sid ) : $c = isset( $dist[ $slug ] ) ? $dist[ $slug ] : 0; ?>
					<span style="display:inline-block;background:#f3f6fb;border:1px solid #cfd8e3;border-radius:14px;padding:3px 12px;margin:2px;"><?php echo esc_html( get_the_title( $sid ) ?: $slug ); ?>：<strong><?php echo (int) $c; ?></strong> 台</span>
				<?php endforeach; ?>
				</p>

				<?php if ( count( $targets ) > 0 && ( 'random' === $mode || ( 'fixed' === $mode && $fixed ) ) ) : ?>
					<?php wp_nonce_field( 'csa_apply' ); ?>
					<p><button class="button button-primary button-hero" name="csa_apply" value="1"
						onclick="return confirm('<?php echo count( $targets ); ?> 台に店舗を割り当てます。よろしいですか？');">
						今すぐ <?php echo count( $targets ); ?> 台に割り当てる</button></p>
					<p style="color:#666;font-size:12px;">※ ランダムは実行ボタンを押した瞬間に再抽選されます（上のプレビューは件数の目安）。</p>
				<?php elseif ( 'fixed' === $mode && ! $fixed ) : ?>
					<p><em>指定店舗を選んでください。</em></p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	private function run( $scope, $mode, $fixed, $slugs, $sync ) {
		$map     = $this->shop_map();
		$targets = $this->target_ids( $scope );
		$plan    = $this->plan( $targets, $mode, $fixed, $slugs );
		$contact_cache = array();
		$applied = 0;
		foreach ( $plan as $pid => $slug ) {
			if ( ! $slug ) { continue; }
			$this->save( $pid, 'shop', $slug );
			if ( $sync && isset( $map[ $slug ] ) ) {
				if ( ! isset( $contact_cache[ $slug ] ) ) { $contact_cache[ $slug ] = $this->shop_contact( (int) $map[ $slug ] ); }
				foreach ( $contact_cache[ $slug ] as $k => $v ) {
					if ( '' === $v ) { continue; }
					if ( $this->blank( get_post_meta( $pid, $k, true ) ) ) { $this->save( $pid, $k, $v ); }
				}
			}
			$applied++;
		}
		return $applied;
	}
}

new Carmel_Shop_Assign();

}
