<?php
/**
 * GAS integration client (WP is the source of truth; GAS is a service).
 *
 * Outbound: WP posts deal data to the GAS Web App (Secret Token auth) to
 * request AI scoring or document (PDF) generation.
 * Write-back: results are stored either from the synchronous response, or
 * asynchronously via the REST callback POST /wp-json/carmel/v1/gas-callback.
 *
 * Auto-wiring:
 *   - new loan application  → request AI score → deal advances to 'scored'
 *   - deal enters doc_prep  → request the application document PDF
 *
 * Unconfigured GAS endpoints no-op gracefully so the rest of the system runs.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_GAS_Client {

	/** @var Carmel_GAS_Client|null */
	private static $instance = null;

	const REST_NAMESPACE = 'carmel/v1';
	const REST_CALLBACK  = '/gas-callback';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		// New loan application → AI scoring (deferred so form submit stays fast).
		add_action( 'carmel_application_created', array( $this, 'on_application_created' ), 10, 3 );
		add_action( 'carmel_async_score', array( $this, 'request_score' ) );

		// Entering 書類準備 → generate the application document.
		add_action( 'carmel_deal_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	private function endpoint() {
		return defined( 'CARMEL_GAS_ENDPOINT' ) ? CARMEL_GAS_ENDPOINT : get_option( 'carmel_gas_endpoint', '' );
	}

	private function token() {
		return defined( 'CARMEL_GAS_TOKEN' ) ? CARMEL_GAS_TOKEN : get_option( 'carmel_gas_token', '' );
	}

	public function is_ready() {
		return '' !== (string) $this->endpoint() && '' !== (string) $this->token();
	}

	/* --------------------------------------------------------------------- *
	 * Auto-wiring
	 * --------------------------------------------------------------------- */

	/**
	 * @param int   $deal_id
	 * @param int   $customer_id
	 * @param array $data
	 */
	public function on_application_created( $deal_id, $customer_id, $data ) {
		if ( 'loan' !== get_post_meta( $deal_id, 'deal_type', true ) ) {
			return;
		}
		// Defer to a single cron event to avoid blocking the form response.
		if ( ! wp_next_scheduled( 'carmel_async_score', array( (int) $deal_id ) ) ) {
			wp_schedule_single_event( time() + 5, 'carmel_async_score', array( (int) $deal_id ) );
		}
	}

	/**
	 * @param int    $deal_id
	 * @param string $new
	 * @param string $old
	 */
	public function on_status_changed( $deal_id, $new, $old ) {
		if ( 'doc_prep' === $new ) {
			$this->request_document( $deal_id, 'application_form' );
		}
	}

	/* --------------------------------------------------------------------- *
	 * Outbound requests
	 * --------------------------------------------------------------------- */

	/**
	 * Ask GAS to AI-score a deal. If the response carries a score it is stored
	 * immediately; otherwise GAS is expected to call back asynchronously.
	 *
	 * @param int $deal_id
	 * @return array|WP_Error
	 */
	public function request_score( $deal_id ) {
		$res = $this->post(
			array(
				'action'  => 'score',
				'deal_id' => (int) $deal_id,
				'data'    => $this->deal_payload( $deal_id ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( isset( $res['score'] ) ) {
			$this->store_score( $deal_id, $res['score'], isset( $res['rank'] ) ? $res['rank'] : '' );
		}
		return $res;
	}

	/**
	 * Ask GAS to generate a document PDF.
	 *
	 * @param int    $deal_id
	 * @param string $doc_type
	 * @return array|WP_Error
	 */
	public function request_document( $deal_id, $doc_type ) {
		$res = $this->post(
			array(
				'action'   => 'generate_document',
				'deal_id'  => (int) $deal_id,
				'doc_type' => sanitize_key( $doc_type ),
				'data'     => $this->deal_payload( $deal_id ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( ! empty( $res['pdf_url'] ) ) {
			$this->store_document( $deal_id, $doc_type, $res['pdf_url'] );
		}
		return $res;
	}

	/**
	 * Low-level POST to the GAS web app with Secret Token auth.
	 *
	 * @param array $body
	 * @return array|WP_Error Decoded JSON array, or WP_Error.
	 */
	private function post( array $body ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'gas_not_configured', 'GAS エンドポイント未設定' );
		}

		$body['token'] = $this->token(); // GAS-side convenience

		$response = wp_remote_post(
			$this->endpoint(),
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'   => 'application/json',
					'X-Carmel-Token' => $this->token(),
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->notify_failure( $body, $response->get_error_message() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$err = new WP_Error( 'gas_http_' . $code, 'GAS HTTP ' . $code );
			$this->notify_failure( $body, 'HTTP ' . $code );
			return $err;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Alert ops via Slack when a GAS call fails.
	 */
	private function notify_failure( array $body, $message ) {
		Carmel_Notifier::notify(
			'system_error',
			array(
				'event_id' => 'gas_fail:' . ( isset( $body['deal_id'] ) ? $body['deal_id'] : '0' ) . ':' . time(),
				'vars'     => array( 'message' => 'GAS連携失敗（' . ( isset( $body['action'] ) ? $body['action'] : '?' ) . '）: ' . $message ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Write-back
	 * --------------------------------------------------------------------- */

	/**
	 * Store an AI score and advance a fresh deal to 'scored'.
	 *
	 * @param int        $deal_id
	 * @param int|float  $score
	 * @param string     $rank
	 */
	public function store_score( $deal_id, $score, $rank = '' ) {
		update_post_meta( $deal_id, 'ai_score', (float) $score );
		if ( '' !== $rank ) {
			update_post_meta( $deal_id, 'score_rank', sanitize_text_field( $rank ) );
		}
		if ( 'provisional' === get_post_meta( $deal_id, 'deal_status', true ) ) {
			Carmel_Deal_Status::change( $deal_id, 'scored', array( 'system' => true ) );
		}
		do_action( 'carmel_score_stored', $deal_id, $score, $rank );
	}

	/**
	 * Store a generated document as a carmel_document linked to the deal.
	 *
	 * @param int    $deal_id
	 * @param string $doc_type
	 * @param string $pdf_url
	 * @return int Document post ID.
	 */
	public function store_document( $deal_id, $doc_type, $pdf_url ) {
		$doc_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_document',
				'post_status' => 'publish',
				'post_title'  => sprintf( '#%d %s', (int) $deal_id, $doc_type ),
				'meta_input'  => array(
					'deal_id'      => (int) $deal_id,
					'doc_type'     => sanitize_key( $doc_type ),
					'pdf_url'      => esc_url_raw( $pdf_url ),
					'generated_at' => current_time( 'mysql' ),
				),
			),
			true
		);
		if ( ! is_wp_error( $doc_id ) ) {
			do_action( 'carmel_document_stored', $deal_id, $doc_id, $doc_type );
			return (int) $doc_id;
		}
		return 0;
	}

	/* --------------------------------------------------------------------- *
	 * Inbound async callback
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

	/**
	 * Authenticate the GAS callback by Secret Token.
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
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
	 * Handle an async write-back from GAS.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_callback( $request ) {
		$type    = sanitize_key( $request->get_param( 'type' ) );
		$deal_id = (int) $request->get_param( 'deal_id' );

		if ( ! $deal_id || 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return new WP_Error( 'gas_bad_deal', 'deal_id が不正です', array( 'status' => 400 ) );
		}

		switch ( $type ) {
			case 'score':
				$this->store_score( $deal_id, $request->get_param( 'score' ), (string) $request->get_param( 'rank' ) );
				break;

			case 'document':
				$pdf = $request->get_param( 'pdf_url' );
				if ( ! $pdf ) {
					return new WP_Error( 'gas_no_pdf', 'pdf_url がありません', array( 'status' => 400 ) );
				}
				$this->store_document( $deal_id, (string) $request->get_param( 'doc_type' ), $pdf );
				break;

			default:
				return new WP_Error( 'gas_bad_type', 'type が不正です', array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* --------------------------------------------------------------------- *
	 * Payload
	 * --------------------------------------------------------------------- */

	/**
	 * Build the deal payload sent to GAS (filterable).
	 *
	 * @param int $deal_id
	 * @return array
	 */
	private function deal_payload( $deal_id ) {
		$keys = array(
			'deal_type', 'deal_status', 'customer_id', 'store_id', 'vehicle_id',
			'applicant_name', 'applicant_email', 'applicant_phone', 'application_note',
		);
		$data = array( 'deal_id' => (int) $deal_id, 'title' => get_the_title( $deal_id ) );
		foreach ( $keys as $k ) {
			$data[ $k ] = get_post_meta( $deal_id, $k, true );
		}
		return apply_filters( 'carmel_gas_deal_payload', $data, $deal_id );
	}
}
