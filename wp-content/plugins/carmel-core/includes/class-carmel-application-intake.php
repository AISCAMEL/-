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

		// WPForms: fire after a completed submission.
		add_action( 'wpforms_process_complete', array( $this, 'handle_wpforms' ), 10, 4 );
	}

	/**
	 * 取り込み対象の WPForms フォームID（許可制）。
	 * 未指定のフォームは処理しない（無関係なフォームの誤起票を防ぐ）。
	 *
	 * @param array $form_data
	 * @return array<int>  許可フォームID
	 */
	private function wpforms_allowed_forms( $form_data ) {
		$maps    = apply_filters( 'carmel_wpforms_field_map', array(), $form_data );
		$inquiry = (array) apply_filters( 'carmel_wpforms_inquiry_forms', get_option( 'carmel_wpforms_inquiry_forms', array() ) );
		$forms   = (array) apply_filters( 'carmel_wpforms_forms', get_option( 'carmel_wpforms_forms', array() ) );
		$ids     = array_merge( array_keys( (array) $maps ), $inquiry, $forms );
		return array_map( 'intval', $ids );
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

		// intent: application（正式申込＝AIスコア対象） / inquiry（反響・問い合わせ＝スコア無し）。
		$intent = isset( $data['intent'] ) ? sanitize_key( $data['intent'] ) : 'application';
		if ( ! in_array( $intent, array( 'application', 'inquiry' ), true ) ) {
			$intent = 'application';
		}

		// --- 1. Provision (or reuse) the customer account -------------------
		$account = self::provision_customer( $name, $email );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		$customer_id    = $account['user_id'];
		$created        = $account['created'];
		$set_pw_url     = $account['set_password_url'];

		// LINE ユーザーID（LIFF等から）を顧客に保存。以後の通知がLINEへ届く鍵。
		if ( ! empty( $data['line_user_id'] ) ) {
			update_user_meta( $customer_id, 'line_user_id', sanitize_text_field( $data['line_user_id'] ) );
		}

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
					'lead_intent' => $intent,
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

		// 正式申込のみ AIスコア等の後続処理（carmel_application_created）を発火。
		// 反響（inquiry）はスコアを走らせず、別イベントで通知/集計のみ。
		if ( 'inquiry' === $intent ) {
			update_post_meta( $deal_id, 'is_lead', 1 );
			do_action( 'carmel_inquiry_created', $deal_id, $customer_id, $data );
		} else {
			do_action( 'carmel_application_created', $deal_id, $customer_id, $data );
		}

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
	 * Generic account provisioning for any role (owner / staff / customer).
	 * Reuses an existing user by email (adding the role if missing); otherwise
	 * creates one and returns a password-set link.
	 *
	 * @param string $name
	 * @param string $email
	 * @param string $role
	 * @return array|WP_Error [ user_id, created(bool), set_password_url ]
	 */
	public static function provision_user( $name, $email, $role ) {
		$email = sanitize_email( $email );
		$name  = sanitize_text_field( $name );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'carmel_bad_email', '有効なメールアドレスが必要です。' );
		}

		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			if ( ! in_array( $role, (array) $existing->roles, true ) ) {
				$existing->add_role( $role );
			}
			return array( 'user_id' => (int) $existing->ID, 'created' => false, 'set_password_url' => '' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => self::unique_username_from_email( $email ),
				'user_email'   => $email,
				'display_name' => $name,
				'first_name'   => $name,
				'user_pass'    => wp_generate_password( 24, true, true ),
				'role'         => $role,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$user       = get_userdata( $user_id );
		$key        = get_password_reset_key( $user );
		$set_pw_url = is_wp_error( $key ) ? '' : network_site_url( "wp-login.php?action=rp&key={$key}&login=" . rawurlencode( $user->user_login ), 'login' );

		return array( 'user_id' => (int) $user_id, 'created' => true, 'set_password_url' => $set_pw_url );
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
			'name'         => $request->get_param( 'name' ),
			'email'        => $request->get_param( 'email' ),
			'phone'        => $request->get_param( 'phone' ),
			'deal_type'    => $request->get_param( 'deal_type' ),
			'address'      => $request->get_param( 'address' ),
			'message'      => $request->get_param( 'message' ),
			'line_user_id' => $request->get_param( 'line_user_id' ),
			'intent'       => $request->get_param( 'intent' ),
			'extra'        => $request->get_param( 'extra' ),
			'source'       => 'rest',
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
				'name'         => 'your-name',
				'email'        => 'your-email',
				'phone'        => 'your-tel',
				'deal_type'    => 'deal-type',
				'address'      => 'your-address',
				'message'      => 'your-message',
				'line_user_id' => 'line-user-id',
				'intent'       => 'intent',
			),
			$contact_form
		);

		// フォーム単位の intent 既定（フィルタで上書き可。例：問い合わせフォームは inquiry）。
		$data = array( 'source' => 'cf7', 'intent' => apply_filters( 'carmel_cf7_intent', 'application', $contact_form ) );
		foreach ( $map as $key => $field ) {
			if ( isset( $posted[ $field ] ) && '' !== $posted[ $field ] ) {
				$data[ $key ] = is_array( $posted[ $field ] ) ? reset( $posted[ $field ] ) : $posted[ $field ];
			}
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

	/**
	 * WPForms bridge. Runs on `wpforms_process_complete`.
	 *
	 * 安全のため許可制：`carmel_wpforms_field_map` / `carmel_wpforms_forms`(option) /
	 * `carmel_wpforms_inquiry_forms`(option) のいずれかに含まれるフォームのみ処理する。
	 * 明示マップが無ければフィールド型・ラベルから自動マッピングする。
	 *
	 * @param array $fields    送信フィールド（id => [ name(label), value, type, ... ]）
	 * @param array $entry     生エントリ
	 * @param array $form_data フォーム定義（id を含む）
	 * @param int   $entry_id
	 */
	public function handle_wpforms( $fields, $entry, $form_data, $entry_id ) {
		$form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
		if ( ! $form_id ) {
			return;
		}

		$allowed = $this->wpforms_allowed_forms( $form_data );
		if ( ! in_array( $form_id, $allowed, true ) && ! apply_filters( 'carmel_wpforms_enabled', false, $form_id, $form_data ) ) {
			return; // 許可されていないフォームは無視。
		}

		// フィールドID => 値。
		$by_id = array();
		foreach ( (array) $fields as $fid => $f ) {
			$by_id[ (string) $fid ] = isset( $f['value'] ) ? $f['value'] : '';
		}

		$maps = apply_filters( 'carmel_wpforms_field_map', array(), $form_data );
		$data = array( 'source' => 'wpforms' );
		if ( ! empty( $maps[ $form_id ] ) ) {
			foreach ( (array) $maps[ $form_id ] as $key => $fid ) {
				$data[ $key ] = isset( $by_id[ (string) $fid ] ) ? $by_id[ (string) $fid ] : '';
			}
		} else {
			$data = array_merge( $data, $this->wpforms_auto_map( (array) $fields ) );
		}

		// LIFF が挿入する hidden（WPForms は AJAX でフォーム全体を送るため $_POST に入る）。
		if ( empty( $data['line_user_id'] ) && ! empty( $_POST['line_user_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['line_user_id'] = sanitize_text_field( wp_unslash( $_POST['line_user_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		// intent / deal_type（フォーム単位）。
		$inquiry = array_map( 'intval', (array) apply_filters( 'carmel_wpforms_inquiry_forms', get_option( 'carmel_wpforms_inquiry_forms', array() ) ) );
		$intent  = in_array( $form_id, $inquiry, true ) ? 'inquiry' : 'application';
		$data['intent']    = apply_filters( 'carmel_wpforms_intent', $intent, $form_id, $form_data );
		if ( empty( $data['deal_type'] ) ) {
			$data['deal_type'] = apply_filters( 'carmel_wpforms_deal_type', 'loan', $form_id, $form_data );
		}

		self::process( $data );
	}

	/**
	 * WPForms のフィールドを型・ラベルから正規化キーへ自動マッピング。
	 *
	 * @param array $fields
	 * @return array
	 */
	private function wpforms_auto_map( array $fields ) {
		$out = array();
		foreach ( $fields as $f ) {
			$type  = isset( $f['type'] ) ? strtolower( (string) $f['type'] ) : '';
			$label = isset( $f['name'] ) ? (string) $f['name'] : '';
			$val   = isset( $f['value'] ) ? $f['value'] : '';
			if ( is_array( $val ) ) {
				$val = implode( ' ', $val );
			}
			$val = trim( (string) $val );
			$lc  = strtolower( $label );

			// LINE userId（hidden 等・ラベル/メタ名が line_user_id）。
			if ( 'line_user_id' === $lc || false !== strpos( $lc, 'line_user_id' ) || false !== strpos( $lc, 'line-user-id' ) ) {
				if ( '' !== $val ) {
					$out['line_user_id'] = $val;
				}
				continue;
			}
			if ( '' === $val ) {
				continue;
			}
			if ( empty( $out['email'] ) && ( 'email' === $type || false !== strpos( $lc, 'email' ) || false !== strpos( $label, 'メール' ) ) ) {
				$out['email'] = $val;
			} elseif ( empty( $out['name'] ) && ( 'name' === $type || false !== strpos( $lc, 'name' ) || false !== strpos( $label, '氏名' ) || false !== strpos( $label, 'お名前' ) ) ) {
				$out['name'] = $val;
			} elseif ( empty( $out['phone'] ) && ( 'phone' === $type || false !== strpos( $lc, 'phone' ) || false !== strpos( $lc, 'tel' ) || false !== strpos( $label, '電話' ) ) ) {
				$out['phone'] = $val;
			} elseif ( empty( $out['address'] ) && ( 'address' === $type || false !== strpos( $label, '住所' ) ) ) {
				$out['address'] = $val;
			} elseif ( empty( $out['message'] ) && ( 'textarea' === $type || false !== strpos( $label, '内容' ) || false !== strpos( $label, '問い合わせ' ) || false !== strpos( $label, '備考' ) || false !== strpos( $label, 'ご要望' ) ) ) {
				$out['message'] = $val;
			}
		}
		return $out;
	}
}
