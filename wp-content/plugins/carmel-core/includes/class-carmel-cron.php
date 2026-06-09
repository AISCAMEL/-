<?php
/**
 * Scheduled jobs (WP-Cron).
 *
 * Daily: repayment reminders, delinquency detection + interest calc,
 *        vehicle inspection (車検) and insurance (保険) expiry alerts.
 * Weekly: a summary report to ops (Slack) + HQ (mail).
 *
 * All outbound messaging goes through Carmel_Notifier so dedup / fallback /
 * logging apply uniformly. Per §9.4 these jobs centralize the recurring
 * notifications that used to live in GAS triggers.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Cron {

	/** @var Carmel_Cron|null */
	private static $instance = null;

	const DAILY_HOOK  = 'carmel_daily_cron';
	const WEEKLY_HOOK = 'carmel_weekly_cron';

	/** Days-before-due on which to remind. */
	const REMIND_DAYS = array( 3, 1, 0 );

	/** Delinquency day-counts on which to escalate. */
	const DELINQUENT_DAYS = array( 1, 5, 14 );

	/** Inspection alert lead times (days). */
	const INSPECTION_DAYS = array( 90, 60, 30 );

	/** Insurance alert lead times (days). */
	const INSURANCE_DAYS = array( 90, 30 );

	/** Annual delinquency interest rate (14.6%). */
	const DELAY_RATE = 0.146;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
		add_action( self::DAILY_HOOK, array( $this, 'run_daily' ) );
		add_action( self::WEEKLY_HOOK, array( $this, 'run_weekly' ) );
		// Self-heal: ensure events are scheduled even if activation missed it.
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Register a "weekly" recurrence if WordPress doesn't define one.
	 *
	 * @param array $schedules
	 * @return array
	 */
	public function add_weekly_schedule( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( '毎週', 'carmel-core' ),
			);
		}
		return $schedules;
	}

	/**
	 * Schedule both events. Called on activation.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::DAILY_HOOK ) ) {
			wp_schedule_event( self::next_local_time( '08:00' ), 'daily', self::DAILY_HOOK );
		}
		if ( ! wp_next_scheduled( self::WEEKLY_HOOK ) ) {
			wp_schedule_event( self::next_local_time( '09:00' ), 'weekly', self::WEEKLY_HOOK );
		}
	}

	/**
	 * Clear both events. Called on deactivation.
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::DAILY_HOOK );
		wp_clear_scheduled_hook( self::WEEKLY_HOOK );
	}

	public function ensure_scheduled() {
		self::schedule();
	}

	/**
	 * Next occurrence of HH:MM in site local time, as a UTC timestamp.
	 *
	 * @param string $hhmm
	 * @return int
	 */
	private static function next_local_time( $hhmm ) {
		$now    = current_time( 'timestamp' ); // local
		$target = strtotime( current_time( 'Y-m-d' ) . ' ' . $hhmm . ':00' );
		if ( $target <= $now ) {
			$target += DAY_IN_SECONDS;
		}
		// Convert local target to UTC for wp_schedule_event.
		return $target - ( (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/* --------------------------------------------------------------------- *
	 * Daily job
	 * --------------------------------------------------------------------- */

	public function run_daily() {
		$this->process_repayments();
		$this->process_inspections();
		$this->process_insurance();
		do_action( 'carmel_daily_cron_done' );
	}

	/**
	 * Repayment reminders + delinquency escalation.
	 */
	private function process_repayments() {
		$repayments = get_posts(
			array(
				'post_type'      => 'carmel_repayment',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_query'     => array(
					array(
						'key'     => 'paid_flag',
						'value'   => '1',
						'compare' => '!=',
					),
				),
			)
		);

		foreach ( $repayments as $r ) {
			$due     = get_post_meta( $r->ID, 'due_date', true );
			$deal_id = (int) get_post_meta( $r->ID, 'deal_id', true );
			$amount  = (float) get_post_meta( $r->ID, 'amount', true );
			if ( ! $due ) {
				continue;
			}
			$days = $this->days_until( $due ); // >0 future, <0 overdue

			if ( null === $days ) {
				continue;
			}

			// Upcoming reminders.
			if ( in_array( $days, self::REMIND_DAYS, true ) ) {
				Carmel_Notifier::notify(
					'repayment_reminder',
					array(
						'event_id' => 'repayment_reminder:' . $r->ID . ':' . gmdate( 'Ymd' ),
						'deal_id'  => $deal_id,
						'vars'     => array(
							'due_date' => $due,
							'amount'   => number_format( $amount ),
						),
					)
				);
			}

			// Delinquency.
			if ( $days < 0 ) {
				$overdue = abs( $days );
				$interest = round( $amount * self::DELAY_RATE / 365 * $overdue );
				update_post_meta( $r->ID, 'delay_days', $overdue );
				update_post_meta( $r->ID, 'delay_interest', $interest );

				if ( in_array( $overdue, self::DELINQUENT_DAYS, true ) ) {
					Carmel_Notifier::notify(
						'delinquency',
						array(
							'event_id' => 'delinquency:' . $r->ID . ':' . $overdue,
							'deal_id'  => $deal_id,
							'vars'     => array(
								'due_date'       => $due,
								'amount'         => number_format( $amount ),
								'delay_days'     => $overdue,
								'delay_interest' => number_format( $interest ),
							),
						)
					);
				}
			}
		}
	}

	/**
	 * Vehicle inspection (車検) expiry alerts.
	 */
	private function process_inspections() {
		$vehicles = get_posts(
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_query'     => array(
					array(
						'key'     => 'inspection_expiry',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $vehicles as $v ) {
			$expiry = get_post_meta( $v->ID, 'inspection_expiry', true );
			$days   = $this->days_until( $expiry );
			if ( null === $days || ! in_array( $days, self::INSPECTION_DAYS, true ) ) {
				continue;
			}
			$deal_id = (int) get_post_meta( $v->ID, 'linked_deal_id', true );
			Carmel_Notifier::notify(
				'inspection_notice',
				array(
					'event_id' => 'inspection_notice:' . $v->ID . ':' . $days,
					'deal_id'  => $deal_id,
					'vars'     => array(
						'expiry_date' => $expiry,
						'days'        => $days,
					),
				)
			);
		}
	}

	/**
	 * Insurance (保険) renewal alerts.
	 */
	private function process_insurance() {
		$policies = get_posts(
			array(
				'post_type'      => 'carmel_insurance',
				'post_status'    => 'publish',
				'posts_per_page' => 500,
				'meta_query'     => array(
					array(
						'key'     => 'end_date',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $policies as $p ) {
			$end  = get_post_meta( $p->ID, 'end_date', true );
			$days = $this->days_until( $end );
			if ( null === $days || ! in_array( $days, self::INSURANCE_DAYS, true ) ) {
				continue;
			}
			$deal_id = (int) get_post_meta( $p->ID, 'deal_id', true );
			Carmel_Notifier::notify(
				'insurance_notice',
				array(
					'event_id' => 'insurance_notice:' . $p->ID . ':' . $days,
					'deal_id'  => $deal_id,
					'vars'     => array(
						'end_date' => $end,
						'days'     => $days,
					),
				)
			);
		}
	}

	/* --------------------------------------------------------------------- *
	 * Weekly job
	 * --------------------------------------------------------------------- */

	public function run_weekly() {
		$counts = $this->count_deals_by_type();
		$report = sprintf(
			"今週のサマリー（%s 時点）\n・案件総数: %d\n・ローン: %d / 買取: %d / リース: %d",
			current_time( 'Y-m-d' ),
			$counts['total'],
			$counts['loan'],
			$counts['buyback'],
			$counts['lease']
		);

		Carmel_Notifier::notify(
			'weekly_report',
			array(
				'event_id' => 'weekly_report:' . gmdate( 'oW' ), // ISO year-week
				'vars'     => array( 'report' => $report ),
			)
		);
		do_action( 'carmel_weekly_cron_done' );
	}

	/**
	 * @return array{total:int,loan:int,buyback:int,lease:int}
	 */
	private function count_deals_by_type() {
		$out = array( 'total' => 0, 'loan' => 0, 'buyback' => 0, 'lease' => 0 );
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$out['total'] = count( $ids );
		foreach ( $ids as $id ) {
			$t = get_post_meta( $id, 'deal_type', true );
			if ( isset( $out[ $t ] ) ) {
				$out[ $t ]++;
			}
		}
		return $out;
	}

	/**
	 * Whole days from today (local) until $date. Null if unparseable.
	 *
	 * @param string $date
	 * @return int|null
	 */
	private function days_until( $date ) {
		$ts = strtotime( (string) $date );
		if ( false === $ts ) {
			return null;
		}
		$today = strtotime( current_time( 'Y-m-d' ) );
		return (int) floor( ( $ts - $today ) / DAY_IN_SECONDS );
	}
}
