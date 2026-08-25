<?php
/**
 * Franchise (加盟店) recruitment.
 *
 * Public application form [carmel_franchise_form] for prospective franchises —
 * distinct from the customer application. A submission creates a *draft*
 * carmel_store with application_status=pending and alerts HQ. HQ reviews and
 * approves it from the store-management screen (Carmel_HQ_Stores), which
 * activates the store and provisions the owner account.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Franchise {

	/** @var Carmel_Franchise|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_franchise_form';
	const ACTION    = 'carmel_franchise_submit';
	const NONCE     = 'carmel_franchise_form';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submit' ) );
	}

	public function handle_submit() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ACTION ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_fr', 'err', $redirect ) );
			exit;
		}
		if ( ! empty( $_POST['carmel_hp'] ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_fr', 'ok', $redirect ) );
			exit;
		}

		$store_name = isset( $_POST['carmel_store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_store_name'] ) ) : '';
		$owner      = isset( $_POST['carmel_owner_name'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_owner_name'] ) ) : '';
		$email      = isset( $_POST['carmel_email'] ) ? sanitize_email( wp_unslash( $_POST['carmel_email'] ) ) : '';
		$phone      = isset( $_POST['carmel_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_phone'] ) ) : '';
		$address    = isset( $_POST['carmel_address'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_address'] ) ) : '';
		$message    = isset( $_POST['carmel_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['carmel_message'] ) ) : '';

		if ( '' === $store_name || '' === $email || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_fr', 'invalid', $redirect ) );
			exit;
		}

		$store_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_store',
				'post_status' => 'draft', // not active until approved
				'post_title'  => $store_name,
				'meta_input'  => array(
					'application_status' => 'pending',
					'store_name'         => $store_name,
					'store_address'      => $address,
					'applicant_owner'    => $owner,
					'applicant_email'    => $email,
					'applicant_phone'    => $phone,
					'application_note'   => $message,
					'membership_status'  => 'none',
				),
			),
			true
		);

		if ( is_wp_error( $store_id ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_fr', 'err', $redirect ) );
			exit;
		}

		Carmel_Notifier::notify(
			'franchise_application',
			array(
				'event_id' => 'franchise_application:' . $store_id,
				'vars'     => array( 'store' => $store_name, 'contact' => $owner ? $owner : $email ),
			)
		);

		do_action( 'carmel_franchise_applied', $store_id );
		wp_safe_redirect( add_query_arg( 'carmel_fr', 'ok', $redirect ) );
		exit;
	}

	public function render( $atts ) {
		$atts = shortcode_atts( array( 'title' => '加盟店募集・お問い合わせ' ), $atts, self::SHORTCODE );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<form class="carmel-frform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<h3>' . esc_html( $atts['title'] ) . '</h3>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo wp_nonce_field( self::ACTION, self::NONCE, true, false ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-hp" aria-hidden="true"><label>未入力<input type="text" name="carmel_hp" tabindex="-1" autocomplete="off"></label></div>';

		echo '<label class="carmel-f">店舗・会社名<span class="req">*</span><input type="text" name="carmel_store_name" required></label>';
		echo '<label class="carmel-f">ご担当者名<input type="text" name="carmel_owner_name"></label>';
		echo '<label class="carmel-f">メールアドレス<span class="req">*</span><input type="email" name="carmel_email" required></label>';
		echo '<label class="carmel-f">電話番号<input type="tel" name="carmel_phone"></label>';
		echo '<label class="carmel-f">所在地<input type="text" name="carmel_address"></label>';
		echo '<label class="carmel-f">ご質問・ご要望<textarea name="carmel_message" rows="4"></textarea></label>';
		echo '<button type="submit" class="carmel-frform-btn">応募する</button>';
		echo '</form>';
		return ob_get_clean();
	}

	private function banner() {
		$msg = isset( $_GET['carmel_fr'] ) ? sanitize_key( $_GET['carmel_fr'] ) : '';
		$map = array(
			'ok'      => array( 'success', '加盟店応募を受け付けました。本部より追ってご連絡します。' ),
			'invalid' => array( 'error', '店舗名と有効なメールアドレスをご入力ください。' ),
			'err'     => array( 'error', '送信に失敗しました。再度お試しください。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-frform-banner carmel-frform-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-frform{max-width:520px;font-size:14px;display:flex;flex-direction:column;gap:.8em;border:1px solid #e0e3ea;border-radius:.6em;padding:1.4em;background:#fff}
.carmel-frform h3{margin:0 0 .3em}
.carmel-frform .carmel-f{display:flex;flex-direction:column;gap:.3em;font-weight:bold}
.carmel-frform .carmel-f input,.carmel-frform .carmel-f textarea{font-weight:normal;border:1px solid #ccc;border-radius:.4em;padding:.6em}
.carmel-frform .req{color:#c0392b;margin-left:.2em}
.carmel-frform-btn{background:#1a1a2e;color:#fff;border:0;border-radius:.4em;padding:.8em;font-size:1em;cursor:pointer}
.carmel-hp{position:absolute;left:-9999px;height:0;overflow:hidden}
.carmel-frform-banner{max-width:520px;padding:.8em 1em;border-radius:.4em;margin-bottom:1em}
.carmel-frform-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-frform-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
</style>';
	}
}
