<?php
/**
 * LINE ステップ配信（友だち追加起点のドリップ）。
 *
 * 仕組み：
 *  1) LINE Webhook（/line/webhook）で follow（友だち追加）/ unfollow を受信し、友だちを登録。
 *  2) cron（hourly）が各友だちの経過時間に応じて、ステップメッセージを push 送信。
 *  3) 管理画面「APPREX LINEステップ」でステップ（経過時間＋本文＋任意画像）を追加/編集/削除。
 *
 * 送信トークンは inc/line-direct.php の apprex_line_channel_token() を共用。
 * Webhook署名検証にチャネルシークレットが必要。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_line_channel_secret() {
	return (string) get_option( 'apprex_line_channel_secret', '' );
}

/** ステップ定義（配列：[ offset(分), text, image ]）。 */
function apprex_line_steps() {
	$s = get_option( 'apprex_line_steps', array() );
	return is_array( $s ) ? $s : array();
}

/* =========================================================================
 * 友だちCPT
 * ====================================================================== */
add_action( 'init', function () {
	register_post_type(
		'apprex_line_friend',
		array(
			'labels'       => array( 'name' => 'LINE友だち', 'singular_name' => 'LINE友だち' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'options-general.php',
			'menu_icon'    => 'dashicons-groups',
			'supports'     => array( 'title' ),
			'capability_type' => 'post',
		)
	);
} );

/** userId から友だちレコードを取得（無ければ作成可）。 */
function apprex_line_friend_get( $uid, $create = false ) {
	$q = get_posts(
		array(
			'post_type'      => 'apprex_line_friend',
			'post_status'    => 'any',
			'meta_key'       => 'apprex_uid',
			'meta_value'     => $uid,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( $q ) {
		return (int) $q[0];
	}
	if ( ! $create ) {
		return 0;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'apprex_line_friend',
			'post_status' => 'publish',
			'post_title'  => 'LINE ' . substr( $uid, 0, 12 ),
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, 'apprex_uid', $uid );
		return (int) $id;
	}
	return 0;
}

/* =========================================================================
 * Webhook エンドポイント /line/webhook
 * ====================================================================== */
add_action( 'init', function () {
	add_rewrite_rule( '^line/webhook/?$', 'index.php?apprex_line_webhook=1', 'top' );
	if ( '1' !== get_option( 'apprex_line_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_line_rw', '1' );
	}
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'apprex_line_webhook';
	return $v;
} );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_line_webhook' ) ) {
		return;
	}
	$secret = apprex_line_channel_secret();
	$body   = file_get_contents( 'php://input' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$sig    = isset( $_SERVER['HTTP_X_LINE_SIGNATURE'] ) ? wp_unslash( $_SERVER['HTTP_X_LINE_SIGNATURE'] ) : '';

	// 署名検証（シークレット設定時）。
	if ( $secret ) {
		$calc = base64_encode( hash_hmac( 'sha256', $body, $secret, true ) );
		if ( ! hash_equals( $calc, (string) $sig ) ) {
			status_header( 403 );
			exit;
		}
	}

	$data = json_decode( $body, true );
	if ( ! empty( $data['events'] ) && is_array( $data['events'] ) ) {
		foreach ( $data['events'] as $ev ) {
			$uid  = isset( $ev['source']['userId'] ) ? sanitize_text_field( $ev['source']['userId'] ) : '';
			$type = isset( $ev['type'] ) ? $ev['type'] : '';
			if ( '' === $uid ) {
				continue;
			}
			if ( 'follow' === $type ) {
				$fid = apprex_line_friend_get( $uid, true );
				if ( $fid ) {
					update_post_meta( $fid, 'apprex_follow_at', time() );
					update_post_meta( $fid, 'apprex_active', 1 );
					update_post_meta( $fid, 'apprex_steps_sent', array() );
				}
			} elseif ( 'unfollow' === $type ) {
				$fid = apprex_line_friend_get( $uid, false );
				if ( $fid ) {
					update_post_meta( $fid, 'apprex_active', 0 );
				}
			}
		}
	}
	status_header( 200 );
	echo 'OK';
	exit;
} );

/* =========================================================================
 * push 送信
 * ====================================================================== */
function apprex_line_push( $uid, $messages ) {
	$token = function_exists( 'apprex_line_channel_token' ) ? apprex_line_channel_token() : '';
	if ( '' === $token ) {
		return new WP_Error( 'line', 'チャネルアクセストークン未設定' );
	}
	$res = wp_remote_post(
		'https://api.line.me/v2/bot/message/push',
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array( 'to' => $uid, 'messages' => array_values( $messages ) ) ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		$b = json_decode( wp_remote_retrieve_body( $res ), true );
		return new WP_Error( 'line', isset( $b['message'] ) ? $b['message'] : ( 'HTTP ' . $code ) );
	}
	return true;
}

/** 1ステップをLINEメッセージ配列に変換。 */
function apprex_line_step_messages( $step ) {
	$msgs = array();
	$text = isset( $step['text'] ) ? trim( (string) $step['text'] ) : '';
	if ( '' !== $text ) {
		$msgs[] = array( 'type' => 'text', 'text' => mb_substr( $text, 0, 4900 ) );
	}
	$img = isset( $step['image'] ) ? trim( (string) $step['image'] ) : '';
	if ( $img && 0 === strpos( $img, 'https://' ) ) {
		$msgs[] = array( 'type' => 'image', 'originalContentUrl' => $img, 'previewImageUrl' => $img );
	}
	return $msgs;
}

/* =========================================================================
 * cron：期限到来ステップを配信
 * ====================================================================== */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_line_steps_cron' ) ) {
		wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'apprex_line_steps_cron' );
	}
} );
add_action( 'apprex_line_steps_cron', 'apprex_line_process_steps' );

function apprex_line_process_steps() {
	$steps = apprex_line_steps();
	if ( ! $steps || ! function_exists( 'apprex_line_channel_token' ) || '' === apprex_line_channel_token() ) {
		return;
	}
	$friends = get_posts(
		array(
			'post_type'      => 'apprex_line_friend',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'meta_key'       => 'apprex_active',
			'meta_value'     => 1,
			'fields'         => 'ids',
		)
	);
	$now = time();
	foreach ( $friends as $fid ) {
		$uid = get_post_meta( $fid, 'apprex_uid', true );
		$at  = (int) get_post_meta( $fid, 'apprex_follow_at', true );
		if ( ! $uid || ! $at ) {
			continue;
		}
		$sent    = get_post_meta( $fid, 'apprex_steps_sent', true );
		$sent    = is_array( $sent ) ? $sent : array();
		$elapsed = ( $now - $at ) / 60; // 分
		$changed = false;
		foreach ( $steps as $i => $step ) {
			if ( in_array( $i, $sent, true ) ) {
				continue;
			}
			if ( $elapsed >= (int) $step['offset'] ) {
				$msgs = apprex_line_step_messages( $step );
				if ( $msgs ) {
					$r = apprex_line_push( $uid, $msgs );
					if ( ! is_wp_error( $r ) ) {
						$sent[]  = $i;
						$changed = true;
					}
				} else {
					$sent[]  = $i; // 空ステップはスキップ済み扱い。
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			update_post_meta( $fid, 'apprex_steps_sent', $sent );
		}
	}
}

/* =========================================================================
 * 管理画面：ステップ編集
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX LINEステップ', 'APPREX LINEステップ', 'manage_options', 'apprex-line-steps', 'apprex_line_steps_page' );
} );

/** 保存（シークレット＋ステップ）。 */
add_action( 'admin_post_apprex_line_steps_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_line_steps_save' );

	update_option( 'apprex_line_channel_secret', isset( $_POST['secret'] ) ? sanitize_text_field( wp_unslash( $_POST['secret'] ) ) : '' );

	$steps = array();
	$units = isset( $_POST['unit'] ) ? (array) $_POST['unit'] : array();
	$vals  = isset( $_POST['offset'] ) ? (array) $_POST['offset'] : array();
	$texts = isset( $_POST['text'] ) ? (array) $_POST['text'] : array();
	$imgs  = isset( $_POST['image'] ) ? (array) $_POST['image'] : array();
	foreach ( $texts as $i => $t ) {
		$t = sanitize_textarea_field( wp_unslash( $t ) );
		$img = isset( $imgs[ $i ] ) ? esc_url_raw( wp_unslash( $imgs[ $i ] ) ) : '';
		if ( '' === trim( $t ) && '' === $img ) {
			continue; // 空行は削除扱い。
		}
		$unit = isset( $units[ $i ] ) ? (int) $units[ $i ] : 1440;
		$val  = isset( $vals[ $i ] ) ? max( 0, (int) $vals[ $i ] ) : 0;
		$steps[] = array(
			'offset' => $val * $unit, // 分換算
			'text'   => $t,
			'image'  => $img,
		);
	}
	// 経過時間順に並べ替え。
	usort( $steps, function ( $a, $b ) {
		return $a['offset'] <=> $b['offset'];
	} );
	update_option( 'apprex_line_steps', $steps );

	wp_safe_redirect( add_query_arg( 'apprex_ls', 'saved', admin_url( 'options-general.php?page=apprex-line-steps' ) ) );
	exit;
} );

/** 分→人間可読。 */
function apprex_line_offset_label( $min ) {
	$min = (int) $min;
	if ( 0 === $min ) {
		return '友だち追加直後';
	}
	if ( 0 === $min % 1440 ) {
		return ( $min / 1440 ) . '日後';
	}
	if ( 0 === $min % 60 ) {
		return ( $min / 60 ) . '時間後';
	}
	return $min . '分後';
}

function apprex_line_steps_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$steps   = apprex_line_steps();
	$secret  = apprex_line_channel_secret();
	$hook    = home_url( '/line/webhook' );
	$friends = (int) wp_count_posts( 'apprex_line_friend' )->publish;
	// 編集用に空行を2つ足す。
	$rows = $steps;
	$rows[] = array( 'offset' => 1440, 'text' => '', 'image' => '' );
	$rows[] = array( 'offset' => 4320, 'text' => '', 'image' => '' );
	?>
	<div class="wrap">
		<h1>APPREX LINEステップ配信</h1>

		<?php if ( isset( $_GET['apprex_ls'] ) && 'saved' === $_GET['apprex_ls'] ) : ?>
			<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>
		<?php endif; ?>

		<div class="notice notice-info"><p>
			友だち追加（follow）から経過時間に応じて、LINEで自動メッセージを送ります（メールのステップ配信のLINE版）。<br>
			<strong>登録中の友だち：<?php echo esc_html( $friends ); ?> 人</strong>
		</p></div>

		<div class="notice notice-warning"><p>
			<strong>LINE側の設定（1回だけ）</strong>：LINE Developers → Messaging API設定 →<br>
			・<strong>Webhook URL</strong> に <code><?php echo esc_html( $hook ); ?></code> を登録し「Webhookの利用」をON<br>
			・<strong>チャネルシークレット</strong>を下に入力（署名検証用）<br>
			・あいさつメッセージ/自動応答はお好みで（ステップ配信はこのプラグインが担当）
		</p></div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_line_steps_save">
			<?php wp_nonce_field( 'apprex_line_steps_save' ); ?>

			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="secret">チャネルシークレット</label></th>
					<td><input type="text" id="secret" name="secret" class="regular-text" value="<?php echo esc_attr( $secret ); ?>" autocomplete="off" placeholder="Webhook署名検証用">
					<p class="description">未設定だと署名検証をスキップします（設定を強く推奨）。</p></td>
				</tr>
			</tbody></table>

			<h2>ステップ（経過時間順に自動並び替え）</h2>
			<p class="description">本文・画像が両方空の行は削除されます。画像はhttpsのURLのみ。</p>
			<table class="widefat striped" style="max-width:920px;">
				<thead><tr><th style="width:160px;">送信タイミング</th><th>本文 / 画像URL（任意）</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $i => $s ) :
					// 既存はラベル、単位の初期値は日。
					$min  = (int) ( isset( $s['offset'] ) ? $s['offset'] : 0 );
					if ( 0 === $min % 1440 ) { $unit = 1440; $val = $min / 1440; }
					elseif ( 0 === $min % 60 ) { $unit = 60; $val = $min / 60; }
					else { $unit = 1; $val = $min; }
					?>
					<tr>
						<td>
							<input type="number" name="offset[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $val ); ?>" min="0" style="width:80px;">
							<select name="unit[<?php echo (int) $i; ?>]">
								<option value="1440" <?php selected( $unit, 1440 ); ?>>日後</option>
								<option value="60" <?php selected( $unit, 60 ); ?>>時間後</option>
								<option value="1" <?php selected( $unit, 1 ); ?>>分後</option>
							</select>
							<?php if ( isset( $steps[ $i ] ) ) : ?>
								<p class="description"><?php echo esc_html( apprex_line_offset_label( $min ) ); ?></p>
							<?php endif; ?>
						</td>
						<td>
							<textarea name="text[<?php echo (int) $i; ?>]" rows="3" style="width:100%;" placeholder="例）友だち追加ありがとうございます！APPREXは…"><?php echo esc_textarea( isset( $s['text'] ) ? $s['text'] : '' ); ?></textarea>
							<input type="url" name="image[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( isset( $s['image'] ) ? $s['image'] : '' ); ?>" style="width:100%;margin-top:4px;" placeholder="画像URL（任意・https）">
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">＋ さらに追加したい場合は、一番下の空行に入力して保存すると新しい空行が増えます。</p>

			<?php submit_button( '保存する' ); ?>
		</form>
	</div>
	<?php
}
