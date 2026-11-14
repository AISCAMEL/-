<?php
/**
 * カーメル：画像最適化（新規アップ自動＋既存の一括最適化）
 * ---------------------------------------------------------------------------
 * 目的：ページが重い最大の原因「未圧縮・特大画像」を軽量化する。
 *   ・新規アップロード：大きすぎる画像を自動で上限幅にリサイズ＋JPEG品質を最適化。
 *   ・既存の一括最適化：特大画像をリサイズ＋再圧縮。写真のPNGはJPEGへ変換して
 *     本文中のURLも自動置換。元画像は必ずバックアップし「元に戻す」で復元可能。
 *
 * 安全設計：承認制（オーナーがボタンを押した分だけ実行）。バックアップ＆ワンクリック復元。
 *   ※ ライブ反映前に必ずコピー(grissom)で確認してください（DB内の画像URLを置換するため）。
 * 管理者専用。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'CARMEL_IO_OPT' ) ) { define( 'CARMEL_IO_OPT', 'carmel_io_opts' ); }

if ( ! function_exists( 'carmelx_io_opts' ) ) {
	function carmelx_io_opts() {
		$o = get_option( CARMEL_IO_OPT );
		return array(
			'auto_new'    => isset( $o['auto_new'] ) ? (int) $o['auto_new'] : 0,
			'max_w'       => isset( $o['max_w'] ) ? max( 600, (int) $o['max_w'] ) : 1600,
			'quality'     => isset( $o['quality'] ) ? min( 95, max( 50, (int) $o['quality'] ) ) : 82,
			'convert_png' => isset( $o['convert_png'] ) ? (int) $o['convert_png'] : 1,
		);
	}
}

/* ========== 新規アップロードの自動最適化（安全・URL変化なし） ========== */
if ( ! function_exists( 'carmelx_io_hook_new' ) ) {
	function carmelx_io_hook_new() {
		$o = carmelx_io_opts();
		if ( empty( $o['auto_new'] ) ) { return; }
		add_filter( 'big_image_size_threshold', function () use ( $o ) { return (int) $o['max_w']; } );
		add_filter( 'jpeg_quality', function () use ( $o ) { return (int) $o['quality']; } );
		add_filter( 'wp_editor_set_quality', function () use ( $o ) { return (int) $o['quality']; } );
	}
	add_action( 'init', 'carmelx_io_hook_new', 5 );
}

/* ========== バックアップ場所 ========== */
if ( ! function_exists( 'carmelx_io_backup_dir' ) ) {
	function carmelx_io_backup_dir() {
		$up = wp_get_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . 'carmel-io-backup';
		if ( ! file_exists( $dir ) ) { wp_mkdir_p( $dir ); }
		return $dir;
	}
}

/* ========== 最適化対象の候補（特大画像）を探す ========== */
if ( ! function_exists( 'carmelx_io_candidates' ) ) {
	function carmelx_io_candidates( $limit = 500 ) {
		$ids = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => $limit, 'post_status' => 'inherit', 'fields' => 'ids' ) );
		$o = carmelx_io_opts();
		$out = array();
		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) { continue; }
			$kb = (int) round( filesize( $file ) / 1024 );
			$meta = wp_get_attachment_metadata( $id );
			$w = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$mime = get_post_mime_type( $id );
			$big = ( $kb >= 300 ) || ( $w > $o['max_w'] ) || ( $mime === 'image/png' && $kb >= 300 );
			if ( $big ) { $out[] = array( 'id' => $id, 'kb' => $kb, 'w' => $w, 'mime' => $mime ); }
		}
		usort( $out, function ( $a, $b ) { return $b['kb'] <=> $a['kb']; } );
		return $out;
	}
}

/* ========== 1枚を最適化（バックアップ→リサイズ/再圧縮→必要ならPNG→JPEG＋URL置換） ========== */
if ( ! function_exists( 'carmelx_io_optimize_one' ) ) {
	function carmelx_io_optimize_one( $id ) {
		global $wpdb;
		$o    = carmelx_io_opts();
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) { return array( 'ok' => false, 'msg' => 'ファイルなし' ); }
		$mime = get_post_mime_type( $id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) { return array( 'ok' => false, 'msg' => '対象外' ); }

		$before = filesize( $file );

		// すでにバックアップ済みならスキップ（二重最適化防止）
		$bdir = carmelx_io_backup_dir() . '/' . $id;
		if ( file_exists( $bdir . '/done.json' ) ) { return array( 'ok' => false, 'msg' => '最適化済み' ); }
		wp_mkdir_p( $bdir );

		$old_url  = wp_get_attachment_url( $id );
		$old_meta = wp_get_attachment_metadata( $id );
		$old_attached = get_post_meta( $id, '_wp_attached_file', true );

		// バックアップ（元ファイル＋情報）
		@copy( $file, $bdir . '/' . basename( $file ) );
		file_put_contents( $bdir . '/info.json', wp_json_encode( array(
			'file' => $file, 'url' => $old_url, 'attached' => $old_attached, 'mime' => $mime, 'meta' => $old_meta,
		), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) { return array( 'ok' => false, 'msg' => '画像処理不可' ); }
		$size = $editor->get_size();
		if ( $o['max_w'] && isset( $size['width'] ) && $size['width'] > $o['max_w'] ) {
			$editor->resize( $o['max_w'], null, false );
		}
		$editor->set_quality( $o['quality'] );

		$convert = ( $mime === 'image/png' && ! empty( $o['convert_png'] ) && ! carmelx_io_png_has_alpha( $file ) );
		$new_file = $file; $new_mime = $mime; $url_changed = false;

		if ( $convert ) {
			$new_file = preg_replace( '/\.png$/i', '.jpg', $file );
			$saved = $editor->save( $new_file, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) { return array( 'ok' => false, 'msg' => '保存失敗' ); }
			$new_file = $saved['path'];
			$new_mime = 'image/jpeg';
			// 旧PNG本体を削除（バックアップ済み）
			if ( $new_file !== $file && file_exists( $file ) ) { @unlink( $file ); }
			$url_changed = true;
		} else {
			$saved = $editor->save( $file, $mime );
			if ( is_wp_error( $saved ) ) { return array( 'ok' => false, 'msg' => '保存失敗' ); }
			$new_file = $saved['path'];
		}

		// 添付のファイル・メタ・MIMEを更新し、サムネイルを再生成
		update_attached_file( $id, $new_file );
		if ( $url_changed ) {
			$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $new_mime, 'guid' => str_ireplace( '.png', '.jpg', $old_url ) ), array( 'ID' => $id ) );
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) { require_once ABSPATH . 'wp-admin/includes/image.php'; }
		$new_meta = wp_generate_attachment_metadata( $id, $new_file );
		wp_update_attachment_metadata( $id, $new_meta );

		// URLが変わった場合のみ、この画像の“具体的なURL”だけを厳密に置換（フル＋各サイズ）。
		// 「.png→.jpg」は4文字→4文字で長さが同じ＝直列化メタも壊れない。全体一括置換は絶対にしない。
		$replaced = 0;
		if ( $url_changed ) {
			$dir_old = trailingslashit( dirname( $old_url ) );
			$olds = array( $old_url );
			if ( ! empty( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
				foreach ( $old_meta['sizes'] as $s ) {
					if ( ! empty( $s['file'] ) ) { $olds[] = $dir_old . $s['file']; }
				}
			}
			foreach ( array_unique( $olds ) as $ou ) {
				$nu = preg_replace( '/\.png$/i', '.jpg', $ou );
				if ( $nu === $ou ) { continue; }
				$replaced += (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $ou, $nu ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s", $ou, $nu, '%' . $wpdb->esc_like( $ou ) . '%' ) );
			}
		}

		$after = file_exists( $new_file ) ? filesize( $new_file ) : $before;
		file_put_contents( $bdir . '/done.json', wp_json_encode( array( 'before' => $before, 'after' => $after, 'url_changed' => $url_changed, 'time' => time() ) ) );

		return array( 'ok' => true, 'before' => $before, 'after' => $after, 'converted' => $url_changed, 'replaced' => $replaced );
	}
}

/* PNGに透過（アルファ）が実際に使われているか簡易判定 */
if ( ! function_exists( 'carmelx_io_png_has_alpha' ) ) {
	function carmelx_io_png_has_alpha( $file ) {
		if ( ! function_exists( 'imagecreatefrompng' ) ) { return true; } // 不明なら安全側（変換しない）
		$c = ord( @file_get_contents( $file, false, null, 25, 1 ) ); // IHDR color type byte
		// color type 4(gray+alpha) or 6(rgba) はアルファあり
		return in_array( $c, array( 4, 6 ), true );
	}
}

/* ========== 1枚を復元（バックアップから元に戻す） ========== */
if ( ! function_exists( 'carmelx_io_restore_one' ) ) {
	function carmelx_io_restore_one( $id ) {
		global $wpdb;
		$bdir = carmelx_io_backup_dir() . '/' . $id;
		if ( ! file_exists( $bdir . '/info.json' ) ) { return array( 'ok' => false, 'msg' => 'バックアップなし' ); }
		$info = json_decode( (string) file_get_contents( $bdir . '/info.json' ), true );
		if ( ! is_array( $info ) || empty( $info['file'] ) ) { return array( 'ok' => false, 'msg' => '情報破損' ); }

		$orig_file = $info['file'];
		$backup    = $bdir . '/' . basename( $orig_file );
		if ( ! file_exists( $backup ) ) { return array( 'ok' => false, 'msg' => '元ファイルなし' ); }

		// 変換後(JPEG)のファイル群を削除
		$cur_file = get_attached_file( $id );
		$cur_meta = wp_get_attachment_metadata( $id );
		if ( $cur_file && file_exists( $cur_file ) && $cur_file !== $orig_file ) { @unlink( $cur_file ); }
		if ( ! empty( $cur_meta['sizes'] ) && is_array( $cur_meta['sizes'] ) && $cur_file ) {
			$cdir = trailingslashit( dirname( $cur_file ) );
			foreach ( $cur_meta['sizes'] as $s ) { if ( ! empty( $s['file'] ) && file_exists( $cdir . $s['file'] ) ) { @unlink( $cdir . $s['file'] ); } }
		}

		// 元ファイルを戻す
		@copy( $backup, $orig_file );
		update_attached_file( $id, $orig_file );
		$wpdb->update( $wpdb->posts, array( 'post_mime_type' => $info['mime'], 'guid' => $info['url'] ), array( 'ID' => $id ) );
		if ( ! empty( $info['meta'] ) && is_array( $info['meta'] ) ) { wp_update_attachment_metadata( $id, $info['meta'] ); }
		// サムネイルを再生成（元画像から）
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) { require_once ABSPATH . 'wp-admin/includes/image.php'; }
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $orig_file ) );

		// URL置換を逆戻し（この画像の .jpg → .png）
		$done = json_decode( (string) @file_get_contents( $bdir . '/done.json' ), true );
		if ( is_array( $done ) && ! empty( $done['url_changed'] ) ) {
			$dir_old = trailingslashit( dirname( $info['url'] ) );
			$olds = array( $info['url'] );
			if ( ! empty( $info['meta']['sizes'] ) && is_array( $info['meta']['sizes'] ) ) {
				foreach ( $info['meta']['sizes'] as $s ) { if ( ! empty( $s['file'] ) ) { $olds[] = $dir_old . $s['file']; } }
			}
			foreach ( array_unique( $olds ) as $ou ) {
				$nu = preg_replace( '/\.png$/i', '.jpg', $ou );
				if ( $nu === $ou ) { continue; }
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s)", $nu, $ou ) );
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_value LIKE %s", $nu, $ou, '%' . $wpdb->esc_like( $nu ) . '%' ) );
			}
		}
		// バックアップ削除
		array_map( 'unlink', array_filter( glob( $bdir . '/*' ) ?: array(), 'is_file' ) );
		@rmdir( $bdir );
		return array( 'ok' => true );
	}
}

/* 最適化済み件数 */
if ( ! function_exists( 'carmelx_io_done_ids' ) ) {
	function carmelx_io_done_ids() {
		$base = carmelx_io_backup_dir();
		$ids = array();
		foreach ( glob( $base . '/*', GLOB_ONLYDIR ) ?: array() as $d ) {
			if ( file_exists( $d . '/done.json' ) ) { $ids[] = (int) basename( $d ); }
		}
		return $ids;
	}
}

/* ========== 管理ページ（ツール → 画像最適化）オーナー専用 ========== */
if ( ! function_exists( 'carmelx_io_add_page' ) ) {
	function carmelx_io_add_page() {
		add_management_page( '画像最適化', '画像最適化', 'manage_options', 'carmel-image-optimize', 'carmelx_io_render' );
	}
	add_action( 'admin_menu', 'carmelx_io_add_page' );
}

if ( ! function_exists( 'carmelx_io_render' ) ) {
	function carmelx_io_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$batch_results = array(); $msg = '';
		if ( isset( $_POST['carmel_io_nonce'] ) && wp_verify_nonce( $_POST['carmel_io_nonce'], 'carmel_io' ) ) {
			if ( isset( $_POST['carmel_io_save'] ) ) {
				update_option( CARMEL_IO_OPT, array(
					'auto_new'    => isset( $_POST['auto_new'] ) ? 1 : 0,
					'max_w'       => (int) ( $_POST['max_w'] ?? 1600 ),
					'quality'     => (int) ( $_POST['quality'] ?? 82 ),
					'convert_png' => isset( $_POST['convert_png'] ) ? 1 : 0,
				) );
				$msg = '設定を保存しました。';
			} elseif ( isset( $_POST['carmel_io_run'] ) ) {
				@set_time_limit( 180 );
				$done = array_flip( carmelx_io_done_ids() );
				$n = 0;
				foreach ( carmelx_io_candidates( 500 ) as $c ) {
					if ( isset( $done[ $c['id'] ] ) ) { continue; }
					$r = carmelx_io_optimize_one( $c['id'] );
					if ( ! empty( $r['ok'] ) ) { $r['id'] = $c['id']; $batch_results[] = $r; $n++; }
					if ( $n >= 6 ) { break; }
				}
				$msg = $n ? ( $n . '件を最適化しました。' ) : '最適化対象は残っていません。';
			} elseif ( isset( $_POST['carmel_io_restore'] ) ) {
				$cnt = 0; foreach ( carmelx_io_done_ids() as $rid ) { $rr = carmelx_io_restore_one( $rid ); if ( ! empty( $rr['ok'] ) ) { $cnt++; } }
				$msg = $cnt . '件を元に戻しました。';
			}
		}
		$o = carmelx_io_opts();
		$cands = carmelx_io_candidates( 500 );
		$done_ids = array_flip( carmelx_io_done_ids() );
		$remaining = 0; $rem_kb = 0;
		foreach ( $cands as $c ) { if ( ! isset( $done_ids[ $c['id'] ] ) ) { $remaining++; $rem_kb += $c['kb']; } }
		$done_count = count( $done_ids );
		?>
		<div class="wrap">
			<h1>画像最適化</h1>
			<?php if ( $msg ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<div class="notice notice-warning" style="max-width:860px;"><p><strong>重要：</strong>一括最適化は画像を再圧縮し、写真のPNGはJPEGに変換して<strong>本文中の画像URLも置換</strong>します。元画像はバックアップし「元に戻す」で復元できますが、<strong>必ず先にコピー(grissom)で確認</strong>してから本番に適用してください。</p></div>

			<h2>設定</h2>
			<form method="post">
				<?php wp_nonce_field( 'carmel_io', 'carmel_io_nonce' ); ?>
				<table class="form-table" style="max-width:720px;">
					<tr><th>新規アップロードの自動最適化</th><td><label><input type="checkbox" name="auto_new" value="1" <?php checked( $o['auto_new'] ); ?>> これから上げる画像を自動でリサイズ＋圧縮する（安全・URLは変わりません）</label></td></tr>
					<tr><th>最大の横幅</th><td><input type="number" name="max_w" value="<?php echo esc_attr( $o['max_w'] ); ?>" min="600" max="4000" style="width:100px;"> px <span style="color:#888;">（これより大きい画像は縮小。目安1600）</span></td></tr>
					<tr><th>画質（JPEG）</th><td><input type="number" name="quality" value="<?php echo esc_attr( $o['quality'] ); ?>" min="50" max="95" style="width:80px;"> <span style="color:#888;">（50〜95。82が軽さと画質のバランス良）</span></td></tr>
					<tr><th>写真のPNGをJPEGに変換</th><td><label><input type="checkbox" name="convert_png" value="1" <?php checked( $o['convert_png'] ); ?>> 透過のない写真PNGをJPEGへ（大幅に軽くなります／URL置換あり）</label></td></tr>
				</table>
				<p><button type="submit" name="carmel_io_save" value="1" class="button button-primary">設定を保存</button></p>
			</form>

			<h2 style="margin-top:24px;">既存画像の一括最適化</h2>
			<p>最適化候補：<strong><?php echo esc_html( $remaining ); ?></strong>件（約 <?php echo esc_html( $rem_kb >= 1024 ? round( $rem_kb / 1024, 1 ) . 'MB' : $rem_kb . 'KB' ); ?>）／ 最適化済み：<?php echo esc_html( $done_count ); ?>件</p>
			<form method="post" style="display:inline;">
				<?php wp_nonce_field( 'carmel_io', 'carmel_io_nonce' ); ?>
				<button type="submit" name="carmel_io_run" value="1" class="button button-primary" <?php disabled( $remaining === 0 ); ?>>次の6件を最適化する</button>
			</form>
			<form method="post" style="display:inline;margin-left:8px;">
				<?php wp_nonce_field( 'carmel_io', 'carmel_io_nonce' ); ?>
				<button type="submit" name="carmel_io_restore" value="1" class="button" onclick="return confirm('最適化した画像をすべて元に戻します。よろしいですか？');" <?php disabled( $done_count === 0 ); ?>>元に戻す（すべて復元）</button>
			</form>
			<p style="color:#888;font-size:12px;">※ 一度に6件ずつ処理します。ボタンを繰り返し押して進めてください（サーバー負荷・タイムアウト対策）。</p>

			<?php if ( $batch_results ) : ?>
			<h3>今回の結果</h3>
			<table class="widefat striped" style="max-width:640px;">
				<thead><tr><th>画像ID</th><th>前</th><th>後</th><th>削減</th><th>変換</th></tr></thead>
				<tbody>
				<?php $tb = 0; $ta = 0; foreach ( $batch_results as $r ) : $tb += $r['before']; $ta += $r['after']; ?>
					<tr>
						<td><?php echo (int) $r['id']; ?></td>
						<td><?php echo esc_html( round( $r['before'] / 1024 ) ); ?>KB</td>
						<td><?php echo esc_html( round( $r['after'] / 1024 ) ); ?>KB</td>
						<td style="color:#1c7a3a;font-weight:700;"><?php echo esc_html( $r['before'] > 0 ? round( ( 1 - $r['after'] / $r['before'] ) * 100 ) : 0 ); ?>%</td>
						<td><?php echo ! empty( $r['converted'] ) ? 'PNG→JPEG' : '再圧縮'; ?></td>
					</tr>
				<?php endforeach; ?>
					<tr><th>合計</th><th><?php echo esc_html( round( $tb / 1024 ) ); ?>KB</th><th><?php echo esc_html( round( $ta / 1024 ) ); ?>KB</th><th style="color:#1c7a3a;"><?php echo esc_html( $tb > 0 ? round( ( 1 - $ta / $tb ) * 100 ) : 0 ); ?>%</th><th></th></tr>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
