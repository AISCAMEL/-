<?php
/**
 * HQ screening screen.
 *
 * Renders via the [carmel_hq_screening] shortcode (placed on the /hq page,
 * which Carmel_Access_Control already gates to hq_admin). Lists deals awaiting
 * credit screening and exposes approve / reject / start-screening buttons that
 * drive Carmel_Deal_Status::change() through admin-post.php with nonces.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_HQ_Screening {

	/** @var Carmel_HQ_Screening|null */
	private static $instance = null;

	const ACTION   = 'carmel_screening_action';
	const NONCE    = 'carmel_screening_nonce';
	const SHORTCODE = 'carmel_hq_screening';

	/** Statuses that make up the screening queue. */
	const QUEUE = array( 'provisional', 'scored', 'screening' );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Human labels for the statuses this screen touches. */
	public static function status_labels() {
		return array(
			'provisional' => '仮申込',
			'scored'      => 'AIスコア済',
			'screening'   => '信販審査中',
			'approved'    => '審査OK',
			'rejected'    => '審査NG',
		);
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_post' ) );
	}

	/**
	 * Handle an approve/reject/start-screening submission.
	 */
	public function handle_post() {
		if ( ! is_user_logged_in() || ! current_user_can( 'carmel_screening' ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$deal_id   = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$decision  = isset( $_POST['decision'] ) ? sanitize_key( $_POST['decision'] ) : '';
		$reason    = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$redirect  = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}

		$status_map = array(
			'start'   => 'screening',
			'approve' => 'approved',
			'reject'  => 'rejected',
		);
		if ( ! isset( $status_map[ $decision ] ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_msg', 'bad', $redirect ) );
			exit;
		}

		$args = array();
		if ( 'reject' === $decision && '' !== $reason ) {
			$args['vars'] = array( 'result' => 'NG（' . $reason . '）' );
			$args['note'] = $reason;
		}
		if ( 'reject' === $decision ) {
			update_post_meta( $deal_id, 'screening_result', 'NG' );
			update_post_meta( $deal_id, 'screening_reason', $reason );
		} elseif ( 'approve' === $decision ) {
			update_post_meta( $deal_id, 'screening_result', 'OK' );
		}

		$result = Carmel_Deal_Status::change( $deal_id, $status_map[ $decision ], $args );
		$msg    = is_wp_error( $result ) ? 'err' : 'ok';

		wp_safe_redirect( add_query_arg( 'carmel_msg', $msg, $redirect ) );
		exit;
	}

	/**
	 * Render the screening queue.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() || ! current_user_can( 'carmel_screening' ) ) {
			return '<p class="carmel-notice">' . esc_html__( '審査管理を表示する権限がありません。', 'carmel-core' ) . '</p>';
		}

		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
					array(
						'key'     => 'deal_status',
						'value'   => self::QUEUE,
						'compare' => 'IN',
					),
				),
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->notice_banner(); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<div class="carmel-hq-screening">';
		echo '<h2>信販審査キュー <span class="carmel-count">' . count( $deals ) . '件</span></h2>';

		if ( empty( $deals ) ) {
			echo '<p>審査待ちの案件はありません。</p></div>';
			return ob_get_clean();
		}

		$labels = self::status_labels();

		echo '<table class="carmel-table"><thead><tr>';
		echo '<th>案件</th><th>申込者</th><th>種別</th><th>AIスコア</th><th>現在</th><th>審査操作</th>';
		echo '</tr></thead><tbody>';

		foreach ( $deals as $deal ) {
			$status   = get_post_meta( $deal->ID, 'deal_status', true );
			$type     = get_post_meta( $deal->ID, 'deal_type', true );
			$name     = get_post_meta( $deal->ID, 'applicant_name', true );
			$score    = get_post_meta( $deal->ID, 'ai_score', true );
			$rank     = get_post_meta( $deal->ID, 'score_rank', true );
			$label    = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

			echo '<tr>';
			echo '<td>#' . (int) $deal->ID . '</td>';
			echo '<td>' . esc_html( $name ? $name : $deal->post_title ) . '</td>';
			echo '<td>' . esc_html( $this->type_label( $type ) ) . '</td>';
			echo '<td>' . ( '' !== $score ? esc_html( $score . ( $rank ? " ({$rank})" : '' ) ) : '—' ) . '</td>';
			echo '<td><span class="carmel-badge carmel-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span></td>';
			echo '<td>' . $this->action_buttons( $deal->ID, $status ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	/**
	 * Build the action forms for one deal row.
	 *
	 * @param int    $deal_id
	 * @param string $status
	 * @return string
	 */
	private function action_buttons( $deal_id, $status ) {
		$action_url = esc_url( admin_url( 'admin-post.php' ) );
		$nonce      = wp_create_nonce( self::ACTION . '_' . $deal_id );

		$hidden = '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<input type="hidden" name="deal_id" value="' . (int) $deal_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">';

		$out = '<div class="carmel-actions">';

		// Start screening (only before it has begun).
		if ( in_array( $status, array( 'provisional', 'scored' ), true ) ) {
			$out .= '<form method="post" action="' . $action_url . '">' . $hidden
				. '<input type="hidden" name="decision" value="start">'
				. '<button type="submit" class="carmel-btn carmel-btn-grey">審査開始</button></form>';
		}

		// Approve.
		$out .= '<form method="post" action="' . $action_url . '" onsubmit="return confirm(\'審査OKにします。よろしいですか？\');">' . $hidden
			. '<input type="hidden" name="decision" value="approve">'
			. '<button type="submit" class="carmel-btn carmel-btn-green">審査OK</button></form>';

		// Reject (with reason).
		$out .= '<form method="post" action="' . $action_url . '" onsubmit="return confirm(\'審査NGにします。よろしいですか？\');">' . $hidden
			. '<input type="hidden" name="decision" value="reject">'
			. '<input type="text" name="reason" placeholder="NG理由" class="carmel-reason">'
			. '<button type="submit" class="carmel-btn carmel-btn-red">審査NG</button></form>';

		$out .= '</div>';
		return $out;
	}

	private function notice_banner() {
		$msg = isset( $_GET['carmel_msg'] ) ? sanitize_key( $_GET['carmel_msg'] ) : '';
		if ( '' === $msg ) {
			return '';
		}
		$map = array(
			'ok'  => array( 'success', 'ステータスを更新し、通知を送信しました。' ),
			'err' => array( 'error', '更新できませんでした（権限または案件をご確認ください）。' ),
			'bad' => array( 'error', '不正な操作です。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	private function type_label( $type ) {
		$labels = array( 'loan' => 'ローン', 'buyback' => '買取', 'lease' => 'リース' );
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ( $type ? $type : '—' );
	}

	private function styles() {
		return '<style>
.carmel-hq-screening{font-size:14px}
.carmel-hq-screening h2{display:flex;align-items:center;gap:.5em}
.carmel-count{font-size:.7em;background:#1a1a2e;color:#fff;border-radius:1em;padding:.1em .8em}
.carmel-table{width:100%;border-collapse:collapse;margin-top:1em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.6em .7em;text-align:left;vertical-align:middle}
.carmel-table th{background:#f4f6fb}
.carmel-badge{display:inline-block;padding:.2em .7em;border-radius:1em;color:#fff;font-size:.85em;white-space:nowrap}
.carmel-provisional{background:#7f8c8d}.carmel-scored{background:#2e86de}.carmel-screening{background:#e67e22}
.carmel-actions{display:flex;flex-wrap:wrap;gap:.4em;align-items:center}
.carmel-actions form{display:inline-flex;gap:.3em;margin:0}
.carmel-btn{border:0;border-radius:.3em;padding:.4em .8em;color:#fff;cursor:pointer;font-size:.85em}
.carmel-btn-green{background:#16a085}.carmel-btn-red{background:#c0392b}.carmel-btn-grey{background:#7f8c8d}
.carmel-reason{border:1px solid #ccc;border-radius:.3em;padding:.35em;width:9em}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
