<?php
/**
 * Persists every send attempt to the carmel_notify_log CPT and provides
 * idempotency lookups (event_id × recipient × channel).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Notification_Log {

	const POST_TYPE = 'carmel_notify_log';

	/**
	 * Record a send attempt.
	 *
	 * @param array  $context   [ event_id, event_type, deal_id ].
	 * @param array  $recipient [ id, ... ].
	 * @param string $channel   Channel key.
	 * @param string $status    sent|failed|retrying|skipped.
	 * @param array  $extra     [ is_fallback, retry_count, payload_ref, error ].
	 * @return int Log post ID (0 on failure).
	 */
	public static function record( array $context, array $recipient, $channel, $status, array $extra = array() ) {
		$event_id    = isset( $context['event_id'] ) ? $context['event_id'] : '';
		$event_type  = isset( $context['event_type'] ) ? $context['event_type'] : '';
		$recipient_id = isset( $recipient['id'] ) ? (int) $recipient['id'] : 0;

		$title = sprintf( '[%s] %s → %s (%s)', $event_type, $recipient_id, $channel, $status );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => array(
					'event_id'     => $event_id,
					'event_type'   => $event_type,
					'deal_id'      => isset( $context['deal_id'] ) ? (int) $context['deal_id'] : 0,
					'recipient_id' => $recipient_id,
					'channel'      => $channel,
					'status'       => $status,
					'is_fallback'  => ! empty( $extra['is_fallback'] ) ? 1 : 0,
					'retry_count'  => isset( $extra['retry_count'] ) ? (int) $extra['retry_count'] : 0,
					'payload_ref'  => isset( $extra['payload_ref'] ) ? $extra['payload_ref'] : '',
					'error'        => isset( $extra['error'] ) ? $extra['error'] : '',
					'sent_at'      => current_time( 'mysql' ),
				),
			),
			true
		);

		return is_wp_error( $post_id ) ? 0 : (int) $post_id;
	}

	/**
	 * Idempotency check: has this exact (event, recipient, channel) tuple
	 * already been sent successfully?
	 *
	 * @param string $event_id
	 * @param int    $recipient_id
	 * @param string $channel
	 * @return bool
	 */
	public static function already_sent( $event_id, $recipient_id, $channel ) {
		if ( '' === (string) $event_id ) {
			return false;
		}
		$found = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => 'event_id',
						'value' => $event_id,
					),
					array(
						'key'   => 'recipient_id',
						'value' => (int) $recipient_id,
					),
					array(
						'key'   => 'channel',
						'value' => $channel,
					),
					array(
						'key'   => 'status',
						'value' => 'sent',
					),
				),
			)
		);
		return ! empty( $found );
	}
}
