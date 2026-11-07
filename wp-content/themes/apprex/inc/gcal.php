<?php
/**
 * Google カレンダーとミーティング予約をつなぐ。
 *
 * - 空き：Google カレンダーに予定が入っている時間は、予約の画面で「予約済み」にする（FreeBusy API）。
 *   予約を確定する直前にも、もう一度確かめる（ダブルブッキングを防ぐ）。
 * - 書き込み：予約が入ったら、Google カレンダーに予定を入れる。キャンセルしたら予定を消す。
 *
 * 仕組み：Google Cloud の「サービスアカウント」の鍵（JSON）を使う。
 * 使うカレンダーを、サービスアカウントのメールアドレスに共有する（予定の変更権限）。
 * 設定：お問い合わせ → ミーティング予約 のいちばん下。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** サービスアカウントの鍵（JSONを読んだもの）。 */
function apprex_gcal_sa() {
	$j = json_decode( (string) get_option( 'apprex_gcal_sa', '' ), true );
	if ( ! is_array( $j ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) ) {
		return null;
	}
	$j['private_key'] = apprex_gcal_fix_pem( $j['private_key'] );
	return $j;
}

/**
 * 鍵の文字を直す（改行が「n」や「\n」の文字になってしまったもの）。
 *
 * PEM は「-----BEGIN PRIVATE KEY-----」＋64文字ごとの行＋「-----END PRIVATE KEY-----」。
 */
function apprex_gcal_fix_pem( $pem ) {
	$pem = str_replace( array( "\r\n", "\r" ), "\n", (string) $pem );
	$pem = str_replace( '\\n', "\n", $pem ); // 「\n」の2文字になっているもの。
	if ( false !== strpos( $pem, "\n" ) && preg_match( '/-----BEGIN [A-Z ]+-----\n/', $pem ) ) {
		return $pem;
	}
	// 改行が「n」1文字になっているもの：中身を64文字ずつに戻す。
	if ( ! preg_match( '/^-----BEGIN ([A-Z ]+)-----n?(.+?)n?-----END \1-----n?$/s', trim( $pem ), $m ) ) {
		return $pem;
	}
	$body = $m[2];
	$out  = array();
	$i    = 0;
	$len  = strlen( $body );
	while ( $i < $len ) {
		$out[] = substr( $body, $i, 64 );
		$i    += 64;
		if ( $i < $len && 'n' === $body[ $i ] ) {
			$i++;
		}
	}
	return '-----BEGIN ' . $m[1] . "-----\n" . implode( "\n", $out ) . "\n-----END " . $m[1] . "-----\n";
}

/** 見るカレンダー（1行に1つ）。最初のものに予定を書き込む。 */
function apprex_gcal_ids() {
	return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) get_option( 'apprex_gcal_ids', '' ) ) ) ) );
}

function apprex_gcal_ready() {
	return apprex_gcal_sa() && apprex_gcal_ids() && function_exists( 'openssl_sign' );
}

function apprex_gcal_err( $msg ) {
	update_option( 'apprex_gcal_last_err', array( time(), mb_substr( (string) $msg, 0, 300 ) ), false );
}

function apprex_gcal_b64u( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/** アクセストークン。 */
function apprex_gcal_token() {
	$t = get_transient( 'apprex_gcal_token' );
	if ( $t ) {
		return $t;
	}
	$sa = apprex_gcal_sa();
	if ( ! $sa ) {
		return new WP_Error( 'gcal', 'サービスアカウントの鍵（JSON）が入っていません。' );
	}
	$now = time();
	$jwt = apprex_gcal_b64u( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' . apprex_gcal_b64u(
		wp_json_encode(
			array(
				'iss'   => $sa['client_email'],
				'scope' => 'https://www.googleapis.com/auth/calendar',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'iat'   => $now,
				'exp'   => $now + 3600,
			)
		)
	);
	$key = openssl_pkey_get_private( $sa['private_key'] );
	if ( ! $key || ! openssl_sign( $jwt, $sig, $key, OPENSSL_ALGO_SHA256 ) ) {
		return new WP_Error( 'gcal', '鍵（JSON）の private_key が読めません。ダウンロードしたJSONファイルの中身をそのまま貼ってください。' );
	}
	$r = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 15,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt . '.' . apprex_gcal_b64u( $sig ),
			),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$b = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( empty( $b['access_token'] ) ) {
		return new WP_Error( 'gcal', 'Google にログインできませんでした：' . ( $b['error_description'] ?? $b['error'] ?? ( 'HTTP ' . wp_remote_retrieve_response_code( $r ) ) ) );
	}
	set_transient( 'apprex_gcal_token', $b['access_token'], max( 300, (int) ( $b['expires_in'] ?? 3600 ) - 300 ) );
	return $b['access_token'];
}

/** Calendar API を呼ぶ。 */
function apprex_gcal_api( $method, $path, $body = null ) {
	$tok = apprex_gcal_token();
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$r = wp_remote_request( 'https://www.googleapis.com/calendar/v3' . $path, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$b    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'gcal', 'Google カレンダー ' . $code . '：' . ( $b['error']['message'] ?? '' ) );
	}
	return is_array( $b ) ? $b : array();
}

/**
 * 予定が入っている時間（[開始, 終了] の配列）。5分だけ覚えておく。
 *
 * @param bool $fresh 覚えたものを使わない（予約を確定する直前）。
 */
function apprex_gcal_busy( $from, $to, $fresh = false ) {
	if ( ! apprex_gcal_ready() ) {
		return array();
	}
	$key = 'apprex_gcal_busy_' . md5( $from . '-' . $to . implode( ',', apprex_gcal_ids() ) );
	if ( ! $fresh ) {
		$c = get_transient( $key );
		if ( is_array( $c ) ) {
			return $c;
		}
	}
	$r = apprex_gcal_api(
		'POST',
		'/freeBusy',
		array(
			'timeMin'  => gmdate( 'c', $from ),
			'timeMax'  => gmdate( 'c', $to ),
			'timeZone' => wp_timezone_string(),
			'items'    => array_map(
				function ( $id ) {
					return array( 'id' => $id );
				},
				apprex_gcal_ids()
			),
		)
	);
	if ( is_wp_error( $r ) ) {
		apprex_gcal_err( '空きの確認：' . $r->get_error_message() );
		return array();
	}
	$out = array();
	foreach ( (array) ( $r['calendars'] ?? array() ) as $cid => $cal ) {
		if ( ! empty( $cal['errors'] ) ) {
			apprex_gcal_err( 'カレンダー「' . $cid . '」を見られません（' . ( $cal['errors'][0]['reason'] ?? '' ) . '）。サービスアカウントに共有されているか確認してください。' );
		}
		foreach ( (array) ( $cal['busy'] ?? array() ) as $b ) {
			$out[] = array( strtotime( $b['start'] ), strtotime( $b['end'] ) );
		}
	}
	set_transient( $key, $out, 5 * MINUTE_IN_SECONDS );
	update_option( 'apprex_gcal_last_ok', time(), false );
	return $out;
}

/** その時間に予定が入っているか。 */
function apprex_gcal_is_busy( $at, $min, $busy ) {
	$end = $at + $min * 60;
	foreach ( $busy as $b ) {
		if ( $b[0] < $end && $b[1] > $at ) {
			return true;
		}
	}
	return false;
}

/* 予約の画面の空き：Google カレンダーの予定を「予約済み」にする（inc/meeting-booking.php）。 */
add_filter(
	'apprex_mb_slots',
	function ( $days, $fresh = false ) {
		if ( ! apprex_gcal_ready() || ! $days ) {
			return $days;
		}
		$min  = (int) apprex_mb_settings()['min'];
		$from = (int) $days[0]['times'][0]['at'];
		$last = end( $days );
		$to   = (int) end( $last['times'] )['at'] + $min * 60;
		$busy = apprex_gcal_busy( $from, $to, $fresh );
		foreach ( $days as &$d ) {
			foreach ( $d['times'] as &$t ) {
				if ( $t['ok'] && apprex_gcal_is_busy( (int) $t['at'], $min, $busy ) ) {
					$t['ok'] = false;
				}
			}
			unset( $t );
		}
		unset( $d );
		return $days;
	},
	10,
	2
);

/* 予約が入ったら、Google カレンダーに予定を入れる。 */
add_action(
	'apprex_inquiry_submitted',
	function ( $post_id, $type, $fields = array() ) {
		if ( 'meeting' !== $type || ! apprex_gcal_ready() || ! get_option( 'apprex_gcal_write', 1 ) ) {
			return;
		}
		$at = (int) get_post_meta( $post_id, 'apprex_meeting_at', true );
		if ( ! $at ) {
			return;
		}
		$s    = apprex_mb_settings();
		$f    = function ( $k ) use ( $fields ) {
			return isset( $fields[ $k ] ) ? trim( (string) $fields[ $k ] ) : '';
		};
		$desc = "APPREX のサイトから予約されました。\n\n"
			. 'お名前：' . $f( 'name' ) . " 様\n"
			. ( $f( 'company' ) ? '会社：' . $f( 'company' ) . "\n" : '' )
			. 'メール：' . $f( 'email' ) . "\n"
			. ( $f( 'phone' ) ? '電話：' . $f( 'phone' ) . "\n" : '' )
			. ( $f( 'message' ) ? "\nご相談の内容：\n" . $f( 'message' ) . "\n" : '' )
			. "\n管理画面：" . admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
		$ev = array(
			'summary'     => '【APPREX】オンライン相談：' . $f( 'name' ) . ' 様' . ( $f( 'company' ) ? '（' . $f( 'company' ) . '）' : '' ),
			'description' => $desc,
			'start'       => array( 'dateTime' => wp_date( 'c', $at ), 'timeZone' => wp_timezone_string() ),
			'end'         => array( 'dateTime' => wp_date( 'c', $at + (int) $s['min'] * 60 ), 'timeZone' => wp_timezone_string() ),
			'reminders'   => array( 'useDefault' => true ),
		);
		if ( ! empty( $s['url'] ) ) {
			$ev['location'] = $s['url'];
		}
		$cal = apprex_gcal_ids()[0];
		$r   = apprex_gcal_api( 'POST', '/calendars/' . rawurlencode( $cal ) . '/events', $ev );
		if ( is_wp_error( $r ) ) {
			apprex_gcal_err( '予定の書き込み：' . $r->get_error_message() );
			return;
		}
		update_post_meta( $post_id, 'apprex_gcal_event', $cal . '|' . ( $r['id'] ?? '' ) );
	},
	40,
	3
);

/* キャンセルしたら、Google カレンダーの予定を消す。 */
$apprex_gcal_on_cancel = function ( $mid, $post_id, $key, $value ) {
		if ( 'apprex_mb_cancel' !== $key || ! $value ) {
			return;
		}
		$ev = (string) get_post_meta( $post_id, 'apprex_gcal_event', true );
		if ( '' === $ev || ! apprex_gcal_ready() ) {
			return;
		}
		list( $cal, $id ) = array_pad( explode( '|', $ev, 2 ), 2, '' );
		if ( '' !== $id ) {
			$r = apprex_gcal_api( 'DELETE', '/calendars/' . rawurlencode( $cal ) . '/events/' . rawurlencode( $id ) );
			if ( is_wp_error( $r ) ) {
				apprex_gcal_err( '予定の削除：' . $r->get_error_message() );
			} else {
				delete_post_meta( $post_id, 'apprex_gcal_event' );
			}
		}
};
add_action( 'added_post_meta', $apprex_gcal_on_cancel, 10, 4 );
add_action( 'updated_post_meta', $apprex_gcal_on_cancel, 10, 4 );

/* =========================================================================
 * 設定（お問い合わせ → ミーティング予約 のいちばん下）
 * ====================================================================== */
add_action(
	'admin_init',
	function () {
		register_setting(
			'apprex_gcal',
			'apprex_gcal_sa',
			array(
				'sanitize_callback' => function ( $v ) {
					$v = trim( (string) $v );
					if ( '' === $v ) {
						return (string) get_option( 'apprex_gcal_sa', '' ); // 空のまま保存したら前のまま。
					}
					// options.php がもう \ を外しているので、ここでは外さない（外すと鍵の改行「\n」が壊れる）。
					$j = json_decode( $v, true );
					if ( ! is_array( $j ) ) {
						$j = json_decode( wp_unslash( $v ), true );
					}
					if ( ! is_array( $j ) || empty( $j['client_email'] ) || empty( $j['private_key'] ) ) {
						add_settings_error( 'apprex_gcal_sa', 'bad', '鍵（JSON）の形が正しくありません。ダウンロードしたJSONファイルの中身をそのまま貼ってください。' );
						return (string) get_option( 'apprex_gcal_sa', '' );
					}
					delete_transient( 'apprex_gcal_token' );
					$pk = apprex_gcal_fix_pem( $j['private_key'] );
					if ( function_exists( 'openssl_pkey_get_private' ) && ! openssl_pkey_get_private( $pk ) ) {
						add_settings_error( 'apprex_gcal_sa', 'badkey', '鍵（JSON）の private_key が読めませんでした。Google Cloud で新しい鍵を作って、JSONファイルの中身をそのまま貼ってください。' );
						return (string) get_option( 'apprex_gcal_sa', '' );
					}
					return wp_json_encode( array( 'client_email' => $j['client_email'], 'private_key' => $pk ) );
				},
			)
		);
		register_setting( 'apprex_gcal', 'apprex_gcal_ids', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'apprex_gcal', 'apprex_gcal_write', array( 'sanitize_callback' => 'absint' ) );
	}
);

add_action(
	'admin_post_apprex_gcal_test',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_gcal_test' );
		delete_transient( 'apprex_gcal_token' );
		$from = time();
		$busy = apprex_gcal_busy( $from, $from + 7 * DAY_IN_SECONDS, true );
		$err  = get_option( 'apprex_gcal_last_err' );
		$ok   = (int) get_option( 'apprex_gcal_last_ok', 0 );
		$msg  = ( $ok >= $from - 5 && ( ! is_array( $err ) || (int) $err[0] < $from - 5 ) )
			? 'つながりました。これから7日間に、予定が入っている時間が ' . count( $busy ) . ' 件あります（その時間は予約できなくなります）。'
			: '失敗：' . ( is_array( $err ) ? $err[1] : '設定を確かめてください。' );
		set_transient( 'apprex_gcal_test_res', $msg, 300 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=apprex_inquiry&page=apprex-meeting#gcal' ) );
		exit;
	}
);

/** 設定の欄。 */
function apprex_gcal_settings_box() {
	$sa  = apprex_gcal_sa();
	$res = get_transient( 'apprex_gcal_test_res' );
	delete_transient( 'apprex_gcal_test_res' );
	$err = get_option( 'apprex_gcal_last_err' );
	?>
	<h2 id="gcal" style="margin-top:28px">Google カレンダーと連携</h2>
	<p>Google カレンダーに予定が入っている時間は、予約の画面で「予約済み」になります（予約が重なりません）。予約が入ると、Google カレンダーにも予定が入ります。</p>
	<p>状態：<b><?php echo apprex_gcal_ready() ? '✅ つながる設定です' : '⛔ 未設定'; ?></b>
	<?php if ( is_array( $err ) && time() - (int) $err[0] < 3 * DAY_IN_SECONDS ) : ?><br><span style="color:#b32d2e">最近のエラー（<?php echo esc_html( wp_date( 'n/j G:i', (int) $err[0] ) ); ?>）：<?php echo esc_html( $err[1] ); ?></span><?php endif; ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'apprex_gcal' ); ?>
		<table class="form-table" role="presentation"><tbody>
			<tr><th><label for="apprex_gcal_sa">サービスアカウントの鍵（JSON）</label></th><td>
				<textarea id="apprex_gcal_sa" name="apprex_gcal_sa" rows="4" class="large-text code" placeholder="<?php echo $sa ? '入っています（変えるときだけ貼る）' : '{ &quot;type&quot;: &quot;service_account&quot;, ... }'; ?>"></textarea>
				<?php if ( $sa ) : ?><p>サービスアカウントのメールアドレス：<code><?php echo esc_html( $sa['client_email'] ); ?></code><br><span class="description">使うカレンダーを、このメールアドレスに共有してください（権限：「予定の変更」）。</span></p><?php endif; ?>
			</td></tr>
			<tr><th><label for="apprex_gcal_ids">見るカレンダー</label></th><td>
				<textarea id="apprex_gcal_ids" name="apprex_gcal_ids" rows="2" class="regular-text" placeholder="info@aisjaltd.com"><?php echo esc_textarea( (string) get_option( 'apprex_gcal_ids', '' ) ); ?></textarea>
				<p class="description">カレンダーID（ふつうは Google アカウントのメールアドレス）を1行に1つ。担当者が複数なら全員分を入れると、全員の予定を見ます。予定は1つ目のカレンダーに入れます。</p>
			</td></tr>
			<tr><th>予定を書き込む</th><td><label><input type="checkbox" name="apprex_gcal_write" value="1" <?php checked( (int) get_option( 'apprex_gcal_write', 1 ) ); ?>> 予約が入ったら Google カレンダーに予定を入れる（キャンセルしたら消す）</label></td></tr>
		</tbody></table>
		<?php submit_button( 'カレンダーの設定を保存' ); ?>
	</form>
	<?php if ( $res ) : ?><div class="notice notice-info inline"><p><?php echo esc_html( $res ); ?></p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="apprex_gcal_test"><?php wp_nonce_field( 'apprex_gcal_test' ); ?>
		<?php submit_button( 'つながるか確かめる', 'secondary', 'submit', false, apprex_gcal_ready() ? array() : array( 'disabled' => 'disabled' ) ); ?>
	</form>
	<?php
}
