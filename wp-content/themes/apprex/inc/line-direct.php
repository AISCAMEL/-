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

		<?php
		// ── AI自動応答 診断 ──────────────────────────────
		$diag_token  = '' !== apprex_line_channel_token();
		$diag_secret = function_exists( 'apprex_line_channel_secret' ) && '' !== apprex_line_channel_secret();
		$diag_ai     = (int) get_option( 'apprex_line_ai_enabled', 0 );
		$diag_key    = function_exists( 'apprex_openrouter_key' ) && '' !== apprex_openrouter_key();
		$hook_last   = (int) get_option( 'apprex_line_hook_last', 0 );
		$hook_note   = (string) get_option( 'apprex_line_hook_note', '' );
		$ai_last     = get_option( 'apprex_line_ai_last', array() );
		$ok = function ( $b ) {
			return $b ? '<span style="color:#15803d;font-weight:700;">✓ OK</span>' : '<span style="color:#b91c1c;font-weight:700;">✗ 未設定</span>';
		};
		?>
		<h2>AI自動応答の診断</h2>
		<table class="widefat striped" style="max-width:760px;">
			<tbody>
				<tr><td style="width:280px;">AI自動応答スイッチ</td><td><?php echo $diag_ai ? '<span style="color:#15803d;font-weight:700;">✓ ON</span>' : '<span style="color:#b91c1c;font-weight:700;">✗ OFF（下でONにしてください）</span>'; ?></td></tr>
				<tr><td>OpenRouter APIキー（AIの頭脳）</td><td><?php echo wp_kses_post( $ok( $diag_key ) ); ?> <span class="description">設定：APPREX チャット</span></td></tr>
				<tr><td>チャネルアクセストークン（返信用）</td><td><?php echo wp_kses_post( $ok( $diag_token ) ); ?></td></tr>
				<tr><td>チャネルシークレット（署名検証）</td><td><?php echo wp_kses_post( $ok( $diag_secret ) ); ?> <span class="description">設定：APPREX LINEステップ</span></td></tr>
				<tr><td>Webhook 最終受信</td><td><?php
					if ( $hook_last ) {
						echo esc_html( wp_date( 'n/j H:i', $hook_last ) ) . '　' . esc_html( $hook_note );
					} else {
						echo '<span style="color:#b91c1c;font-weight:700;">✗ まだ一度も受信していません</span><br><span class="description">→ LINE DevelopersでWebhook URL登録＋「Webhookの利用」ONを確認。URL：<code>' . esc_html( home_url( '/line/webhook' ) ) . '</code></span>';
					}
				?></td></tr>
				<tr><td>AI 最終応答</td><td><?php
					if ( is_array( $ai_last ) && ! empty( $ai_last['t'] ) ) {
						$col = ( 'ok' === $ai_last['s'] ) ? '#15803d' : '#b91c1c';
						echo '<span style="color:' . esc_attr( $col ) . ';font-weight:700;">' . esc_html( 'ok' === $ai_last['s'] ? '✓ 応答成功' : '✗ 失敗' ) . '</span> ' . esc_html( wp_date( 'n/j H:i', (int) $ai_last['t'] ) . '：' . ( isset( $ai_last['n'] ) ? $ai_last['n'] : '' ) );
					} else {
						echo '<span class="description">まだ応答記録なし（LINEでメッセージを送ってテスト）</span>';
					}
				?></td></tr>
			</tbody>
		</table>
		<p class="description" style="max-width:760px;">
			<strong>動かない時のチェック順：</strong>
			①「Webhook 最終受信」が「未受信」→ LINE側のWebhook URL登録／「Webhookの利用」ON／<a href="<?php echo esc_url( admin_url( 'options-general.php?page=apprex-line-steps' ) ); ?>">シークレット入力</a>／パーマリンク保存 を確認。
			② 受信はあるのに応答しない →「AI 最終応答」のエラー文を確認。
			③ LINE公式アカウントの<strong>「応答モード」をBotにし、「あいさつ/応答メッセージ（定型）」をOFF</strong>。
		</p>
		<hr>

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
				<tr>
					<th scope="row">AI自動応答</th>
					<td><label><input type="checkbox" name="apprex_line_ai_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_line_ai_enabled', 0 ) ); ?>> LINEに届いたメッセージへAIが自動返信する</label>
					<p class="description">サイトのAIチャットと同じ頭脳で返信します。<strong>「APPREX チャット」のOpenRouter APIキー</strong>と、上のチャネルアクセストークン、Webhook（/line/webhook）の設定が必要です。</p></td>
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
