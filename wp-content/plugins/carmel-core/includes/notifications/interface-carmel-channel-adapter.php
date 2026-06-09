<?php
/**
 * Contract every notification channel adapter must implement.
 * Adding a new channel = adding one class implementing this interface.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

interface Carmel_Channel_Adapter {

	/**
	 * Stable channel key stored in the notification log
	 * (e.g. 'proline', 'lineworks', 'slack', 'mail').
	 *
	 * @return string
	 */
	public function key();

	/**
	 * Whether the adapter is configured and ready to send.
	 * Unconfigured adapters are skipped gracefully (logged as failed/skipped)
	 * so the system runs before credentials are provisioned.
	 *
	 * @return bool
	 */
	public function is_ready();

	/**
	 * Deliver one notification.
	 *
	 * @param array $recipient Normalized recipient: [ id, line_user_id, email, name, roles ].
	 * @param array $message   Resolved message: [ subject, body, template, vars ].
	 * @param array $context   Event context: [ event_id, event_type, deal_id ].
	 * @return true|WP_Error    True on success, WP_Error on failure (triggers fallback/retry).
	 */
	public function send( array $recipient, array $message, array $context );
}
