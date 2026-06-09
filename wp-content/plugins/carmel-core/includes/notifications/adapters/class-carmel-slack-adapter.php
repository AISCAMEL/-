<?php
/**
 * Slack adapter — operations / engineering system monitoring.
 * Uses an Incoming Webhook URL.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Slack_Adapter implements Carmel_Channel_Adapter {

	public function key() {
		return 'slack';
	}

	public function is_ready() {
		return '' !== (string) $this->webhook();
	}

	public function send( array $recipient, array $message, array $context ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'slack_not_configured', 'Slack Webhook 未設定' );
		}

		$prefix = isset( $message['subject'] ) ? '*' . $message['subject'] . "*\n" : '';

		$response = wp_remote_post(
			$this->webhook(),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $prefix . $message['body'] ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'slack_http_' . $code, 'Slack 送信失敗 HTTP ' . $code );
		}
		return true;
	}

	private function webhook() {
		return defined( 'CARMEL_SLACK_WEBHOOK' ) ? CARMEL_SLACK_WEBHOOK : get_option( 'carmel_slack_webhook', '' );
	}
}
