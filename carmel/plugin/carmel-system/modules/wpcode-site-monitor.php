<?php
/**
 * カーメル：サイト健康モニター（重いページ検出＋安全な軽量化）
 * ---------------------------------------------------------------------------
 * ・主要ページを巡回して「重さ」（HTMLサイズ・画像/スクリプト/CSSの数・取得時間・
 *   画像の合計容量サンプル）を計測し、重い順に一覧表示。原因も表示。
 * ・メディア内の“特大画像”を検出して圧縮/差し替えを推奨（自動では触らない）。
 * ・安全な軽量化トグル（絵文字スクリプト停止／oEmbed埋め込みJS停止）はオーナーが
 *   ONにしたものだけ適用（＝承認制）。見た目は変えません。
 * ・AI診断は将来追加予定（本モジュールはAPIキー不要・無料）。
 * 管理者専用。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'CARMEL_SM_OPT' ) )  { define( 'CARMEL_SM_OPT', 'carmel_sm_opts' ); }
if ( ! defined( 'CARMEL_SM_DATA' ) ) { define( 'CARMEL_SM_DATA', 'carmel_sm_results' ); }

/* ========== 計測（WP非依存の純ロジック：テスト可能） ========== */
if ( ! function_exists( 'carmelx_sm_parse' ) ) {
	function carmelx_sm_parse( $html, $base = '' ) {
		$r = array( 'html_bytes' => strlen( $html ), 'img' => 0, 'script' => 0, 'style' => 0, 'inline_css' => 0, 'assets' => array() );
		$r['img']    = preg_match_all( '/<img\b[^>]*>/i', $html );
		$r['script'] = preg_match_all( '/<script\b[^>]*\bsrc=/i', $html );
		$r['style']  = preg_match_all( '/<link\b[^>]*stylesheet[^>]*>/i', $html );
		$r['inline_css'] = preg_match_all( '/<style\b[^>]*>/i', $html );
		$urls = array();
		if ( preg_match_all( '/<img\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $m ) )  { $urls = array_merge( $urls, $m[1] ); }
		if ( preg_match_all( '/<script\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $html, $m ) ){ $urls = array_merge( $urls, $m[1] ); }
		if ( preg_match_all( '/<link\b[^>]*stylesheet[^>]*\bhref=["\']([^"\']+)["\']/i', $html, $m ) ) { $urls = array_merge( $urls, $m[1] ); }
		if ( preg_match_all( '/<link\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*stylesheet/i', $html, $m ) )  { $urls = array_merge( $urls, $m[1] ); }
		// 絶対URL化＆重複除去
		$abs = array();
		foreach ( $urls as $u ) {
			$u = trim( html_entity_decode( $u ) );
			if ( $u === '' || strpos( $u, 'data:' ) === 0 ) { continue; }
			if ( strpos( $u, '//' ) === 0 ) { $u = 'https:' . $u; }
			elseif ( $u[0] === '/' && $base ) { $u = rtrim( $base, '/' ) . $u; }
			$abs[ $u ] = 1;
		}
		$r['assets'] = array_keys( $abs );
		return $r;
	}
}

/* 重さの判定（flags） */
if ( ! function_exists( 'carmelx_sm_flags' ) ) {
	function carmelx_sm_flags( $row ) {
		$f = array();
		if ( $row['total_kb'] >= 3000 ) { $f[] = 'ページが重い（' . round( $row['total_kb'] / 1024, 1 ) . 'MB）'; }
		elseif ( $row['total_kb'] >= 1500 ) { $f[] = 'やや重い（' . round( $row['total_kb'] / 1024, 1 ) . 'MB）'; }
		if ( $row['img_kb'] >= 2000 ) { $f[] = '画像が大きい（' . round( $row['img_kb'] / 1024, 1 ) . 'MB）'; }
		if ( $row['req'] >= 60 ) { $f[] = '読み込み要素が多い（' . $row['req'] . '件）'; }
		if ( $row['ms'] >= 1500 ) { $f[] = '応答が遅い（' . $row['ms'] . 'ms）'; }
		if ( $row['html_kb'] >= 400 ) { $f[] = 'HTMLが大きい（' . $row['html_kb'] . 'KB）'; }
		return $f;
	}
}

/* ========== WP依存：取得・巡回 ========== */
if ( ! function_exists( 'carmelx_sm_head_bytes' ) ) {
	function carmelx_sm_head_bytes( $url ) {
		$res = wp_remote_head( $url, array( 'timeout' => 4, 'redirection' => 2, 'sslverify' => true ) );
		if ( is_wp_error( $res ) ) { return 0; }
		$len = wp_remote_retrieve_header( $res, 'content-length' );
		return $len ? (int) $len : 0;
	}
}

if ( ! function_exists( 'carmelx_sm_scan_url' ) ) {
	function carmelx_sm_scan_url( $url ) {
		$t0  = microtime( true );
		$res = wp_remote_get( $url, array( 'timeout' => 12, 'redirection' => 3, 'sslverify' => true, 'user-agent' => 'CarmelSiteMonitor/1.0' ) );
		$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( is_wp_error( $res ) ) {
			return array( 'url' => $url, 'error' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$html = (string) wp_remote_retrieve_body( $res );
		$p    = carmelx_sm_parse( $html, home_url() );

		// 画像の合計容量をサンプル計測（上位20件まで・短時間）
		$img_bytes = 0; $sampled = 0; $base = home_url();
		foreach ( $p['assets'] as $a ) {
			if ( $sampled >= 20 ) { break; }
			if ( ! preg_match( '/\.(jpg|jpeg|png|webp|gif|avif)(\?|$)/i', $a ) ) { continue; }
			$img_bytes += carmelx_sm_head_bytes( $a );
			$sampled++;
		}
		$row = array(
			'url'      => $url,
			'code'     => $code,
			'ms'       => $ms,
			'html_kb'  => (int) round( $p['html_bytes'] / 1024 ),
			'img'      => (int) $p['img'],
			'script'   => (int) $p['script'],
			'style'    => (int) $p['style'],
			'req'      => (int) ( $p['img'] + $p['script'] + $p['style'] ),
			'img_kb'   => (int) round( $img_bytes / 1024 ),
			'img_sampled' => $sampled,
		);
		$row['total_kb'] = $row['html_kb'] + $row['img_kb'];
		$row['flags']    = carmelx_sm_flags( $row );
		return $row;
	}
}

/* 巡回対象URL（ホーム＋在庫数件＋主要固定ページ、上限15） */
if ( ! function_exists( 'carmelx_sm_targets' ) ) {
	function carmelx_sm_targets() {
		$urls = array( home_url( '/' ) );
		if ( post_type_exists( 'portfolio' ) ) {
			$q = get_posts( array( 'post_type' => 'portfolio', 'posts_per_page' => 6, 'post_status' => 'publish', 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
			foreach ( $q as $pid ) { $urls[] = get_permalink( $pid ); }
		}
		foreach ( array( 'search', 'shop', 'contact-form', 'loan_new2', 'buy2' ) as $slug ) {
			$pg = get_page_by_path( $slug );
			if ( $pg ) { $urls[] = get_permalink( $pg ); }
			else { $urls[] = home_url( '/' . $slug . '/' ); }
		}
		$urls = array_values( array_unique( array_filter( $urls ) ) );
		return array_slice( $urls, 0, 15 );
	}
}

if ( ! function_exists( 'carmelx_sm_run_scan' ) ) {
	function carmelx_sm_run_scan() {
		@set_time_limit( 120 );
		$rows = array();
		foreach ( carmelx_sm_targets() as $u ) { $rows[] = carmelx_sm_scan_url( $u ); }
		usort( $rows, function ( $a, $b ) {
			$ta = isset( $a['total_kb'] ) ? $a['total_kb'] : -1;
			$tb = isset( $b['total_kb'] ) ? $b['total_kb'] : -1;
			return $tb <=> $ta;
		} );
		update_option( CARMEL_SM_DATA, array( 'time' => time(), 'rows' => $rows ), false );
		return $rows;
	}
}

/* 特大画像（メディア）トップN */
if ( ! function_exists( 'carmelx_sm_big_images' ) ) {
	function carmelx_sm_big_images( $limit = 15 ) {
		$q = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => 300, 'post_status' => 'inherit', 'fields' => 'ids' ) );
		$items = array();
		foreach ( $q as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file || ! file_exists( $file ) ) { continue; }
			$bytes = (int) filesize( $file );
			$meta  = wp_get_attachment_metadata( $id );
			$w = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
			$h = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
			$items[] = array( 'id' => $id, 'kb' => (int) round( $bytes / 1024 ), 'w' => $w, 'h' => $h, 'url' => wp_get_attachment_url( $id ), 'edit' => get_edit_post_link( $id, '' ) );
		}
		usort( $items, function ( $a, $b ) { return $b['kb'] <=> $a['kb']; } );
		return array_slice( $items, 0, $limit );
	}
}

/* ========== 安全な軽量化トグル（承認制：ONにした分だけ適用） ========== */
if ( ! function_exists( 'carmelx_sm_opts' ) ) {
	function carmelx_sm_opts() {
		$o = get_option( CARMEL_SM_OPT );
		return array(
			'disable_emoji' => ! empty( $o['disable_emoji'] ),
			'disable_embed' => ! empty( $o['disable_embed'] ),
		);
	}
}
if ( ! function_exists( 'carmelx_sm_apply_toggles' ) ) {
	function carmelx_sm_apply_toggles() {
		$o = carmelx_sm_opts();
		if ( $o['disable_emoji'] ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			add_filter( 'tiny_mce_plugins', function ( $p ) { return is_array( $p ) ? array_diff( $p, array( 'wpemoji' ) ) : $p; } );
		}
		if ( $o['disable_embed'] ) {
			add_action( 'wp_footer', function () { wp_dequeue_script( 'wp-embed' ); } );
		}
	}
	add_action( 'init', 'carmelx_sm_apply_toggles', 20 );
}

/* ========== 管理ページ（ツール → サイト健康モニター）オーナー専用 ========== */
if ( ! function_exists( 'carmelx_sm_add_page' ) ) {
	function carmelx_sm_add_page() {
		add_management_page( 'サイト健康モニター', 'サイト健康モニター', 'manage_options', 'carmel-site-monitor', 'carmelx_sm_render' );
	}
	add_action( 'admin_menu', 'carmelx_sm_add_page' );
}

if ( ! function_exists( 'carmelx_sm_render' ) ) {
	function carmelx_sm_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$msg = '';
		if ( isset( $_POST['carmel_sm_nonce'] ) && wp_verify_nonce( $_POST['carmel_sm_nonce'], 'carmel_sm' ) ) {
			if ( isset( $_POST['carmel_sm_scan'] ) ) {
				carmelx_sm_run_scan();
				$msg = '診断しました。';
			} elseif ( isset( $_POST['carmel_sm_save'] ) ) {
				update_option( CARMEL_SM_OPT, array(
					'disable_emoji' => isset( $_POST['disable_emoji'] ) ? 1 : 0,
					'disable_embed' => isset( $_POST['disable_embed'] ) ? 1 : 0,
				) );
				$msg = '軽量化設定を保存しました。';
			}
		}
		$data = get_option( CARMEL_SM_DATA );
		$rows = ( is_array( $data ) && isset( $data['rows'] ) ) ? $data['rows'] : array();
		$when = ( is_array( $data ) && isset( $data['time'] ) ) ? $data['time'] : 0;
		$o    = carmelx_sm_opts();
		?>
		<div class="wrap">
			<h1>サイト健康モニター</h1>
			<?php if ( $msg ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
			<p style="max-width:820px;">主要ページの「重さ」を計測して、重い順に表示します。改善は<strong>提案のみ</strong>で、見た目を勝手に変えることはありません。</p>

			<form method="post" style="margin:10px 0 22px;">
				<?php wp_nonce_field( 'carmel_sm', 'carmel_sm_nonce' ); ?>
				<button type="submit" name="carmel_sm_scan" value="1" class="button button-primary">今すぐ診断する</button>
				<?php if ( $when ) : ?><span style="margin-left:10px;color:#777;">最終診断：<?php echo esc_html( date_i18n( 'Y/m/d H:i', $when ) ); ?></span><?php endif; ?>
			</form>

			<?php if ( $rows ) : ?>
			<h2>ページの重さ（重い順）</h2>
			<table class="widefat striped" style="max-width:960px;">
				<thead><tr><th>ページ</th><th style="width:90px;">合計</th><th style="width:80px;">画像</th><th style="width:70px;">要素数</th><th style="width:80px;">応答</th><th>気になる点</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) :
					if ( isset( $r['error'] ) ) { echo '<tr><td colspan="6"><code>' . esc_html( $r['url'] ) . '</code> … 取得エラー: ' . esc_html( $r['error'] ) . '</td></tr>'; continue; }
					$tot = $r['total_kb'] >= 1024 ? round( $r['total_kb'] / 1024, 1 ) . 'MB' : $r['total_kb'] . 'KB';
					$imgk = $r['img_kb'] >= 1024 ? round( $r['img_kb'] / 1024, 1 ) . 'MB' : $r['img_kb'] . 'KB';
					$heavy = $r['total_kb'] >= 3000; $warn = ( ! $heavy && $r['total_kb'] >= 1500 );
					$color = $heavy ? '#b32d2e' : ( $warn ? '#bd7b00' : '#1c7a3a' );
					?>
					<tr>
						<td><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener" style="word-break:break-all;"><?php echo esc_html( preg_replace( '#^https?://[^/]+#', '', $r['url'] ) ?: '/' ); ?></a></td>
						<td style="font-weight:800;color:<?php echo $color; ?>;"><?php echo esc_html( $tot ); ?></td>
						<td><?php echo esc_html( $imgk ); ?><?php echo $r['img_sampled'] < $r['img'] ? '<small style="color:#aaa;">+</small>' : ''; ?></td>
						<td><?php echo esc_html( $r['req'] ); ?></td>
						<td><?php echo esc_html( $r['ms'] ); ?>ms</td>
						<td><?php echo $r['flags'] ? esc_html( implode( '／', $r['flags'] ) ) : '<span style="color:#1c7a3a;">良好</span>'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="color:#888;font-size:12px;max-width:820px;">※「画像」はページ内画像の合計容量（上位20件サンプル。<small>+</small>はさらに多いことを示します）。要素数＝画像＋スクリプト＋CSSの数。</p>

			<h2 style="margin-top:26px;">特大画像トップ（圧縮・差し替えの候補）</h2>
			<?php $bigs = carmelx_sm_big_images( 12 ); ?>
			<table class="widefat striped" style="max-width:760px;">
				<thead><tr><th>画像</th><th style="width:100px;">容量</th><th style="width:120px;">寸法</th><th style="width:90px;"></th></tr></thead>
				<tbody>
				<?php foreach ( $bigs as $b ) :
					$big = $b['kb'] >= 500 || $b['w'] >= 2000; ?>
					<tr>
						<td><a href="<?php echo esc_url( $b['url'] ); ?>" target="_blank" rel="noopener" style="word-break:break-all;"><?php echo esc_html( basename( parse_url( $b['url'], PHP_URL_PATH ) ) ); ?></a></td>
						<td style="font-weight:700;color:<?php echo $big ? '#b32d2e' : '#444'; ?>;"><?php echo esc_html( $b['kb'] >= 1024 ? round( $b['kb'] / 1024, 1 ) . 'MB' : $b['kb'] . 'KB' ); ?></td>
						<td><?php echo $b['w'] ? esc_html( $b['w'] . '×' . $b['h'] ) : '—'; ?></td>
						<td><?php if ( $b['edit'] ) : ?><a href="<?php echo esc_url( $b['edit'] ); ?>" class="button button-small">開く</a><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="color:#888;font-size:12px;max-width:820px;">※ 500KB超 または 横2000px超は圧縮・縮小の効果が大きい候補です（元画像は自動で変更しません）。</p>
			<?php endif; ?>

			<h2 style="margin-top:26px;">安全な軽量化（ONにしたものだけ適用）</h2>
			<form method="post">
				<?php wp_nonce_field( 'carmel_sm', 'carmel_sm_nonce' ); ?>
				<p><label><input type="checkbox" name="disable_emoji" value="1" <?php checked( $o['disable_emoji'] ); ?>> 絵文字スクリプトを停止（WordPressが全ページに読み込む絵文字用JSを停止。見た目に影響なし）</label></p>
				<p><label><input type="checkbox" name="disable_embed" value="1" <?php checked( $o['disable_embed'] ); ?>> 埋め込み用スクリプト(wp-embed)を停止（他サイトを埋め込む時だけ使うJS。通常は不要）</label></p>
				<p><button type="submit" name="carmel_sm_save" value="1" class="button button-primary">軽量化設定を保存</button></p>
				<p style="color:#888;font-size:12px;max-width:820px;">※ いずれも見た目は変わりません。問題が出たらOFFに戻すだけで元通りです。<br>レイアウト・色などデザインに関わる改善は、今後「提案→プレビュー→承認」で行えるようにします（AI診断は次段で追加）。</p>
			</form>
		</div>
		<?php
	}
}
