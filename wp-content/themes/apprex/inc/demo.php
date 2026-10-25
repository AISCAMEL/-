<?php
/**
 * デモアプリ（AIスタジオのデザインを、実際に触れるアプリとして表示）。
 *
 * - /app-demo/{トークン}/：会員ごとのデモアプリ（リンクを知っている人だけが見られる・検索除外）
 * - /app-demo/sample/?ind=food：訪問者向けのサンプル（業種別）
 * - ホーム画面に追加すると、アプリのように全画面で使える（manifest）
 * - ロゴ・写真はアカウントに保存し、どの端末のデモにも反映
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 管理画面（アプリの基盤）に表示されている、プレビューアプリの既定URL。 */
const APPREX_PREVIEW_IOS     = 'https://apps.apple.com/jp/app/%E3%83%97%E3%83%AC%E3%83%93%E3%83%A5%E3%83%BC%E3%82%A2%E3%83%97%E3%83%AA/id6504839258';
const APPREX_PREVIEW_ANDROID = 'https://play.google.com/store/apps/details?id=jp.global.ebookset.app1.previewapp';

/**
 * プレビューアプリ（本番の画面を実機で確認するアプリ）のURL。
 *
 * @return array{ios:string,android:string}
 */
function apprex_preview_app_links() {
	$out = array();
	foreach ( array( 'ios' => APPREX_PREVIEW_IOS, 'android' => APPREX_PREVIEW_ANDROID ) as $k => $def ) {
		$v         = trim( (string) get_option( 'apprex_preview_app_' . $k, '' ) );
		$out[ $k ] = '-' === $v ? '' : ( $v ? $v : $def );
	}
	return apply_filters( 'apprex_preview_app_links', $out );
}

/**
 * 保存されたメニュー構成を検証（studio-data.js のページキーのみ）。
 *
 * @param mixed $app 保存値。
 * @return array|null
 */
function apprex_demo_clean_app( $app ) {
	if ( ! is_array( $app ) || empty( $app['menu'] ) || ! is_array( $app['menu'] ) ) {
		return null;
	}
	$key   = function ( $k ) {
		return preg_match( '/^[a-z_]{2,20}$/i', (string) $k ) ? (string) $k : '';
	};
	$menu  = array_values( array_filter( array_map( $key, array_slice( $app['menu'], 0, 60 ) ) ) );
	$foot  = isset( $app['footer'] ) && is_array( $app['footer'] ) ? array_values( array_filter( array_map( $key, array_slice( $app['footer'], 0, 5 ) ) ) ) : array();
	$names = array();
	if ( isset( $app['names'] ) && is_array( $app['names'] ) ) {
		foreach ( $app['names'] as $k => $v ) {
			if ( $key( $k ) && is_string( $v ) ) {
				$names[ $k ] = mb_substr( sanitize_text_field( $v ), 0, 10 );
			}
		}
	}
	return array( 'menu' => $menu, 'footer' => $foot, 'names' => (object) $names );
}

/** サンプルの業種（studio-data.js の IND と同じキー）。 */
function apprex_demo_industries() {
	return array( 'food' => '飲食店', 'beauty' => '美容サロン', 'golf' => 'ゴルフ・スポーツ', 'clinic' => 'クリニック', 'school' => 'スクール・教室', 'retail' => '小売・EC' );
}

/** 会員のデモ用トークン（無ければ作成）。 */
function apprex_demo_token( $user_id ) {
	$t = (string) get_user_meta( $user_id, 'apprex_demo_token', true );
	if ( '' === $t ) {
		$t = strtolower( wp_generate_password( 14, false, false ) );
		update_user_meta( $user_id, 'apprex_demo_token', $t );
	}
	return $t;
}

/** デモアプリのURL。 */
function apprex_demo_url( $token, $args = array() ) {
	return add_query_arg( $args, home_url( '/app-demo/' . rawurlencode( $token ) . '/' ) );
}

/* ルーティング */
add_action( 'init', function () {
	add_rewrite_rule( '^app-demo/([a-z0-9]+)/?$', 'index.php?apprex_demo=$matches[1]', 'top' );
	if ( (int) get_option( 'apprex_demo_rewrite' ) < 1 ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_demo_rewrite', 1 );
	}
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'apprex_demo';
	$vars[] = 'apprex_demo_manifest';
	return $vars;
} );

/**
 * トークンから会員を探す。
 *
 * @param string $token トークン。
 * @return WP_User|null
 */
function apprex_demo_user( $token ) {
	if ( '' === $token || 'sample' === $token ) {
		return null;
	}
	$users = get_users(
		array(
			'meta_key'   => 'apprex_demo_token',
			'meta_value' => $token,
			'number'     => 1,
		)
	);
	return $users ? $users[0] : null;
}

/** ロゴ・写真（アカウントに保存したもの）のURL。 */
function apprex_studio_assets( $user_id ) {
	$a   = get_user_meta( $user_id, 'apprex_studio_assets', true );
	$out = array();
	foreach ( array( 'logo', 'photo' ) as $k ) {
		if ( is_array( $a ) && ! empty( $a[ $k ] ) ) {
			$url = wp_get_attachment_url( (int) $a[ $k ] );
			if ( $url ) {
				$out[ $k ] = $url;
			}
		}
	}
	return $out;
}

/**
 * デモアプリに渡す設定。
 *
 * @param string $token トークン（sample 可）。
 * @return array|null
 */
function apprex_demo_config( $token ) {
	$inds  = apprex_demo_industries();
	$proto = isset( $_GET['v'] ) && 'orig' === $_GET['v']; // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'sample' === $token && $proto ) {
		// オリジナルアプリの試作サンプル（t=matching|business|learning|event）。
		$t     = isset( $_GET['t'] ) ? sanitize_key( wp_unslash( $_GET['t'] ) ) : 'matching'; // phpcs:ignore WordPress.Security.NonceVerification
		$ideas = array( 'matching' => 'スキルマッチング', 'business' => '社内の日報と在庫', 'learning' => '動画レッスンのサブスク', 'event' => '地域イベントの予約' );
		$t     = isset( $ideas[ $t ] ) ? $t : 'matching';
		return array(
			'sample'    => true,
			'protoKinds' => $ideas,
			'protoKind' => $t,
			'proto'     => apprex_orig_sanitize( apprex_orig_template( $ideas[ $t ] ) ),
			'd'         => null,
			'images'    => array(),
			'studioUrl' => apprex_studio_url(),
			'url'       => apprex_demo_url( 'sample', array( 'v' => 'orig', 't' => $t ) ),
			'owner'     => false,
			'preview'   => array(),
		);
	}
	if ( 'sample' === $token ) {
		$ind = isset( $_GET['ind'] ) ? sanitize_key( wp_unslash( $_GET['ind'] ) ) : 'food'; // phpcs:ignore WordPress.Security.NonceVerification
		$ind = isset( $inds[ $ind ] ) ? $ind : 'food';
		return array(
			'sample'    => true,
			'ind'       => $ind,
			'inds'      => $inds,
			'd'         => null,
			'feat'      => null,
			'images'    => array(),
			'studioUrl' => apprex_studio_url(),
			'url'       => apprex_demo_url( 'sample', array( 'ind' => $ind ) ),
			'owner'     => false,
			'app'       => null,
			'preview'   => apprex_preview_app_links(),
		);
	}
	$user = apprex_demo_user( $token );
	if ( ! $user ) {
		return null;
	}
	$state  = json_decode( (string) get_user_meta( $user->ID, 'apprex_studio_state', true ), true );
	$images = apprex_studio_assets( $user->ID );
	foreach ( apprex_studio_images_out( $user->ID ) as $im ) {
		if ( $im['picked'] && in_array( $im['kind'], array( 'icon', 'hero' ), true ) && empty( $images[ $im['kind'] ] ) ) {
			$images[ $im['kind'] ] = $im['url'];
		}
	}
	$cfg = array(
		'sample'    => false,
		'd'         => is_array( $state ) && isset( $state['d'] ) ? $state['d'] : null,
		'feat'      => is_array( $state ) && isset( $state['feat'] ) ? $state['feat'] : null,
		'images'    => $images,
		'studioUrl' => apprex_studio_url(),
		'url'       => apprex_demo_url( $token ),
		'owner'     => get_current_user_id() === (int) $user->ID,
		'app'       => is_array( $state ) && isset( $state['app'] ) ? apprex_demo_clean_app( $state['app'] ) : null,
		'stores'    => is_array( $state ) && ! empty( $state['stores'] ) && 'biz' === ( isset( $state['tier'] ) ? $state['tier'] : '' ) ? array_map(
			function ( $x ) {
				return array( 'n' => mb_substr( sanitize_text_field( isset( $x['n'] ) ? (string) $x['n'] : '' ), 0, 20 ), 'a' => mb_substr( sanitize_text_field( isset( $x['a'] ) ? (string) $x['a'] : '' ), 0, 30 ) );
			},
			array_slice( (array) $state['stores'], 0, 60 )
		) : null,
		'preview'   => apprex_preview_app_links(),
		'proto'     => $proto ? apprex_orig_sanitize( json_decode( (string) get_user_meta( $user->ID, 'apprex_orig_spec', true ), true ) ) : null,
		'protoImages' => $proto && function_exists( 'apprex_orig_images_out' ) ? apprex_orig_images_out( $user->ID ) : null,
	);
	if ( $proto ) {
		$cfg['url'] = apprex_demo_url( $token, array( 'v' => 'orig' ) );
	}
	return $cfg;
}

/** テーマカラー（studio-data.js の COLORS と同じ並び）。 */
function apprex_demo_theme_color( $d ) {
	$colors = array( '#2f66f5', '#c0392b', '#1f9d6b', '#e0679a', '#b8912a', '#ff7a1a' );
	if ( is_array( $d ) && ! empty( $d['custom'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $d['custom'] ) ) {
		return $d['custom'];
	}
	$i = is_array( $d ) && isset( $d['color'] ) ? (int) $d['color'] : 1;
	if ( ! is_array( $d ) && isset( $_GET['ind'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		// サンプル：業種ごとの色（app-demo.js の SAMPLE と同じ）。
		$sample = array( 'food' => 1, 'beauty' => 3, 'golf' => 2, 'clinic' => 0, 'school' => 5, 'retail' => 2 );
		$ind    = sanitize_key( wp_unslash( $_GET['ind'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$i      = isset( $sample[ $ind ] ) ? $sample[ $ind ] : 1;
	}
	return isset( $colors[ $i ] ) ? $colors[ $i ] : $colors[1];
}

/** デモアプリの名前。 */
function apprex_demo_name( $cfg ) {
	if ( ! empty( $cfg['proto']['name'] ) ) {
		return $cfg['proto']['name'];
	}
	if ( ! empty( $cfg['d']['name'] ) ) {
		return sanitize_text_field( $cfg['d']['name'] );
	}
	$names = array( 'food' => '炭火焼肉 はなび', 'beauty' => 'hair salon Luce', 'golf' => 'SWING BASE', 'clinic' => 'さくら内科', 'school' => 'こども英語 Sunny', 'retail' => 'Select Shop MIKAN' );
	$ind   = ! empty( $cfg['d']['ind'] ) ? $cfg['d']['ind'] : ( isset( $cfg['ind'] ) ? $cfg['ind'] : 'food' );
	return isset( $names[ $ind ] ) ? $names[ $ind ] : 'APPREX';
}

/* 表示 */
add_action( 'template_redirect', function () {
	$manifest = get_query_var( 'apprex_demo_manifest' );
	$token    = $manifest ? $manifest : get_query_var( 'apprex_demo' );
	if ( ! $token ) {
		return;
	}
	$token = sanitize_key( $token );
	// 公開前プレビュー中は管理者だけ。
	if ( function_exists( 'apprex_rn_public' ) && ! apprex_rn_public() && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}
	$cfg = apprex_demo_config( $token );
	if ( ! $cfg ) {
		status_header( 404 );
		nocache_headers();
		echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>デモアプリが見つかりません</title><p style="font-family:sans-serif;padding:24px">デモアプリが見つかりませんでした。URLをご確認ください。<br><a href="' . esc_url( home_url( '/' ) ) . '">APPREX トップへ</a></p>';
		exit;
	}
	$name  = apprex_demo_name( $cfg );
	$color = apprex_demo_theme_color( $cfg['d'] );
	$icon  = ! empty( $cfg['images']['icon'] ) ? $cfg['images']['icon'] : APPREX_URI . '/assets/images/rn/demo-icon.png';

	if ( $manifest ) {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'X-Robots-Tag: noindex', true );
		echo wp_json_encode(
			array(
				'name'             => $name,
				'short_name'       => mb_substr( $name, 0, 12 ),
				'start_url'        => $cfg['url'],
				'scope'            => home_url( '/app-demo/' ),
				'display'          => 'standalone',
				'background_color' => '#ffffff',
				'theme_color'      => $color,
				'icons'            => array(
					array( 'src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
				),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		exit;
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	$apprex_demo = array(
		'cfg'      => $cfg,
		'name'     => $name,
		'color'    => $color,
		'icon'     => $icon,
		'manifest' => add_query_arg( array_filter( array( 'apprex_demo_manifest' => $token, 'ind' => ! empty( $cfg['sample'] ) && isset( $cfg['ind'] ) ? $cfg['ind'] : '', 'v' => ! empty( $cfg['proto'] ) ? 'orig' : '', 't' => isset( $cfg['protoKind'] ) ? $cfg['protoKind'] : '' ) ), home_url( '/' ) ),
	);
	include APPREX_DIR . '/template-parts/app-demo.php';
	exit;
}, 1 );

/* -------------------------------------------------------------------------
 * ロゴ・写真をアカウントに保存（REST /studio/upload）
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/studio/upload',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_studio_rest_upload',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
} );

/**
 * 画像（data URL）を受け取り、メディアに保存してアカウントに紐づける。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_upload( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$kind = sanitize_key( (string) $request->get_param( 'kind' ) );
	if ( ! in_array( $kind, array( 'logo', 'photo' ), true ) ) {
		return new WP_Error( 'bad_request', '種類が不正です。', array( 'status' => 400 ) );
	}
	$uid    = get_current_user_id();
	$assets = get_user_meta( $uid, 'apprex_studio_assets', true );
	$assets = is_array( $assets ) ? $assets : array();

	// 削除。
	if ( $request->get_param( 'remove' ) ) {
		if ( ! empty( $assets[ $kind ] ) ) {
			wp_delete_attachment( (int) $assets[ $kind ], true );
			unset( $assets[ $kind ] );
			update_user_meta( $uid, 'apprex_studio_assets', $assets );
		}
		return rest_ensure_response( array( 'ok' => true, 'assets' => apprex_studio_assets( $uid ) ) );
	}

	$data = (string) $request->get_param( 'data' );
	if ( ! preg_match( '#^data:(image/(?:png|jpeg|webp|gif));base64,(.+)$#s', $data, $m ) ) {
		return new WP_Error( 'bad_request', 'PNG・JPG・WebP・GIFの画像を選んでください。', array( 'status' => 400 ) );
	}
	$bin = base64_decode( $m[2], true );
	if ( false === $bin || strlen( $bin ) > 5 * MB_IN_BYTES ) {
		return new WP_Error( 'bad_request', '5MB以下の画像を選んでください。', array( 'status' => 400 ) );
	}
	$info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bin ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $info || $info['mime'] !== $m[1] ) {
		return new WP_Error( 'bad_request', '画像として読み込めませんでした。', array( 'status' => 400 ) );
	}
	$att = apprex_save_generated_image( array( 'mime' => $m[1], 'data' => $bin ), 'AIスタジオ-' . $uid . '-' . $kind );
	if ( is_wp_error( $att ) ) {
		return new WP_Error( 'failed', '保存できませんでした。', array( 'status' => 500 ) );
	}
	update_post_meta( $att, '_apprex_studio_user', $uid );
	if ( ! empty( $assets[ $kind ] ) ) {
		wp_delete_attachment( (int) $assets[ $kind ], true ); // 古い画像は削除。
	}
	$assets[ $kind ] = $att;
	update_user_meta( $uid, 'apprex_studio_assets', $assets );
	return rest_ensure_response( array( 'ok' => true, 'assets' => apprex_studio_assets( $uid ) ) );
}
