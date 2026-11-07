<?php
/**
 * Plugin Name: カーメル 在庫 ページ診断
 * Description: 全在庫を診断し、未入力・数字の不備・シミュレーション不足・店舗情報なしなど「未完成ページ」をエラー/警告として一覧化します（読み取り専用・データ変更なし）。判定は本体の表示解決(carmel_detail_get_any/複数キー/ACF)に合わせています。
 * Version: 1.1.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「在庫ページ診断」を開く。読み取り専用。
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
			'在庫ページ診断',
			'manage_options',
			'carmel-stock-audit',
			array( $this, 'render' )
		);
	}

	private function blank( $v ) { return ( null === $v || '' === $v || false === $v || ( is_array( $v ) && empty( $v ) ) ); }
	private function num( $v ) { return (float) preg_replace( '/[^0-9.]/', '', (string) $v ); }
	private function has_zenkaku( $v ) { return is_string( $v ) && preg_match( '/[\x{FF10}-\x{FF19}]/u', $v ); }
	private function has_tilde( $v ) { return is_string( $v ) && preg_match( '/[\x{301C}\x{FF5E}]/u', $v ); }

	/* 本体と同じ解決：複数候補キー × ACF(get_field)優先 × post_metaフォールバック */
	private function get_any( $pid, $keys ) {
		if ( function_exists( 'carmel_detail_get_any' ) ) {
			return (string) carmel_detail_get_any( $pid, (array) $keys );
		}
		foreach ( (array) $keys as $k ) {
			$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : '';
			if ( $this->blank( $v ) ) { $v = get_post_meta( $pid, $k, true ); }
			if ( is_array( $v ) ) { $v = implode( '', array_filter( $v ) ); }
			$v = is_string( $v ) ? trim( $v ) : $v;
			if ( ! $this->blank( $v ) ) { return (string) $v; }
		}
		return '';
	}

	/* 1台を診断 → 問題の配列を返す */
	private function check( $pid ) {
		$errors = array();
		$warns  = array();

		// 候補キー（本体 carmel_basic と同一）
		$req = array(
			'メーカー'     => array( 'marker', 'maker', 'メーカー' ),
			'年式'         => array( 'year', 'nenshiki' ),
			'走行距離'     => array( 'mileage', 'soukou', 'soukou_kyori', 'kyori' ),
			'車検'         => array( 'shaken', 'inspection' ),
			'ボディカラー' => array( 'color', 'body_color', 'iro' ),
		);
		foreach ( $req as $label => $keys ) {
			if ( '' === $this->get_any( $pid, $keys ) ) { $errors[] = $label . '未入力'; }
		}

		// 価格（複数キーを検索して古い車両の価格キーにも対応）
		$price = $this->num( $this->get_any( $pid, array( 'price', 'est_honntai', 'honntai', 'hontai', 'kakaku', 'honbai', 'price_main' ) ) );
		$total = $this->num( $this->get_any( $pid, array( 'est_total' ) ) );
		if ( $price <= 0 && $total <= 0 ) { $errors[] = '価格なし'; }

		// 店舗情報
		if ( '' === $this->get_any( $pid, array( 'shop' ) ) ) { $errors[] = '店舗未設定'; }
		if ( '' === $this->get_any( $pid, array( 'tel', 'phone', 'denwa' ) ) ) { $warns[] = '電話番号なし'; }

		// 画像
		$gal = get_post_meta( $pid, 'wpex_post_gallery_ids', true );
		$cg  = get_post_meta( $pid, 'carmel_gallery', true );
		if ( ! has_post_thumbnail( $pid ) && $this->blank( $gal ) && $this->blank( $cg ) ) { $errors[] = '画像なし'; }

		// シミュレーション（価格があるのに月々が未整備）
		if ( $price > 0 || $total > 0 ) {
			if ( '' === $this->get_any( $pid, array( 'est_nenritsu' ) ) ) { $warns[] = 'シミュ未設定(年率)'; }
			if ( '' === $this->get_any( $pid, array( 'total' ) ) )        { $warns[] = '月々表示なし'; }
		}

		// 数字の不備（全角・〜）
		$num_fields = array(
			'年式'     => array( 'year', 'nenshiki' ),
			'走行距離' => array( 'mileage', 'soukou', 'kyori' ),
			'本体価格' => array( 'price' ),
			'月々'     => array( 'total' ),
		);
		foreach ( $num_fields as $label => $keys ) {
			$v = $this->get_any( $pid, $keys );
			if ( $this->has_zenkaku( $v ) ) { $warns[] = $label . 'に全角数字'; }
			if ( $this->has_tilde( $v ) )   { $warns[] = $label . 'に「〜」混入'; }
		}

		// タイトル
		if ( '' === trim( (string) get_the_title( $pid ) ) ) { $errors[] = 'タイトル空'; }

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

	/* エラー種別 → 対処方法ヒントと関連ツールリンク */
	private function hints() {
		$base = 'edit.php?post_type=portfolio&page=';
		return array(
			'ボディカラー未入力' => array( '編集画面のカラーフィールドに色を入力してください。',    '' ),
			'メーカー未入力'     => array( '編集画面のメーカーを入力してください。',                '' ),
			'年式未入力'         => array( '年式フィールドを入力してください（例：2020年）。',      '' ),
			'走行距離未入力'     => array( '走行距離フィールドを入力してください（例：40,000km）。', '' ),
			'車検未入力'         => array( '車検フィールドを入力してください。',                    '' ),
			'価格なし'           => array( 'price フィールドに本体価格を入力してください。車両データ確認で実際のキーを確認できます。', admin_url( $base . 'carmel-car-inspect' ) ),
			'店舗未設定'         => array( '「店舗一括割当」で一括設定できます。',                  admin_url( $base . 'carmel-shop-assign' ) ),
			'電話番号なし'       => array( '店舗の電話番号を設定するか、「店舗一括割当」で連絡先補完を使ってください。', admin_url( $base . 'carmel-shop-assign' ) ),
			'画像なし'           => array( 'アイキャッチまたはギャラリーに画像を登録してください。', '' ),
			'タイトル空'         => array( '「タイトル一括修正」で自動補完できます。',              admin_url( $base . 'carmel-title-bulk' ) ),
			'シミュ未設定(年率)' => array( '「月々シミュ補完」で一括補完できます（年率9.8%・頭金0）。', admin_url( $base . 'carmel-monthly-backfill' ) ),
			'月々表示なし'       => array( '「月々シミュ補完」で月々支払いを自動計算して補完できます。', admin_url( $base . 'carmel-monthly-backfill' ) ),
			'年式に「〜」混入'   => array( '「〜修正（年式）」で一括修正できます。',               admin_url( $base . 'carmel-tilde-fix' ) ),
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$filter        = isset( $_GET['cmb_filter'] ) ? sanitize_key( $_GET['cmb_filter'] ) : 'issues';
		$reason_filter = isset( $_GET['cmb_reason'] ) ? sanitize_text_field( wp_unslash( $_GET['cmb_reason'] ) ) : '';

		$ids = $this->all_ids();
		$all_rows = array();
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
			$all_rows[] = array( 'pid' => $pid, 'c' => $c, 'e' => $has_e, 'w' => $has_w );
		}
		arsort( $reason_count );

		// フィルタ適用
		$rows = array();
		foreach ( $all_rows as $row ) {
			$has_e = $row['e']; $has_w = $row['w'];
			$all_reasons = array_merge( $row['c']['errors'], $row['c']['warns'] );
			if ( $reason_filter && ! in_array( $reason_filter, $all_reasons, true ) ) { continue; }
			$show = ( 'all' === $filter ) || ( 'errors' === $filter && $has_e ) || ( 'issues' === $filter && ( $has_e || $has_w ) );
			if ( $show ) { $rows[] = $row; }
		}

		$base_url = admin_url( 'edit.php?post_type=portfolio&page=carmel-stock-audit' );
		$hints    = $this->hints();
		?>
		<div class="wrap">
			<h1>在庫 ページ診断</h1>
			<p>全在庫を診断（<strong>読み取り専用・データは変更しません</strong>）。判定は実ページの表示解決（複数キー＋ACF）に合わせています。</p>

			<!-- サマリカード -->
			<div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0;">
				<div style="background:#fff;border:1px solid #f0c4bf;border-left:4px solid #d63638;padding:10px 18px;border-radius:6px;">
					<div style="font-size:12px;color:#666;">エラー（要修正）</div>
					<div style="font-size:28px;font-weight:800;color:#d63638;"><?php echo (int) $n_err; ?> 台</div>
				</div>
				<div style="background:#fff;border:1px solid #f0e0a0;border-left:4px solid #dba617;padding:10px 18px;border-radius:6px;">
					<div style="font-size:12px;color:#666;">警告（改善推奨）</div>
					<div style="font-size:28px;font-weight:800;color:#dba617;"><?php echo (int) $n_warn; ?> 台</div>
				</div>
				<div style="background:#fff;border:1px solid #b6e0b6;border-left:4px solid #46b450;padding:10px 18px;border-radius:6px;">
					<div style="font-size:12px;color:#666;">問題なし</div>
					<div style="font-size:28px;font-weight:800;color:#46b450;"><?php echo (int) $n_ok; ?> 台</div>
				</div>
			</div>

			<!-- 表示切替 -->
			<p>
				<a class="button <?php echo 'errors' === $filter ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'cmb_filter' => 'errors', 'cmb_reason' => $reason_filter ), $base_url ) ); ?>">エラーのみ</a>
				<a class="button <?php echo 'issues' === $filter ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'cmb_filter' => 'issues', 'cmb_reason' => $reason_filter ), $base_url ) ); ?>">エラー＋警告</a>
				<a class="button <?php echo 'all' === $filter ? 'button-primary' : ''; ?>"   href="<?php echo esc_url( add_query_arg( array( 'cmb_filter' => 'all',    'cmb_reason' => $reason_filter ), $base_url ) ); ?>">全件</a>
				<?php if ( $reason_filter ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'cmb_filter' => $filter, 'cmb_reason' => '' ), $base_url ) ); ?>" style="color:#a30000;">✕ 絞り込み解除：<?php echo esc_html( $reason_filter ); ?></a>
				<?php endif; ?>
			</p>

			<!-- 問題の内訳（クリックで絞り込み） -->
			<?php if ( ! empty( $reason_count ) ) : ?>
				<h2 style="margin-bottom:6px;">問題の内訳（クリックで絞り込み）</h2>
				<p style="font-size:13px;line-height:2.2;">
				<?php foreach ( $reason_count as $r => $cnt ) :
					$active = ( $r === $reason_filter );
					$url    = esc_url( add_query_arg( array( 'cmb_filter' => $filter, 'cmb_reason' => $r ), $base_url ) );
					$hint   = isset( $hints[ $r ] ) ? $hints[ $r ] : null;
					$has_tool = $hint && '' !== $hint[1];
				?>
					<a href="<?php echo $url; ?>" style="display:inline-block;text-decoration:none;border-radius:14px;padding:3px 12px;margin:2px;font-size:13px;
						<?php echo $active ? 'background:#1f6feb;color:#fff;border:1px solid #1f6feb;' : 'background:#f3f6fb;color:#444;border:1px solid #cfd8e3;'; ?>">
						<?php echo esc_html( $r ); ?>：<strong><?php echo (int) $cnt; ?></strong>
						<?php echo $has_tool ? ' 🔧' : ''; ?>
					</a>
				<?php endforeach; ?>
				</p>

				<!-- 対処ヒント（絞り込み中のみ表示） -->
				<?php if ( $reason_filter && isset( $hints[ $reason_filter ] ) ) :
					$h = $hints[ $reason_filter ];
				?>
					<div style="background:#eef4ff;border:1px solid #b8d4ff;border-radius:6px;padding:12px 16px;max-width:820px;margin-bottom:16px;">
						<strong>「<?php echo esc_html( $reason_filter ); ?>」の対処方法：</strong>
						<?php echo esc_html( $h[0] ); ?>
						<?php if ( '' !== $h[1] ) : ?>
							<a class="button button-small" href="<?php echo esc_url( $h[1] ); ?>" style="margin-left:10px;">関連ツールを開く →</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- 車両リスト -->
			<h2>該当車両（<?php echo count( $rows ); ?> 台<?php echo $reason_filter ? '：' . esc_html( $reason_filter ) . 'のみ' : ''; ?>）</h2>
			<table class="widefat striped" style="max-width:1100px;">
				<thead><tr><th style="width:70px;">状態</th><th>車両</th><th>問題</th><th style="width:90px;">操作</th></tr></thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?><tr><td colspan="4"><em>該当なし。</em></td></tr><?php endif; ?>
				<?php foreach ( $rows as $row ) :
					$pid = $row['pid'];
					$badge = $row['e']
						? '<span style="background:#d63638;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">エラー</span>'
						: ( $row['w']
							? '<span style="background:#dba617;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">警告</span>'
							: '<span style="background:#46b450;color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;">OK</span>' );
					$tags = '';
					foreach ( $row['c']['errors'] as $r ) {
						$url_r = esc_url( add_query_arg( array( 'cmb_filter' => $filter, 'cmb_reason' => $r ), $base_url ) );
						$active_r = ( $r === $reason_filter ) ? 'font-weight:700;' : '';
						$tags .= '<a href="' . $url_r . '" style="display:inline-block;background:#fdeaea;color:#a30000;border-radius:10px;padding:1px 9px;margin:2px;font-size:12px;text-decoration:none;' . $active_r . '">' . esc_html( $r ) . '</a>';
					}
					foreach ( $row['c']['warns'] as $r ) {
						$url_r = esc_url( add_query_arg( array( 'cmb_filter' => $filter, 'cmb_reason' => $r ), $base_url ) );
						$tags .= '<a href="' . $url_r . '" style="display:inline-block;background:#fbf6e3;color:#8a6d00;border-radius:10px;padding:1px 9px;margin:2px;font-size:12px;text-decoration:none;">' . esc_html( $r ) . '</a>';
					}
				?>
					<tr>
						<td><?php echo $badge; ?></td>
						<td style="font-size:13px;">
							<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: '(無題 #' . $pid . ')' ); ?></a>
						</td>
						<td><?php echo $tags ?: '<span style="color:#46b450;">問題なし</span>'; ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">編集</a>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'edit.php?post_type=portfolio&page=carmel-car-inspect&car=' . $pid ) ); ?>">確認</a>
						</td>
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
