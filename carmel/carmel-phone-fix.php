<?php
/**
 * Plugin Name: カーメル 電話番号修正
 * Description: 店舗ごとに正しい電話番号を入力し、その店舗の全車両のtelを一括で正しい番号へ統一（旧番号を上書き）します。店舗投稿のtelも更新可。プレビュー付き。
 * Version: 1.1.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「電話番号修正」を開く → 各店舗の正番号を入力 → プレビュー → 実行。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Phone_Fix' ) ) {

class Carmel_Phone_Fix {

	/* 正しい番号を最初から設定（千葉・山梨は空＝触らない） */
	private function defaults() {
		return array(
			'fukushima' => '050-1793-5554',
			'odawara'   => '0465-20-4286',
			'chiba'     => '', // そのまま（触らない）
			'yamanashi' => '', // そのまま（触らない）
		);
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'電話番号修正',
			'電話番号修正',
			'manage_options',
			'carmel-phone-fix',
			array( $this, 'render' )
		);
	}

	private function blank( $v ) { return ( null === $v || '' === $v || false === $v ); }
	private function digits( $v ) { return preg_replace( '/[^0-9]/', '', (string) $v ); }

	private function shop_map() {
		return function_exists( 'carmel_shop_post_map' ) ? (array) carmel_shop_post_map() : array();
	}

	private function save( $pid, $key, $val ) {
		if ( function_exists( 'update_field' ) ) { update_field( $key, $val, $pid ); }
		else { update_post_meta( $pid, $key, $val ); }
	}

	private function vehicles_by_shop( $slug ) {
		return get_posts( array(
			'post_type'      => 'portfolio',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => 'shop',
			'meta_value'     => $slug,
			'suppress_filters' => true,
		) );
	}

	/* 入力された各店舗の正番号を取得（POST > 現在のshop投稿tel > 既定） */
	private function numbers( $map ) {
		$def = $this->defaults();
		$out = array();
		foreach ( $map as $slug => $sid ) {
			if ( isset( $_POST['cpf_num'][ $slug ] ) ) {
				$out[ $slug ] = sanitize_text_field( wp_unslash( $_POST['cpf_num'][ $slug ] ) );
			} elseif ( array_key_exists( $slug, $def ) ) {
				// 設定済みの正番号を採用（千葉・山梨は '' = 触らない）
				$out[ $slug ] = (string) $def[ $slug ];
			} else {
				$cur = get_post_meta( (int) $sid, 'tel', true );
				$out[ $slug ] = is_string( $cur ) ? trim( $cur ) : '';
			}
		}
		return $out;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$map = $this->shop_map();
		if ( empty( $map ) ) { echo '<div class="wrap"><h1>電話番号修正</h1><div class="notice notice-error"><p>店舗マップが見つかりません。</p></div></div>'; return; }

		$nums      = $this->numbers( $map );
		$sync_shop = isset( $_POST['cpf_submit'] ) || isset( $_POST['cpf_apply'] ) ? ! empty( $_POST['cpf_sync_shop'] ) : true;

		$did = false; $applied = 0;
		if ( isset( $_POST['cpf_apply'] ) && check_admin_referer( 'cpf_apply' ) ) {
			$applied = $this->run( $map, $nums, $sync_shop );
			$did = true;
		}

		// プレビュー：各店舗で「正番号と違うtelを持つ車」の件数
		$preview = array(); $total_change = 0;
		foreach ( $map as $slug => $sid ) {
			$correct = $nums[ $slug ];
			$cd = $this->digits( $correct );
			$ids = $this->vehicles_by_shop( $slug );
			$change = 0;
			if ( '' !== $cd ) {
				foreach ( $ids as $pid ) {
					$cur = $this->digits( get_post_meta( $pid, 'tel', true ) );
					if ( $cur !== $cd ) { $change++; }
				}
			}
			$preview[ $slug ] = array( 'total' => count( $ids ), 'change' => $change );
			$total_change += $change;
		}
		?>
		<div class="wrap">
			<h1>電話番号修正（店舗別に統一）</h1>
			<p>各店舗の正しい番号を入れて実行すると、その店舗の全車両の電話番号を統一します（旧番号を上書き）。実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( $did ) : ?><div class="notice notice-success"><p><?php echo (int) $applied; ?> 台の電話番号を更新しました。</p></div><?php endif; ?>

			<form method="post" style="margin:16px 0;padding:14px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:720px;">
				<table class="form-table">
					<?php foreach ( $map as $slug => $sid ) : ?>
						<tr>
							<th><?php echo esc_html( get_the_title( $sid ) ?: $slug ); ?><br><span style="font-weight:400;color:#888;font-size:12px;"><?php echo esc_html( $slug ); ?></span></th>
							<td>
								<input type="text" name="cpf_num[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $nums[ $slug ] ); ?>" style="width:220px;" placeholder="例：050-1793-5554">
								<span style="color:#666;font-size:12px;">対象 <?php echo (int) $preview[ $slug ]['total']; ?> 台 / 変更 <strong style="color:#1f6feb;"><?php echo (int) $preview[ $slug ]['change']; ?></strong> 台</span>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr><th>店舗投稿も更新</th><td><label><input type="checkbox" name="cpf_sync_shop" value="1" <?php checked( $sync_shop ); ?>> 各店舗投稿の電話番号も入力値に更新（今後の新規車に正番号が入る）</label></td></tr>
				</table>
				<p><button class="button" name="cpf_submit" value="1">プレビューを更新</button></p>

				<h2>変更対象：合計 <strong style="color:#1f6feb;"><?php echo (int) $total_change; ?></strong> 台</h2>
				<?php if ( $total_change > 0 ) : ?>
					<?php wp_nonce_field( 'cpf_apply' ); ?>
					<p><button class="button button-primary button-hero" name="cpf_apply" value="1"
						onclick="return confirm('合計 <?php echo (int) $total_change; ?> 台の電話番号を統一します。よろしいですか？');">
						今すぐ統一する</button></p>
				<?php else : ?>
					<p><em>変更が必要な車両はありません（または番号未入力）。</em></p>
				<?php endif; ?>
			</form>
			<p style="color:#666;font-size:12px;">※ 番号を空にした店舗は対象外（触りません）。番号はハイフン有無どちらでも判定します。</p>
		</div>
		<?php
	}

	private function run( $map, $nums, $sync_shop ) {
		$applied = 0;
		foreach ( $map as $slug => $sid ) {
			$correct = trim( (string) $nums[ $slug ] );
			$cd = $this->digits( $correct );
			if ( '' === $cd ) { continue; } // 未入力はスキップ

			if ( $sync_shop ) { $this->save( (int) $sid, 'tel', $correct ); }

			foreach ( $this->vehicles_by_shop( $slug ) as $pid ) {
				$cur = $this->digits( get_post_meta( $pid, 'tel', true ) );
				if ( $cur !== $cd ) {
					$this->save( $pid, 'tel', $correct );
					$applied++;
				}
			}
		}
		return $applied;
	}
}

new Carmel_Phone_Fix();

}
