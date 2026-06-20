<?php
/**
 * Unified, branded login screen.
 *
 * Shortcode [carmel_login] for the /login page. Uses WordPress core auth
 * (wp_login_form posts to wp-login.php), so it inherits all of core's security.
 * After login, role-based routing (Carmel_Access_Control::role_login_redirect)
 * sends each user to their portal. A ?redirect_to deep-link is honored.
 *
 * When already logged in it shows the user's portal entrance + logout.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Login {

	/** @var Carmel_Login|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_login';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ) );
	}

	/**
	 * Send failed logins originating from the branded /login page back to it
	 * with an error flag (instead of the default wp-login.php screen).
	 *
	 * @param string $username
	 */
	public function on_login_failed( $username ) {
		$ref = wp_get_referer();
		if ( $ref && false !== strpos( $ref, '/login' ) ) {
			wp_safe_redirect( add_query_arg( 'login', 'failed', $ref ) );
			exit;
		}
	}

	/**
	 * @param array $atts
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'   => 'ログイン',
				'logo'    => '',                         // ロゴ画像URL（空なら assets/logo.png → 文字ロゴ）
				'wordmark'=> 'CarMel',                   // 文字ロゴ
				'tagline' => 'ネットで安心してクルマ頼める！', // キャッチコピー
				'brand'   => '#5b2a86',                  // ブランドカラー（紫）
				'accent'  => '#7c3aed',                  // アクセントカラー（紫）
			),
			$atts,
			self::SHORTCODE
		);

		$brand  = $this->sanitize_color( $atts['brand'], '#1a1a2e' );
		$accent = $this->sanitize_color( $atts['accent'], '#2e86de' );
		$style  = '--carmel-brand:' . $brand . ';--carmel-accent:' . $accent . ';';

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-login-wrap" style="' . esc_attr( $style ) . '">';
		echo '<div class="carmel-login-card">';

		// Logo: explicit attr → bundled assets/logo.png → text wordmark.
		$logo = $atts['logo'];
		if ( '' === $logo && defined( 'CARMEL_CORE_DIR' ) && file_exists( CARMEL_CORE_DIR . 'assets/logo.png' ) ) {
			$logo = CARMEL_CORE_URL . 'assets/logo.png';
		}

		// Brand header.
		echo '<div class="carmel-login-brand">';
		if ( $logo ) {
			echo '<img class="carmel-login-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $atts['wordmark'] ) . '">';
		} else {
			echo '<span class="carmel-login-wordmark">' . esc_html( $atts['wordmark'] ) . '</span>';
		}
		if ( $atts['tagline'] ) {
			echo '<p class="carmel-login-tagline">' . esc_html( $atts['tagline'] ) . '</p>';
		}
		echo '</div>';

		if ( is_user_logged_in() ) {
			echo $this->logged_in_view(); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div></div>';
			return ob_get_clean();
		}

		echo '<h2 class="carmel-login-title">' . esc_html( $atts['title'] ) . '</h2>';

		if ( isset( $_GET['login'] ) && 'failed' === sanitize_key( $_GET['login'] ) ) {
			echo '<div class="carmel-login-err">メールアドレスまたはパスワードが正しくありません。</div>';
		}

		// Honor a safe deep-link; otherwise let role routing decide
		// (passing the login page URL makes the redirect filter fall through).
		$redirect = '';
		if ( ! empty( $_GET['redirect_to'] ) ) {
			$redirect = esc_url_raw( wp_unslash( $_GET['redirect_to'] ) );
		}
		$form = wp_login_form(
			array(
				'echo'           => false,
				'redirect'       => $redirect ? $redirect : home_url( '/login' ),
				'label_username' => 'メールアドレス または ユーザー名',
				'label_password' => 'パスワード',
				'label_remember' => 'ログイン状態を保持',
				'label_log_in'   => 'ログイン',
				'remember'       => true,
			)
		);
		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<div class="carmel-login-links">';
		echo '<a href="' . esc_url( wp_lostpassword_url() ) . '">パスワードをお忘れですか？</a>';
		echo '</div>';

		echo '<p class="carmel-login-note">お申込み済みの方は、受付時にお送りしたメール／LINEのリンクからパスワードを設定してください。</p>';

		echo '</div>'; // card
		echo '<p class="carmel-login-footer">© ' . esc_html( gmdate( 'Y' ) ) . ' CARMEL</p>';
		echo '</div>'; // wrap
		return ob_get_clean();
	}

	/**
	 * Validate a hex color, with fallback.
	 *
	 * @param string $color
	 * @param string $fallback
	 * @return string
	 */
	private function sanitize_color( $color, $fallback ) {
		$color = sanitize_text_field( $color );
		return preg_match( '/^#[0-9a-fA-F]{3,8}$/', $color ) ? $color : $fallback;
	}

	/**
	 * View shown when the visitor is already authenticated.
	 *
	 * @return string
	 */
	private function logged_in_view() {
		$user = wp_get_current_user();
		$home = $this->role_home( (array) $user->roles );
		$label = $this->portal_label( (array) $user->roles );

		$out  = '<h2 class="carmel-login-title">ログイン中</h2>';
		$out .= '<p class="carmel-login-hello">' . esc_html( $user->display_name ) . ' 様</p>';
		$out .= '<p><a class="carmel-login-btn" href="' . esc_url( home_url( $home ) ) . '">' . esc_html( $label ) . 'を開く</a></p>';
		$out .= '<p class="carmel-login-links"><a href="' . esc_url( wp_logout_url( home_url( '/login' ) ) ) . '">ログアウト</a></p>';
		return $out;
	}

	private function role_home( array $roles ) {
		$map = array( 'hq_admin' => '/hq', 'store_owner' => '/store', 'store_staff' => '/store', 'customer' => '/mypage' );
		foreach ( $map as $role => $path ) {
			if ( in_array( $role, $roles, true ) ) {
				return $path;
			}
		}
		return '/';
	}

	private function portal_label( array $roles ) {
		if ( in_array( 'hq_admin', $roles, true ) ) {
			return '本部管理画面';
		}
		if ( array_intersect( array( 'store_owner', 'store_staff' ), $roles ) ) {
			return '加盟店ポータル';
		}
		if ( in_array( 'customer', $roles, true ) ) {
			return 'マイページ';
		}
		return 'トップ';
	}

	private function styles() {
		return '<style>
.carmel-login-wrap{--carmel-brand:#1a1a2e;--carmel-accent:#2e86de;min-height:70vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2.5em 1em;
  background:radial-gradient(120% 120% at 50% 0%, rgba(46,134,222,.10) 0%, rgba(26,26,46,.04) 45%, transparent 70%);font-size:14px}
.carmel-login-card{width:100%;max-width:400px;background:#fff;border:1px solid #e7eaf0;border-radius:1em;
  box-shadow:0 12px 40px rgba(26,26,46,.12);padding:2.4em 2em}
.carmel-login-brand{text-align:center;margin-bottom:1.6em}
.carmel-login-logo{max-height:54px;width:auto}
.carmel-login-wordmark{font-size:2.2em;font-weight:800;letter-spacing:.01em;font-style:italic;color:var(--carmel-brand)}
.carmel-login-tagline{margin:.5em 0 0;color:#7a8090;font-size:.85em}
.carmel-login-title{text-align:center;font-size:1.1em;color:var(--carmel-brand);margin:0 0 1.2em;
  position:relative}
.carmel-login-title:after{content:"";display:block;width:40px;height:3px;border-radius:3px;background:var(--carmel-accent);margin:.6em auto 0}
.carmel-login-card .login-username,.carmel-login-card .login-password{display:flex;flex-direction:column;gap:.35em;margin-bottom:1em;font-weight:600;color:#3a3f4b;font-size:.9em}
.carmel-login-card input[type=text],.carmel-login-card input[type=password]{border:1.5px solid #d9dee7;border-radius:.55em;padding:.75em .8em;font-weight:normal;font-size:1em;transition:border-color .15s}
.carmel-login-card input[type=text]:focus,.carmel-login-card input[type=password]:focus{border-color:var(--carmel-accent);outline:none;box-shadow:0 0 0 3px rgba(46,134,222,.15)}
.carmel-login-card .login-remember{font-weight:normal;font-size:.85em;color:#666;margin-bottom:1em}
.carmel-login-card .login-submit input{width:100%;background:var(--carmel-accent);color:#fff;border:0;border-radius:.55em;padding:.85em;font-size:1em;font-weight:700;cursor:pointer;transition:filter .15s}
.carmel-login-card .login-submit input:hover{filter:brightness(1.07)}
.carmel-login-btn{display:inline-block;background:var(--carmel-accent);color:#fff;text-decoration:none;border-radius:.55em;padding:.7em 1.4em;font-weight:700}
.carmel-login-hello{text-align:center;font-size:1.05em;margin:.2em 0 1em}
.carmel-login-links{text-align:center;margin-top:1.1em;font-size:.85em}
.carmel-login-links a{color:var(--carmel-accent);text-decoration:none}
.carmel-login-note{font-size:.78em;color:#9298a5;margin-top:1.4em;text-align:center;line-height:1.6}
.carmel-login-err{background:#fdecea;color:#a5281b;border:1px solid #e7a59d;border-radius:.5em;padding:.7em 1em;margin-bottom:1.1em;font-size:.9em}
.carmel-login-footer{color:#aab;font-size:.75em;margin-top:1.4em}
</style>';
	}
}
