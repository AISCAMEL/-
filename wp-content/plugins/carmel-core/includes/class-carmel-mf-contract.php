<?php
/**
 * Money Forward Cloud 契約 (electronic signature) integration — HQ only.
 *
 * HQ sends a contract for signature from the [carmel_hq_contracts] screen
 * (requires the carmel_send_contract cap — stores/staff can never send).
 * The customer is notified to sign; when Money Forward reports completion via
 * the verified webhook, the deal advances to 'contracted'.
 *
 * The Money Forward 契約 API is multi-step; this client posts a normalized
 * payload to a configurable endpoint (direct API or a thin wrapper) and is
 * filterable so the exact request shape can be adapted at integration time.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_MF_Contract {

	/** @var Carmel_MF_Contract|null */
	private static $instance = null;

	const SHORTCODE      = 'carmel_hq_contracts';
	const SEND_ACTION    = 'carmel_mf_contract_send';
	const NONCE          = 'carmel_mf_contract_nonce';
	const REST_NAMESPACE = 'carmel/v1';
	const REST_CALLBACK  = '/mf-contract-callback';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::SEND_ACTION, array( $this, 'handle_send' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	private function endpoint() {
		return defined( 'CARMEL_MF_ENDPOINT' ) ? CARMEL_MF_ENDPOINT : get_option( 'carmel_mf_endpoint', '' );
	}

	private function token() {
		return defined( 'CARMEL_MF_TOKEN' ) ? CARMEL_MF_TOKEN : get_option( 'carmel_mf_token', '' );
	}

	public function is_ready() {
		return '' !== (string) $this->endpoint() && '' !== (string) $this->token();
	}

	/* --------------------------------------------------------------------- *
	 * Sending
	 * --------------------------------------------------------------------- */

	/**
	 * Send a contract for signature. HQ-only.
	 *
	 * @param int   $deal_id
	 * @param array $args [ system(bool) ]
	 * @return array|WP_Error
	 */
	public function send( $deal_id, array $args = array() ) {
		if ( empty( $args['system'] ) && ! current_user_can( 'carmel_send_contract' ) ) {
			return new WP_Error( 'carmel_forbidden', 'マネーフォワード契約の送付は本部のみ可能です。', array( 'status' => 403 ) );
		}
		if ( 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return new WP_Error( 'carmel_not_a_deal', '案件が見つかりません。' );
		}
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'mf_not_configured', 'マネーフォワード契約が未設定です。' );
		}

		$customer_id = (int) get_post_meta( $deal_id, 'customer_id', true );
		$customer    = $customer_id ? get_userdata( $customer_id ) : null;

		$payload = apply_filters(
			'carmel_mf_contract_payload',
			array(
				'deal_id' => (int) $deal_id,
				'title'   => '売買契約書 #' . (int) $deal_id,
				'email'   => $customer ? $customer->user_email : get_post_meta( $deal_id, 'applicant_email', true ),
				'name'    => get_post_meta( $deal_id, 'applicant_name', true ),
			),
			$deal_id
		);

		$response = wp_remote_post(
			$this->endpoint(),
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->token(),
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->notify_failure( $deal_id, $response->get_error_message() );
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->notify_failure( $deal_id, 'HTTP ' . $code );
			return new WP_Error( 'mf_http_' . $code, 'マネーフォワード契約 送付失敗 HTTP ' . $code );
		}

		$decoded     = json_decode( wp_remote_retrieve_body( $response ), true );
		$document_id = is_array( $decoded ) && isset( $decoded['document_id'] ) ? $decoded['document_id'] : ( isset( $decoded['id'] ) ? $decoded['id'] : '' );

		// Record on the deal + a contract document.
		update_post_meta( $deal_id, 'mf_contract_id', sanitize_text_field( $document_id ) );
		update_post_meta( $deal_id, 'mf_contract_status', 'sent' );
		$this->upsert_contract_document( $deal_id, $document_id );

		// Notify the customer to sign.
		Carmel_Notifier::notify(
			'contract_sign_request',
			array(
				'event_id' => 'contract_sign_request:' . $deal_id,
				'deal_id'  => $deal_id,
				'vars'     => array( 'name' => get_post_meta( $deal_id, 'applicant_name', true ) ),
			)
		);

		do_action( 'carmel_mf_contract_sent', $deal_id, $document_id );
		return array( 'document_id' => $document_id );
	}

	/**
	 * Create or update the contract carmel_document for a deal.
	 */
	private function upsert_contract_document( $deal_id, $contract_id ) {
		$existing = get_posts(
			array(
				'post_type'      => 'carmel_document',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'deal_id', 'value' => (int) $deal_id ),
					array( 'key' => 'doc_type', 'value' => 'contract' ),
				),
			)
		);

		$meta = array(
			'deal_id'        => (int) $deal_id,
			'doc_type'       => 'contract',
			'mf_contract_id' => sanitize_text_field( $contract_id ),
			'generated_at'   => current_time( 'mysql' ),
		);

		if ( ! empty( $existing ) ) {
			foreach ( $meta as $k => $v ) {
				update_post_meta( $existing[0], $k, $v );
			}
			return (int) $existing[0];
		}
		$doc_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_document',
				'post_status' => 'publish',
				'post_title'  => '売買契約書 #' . (int) $deal_id,
				'meta_input'  => $meta,
			)
		);
		return is_wp_error( $doc_id ) ? 0 : (int) $doc_id;
	}

	private function notify_failure( $deal_id, $message ) {
		Carmel_Notifier::notify(
			'system_error',
			array(
				'event_id' => 'mf_contract_fail:' . $deal_id . ':' . time(),
				'vars'     => array( 'message' => 'マネーフォワード契約 送付失敗 #' . $deal_id . ': ' . $message ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * HQ screen
	 * --------------------------------------------------------------------- */

	public function handle_send() {
		if ( ! current_user_can( 'carmel_send_contract' ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$deal_id  = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? $_POST[ self::NONCE ] : '', self::SEND_ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}

		$result = $this->send( $deal_id );
		$msg    = is_wp_error( $result ) ? 'err' : 'sent';
		wp_safe_redirect( add_query_arg( 'carmel_mf', $msg, $redirect ) );
		exit;
	}

	/**
	 * Render the contract queue.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! current_user_can( 'carmel_send_contract' ) ) {
			return '<p class="carmel-notice">契約管理を表示する権限がありません。</p>';
		}

		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
					array( 'key' => 'deal_status', 'value' => array( 'doc_prep', 'contracted' ), 'compare' => 'IN' ),
				),
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-contracts"><h2>契約（マネーフォワード契約）</h2>';

		if ( empty( $deals ) ) {
			echo '<p>契約待ちの案件はありません。</p></div>';
			return ob_get_clean();
		}

		echo '<table class="carmel-table"><thead><tr><th>案件</th><th>申込者</th><th>署名状況</th><th>操作</th></tr></thead><tbody>';
		foreach ( $deals as $deal ) {
			$cs_status = get_post_meta( $deal->ID, 'mf_contract_status', true );
			$name      = get_post_meta( $deal->ID, 'applicant_name', true );
			echo '<tr>';
			echo '<td>#' . (int) $deal->ID . '</td>';
			echo '<td>' . esc_html( $name ? $name : $deal->post_title ) . '</td>';
			echo '<td>' . esc_html( $this->cs_label( $cs_status ) ) . '</td>';
			echo '<td>' . $this->send_button( $deal->ID, $cs_status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	private function send_button( $deal_id, $cs_status ) {
		if ( 'completed' === $cs_status ) {
			return '<span class="carmel-done">署名完了</span>';
		}
		$label = ( 'sent' === $cs_status ) ? '再送付' : 'マネーフォワード契約 送付';
		$nonce = wp_create_nonce( self::SEND_ACTION . '_' . $deal_id );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'契約書を署名依頼として送付します。よろしいですか？\');">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::SEND_ACTION ) . '">'
			. '<input type="hidden" name="deal_id" value="' . (int) $deal_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-btn carmel-btn-blue">' . esc_html( $label ) . '</button></form>';
	}

	private function cs_label( $status ) {
		$map = array( '' => '未送付', 'sent' => '署名待ち', 'completed' => '署名完了', 'rejected' => '却下' );
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	private function banner() {
		$msg = isset( $_GET['carmel_mf'] ) ? sanitize_key( $_GET['carmel_mf'] ) : '';
		$map = array(
			'sent' => array( 'success', '署名依頼を送付しました。' ),
			'err'  => array( 'error', '送付できませんでした（設定・権限をご確認ください）。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	/* --------------------------------------------------------------------- *
	 * Webhook (signature completion)
	 * --------------------------------------------------------------------- */

	public function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_CALLBACK,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_callback' ),
				'permission_callback' => array( $this, 'verify_callback' ),
			)
		);
	}

	public function verify_callback( $request ) {
		$token = $this->token();
		if ( '' === (string) $token ) {
			return false;
		}
		$provided = $request->get_header( 'x-carmel-token' );
		if ( ! $provided ) {
			$provided = $request->get_param( 'token' );
		}
		return hash_equals( (string) $token, (string) $provided );
	}

	/**
	 * Handle a Money Forward 契約 status callback.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_callback( $request ) {
		$deal_id = (int) $request->get_param( 'deal_id' );
		$status  = sanitize_key( (string) $request->get_param( 'status' ) ); // completed|rejected
		$pdf_url = $request->get_param( 'pdf_url' );

		if ( ! $deal_id || 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return new WP_Error( 'mf_bad_deal', 'deal_id が不正です', array( 'status' => 400 ) );
		}

		if ( 'completed' === $status ) {
			update_post_meta( $deal_id, 'mf_contract_status', 'completed' );
			if ( $pdf_url ) {
				$this->store_signed_pdf( $deal_id, $pdf_url );
			}
			// Advance to contracted (system bypasses the HQ cap check).
			Carmel_Deal_Status::change( $deal_id, 'contracted', array( 'system' => true, 'note' => 'マネーフォワード契約 署名完了' ) );
		} elseif ( 'rejected' === $status ) {
			update_post_meta( $deal_id, 'mf_contract_status', 'rejected' );
		}

		do_action( 'carmel_mf_contract_callback', $deal_id, $status );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function store_signed_pdf( $deal_id, $pdf_url ) {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_document',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'deal_id', 'value' => (int) $deal_id ),
					array( 'key' => 'doc_type', 'value' => 'contract' ),
				),
			)
		);
		if ( ! empty( $ids ) ) {
			update_post_meta( $ids[0], 'pdf_url', esc_url_raw( $pdf_url ) );
		}
	}

	private function styles() {
		return '<style>
.carmel-contracts{font-size:14px}
.carmel-table{width:100%;border-collapse:collapse;margin-top:1em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.6em .7em;text-align:left}
.carmel-table th{background:#f4f6fb}
.carmel-btn{border:0;border-radius:.3em;padding:.4em .9em;color:#fff;cursor:pointer}
.carmel-btn-blue{background:#2e86de}
.carmel-done{color:#16a085;font-weight:bold}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
