<?php
/**
 * LINE 直接配信（GAS不要）＋ 記事ごとの配信ON/OFF＋手動配信。
 *
 * LINE Messaging API のチャネルアクセストークンを使い、記事公開時に
 * 「新着記事」Flexバナーを WordPress から直接 broadcast する。
 *
 * - 設定：設定 → APPREX 配信(LINE)（チャネルアクセストークン・既定ON/OFF）
 * - 各記事：「LINEに配信する」チェック＋「今すぐLINEに配信」ボタン
 * - 二重送信防止：1記事1回（再配信は手動ボタンで明示的に）
 *
 * ※ Flexメッセージの組み立ては inc/line-banner.php の apprex_line_flex_build() を再利用。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_line_channel_token() {
	return (string) get_option( 'apprex_line_channel_token', '' );
}
/** 直接配信が使えるか（トークンあり）。 */
function apprex_line_direct_ready() {
	return '' !== apprex_line_channel_token();
}
/** 新規記事を既定で配信するか。 */
function apprex_line_distribute_default() {
	return (bool) get_option( 'apprex_line_distribute_default', 1 );
}

/* -------------------------------------------------------------------------
 * LINE API
 * ---------------------------------------------------------------------- */

/**
 * 全友だちへ broadcast。成功で true、失敗で WP_Error。
 *
 * @param array $messages LINE messageオブジェクトの配列。
 * @return true|WP_Error
 */
function apprex_line_broadcast( $messages ) {
	$token = apprex_line_channel_token();
	if ( '' === $token ) {
		return new WP_Error( 'line', 'チャネルアクセストークンが未設定です。' );
	}
	$res = wp_remote_post(
		'https://api.line.me/v2/bot/message/broadcast',
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array( 'messages' => array_values( $messages ) ) ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		$msg  = isset( $body['message'] ) ? $body['message'] : ( 'HTTP ' . $code );
		if ( ! empty( $body['details'][0]['message'] ) ) {
			$msg .= '（' . $body['details'][0]['message'] . '）';
		}
		return new WP_Error( 'line', $msg );
	}
	return true;
}

/** 投稿データ配列を組み立て（Flex生成用）。 */
function apprex_line_post_data( $post ) {
	return array(
		'id'      => $post->ID,
		'title'   => get_the_title( $post ),
		'url'     => get_permalink( $post ),
		'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 ),
		'image'   => get_the_post_thumbnail_url( $post, 'large' ),
	);
}

/** 1記事をLINEへ配信。成功で true。 */
function apprex_line_send_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'line', '記事が見つかりません。' );
	}
	$flex = function_exists( 'apprex_line_flex_build' ) ? apprex_line_flex_build( apprex_line_post_data( $post ) ) : null;
	if ( ! $flex ) {
		return new WP_Error( 'line', '配信メッセージを生成できません（タイトル/URLを確認）。' );
	}
	$r = apprex_line_broadcast( array( $flex ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	update_post_meta( $post_id, 'apprex_line_sent', current_time( 'mysql' ) );
	return true;
}

/* -------------------------------------------------------------------------
 * 公開時の自動配信（GAS不要）
 * ---------------------------------------------------------------------- */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old ) {
		return;
	}
	if ( 'post' !== $post->post_type ) {
		return;
	}
	if ( ! apprex_line_direct_ready() ) {
		return; // トークン未設定なら何もしない。
	}
	// 記事の配信トグル（未設定なら既定値）。
	$flag = get_post_meta( $post->ID, 'apprex_distribute_line', true );
	$on   = ( '' === $flag ) ? apprex_line_distribute_default() : ( '1' === $flag );
	if ( ! $on ) {
		return;
	}
	if ( get_post_meta( $post->ID, 'apprex_line_sent', true ) ) {
		return; // 二重送信防止。
	}
	$r = apprex_line_send_post( $post->ID );
	if ( is_wp_error( $r ) ) {
		error_log( '[APPREX LINE] ' . $r->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}, 9, 3 ); // GAS(10)より先に実行。

/* -------------------------------------------------------------------------
 * 記事編集画面：配信トグル＋手動配信
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_line_box', '📣 LINE配信', 'apprex_line_box', 'post', 'side', 'high' );
} );

function apprex_line_box( $post ) {
	wp_nonce_field( 'apprex_line_box', 'apprex_line_box_nonce' );
	$flag = get_post_meta( $post->ID, 'apprex_distribute_line', true );
	$on   = ( '' === $flag ) ? apprex_line_distribute_default() : ( '1' === $flag );
	$sent = get_post_meta( $post->ID, 'apprex_line_sent', true );

	if ( ! apprex_line_direct_ready() ) {
		echo '<p style="color:#b91c1c;">LINEチャネルアクセストークンが未設定です。<br><a href="' . esc_url( admin_url( 'options-general.php?page=apprex-line' ) ) . '">設定はこちら</a></p>';
		return;
	}

	echo '<p><label><input type="checkbox" name="apprex_distribute_line" value="1" ' . checked( $on, true, false ) . '> 公開時にLINEへ自動配信する</label></p>';

	if ( $sent ) {
		echo '<p style="color:#15803d;margin:6px 0;">✅ 配信済み：' . esc_html( $sent ) . '</p>';
	} else {
		echo '<p style="color:#6b7280;margin:6px 0;">未配信</p>';
	}

	if ( 'publish' === $post->post_status ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_line_send&post=' . $post->ID ), 'apprex_line_send_' . $post->ID );
		echo '<a class="button button-primary" style="width:100%;text-align:center;" href="' . esc_url( $url ) . '">' . ( $sent ? '🔁 もう一度LINEに配信' : '📣 今すぐLINEに配信' ) . '</a>';
		echo '<p class="description" style="margin-top:6px;">全友だちへ即時配信します。</p>';
	} else {
		echo '<p class="description">公開後に「今すぐ配信」ボタンが表示されます。</p>';
	}
}

add_action( 'save_post_post', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['apprex_line_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['apprex_line_box_nonce'] ) ), 'apprex_line_box' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'apprex_distribute_line', isset( $_POST['apprex_distribute_line'] ) ? '1' : '0' );
} );

add_action( 'admin_post_apprex_line_send', function () {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_line_send_' . $post_id );
	$r    = apprex_line_send_post( $post_id );
	$back = get_edit_post_link( $post_id, 'url' );
	$arg  = is_wp_error( $r ) ? array( 'apprex_line_err' => rawurlencode( $r->get_error_message() ) ) : array( 'apprex_line_ok' => 1 );
	wp_safe_redirect( add_query_arg( $arg, $back ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( isset( $_GET['apprex_line_ok'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>LINEへ配信しました。</p></div>';
	} elseif ( isset( $_GET['apprex_line_err'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>LINE配信エラー：' . esc_html( rawurldecode( wp_unslash( $_GET['apprex_line_err'] ) ) ) . '</p></div>';
	}
} );

/* -------------------------------------------------------------------------
 * 設定ページ
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 配信(LINE)', 'APPREX 配信(LINE)', 'manage_options', 'apprex-line', 'apprex_line_settings_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_line_dist', 'apprex_line_channel_token', array( 'sanitize_callback' => 'apprex_line_sanitize_token' ) );
	register_setting( 'apprex_line_dist', 'apprex_line_distribute_default', array( 'sanitize_callback' => 'absint' ) );
} );
function apprex_line_sanitize_token( $raw ) {
	$raw = trim( (string) wp_unslash( $raw ) );
	if ( '' !== $raw && false === strpos( $raw, '●' ) ) {
		return $raw;
	}
	return get_option( 'apprex_line_channel_token', '' );
}

/** 接続確認（ユーザーには送らず /v2/bot/info でトークン検証）。 */
add_action( 'admin_post_apprex_line_verify', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_line_verify' );
	$back = admin_url( 'options-general.php?page=apprex-line' );
	$res  = wp_remote_get(
		'https://api.line.me/v2/bot/info',
		array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . apprex_line_channel_token() ),
		)
	);
	if ( is_wp_error( $res ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_line_err', rawurlencode( $res->get_error_message() ), $back ) );
		exit;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['userId'] ) && empty( $body['basicId'] ) ) {
		$msg = isset( $body['message'] ) ? $body['message'] : 'トークンが正しくありません。';
		wp_safe_redirect( add_query_arg( 'apprex_line_err', rawurlencode( $msg ), $back ) );
		exit;
	}
	$name = isset( $body['displayName'] ) ? $body['displayName'] : 'LINE公式アカウント';
	wp_safe_redirect( add_query_arg( 'apprex_line_ok2', rawurlencode( '接続OK：「' . $name . '」を確認しました。' ), $back ) );
	exit;
} );

function apprex_line_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$has = '' !== apprex_line_channel_token();
	?>
	<div class="wrap">
		<h1>APPREX 配信（LINE・GAS不要）</h1>

		<?php if ( isset( $_GET['apprex_line_ok2'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( rawurldecode( wp_unslash( $_GET['apprex_line_ok2'] ) ) ); ?></p></div>
		<?php elseif ( isset( $_GET['apprex_line_err'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>エラー：<?php echo esc_html( rawurldecode( wp_unslash( $_GET['apprex_line_err'] ) ) ); ?></p></div>
		<?php endif; ?>

		<div class="notice notice-info"><p>
			<strong>準備</strong>：LINE Developers で<strong>Messaging APIチャネル</strong>を作成 →
			「チャネルアクセストークン（長期）」を発行 → 下に貼り付け。これで<strong>GASなしで</strong>記事公開時にLINEへ自動配信されます。
		</p></div>

		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_line_dist' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="apprex_line_channel_token">チャネルアクセストークン（長期）</label></th>
					<td><input type="password" id="apprex_line_channel_token" name="apprex_line_channel_token" class="large-text" value="<?php echo $has ? '●●●●●●●●●●●●●●●●' : ''; ?>" autocomplete="off" placeholder="LINE Messaging API の長期トークン">
					<p class="description"><?php echo $has ? '保存済み（変更時のみ入力）。' : 'LINE Developers → Messaging API設定 → チャネルアクセストークン（長期）。'; ?></p></td>
				</tr>
				<tr>
					<th scope="row">新規記事の既定</th>
					<td><label><input type="checkbox" name="apprex_line_distribute_default" value="1" <?php checked( 1, (int) get_option( 'apprex_line_distribute_default', 1 ) ); ?>> 新しい記事は既定で「公開時にLINE配信」をONにする</label>
					<p class="description">各記事の編集画面でON/OFFを個別に切り替えられます。</p></td>
				</tr>
			</tbody></table>
			<?php submit_button( '保存する' ); ?>
		</form>

		<hr>
		<h2>接続確認</h2>
		<p>友だちには送信せず、トークンが有効かだけを確認します。</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_line_verify">
			<?php wp_nonce_field( 'apprex_line_verify' ); ?>
			<?php submit_button( '接続を確認する', 'secondary', 'submit', false ); ?>
		</form>

		<hr>
		<p class="description">※ 「APPREX 連携設定」の GAS Webhook も併用すると<strong>二重配信</strong>になります。GAS不要にする場合は、連携設定のGAS Webhook URLを空にしてください。</p>
	</div>
	<?php
}
