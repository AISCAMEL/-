<?php
/**
 * ProLine (LINE) adapter — customer-facing notifications.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_ProLine_Adapter implements Carmel_Channel_Adapter {

	public function key() {
		return 'proline';
	}

	public function is_ready() {
		return '' !== (string) $this->endpoint() && '' !== (string) $this->token();
	}

	public function send( array $recipient, array $message, array $context ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'proline_not_configured', 'ProLine API 未設定' );
		}
		if ( empty( $recipient['line_user_id'] ) ) {
			return new WP_Error( 'proline_no_line_id', '宛先にLINEユーザーIDがありません' );
		}

		$response = wp_remote_post(
			$this->endpoint(),
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'to'      => $recipient['line_user_id'],
						'message' => $message['body'],
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'proline_http_' . $code, 'ProLine 送信失敗 HTTP ' . $code );
		}
		return true;
	}

	private function endpoint() {
		return defined( 'CARMEL_PROLINE_ENDPOINT' ) ? CARMEL_PROLINE_ENDPOINT : get_option( 'carmel_proline_endpoint', '' );
	}

	private function token() {
		return defined( 'CARMEL_PROLINE_TOKEN' ) ? CARMEL_PROLINE_TOKEN : get_option( 'carmel_proline_token', '' );
	}
}
