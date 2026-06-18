<?php
/**
 * Community (bbPress) + learning content (Notion) links.
 *
 * Per the constraints, learning content lives in Notion (external link, not
 * built into WP) and the community uses bbPress. This module surfaces those
 * via the [carmel_learning] shortcode: it shows the current user's store Notion
 * link plus the community forum link. Access follows the matrix — learning
 * content is for store/HQ staff (not customers); the community is open to all
 * logged-in roles.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Community {

	/** @var Carmel_Community|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_learning';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/** Community (bbPress) URL from settings, if configured. */
	private function community_url() {
		$url = defined( 'CARMEL_COMMUNITY_URL' ) ? CARMEL_COMMUNITY_URL : get_option( 'carmel_community_url', '' );
		if ( '' === $url && function_exists( 'bbp_get_forums_url' ) ) {
			$url = bbp_get_forums_url();
		}
		return $url;
	}

	/**
	 * Notion learning URL for the current user's store
	 * (falls back to a global option).
	 */
	private function notion_url() {
		$store_id = (int) get_user_meta( get_current_user_id(), 'store_id', true );
		$url      = $store_id ? (string) get_post_meta( $store_id, 'notion_url', true ) : '';
		if ( '' === $url ) {
			$url = defined( 'CARMEL_NOTION_URL' ) ? CARMEL_NOTION_URL : get_option( 'carmel_notion_url', '' );
		}
		return $url;
	}

	/**
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p class="carmel-notice">ログインするとご利用いただけます。</p>';
		}

		$is_staff   = current_user_can( 'carmel_change_deal_status' ) || current_user_can( 'carmel_manage_stores' );
		$notion     = $this->notion_url();
		$community  = $this->community_url();

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-learning"><div class="carmel-learn-grid">';

		// Learning content (staff/HQ only).
		if ( $is_staff ) {
			echo '<div class="carmel-learn-card">';
			echo '<h3>📘 学習コンテンツ</h3>';
			if ( $notion ) {
				echo '<p>マニュアル・研修資料（Notion）をご確認いただけます。</p>';
				echo '<a class="carmel-learn-btn" href="' . esc_url( $notion ) . '" target="_blank" rel="noopener">学習コンテンツを開く</a>';
			} else {
				echo '<p class="carmel-muted">学習コンテンツのリンクが未設定です。本部にお問い合わせください。</p>';
			}
			echo '</div>';
		}

		// Community (all logged-in roles).
		echo '<div class="carmel-learn-card">';
		echo '<h3>💬 コミュニティ</h3>';
		if ( $community ) {
			echo '<p>加盟店・本部の情報交換フォーラムです。</p>';
			echo '<a class="carmel-learn-btn" href="' . esc_url( $community ) . '" target="_blank" rel="noopener">コミュニティを開く</a>';
		} else {
			echo '<p class="carmel-muted">コミュニティのリンクが未設定です。</p>';
		}
		echo '</div>';

		echo '</div></div>';
		return ob_get_clean();
	}

	private function styles() {
		return '<style>
.carmel-learning{font-size:14px}
.carmel-learn-grid{display:flex;gap:1em;flex-wrap:wrap}
.carmel-learn-card{flex:1;min-width:240px;border:1px solid #e0e3ea;border-radius:.6em;padding:1.2em;background:#fff}
.carmel-learn-card h3{margin:0 0 .5em}
.carmel-learn-btn{display:inline-block;margin-top:.5em;background:#2e86de;color:#fff;text-decoration:none;border-radius:.4em;padding:.5em 1.1em}
.carmel-muted{color:#888}
.carmel-notice{padding:1em;background:#f4f6fb;border:1px solid #cdd2dc;border-radius:.4em}
</style>';
	}
}
