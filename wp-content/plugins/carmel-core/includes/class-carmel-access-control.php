<?php
/**
 * Enforces login + role requirements on the portal pages
 * (/mypage, /store, /hq). Unauthenticated users are redirected to /login;
 * authenticated-but-unauthorized users get a 403.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Access_Control {

	/** @var Carmel_Access_Control|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Protected page slug => roles allowed to view it.
	 *
	 * @return array<string,string[]>
	 */
	public static function protected_pages() {
		return apply_filters(
			'carmel_protected_pages',
			array(
				'mypage'        => array( 'customer', 'hq_admin' ),
				'store'         => array( 'store_owner', 'store_staff', 'hq_admin' ),
				'store-billing' => array( 'store_owner', 'store_staff', 'hq_admin' ),
				'sales-support' => array( 'store_owner', 'store_staff', 'hq_admin' ),
				'store-content'  => array( 'store_owner', 'store_staff', 'hq_admin' ),
				'store-inventory'=> array( 'store_owner', 'store_staff', 'hq_admin' ),
				'community'      => array( 'customer', 'store_owner', 'store_staff', 'hq_admin' ),
				'hq'             => array( 'hq_admin' ),
			)
		);
	}

	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'guard' ) );
		add_filter( 'login_redirect', array( $this, 'role_login_redirect' ), 10, 3 );
	}

	/**
	 * Route users to their portal after login, by role.
	 *
	 * @param string           $redirect_to Default redirect.
	 * @param string           $requested   Requested redirect.
	 * @param WP_User|WP_Error $user
	 * @return string
	 */
	public function role_login_redirect( $redirect_to, $requested, $user ) {
		if ( ! ( $user instanceof WP_User ) || empty( $user->roles ) ) {
			return $redirect_to;
		}
		// Honor an explicit, safe deep-link — but not the login page or home,
		// so default logins fall through to role-based routing.
		$login = untrailingslashit( home_url( '/login' ) );
		$home  = untrailingslashit( home_url( '/' ) );
		if ( $requested && false === strpos( $requested, 'wp-admin' ) && wp_validate_redirect( $requested, false ) ) {
			$req = untrailingslashit( $requested );
			if ( $req !== $login && $req !== $home ) {
				return $redirect_to;
			}
		}

		$roles = (array) $user->roles;
		$map   = apply_filters(
			'carmel_role_home',
			array(
				'hq_admin'    => '/hq',
				'store_owner' => '/store',
				'store_staff' => '/store',
				'customer'    => '/mypage',
			)
		);
		foreach ( $map as $role => $path ) {
			if ( in_array( $role, $roles, true ) ) {
				return home_url( $path );
			}
		}
		return $redirect_to;
	}

	/**
	 * Gate the current request if it targets a protected page.
	 */
	public function guard() {
		if ( is_admin() ) {
			return;
		}

		$slug = $this->current_page_slug();
		if ( null === $slug ) {
			return;
		}

		$map = self::protected_pages();
		if ( ! isset( $map[ $slug ] ) ) {
			return;
		}

		$allowed_roles = $map[ $slug ];

		// 未ログイン → /login（ログイン後に戻す）
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( $this->login_url() );
			exit;
		}

		// ログイン済みだが権限なし → 403
		if ( ! $this->user_has_any_role( $allowed_roles ) ) {
			wp_die(
				esc_html__( 'このページにアクセスする権限がありません。', 'carmel-core' ),
				esc_html__( 'アクセス拒否', 'carmel-core' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Resolve the current singular page slug, or null.
	 *
	 * @return string|null
	 */
	private function current_page_slug() {
		if ( ! is_page() ) {
			return null;
		}
		$post = get_queried_object();
		return ( $post instanceof WP_Post ) ? $post->post_name : null;
	}

	/**
	 * Build the login URL, preserving the originally requested page.
	 *
	 * @return string
	 */
	private function login_url() {
		$login_page = get_page_by_path( 'login' );
		$redirect   = home_url( add_query_arg( array() ) );

		if ( $login_page instanceof WP_Post ) {
			return add_query_arg( 'redirect_to', rawurlencode( $redirect ), get_permalink( $login_page ) );
		}
		return wp_login_url( $redirect );
	}

	/**
	 * Whether the current user has at least one of the given roles.
	 *
	 * @param string[] $roles
	 * @return bool
	 */
	private function user_has_any_role( array $roles ) {
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}
		// administrator も全ポータルを閲覧可（運用補助）
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}
		return (bool) array_intersect( $roles, (array) $user->roles );
	}
}
