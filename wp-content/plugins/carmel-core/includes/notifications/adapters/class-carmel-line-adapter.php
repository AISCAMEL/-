<?php
/**
 * LINE 公式アカウント（Messaging API）直送アダプタ — 顧客向け通知。
 *
 * プロライン（ProLine）からの段階移行用。チャネルアクセストークンを設定し、
 * `carmel_line_mode` を 'line' にすると、顧客向けの 'proline' 配信が 'line'
 * （Messaging API push）に置き換わる（フォールバックはメール）。'proline'（既定）の
 * 間は本アダプタは登録されているだけで使われない＝いつでも切替・併用できる。
 *
 * 設定：CARMEL_LINE_CHANNEL_TOKEN / carmel_line_channel_token（チャネルアクセストークン）
 *        CARMEL_LINE_MODE / carmel_line_mode（'proline'|'line'、既定 'proline'）
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_LINE_Adapter implements Carmel_Channel_Adapter {

	const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

	public function key() {
		return 'line';
	}

	public function is_ready() {
		return '' !== (string) $this->token();
	}

	public function send( array $recipient, array $message, array $context ) {
		if ( ! $this->is_ready() ) {
			return new WP_Error( 'line_not_configured', 'LINE Messaging API 未設定' );
		}
		if ( empty( $recipient['line_user_id'] ) ) {
			return new WP_Error( 'line_no_user_id', '宛先にLINEユーザーIDがありません' );
		}

		$subject = isset( $message['subject'] ) ? trim( (string) $message['subject'] ) : '';
		$body    = isset( $message['body'] ) ? trim( (string) $message['body'] ) : '';
		$text    = trim( ( '' !== $subject ? $subject . "\n" : '' ) . $body );
		if ( '' === $text ) {
			$text = 'カーメルからのお知らせ';
		}
		// LINE のテキスト上限（5000）に丸める。
		if ( function_exists( 'mb_substr' ) ) {
			$text = mb_substr( $text, 0, 5000 );
		}

		$response = wp_remote_post(
			self::PUSH_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'to'       => $recipient['line_user_id'],
						'messages' => array(
							array( 'type' => 'text', 'text' => $text ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'line_http_' . $code, 'LINE 送信失敗 HTTP ' . $code . ' ' . wp_remote_retrieve_body( $response ) );
		}
		return true;
	}

	private function token() {
		return defined( 'CARMEL_LINE_CHANNEL_TOKEN' ) ? CARMEL_LINE_CHANNEL_TOKEN : get_option( 'carmel_line_channel_token', '' );
	}

	/* --------------------------------------------------------------------- *
	 * 段階移行：プロライン → LINE の切替
	 * --------------------------------------------------------------------- */

	/** 現在の配信モード（'proline'|'line'）。 */
	public static function mode() {
		$m = defined( 'CARMEL_LINE_MODE' ) ? CARMEL_LINE_MODE : get_option( 'carmel_line_mode', 'proline' );
		return ( 'line' === $m ) ? 'line' : 'proline';
	}

	/**
	 * アダプタ登録（`carmel_notification_adapters` フィルタ）。
	 *
	 * @param array $adapters
	 * @return array
	 */
	public static function register_adapter( $adapters ) {
		$adapters[] = new self();
		return $adapters;
	}

	/**
	 * ルーティング書き換え（`carmel_routing_table` フィルタ）。
	 * モードが 'line' のとき、顧客向け 'proline' 配信を 'line'（fallback=mail）に置換。
	 *
	 * @param array $table
	 * @return array
	 */
	public static function rewrite_routing( $table ) {
		if ( 'line' !== self::mode() ) {
			return $table;
		}
		foreach ( $table as $event => $deliveries ) {
			foreach ( $deliveries as $i => $d ) {
				if ( isset( $d['channel'] ) && 'proline' === $d['channel'] ) {
					$table[ $event ][ $i ]['channel']  = 'line';
					$table[ $event ][ $i ]['fallback'] = isset( $d['fallback'] ) && $d['fallback'] ? $d['fallback'] : 'mail';
				}
			}
		}
		return $table;
	}
}
