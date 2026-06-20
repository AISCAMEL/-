<?php
/**
 * Application intake: turns a submitted application form into a customer
 * account (auto-provisioned) + a carmel_deal, then fires the
 * 'application_received' notification.
 *
 * Form-agnostic core: Carmel_Application_Intake::process( $data ) can be
 * called from Contact Form 7, Gravity Forms, or the REST endpoint. Each
 * front-end only has to map its fields to the normalized keys below.
 *
 * Normalized input keys:
 *   name      (required) 氏名
 *   email     (required) メールアドレス
 *   phone                電話番号
 *   deal_type            loan|buyback|lease (default: loan)
 *   message              備考・問い合わせ内容
 *   extra                array 追加メタ（任意）
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Application_Intake {

	/** @var Carmel_Application_Intake|null */
	private static $instance = null;

	const REST_NAMESPACE = 'carmel/v1';
	const REST_ROUTE      = '/application';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Initial deal status per business type. */
	public static function initial_status( $deal_type ) {
		$map = array(
			'loan'    => 'provisional',
			'buyback' => 'appraisal_request',
			'lease'   => 'lease_request',
		);
		return isset( $map[ $deal_type ] ) ? $map[ $deal_type ] : 'provisional';
	}

	public function register_hooks() {
		// REST endpoint for headless / external forms.
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		// Contact Form 7: fire after a successful submission.
		add_action( 'wpcf7_mail_sent', array( $this, 'handle_cf7' ) );

		// Gravity Forms: fire after submission.
		add_action( 'gform_after_submission', array( $this, 'handle_gform' ), 10, 2 );
	}

	/**
	 * Core routine. Idempotent-ish: re-uses an existing user by email.
	 *
	 * @param array $data Normalized input.
	 * @return array|WP_Error [ deal_id, customer_id, created_account(bool) ]
	 */
	public static function process( array $data ) {
		$name  = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'carmel_intake_no_name', '氏名は必須です。' );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'carmel_intake_bad_email', '有効なメールアドレスが必要です。' );
		}

		$deal_type = isset( $data['deal_type'] ) ? sanitize_key( $data['deal_type'] ) : 'loan';
		if ( ! in_array( $deal_type, array( 'loan', 'buyback', 'lease' ), true ) ) {
			$deal_type = 'loan';
		}

		// --- 1. Provision (or reuse) the customer account -------------------
		$account = self::provision_customer( $name, $email );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		$customer_id    = $account['user_id'];
		$created        = $account['created'];
		$set_pw_url     = $account['set_password_url'];

		// --- 2. Create the deal --------------------------------------------
		$deal_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_deal',
				'post_status' => 'publish',
				'post_title'  => sprintf( '%s 様 / %s / %s', $name, self::type_label( $deal_type ), gmdate( 'Y-m-d' ) ),
				'post_author' => $customer_id,
				'meta_input'  => array(
					'deal_type'   => $deal_type,
					'deal_status' => self::initial_status( $deal_type ),
					'customer_id' => $customer_id,
					'created_via' => isset( $data['source'] ) ? sanitize_key( $data['source'] ) : 'form',
					'applicant_name'  => $name,
					'applicant_email' => $email,
					'applicant_phone' => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
					'applicant_address' => isset( $data['address'] ) ? sanitize_text_field( $data['address'] ) : '',
					'application_note'=> isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : '',
				),
			),
			true
		);

		if ( is_wp_error( $deal_id ) ) {
			return $deal_id;
		}

		// Store any extra fields as namespaced meta.
		if ( ! empty( $data['extra'] ) && is_array( $data['extra'] ) ) {
			foreach ( $data['extra'] as $k => $v ) {
				update_post_meta( $deal_id, 'extra_' . sanitize_key( $k ), sanitize_text_field( is_scalar( $v ) ? $v : wp_json_encode( $v ) ) );
			}
		}

		do_action( 'carmel_application_created', $deal_id, $customer_id, $data );

		// --- 3. Notify (welcome + account info) ----------------------------
		$account_notice = $created && $set_pw_url
			? "アカウントを発行しました。初回ログイン用パスワードの設定はこちら：\n{$set_pw_url}"
			: '既存のアカウントでマイページにログインできます。';

		Carmel_Notifier::notify(
			'application_received',
			array(
				'event_id'     => 'application_received:' . $deal_id,
				'deal_id'      => $deal_id,
				'recipient_id' => $customer_id,
				'vars'         => array(
					'name'           => $name,
					'account_notice' => $account_notice,
				),
			)
		);

		return array(
			'deal_id'         => (int) $deal_id,
			'customer_id'     => (int) $customer_id,
			'created_account' => (bool) $created,
		);
	}

	/**
	 * Find an existing user by email or create a new customer.
	 * New accounts get a password-reset ("set password") link rather than a
	 * plaintext password — secure default; see requirements §13-#5.
	 *
	 * @param string $name
	 * @param string $email
	 * @return array|WP_Error [ user_id, created(bool), set_password_url ]
	 */
	private static function provision_customer( $name, $email ) {
		$existing = get_user_by( 'email', $email );

		if ( $existing ) {
			// Ensure they have the customer role (don't downgrade staff/admin).
			if ( ! array_intersect( array( 'customer', 'store_owner', 'store_staff', 'hq_admin', 'administrator' ), (array) $existing->roles ) ) {
				$existing->add_role( 'customer' );
			}
			return array(
				'user_id'          => (int) $existing->ID,
				'created'          => false,
				'set_password_url' => '',
			);
		}

		$username = self::unique_username_from_email( $email );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_email'   => $email,
				'display_name' => $name,
				'first_name'   => $name,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'role'         => 'customer',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user      = get_userdata( $user_id );
		$key       = get_password_reset_key( $user );
		$set_pw_url = is_wp_error( $key )
			? ''
			: network_site_url( "wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user->user_login ), 'login' );

		return array(
			'user_id'          => (int) $user_id,
			'created'          => true,
			'set_password_url' => $set_pw_url,
		);
	}

	/**
	 * Derive a unique, sanitized username from an email local part.
	 *
	 * @param string $email
	 * @return string
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base ) {
			$base = 'customer';
		}
		$username = $base;
		$i        = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $i;
			$i++;
		}
		return $username;
	}

	private static function type_label( $deal_type ) {
		$labels = array(
			'loan'    => 'ローン販売',
			'buyback' => '車買取',
			'lease'   => '自社リース',
		);
		return isset( $labels[ $deal_type ] ) ? $labels[ $deal_type ] : $deal_type;
	}

	/* --------------------------------------------------------------------- *
	 * Front-end bridges
	 * --------------------------------------------------------------------- */

	/**
	 * REST: POST /wp-json/carmel/v1/application
	 */
	public function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rest' ),
				'permission_callback' => array( $this, 'rest_permission' ),
			)
		);
	}

	/**
	 * REST permission: require a configured intake token (header
	 * X-Carmel-Token). If no token is configured, deny by default and let a
	 * filter opt in — avoids an open, spammable endpoint.
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function rest_permission( $request ) {
		$configured = defined( 'CARMEL_INTAKE_TOKEN' ) ? CARMEL_INTAKE_TOKEN : get_option( 'carmel_intake_token', '' );

		if ( '' === (string) $configured ) {
			return (bool) apply_filters( 'carmel_intake_allow_unauthenticated', false, $request );
		}

		$provided = $request->get_header( 'x-carmel-token' );
		return hash_equals( (string) $configured, (string) $provided );
	}

	/**
	 * REST handler.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_rest( $request ) {
		$data = array(
			'name'      => $request->get_param( 'name' ),
			'email'     => $request->get_param( 'email' ),
			'phone'     => $request->get_param( 'phone' ),
			'deal_type' => $request->get_param( 'deal_type' ),
			'address'   => $request->get_param( 'address' ),
			'message'   => $request->get_param( 'message' ),
			'extra'     => $request->get_param( 'extra' ),
			'source'    => 'rest',
		);

		$result = self::process( $data );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Contact Form 7 bridge. Field names are mapped via the
	 * 'carmel_cf7_field_map' filter (defaults assume your-name/your-email/etc).
	 *
	 * @param WPCF7_ContactForm $contact_form
	 */
	public function handle_cf7( $contact_form ) {
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}
		$posted = $submission->get_posted_data();

		$map = apply_filters(
			'carmel_cf7_field_map',
			array(
				'name'      => 'your-name',
				'email'     => 'your-email',
				'phone'     => 'your-tel',
				'deal_type' => 'deal-type',
				'address'   => 'your-address',
				'message'   => 'your-message',
			),
			$contact_form
		);

		$data = array( 'source' => 'cf7' );
		foreach ( $map as $key => $field ) {
			$data[ $key ] = isset( $posted[ $field ] ) ? $posted[ $field ] : '';
		}

		self::process( $data );
	}

	/**
	 * Gravity Forms bridge. Field IDs are mapped via 'carmel_gform_field_map'
	 * keyed by form ID.
	 *
	 * @param array $entry
	 * @param array $form
	 */
	public function handle_gform( $entry, $form ) {
		$maps = apply_filters( 'carmel_gform_field_map', array(), $form );
		$form_id = isset( $form['id'] ) ? (int) $form['id'] : 0;

		if ( empty( $maps[ $form_id ] ) ) {
			return; // Form not configured for intake.
		}
		$map = $maps[ $form_id ];

		$data = array( 'source' => 'gform' );
		foreach ( $map as $key => $field_id ) {
			$data[ $key ] = isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';
		}

		self::process( $data );
	}
}
