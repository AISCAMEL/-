<?php
/**
 * Notification Orchestrator.
 *
 * Single entry point for all outbound notifications. Features (CPT status
 * changes, WP-Cron jobs, payment webhooks, …) fire a single event; this class
 * consults the routing table, resolves recipients per audience, dispatches
 * through channel adapters, applies the LINE→mail fallback, deduplicates, and
 * logs every attempt.
 *
 * Usage:
 *   Carmel_Notifier::notify( 'screening_result', array(
 *       'event_id' => 'screening:123',
 *       'deal_id'  => 123,
 *       'vars'     => array( 'name' => '山田', 'result' => 'OK' ),
 *   ) );
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Notifier {

	/** @var Carmel_Notifier|null */
	private static $instance = null;

	/** @var array<string,Carmel_Channel_Adapter> */
	private $adapters = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		// Generic event entry point usable from anywhere via do_action().
		add_action( 'carmel_event', array( $this, 'handle_event' ), 10, 2 );
	}

	/**
	 * Lazily build the adapter registry (filterable to add channels).
	 *
	 * @return array<string,Carmel_Channel_Adapter>
	 */
	private function adapters() {
		if ( empty( $this->adapters ) ) {
			$adapters = array(
				new Carmel_ProLine_Adapter(),
				new Carmel_LineWorks_Adapter(),
				new Carmel_Slack_Adapter(),
				new Carmel_Mail_Adapter(),
			);
			$adapters = apply_filters( 'carmel_notification_adapters', $adapters );
			foreach ( $adapters as $adapter ) {
				if ( $adapter instanceof Carmel_Channel_Adapter ) {
					$this->adapters[ $adapter->key() ] = $adapter;
				}
			}
		}
		return $this->adapters;
	}

	/**
	 * Routing table (event => deliveries).
	 * Each delivery: [ audience, channel, fallback ].
	 * audience: customer | store | hq | system.
	 *
	 * @return array<string,array<int,array>>
	 */
	public static function routing_table() {
		$cust = function ( $channel, $fallback = null ) {
			return array( 'audience' => 'customer', 'channel' => $channel, 'fallback' => $fallback );
		};
		$d = function ( $audience, $channel ) {
			return array( 'audience' => $audience, 'channel' => $channel, 'fallback' => null );
		};

		$table = array(
			'application_received' => array( $cust( 'proline' ), $cust( 'mail' ) ),
			'ai_score_done'        => array( $d( 'hq', 'lineworks' ) ),
			'screening_result'     => array( $cust( 'proline', 'mail' ), $d( 'store', 'lineworks' ) ),
			'store_assigned'       => array( $d( 'store', 'lineworks' ), $d( 'store', 'mail' ) ),
			'contract_sign_request'=> array( $cust( 'proline' ), $cust( 'mail' ) ),
			'delivery_date_fixed'  => array( $cust( 'proline' ), $d( 'store', 'lineworks' ) ),
			'payment_completed'    => array( $cust( 'proline', 'mail' ) ),
			'payment_failed'       => array( $d( 'store', 'lineworks' ), $d( 'system', 'slack' ), $cust( 'mail' ) ),
			'repayment_reminder'   => array( $cust( 'proline', 'mail' ) ),
			'delinquency'          => array( $cust( 'proline', 'mail' ), $d( 'hq', 'lineworks' ), $d( 'system', 'slack' ) ),
			'inspection_notice'    => array( $cust( 'proline', 'mail' ), $d( 'store', 'lineworks' ) ),
			'insurance_notice'     => array( $cust( 'proline', 'mail' ), $d( 'store', 'lineworks' ) ),
			'maintenance_notice'   => array( $cust( 'proline', 'mail' ) ),
			'system_error'         => array( $d( 'system', 'slack' ) ),
		);

		return apply_filters( 'carmel_routing_table', $table );
	}

	/**
	 * do_action('carmel_event', $type, $context) bridge.
	 *
	 * @param string $event_type
	 * @param array  $context
	 */
	public function handle_event( $event_type, $context = array() ) {
		self::notify( $event_type, (array) $context );
	}

	/**
	 * Main dispatch routine.
	 *
	 * @param string $event_type
	 * @param array  $context [ event_id, deal_id, vars, recipient_id ].
	 * @return void
	 */
	public static function notify( $event_type, array $context = array() ) {
		$self  = self::instance();
		$table = self::routing_table();

		if ( ! isset( $table[ $event_type ] ) ) {
			return;
		}

		// Stable idempotency key (daily granularity if not provided).
		if ( empty( $context['event_id'] ) ) {
			$context['event_id'] = $event_type . ':' . ( isset( $context['deal_id'] ) ? (int) $context['deal_id'] : 0 ) . ':' . gmdate( 'Ymd' );
		}
		$context['event_type'] = $event_type;

		foreach ( $table[ $event_type ] as $delivery ) {
			$recipients = $self->resolve_recipients( $delivery['audience'], $context );
			$message    = $self->build_message( $event_type, $context );

			foreach ( $recipients as $recipient ) {
				$self->dispatch_one( $delivery['channel'], $delivery['fallback'], $recipient, $message, $context );
			}
		}
	}

	/**
	 * Send to one recipient on one channel, with dedup + fallback + logging.
	 */
	private function dispatch_one( $channel, $fallback, array $recipient, array $message, array $context ) {
		$adapters = $this->adapters();
		if ( ! isset( $adapters[ $channel ] ) ) {
			return;
		}

		// Idempotency: skip if already delivered successfully.
		if ( Carmel_Notification_Log::already_sent( $context['event_id'], $recipient['id'], $channel ) ) {
			return;
		}

		$result = $adapters[ $channel ]->send( $recipient, $message, $context );

		if ( true === $result ) {
			Carmel_Notification_Log::record( $context, $recipient, $channel, 'sent' );
			return;
		}

		// Failed (incl. not-configured): log it.
		$error = is_wp_error( $result ) ? $result->get_error_message() : 'unknown';
		Carmel_Notification_Log::record( $context, $recipient, $channel, 'failed', array( 'error' => $error ) );

		// Fallback (e.g. customer LINE → mail).
		if ( $fallback && isset( $adapters[ $fallback ] ) && $fallback !== $channel ) {
			if ( Carmel_Notification_Log::already_sent( $context['event_id'], $recipient['id'], $fallback ) ) {
				return;
			}
			$fb = $adapters[ $fallback ]->send( $recipient, $message, $context );
			$status = ( true === $fb ) ? 'sent' : 'failed';
			Carmel_Notification_Log::record(
				$context,
				$recipient,
				$fallback,
				$status,
				array(
					'is_fallback' => true,
					'error'       => is_wp_error( $fb ) ? $fb->get_error_message() : '',
				)
			);
		}
	}

	/**
	 * Resolve normalized recipients for an audience.
	 *
	 * @param string $audience customer|store|hq|system
	 * @param array  $context
	 * @return array<int,array>
	 */
	private function resolve_recipients( $audience, array $context ) {
		$deal_id  = isset( $context['deal_id'] ) ? (int) $context['deal_id'] : 0;
		$out      = array();

		switch ( $audience ) {
			case 'customer':
				$uid = isset( $context['recipient_id'] ) ? (int) $context['recipient_id'] : 0;
				if ( ! $uid && $deal_id ) {
					$uid = (int) get_post_meta( $deal_id, 'customer_id', true );
				}
				if ( $uid ) {
					$out[] = $this->normalize_user( $uid );
				}
				break;

			case 'store':
				$store_id = $deal_id ? (int) get_post_meta( $deal_id, 'store_id', true ) : 0;
				if ( $store_id ) {
					$users = get_users(
						array(
							'role__in'   => array( 'store_owner', 'store_staff' ),
							'meta_key'   => 'store_id',
							'meta_value' => $store_id,
						)
					);
					foreach ( $users as $u ) {
						$out[] = $this->normalize_user( $u->ID );
					}
				}
				break;

			case 'hq':
				foreach ( get_users( array( 'role' => 'hq_admin' ) ) as $u ) {
					$out[] = $this->normalize_user( $u->ID );
				}
				break;

			case 'system':
				// Slack has no per-user address; one pseudo recipient.
				$out[] = array(
					'id'           => 0,
					'line_user_id' => '',
					'email'        => '',
					'name'         => 'system',
					'roles'        => array(),
				);
				break;
		}

		return apply_filters( 'carmel_notification_recipients', $out, $audience, $context );
	}

	/**
	 * Normalize a WP user into the recipient shape adapters expect.
	 *
	 * @param int $user_id
	 * @return array
	 */
	private function normalize_user( $user_id ) {
		$user = get_userdata( $user_id );
		return array(
			'id'           => (int) $user_id,
			'line_user_id' => $user ? (string) get_user_meta( $user_id, 'line_user_id', true ) : '',
			'email'        => $user ? $user->user_email : '',
			'name'         => $user ? $user->display_name : '',
			'roles'        => $user ? (array) $user->roles : array(),
		);
	}

	/**
	 * Build the message body for an event. Templates are filterable so the
	 * /hq template manager can override them later.
	 *
	 * @param string $event_type
	 * @param array  $context
	 * @return array [ subject, body, template, vars ]
	 */
	private function build_message( $event_type, array $context ) {
		$vars = isset( $context['vars'] ) && is_array( $context['vars'] ) ? $context['vars'] : array();

		$defaults = array(
			'application_received'  => array( '申込を受け付けました', "{name} 様\nお申込みを受け付けました。マイページからお手続き状況をご確認いただけます。" ),
			'screening_result'      => array( '審査結果のお知らせ', "{name} 様\n審査結果：{result}\n詳細はマイページをご確認ください。" ),
			'store_assigned'        => array( '新規案件アサイン', "案件 #{deal_id} が割り当てられました。" ),
			'contract_sign_request' => array( '契約書ご署名のお願い', "{name} 様\nご契約手続きの準備が整いました。マイページよりご署名ください。" ),
			'delivery_date_fixed'   => array( '納車日確定のお知らせ', "{name} 様\n納車日が確定しました：{delivery_date}" ),
			'payment_completed'     => array( 'お支払い完了', "{name} 様\nお支払いを確認しました。ありがとうございます。" ),
			'payment_failed'        => array( '決済失敗', "案件 #{deal_id} の決済に失敗しました。確認してください。" ),
			'repayment_reminder'    => array( 'お支払いのご案内', "{name} 様\nお支払い期日が近づいています（{due_date}）。" ),
			'delinquency'           => array( 'お支払い遅延のご連絡', "{name} 様\nお支払いの確認が取れておりません。ご確認をお願いします。" ),
			'inspection_notice'     => array( '車検のご案内', "{name} 様\n車検満了日が近づいています（{expiry_date}）。ご予約はマイページから。" ),
			'insurance_notice'      => array( '保険更新のご案内', "{name} 様\n保険満了日が近づいています（{end_date}）。" ),
			'maintenance_notice'    => array( '定期点検のご案内', "{name} 様\n定期点検の時期です。" ),
			'system_error'          => array( 'システム通知', '{message}' ),
		);

		$subject = isset( $defaults[ $event_type ][0] ) ? $defaults[ $event_type ][0] : 'カーメルからのお知らせ';
		$body    = isset( $defaults[ $event_type ][1] ) ? $defaults[ $event_type ][1] : '';

		// Replace {placeholders} from vars (+ deal_id convenience).
		$vars['deal_id'] = isset( $context['deal_id'] ) ? (int) $context['deal_id'] : '';
		foreach ( $vars as $k => $v ) {
			$subject = str_replace( '{' . $k . '}', (string) $v, $subject );
			$body    = str_replace( '{' . $k . '}', (string) $v, $body );
		}

		$message = array(
			'subject'  => $subject,
			'body'     => $body,
			'template' => $event_type,
			'vars'     => $vars,
		);

		return apply_filters( 'carmel_notification_message', $message, $event_type, $context );
	}
}
