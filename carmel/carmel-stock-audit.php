<?php
/**
 * Plugin Name: カーメル 在庫 ページ診断
 * Description: 全在庫を診断し、未入力・数字の不備・シミュレーション不足・店舗情報なしなど「未完成ページ」をエラー/警告として一覧化します（読み取り専用・データ変更なし）。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「🩺 ページ診断」を開く。
 *         読み取り専用なので、見るだけで在庫を壊しません。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Stock_Audit' ) ) {

class Carmel_Stock_Audit {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'在庫 ページ診断',
			'🩺 ページ診断',
			'manage_options',
			'carmel-stock-audit',
			array( $this, 'render' )
		);
	}

	private function raw( $pid, $key ) {
		$v = get_post_meta( $pid, $key, true );
		return is_string( $v ) ? trim( $v ) : $v;
	}
	private function blank( $v ) { return ( null === $v || '' === $v || false === $v ); }
	private function num( $v ) { return (float) preg_replace( '/[^0-9.]/', '', (string) $v ); }
	private function has_zenkaku( $v ) { return is_string( $v ) && preg_match( '/[\x{FF10}-\x{FF19}]/u', $v ); } // full-width 0-9
	private function has_tilde( $v ) { return is_string( $v ) && preg_match( '/[\x{301C}\x{FF5E}]/u', $v ); }     // 〜 / ～

	/* 1台を診断 → 問題の配列を返す */
	private function check( $pid ) {
		$errors = array();   // 致命的
		$warns  = array();   // 改善推奨

		// --- 価格 ---
		$price = $this->num( $this->raw( $pid, 'price' ) );
		$total = $this->num( $this->raw( $pid, 'est_total' ) );
		if ( $price <= 0 && $total <= 0 ) { $errors[] = '価格なし'; }

		// --- 店舗情報 ---
		if ( $this->blank( $this->raw( $pid, 'shop' ) ) ) { $errors[] = '店舗未設定'; }
		if ( $this->blank( $this->raw( $pid, 'tel' ) ) )  { $warns[]  = '電話番号なし'; }

		// --- 基本情報（重要項目） ---
		$req = array(
			'marker'     => 'メーカー',
			'year'       => '年式',
			'mileage'    => '走行距離',
			'inspection' => '車検',
			'color'      => 'ボディカラー',
		);
		foreach ( $req as $k => $label ) {
			if ( $this->blank( $this->raw( $pid, $k ) ) ) { $errors[] = $label . '未入力'; }
		}

		// --- 画像 ---
		$has_thumb = has_post_thumbnail( $pid );
		$gal = $this->raw( $pid, 'wpex_post_gallery_ids' );
		$cg  = $this->raw( $pid, 'carmel_gallery' );
		if ( ! $has_thumb && $this->blank( $gal ) && $this->blank( $cg ) ) { $errors[] = '画像なし'; }

		// --- シミュレーション（月々） ---
		$has_price = ( $price > 0 || $total > 0 );
		if ( $has_price ) {
			if ( $this->blank( $this->raw( $pid, 'est_nenritsu' ) ) ) { $warns[] = 'シミュ未設定(年率)'; }
			if ( $this->blank( $this->raw( $pid, 'total' ) ) )        { $warns[] = '月々表示なし'; }
		}

		// --- 数字の不備（全角・〜） ---
		foreach ( array( 'year' => '年式', 'mileage' => '走行距離', 'price' => '本体価格', 'total' => '月々' ) as $k => $label ) {
			$v = $this->raw( $pid, $k );
			if ( $this->has_zenkaku( $v ) ) { $warns[] = $label . 'に全角数字'; }
			if ( $this->has_tilde( $v ) )   { $warns[] = $label . 'に「〜」混入'; }
		}

		// --- タイトル ---
		$title = get_the_title( $pid );
		if ( '' === trim( $title ) ) { $errors[] = 'タイトル空'; }

		return array( 'errors' => $errors, 'warns' => $warns );
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
		$filter = isset( $_GET['cmb_filter'] ) ? sanitize_key( $_GET['cmb_filter'] ) : 'issues';

		$ids = $this->all_ids();
		$rows = array();
		$n_err = 0; $n_warn = 0; $n_ok = 0;
		$reason_count = array();

		foreach ( $ids as $pid ) {
			$c = $this->check( $pid );
			$has_e = ! empty( $c['errors'] );
			$has_w = ! empty( $c['warns'] );
			if ( $has_e ) { $n_err++; } elseif ( $has_w ) { $n_warn++; } else { $n_ok++; }
			foreach ( array_merge( $c['errors'], $c['warns'] ) as $r ) {
				$reason_count[ $r ] = isset( $reason_count[ $r ] ) ? $reason_count[ $r ] + 1 : 1;
			}
			$show = false;
			if ( 'all' === $filter ) { $show = true; }
			elseif ( 'errors' === $filter ) { $show = $has_e; }
			elseif ( 'issues' === $filter ) { $show = ( $has_e || $has_w ); }
			if ( $show ) { $rows[] = array( 'pid' => $pid, 'c' => $c, 'e' => $has_e, 'w' => $has_w ); }
		}
		arsort( $reason_count );

		$base_url = admin_url( 'edit.php?post_type=portfolio&page=carmel-stock-audit' );
		?>
		<div class="wrap">
			<h1>🩺 在庫 ページ診断</h1>
			<p>全在庫を診断しました（<strong>読み取り専用・データは変更しません</strong>）。各行の編集リンクから直せます。</p>

			<div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0;">
				<div style="background:#fff;border:1px solid #f0c4bf;border-left:4px solid #d63638;padding:10px 16px;border-radius:6px;">
					<div style="font-size:12px;color:#666;">エラー（要修正）</div>
					<div style="font-size:24px;font-weight:800;color:#d63638;"><?php echo (int) $n_err; ?> 台</div>
				</div>
				<div style="background:#fff;border:1px solid #f0e0a0;border-left:4px solid #dba617;padding:10px 16px;border-radius:6px;">
					<div style="font-size:12px;color:#666;">警告（改善推奨）</div>
					<div style="font-size:24px;font-weight:800;color:#dba617;"><?php echo (int) $n_warn; ?> 台</div>
				</div>
				<div style="background:#fff;border:1px solid #b6e0b6;border-left:4px solid #46b450;padding:10px 16px;border-radius:6px;">
					<div style="font-size:12px;color:#666;">問題なし</div>
					<div style="font-size:24px;font-weight:800;color:#46b450;"><?php echo (int) $n_ok; ?> 台</div>
				</div>
			</div>

			<p>
				表示：
				<a class="button <?php echo 'errors' === $filter ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base_url . '&cmb_filter=errors' ); ?>">エラーのみ</a>
				<a class="button <?php echo 'issues' === $filter ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base_url . '&cmb_filter=issues' ); ?>">エラー＋警告</a>
				<a class="button <?php echo 'all' === $filter ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base_url . '&cmb_filter=all' ); ?>">全件</a>
			</p>

			<?php if ( ! empty( $reason_count ) ) : ?>
				<h2>問題の内訳（多い順）</h2>
				<p style="font-size:13px;line-height:2;">
				<?php foreach ( $reason_count as $r => $cnt ) : ?>
					<span style="display:inline-block;background:#f3f6fb;border:1px solid #cfd8e3;border-radius:14px;padding:3px 12px;margin:2px;"><?php echo esc_html( $r ); ?>：<strong><?php echo (int) $cnt; ?></strong></span>
				<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<h2>該当車両（<?php echo count( $rows ); ?> 台）</h2>
			<table class="widefat striped" style="max-width:1100px;">
				<thead><tr><th style="width:90px;">状態</th><th>車両</th><th>問題</th><th style="width:80px;">操作</th></tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="4"><em>該当なし。すべて問題ありません。</em></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) :
					$pid = $row['pid'];
					$badge = $row['e']
						? '<span style="background:#d63638;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">エラー</span>'
						: ( $row['w'] ? '<span style="background:#dba617;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">警告</span>'
						: '<span style="background:#46b450;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">OK</span>' );
					$tags = '';
					foreach ( $row['c']['errors'] as $r ) { $tags .= '<span style="display:inline-block;background:#fdeaea;color:#a30000;border-radius:10px;padding:1px 9px;margin:2px;font-size:12px;">' . esc_html( $r ) . '</span>'; }
					foreach ( $row['c']['warns'] as $r )  { $tags .= '<span style="display:inline-block;background:#fbf6e3;color:#8a6d00;border-radius:10px;padding:1px 9px;margin:2px;font-size:12px;">' . esc_html( $r ) . '</span>'; }
				?>
					<tr>
						<td><?php echo $badge; ?></td>
						<td><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $pid ) ?: '(無題 #' . $pid . ')' ); ?></a></td>
						<td><?php echo $tags ? $tags : '<span style="color:#46b450;">問題なし</span>'; ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">編集</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

new Carmel_Stock_Audit();

}
