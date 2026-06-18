<?php
/**
 * Store (franchise) portal.
 *
 * Renders via the [carmel_store] shortcode on the /store page (gated to
 * store_owner / store_staff by Carmel_Access_Control). Shows a dashboard of
 * the *own store's* deals (row-level scoped by store_id) and lets staff advance
 * the deals through the phases they own. HQ-only transitions (審査・契約) are
 * intentionally not offered here and are blocked by Carmel_Deal_Status anyway.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store {

	/** @var Carmel_Store|null */
	private static $instance = null;

	const ACTION    = 'carmel_store_action';
	const NONCE     = 'carmel_store_nonce';
	const SHORTCODE = 'carmel_store';

	/** Target statuses that only HQ may set — never offered to stores. */
	const HQ_ONLY = array( 'approved', 'rejected', 'matched', 'contracted' );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Forward flow: status => candidate next statuses. */
	public static function forward_map() {
		return array(
			// loan
			'matched'        => array( 'doc_prep' ),
			'doc_prep'       => array( 'contracted' ), // HQ only → filtered out
			'contracted'     => array( 'delivery_prep' ),
			'delivery_prep'  => array( 'delivered' ),
			'delivered'      => array( 'after_support' ),
			'after_support'  => array( 'closed' ),
			// buyback
			'appraisal_request' => array( 'appraising' ),
			'appraising'        => array( 'quoted' ),
			'quoted'            => array( 'bb_agreed', 'bb_declined' ),
			'bb_agreed'         => array( 'bb_doc_prep' ),
			'bb_doc_prep'       => array( 'bb_collected' ),
			'bb_collected'      => array( 'bb_closed' ),
			// lease
			'lease_request'    => array( 'lease_screening' ),
			'lease_screening'  => array( 'lease_contracted' ),
			'lease_contracted' => array( 'lease_delivered' ),
			'lease_delivered'  => array( 'lease_active' ),
			'lease_active'     => array( 'lease_completed' ),
			'lease_completed'  => array( 'lease_closed' ),
		);
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_post' ) );
	}

	/**
	 * The store_id the current user belongs to (0 if none).
	 *
	 * @return int
	 */
	private function current_store_id() {
		return (int) get_user_meta( get_current_user_id(), 'store_id', true );
	}

	/**
	 * Whether the current user may operate the store portal.
	 *
	 * @return bool
	 */
	private function can_access() {
		return is_user_logged_in() && current_user_can( 'carmel_change_deal_status' );
	}

	/**
	 * Handle a status-advance submission with row-level scope enforcement.
	 */
	public function handle_post() {
		if ( ! $this->can_access() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$deal_id   = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$to_status = isset( $_POST['to_status'] ) ? sanitize_key( $_POST['to_status'] ) : '';
		$delivery  = isset( $_POST['delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_date'] ) ) : '';
		$redirect  = wp_get_referer() ? wp_get_referer() : home_url( '/store' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}

		// Row-level security: the deal must belong to the user's store
		// (HQ users with carmel_manage_stores bypass).
		$deal_store = (int) get_post_meta( $deal_id, 'store_id', true );
		$my_store   = $this->current_store_id();
		if ( ! current_user_can( 'carmel_manage_stores' ) && ( ! $my_store || $deal_store !== $my_store ) ) {
			wp_die( esc_html__( '他店舗の案件は操作できません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		// Never allow HQ-only targets from the store portal.
		if ( in_array( $to_status, self::HQ_ONLY, true ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_msg', 'forbidden', $redirect ) );
			exit;
		}

		// Validate that this is a legitimate forward step from the current status.
		$current = get_post_meta( $deal_id, 'deal_status', true );
		$map     = self::forward_map();
		$allowed = isset( $map[ $current ] ) ? array_diff( $map[ $current ], self::HQ_ONLY ) : array();
		if ( ! in_array( $to_status, $allowed, true ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_msg', 'badstep', $redirect ) );
			exit;
		}

		$args = array();
		if ( 'delivery_prep' === $to_status && '' !== $delivery ) {
			update_post_meta( $deal_id, 'delivery_date', $delivery );
			$args['vars'] = array( 'delivery_date' => $delivery );
		}

		$result = Carmel_Deal_Status::change( $deal_id, $to_status, $args );
		$msg    = is_wp_error( $result ) ? 'err' : 'ok';

		wp_safe_redirect( add_query_arg( 'carmel_msg', $msg, $redirect ) );
		exit;
	}

	/**
	 * Render the store dashboard + deal list.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! $this->can_access() ) {
			return '<p class="carmel-notice">加盟店ポータルを表示する権限がありません。</p>';
		}

		$store_id = $this->current_store_id();
		$is_hq    = current_user_can( 'carmel_manage_stores' );

		if ( ! $store_id && ! $is_hq ) {
			return '<p class="carmel-notice">アカウントに加盟店が紐付いていません。本部にお問い合わせください。</p>';
		}

		$meta_query = array();
		if ( $store_id ) {
			$meta_query[] = array( 'key' => 'store_id', 'value' => $store_id );
		}

		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_query'     => $meta_query ? $meta_query : array(),
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->notice_banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-store">';
		echo '<h2>加盟店ダッシュボード' . ( $is_hq && ! $store_id ? '（全店）' : '' ) . '</h2>';

		echo $this->dashboard( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( empty( $deals ) ) {
			echo '<p>担当案件はありません。</p></div>';
			return ob_get_clean();
		}

		echo '<table class="carmel-table"><thead><tr>';
		echo '<th>案件</th><th>申込者</th><th>種別</th><th>ステータス</th><th>操作</th>';
		echo '</tr></thead><tbody>';

		foreach ( $deals as $deal ) {
			$status = get_post_meta( $deal->ID, 'deal_status', true );
			$name   = get_post_meta( $deal->ID, 'applicant_name', true );
			$type   = get_post_meta( $deal->ID, 'deal_type', true );
			$label  = $this->status_label( $status );

			echo '<tr>';
			echo '<td>#' . (int) $deal->ID . '</td>';
			echo '<td>' . esc_html( $name ? $name : $deal->post_title ) . '</td>';
			echo '<td>' . esc_html( $this->type_label( $type ) ) . '</td>';
			echo '<td><span class="carmel-badge">' . esc_html( $label ) . '</span></td>';
			echo '<td>' . $this->action_cell( $deal->ID, $status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	/**
	 * Status-count summary cards.
	 *
	 * @param WP_Post[] $deals
	 * @return string
	 */
	private function dashboard( array $deals ) {
		$counts = array();
		foreach ( $deals as $deal ) {
			$s = get_post_meta( $deal->ID, 'deal_status', true );
			$counts[ $s ] = isset( $counts[ $s ] ) ? $counts[ $s ] + 1 : 1;
		}
		$out = '<div class="carmel-cards">';
		$out .= '<div class="carmel-stat"><div class="carmel-stat-num">' . count( $deals ) . '</div><div class="carmel-stat-label">担当案件</div></div>';
		foreach ( $counts as $status => $n ) {
			$out .= '<div class="carmel-stat"><div class="carmel-stat-num">' . (int) $n . '</div>'
				. '<div class="carmel-stat-label">' . esc_html( $this->status_label( $status ) ) . '</div></div>';
		}
		$out .= '</div>';
		return $out;
	}

	/**
	 * Build the action cell (forward-step buttons) for a deal.
	 *
	 * @param int    $deal_id
	 * @param string $status
	 * @return string
	 */
	private function action_cell( $deal_id, $status ) {
		$map     = self::forward_map();
		$allowed = isset( $map[ $status ] ) ? array_diff( $map[ $status ], self::HQ_ONLY ) : array();

		// If the only next step is HQ-only, show a waiting hint.
		if ( empty( $allowed ) ) {
			$next_raw = isset( $map[ $status ] ) ? $map[ $status ] : array();
			if ( ! empty( array_intersect( $next_raw, self::HQ_ONLY ) ) ) {
				return '<span class="carmel-wait">本部の手続き待ち</span>';
			}
			return '<span class="carmel-wait">—</span>';
		}

		$action_url = esc_url( admin_url( 'admin-post.php' ) );
		$nonce      = wp_create_nonce( self::ACTION . '_' . $deal_id );
		$hidden     = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<input type="hidden" name="deal_id" value="' . (int) $deal_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">';

		$out = '<div class="carmel-actions">';
		foreach ( $allowed as $to ) {
			$cls   = ( 'bb_declined' === $to ) ? 'carmel-btn-red' : 'carmel-btn-green';
			$out  .= '<form method="post" action="' . $action_url . '">' . $hidden
				. '<input type="hidden" name="to_status" value="' . esc_attr( $to ) . '">';
			// Optional delivery date when entering 納車準備.
			if ( 'delivery_prep' === $to ) {
				$out .= '<input type="date" name="delivery_date" class="carmel-date">';
			}
			$out .= '<button type="submit" class="carmel-btn ' . $cls . '">' . esc_html( $this->status_label( $to ) ) . 'へ</button></form>';
		}
		$out .= '</div>';
		return $out;
	}

	private function notice_banner() {
		$msg = isset( $_GET['carmel_msg'] ) ? sanitize_key( $_GET['carmel_msg'] ) : '';
		if ( '' === $msg ) {
			return '';
		}
		$map = array(
			'ok'        => array( 'success', 'ステータスを更新しました。' ),
			'err'       => array( 'error', '更新できませんでした。' ),
			'forbidden' => array( 'error', 'この操作は本部のみ可能です。' ),
			'badstep'   => array( 'error', 'この遷移は許可されていません。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	private function status_label( $status ) {
		$labels = Carmel_MyPage::status_labels();
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	private function type_label( $type ) {
		$labels = array( 'loan' => 'ローン', 'buyback' => '買取', 'lease' => 'リース' );
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ( $type ? $type : '—' );
	}

	private function styles() {
		return '<style>
.carmel-store{font-size:14px}
.carmel-cards{display:flex;gap:.7em;flex-wrap:wrap;margin:1em 0}
.carmel-stat{border:1px solid #e0e3ea;border-radius:.5em;padding:.7em 1.1em;min-width:90px;text-align:center;background:#fff}
.carmel-stat-num{font-size:1.6em;font-weight:bold;color:#2e86de}
.carmel-stat-label{font-size:.78em;color:#666}
.carmel-table{width:100%;border-collapse:collapse;margin-top:1em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.6em .7em;text-align:left;vertical-align:middle}
.carmel-table th{background:#f4f6fb}
.carmel-badge{display:inline-block;padding:.2em .7em;border-radius:1em;background:#2e86de;color:#fff;font-size:.85em;white-space:nowrap}
.carmel-actions{display:flex;flex-wrap:wrap;gap:.4em}
.carmel-actions form{display:inline-flex;gap:.3em;margin:0;align-items:center}
.carmel-btn{border:0;border-radius:.3em;padding:.4em .8em;color:#fff;cursor:pointer;font-size:.85em}
.carmel-btn-green{background:#16a085}.carmel-btn-red{background:#c0392b}
.carmel-date{border:1px solid #ccc;border-radius:.3em;padding:.3em}
.carmel-wait{color:#999;font-size:.85em}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
