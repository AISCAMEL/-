<?php
/**
 * テーマ内蔵 SMTP 送信設定。
 *
 * Gmail / Google Workspace 等の SMTP を使って WordPress のメール送信を本番化する。
 * プラグイン不要。設定が揃ったときだけ phpmailer_init で SMTP に切り替える安全設計。
 *
 * 推奨（Google Workspace・月〜1,000通）:
 *   ホスト smtp.gmail.com / ポート 587 / 暗号化 TLS
 *   ユーザー名＝送信元アドレス（例 info@aisjaltd.com）
 *   パスワード＝Googleの「アプリパスワード」（2段階認証ONで発行）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_smtp_enabled() {
	return (bool) get_option( 'apprex_smtp_enabled', 0 );
}
function apprex_smtp_host() {
	return (string) get_option( 'apprex_smtp_host', 'smtp.gmail.com' );
}
function apprex_smtp_port() {
	return (int) get_option( 'apprex_smtp_port', 587 );
}
function apprex_smtp_secure() {
	$v = (string) get_option( 'apprex_smtp_secure', 'tls' );
	return in_array( $v, array( 'tls', 'ssl', 'none' ), true ) ? $v : 'tls';
}
function apprex_smtp_user() {
	return (string) get_option( 'apprex_smtp_user', '' );
}
function apprex_smtp_pass() {
	return (string) get_option( 'apprex_smtp_pass', '' );
}
function apprex_smtp_from() {
	$f = (string) get_option( 'apprex_smtp_from', '' );
	return $f ? $f : apprex_smtp_user();
}
function apprex_smtp_from_name() {
	$n = (string) get_option( 'apprex_smtp_from_name', '' );
	return $n ? $n : 'APPREX';
}
/** 送信に必要な情報が揃っているか。 */
function apprex_smtp_ready() {
	return apprex_smtp_enabled() && apprex_smtp_host() && apprex_smtp_user() && apprex_smtp_pass();
}

/* -------------------------------------------------------------------------
 * 実際の送信を SMTP に切替
 * ---------------------------------------------------------------------- */

add_action( 'phpmailer_init', function ( $phpmailer ) {
	if ( ! apprex_smtp_ready() ) {
		return;
	}
	$phpmailer->isSMTP();
	$phpmailer->Host       = apprex_smtp_host();
	$phpmailer->Port       = apprex_smtp_port();
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = apprex_smtp_user();
	$phpmailer->Password   = apprex_smtp_pass();
	$secure                = apprex_smtp_secure();
	$phpmailer->SMTPSecure = ( 'none' === $secure ) ? '' : $secure;

	// サーバーの返事を記録する（失敗の理由を画面に出すため。パスワードは記録しない）。
	$GLOBALS['apprex_smtp_dbg'] = array();
	$phpmailer->SMTPDebug       = 2;
	$phpmailer->Debugoutput     = function ( $str ) {
		if ( 0 === strpos( ltrim( $str ), 'SERVER -> CLIENT:' ) ) {
			$GLOBALS['apprex_smtp_dbg'][] = trim( substr( ltrim( $str ), 17 ) );
		}
	};

	// 差出人を認証アカウントに揃える（Gmailは不一致だと書き換え/拒否するため）。
	$from = apprex_smtp_from();
	if ( is_email( $from ) ) {
		try {
			$phpmailer->setFrom( $from, apprex_smtp_from_name(), false );
		} catch ( Exception $e ) {
			$phpmailer->From     = $from;
			$phpmailer->FromName = apprex_smtp_from_name();
		}
	}
} );

// WordPress 全体の差出人も統一（パスワード再設定メール等にも適用）。
add_filter( 'wp_mail_from', function ( $email ) {
	return apprex_smtp_ready() && is_email( apprex_smtp_from() ) ? apprex_smtp_from() : $email;
} );
add_filter( 'wp_mail_from_name', function ( $name ) {
	return apprex_smtp_ready() ? apprex_smtp_from_name() : $name;
} );

/* -------------------------------------------------------------------------
 * 設定ページ
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'options-general.php',
		'APPREX メール送信(SMTP)',
		'APPREX メール送信(SMTP)',
		'manage_options',
		'apprex-smtp',
		'apprex_smtp_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_smtp', 'apprex_smtp_enabled', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_host', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_port', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_secure', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_user', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_pass', array( 'sanitize_callback' => 'apprex_smtp_sanitize_pass' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_from', array( 'sanitize_callback' => 'sanitize_email' ) );
	register_setting( 'apprex_smtp', 'apprex_smtp_from_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
} );

/** アプリパスワードの空白（Googleが4桁区切りで表示）を除去して保持。 */
function apprex_smtp_sanitize_pass( $raw ) {
	$raw = (string) wp_unslash( $raw );
	// マスク表示（●）のまま保存された場合は既存値を維持。
	if ( '' !== $raw && false === strpos( $raw, '●' ) ) {
		return preg_replace( '/\s+/', '', $raw );
	}
	return get_option( 'apprex_smtp_pass', '' );
}

/** Google（サーバー）の返事から、失敗の理由を分かる言葉にする。 */
function apprex_smtp_explain( $reply ) {
	$r = (string) $reply;
	$map = array(
		'/5\.4\.5|sending limit|limit exceeded/i'           => '1日に送れる数の上限に達しています（Google Workspace は1日約2,000通、使いはじめや試用中はもっと少なくなります）。24時間たつと送れるようになります。',
		'/5\.7\.0.*(security|virus|blocked)|content presents/i' => 'メールの中身が危ないと判断されて止められました（リンクや添付の内容）。',
		'/unusual (rate|activity)|spam|5\.7\.1.*(spam|unsolicited)/i' => '迷惑メールのような送り方だと判断されて止められました。少し時間をおいて、送る数を減らしてください。',
		'/5\.7\.(26|27|25)|unauthenticated|SPF|DKIM|DMARC/i' => '送信ドメインの認証（SPF・DKIM）で止められました。DNSの設定が必要です。',
		'/5\.7\.1|not (allowed|authorized)|relay/i'           => 'このアカウントから、この差出人では送れません。差出人メールをユーザー名と同じにしてください。',
		'/5\.7\.8|535|BadCredentials|Username and Password/i' => 'ユーザー名かアプリパスワードがちがいます。アプリパスワードを作り直して入れてください。',
		'/5\.7\.9|Application-specific password required/i' => 'アプリパスワードが必要です（ふつうのパスワードでは送れません）。',
		'/5\.5\.1|Authentication Required/i'                  => 'ログインできていません。ユーザー名とアプリパスワードを確かめてください。',
		'/^4\d\d|4\.\d\.\d|try again later|temporar/i'      => 'Google 側が一時的に受け付けていません。少し待ってからもう一度送ってください。',
		'/5\.2\.2|mailbox full|over quota/i'                  => '送り先のメールボックスがいっぱいです。',
		'/5\.1\.1|does not exist|no such user/i'              => '送り先のアドレスがありません。',
		'/suspended|disabled/i'                                => 'Google のアカウントの送信が止められています。Google Workspace の管理コンソールで確かめてください。',
	);
	foreach ( $map as $re => $t ) {
		if ( preg_match( $re, $r ) ) {
			return $t;
		}
	}
	return '';
}

/** いちばん最後の、エラーのサーバーの返事。 */
function apprex_smtp_last_reply() {
	$err = '';
	foreach ( (array) ( $GLOBALS['apprex_smtp_dbg'] ?? array() ) as $l ) {
		if ( preg_match( '/^[45]\d\d[ -]/', $l ) ) {
			$err .= ( '' !== $err ? ' ' : '' ) . preg_replace( '/^([45]\d\d)[ -]/', '$1 ', $l );
		}
	}
	return mb_substr( preg_replace( '/\s+/', ' ', $err ), 0, 400 );
}

// 本番の送信でも、失敗したら理由を残す（設定画面と業務ナビに出す）。
add_action(
	'wp_mail_failed',
	function ( $e ) {
		if ( ! apprex_smtp_ready() ) {
			return;
		}
		$rep = apprex_smtp_last_reply();
		update_option( 'apprex_smtp_last_err', array( time(), $e->get_error_message(), $rep, apprex_smtp_explain( $rep . ' ' . $e->get_error_message() ) ), false );
	}
);

/** テスト送信。 */
add_action( 'admin_post_apprex_smtp_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_smtp_test' );
	$to   = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
	$back = admin_url( 'options-general.php?page=apprex-smtp' );
	if ( ! is_email( $to ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_smtp_msg', rawurlencode( '送信先メールが不正です。' ), $back ) );
		exit;
	}

	// 送信エラーを捕捉。
	$err = '';
	$grab = function ( $wp_error ) use ( &$err ) {
		$err = $wp_error->get_error_message();
	};
	add_action( 'wp_mail_failed', $grab );

	$subject = '【APPREX】SMTPテスト送信';
	$body    = "これは APPREX のSMTPテスト送信です。\nこのメールが受信トレイ（迷惑メールではなく）に届いていれば設定は成功です。\n\n送信時刻：" . wp_date( 'Y-m-d H:i' );
	$html    = function_exists( 'apprex_render_email' )
		? apprex_render_email( $subject, $body, array( 'heading' => 'SMTPテスト送信' ) )
		: nl2br( esc_html( $body ) );
	$headers = function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' );

	if ( ! empty( $_POST['plain'] ) ) {
		$html    = $body;
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	}
	$ok = wp_mail( $to, $subject, $html, $headers );
	remove_action( 'wp_mail_failed', $grab );

	$rep = apprex_smtp_last_reply();
	$why = apprex_smtp_explain( $rep . ' ' . $err );
	$msg = $ok
		? '✅ 送信しました。受信トレイ（迷惑メールも）をご確認ください。'
		: '❌ 送信に失敗しました。' . ( $err ? '（' . $err . '）' : '' ) . ( '' !== $why ? "\n考えられる原因：" . $why : '' ) . ( '' !== $rep ? "\nGoogleからの返事：" . $rep : '' );
	if ( $ok ) {
		delete_option( 'apprex_smtp_last_err' );
	}
	wp_safe_redirect( add_query_arg( 'apprex_smtp_msg', rawurlencode( $msg ), $back ) );
	exit;
} );

function apprex_smtp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$has_pass = '' !== apprex_smtp_pass();
	$default  = wp_get_current_user()->user_email;
	?>
	<div class="wrap">
		<h1>APPREX メール送信（SMTP）</h1>

		<?php if ( isset( $_GET['apprex_smtp_msg'] ) ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo nl2br( esc_html( rawurldecode( wp_unslash( $_GET['apprex_smtp_msg'] ) ) ) ); ?></p></div>
		<?php endif; ?>
		<?php $apprex_le = get_option( 'apprex_smtp_last_err' ); ?>
		<?php if ( is_array( $apprex_le ) && ! isset( $_GET['apprex_smtp_msg'] ) ) : ?>
			<div class="notice notice-warning"><p><b>最後に送れなかったメール（<?php echo esc_html( wp_date( 'n/j G:i', (int) $apprex_le[0] ) ); ?>）</b>：<?php echo esc_html( $apprex_le[1] ); ?><?php echo '' !== (string) $apprex_le[3] ? '<br>考えられる原因：' . esc_html( $apprex_le[3] ) : ''; ?><?php echo '' !== (string) $apprex_le[2] ? '<br><small>Googleからの返事：' . esc_html( $apprex_le[2] ) . '</small>' : ''; ?></p></div>
		<?php endif; ?>

		<div class="notice notice-<?php echo apprex_smtp_ready() ? 'success' : 'warning'; ?>">
			<p><strong>現在の状態：</strong>
			<?php echo apprex_smtp_ready() ? 'SMTP送信が有効です（このサーバーの標準送信ではなく SMTP 経由で送信します）。' : 'SMTP未設定です。現在はサーバー標準送信のため、迷惑メール行き・不達の恐れがあります。'; ?></p>
		</div>

		<div class="notice notice-info"><p>
			<strong>Google Workspace の設定手順</strong>：① 送信に使うアカウント（例 <code>info@aisjaltd.com</code>）で
			<strong>2段階認証をON</strong> → ② Googleアカウント →「アプリパスワード」を発行 →
			③ 下記に入力（ホスト <code>smtp.gmail.com</code> / ポート <code>587</code> / TLS）。
		</p></div>

		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_smtp' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row">SMTP送信を有効化</th>
					<td><label><input type="checkbox" name="apprex_smtp_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_smtp_enabled', 0 ) ); ?>> 有効にする</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_host">SMTPホスト</label></th>
					<td><input type="text" id="apprex_smtp_host" name="apprex_smtp_host" class="regular-text" value="<?php echo esc_attr( apprex_smtp_host() ); ?>" placeholder="smtp.gmail.com"></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_port">ポート</label></th>
					<td><input type="number" id="apprex_smtp_port" name="apprex_smtp_port" value="<?php echo esc_attr( apprex_smtp_port() ); ?>" style="width:100px"> <span class="description">TLS=587 / SSL=465</span></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_secure">暗号化</label></th>
					<td><select id="apprex_smtp_secure" name="apprex_smtp_secure">
						<option value="tls" <?php selected( apprex_smtp_secure(), 'tls' ); ?>>TLS（推奨・587）</option>
						<option value="ssl" <?php selected( apprex_smtp_secure(), 'ssl' ); ?>>SSL（465）</option>
						<option value="none" <?php selected( apprex_smtp_secure(), 'none' ); ?>>なし</option>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_user">ユーザー名（送信元アドレス）</label></th>
					<td><input type="text" id="apprex_smtp_user" name="apprex_smtp_user" class="regular-text" value="<?php echo esc_attr( apprex_smtp_user() ); ?>" placeholder="info@aisjaltd.com" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_pass">パスワード（アプリパスワード）</label></th>
					<td><input type="password" id="apprex_smtp_pass" name="apprex_smtp_pass" class="regular-text" value="<?php echo $has_pass ? '●●●●●●●●●●●●' : ''; ?>" placeholder="Googleのアプリパスワード" autocomplete="new-password">
					<p class="description"><?php echo $has_pass ? '保存済み（変更する場合のみ入力。空白は自動で除去します）。' : '2段階認証ONで発行した16桁のアプリパスワード（空白は気にせず貼り付けでOK）。'; ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_from">差出人メール（From）</label></th>
					<td><input type="email" id="apprex_smtp_from" name="apprex_smtp_from" class="regular-text" value="<?php echo esc_attr( (string) get_option( 'apprex_smtp_from', '' ) ); ?>" placeholder="（空欄ならユーザー名と同じ）">
					<p class="description">Gmailは「ユーザー名」または「送信専用エイリアス」と一致している必要があります。</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_smtp_from_name">差出人名</label></th>
					<td><input type="text" id="apprex_smtp_from_name" name="apprex_smtp_from_name" class="regular-text" value="<?php echo esc_attr( apprex_smtp_from_name() ); ?>" placeholder="APPREX"></td>
				</tr>
			</tbody></table>
			<?php submit_button( '保存する' ); ?>
		</form>

		<hr>
		<h2>テスト送信</h2>
		<p>保存後にこのボタンで、実際に SMTP 経由で届くか確認できます。</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_smtp_test">
			<?php wp_nonce_field( 'apprex_smtp_test' ); ?>
			<input type="email" name="to" value="<?php echo esc_attr( $default ); ?>" class="regular-text" required>
			<?php submit_button( 'テスト送信する', 'secondary', 'submit', false ); ?>
			<label style="margin-left:8px"><input type="checkbox" name="plain" value="1"> 文字だけで送る（デザインなし。中身が原因かを確かめるとき）</label>
		</form>
	</div>
	<?php
}
