<?php
/**
 * Membership (会費) management.
 *
 * Tracks each store's membership on the carmel_store record
 * (membership_status / membership_next_billing / membership_plan /
 * membership_fee — editable via ACF). Supports three billing modes so the
 * §13-#1 decision stays flexible:
 *
 *   (a) WooCommerce Subscriptions  → status synced from subscription hooks
 *   (b) 都度 / 銀行振込（手動）      → HQ edits the store's membership fields
 *   (c) いずれの場合も              → Cron sends renewal reminders & flags expiry
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Membership {

	/** @var Carmel_Membership|null */
	private static $instance = null;

	/** Days-before-billing on which to remind. */
	const REMIND_DAYS = array( 7, 1 );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		// WooCommerce Subscriptions lifecycle (no-op if not installed).
		add_action( 'woocommerce_subscription_status_active', array( $this, 'on_subscription_active' ) );
		add_action( 'woocommerce_subscription_status_expired', array( $this, 'on_subscription_ended' ) );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'on_subscription_ended' ) );
	}

	/* --------------------------------------------------------------------- *
	 * State
	 * --------------------------------------------------------------------- */

	/**
	 * Set a store's membership status (and optionally next billing date).
	 *
	 * @param int         $store_id
	 * @param string      $status active|grace|expired|none
	 * @param string|null $next_billing Y-m-d
	 */
	public static function set_status( $store_id, $status, $next_billing = null ) {
		$store_id = (int) $store_id;
		if ( 'carmel_store' !== get_post_type( $store_id ) ) {
			return;
		}
		update_post_meta( $store_id, 'membership_status', sanitize_key( $status ) );
		if ( null !== $next_billing ) {
			update_post_meta( $store_id, 'membership_next_billing', sanitize_text_field( $next_billing ) );
		}
		do_action( 'carmel_membership_status_changed', $store_id, $status );
	}

	/* --------------------------------------------------------------------- *
	 * WooCommerce Subscriptions bridge
	 * --------------------------------------------------------------------- */

	public function on_subscription_active( $subscription ) {
		$store_id = $this->store_from_subscription( $subscription );
		if ( ! $store_id ) {
			return;
		}
		$next = method_exists( $subscription, 'get_date' ) ? $subscription->get_date( 'next_payment' ) : null;
		self::set_status( $store_id, 'active', $next ? substr( $next, 0, 10 ) : null );
	}

	public function on_subscription_ended( $subscription ) {
		$store_id = $this->store_from_subscription( $subscription );
		if ( ! $store_id ) {
			return;
		}
		self::set_status( $store_id, 'expired' );
		$this->notify_expired( $store_id );
	}

	/**
	 * Resolve the store from a subscription: explicit meta, else the
	 * subscriber's user store_id.
	 *
	 * @param mixed $subscription
	 * @return int
	 */
	private function store_from_subscription( $subscription ) {
		if ( ! is_object( $subscription ) ) {
			return 0;
		}
		if ( method_exists( $subscription, 'get_meta' ) ) {
			$sid = (int) $subscription->get_meta( 'carmel_store_id' );
			if ( $sid ) {
				return $sid;
			}
		}
		if ( method_exists( $subscription, 'get_user_id' ) ) {
			return (int) get_user_meta( $subscription->get_user_id(), 'store_id', true );
		}
		return 0;
	}

	/* --------------------------------------------------------------------- *
	 * Cron (called from Carmel_Cron::run_daily)
	 * --------------------------------------------------------------------- */

	/**
	 * Send renewal reminders and flag expired memberships.
	 */
	public function process_renewals() {
		$stores = get_posts(
			array(
				'post_type'      => 'carmel_store',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_query'     => array(
					array( 'key' => 'membership_next_billing', 'compare' => 'EXISTS' ),
				),
			)
		);

		foreach ( $stores as $store ) {
			$next = get_post_meta( $store->ID, 'membership_next_billing', true );
			$days = $this->days_until( $next );
			if ( null === $days ) {
				continue;
			}

			if ( in_array( $days, self::REMIND_DAYS, true ) ) {
				Carmel_Notifier::notify(
					'membership_renewal',
					array(
						'event_id' => 'membership_renewal:' . $store->ID . ':' . $days,
						'store_id' => $store->ID,
						'vars'     => array( 'store' => get_the_title( $store->ID ), 'due' => $next ),
					)
				);
			}

			// Past due + still marked active → expire and alert.
			if ( $days < 0 && 'active' === get_post_meta( $store->ID, 'membership_status', true ) ) {
				self::set_status( $store->ID, 'expired' );
				$this->notify_expired( $store->ID );
			}
		}
	}

	private function notify_expired( $store_id ) {
		Carmel_Notifier::notify(
			'membership_expired',
			array(
				'event_id' => 'membership_expired:' . $store_id . ':' . gmdate( 'Ym' ),
				'store_id' => $store_id,
				'vars'     => array( 'store' => get_the_title( $store_id ) ),
			)
		);
	}

	private function days_until( $date ) {
		$ts = strtotime( (string) $date );
		if ( false === $ts ) {
			return null;
		}
		return (int) floor( ( $ts - strtotime( current_time( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
	}
}
