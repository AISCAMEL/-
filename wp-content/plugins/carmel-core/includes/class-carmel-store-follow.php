<?php
/**
 * お気に入り店舗（フォロー）＋新着在庫通知。
 *
 * 会員が店舗ページから加盟店をフォロー（user_meta `carmel_followed_stores`）すると、
 * その店舗が在庫を公開した際に新着通知（store_new_stock・プロライン→メール）が届く。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store_Follow {

	/** @var Carmel_Store_Follow|null */
	private static $instance = null;

	const ACTION = 'carmel_store_follow';
	const NONCE  = 'carmel_follow_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_follow' ) );
		// 在庫公開を検知してフォロワーへ通知。
		add_action( 'added_post_meta', array( $this, 'on_vehicle_meta' ), 25, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_vehicle_meta' ), 25, 4 );
		// 通知ルーティング／文面。
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );
	}

	/* --------------------------------------------------------------------- *
	 * フォロー状態
	 * --------------------------------------------------------------------- */

	public function followed( $uid = 0 ) {
		$uid = $uid ? $uid : get_current_user_id();
		if ( ! $uid ) {
			return array();
		}
		$f = get_user_meta( $uid, 'carmel_followed_stores', true );
		return is_array( $f ) ? array_map( 'intval', $f ) : array();
	}

	public function is_following( $store_id ) {
		return in_array( (int) $store_id, $this->followed(), true );
	}

	/** フォロー/解除ボタン（未ログインはログイン誘導）。 */
	public function follow_button( $store_id ) {
		if ( ! is_user_logged_in() ) {
			return '<a class="carmel-btn carmel-btn-ghost" href="' . esc_url( home_url( '/login' ) ) . '">♡ ログインしてフォロー</a>';
		}
		$on    = $this->is_following( $store_id );
		$nonce = wp_create_nonce( self::ACTION . '_' . $store_id );
		$label = $on ? '✓ フォロー中' : '♡ この店舗をフォロー';
		$cls   = $on ? 'carmel-btn-ghost' : 'carmel-btn-purple';
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-follow-form" style="display:inline">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<input type="hidden" name="store_id" value="' . (int) $store_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-btn ' . $cls . '">' . esc_html( $label ) . '</button></form>';
	}

	public function handle_follow() {
		$store_id = isset( $_POST['store_id'] ) ? (int) $_POST['store_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ACTION . '_' . $store_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! is_user_logged_in() || 'carmel_store' !== get_post_type( $store_id ) ) {
			wp_die( esc_html__( 'ログインが必要です。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$uid = get_current_user_id();
		$f   = $this->followed( $uid );
		if ( in_array( $store_id, $f, true ) ) {
			$f = array_values( array_diff( $f, array( $store_id ) ) );
		} else {
			$f[] = $store_id;
		}
		update_user_meta( $uid, 'carmel_followed_stores', $f );
		wp_safe_redirect( $redirect );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 新着在庫通知
	 * --------------------------------------------------------------------- */

	public function on_vehicle_meta( $meta_id, $post_id, $key, $val ) {
		static $done = array();
		if ( 'published' !== $key || isset( $done[ $post_id ] ) ) {
			return;
		}
		if ( ! in_array( (string) $val, array( '1', 'yes', 'true' ), true ) || 'carmel_vehicle' !== get_post_type( $post_id ) ) {
			return;
		}
		$done[ $post_id ] = true;
		$this->notify_followers( (int) $post_id );
	}

	private function notify_followers( $vehicle_id ) {
		$store_id = (int) get_post_meta( $vehicle_id, 'store_id', true );
		if ( ! $store_id ) {
			return;
		}
		$status = (string) get_post_meta( $vehicle_id, 'vehicle_status', true );
		$sell   = class_exists( 'Carmel_Inventory' ) ? Carmel_Inventory::sellable_statuses() : array( '販売中', '商談中' );
		if ( ! in_array( $status ? $status : '販売中', $sell, true ) ) {
			return;
		}

		$users = get_users( array( 'meta_key' => 'carmel_followed_stores', 'fields' => 'ID', 'number' => 2000 ) );
		if ( empty( $users ) ) {
			return;
		}
		$car   = trim( get_post_meta( $vehicle_id, 'maker', true ) . ' ' . get_post_meta( $vehicle_id, 'model', true ) );
		$car   = $car ? $car : get_the_title( $vehicle_id );
		$sname = get_post_meta( $store_id, 'store_name', true ) ?: get_the_title( $store_id );
		$url   = add_query_arg( 'vehicle', (int) $vehicle_id, home_url( '/' . ltrim( apply_filters( 'carmel_inventory_page_slug', 'inventory' ), '/' ) ) );

		foreach ( $users as $uid ) {
			$f = get_user_meta( $uid, 'carmel_followed_stores', true );
			if ( ! is_array( $f ) || ! in_array( $store_id, array_map( 'intval', $f ), true ) ) {
				continue;
			}
			Carmel_Notifier::notify(
				'store_new_stock',
				array(
					'event_id'     => 'store_new_stock:' . $vehicle_id . ':' . $uid,
					'recipient_id' => (int) $uid,
					'vars'         => array( 'car' => $car, 'store' => $sname, 'url' => $url ),
				)
			);
		}
	}

	public function add_routing( $table ) {
		$table['store_new_stock'] = array(
			array( 'audience' => 'customer', 'channel' => 'proline', 'fallback' => 'mail' ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'store_new_stock' === $event_type ) {
			$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$message['subject'] = 'フォロー店舗の新着在庫';
			$message['body']    = ( isset( $vars['store'] ) ? $vars['store'] : 'フォロー中の店舗' ) . " に新着が入荷しました。\n"
				. ( isset( $vars['car'] ) ? $vars['car'] : '' ) . "\n" . ( isset( $vars['url'] ) ? $vars['url'] : '' );
		}
		return $message;
	}
}
