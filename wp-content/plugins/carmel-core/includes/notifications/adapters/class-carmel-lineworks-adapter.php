<?php
/**
 * LINE WORKS adapter — internal (HQ / store staff) business notifications.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_LineWorks_Adapter implements Carmel_Channel_Adapter {

	public function key() {
		return 'lineworks';
	}

	public function is_ready() {
		return '' !== (string) $this->webhook();
	}

	public function send( array $recipient, array $message, array $context ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'lineworks_not_configured', 'LINE WORKS 未設定' );
		}

		$text = ( isset( $message['subject'] ) ? '【' . $message['subject'] . "】\n" : '' ) . $message['body'];

		$response = wp_remote_post(
			$this->webhook(),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'content' => array( 'type' => 'text', 'text' => $text ) ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'lineworks_http_' . $code, 'LINE WORKS 送信失敗 HTTP ' . $code );
		}
		return true;
	}

	private function webhook() {
		return defined( 'CARMEL_LINEWORKS_WEBHOOK' ) ? CARMEL_LINEWORKS_WEBHOOK : get_option( 'carmel_lineworks_webhook', '' );
	}
}
