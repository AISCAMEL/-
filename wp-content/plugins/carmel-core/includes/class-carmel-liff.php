<?php
/**
 * LINE LIFF 連携ヘルパー。
 *
 * リッチメニュー →（LIFF URL）→ 審査/問い合わせフォームを置いた WP ページ、という
 * 動線で使う。ショートコード [carmel_liff] をそのページに置くと、LIFF SDK を読み込み、
 * ログイン中の LINE ユーザーの userId を取得して、ページ内フォームの hidden 入力
 * （既定 name="line_user_id"）に自動で差し込む。氏名が空なら LINE 表示名で補完する。
 *
 * フォーム送信時にこの line_user_id が Carmel_Application_Intake::process() へ渡れば、
 * 顧客の user_meta `line_user_id` に保存され、以後の通知がその人の LINE へ届く。
 *
 * 設定：LIFF ID は属性 id="..." または定数 CARMEL_LIFF_ID / オプション carmel_liff_id。
 * 使い方例：[carmel_liff]  /  [carmel_liff id="1657xxxxxx-XXXXXXXX" field="line_user_id"]
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_LIFF {

	/** @var Carmel_LIFF|null */
	private static $instance = null;

	const SHORTCODE       = 'carmel_liff';
	const LOGIN_SHORTCODE = 'carmel_liff_login';
	const REST_NAMESPACE  = 'carmel/v1';
	const LOGIN_ROUTE     = '/liff-login';
	const VERIFY_ENDPOINT = 'https://api.line.me/oauth2/v2.1/verify';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_shortcode( self::LOGIN_SHORTCODE, array( $this, 'render_login' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	/** LIFF が紐づく LINEログインチャネルID（IDトークン検証の aud）。 */
	private function channel_id() {
		return defined( 'CARMEL_LIFF_CHANNEL_ID' ) ? CARMEL_LIFF_CHANNEL_ID : get_option( 'carmel_liff_channel_id', '' );
	}

	private function mypage_url() {
		$u = get_option( 'carmel_mypage_url', '' );
		return $u ? $u : home_url( '/' . ltrim( apply_filters( 'carmel_mypage_slug', 'mypage' ), '/' ) );
	}

	private function apply_url() {
		return home_url( '/' . ltrim( apply_filters( 'carmel_apply_page_slug', 'apply' ), '/' ) );
	}

	/* --------------------------------------------------------------------- *
	 * LIFF ワンタップ会員ログイン（IDトークン検証 → 自動ログイン）
	 * --------------------------------------------------------------------- */

	public function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::LOGIN_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_login' ),
				'permission_callback' => '__return_true', // 認証情報は IDトークン本体（サーバ検証）。
			)
		);
	}

	/**
	 * IDトークンを LINE で検証し、line_user_id 一致の会員を自動ログイン。
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_login( $request ) {
		$id_token = (string) $request->get_param( 'id_token' );
		$apply    = $this->apply_url();

		if ( '' === $id_token || '' === (string) $this->channel_id() ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'unconfigured', 'redirect' => $apply ), 200 );
		}

		$payload = $this->verify_id_token( $id_token );
		if ( is_wp_error( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'invalid_token', 'redirect' => $apply ), 200 );
		}

		$sub   = isset( $payload['sub'] ) ? (string) $payload['sub'] : '';
		$email = isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '';
		if ( '' === $sub ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'no_sub', 'redirect' => $apply ), 200 );
		}

		// 1) line_user_id 一致の会員を探す。
		$user = $this->find_user_by_line_id( $sub );

		// 2) 無ければ、検証済みメール一致の会員に line_user_id を紐付け（顧客のみ）。
		if ( ! $user && '' !== $email ) {
			$by_email = get_user_by( 'email', $email );
			if ( $by_email && $this->login_allowed( $by_email ) ) {
				update_user_meta( $by_email->ID, 'line_user_id', $sub );
				$user = $by_email;
			}
		}

		if ( ! $user ) {
			// 会員が見つからない＝未申込。審査/申込へ誘導。
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'no_member', 'redirect' => $apply ), 200 );
		}
		if ( ! $this->login_allowed( $user ) ) {
			// 顧客以外（本部/加盟店/管理者）は LINE 自動ログイン対象外。
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'not_allowed', 'redirect' => home_url( '/login' ) ), 200 );
		}

		// 自動ログイン（永続Cookie）。
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		do_action( 'carmel_liff_logged_in', $user->ID, $sub );

		return new WP_REST_Response( array( 'ok' => true, 'redirect' => $this->mypage_url() ), 200 );
	}

	/**
	 * LINE で IDトークンを検証（署名・iss・aud・exp は verify 側で検証）。
	 *
	 * @param string $id_token
	 * @return array|WP_Error  ペイロード（sub/name/email…）または WP_Error
	 */
	private function verify_id_token( $id_token ) {
		$response = wp_remote_post(
			self::VERIFY_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'id_token'  => $id_token,
					'client_id' => $this->channel_id(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) || ! empty( $body['error'] ) || empty( $body['sub'] ) ) {
			return new WP_Error( 'liff_verify_failed', 'IDトークン検証に失敗しました。' );
		}
		// aud（client_id）一致を二重確認。
		if ( isset( $body['aud'] ) && (string) $body['aud'] !== (string) $this->channel_id() ) {
			return new WP_Error( 'liff_aud_mismatch', 'aud 不一致' );
		}
		return $body;
	}

	private function find_user_by_line_id( $sub ) {
		$users = get_users(
			array(
				'meta_key'   => 'line_user_id',
				'meta_value' => $sub,
				'number'     => 1,
				'fields'     => 'all',
			)
		);
		return ! empty( $users ) ? $users[0] : null;
	}

	/**
	 * LINE自動ログインを許可する会員か（顧客のみ・特権ロールは除外）。
	 *
	 * @param WP_User $user
	 * @return bool
	 */
	private function login_allowed( $user ) {
		$roles   = (array) $user->roles;
		$blocked = array( 'administrator', 'hq_admin', 'store_owner', 'store_staff' );
		$ok      = in_array( 'customer', $roles, true ) && ! array_intersect( $blocked, $roles );
		return (bool) apply_filters( 'carmel_liff_login_allowed', $ok, $user );
	}

	private function liff_id( $attr_id = '' ) {
		if ( '' !== (string) $attr_id ) {
			return (string) $attr_id;
		}
		if ( defined( 'CARMEL_LIFF_ID' ) && CARMEL_LIFF_ID ) {
			return (string) CARMEL_LIFF_ID;
		}
		return (string) get_option( 'carmel_liff_id', '' );
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'        => '',
				'field'     => 'line_user_id',
				'fill_name' => 'yes', // 氏名が空なら LINE 表示名で補完
			),
			$atts,
			self::SHORTCODE
		);

		$liff_id = $this->liff_id( $atts['id'] );
		if ( '' === $liff_id ) {
			// 未設定時は管理者にだけ注意を表示（一般来訪者には何も出さない）。
			return current_user_can( 'manage_options' )
				? '<p style="color:#a5281b">[carmel_liff] LIFF ID が未設定です（属性 id か CARMEL_LIFF_ID / carmel_liff_id を設定）。</p>'
				: '';
		}

		$field      = preg_replace( '/[^A-Za-z0-9_\-\[\]]/', '', $atts['field'] );
		$fill_name  = in_array( strtolower( $atts['fill_name'] ), array( 'yes', '1', 'true' ), true ) ? 1 : 0;
		$name_sel   = esc_js( apply_filters( 'carmel_liff_name_selector', 'input[name="your-name"],input[name="name"],input[name="氏名"]' ) );

		ob_start();
		?>
<script>
(function(){
	var LIFF_ID=<?php echo wp_json_encode( $liff_id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	var FIELD=<?php echo wp_json_encode( $field ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	var FILL_NAME=<?php echo (int) $fill_name; ?>;
	function apply(p){
		document.querySelectorAll('form').forEach(function(f){
			var inp=f.querySelector('input[name="'+FIELD+'"]');
			if(!inp){inp=document.createElement('input');inp.type='hidden';inp.name=FIELD;f.appendChild(inp);}
			inp.value=p.userId||'';
			if(FILL_NAME&&p.displayName){
				var nm=f.querySelector('<?php echo $name_sel; // phpcs:ignore WordPress.Security.EscapeOutput ?>');
				if(nm&&!nm.value)nm.value=p.displayName;
			}
		});
		document.dispatchEvent(new CustomEvent('carmel-liff-ready',{detail:p}));
	}
	function boot(){
		if(typeof liff==='undefined')return;
		liff.init({liffId:LIFF_ID}).then(function(){
			if(!liff.isLoggedIn()){liff.login();return;}
			liff.getProfile().then(apply).catch(function(e){window.console&&console.warn('LIFF getProfile',e);});
		}).catch(function(e){window.console&&console.warn('LIFF init',e);});
	}
	var s=document.createElement('script');
	s.src='https://static.line-scdn.net/liff/edge/2/sdk.js';
	s.charset='utf-8';s.onload=boot;
	document.head.appendChild(s);
})();
</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * 会員ページLIFFページ用。LINEのIDトークンを検証ログインRESTへ送り、
	 * 成功なら /mypage、未会員なら申込/審査へ自動遷移する。
	 *
	 * 設置：会員ページLIFFのエンドポイントURLにこのショートコードを置き、
	 * LINEボット/リッチメニューの「会員ページ」をそのLIFF URL（carmel_member_page_url）に向ける。
	 *
	 * @param array $atts
	 * @return string
	 */
	public function render_login( $atts ) {
		$atts    = shortcode_atts( array( 'id' => '' ), $atts, self::LOGIN_SHORTCODE );
		$liff_id = $this->liff_id( $atts['id'] );
		$rest    = esc_url_raw( rest_url( self::REST_NAMESPACE . self::LOGIN_ROUTE ) );
		$apply   = $this->apply_url();

		if ( '' === $liff_id || '' === (string) $this->channel_id() ) {
			return current_user_can( 'manage_options' )
				? '<p style="color:#a5281b">[carmel_liff_login] LIFF ID / チャネルID が未設定です（carmel_liff_id・carmel_liff_channel_id）。</p>'
				: '<p>会員ページの準備中です。</p>';
		}

		ob_start();
		?>
<div class="carmel-liff-login" style="text-align:center;padding:2em 1em;font-size:15px;color:#555">
	会員ページへ移動しています…
	<noscript>JavaScriptを有効にしてください。</noscript>
</div>
<script>
(function(){
	var LIFF_ID=<?php echo wp_json_encode( $liff_id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	var REST=<?php echo wp_json_encode( $rest ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	var APPLY=<?php echo wp_json_encode( $apply ); // phpcs:ignore WordPress.Security.EscapeOutput ?>;
	function go(u){location.href=u||APPLY;}
	function boot(){
		if(typeof liff==='undefined'){go(APPLY);return;}
		liff.init({liffId:LIFF_ID}).then(function(){
			if(!liff.isLoggedIn()){liff.login();return;}
			var idt=null;
			try{idt=liff.getIDToken();}catch(e){}
			if(!idt){go(APPLY);return;}
			fetch(REST,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',
				body:JSON.stringify({id_token:idt})})
				.then(function(r){return r.json();})
				.then(function(d){ go(d&&d.redirect?d.redirect:APPLY); })
				.catch(function(){go(APPLY);});
		}).catch(function(){go(APPLY);});
	}
	var s=document.createElement('script');
	s.src='https://static.line-scdn.net/liff/edge/2/sdk.js';
	s.charset='utf-8';s.onload=boot;
	document.head.appendChild(s);
})();
</script>
		<?php
		return ob_get_clean();
	}
}
