<?php
/**
 * LINE配信バナー（Flexメッセージ）生成。
 *
 * ブログ記事の公開時、GAS へ送る post_published データに「LINE用Flexメッセージ」を
 * 追加します。GAS 側はそれをそのまま LINE（broadcast / push）へ転送するだけで、
 * 画像（アイキャッチ）＋タイトル・訴求コピー＋「記事を読む」ボタンの訴求バナーが届き、
 * 画像・ボタン・カードのどこをタップしても記事ページへ遷移します。
 *
 * 連携：apprex_dispatch_payload フィルタで data.line_flex / data.line_alt を付与。
 * 設定：設定 > APPREX 連携（バッジ文言・ボタン文言・代替バナー画像）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 文言・既定値
 * ---------------------------------------------------------------------- */
function apprex_line_badge_text() {
	return (string) get_option( 'apprex_line_badge', '📱 新着記事｜APPREX' );
}
function apprex_line_cta_text() {
	$v = (string) get_option( 'apprex_line_cta', '' );
	return '' !== $v ? $v : '記事を読む ▶';
}
/** アイキャッチが無い記事のための代替バナー画像URL（任意）。 */
function apprex_line_banner_fallback() {
	return (string) get_option( 'apprex_line_banner_fallback', '' );
}

/* -------------------------------------------------------------------------
 * Flexメッセージ生成
 * ---------------------------------------------------------------------- */

/**
 * post_published データから LINE Flex メッセージ（1バブル）を組み立てる。
 *
 * @param array $data { id, title, url, excerpt, image }。
 * @return array|null LINE メッセージオブジェクト（type:flex）。生成不可なら null。
 */
function apprex_line_flex_build( $data ) {
	$title = isset( $data['title'] ) ? trim( wp_strip_all_tags( (string) $data['title'] ) ) : '';
	$url   = isset( $data['url'] ) ? (string) $data['url'] : '';
	if ( '' === $title || '' === $url ) {
		return null;
	}
	$excerpt = isset( $data['excerpt'] ) ? trim( wp_strip_all_tags( (string) $data['excerpt'] ) ) : '';
	$banner  = ! empty( $data['image'] ) ? (string) $data['image'] : apprex_line_banner_fallback();

	// 記事へ遷移するアクション（画像・ボタン・カード共通）。
	$action = array(
		'type'  => 'uri',
		'label' => '記事を読む',
		'uri'   => $url,
	);

	// 本文（バッジ＋タイトル＋抜粋）。
	$body_contents = array(
		array(
			'type'   => 'text',
			'text'   => mb_substr( apprex_line_badge_text(), 0, 40 ),
			'size'   => 'xs',
			'color'  => '#06C755',
			'weight' => 'bold',
			'wrap'   => true,
		),
		array(
			'type'   => 'text',
			'text'   => mb_substr( $title, 0, 120 ),
			'size'   => 'lg',
			'weight' => 'bold',
			'wrap'   => true,
			'margin' => 'sm',
		),
	);
	if ( '' !== $excerpt ) {
		$body_contents[] = array(
			'type'     => 'text',
			'text'     => mb_substr( $excerpt, 0, 200 ),
			'size'     => 'sm',
			'color'    => '#666666',
			'wrap'     => true,
			'maxLines' => 3,
			'margin'   => 'md',
		);
	}

	$bubble = array(
		'type'   => 'bubble',
		'action' => $action, // カード全体タップでも記事へ。
		'body'   => array(
			'type'     => 'box',
			'layout'   => 'vertical',
			'spacing'  => 'sm',
			'contents' => $body_contents,
		),
		'footer' => array(
			'type'     => 'box',
			'layout'   => 'vertical',
			'contents' => array(
				array(
					'type'   => 'button',
					'style'  => 'primary',
					'color'  => '#06C755',
					'height' => 'sm',
					'action' => array(
						'type'  => 'uri',
						'label' => mb_substr( apprex_line_cta_text(), 0, 40 ),
						'uri'   => $url,
					),
				),
			),
		),
	);

	// バナー画像（HTTPSのみ。LINEはhttps画像のみ受理）。
	if ( $banner && 0 === strpos( $banner, 'https://' ) ) {
		$bubble['hero'] = array(
			'type'        => 'image',
			'url'         => $banner,
			'size'        => 'full',
			'aspectRatio' => '20:13',
			'aspectMode'  => 'cover',
			'action'      => $action,
		);
	}

	$alt = mb_substr( '【新着記事】' . $title, 0, 390 );

	return array(
		'type'     => 'flex',
		'altText'  => $alt,
		'contents' => $bubble,
	);
}

/* -------------------------------------------------------------------------
 * post_published ペイロードに line_flex を付与
 * ---------------------------------------------------------------------- */
add_filter( 'apprex_dispatch_payload', function ( $payload, $event, $data ) {
	if ( 'post_published' !== $event ) {
		return $payload;
	}
	$flex = apprex_line_flex_build( $data );
	if ( $flex ) {
		$payload['data']['line_flex'] = $flex;          // GASはこれをLINEへ転送。
		$payload['data']['line_alt']  = $flex['altText']; // 通知/フォールバック用。
	}
	return $payload;
}, 10, 3 );

/* -------------------------------------------------------------------------
 * 設定（APPREX 連携ページに項目を追加）
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	register_setting( 'apprex_integrations', 'apprex_line_badge', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_integrations', 'apprex_line_cta', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_integrations', 'apprex_line_banner_fallback', array( 'sanitize_callback' => 'esc_url_raw' ) );
} );
