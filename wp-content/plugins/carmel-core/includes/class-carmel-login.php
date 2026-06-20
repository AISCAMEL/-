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
				'title' => 'ログイン',
			),
			$atts,
			self::SHORTCODE
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-login">';

		if ( is_user_logged_in() ) {
			echo $this->logged_in_view(); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return ob_get_clean();
		}

		echo '<h2>' . esc_html( $atts['title'] ) . '</h2>';

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

		echo '</div>';
		return ob_get_clean();
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

		$out  = '<h2>ログイン中</h2>';
		$out .= '<p>' . esc_html( $user->display_name ) . ' 様としてログインしています。</p>';
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
.carmel-login{max-width:380px;margin:0 auto;font-size:14px}
.carmel-login h2{text-align:center;margin-bottom:1em}
.carmel-login .login-username,.carmel-login .login-password{display:flex;flex-direction:column;gap:.3em;margin-bottom:.8em;font-weight:bold}
.carmel-login input[type=text],.carmel-login input[type=password]{border:1px solid #ccc;border-radius:.4em;padding:.6em;font-weight:normal}
.carmel-login .login-remember{font-weight:normal;margin-bottom:.8em}
.carmel-login .login-submit input{width:100%;background:#2e86de;color:#fff;border:0;border-radius:.4em;padding:.8em;font-size:1em;cursor:pointer}
.carmel-login-btn{display:inline-block;background:#2e86de;color:#fff;text-decoration:none;border-radius:.4em;padding:.6em 1.2em}
.carmel-login-links{text-align:center;margin-top:1em;font-size:.9em}
.carmel-login-note{font-size:.8em;color:#888;margin-top:1.2em;text-align:center}
.carmel-login-err{background:#fdecea;color:#a5281b;border:1px solid #c0392b;border-radius:.4em;padding:.7em 1em;margin-bottom:1em}
</style>';
	}
}
