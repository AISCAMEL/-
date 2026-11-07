<?php
/**
 * 古いアドレスの転送（301）と、見つからないページ（404）の記録。
 *
 * - 前のサイトのアドレス（privacy.html など）を、今のページへ転送する。
 * - 名前が変わった記事（/2026/06/11/〜/）は、同じ日の記事や、名前の近い記事へ転送する。
 * - 「*」が入った、ありえないアドレスは 410（もうありません）を返して、Google に早く忘れてもらう。
 * - 設定 → 転送（301）：自分で転送を足せる・最近の404が見られる。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** スラッグから、公開中の固定ページのURL。 */
function apprex_rd_page( $slugs ) {
	foreach ( (array) $slugs as $s ) {
		$p = get_page_by_path( $s );
		if ( $p && 'publish' === $p->post_status ) {
			return get_permalink( $p );
		}
	}
	return '';
}

/** 決まった転送（古いアドレス → 今のページ）。 */
function apprex_rd_fixed( $path ) {
	$path = strtolower( rtrim( $path, '/' ) );
	$priv = (int) get_option( 'wp_page_for_privacy_policy', 0 );
	$map  = array(
		'/privacy.html'    => $priv && 'publish' === get_post_status( $priv ) ? get_permalink( $priv ) : apprex_rd_page( array( 'privacy-policy', 'privacy' ) ),
		'/privacy'         => $priv && 'publish' === get_post_status( $priv ) ? get_permalink( $priv ) : apprex_rd_page( array( 'privacy-policy' ) ),
		'/legal.html'      => apprex_rd_page( array( 'legal', 'tokushoho', 'law', 'terms', 'company' ) ),
		'/free-trial.html' => apprex_rd_page( array( 'free-trial', 'studio' ) ),
		'/matching-cases'  => apprex_rd_page( array( 'matching-app', 'matching' ) ),
		'/hello-world'     => '',
		'/index.html'      => home_url( '/' ),
		'/contact.html'    => apprex_rd_page( array( 'contact' ) ),
		'/company.html'    => apprex_rd_page( array( 'company' ) ),
		'/pricing.html'    => apprex_rd_page( array( 'pricing' ) ),
	);
	return $map[ $path ] ?? null;
}

/** 自分で足した転送（1行に「古いアドレス 新しいアドレス」）。 */
function apprex_rd_custom() {
	$out = array();
	foreach ( preg_split( '/\R/', (string) get_option( 'apprex_redirects', '' ) ) as $l ) {
		$p = preg_split( '/\s+/', trim( $l ) );
		if ( count( $p ) >= 2 && '' !== $p[0] && '#' !== $p[0][0] ) {
			$from         = '/' . ltrim( (string) wp_parse_url( $p[0], PHP_URL_PATH ), '/' );
			$out[ strtolower( rtrim( rawurldecode( $from ), '/' ) ) ] = $p[1];
		}
	}
	return $out;
}

/** ブログの一覧のURL。 */
function apprex_rd_blog() {
	$id = (int) get_option( 'page_for_posts', 0 );
	return $id ? get_permalink( $id ) : home_url( '/' );
}

/**
 * 404 のアドレスから、転送先を探す。
 *
 * @return string|int|null URL・410・null（見つからない）。
 */
function apprex_rd_target( $uri ) {
	$path = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== trim( $home, '/' ) && 0 === strpos( $path, $home ) ) {
		$path = '/' . substr( $path, strlen( $home ) );
	}
	$key = strtolower( rtrim( $path, '/' ) );
	// ありえないアドレス（「*」入り）は、もうありません。
	if ( false !== strpos( $path, '*' ) ) {
		return 410;
	}
	$custom = apprex_rd_custom();
	if ( isset( $custom[ $key ] ) ) {
		return 0 === strpos( $custom[ $key ], '/' ) ? home_url( $custom[ $key ] ) : $custom[ $key ];
	}
	$fixed = apprex_rd_fixed( $path );
	if ( null !== $fixed ) {
		return '' !== $fixed ? $fixed : apprex_rd_blog();
	}
	// 前のサイトの「〇〇.html」→ 同じ名前の固定ページ。
	if ( preg_match( '#^/([a-z0-9\-]+)\.html?$#i', $path, $m ) ) {
		$u = apprex_rd_page( array( strtolower( $m[1] ) ) );
		if ( '' !== $u ) {
			return $u;
		}
	}
	// 日付つきの記事（/2026/06/11/名前/）。
	if ( preg_match( '#^/(\d{4})/(\d{2})/(\d{2})/([^/]+)/?$#u', $path, $m ) ) {
		$slug = $m[4];
		if ( 'hello-world' === $slug ) {
			return apprex_rd_blog();
		}
		$day = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 10, 'date_query' => array( array( 'year' => (int) $m[1], 'month' => (int) $m[2], 'day' => (int) $m[3] ) ) ) );
		$best = null;
		$bs   = 0;
		$want = mb_substr( preg_replace( '/[\s\-ー：:、。・]/u', '', $slug ), 0, 14 );
		foreach ( $day as $p ) {
			$t = preg_replace( '/[\s\-ー：:、。・]/u', '', $p->post_title . rawurldecode( $p->post_name ) );
			similar_text( $want, mb_substr( $t, 0, 14 ), $pct );
			if ( mb_strlen( $want ) && false !== mb_strpos( $t, mb_substr( $want, 0, 8 ) ) ) {
				$pct = 100;
			}
			if ( $pct > $bs ) {
				$bs   = $pct;
				$best = $p;
			}
		}
		if ( $best && ( $bs >= 40 || 1 === count( $day ) ) ) {
			return get_permalink( $best );
		}
		// 名前の近い記事（日付が変わったもの）。
		$q = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 's' => mb_substr( $want, 0, 8 ) ) );
		if ( $q ) {
			return get_permalink( $q[0] );
		}
		return apprex_rd_blog();
	}
	// 最後の部分が、ほかの場所の固定ページ・記事の名前と同じ。
	$base = basename( rtrim( $path, '/' ) );
	if ( '' !== $base && preg_match( '/^[\p{L}\p{N}\-_]+$/u', $base ) ) {
		foreach ( array( 'page', 'post', 'case' ) as $pt ) {
			$p = get_page_by_path( $base, OBJECT, $pt );
			if ( $p && 'publish' === $p->post_status ) {
				return get_permalink( $p );
			}
		}
	}
	return null;
}

add_action(
	'template_redirect',
	function () {
		if ( ! is_404() ) {
			return;
		}
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		$t   = apprex_rd_target( $uri );
		if ( 410 === $t ) {
			status_header( 410 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex' );
			echo '<!doctype html><meta charset="utf-8"><title>このページはありません</title><p>このページはありません。<a href="' . esc_url( home_url( '/' ) ) . '">トップへ</a></p>';
			exit;
		}
		if ( is_string( $t ) && '' !== $t ) {
			$here = untrailingslashit( set_url_scheme( home_url( wp_parse_url( $uri, PHP_URL_PATH ) ) ) );
			if ( untrailingslashit( $t ) !== $here ) { // 同じアドレスへ転送しない（ぐるぐる回らないように）。
				wp_safe_redirect( $t, 301 );
				exit;
			}
		}
		// 見つからなかったアドレスを記録（ボット・画像などは除く）。
		if ( ! preg_match( '#\.(png|jpe?g|gif|webp|svg|ico|css|js|map|txt|xml)$|/wp-(admin|includes|content)/|wp-login#i', $uri ) ) {
			$log = (array) get_option( 'apprex_404_log', array() );
			$k   = substr( strtok( $uri, '?' ), 0, 200 );
			$log[ $k ] = array( (int) ( $log[ $k ][0] ?? 0 ) + 1, time() );
			if ( count( $log ) > 200 ) {
				uasort(
					$log,
					function ( $a, $b ) {
						return $b[1] <=> $a[1];
					}
				);
				$log = array_slice( $log, 0, 150, true );
			}
			update_option( 'apprex_404_log', $log, false );
		}
	},
	1
);

/* =========================================================================
 * 画面：設定 → 転送（301）
 * ====================================================================== */
add_action(
	'admin_menu',
	function () {
		add_options_page( '転送（301）', '転送（301）', 'manage_options', 'apprex-redirects', 'apprex_rd_page_ui' );
	}
);
add_action(
	'admin_init',
	function () {
		register_setting( 'apprex_redirects', 'apprex_redirects', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	}
);
add_action(
	'admin_post_apprex_rd_clear',
	function () {
		if ( current_user_can( 'manage_options' ) && check_admin_referer( 'apprex_rd_clear' ) ) {
			delete_option( 'apprex_404_log' );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=apprex-redirects' ) );
		exit;
	}
);

function apprex_rd_page_ui() {
	$log = (array) get_option( 'apprex_404_log', array() );
	uasort(
		$log,
		function ( $a, $b ) {
			return $b[1] <=> $a[1];
		}
	);
	$tests = array( '/privacy.html', '/legal.html', '/free-trial.html', '/matching-cases', '/subsidy', '/partner', '/blog', '/2026/04/05/hello-world/' );
	?>
	<div class="wrap">
		<h1>転送（301）</h1>
		<p>前のサイトのアドレスや、名前が変わった記事のアドレスに来た人を、今のページへ送ります（Google にも「引っ越しました」と伝わります）。</p>
		<h2>自動の転送</h2>
		<table class="widefat striped" style="max-width:900px"><thead><tr><th>古いアドレス</th><th>転送先</th></tr></thead><tbody>
		<?php foreach ( $tests as $t ) : ?>
			<?php
			$exists = url_to_postid( home_url( $t ) );
			$to     = $exists ? '（今のページがあるので、そのまま表示）' : apprex_rd_target( $t );
			?>
			<tr><td><code><?php echo esc_html( $t ); ?></code></td><td><?php echo is_string( $to ) ? esc_html( $to ) : ( 410 === $to ? '410（もうありません）' : '—（404のまま）' ); ?></td></tr>
		<?php endforeach; ?>
			<tr><td><code>/wp-content/plugins/*</code> など「*」入り</td><td>410（もうありません）… Google が早く一覧から外します</td></tr>
			<tr><td><code>/2026/06/11/記事の名前/</code></td><td>同じ日の記事・名前の近い記事 → なければブログの一覧</td></tr>
		</tbody></table>

		<h2>自分で足す</h2>
		<form method="post" action="options.php" style="max-width:900px">
			<?php settings_fields( 'apprex_redirects' ); ?>
			<textarea name="apprex_redirects" rows="8" class="large-text code" placeholder="/old-page /new-page/&#10;/campaign.html https://site.aiscompany.jp/studio/"><?php echo esc_textarea( (string) get_option( 'apprex_redirects', '' ) ); ?></textarea>
			<p class="description">1行に「古いアドレス　新しいアドレス」（あいだは空白）。自動の転送より先に使います。</p>
			<?php submit_button( '保存' ); ?>
		</form>

		<h2>最近の「見つからない」アドレス（<?php echo count( $log ); ?>件）</h2>
		<table class="widefat striped" style="max-width:900px"><thead><tr><th>アドレス</th><th>回数</th><th>最後</th></tr></thead><tbody>
		<?php if ( ! $log ) : ?><tr><td colspan="3">ありません。</td></tr><?php endif; ?>
		<?php foreach ( array_slice( $log, 0, 50, true ) as $u => $v ) : ?>
			<tr><td><code><?php echo esc_html( rawurldecode( $u ) ); ?></code></td><td><?php echo (int) $v[0]; ?></td><td><?php echo esc_html( wp_date( 'n/j G:i', (int) $v[1] ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( $log ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px"><input type="hidden" name="action" value="apprex_rd_clear"><?php wp_nonce_field( 'apprex_rd_clear' ); ?><?php submit_button( '記録を消す', 'secondary', 'submit', false ); ?></form>
		<?php endif; ?>
	</div>
	<?php
}
