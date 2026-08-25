<?php
/**
 * Square / WooCommerce payment integration.
 *
 * Payments are taken via Square for WooCommerce. WooCommerce orders carry the
 * deal id + payment type (`carmel_deal_id`, `carmel_payment_type` order meta);
 * on completion the deal's payment status is updated and the customer is
 * notified. A separate Square webhook endpoint (HMAC-SHA256 verified) handles
 * direct payment events.
 *
 * Car body price is NOT handled here (loans go through the credit company) —
 * only: 申込金/手付金・保証プラン・オプション・加盟店販促購入費・会費 (§8.2).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Payments {

	/** @var Carmel_Payments|null */
	private static $instance = null;

	const REST_NAMESPACE = 'carmel/v1';
	const REST_WEBHOOK   = '/square-webhook';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Supported payment types => label. */
	public static function payment_types() {
		return array(
			'deposit'    => '申込金・手付金',
			'warranty'   => '保証プラン',
			'option'     => 'オプション',
			'promo'      => '加盟店販促購入費',
			'membership' => '会費',
		);
	}

	public function register_hooks() {
		// Square webhook receiver.
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		// WooCommerce order lifecycle (harmless if WC is absent).
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_order_completed' ) );
		add_action( 'woocommerce_order_status_failed', array( $this, 'on_order_failed' ) );
	}

	private function signature_key() {
		return defined( 'CARMEL_SQUARE_SIGNATURE_KEY' ) ? CARMEL_SQUARE_SIGNATURE_KEY : get_option( 'carmel_square_signature_key', '' );
	}

	/**
	 * The exact notification URL registered in the Square dashboard
	 * (used in the signature). Defaults to this route's REST URL.
	 *
	 * @return string
	 */
	private function webhook_url() {
		$configured = defined( 'CARMEL_SQUARE_WEBHOOK_URL' ) ? CARMEL_SQUARE_WEBHOOK_URL : get_option( 'carmel_square_webhook_url', '' );
		return $configured ? $configured : rest_url( self::REST_NAMESPACE . self::REST_WEBHOOK );
	}

	/* --------------------------------------------------------------------- *
	 * WooCommerce
	 * --------------------------------------------------------------------- */

	/**
	 * @param int $order_id
	 */
	public function on_order_completed( $order_id ) {
		$info = $this->order_deal_info( $order_id );
		if ( ! $info ) {
			return;
		}
		$this->record_payment( $info['deal_id'], $info['type'], $info['amount'], 'paid', 'woocommerce' );
		Carmel_Notifier::notify(
			'payment_completed',
			array(
				'event_id' => 'payment_completed:' . $order_id,
				'deal_id'  => $info['deal_id'],
				'vars'     => array(
					'amount' => number_format( $info['amount'] ),
					'type'   => $this->type_label( $info['type'] ),
				),
			)
		);
	}

	/**
	 * @param int $order_id
	 */
	public function on_order_failed( $order_id ) {
		$info = $this->order_deal_info( $order_id );
		if ( ! $info ) {
			return;
		}
		$this->record_payment( $info['deal_id'], $info['type'], $info['amount'], 'failed', 'woocommerce' );
		Carmel_Notifier::notify(
			'payment_failed',
			array(
				'event_id' => 'payment_failed:' . $order_id . ':' . time(),
				'deal_id'  => $info['deal_id'],
				'vars'     => array(
					'amount' => number_format( $info['amount'] ),
					'type'   => $this->type_label( $info['type'] ),
				),
			)
		);
	}

	/**
	 * Extract deal/type/amount from a WooCommerce order.
	 *
	 * @param int $order_id
	 * @return array|null [ deal_id, type, amount ]
	 */
	private function order_deal_info( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return null;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}
		$deal_id = (int) $order->get_meta( 'carmel_deal_id' );
		$type    = sanitize_key( $order->get_meta( 'carmel_payment_type' ) );
		if ( ! $deal_id || ! isset( self::payment_types()[ $type ] ) ) {
			return null;
		}
		return array(
			'deal_id' => $deal_id,
			'type'    => $type,
			'amount'  => (float) $order->get_total(),
		);
	}

	/* --------------------------------------------------------------------- *
	 * Payment recording
	 * --------------------------------------------------------------------- */

	/**
	 * Persist a payment outcome on the deal (status meta + append to log).
	 *
	 * @param int    $deal_id
	 * @param string $type
	 * @param float  $amount
	 * @param string $status paid|failed|refunded
	 * @param string $source woocommerce|square
	 */
	public function record_payment( $deal_id, $type, $amount, $status, $source ) {
		update_post_meta( $deal_id, 'payment_' . $type . '_status', $status );
		update_post_meta( $deal_id, 'payment_' . $type . '_amount', (float) $amount );

		$log = get_post_meta( $deal_id, '_carmel_payments', true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'type'   => $type,
			'amount' => (float) $amount,
			'status' => $status,
			'source' => $source,
			'time'   => current_time( 'mysql' ),
		);
		update_post_meta( $deal_id, '_carmel_payments', $log );

		do_action( 'carmel_payment_recorded', $deal_id, $type, $amount, $status );
	}

	/* --------------------------------------------------------------------- *
	 * Square webhook
	 * --------------------------------------------------------------------- */

	public function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_WEBHOOK,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_signature' ),
			)
		);
	}

	/**
	 * Verify Square's HMAC-SHA256 signature:
	 *   base64( HMAC_SHA256( notification_url + raw_body, signature_key ) ).
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function verify_signature( $request ) {
		$key = $this->signature_key();
		if ( '' === (string) $key ) {
			return false;
		}
		$provided = $request->get_header( 'x-square-hmacsha256-signature' );
		if ( ! $provided ) {
			return false;
		}
		$payload  = $this->webhook_url() . $request->get_body();
		$expected = base64_encode( hash_hmac( 'sha256', $payload, $key, true ) );

		return hash_equals( $expected, (string) $provided );
	}

	/**
	 * Handle a verified Square webhook. Maps a completed payment back to a deal
	 * via the payment's reference_id (which the checkout sets to the deal id)
	 * and the note/metadata payment type.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$body = $request->get_json_params();
		$type = isset( $body['type'] ) ? (string) $body['type'] : '';

		$payment = isset( $body['data']['object']['payment'] ) ? $body['data']['object']['payment'] : null;

		if ( $payment && in_array( $type, array( 'payment.created', 'payment.updated' ), true ) ) {
			$status   = isset( $payment['status'] ) ? $payment['status'] : '';
			$ref      = isset( $payment['reference_id'] ) ? (int) $payment['reference_id'] : 0;
			$currency = isset( $payment['amount_money']['currency'] ) ? $payment['amount_money']['currency'] : 'JPY';
			$raw      = isset( $payment['amount_money']['amount'] ) ? (int) $payment['amount_money']['amount'] : 0;
			// JPY's minor unit is the yen itself; other currencies use 1/100.
			$amount   = ( 'JPY' === $currency ) ? $raw : ( $raw / 100 );
			$pay_type = isset( $payment['note'] ) ? sanitize_key( $payment['note'] ) : 'deposit';

			if ( $ref && 'carmel_deal' === get_post_type( $ref ) ) {
				$mapped = ( 'COMPLETED' === $status ) ? 'paid' : ( 'FAILED' === $status ? 'failed' : 'pending' );
				$this->record_payment( $ref, isset( self::payment_types()[ $pay_type ] ) ? $pay_type : 'deposit', $amount, $mapped, 'square' );

				if ( 'paid' === $mapped ) {
					Carmel_Notifier::notify(
						'payment_completed',
						array(
							'event_id' => 'square_payment:' . ( isset( $payment['id'] ) ? $payment['id'] : $ref ),
							'deal_id'  => $ref,
							'vars'     => array( 'amount' => number_format( $amount ), 'type' => $this->type_label( $pay_type ) ),
						)
					);
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function type_label( $type ) {
		$types = self::payment_types();
		return isset( $types[ $type ] ) ? $types[ $type ] : $type;
	}
}
