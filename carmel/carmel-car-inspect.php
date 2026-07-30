<?php
/**
 * Plugin Name: カーメル 車両データ確認
 * Description: 1台の在庫に実際に保存されている全データ（タイトル/本文/全メタ項目/画像）をそのまま表示します（読み取り専用・変更なし）。古い手入力データがどのキーに残っているかを確認し、「本当に消えたか／別キーに残っているか」を判定するための診断ツールです。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化
 *         → 在庫一覧メニューの「車両データ確認」を開く → 車を選ぶ（またはID入力）。読み取り専用。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Car_Inspect' ) ) {

class Carmel_Car_Inspect {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_submenu_page(
			'edit.php?post_type=portfolio',
			'車両データ確認',
			'車両データ確認',
			'manage_options',
			'carmel-car-inspect',
			array( $this, 'render' )
		);
	}

	private function recent( $n = 30 ) {
		return get_posts( array(
			'post_type'      => 'portfolio',
			'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
			'posts_per_page' => $n,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'suppress_filters' => true,
		) );
	}

	/* ID か URL か タイトル から投稿を解決 */
	private function resolve( $q ) {
		$q = trim( (string) $q );
		if ( '' === $q ) { return 0; }
		if ( ctype_digit( $q ) ) { return (int) $q; }
		if ( false !== strpos( $q, 'http' ) ) {
			$id = url_to_postid( $q );
			if ( $id ) { return (int) $id; }
		}
		$p = get_page_by_title( $q, OBJECT, 'portfolio' );
		return $p ? (int) $p->ID : 0;
	}

	private function clip( $v, $n = 300 ) {
		$v = (string) $v;
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $v, 'UTF-8' ) > $n ) {
			return mb_substr( $v, 0, $n, 'UTF-8' ) . ' …（以下省略）';
		}
		return $v;
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$q   = isset( $_GET['car'] ) ? sanitize_text_field( wp_unslash( $_GET['car'] ) ) : '';
		$pid = $this->resolve( $q );
		$base = admin_url( 'edit.php?post_type=portfolio&page=carmel-car-inspect' );
		?>
		<div class="wrap">
			<h1>車両データ確認（読み取り専用）</h1>
			<p>1台に実際に保存されている全データを表示します。<strong>データは一切変更しません。</strong>古い手入力データが残っているか確認できます。</p>

			<form method="get" style="margin:12px 0;padding:12px;border:1px solid #ccd0d4;background:#fff;border-radius:6px;max-width:760px;">
				<input type="hidden" name="post_type" value="portfolio">
				<input type="hidden" name="page" value="carmel-car-inspect">
				<label><strong>車を指定：</strong> ID・URL・タイトルのいずれか
					<input type="text" name="car" value="<?php echo esc_attr( $q ); ?>" style="width:360px;" placeholder="例：1234 / https://… / 三菱 デリカ"></label>
				<button class="button button-primary">表示</button>
			</form>

			<?php if ( '' !== $q && ! $pid ) : ?>
				<div class="notice notice-error"><p>該当する車両が見つかりませんでした。IDで指定すると確実です。</p></div>
			<?php endif; ?>

			<h2>最近更新した在庫（クリックで確認）</h2>
			<p style="font-size:13px;line-height:2;">
			<?php foreach ( $this->recent( 30 ) as $p ) : ?>
				<a href="<?php echo esc_url( $base . '&car=' . $p->ID ); ?>" style="display:inline-block;background:#f3f6fb;border:1px solid #cfd8e3;border-radius:12px;padding:3px 10px;margin:2px;text-decoration:none;">
					#<?php echo (int) $p->ID; ?> <?php echo esc_html( $p->post_title ?: '(無題)' ); ?></a>
			<?php endforeach; ?>
			</p>

			<?php if ( $pid ) :
				$post = get_post( $pid );
				if ( ! $post || 'portfolio' !== $post->post_type ) {
					echo '<div class="notice notice-error"><p>在庫（portfolio）ではありません。</p></div></div>';
					return;
				}
				$all = get_post_meta( $pid );
				ksort( $all );
				$visible = array(); $internal = array();
				foreach ( $all as $k => $vals ) {
					if ( '_' === substr( $k, 0, 1 ) ) { $internal[ $k ] = $vals; }
					else { $visible[ $k ] = $vals; }
				}
				$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
				?>
				<hr>
				<h2>#<?php echo (int) $pid; ?> <?php echo esc_html( $post->post_title ?: '(無題)' ); ?>
					<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">編集画面</a>
					<a class="button button-small" href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank">表示ページ</a>
				</h2>

				<table class="widefat striped" style="max-width:1000px;margin-bottom:18px;">
					<tbody>
						<tr><th style="width:200px;">タイトル(post_title)</th><td><?php echo $post->post_title ? esc_html( $post->post_title ) : '<span style="color:#d63638;font-weight:700;">（空）</span>'; ?></td></tr>
						<tr><th>状態(post_status)</th><td><?php echo esc_html( $post->post_status ); ?></td></tr>
						<tr><th>更新日時</th><td><?php echo esc_html( $post->post_modified ); ?></td></tr>
						<tr><th>アイキャッチ画像</th><td><?php echo $thumb ? '<img src="' . esc_url( $thumb ) . '" style="height:48px;border-radius:4px;">' : '<span style="color:#d63638;">なし</span>'; ?></td></tr>
						<tr><th>本文(post_content)</th><td><?php echo $post->post_content ? '<code style="white-space:pre-wrap;">' . esc_html( $this->clip( $post->post_content, 600 ) ) . '</code>' : '<span style="color:#888;">（空）</span>'; ?></td></tr>
					</tbody>
				</table>

				<h3>入力項目（メタ）：<?php echo count( $visible ); ?> 個</h3>
				<?php if ( empty( $visible ) ) : ?>
					<div class="notice notice-warning" style="max-width:1000px;"><p><strong>表示用の入力項目が1つもありません。</strong>この車は中身が空＝復元（バックアップ/リビジョン）が必要な可能性があります。</p></div>
				<?php else : ?>
					<table class="widefat striped" style="max-width:1000px;">
						<thead><tr><th style="width:240px;">キー（保存場所）</th><th>値</th></tr></thead>
						<tbody>
						<?php foreach ( $visible as $k => $vals ) : ?>
							<tr>
								<td><code><?php echo esc_html( $k ); ?></code></td>
								<td><?php
									$out = array();
									foreach ( (array) $vals as $v ) { $out[] = esc_html( $this->clip( is_string( $v ) ? $v : wp_json_encode( $v ) ) ); }
									echo implode( '<br>', $out );
								?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<?php if ( ! empty( $internal ) ) : ?>
					<h3 style="margin-top:18px;">内部項目（_ 始まり：<?php echo count( $internal ); ?> 個）</h3>
					<details><summary style="cursor:pointer;color:#1f6feb;">クリックで展開（参考用）</summary>
					<table class="widefat striped" style="max-width:1000px;margin-top:8px;">
						<tbody>
						<?php foreach ( $internal as $k => $vals ) : ?>
							<tr><td style="width:240px;"><code><?php echo esc_html( $k ); ?></code></td>
							<td><?php
								$out = array();
								foreach ( (array) $vals as $v ) { $out[] = esc_html( $this->clip( is_string( $v ) ? $v : wp_json_encode( $v ), 120 ) ); }
								echo implode( '<br>', $out );
							?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					</details>
				<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}
}

new Carmel_Car_Inspect();

}
