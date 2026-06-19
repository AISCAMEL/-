<?php
/**
 * SNS 直接投稿（GAS不要）：Facebookページ / X(Twitter) / Instagram。
 *
 * 記事公開時に各SNSへ直接投稿する。各SNSの認証情報が設定されている場合のみ動作し、
 * 投稿失敗してもWordPressの公開処理はブロックしない（ログのみ）。
 *
 * 設定：設定 → APPREX 配信(SNS)
 * 各記事：「📣 SNS配信」ボックスで媒体ごとにON/OFF＋「今すぐ投稿」。
 *
 * ※ 外部API依存のため、設定後は各媒体で実際の投稿テストを行ってください。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 設定アクセサ
 * ====================================================================== */
function apprex_fb_ready() {
	return get_option( 'apprex_fb_enabled' ) && get_option( 'apprex_fb_page_id' ) && get_option( 'apprex_fb_token' );
}
function apprex_x_ready() {
	return get_option( 'apprex_x_enabled' ) && get_option( 'apprex_x_api_key' ) && get_option( 'apprex_x_api_secret' )
		&& get_option( 'apprex_x_token' ) && get_option( 'apprex_x_token_secret' );
}
function apprex_ig_ready() {
	return get_option( 'apprex_ig_enabled' ) && get_option( 'apprex_ig_user_id' ) && get_option( 'apprex_ig_token' );
}

/** 投稿テキスト（タイトル＋短縮URL、必要なら抜粋）。$max でX用に制限。 */
function apprex_sns_text( $post, $max = 0, $with_url = true ) {
	$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );
	$url   = wp_get_shortlink( $post->ID );
	if ( ! $url ) {
		$url = get_permalink( $post );
	}
	$text = $title;
	if ( $with_url ) {
		$text .= "\n" . $url;
	}
	if ( $max > 0 && mb_strlen( $text ) > $max ) {
		// URL分を確保してタイトルを詰める。
		$reserve = $with_url ? ( mb_strlen( $url ) + 2 ) : 0;
		$text    = mb_substr( $title, 0, max( 1, $max - $reserve - 1 ) ) . '…' . ( $with_url ? "\n" . $url : '' );
	}
	return $text;
}

/* =========================================================================
 * Facebook ページ投稿（Graph API）
 * ====================================================================== */
function apprex_fb_post_article( $post ) {
	$page = get_option( 'apprex_fb_page_id' );
	$tok  = get_option( 'apprex_fb_token' );
	$res  = wp_remote_post(
		'https://graph.facebook.com/v19.0/' . rawurlencode( $page ) . '/feed',
		array(
			'timeout' => 20,
			'body'    => array(
				'message'      => apprex_sns_text( $post, 0, false ) . "\n" . wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
				'link'         => get_permalink( $post ),
				'access_token' => $tok,
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$b = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $b['id'] ) ) {
		return new WP_Error( 'fb', isset( $b['error']['message'] ) ? $b['error']['message'] : 'Facebook投稿に失敗' );
	}
	return true;
}

/* =========================================================================
 * X (Twitter) 投稿（API v2 + OAuth1.0a）
 * ====================================================================== */
function apprex_x_oauth_header( $url, $params ) {
	$oauth = array(
		'oauth_consumer_key'     => get_option( 'apprex_x_api_key' ),
		'oauth_nonce'            => wp_generate_password( 32, false ),
		'oauth_signature_method' => 'HMAC-SHA1',
		'oauth_timestamp'        => (string) time(),
		'oauth_token'            => get_option( 'apprex_x_token' ),
		'oauth_version'          => '1.0',
	);
	$base_params = array_merge( $oauth, $params );
	ksort( $base_params );
	$pairs = array();
	foreach ( $base_params as $k => $v ) {
		$pairs[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
	}
	$base = 'POST&' . rawurlencode( $url ) . '&' . rawurlencode( implode( '&', $pairs ) );
	$key  = rawurlencode( get_option( 'apprex_x_api_secret' ) ) . '&' . rawurlencode( get_option( 'apprex_x_token_secret' ) );
	$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base, $key, true ) );

	$header = 'OAuth ';
	$parts  = array();
	foreach ( $oauth as $k => $v ) {
		$parts[] = rawurlencode( $k ) . '="' . rawurlencode( $v ) . '"';
	}
	return $header . implode( ', ', $parts );
}

function apprex_x_post_article( $post ) {
	$url  = 'https://api.twitter.com/2/tweets';
	$text = apprex_sns_text( $post, 270, true );
	// /2/tweets は JSON ボディ。OAuth1.0a 署名はボディを含めない（クエリ無し）。
	$auth = apprex_x_oauth_header( $url, array() );
	$res  = wp_remote_post(
		$url,
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => $auth,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array( 'text' => $text ) ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	$b    = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 || empty( $b['data']['id'] ) ) {
		$msg = isset( $b['detail'] ) ? $b['detail'] : ( isset( $b['title'] ) ? $b['title'] : ( 'HTTP ' . $code ) );
		return new WP_Error( 'x', $msg );
	}
	return true;
}

/* =========================================================================
 * Instagram 投稿（Graph API・画像必須・2段階）
 * ====================================================================== */
function apprex_ig_post_article( $post ) {
	$igid  = get_option( 'apprex_ig_user_id' );
	$tok   = get_option( 'apprex_ig_token' );
	$image = get_the_post_thumbnail_url( $post, 'large' );
	if ( ! $image || 0 !== strpos( $image, 'https://' ) ) {
		return new WP_Error( 'ig', 'Instagramはアイキャッチ画像（https）が必須です。記事に画像を設定してください。' );
	}
	$caption = apprex_sns_text( $post, 0, true ) . "\n" . wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

	// 1) メディアコンテナ作成
	$c = wp_remote_post(
		'https://graph.facebook.com/v19.0/' . rawurlencode( $igid ) . '/media',
		array(
			'timeout' => 25,
			'body'    => array(
				'image_url'    => $image,
				'caption'      => $caption,
				'access_token' => $tok,
			),
		)
	);
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$cb = json_decode( wp_remote_retrieve_body( $c ), true );
	if ( empty( $cb['id'] ) ) {
		return new WP_Error( 'ig', isset( $cb['error']['message'] ) ? $cb['error']['message'] : 'IGコンテナ作成に失敗' );
	}

	// 2) 公開
	$p = wp_remote_post(
		'https://graph.facebook.com/v19.0/' . rawurlencode( $igid ) . '/media_publish',
		array(
			'timeout' => 25,
			'body'    => array(
				'creation_id'  => $cb['id'],
				'access_token' => $tok,
			),
		)
	);
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$pb = json_decode( wp_remote_retrieve_body( $p ), true );
	if ( empty( $pb['id'] ) ) {
		return new WP_Error( 'ig', isset( $pb['error']['message'] ) ? $pb['error']['message'] : 'IG公開に失敗' );
	}
	return true;
}

/* =========================================================================
 * 公開時の自動投稿
 * ====================================================================== */
function apprex_sns_networks() {
	return array(
		'fb' => array( 'label' => 'Facebookページ', 'ready' => 'apprex_fb_ready', 'send' => 'apprex_fb_post_article' ),
		'x'  => array( 'label' => 'X（Twitter）', 'ready' => 'apprex_x_ready', 'send' => 'apprex_x_post_article' ),
		'ig' => array( 'label' => 'Instagram', 'ready' => 'apprex_ig_ready', 'send' => 'apprex_ig_post_article' ),
	);
}

/** 1記事を指定ネットワークへ投稿。 */
function apprex_sns_send( $post_id, $net ) {
	$nets = apprex_sns_networks();
	if ( ! isset( $nets[ $net ] ) || ! call_user_func( $nets[ $net ]['ready'] ) ) {
		return new WP_Error( 'sns', $net . ' は未設定です。' );
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'sns', '記事が見つかりません。' );
	}
	$r = call_user_func( $nets[ $net ]['send'], $post );
	if ( ! is_wp_error( $r ) ) {
		update_post_meta( $post_id, 'apprex_' . $net . '_sent', current_time( 'mysql' ) );
	}
	return $r;
}

add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || 'post' !== $post->post_type ) {
		return;
	}
	foreach ( apprex_sns_networks() as $net => $info ) {
		if ( ! call_user_func( $info['ready'] ) ) {
			continue;
		}
		$flag = get_post_meta( $post->ID, 'apprex_dist_' . $net, true );
		$on   = ( '' === $flag ) ? true : ( '1' === $flag ); // 既定ON（設定済み媒体のみ）。
		if ( ! $on || get_post_meta( $post->ID, 'apprex_' . $net . '_sent', true ) ) {
			continue;
		}
		$r = apprex_sns_send( $post->ID, $net );
		if ( is_wp_error( $r ) ) {
			error_log( '[APPREX SNS ' . $net . '] ' . $r->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}, 11, 3 );

/* =========================================================================
 * 記事編集：SNS配信ボックス
 * ====================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_sns_box', '📣 SNS配信', 'apprex_sns_box', 'post', 'side', 'high' );
} );

function apprex_sns_box( $post ) {
	wp_nonce_field( 'apprex_sns_box', 'apprex_sns_box_nonce' );
	$any = false;
	foreach ( apprex_sns_networks() as $net => $info ) {
		$ready = call_user_func( $info['ready'] );
		if ( ! $ready ) {
			continue;
		}
		$any  = true;
		$flag = get_post_meta( $post->ID, 'apprex_dist_' . $net, true );
		$on   = ( '' === $flag ) ? true : ( '1' === $flag );
		$sent = get_post_meta( $post->ID, 'apprex_' . $net . '_sent', true );
		echo '<p style="margin:4px 0;"><label><input type="checkbox" name="apprex_dist_' . esc_attr( $net ) . '" value="1" ' . checked( $on, true, false ) . '> ' . esc_html( $info['label'] ) . '</label>';
		echo $sent ? ' <span style="color:#15803d;font-size:11px;">✅' . esc_html( $sent ) . '</span>' : ' <span style="color:#9ca3af;font-size:11px;">未投稿</span>';
		echo '</p>';
	}
	if ( ! $any ) {
		echo '<p style="color:#b91c1c;">SNSが未設定です。<br><a href="' . esc_url( admin_url( 'options-general.php?page=apprex-sns' ) ) . '">設定はこちら</a></p>';
		return;
	}
	echo '<p class="description">公開時、チェックした媒体へ自動投稿します。</p>';
	if ( 'publish' === $post->post_status ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_sns_send&post=' . $post->ID ), 'apprex_sns_send_' . $post->ID );
		echo '<a class="button button-primary" style="width:100%;text-align:center;" href="' . esc_url( $url ) . '">📣 今すぐSNSに投稿</a>';
	}
}

add_action( 'save_post_post', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['apprex_sns_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['apprex_sns_box_nonce'] ) ), 'apprex_sns_box' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array_keys( apprex_sns_networks() ) as $net ) {
		update_post_meta( $post_id, 'apprex_dist_' . $net, isset( $_POST[ 'apprex_dist_' . $net ] ) ? '1' : '0' );
	}
} );

add_action( 'admin_post_apprex_sns_send', function () {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_sns_send_' . $post_id );
	$done = array();
	$errs = array();
	foreach ( apprex_sns_networks() as $net => $info ) {
		if ( ! call_user_func( $info['ready'] ) || '1' !== get_post_meta( $post_id, 'apprex_dist_' . $net, true ) ) {
			continue;
		}
		$r = apprex_sns_send( $post_id, $net );
		if ( is_wp_error( $r ) ) {
			$errs[] = $info['label'] . '：' . $r->get_error_message();
		} else {
			$done[] = $info['label'];
		}
	}
	$back = get_edit_post_link( $post_id, 'url' );
	$msg  = ( $done ? '投稿成功：' . implode( '・', $done ) . '。' : '' ) . ( $errs ? ' 失敗：' . implode(' / ', $errs ) : '' );
	if ( '' === trim( $msg ) ) {
		$msg = 'チェックされた媒体がありません。';
	}
	wp_safe_redirect( add_query_arg( 'apprex_sns_msg', rawurlencode( $msg ), $back ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['apprex_sns_msg'] ) ) {
		return;
	}
	echo '<div class="notice notice-info is-dismissible"><p>SNS配信：' . esc_html( rawurldecode( wp_unslash( $_GET['apprex_sns_msg'] ) ) ) . '</p></div>';
} );

/* =========================================================================
 * 設定ページ
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 配信(SNS)', 'APPREX 配信(SNS)', 'manage_options', 'apprex-sns', 'apprex_sns_settings_page' );
} );
add_action( 'admin_init', function () {
	foreach ( array(
		'apprex_fb_enabled'    => 'absint',
		'apprex_fb_page_id'    => 'sanitize_text_field',
		'apprex_fb_token'      => 'sanitize_text_field',
		'apprex_x_enabled'     => 'absint',
		'apprex_x_api_key'     => 'sanitize_text_field',
		'apprex_x_api_secret'  => 'sanitize_text_field',
		'apprex_x_token'       => 'sanitize_text_field',
		'apprex_x_token_secret' => 'sanitize_text_field',
		'apprex_ig_enabled'    => 'absint',
		'apprex_ig_user_id'    => 'sanitize_text_field',
		'apprex_ig_token'      => 'sanitize_text_field',
	) as $opt => $cb ) {
		register_setting( 'apprex_sns_dist', $opt, array( 'sanitize_callback' => $cb ) );
	}
} );

function apprex_sns_field( $opt, $ph = '' ) {
	echo '<input type="text" name="' . esc_attr( $opt ) . '" class="regular-text" value="' . esc_attr( get_option( $opt, '' ) ) . '" placeholder="' . esc_attr( $ph ) . '" autocomplete="off">';
}

function apprex_sns_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>APPREX 配信（SNS・GAS不要）</h1>
		<p>記事公開時に各SNSへ直接投稿します。各媒体の認証情報を入れ、媒体ごとに有効化してください。設定後は実際の投稿テストを推奨します。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_sns_dist' ); ?>

			<h2>Facebook ページ</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>有効化</th><td><label><input type="checkbox" name="apprex_fb_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_fb_enabled', 0 ) ); ?>> Facebookページへ投稿する</label></td></tr>
				<tr><th>ページID</th><td><?php apprex_sns_field( 'apprex_fb_page_id', '例）1234567890' ); ?></td></tr>
				<tr><th>ページアクセストークン（長期）</th><td><?php apprex_sns_field( 'apprex_fb_token' ); ?><p class="description">Meta for Developers → Graph API → ページの長期トークン。</p></td></tr>
			</tbody></table>

			<h2>X（Twitter）</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>有効化</th><td><label><input type="checkbox" name="apprex_x_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_x_enabled', 0 ) ); ?>> Xへ投稿する</label><p class="description">投稿には有料API（Basic以上）が必要です。</p></td></tr>
				<tr><th>API Key</th><td><?php apprex_sns_field( 'apprex_x_api_key' ); ?></td></tr>
				<tr><th>API Secret</th><td><?php apprex_sns_field( 'apprex_x_api_secret' ); ?></td></tr>
				<tr><th>Access Token</th><td><?php apprex_sns_field( 'apprex_x_token' ); ?></td></tr>
				<tr><th>Access Token Secret</th><td><?php apprex_sns_field( 'apprex_x_token_secret' ); ?><p class="description">開発者ポータルでアプリ権限を「Read and Write」にしてトークンを発行。</p></td></tr>
			</tbody></table>

			<h2>Instagram</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>有効化</th><td><label><input type="checkbox" name="apprex_ig_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_ig_enabled', 0 ) ); ?>> Instagramへ投稿する</label><p class="description">ビジネスアカウント＋アイキャッチ画像が必須です。</p></td></tr>
				<tr><th>IGビジネスアカウントID</th><td><?php apprex_sns_field( 'apprex_ig_user_id' ); ?></td></tr>
				<tr><th>アクセストークン</th><td><?php apprex_sns_field( 'apprex_ig_token' ); ?><p class="description">instagram_content_publish 権限付きのトークン（FBページ連携）。</p></td></tr>
			</tbody></table>

			<?php submit_button( '保存する' ); ?>
		</form>
	</div>
	<?php
}
