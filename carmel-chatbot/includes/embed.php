<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 埋め込み・直リンク対応
 *
 * ① 直リンク（フルページ版）: https://サイト/?carmel_chat=1
 *    → チャットだけが全画面で開く共有用ページ。LINEやメール、QRで配布できる。
 * ② 埋め込み: [carmel_chat] ショートコードをページに設置。
 *    → ①のページを iframe で埋め込む。外部サイトにも <iframe> タグを貼れる。
 */

/** 直リンク版のリクエストか判定。 */
function carmel_cb_is_standalone_request() {
	return isset( $_GET['carmel_chat'] ) && $_GET['carmel_chat'] !== '' && $_GET['carmel_chat'] !== '0';
}

/** 直リンク版ページのURLを返す。 */
function carmel_cb_standalone_url() {
	return add_query_arg( 'carmel_chat', '1', home_url( '/' ) );
}

/** 他サイト設置用スクリプトのURL（<script src="..."> で読み込む）。 */
function carmel_cb_embed_script_url() {
	return add_query_arg( 'carmel_chat', 'embed', home_url( '/' ) );
}

/**
 * 他サイトに1行の <script> で設置できるローダーJSを出力。
 * iframeを使わず、相手サイト上に直接チャットウィジェット（右下のボタン＋チャット窓）を描画する。
 * REST通信は carmelonline.jp へクロスオリジンで行う（CORS許可あり）。
 */
function carmel_cb_output_embed_loader() {
	$s = carmel_cb_get_settings();
	nocache_headers();
	header( 'Content-Type: application/javascript; charset=' . get_bloginfo( 'charset' ) );
	header( 'Access-Control-Allow-Origin: *' );

	if ( empty( $s['enabled'] ) ) { echo '/* carmel chat disabled */'; return; }

	$cfg     = carmel_cb_js_config( $s ); // REST URL等は絶対URL
	$widget  = carmel_cb_widget_html( $s, false );
	$css_url = CARMEL_CB_URL . 'assets/chat.css?v=' . CARMEL_CB_VERSION;
	$js_url  = CARMEL_CB_URL . 'assets/chat.js?v=' . CARMEL_CB_VERSION;
	?>
(function(){
	if (window.__carmelEmbedLoaded) { return; }
	window.__carmelEmbedLoaded = true;
	function boot(){
		if (!document.body) { return setTimeout(boot, 50); }
		var css = document.createElement('link');
		css.rel = 'stylesheet'; css.href = <?php echo wp_json_encode( $css_url ); ?>;
		document.head.appendChild(css);
		window.CARMEL_CB = <?php echo wp_json_encode( $cfg ); ?>;
		var wrap = document.createElement('div');
		wrap.innerHTML = <?php echo wp_json_encode( $widget ); ?>;
		while (wrap.firstChild) { document.body.appendChild(wrap.firstChild); }
		var js = document.createElement('script');
		js.src = <?php echo wp_json_encode( $js_url ); ?>; js.defer = true;
		document.body.appendChild(js);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); }
	else { boot(); }
})();
	<?php
}

/* 他サイト埋め込み用：カーメルCBのREST APIをクロスオリジンから呼べるように許可（CORS）。 */
add_action( 'rest_api_init', function () {
	add_filter( 'rest_pre_serve_request', function ( $served, $result, $request ) {
		if ( strpos( (string) $request->get_route(), '/carmel-cb/v1' ) === 0 ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );
		}
		return $served;
	}, 10, 3 );
}, 15 );

/**
 * ?carmel_chat=1 のときに、チャットだけの全画面ページを出力する。
 * 通常のテーマを読み込まず、最小構成でチャットを表示する。
 */
add_action( 'template_redirect', 'carmel_cb_render_standalone', 0 );
function carmel_cb_render_standalone() {
	if ( ! carmel_cb_is_standalone_request() ) { return; }

	// ?carmel_chat=embed → 他サイトに <script> 1行で設置できるローダーJSを出力（iframeを使わない）
	if ( isset( $_GET['carmel_chat'] ) && $_GET['carmel_chat'] === 'embed' ) {
		carmel_cb_output_embed_loader();
		exit;
	}

	$s = carmel_cb_get_settings();
	if ( empty( $s['enabled'] ) ) {
		status_header( 404 );
		wp_die( 'チャットは現在ご利用いただけません。', '', array( 'response' => 404 ) );
	}

	$color  = esc_attr( $s['primary_color'] );
	$cfg    = carmel_cb_js_config( $s, array( 'standalone' => true ) );
	$widget = carmel_cb_widget_html( $s, true );

	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
	// 他サイト（別ドメイン）からの iframe 埋め込みを許可する（セキュリティプラグイン等が付ける制限を解除）
	header_remove( 'X-Frame-Options' );
	header( 'Content-Security-Policy: frame-ancestors *;' );
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $s['bot_name'] ); ?> | <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( CARMEL_CB_URL . 'assets/chat.css?v=' . CARMEL_CB_VERSION ); ?>">
	<style>
		html,body{margin:0;padding:0;height:100%;}
		body.carmel-cb-standalone-page{
			background:<?php echo $color; ?>;
			background:
				radial-gradient(1200px 600px at 50% -10%, rgba(255,255,255,.35), transparent 60%),
				linear-gradient(160deg, <?php echo $color; ?> 0%, #1b2536 100%);
		}
	</style>
</head>
<body class="carmel-cb-standalone-page">
	<?php echo $widget; // 既にエスケープ済みのHTML ?>
	<script>window.CARMEL_CB = <?php echo wp_json_encode( $cfg ); ?>;</script>
	<script src="<?php echo esc_url( CARMEL_CB_URL . 'assets/chat.js?v=' . CARMEL_CB_VERSION ); ?>"></script>
</body>
</html>
	<?php
	exit;
}

/**
 * [carmel_chat] ショートコード：ページ内にチャットを埋め込む（iframe）。
 * 属性: height（既定 640）, width（既定 100%）
 */
add_shortcode( 'carmel_chat', 'carmel_cb_shortcode' );
function carmel_cb_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'height' => '640',
		'width'  => '100%',
	), $atts, 'carmel_chat' );

	$h = preg_match( '/^\d+$/', (string) $atts['height'] ) ? $atts['height'] . 'px' : $atts['height'];
	$w = preg_match( '/^\d+$/', (string) $atts['width'] ) ? $atts['width'] . 'px' : $atts['width'];

	$s        = carmel_cb_get_settings();
	$bot_name = ! empty( $s['bot_name'] ) ? $s['bot_name'] : 'チャット相談';

	return sprintf(
		'<iframe src="%s" style="width:%s;max-width:100%%;height:%s;border:0;border-radius:16px;box-shadow:0 6px 24px rgba(0,0,0,.12);" title="%s" loading="lazy"></iframe>',
		esc_url( carmel_cb_standalone_url() ),
		esc_attr( $w ),
		esc_attr( $h ),
		esc_attr( $bot_name )
	);
}
