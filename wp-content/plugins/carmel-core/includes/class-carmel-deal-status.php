<?php
/**
 * Deal status state machine.
 *
 * Single entry point Carmel_Deal_Status::change() for programmatic status
 * changes (with capability checks per §5.5). However events/vehicle-sync/audit
 * run from a meta-change listener so that *any* path that updates the
 * `deal_status` meta — including the admin editor — is covered consistently.
 *
 * On entering a status it:
 *   - records an audit trail entry (§10),
 *   - syncs the linked vehicle's inventory status (§6.3),
 *   - fires the matching carmel_event notification (§9).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Deal_Status {

	/** @var Carmel_Deal_Status|null */
	private static $instance = null;

	/** Re-entrancy guard while we write meta from within processing. */
	private $processing = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Capability required to move a deal *into* a given status.
	 * Falls back to the generic status-change cap.
	 *
	 * @return array<string,string>
	 */
	public static function transition_caps() {
		return apply_filters(
			'carmel_transition_caps',
			array(
				'approved'   => 'carmel_screening',     // 信販審査結果（本部）
				'rejected'   => 'carmel_screening',
				'contracted' => 'carmel_send_contract', // 契約（本部）
			)
		);
	}

	/**
	 * Notification fired when a deal *enters* a status.
	 * value: [ event => string, vars => array ].
	 *
	 * @return array<string,array>
	 */
	public static function status_events() {
		return apply_filters(
			'carmel_status_events',
			array(
				'approved'      => array( 'event' => 'screening_result', 'vars' => array( 'result' => 'OK' ) ),
				'rejected'      => array( 'event' => 'screening_result', 'vars' => array( 'result' => 'NG' ) ),
				'matched'       => array( 'event' => 'store_assigned' ),
				'contracted'    => array( 'event' => 'contract_sign_request' ),
				'delivery_prep' => array( 'event' => 'delivery_date_fixed' ),
				'delivered'     => array( 'event' => 'delivery_date_fixed' ),
			)
		);
	}

	/**
	 * deal_status => inventory vehicle_status (§6.3 連動).
	 *
	 * @return array<string,string>
	 */
	public static function vehicle_status_map() {
		return apply_filters(
			'carmel_vehicle_status_map',
			array(
				'matched'       => '商談中',
				'doc_prep'      => '商談中',
				'contracted'    => '売約済',
				'delivery_prep' => '売約済',
				'delivered'     => '納車済',
				'rejected'      => '販売中', // 差し戻し時は在庫へ戻す
			)
		);
	}

	public function register_hooks() {
		add_action( 'added_post_meta', array( $this, 'on_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_change' ), 10, 4 );
	}

	/**
	 * Programmatic status change with permission enforcement.
	 *
	 * @param int    $deal_id
	 * @param string $new_status
	 * @param array  $args [ system(bool), actor_id(int), vars(array), note(string) ].
	 * @return true|WP_Error
	 */
	public static function change( $deal_id, $new_status, array $args = array() ) {
		$deal_id = (int) $deal_id;
		if ( 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return new WP_Error( 'carmel_not_a_deal', '案件が見つかりません。' );
		}

		$new_status = sanitize_key( $new_status );
		$system     = ! empty( $args['system'] );

		// Permission (skipped for system/cron actors).
		if ( ! $system ) {
			$caps = self::transition_caps();
			$cap  = isset( $caps[ $new_status ] ) ? $caps[ $new_status ] : 'carmel_change_deal_status';
			if ( ! current_user_can( $cap ) ) {
				return new WP_Error( 'carmel_forbidden', 'この操作を行う権限がありません。', array( 'status' => 403 ) );
			}
		}

		// Stash extra context for the listener to pick up.
		self::instance()->pending_args = $args;

		update_post_meta( $deal_id, 'deal_status', $new_status );

		return true;
	}

	/** @var array Extra args carried from change() to the listener. */
	public $pending_args = array();

	/**
	 * Meta listener: react to any deal_status change.
	 *
	 * @param int    $meta_id
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 */
	public function on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( 'deal_status' !== $meta_key || $this->processing ) {
			return;
		}
		if ( 'carmel_deal' !== get_post_type( $post_id ) ) {
			return;
		}

		$new = sanitize_key( $meta_value );
		$old = (string) get_post_meta( $post_id, 'deal_status_processed', true );
		if ( $new === $old ) {
			return; // No effective change.
		}

		$this->processing = true;

		$args = $this->pending_args;
		$this->pending_args = array();

		$this->record_audit( $post_id, $old, $new, $args );
		$this->sync_vehicle( $post_id, $new );
		$this->maybe_notify( $post_id, $new, $args );

		update_post_meta( $post_id, 'deal_status_processed', $new );

		/**
		 * Fires after a deal status change is fully processed.
		 *
		 * @param int    $post_id
		 * @param string $new
		 * @param string $old
		 */
		do_action( 'carmel_deal_status_changed', $post_id, $new, $old );

		$this->processing = false;
	}

	/**
	 * Append an immutable-ish entry to the deal's status history (audit log).
	 */
	private function record_audit( $deal_id, $old, $new, array $args ) {
		$history = get_post_meta( $deal_id, '_carmel_status_history', true );
		if ( ! is_array( $history ) ) {
			$history = array();
		}
		$actor = ! empty( $args['actor_id'] ) ? (int) $args['actor_id'] : get_current_user_id();
		$history[] = array(
			'from'    => $old,
			'to'      => $new,
			'user_id' => $actor,
			'system'  => ! empty( $args['system'] ),
			'note'    => isset( $args['note'] ) ? sanitize_text_field( $args['note'] ) : '',
			'time'    => current_time( 'mysql' ),
		);
		update_post_meta( $deal_id, '_carmel_status_history', $history );
	}

	/**
	 * Keep the linked vehicle's inventory status in sync with the deal.
	 */
	private function sync_vehicle( $deal_id, $new_status ) {
		$map = self::vehicle_status_map();
		if ( ! isset( $map[ $new_status ] ) ) {
			return;
		}
		$vehicle_id = (int) get_post_meta( $deal_id, 'vehicle_id', true );
		if ( $vehicle_id && 'carmel_vehicle' === get_post_type( $vehicle_id ) ) {
			update_post_meta( $vehicle_id, 'vehicle_status', $map[ $new_status ] );
		}
	}

	/**
	 * Fire the notification event mapped to the new status (if any).
	 */
	private function maybe_notify( $deal_id, $new_status, array $args ) {
		$events = self::status_events();
		if ( ! isset( $events[ $new_status ] ) ) {
			return;
		}
		$event = $events[ $new_status ]['event'];
		$vars  = isset( $events[ $new_status ]['vars'] ) ? $events[ $new_status ]['vars'] : array();

		// Caller-supplied vars (e.g. delivery_date, reject reason) win.
		if ( ! empty( $args['vars'] ) && is_array( $args['vars'] ) ) {
			$vars = array_merge( $vars, $args['vars'] );
		}

		// Convenience: pull a stored delivery date if present.
		if ( 'delivery_date_fixed' === $event && empty( $vars['delivery_date'] ) ) {
			$d = get_post_meta( $deal_id, 'delivery_date', true );
			if ( $d ) {
				$vars['delivery_date'] = $d;
			}
		}

		Carmel_Notifier::notify(
			$event,
			array(
				'event_id' => $event . ':' . $deal_id . ':' . $new_status,
				'deal_id'  => $deal_id,
				'vars'     => $vars,
			)
		);
	}
}
