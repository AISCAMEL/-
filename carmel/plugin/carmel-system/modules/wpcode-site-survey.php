<?php
/**
 * カーメル：構成調査（管理画面「ツール → カーメル 構成調査」）
 * ---------------------------------------------------------------------------
 * WordPress の中身を整理（不要な機能の停止・メニューの簡素化）する前に、
 * 「何が入っていて、どのプラグインが何を担当しているか」を一覧にする。読み取りだけで、何も変更しない。
 *   ・有効なプラグイン／左メニューと各ページの出どころ／投稿タイプ・タクソノミーの出どころ
 *   ・ショートコード／REST の受け口／定期実行／ACF フィールドグループ／スニペット
 *   ・カーメル関連プラグインの中身の要約（登録している投稿タイプ・ショートコード・外部の通信先など）
 *   ・カーメル関連プラグインのソースを zip でダウンロード（中身を確認してもらうため）
 * 設定値・APIキー・パスワードは出力しない。管理者のみ。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_survey_menu' ) ) {
	function carmelx_survey_menu() {
		add_management_page( 'カーメル 構成調査', 'カーメル 構成調査', 'manage_options', 'carmel-site-survey', 'carmelx_survey_render' );
	}
	add_action( 'admin_menu', 'carmelx_survey_menu' );
}

if ( ! function_exists( 'carmelx_survey_rel' ) ) {
	/** ファイルの場所を wp-content からの相対で（プラグイン名が分かる形） */
	function carmelx_survey_rel( $file ) {
		$file = wp_normalize_path( (string) $file );
		$base = wp_normalize_path( WP_CONTENT_DIR ) . '/';
		return 0 === strpos( $file, $base ) ? substr( $file, strlen( $base ) ) : basename( $file );
	}
}

if ( ! function_exists( 'carmelx_survey_src' ) ) {
	/** コールバック（関数・メソッド・無名関数）が定義されているファイル */
	function carmelx_survey_src( $cb ) {
		try {
			if ( $cb instanceof Closure ) { $r = new ReflectionFunction( $cb ); }
			elseif ( is_string( $cb ) && false !== strpos( $cb, '::' ) ) { list( $c, $m ) = explode( '::', $cb, 2 ); $r = new ReflectionMethod( $c, $m ); }
			elseif ( is_string( $cb ) && function_exists( $cb ) ) { $r = new ReflectionFunction( $cb ); }
			elseif ( is_array( $cb ) && 2 === count( $cb ) ) { $r = new ReflectionMethod( is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0], (string) $cb[1] ); }
			else { return ''; }
			$f = $r->getFileName();
			return $f ? carmelx_survey_rel( $f ) : '';
		} catch ( \Throwable $e ) { return ''; }
	}
}

if ( ! function_exists( 'carmelx_survey_owner' ) ) {
	/** 相対パス → 出どころ（plugins/フォルダ名 または themes/テーマ名） */
	function carmelx_survey_owner( $rel ) {
		$p = explode( '/', (string) $rel );
		if ( count( $p ) >= 2 && in_array( $p[0], array( 'plugins', 'themes', 'mu-plugins' ), true ) ) { return $p[0] . '/' . $p[1]; }
		return '' !== $rel ? $rel : '（WordPress本体・不明）';
	}
}

if ( ! function_exists( 'carmelx_survey_carmel_plugins' ) ) {
	/** カーメル関連のプラグイン（このプラグイン自身は除く）：フォルダ名 => 表示名 */
	function carmelx_survey_carmel_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
		$out = array();
		foreach ( (array) get_plugins() as $file => $d ) {
			$dir = false !== strpos( $file, '/' ) ? dirname( $file ) : $file;
			if ( in_array( $dir, array( 'carmel-system', 'carmel-inventory-bridge' ), true ) ) { continue; }
			if ( preg_match( '/carmel|カーメル/iu', $dir . ' ' . $d['Name'] ) ) { $out[ $dir ] = $d['Name'] . ' ' . $d['Version'] . ( is_plugin_active( $file ) ? '' : '（無効）' ); }
		}
		return $out;
	}
}

if ( ! function_exists( 'carmelx_survey_files' ) ) {
	/** プラグインフォルダ（または単体ファイル）の中の、テキスト系ファイル一覧 */
	function carmelx_survey_files( $dir ) {
		$root = wp_normalize_path( WP_PLUGIN_DIR ) . '/' . $dir;
		if ( is_file( $root ) ) { return array( $root ); }
		if ( ! is_dir( $root ) ) { return array(); }
		$out = array();
		$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$p = wp_normalize_path( $f->getPathname() );
			if ( preg_match( '#/(vendor|node_modules|\.git)/#', $p ) ) { continue; }
			if ( ! preg_match( '/\.(php|js|css|json|txt|md|html)$/i', $p ) ) { continue; }
			if ( $f->getSize() > 3 * 1024 * 1024 ) { continue; }
			$out[] = $p;
			if ( count( $out ) >= 600 ) { break; }
		}
		sort( $out );
		return $out;
	}
}

if ( ! function_exists( 'carmelx_survey_scan' ) ) {
	/** PHPファイル1つを読み、登録しているもの（投稿タイプ・ショートコード等）を拾う */
	function carmelx_survey_scan( $path ) {
		$src = (string) @file_get_contents( $path );
		$res = array();
		$pick = function ( $label, $re, $n = 1 ) use ( $src, &$res ) {
			if ( preg_match_all( $re, $src, $m ) ) { $v = array_values( array_unique( $m[ $n ] ) ); if ( $v ) { $res[ $label ] = array_slice( $v, 0, 40 ); } }
		};
		$pick( '投稿タイプ', '/register_post_type\(\s*[\'"]([^\'"]+)/' );
		$pick( 'タクソノミー', '/register_taxonomy\(\s*[\'"]([^\'"]+)/' );
		$pick( 'ショートコード', '/add_shortcode\(\s*[\'"]([^\'"]+)/' );
		if ( preg_match_all( '/register_rest_route\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)/', $src, $m, PREG_SET_ORDER ) ) {
			$v = array(); foreach ( $m as $x ) { $v[] = $x[1] . $x[2]; } $res['REST'] = array_slice( array_values( array_unique( $v ) ), 0, 60 );
		}
		$pick( 'メタ登録', '/register_(?:post_meta|meta|rest_field)\(\s*[^,]+,\s*[\'"]([^\'"]+)/' );
		$pick( '表示に関わるフック', '/add_(?:action|filter)\(\s*[\'"](wp_head|wp_footer|the_content|the_title|template_redirect|template_include|wp_enqueue_scripts|pre_get_posts|body_class|wp_body_open|init|save_post[a-z_]*|transition_post_status|publish_[a-z_]+)[\'"]/' );
		$pick( '定期実行', '/wp_schedule_event\(\s*[^,;]+,\s*[^,;]+,\s*[\'"]([^\'"]+)/' );
		$pick( '定期実行(1回)', '/wp_schedule_single_event\(\s*[^,;]+,\s*[\'"]([^\'"]+)/' );
		$pick( '管理メニュー', '/add_menu_page\(\s*[^,]+,\s*[\'"]([^\'"]{1,40})[\'"]/' );
		$pick( '管理サブメニュー', '/add_submenu_page\(\s*[^,]+,\s*[^,]+,\s*[\'"]([^\'"]{1,40})[\'"]/' );
		$pick( '外部の通信先', '#https?://([a-z0-9\.\-]+\.[a-z]{2,})#i' );
		$pick( 'オプション名', '/(?:get|update|add)_option\(\s*[\'"]([A-Za-z0-9_\-]{3,})[\'"]/' );
		return $res;
	}
}

if ( ! function_exists( 'carmelx_survey_text' ) ) {
	/** 調査結果を1つのテキストにまとめる（読み取りだけ） */
	function carmelx_survey_text() {
		global $menu, $submenu, $wp_filter, $shortcode_tags, $wpdb;
		$L = array();
		$sec = function ( $t ) use ( &$L ) { $L[] = ''; $L[] = '■ ' . $t; };
		$try = function ( $t, $fn ) use ( &$L, $sec ) { $sec( $t ); try { $fn(); } catch ( \Throwable $e ) { $L[] = '（取得できませんでした：' . $e->getMessage() . '）'; } };
		$clean = function ( $s ) { return trim( preg_replace( '/\s+/u', ' ', preg_replace( '/<span.*$/su', '', (string) $s ) ) ) ?: trim( wp_strip_all_tags( (string) $s ) ); };

		$theme = wp_get_theme();
		$L[] = 'カーメル 構成調査 / ' . home_url() . ' / WordPress ' . get_bloginfo( 'version' ) . ' / PHP ' . PHP_VERSION;
		$L[] = 'テーマ：' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $theme->parent() ? '（親：' . $theme->parent()->get( 'Name' ) . '）' : '' ) . ' / パーマリンク：' . ( get_option( 'permalink_structure' ) ?: '無効（基本）' );

		$try( '有効なプラグイン', function () use ( &$L ) {
			if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
			foreach ( (array) get_plugins() as $file => $d ) { if ( is_plugin_active( $file ) ) { $L[] = $d['Name'] . ' | ' . $d['Version'] . ' | ' . $file; } }
			$mu = function_exists( 'get_mu_plugins' ) ? (array) get_mu_plugins() : array();
			foreach ( $mu as $file => $d ) { $L[] = '[必須プラグイン] ' . $d['Name'] . ' | ' . $file; }
		} );

		$try( '左メニュー（表示名 | スラッグ | ページの出どころ）', function () use ( &$L, &$menu, &$submenu, &$wp_filter, $clean ) {
			$src_of = function ( $slug, $parent ) use ( &$wp_filter ) {
				$hook = get_plugin_page_hookname( $slug, $parent );
				if ( $hook && isset( $wp_filter[ $hook ] ) ) {
					foreach ( $wp_filter[ $hook ]->callbacks as $cbs ) { foreach ( $cbs as $cb ) { $s = carmelx_survey_src( $cb['function'] ); if ( '' !== $s ) { return carmelx_survey_owner( $s ); } } }
				}
				return '';
			};
			foreach ( (array) $menu as $m ) {
				if ( empty( $m[0] ) || empty( $m[2] ) || false !== strpos( (string) ( isset( $m[4] ) ? $m[4] : '' ), 'wp-menu-separator' ) ) { continue; }
				$L[] = $clean( $m[0] ) . ' | ' . $m[2] . ' | ' . $src_of( $m[2], '' );
				foreach ( (array) ( isset( $submenu[ $m[2] ] ) ? $submenu[ $m[2] ] : array() ) as $s ) {
					$L[] = '   - ' . $clean( $s[0] ) . ' | ' . $s[2] . ' | ' . $src_of( $s[2], $m[2] );
				}
			}
		} );

		$try( '投稿タイプ（名前 | 表示名 | 公開/下書きの件数 | REST | 出どころ）', function () use ( &$L ) {
			$cptui = (array) get_option( 'cptui_post_types', array() );
			foreach ( get_post_types( array( '_builtin' => false ), 'objects' ) as $pt ) {
				$c   = wp_count_posts( $pt->name );
				$src = isset( $cptui[ $pt->name ] ) ? 'CPT UI（設定で登録）' : 'コード（プラグイン/テーマ）';
				$L[] = $pt->name . ' | ' . $pt->label . ' | ' . ( isset( $c->publish ) ? (int) $c->publish : 0 ) . '/' . ( isset( $c->draft ) ? (int) $c->draft : 0 ) . ' | ' . ( $pt->show_in_rest ? ( $pt->rest_base ?: $pt->name ) : '×' ) . ' | ' . $src;
			}
		} );

		$try( 'タクソノミー（名前 | 表示名 | 対象 | 語の数 | 出どころ）', function () use ( &$L ) {
			$cptui = (array) get_option( 'cptui_taxonomies', array() );
			foreach ( get_taxonomies( array( '_builtin' => false ), 'objects' ) as $tx ) {
				$n = wp_count_terms( array( 'taxonomy' => $tx->name, 'hide_empty' => false ) );
				$L[] = $tx->name . ' | ' . $tx->label . ' | ' . implode( ',', (array) $tx->object_type ) . ' | ' . ( is_wp_error( $n ) ? '?' : (int) $n ) . ' | ' . ( isset( $cptui[ $tx->name ] ) ? 'CPT UI（設定で登録）' : 'コード（プラグイン/テーマ）' );
			}
		} );

		$try( 'Post Types Unlimited で作った投稿タイプ・タクソノミー', function () use ( &$L ) {
			foreach ( array( 'ptu' => '_ptu_name', 'ptu_tax' => '_ptu_name' ) as $type => $key ) {
				foreach ( (array) get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 50, 'suppress_filters' => true ) ) as $p ) {
					$L[] = ( 'ptu' === $type ? '投稿タイプ' : 'タクソノミー' ) . ' | ' . $p->post_title . ' | ' . get_post_meta( $p->ID, $key, true );
				}
			}
		} );

		$try( '記事の入れ物の保持（旧プラグインを止めても残すための控え）', function () use ( &$L ) {
			if ( ! function_exists( 'carmelx_ctypes_status' ) ) { $L[] = 'この機能は入っていません'; return; }
			foreach ( carmelx_ctypes_status() as $k => $v ) { $L[] = $k . ' | ' . $v; }
			foreach ( array( 'media_article', 'shop_blog', 'news' ) as $pt ) {
				$o = get_post_type_object( $pt ); if ( ! $o ) { continue; }
				$L[] = '   ' . $pt . ' の設定：URL=' . wp_json_encode( $o->rewrite, JSON_UNESCAPED_UNICODE ) . ' 一覧ページ=' . wp_json_encode( $o->has_archive ) . ' 対応=' . implode( ',', array_keys( (array) get_all_post_type_supports( $pt ) ) );
			}
		} );

		$try( 'ショートコード（出どころ：名前）', function () use ( &$L, &$shortcode_tags ) {
			$by = array();
			foreach ( (array) $shortcode_tags as $tag => $cb ) { $by[ carmelx_survey_owner( carmelx_survey_src( $cb ) ) ][] = $tag; }
			ksort( $by );
			foreach ( $by as $owner => $tags ) { sort( $tags ); $L[] = $owner . '：' . implode( ', ', array_slice( $tags, 0, 80 ) ) . ( count( $tags ) > 80 ? ' …ほか' . ( count( $tags ) - 80 ) : '' ); }
		} );

		$try( 'REST の受け口（名前空間 | 数 | 出どころ）', function () use ( &$L ) {
			$by = array();
			foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
				$ns = preg_match( '#^/([^/]+/v[0-9\.]+)#', $route, $m ) ? $m[1] : '(root)';
				if ( ! isset( $by[ $ns ] ) ) { $by[ $ns ] = array( 0, '' ); }
				$by[ $ns ][0]++;
				if ( '' === $by[ $ns ][1] ) { foreach ( (array) $handlers as $h ) { if ( isset( $h['callback'] ) ) { $s = carmelx_survey_src( $h['callback'] ); if ( '' !== $s && false !== strpos( $s, '/' ) ) { $by[ $ns ][1] = carmelx_survey_owner( $s ); break; } } } }
			}
			ksort( $by );
			foreach ( $by as $ns => $v ) { $L[] = $ns . ' | ' . $v[0] . ' | ' . $v[1]; }
		} );

		$try( '定期実行（フック | 間隔 | 次回）', function () use ( &$L ) {
			foreach ( (array) _get_cron_array() as $ts => $hooks ) {
				foreach ( (array) $hooks as $hook => $evs ) {
					if ( preg_match( '/^(wp_|delete_|recovery_|do_pings|publish_future_post|action_scheduler)/', $hook ) ) { continue; }
					foreach ( (array) $evs as $ev ) { $L[] = $hook . ' | ' . ( ! empty( $ev['schedule'] ) ? $ev['schedule'] : '1回だけ' ) . ' | ' . wp_date( 'm/d H:i', (int) $ts ); }
				}
			}
		} );

		$try( 'ACF フィールドグループ（名前 | 欄の数 | 対象）', function () use ( &$L ) {
			if ( ! function_exists( 'acf_get_field_groups' ) ) { $L[] = 'ACF は無効'; return; }
			foreach ( (array) acf_get_field_groups() as $g ) {
				$loc = array();
				foreach ( (array) ( isset( $g['location'] ) ? $g['location'] : array() ) as $or ) { foreach ( (array) $or as $r ) { $loc[] = $r['param'] . $r['operator'] . $r['value']; } }
				$L[] = $g['title'] . ' | ' . count( (array) acf_get_fields( $g ) ) . ' | ' . implode( ' / ', array_slice( $loc, 0, 4 ) ) . ( empty( $g['active'] ) ? ' | 無効' : '' );
			}
		} );

		$try( 'スニペット（名前 | 状態）', function () use ( &$L, $wpdb ) {
			$wpcode = get_posts( array( 'post_type' => 'wpcode', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => 200, 'suppress_filters' => true ) );
			foreach ( (array) $wpcode as $p ) { $L[] = '[WPCode] ' . $p->post_title . ' | ' . ( 'publish' === $p->post_status ? '有効' : '無効' ) . ' | ID ' . $p->ID; }
			$t = $wpdb->prefix . 'snippets';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
				foreach ( (array) $wpdb->get_results( "SELECT id, name, active FROM {$t} ORDER BY id LIMIT 300" ) as $r ) { $L[] = '[Code Snippets] ' . $r->name . ' | ' . ( $r->active ? '有効' : '無効' ) . ' | ID ' . $r->id; }
			}
		} );

		$try( 'カーメル関連プラグインの中身（登録しているもの。設定値は含みません）', function () use ( &$L ) {
			foreach ( carmelx_survey_carmel_plugins() as $dir => $name ) {
				$files = carmelx_survey_files( $dir );
				$L[]   = '▼ ' . $name . ' | plugins/' . $dir . ' | ファイル ' . count( $files );
				foreach ( $files as $f ) {
					if ( ! preg_match( '/\.php$/i', $f ) ) { continue; }
					$r = carmelx_survey_scan( $f );
					$L[] = '  ' . carmelx_survey_rel( $f ) . ' (' . round( filesize( $f ) / 1024 ) . 'KB)';
					foreach ( $r as $k => $v ) { $L[] = '     ' . $k . '：' . implode( ', ', $v ); }
				}
			}
		} );

		return implode( "\n", $L ) . "\n";
	}
}

if ( ! function_exists( 'carmelx_survey_zip' ) ) {
	/** カーメル関連プラグインのソース（テキスト系のファイルだけ）を zip でダウンロード */
	function carmelx_survey_zip() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません' ); }
		check_admin_referer( 'carmelx_survey_zip' );
		$dir  = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
		$list = carmelx_survey_carmel_plugins();
		if ( '' === $dir || ! isset( $list[ $dir ] ) || ! class_exists( 'ZipArchive' ) ) { wp_die( 'ダウンロードできません（対象外のプラグイン、またはサーバーが zip に対応していません）' ); }
		// 途中で出た警告文などを捨ててから送る（混ざると zip が壊れるため）。捨てられない時は中止する
		while ( ob_get_level() > 0 ) { ob_end_clean(); }
		if ( headers_sent() ) { wp_die( 'このサイトでは警告文が先に出力されるため、ダウンロードできません。「調査結果を送る」の文字だけ送ってください。' ); }
		$tmp = wp_tempnam( 'carmel-survey' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) { wp_die( 'zip を作れませんでした' ); }
		$base = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
		foreach ( carmelx_survey_files( $dir ) as $f ) { $zip->addFile( $f, substr( $f, strlen( $base ) ) ); }
		$zip->close();
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $dir, '.php' ) ) . '-source.zip"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}
	add_action( 'admin_post_carmelx_survey_zip', 'carmelx_survey_zip' );
}

if ( ! function_exists( 'carmelx_survey_render' ) ) {
	function carmelx_survey_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>カーメル 構成調査</h1>';
		echo '<p>このサイトに何が入っていて、どのプラグインが何を担当しているかを一覧にします。<strong>読み取りだけで、何も変更しません。</strong>設定値・APIキー・パスワードは出力しません。</p>';
		$text = '';
		try { $text = carmelx_survey_text(); } catch ( \Throwable $e ) { echo '<div class="notice notice-error"><p>調査の途中でエラーが起きました：' . esc_html( $e->getMessage() ) . '</p></div>'; }
		echo '<h2>1. 調査結果を送る</h2><p>下の文字をすべてコピーして、そのまま貼り付けて送ってください。</p>';
		echo '<textarea readonly rows="24" style="width:100%;max-width:1100px;font-family:monospace;font-size:12px" onclick="this.select()">' . esc_textarea( $text ) . '</textarea>';
		echo '<h2>2. カーメル関連プラグインのソースを送る</h2><p>中身を正確に確認するため、下のボタンでダウンロードした zip を送ってください（プログラムのファイルだけで、設定値は入りません）。</p><ul style="list-style:disc;margin-left:20px">';
		$list = array();
		try { $list = carmelx_survey_carmel_plugins(); } catch ( \Throwable $e ) { $list = array(); }
		foreach ( $list as $dir => $name ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=carmelx_survey_zip&plugin=' . rawurlencode( $dir ) ), 'carmelx_survey_zip' );
			echo '<li>' . esc_html( $name ) . '　<a class="button" href="' . esc_url( $url ) . '">ソースをダウンロード</a></li>';
		}
		if ( ! $list ) { echo '<li>対象のプラグインが見つかりませんでした。</li>'; }
		echo '</ul></div>';
	}
}
