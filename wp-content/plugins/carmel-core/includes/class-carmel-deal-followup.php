<?php
/**
 * 商談（案件）の停滞アラート（フォロー漏れ防止）。
 *
 * 一定期間（既定7日）ステータスの動きがない進行中案件を、担当加盟店＋本部へ
 * 日次cronで通知する。ステータスが動くと活動日時を更新し再通知フラグをリセット。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Deal_Followup {

	/** @var Carmel_Deal_Followup|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'carmel_deal_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
		add_action( 'carmel_daily_cron_done', array( $this, 'check_stale' ) );
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );
	}

	/** 停滞とみなす日数（既定7）。 */
	public static function stale_days() {
		return (int) apply_filters( 'carmel_deal_stale_days', (int) get_option( 'carmel_deal_stale_days', 7 ) );
	}

	/** アラート対象外（決着済み・後工程）ステータス。 */
	private static function excluded_statuses() {
		return apply_filters(
			'carmel_deal_stale_excluded',
			array(
				'closed', 'bb_closed', 'lease_closed', 'rejected', 'bb_declined',
				'delivered', 'after_support', 'bb_collected',
				'lease_delivered', 'lease_active', 'lease_completed',
			)
		);
	}

	/** ステータスが動いたら活動日時を更新し、再通知フラグをリセット。 */
	public function on_status_changed( $deal_id, $new, $old ) {
		update_post_meta( $deal_id, '_deal_activity_at', current_time( 'mysql' ) );
		delete_post_meta( $deal_id, '_stale_alerted' );
	}

	/** 日次：停滞案件を検出して担当店＋本部へ通知（1回のみ）。 */
	public function check_stale() {
		$threshold = time() - self::stale_days() * DAY_IN_SECONDS;

		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'deal_status', 'value' => self::excluded_statuses(), 'compare' => 'NOT IN' ),
					array( 'key' => '_stale_alerted', 'compare' => 'NOT EXISTS' ),
				),
			)
		);

		foreach ( $deals as $deal ) {
			$act = get_post_meta( $deal->ID, '_deal_activity_at', true );
			$ts  = $act ? strtotime( $act ) : strtotime( $deal->post_date );
			if ( false === $ts || $ts > $threshold ) {
				continue; // まだ動きがある（または新しい）。
			}
			$days     = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
			$store_id = (int) get_post_meta( $deal->ID, 'store_id', true );
			$ctx = array(
				'event_id' => 'deal_stale:' . $deal->ID,
				'deal_id'  => (int) $deal->ID,
				'vars'     => array(
					'name' => get_post_meta( $deal->ID, 'applicant_name', true ),
					'days' => $days,
				),
			);
			if ( $store_id ) {
				$ctx['store_id'] = $store_id;
			}
			Carmel_Notifier::notify( 'deal_stale', $ctx );
			update_post_meta( $deal->ID, '_stale_alerted', 1 );
		}
	}

	public function add_routing( $table ) {
		$table['deal_stale'] = array(
			array( 'audience' => 'store', 'channel' => 'lineworks', 'fallback' => 'mail' ),
			array( 'audience' => 'hq', 'channel' => 'lineworks', 'fallback' => null ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'deal_stale' === $event_type ) {
			$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$did  = isset( $context['deal_id'] ) ? (int) $context['deal_id'] : 0;
			$message['subject'] = '【フォロー漏れ注意】動きのない商談があります';
			$message['body']    = '案件 #' . $did . '（' . ( isset( $vars['name'] ) ? $vars['name'] : '' ) . '）が '
				. ( isset( $vars['days'] ) ? $vars['days'] : '' ) . '日間 動いていません。ご対応をご確認ください。';
		}
		return $message;
	}
}
