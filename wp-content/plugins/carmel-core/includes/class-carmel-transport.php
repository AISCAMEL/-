<?php
/**
 * Transport (陸送) fee calculation via the Google Maps Distance Matrix API.
 *
 * On entering 納車準備 (delivery_prep) the distance from the store address
 * (origin) to the customer address (destination) is fetched and converted to a
 * fee using the HQ-configured rate table. The result is stored on the deal and
 * can be surfaced in the store portal / generated documents.
 *
 * Unconfigured API key → no-op (the rest of the flow continues).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Transport {

	/** @var Carmel_Transport|null */
	private static $instance = null;

	const DM_ENDPOINT = 'https://maps.googleapis.com/maps/api/distancematrix/json';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'carmel_deal_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	private function api_key() {
		return defined( 'CARMEL_MAPS_API_KEY' ) ? CARMEL_MAPS_API_KEY : get_option( 'carmel_maps_api_key', '' );
	}

	public function is_ready() {
		return '' !== (string) $this->api_key();
	}

	/**
	 * Rate table (HQ-configurable). fee = base + per_km × km, clamped to min.
	 *
	 * @return array{base:float,per_km:float,min:float}
	 */
	public static function rates() {
		$defaults = array(
			'base'   => 10000, // 基本料金
			'per_km' => 80,    // 1kmあたり
			'min'    => 10000, // 最低料金
		);
		$opt = get_option( 'carmel_transport_rates', array() );
		$rates = wp_parse_args( is_array( $opt ) ? $opt : array(), $defaults );
		return apply_filters( 'carmel_transport_rates', $rates );
	}

	/**
	 * @param int    $deal_id
	 * @param string $new
	 * @param string $old
	 */
	public function on_status_changed( $deal_id, $new, $old ) {
		if ( 'delivery_prep' === $new ) {
			$this->calculate( $deal_id );
		}
	}

	/**
	 * Calculate and persist the transport fee for a deal.
	 *
	 * @param int $deal_id
	 * @return array|WP_Error [ distance_km, fee, from, to ]
	 */
	public function calculate( $deal_id ) {
		$origin = $this->origin_address( $deal_id );
		$dest   = $this->destination_address( $deal_id );

		if ( '' === $origin || '' === $dest ) {
			return new WP_Error( 'carmel_transport_no_address', '出発地または納車先の住所が不足しています。' );
		}

		$km = $this->fetch_distance_km( $origin, $dest );
		if ( is_wp_error( $km ) ) {
			return $km;
		}

		$fee = $this->fee_for_km( $km );

		update_post_meta( $deal_id, 'transport_from', $origin );
		update_post_meta( $deal_id, 'transport_to', $dest );
		update_post_meta( $deal_id, 'transport_distance_km', $km );
		update_post_meta( $deal_id, 'transport_fee', $fee );

		do_action( 'carmel_transport_calculated', $deal_id, $fee, $km );

		return array(
			'distance_km' => $km,
			'fee'         => $fee,
			'from'        => $origin,
			'to'          => $dest,
		);
	}

	/**
	 * Convert distance (km) to a fee using the rate table.
	 *
	 * @param float $km
	 * @return int
	 */
	public function fee_for_km( $km ) {
		$r   = self::rates();
		$fee = $r['base'] + ( $r['per_km'] * $km );
		$fee = max( $fee, $r['min'] );
		return (int) round( apply_filters( 'carmel_transport_fee', $fee, $km, $r ) );
	}

	/**
	 * Query the Distance Matrix API; returns kilometers.
	 *
	 * @param string $origin
	 * @param string $dest
	 * @return float|WP_Error
	 */
	private function fetch_distance_km( $origin, $dest ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'carmel_maps_not_configured', 'Google Maps API キー未設定' );
		}

		$url = add_query_arg(
			array(
				'origins'      => rawurlencode( $origin ),
				'destinations' => rawurlencode( $dest ),
				'units'        => 'metric',
				'language'     => 'ja',
				'key'          => $this->api_key(),
			),
			self::DM_ENDPOINT
		);

		$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $response ) ) {
			$this->notify_failure( $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$elem = isset( $body['rows'][0]['elements'][0] ) ? $body['rows'][0]['elements'][0] : null;

		if ( ! $elem || 'OK' !== ( isset( $elem['status'] ) ? $elem['status'] : '' ) ) {
			$status = isset( $body['status'] ) ? $body['status'] : 'UNKNOWN';
			$this->notify_failure( '距離取得失敗: ' . $status );
			return new WP_Error( 'carmel_maps_no_route', '距離を取得できませんでした（' . $status . '）' );
		}

		$meters = (int) $elem['distance']['value'];
		return round( $meters / 1000, 1 );
	}

	private function notify_failure( $message ) {
		Carmel_Notifier::notify(
			'system_error',
			array(
				'event_id' => 'maps_fail:' . time(),
				'vars'     => array( 'message' => '陸送費計算（Maps）失敗: ' . $message ),
			)
		);
	}

	/**
	 * Origin = the assigned store's address.
	 *
	 * @param int $deal_id
	 * @return string
	 */
	private function origin_address( $deal_id ) {
		$store_id = (int) get_post_meta( $deal_id, 'store_id', true );
		$origin   = $store_id ? (string) get_post_meta( $store_id, 'store_address', true ) : '';
		return (string) apply_filters( 'carmel_transport_origin', $origin, $deal_id );
	}

	/**
	 * Destination = customer delivery address (deal meta → user meta fallback).
	 *
	 * @param int $deal_id
	 * @return string
	 */
	private function destination_address( $deal_id ) {
		$dest = (string) get_post_meta( $deal_id, 'applicant_address', true );
		if ( '' === $dest ) {
			$customer_id = (int) get_post_meta( $deal_id, 'customer_id', true );
			if ( $customer_id ) {
				$dest = (string) get_user_meta( $customer_id, 'address', true );
			}
		}
		return (string) apply_filters( 'carmel_transport_destination', $dest, $deal_id );
	}
}
