<?php
/**
 * Email adapter — formal notifications, document delivery, and the
 * automatic fallback when a customer's LINE delivery is unavailable.
 * Always "ready" (wp_mail is built in).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Mail_Adapter implements Carmel_Channel_Adapter {

	public function key() {
		return 'mail';
	}

	public function is_ready() {
		return true;
	}

	public function send( array $recipient, array $message, array $context ) {
		if ( empty( $recipient['email'] ) || ! is_email( $recipient['email'] ) ) {
			return new WP_Error( 'mail_no_address', '宛先メールアドレスが無効です' );
		}

		$subject = isset( $message['subject'] ) && '' !== $message['subject']
			? $message['subject']
			: 'カーメルからのお知らせ';

		$sent = wp_mail( $recipient['email'], $subject, $message['body'] );

		return $sent ? true : new WP_Error( 'mail_failed', 'wp_mail 送信失敗' );
	}
}
