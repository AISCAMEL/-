<?php
/**
 * LIFF（LINE Front-end Framework）連携：LINE内で会員マイページを開く。
 *
 * - エンドポイント /line-mypage … LIFF SDKを読み込み、LINEログイン→IDトークン取得。
 * - REST /apprex/v1/liff-login … IDトークンをLINEで検証し、メール一致のWP会員へ自動ログイン。
 *   初回ログイン時に LINE userId を会員へ保存し、次回以降はメール不要で照合。
 *
 * 必要：LINEログインチャネル＋LIFFアプリ登録（LIFF ID・エンドポイントURL）。
 * ProLine等のMessaging API Webhookとは独立して動作（共存可）。
 *
 * 設定：設定 → APPREX LIFF
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_liff_id() {
	return (string) get_option( 'apprex_liff_id', '' );
}
function apprex_liff_login_channel_id() {
	return (string) get_option( 'apprex_liff_login_channel_id', '' );
}
function apprex_liff_ready() {
	return '' !== apprex_liff_id() && '' !== apprex_liff_login_channel_id();
}
function apprex_liff_endpoint_url() {
	return home_url( '/line-mypage' );
}

/* =========================================================================
 * エンドポイント /line-mypage（LIFF SDKページ）
 * ====================================================================== */
add_action( 'init', function () {
	add_rewrite_rule( '^line-mypage/?$', 'index.php?apprex_liff=1', 'top' );
	if ( '1' !== get_option( 'apprex_liff_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_liff_rw', '1' );
	}
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'apprex_liff';
	return $v;
} );
add_filter( 'redirect_canonical', function ( $r ) {
	return get_query_var( 'apprex_liff' ) ? false : $r;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_liff' ) ) {
		return;
	}
	// すでにWPログイン済みなら、そのままマイページへ。
	$mypage = function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' );
	if ( is_user_logged_in() ) {
		wp_safe_redirect( $mypage );
		exit;
	}
	if ( ! apprex_liff_ready() ) {
		wp_die( 'LIFFが未設定です。管理画面「APPREX LIFF」で設定してください。' );
	}
	header( 'Content-Type: text/html; charset=UTF-8' );
	$liff_id   = esc_js( apprex_liff_id() );
	$rest      = esc_js( esc_url_raw( rest_url( 'apprex/v1/liff-login' ) ) );
	$nonce     = esc_js( wp_create_nonce( 'wp_rest' ) );
	$mypage_js = esc_js( $mypage );
	?>
<!doctype html><html lang="ja"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>APPREX マイページ</title>
<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<style>body{font-family:-apple-system,BlinkMacSystemFont,"Hiragino Kaku Gothic ProN",Meiryo,sans-serif;background:#f3f4f6;margin:0;display:grid;place-items:center;min-height:100vh;color:#374151}.box{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px 24px;max-width:360px;width:86%;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,.05)}.spin{width:34px;height:34px;border:3px solid #e5e7eb;border-top-color:#2563eb;border-radius:50%;animation:s 1s linear infinite;margin:0 auto 14px}@keyframes s{to{transform:rotate(360deg)}}.btn{display:inline-block;margin-top:14px;background:#2563eb;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold}.err{color:#b91c1c;font-size:14px;margin-top:8px}</style>
</head><body>
<div class="box">
	<div class="spin" id="spin"></div>
	<div id="msg">LINEログインで会員ページを開いています…</div>
	<div id="extra"></div>
</div>
<script>
(function(){
	var REST="<?php echo $rest; ?>",NONCE="<?php echo $nonce; ?>",MYPAGE="<?php echo $mypage_js; ?>";
	function fail(t,linkEmail){document.getElementById('spin').style.display='none';document.getElementById('msg').textContent=t;
		if(linkEmail){document.getElementById('extra').innerHTML='<p class="err">ご契約時のメールアドレスで照合します。</p><input id="em" type="email" placeholder="メールアドレス" style="width:90%;padding:10px;border:1px solid #d1d5db;border-radius:8px;margin-top:6px"><br><a href="#" class="btn" id="lk">この内容でログイン</a>';
			document.getElementById('lk').onclick=function(e){e.preventDefault();login(document.getElementById('em').value);};}}
	function login(email){
		fetch(REST,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':NONCE},
			body:JSON.stringify({id_token:window.__idt||'',email:email||''})})
		.then(function(r){return r.json();}).then(function(d){
			if(d&&d.ok){location.href=MYPAGE;}else{fail((d&&d.message)||'会員が見つかりませんでした。',true);}
		}).catch(function(){fail('通信エラーが発生しました。',true);});
	}
	if(!window.liff){fail('LIFFを読み込めませんでした。');return;}
	liff.init({liffId:"<?php echo $liff_id; ?>"}).then(function(){
		if(!liff.isLoggedIn()){liff.login();return;}
		return liff.getIDToken();
	}).then(function(idt){
		if(!idt)return;window.__idt=idt;login('');
	}).catch(function(){fail('LINEログインに失敗しました。');});
})();
</script>
</body></html>
	<?php
	exit;
} );

/* =========================================================================
 * REST：IDトークン検証 → 会員ログイン
 * ====================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/liff-login',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'apprex_liff_login',
		)
	);
} );

function apprex_liff_login( WP_REST_Request $req ) {
	if ( ! apprex_liff_ready() ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => 'LIFF未設定です。' ), 200 );
	}
	$idt   = (string) $req->get_param( 'id_token' );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	if ( '' === $idt ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => 'IDトークンがありません。' ), 200 );
	}

	// LINEでIDトークンを検証。
	$res = wp_remote_post(
		'https://api.line.me/oauth2/v2.1/verify',
		array(
			'timeout' => 15,
			'body'    => array(
				'id_token'  => $idt,
				'client_id' => apprex_liff_login_channel_id(),
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => 'LINE検証に接続できませんでした。' ), 200 );
	}
	$claims = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $claims['sub'] ) ) {
		$m = isset( $claims['error_description'] ) ? $claims['error_description'] : 'LINE認証に失敗しました。';
		return new WP_REST_Response( array( 'ok' => false, 'message' => $m ), 200 );
	}
	$line_uid   = sanitize_text_field( $claims['sub'] );
	$line_email = isset( $claims['email'] ) ? sanitize_email( $claims['email'] ) : '';

	// 1) 既に LINE userId が紐づく会員。
	$user = null;
	$by_uid = get_users( array( 'meta_key' => 'apprex_line_uid', 'meta_value' => $line_uid, 'number' => 1 ) );
	if ( $by_uid ) {
		$user = $by_uid[0];
	}
	// 2) メール一致（LINEのメール or 入力されたメール）で照合。
	if ( ! $user ) {
		$try = $line_email ? $line_email : $email;
		if ( $try && is_email( $try ) ) {
			$u = get_user_by( 'email', $try );
			if ( $u ) {
				$user = $u;
			}
		}
	}
	if ( ! $user ) {
		return new WP_REST_Response(
			array(
				'ok'      => false,
				'message' => 'このLINEに紐づく会員が見つかりませんでした。ご契約時のメールアドレスをご入力ください。',
			),
			200
		);
	}

	// 紐付けを保存（次回からメール不要）。
	update_user_meta( $user->ID, 'apprex_line_uid', $line_uid );

	// ログイン。
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* =========================================================================
 * 設定ページ
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX LIFF', 'APPREX LIFF', 'manage_options', 'apprex-liff', 'apprex_liff_settings_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_liff', 'apprex_liff_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_liff', 'apprex_liff_login_channel_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
} );

function apprex_liff_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>APPREX LIFF（LINE内マイページ）</h1>
		<div class="notice notice-info"><p>
			<strong>準備</strong>：① LINE Developers で<strong>LINEログインチャネル</strong>を作成 →
			② <strong>LIFFアプリ</strong>を追加（サイズ=Full）し、<strong>エンドポイントURL</strong>に下記を設定 →
			③ scope に <code>profile openid email</code> を付与 → ④ 発行された<strong>LIFF ID</strong>とログインチャネルの<strong>チャネルID</strong>を下に入力。<br>
			エンドポイントURL：<code><?php echo esc_html( apprex_liff_endpoint_url() ); ?></code><br>
			状態：<strong style="color:<?php echo apprex_liff_ready() ? '#15803d' : '#b91c1c'; ?>;"><?php echo apprex_liff_ready() ? '設定済み' : '未設定'; ?></strong>
		</p></div>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_liff' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="apprex_liff_id">LIFF ID</label></th>
					<td><input type="text" id="apprex_liff_id" name="apprex_liff_id" class="regular-text" value="<?php echo esc_attr( apprex_liff_id() ); ?>" placeholder="1234567890-abcdEFGH"></td></tr>
				<tr><th><label for="apprex_liff_login_channel_id">LINEログイン チャネルID</label></th>
					<td><input type="text" id="apprex_liff_login_channel_id" name="apprex_liff_login_channel_id" class="regular-text" value="<?php echo esc_attr( apprex_liff_login_channel_id() ); ?>" placeholder="IDトークン検証用（数字）">
					<p class="description">LINEログインチャネルの「チャネルID」。IDトークンの検証に使います。</p></td></tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
		<hr>
		<p class="description" style="max-width:820px;">
			リッチメニューやステップ配信のリンク先に <code><?php echo esc_html( apprex_liff_endpoint_url() ); ?></code> を設定すると、
			LINE内でこの会員ページが開き、LINEログインだけで（パスワード不要で）マイページにアクセスできます。
			初回はご契約メールでの照合、以降は自動です。
		</p>
	</div>
	<?php
}
