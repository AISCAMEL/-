<?php
/**
 * Plugin Name: カーメル 〜修正（年式）
 * Description: 年式に残った「〜2010」などの波ダッシュを正しい西暦に一括修正します。タイトルの和暦（平成20年など）から西暦を自動計算。割り出せない場合は「〜」を除去。編集画面を通さず直接更新するので、STEP UIの上書きや保存データ保護に邪魔されません。プレビュー付き・変化する値のみ更新。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「〜修正」を開く → プレビュー → 実行。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Tilde_Fix' ) ) {

class Carmel_Tilde_Fix {

	/* 年式が入る候補キー */
	private $keys = array( 'year', 'nenshiki' );

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'〜修正',
			'〜修正',
			'manage_options',
			'carmel-tilde-fix',
			array( $this, 'render' )
		);
	}

	private function has_tilde( $v ) {
		return is_string( $v ) && preg_match( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', $v );
	}

	private function han( $v ) {
		return function_exists( 'mb_convert_kana' ) ? mb_convert_kana( (string) $v, 'n', 'UTF-8' ) : (string) $v;
	}

	/* タイトル等から正しい西暦を割り出す（0なら不明） */
	private function derive_year( $pid ) {
		$title = $this->han( get_the_title( $pid ) );

		// 令和 / 平成 / 昭和（元年=1）
		if ( preg_match( '/令和\s*(元|\d{1,2})\s*年?/u', $title, $m ) ) {
			$n = ( '元' === $m[1] ) ? 1 : (int) $m[1]; return 2018 + $n; // 令和1=2019
		}
		if ( preg_match( '/平成\s*(元|\d{1,2})\s*年?/u', $title, $m ) ) {
			$n = ( '元' === $m[1] ) ? 1 : (int) $m[1]; return 1988 + $n; // 平成1=1989
		}
		if ( preg_match( '/昭和\s*(元|\d{1,2})\s*年?/u', $title, $m ) ) {
			$n = ( '元' === $m[1] ) ? 1 : (int) $m[1]; return 1925 + $n; // 昭和1=1926
		}
		// 西暦4桁
		if ( preg_match( '/(19\d{2}|20\d{2})/', $title, $m ) ) { return (int) $m[1]; }

		return 0;
	}

	/* この車の修正案： array(key, from, to) または null */
	private function plan_one( $pid ) {
		foreach ( $this->keys as $k ) {
			$cur = get_post_meta( $pid, $k, true );
			if ( ! is_string( $cur ) || ! $this->has_tilde( $cur ) ) { continue; }

			$y = $this->derive_year( $pid );
			if ( $y > 0 ) {
				$new = $y . '年';
			} else {
				// 西暦が割り出せない → 「〜」だけ除去＋半角化
				$new = trim( preg_replace( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', '', $this->han( $cur ) ) );
			}
			if ( '' !== $new && $new !== $cur ) {
				return array( 'key' => $k, 'from' => $cur, 'to' => $new );
			}
		}
		return null;
	}

	private function save( $pid, $key, $val ) {
		if ( function_exists( 'update_field' ) ) { update_field( $key, $val, $pid ); }
		update_post_meta( $pid, $key, $val );
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

		$did = false; $applied = 0;
		if ( isset( $_POST['ctf_apply'] ) && check_admin_referer( 'ctf_apply' ) ) {
			foreach ( $this->all_ids() as $pid ) {
				$p = $this->plan_one( $pid );
				if ( $p ) { $this->save( $pid, $p['key'], $p['to'] ); $applied++; }
			}
			$did = true;
		}

		$rows = array();
		foreach ( $this->all_ids() as $pid ) {
			$p = $this->plan_one( $pid );
			if ( $p ) { $rows[] = array( 'pid' => $pid, 'p' => $p ); }
		}
		?>
		<div class="wrap">
			<h1>〜修正（年式）</h1>
			<p>年式に残った「〜」を、タイトルの和暦から正しい西暦に直します。<strong>編集画面を通さず直接更新</strong>するので、上書きや保護に邪魔されません。実行前に UpdraftPlus でバックアップ推奨。</p>

			<?php if ( $did ) : ?><div class="notice notice-success"><p><?php echo (int) $applied; ?> 台の年式を修正しました。</p></div><?php endif; ?>

			<h2>修正対象：<strong style="color:#1f6feb;"><?php echo count( $rows ); ?></strong> 台</h2>

			<?php if ( empty( $rows ) ) : ?>
				<div class="notice notice-success" style="max-width:900px;"><p>年式に「〜」が残っている車はありません。</p></div>
			<?php else : ?>
				<form method="post" style="margin:12px 0;">
					<table class="widefat striped" style="max-width:1000px;">
						<thead><tr><th>車両</th><th>項目</th><th>修正前 → 修正後</th></tr></thead>
						<tbody>
						<?php foreach ( $rows as $r ) : $pid = $r['pid']; $p = $r['p']; ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: '#' . $pid ); ?></a></td>
								<td>年式（<?php echo esc_html( $p['key'] ); ?>）</td>
								<td><span style="color:#a30000;"><?php echo esc_html( $p['from'] ); ?></span> <strong style="color:#1f6feb;">→</strong> <strong style="color:#46802b;"><?php echo esc_html( $p['to'] ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php wp_nonce_field( 'ctf_apply' ); ?>
					<p><button class="button button-primary button-hero" name="ctf_apply" value="1"
						onclick="return confirm('<?php echo count( $rows ); ?> 台の年式を修正します。よろしいですか？');">
						今すぐ <?php echo count( $rows ); ?> 台を修正する</button></p>
					<p style="color:#666;font-size:12px;">※ タイトルから西暦が割り出せない車は「〜」だけ除去します。</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}

new Carmel_Tilde_Fix();

}
