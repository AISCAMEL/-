<?php
/**
 * Plugin Name: カーメル 〜チェック
 * Description: 全在庫を総当たりでスキャンし、「〜」(波ダッシュ/全角チルダ)が残っている項目をすべて一覧化します（読み取り専用・データ変更なし）。どのページのどの項目に何件あるかを把握できます。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「〜チェック」を開く。読み取り専用。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Tilde_Scan' ) ) {

class Carmel_Tilde_Scan {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'〜チェック',
			'〜チェック',
			'manage_options',
			'carmel-tilde-scan',
			array( $this, 'render' )
		);
	}

	/* 波ダッシュ系の検出：〜(U+301C) ～(U+FF5E) ⁓(U+2053) ∼(U+223C) */
	private function has_tilde( $v ) {
		return is_string( $v ) && preg_match( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', $v );
	}

	/* 見やすいラベル（主要フィールドのみ。なければキーそのまま） */
	private function label( $key ) {
		$map = array(
			'price'    => '本体価格',
			'est_total'=> '支払総額',
			'total'    => '月々',
			'year'     => '年式',
			'nenshiki' => '年式',
			'mileage'  => '走行距離',
			'soukou'   => '走行距離',
			'kyori'    => '走行距離',
			'displacement' => '排気量',
			'marker'   => 'メーカー',
			'type'     => '車種・型式',
			'shaken'   => '車検',
			'tel'      => '電話番号',
			'color'    => 'ボディカラー',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] . '（' . $key . '）' : $key;
	}

	private function clip( $v, $n = 80 ) {
		$v = (string) $v;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $v, 'UTF-8' ) > $n ) {
			return mb_substr( $v, 0, $n, 'UTF-8' ) . '…';
		}
		return $v;
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

		$ids = $this->all_ids();
		$rows = array();          // pid => array( field => value )
		$field_count = array();   // key => 件数
		$total_hits = 0;

		foreach ( $ids as $pid ) {
			$hits = array();

			// タイトル
			$title = (string) get_the_title( $pid );
			if ( $this->has_tilde( $title ) ) { $hits['__title__'] = $title; }

			// 全メタを総当たり
			$all = get_post_meta( $pid );
			if ( is_array( $all ) ) {
				foreach ( $all as $key => $vals ) {
					foreach ( (array) $vals as $v ) {
						if ( is_string( $v ) && $this->has_tilde( $v ) ) {
							$hits[ $key ] = $v;
							break;
						}
					}
				}
			}

			if ( ! empty( $hits ) ) {
				$rows[ $pid ] = $hits;
				foreach ( $hits as $k => $v ) {
					$field_count[ $k ] = isset( $field_count[ $k ] ) ? $field_count[ $k ] + 1 : 1;
					$total_hits++;
				}
			}
		}
		arsort( $field_count );
		?>
		<div class="wrap">
			<h1>〜チェック（波ダッシュ・全角チルダの残り）</h1>
			<p>全在庫を総当たりでスキャンしました（<strong>読み取り専用・データは変更しません</strong>）。「〜」が残っている項目をすべて表示します。</p>

			<div style="display:flex;gap:14px;flex-wrap:wrap;margin:14px 0;">
				<div style="background:#fff;border:1px solid #f0c4bf;border-left:4px solid #d63638;padding:10px 16px;border-radius:6px;"><div style="font-size:12px;color:#666;">「〜」が残る車両</div><div style="font-size:24px;font-weight:800;color:#d63638;"><?php echo count( $rows ); ?> 台</div></div>
				<div style="background:#fff;border:1px solid #cfd8e3;border-left:4px solid #1f6feb;padding:10px 16px;border-radius:6px;"><div style="font-size:12px;color:#666;">該当項目の合計</div><div style="font-size:24px;font-weight:800;color:#1f6feb;"><?php echo (int) $total_hits; ?> 件</div></div>
				<div style="background:#fff;border:1px solid #b6e0b6;border-left:4px solid #46b450;padding:10px 16px;border-radius:6px;"><div style="font-size:12px;color:#666;">スキャン対象</div><div style="font-size:24px;font-weight:800;color:#46b450;"><?php echo count( $ids ); ?> 台</div></div>
			</div>

			<?php if ( empty( $rows ) ) : ?>
				<div class="notice notice-success" style="max-width:900px;"><p><strong>「〜」は1件も見つかりませんでした。</strong>すべて修正済みです。</p></div>
			<?php else : ?>

				<h2>項目別の件数（多い順）</h2>
				<p style="font-size:13px;line-height:2;">
				<?php foreach ( $field_count as $k => $cnt ) : ?>
					<span style="display:inline-block;background:#f3f6fb;border:1px solid #cfd8e3;border-radius:14px;padding:3px 12px;margin:2px;"><?php echo esc_html( '__title__' === $k ? 'タイトル' : $this->label( $k ) ); ?>：<strong><?php echo (int) $cnt; ?></strong></span>
				<?php endforeach; ?>
				</p>

				<h2>該当車両（<?php echo count( $rows ); ?> 台）</h2>
				<table class="widefat striped" style="max-width:1100px;">
					<thead><tr><th style="width:220px;">車両</th><th>「〜」が入っている項目と値</th><th style="width:70px;">操作</th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $pid => $hits ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank"><?php echo esc_html( get_the_title( $pid ) ?: '(無題 #' . $pid . ')' ); ?></a></td>
							<td>
								<?php foreach ( $hits as $k => $v ) : ?>
									<div style="margin:2px 0;">
										<span style="display:inline-block;background:#fdeaea;color:#a30000;border-radius:10px;padding:1px 9px;font-size:12px;"><?php echo esc_html( '__title__' === $k ? 'タイトル' : $this->label( $k ) ); ?></span>
										<code style="background:#fff7f7;"><?php echo esc_html( $this->clip( $v ) ); ?></code>
									</div>
								<?php endforeach; ?>
							</td>
							<td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">編集</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p style="color:#666;font-size:12px;margin-top:10px;">※ このツールは表示のみです。一括で直す場合は、項目ごとに「削除する」「数値に置き換える」など方針を決めてから修正ツールを用意します。</p>

			<?php endif; ?>
		</div>
		<?php
	}
}

new Carmel_Tilde_Scan();

}
