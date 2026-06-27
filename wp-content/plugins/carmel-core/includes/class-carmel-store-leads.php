<?php
/**
 * 加盟店ダッシュボードの「反響（リード）」一覧。
 *
 * LINEチャット反響（support_type=line_inquiry）と在庫問い合わせ
 * （support_type=inventory_inquiry）を `carmel_support` から取得し、加盟店ポータル
 * （/store）の上部に表示する。自店（store_id 一致）に割り当てられた反響のみ表示し、
 * 本部（carmel_manage_stores）は全件。対応状況（新規→対応中→完了）を切り替えられる。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store_Leads {

	/** @var Carmel_Store_Leads|null */
	private static $instance = null;

	const STATUS_ACTION = 'carmel_lead_status';
	const NONCE         = 'carmel_lead_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'carmel_store_dashboard_top', array( $this, 'render_panel' ), 7 );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( $this, 'handle_status' ) );
	}

	/** 対応状況 => ラベル。 */
	public static function statuses() {
		return array( 'new' => '新規', 'working' => '対応中', 'done' => '完了' );
	}

	private static function next_status( $s ) {
		$order = array( 'new', 'working', 'done' );
		$i     = array_search( $s, $order, true );
		if ( false === $i ) {
			return 'working';
		}
		return $order[ ( $i + 1 ) % count( $order ) ];
	}

	private function can_access() {
		return is_user_logged_in() && ( current_user_can( 'carmel_change_deal_status' ) || current_user_can( 'carmel_manage_stores' ) );
	}

	private function current_store_id() {
		return (int) get_user_meta( get_current_user_id(), 'store_id', true );
	}

	/* --------------------------------------------------------------------- *
	 * 表示
	 * --------------------------------------------------------------------- */

	public function render_panel() {
		if ( ! $this->can_access() ) {
			return;
		}
		$is_hq    = current_user_can( 'carmel_manage_stores' );
		$store_id = $this->current_store_id();

		$meta = array(
			'relation' => 'AND',
			array( 'key' => 'support_type', 'value' => array( 'line_inquiry', 'inventory_inquiry' ), 'compare' => 'IN' ),
		);
		// 加盟店は自店割当のみ（本部は全件）。
		if ( ! $is_hq ) {
			if ( ! $store_id ) {
				return;
			}
			$meta[] = array( 'key' => 'store_id', 'value' => $store_id );
		}

		$leads = get_posts(
			array(
				'post_type'      => 'carmel_support',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => $meta,
			)
		);
		if ( empty( $leads ) ) {
			return; // 反響が無ければ何も出さない。
		}

		$new_count = 0;
		foreach ( $leads as $l ) {
			if ( 'done' !== ( get_post_meta( $l->ID, 'lead_status', true ) ?: 'new' ) ) {
				$new_count++;
			}
		}

		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-leads"><h3>📨 反響（LINE・在庫問い合わせ）<span class="carmel-leads-badge">未対応 ' . (int) $new_count . '</span></h3>';
		echo '<table class="carmel-table"><thead><tr><th>日時</th><th>種別</th><th>お客様</th><th>連絡先</th><th>エリア</th><th>内容</th><th>状況</th><th>操作</th></tr></thead><tbody>';
		foreach ( $leads as $l ) {
			$type  = (string) get_post_meta( $l->ID, 'support_type', true );
			$name  = (string) get_post_meta( $l->ID, 'applicant_name', true );
			$phone = (string) get_post_meta( $l->ID, 'applicant_phone', true );
			$area  = (string) get_post_meta( $l->ID, 'area', true );
			$msg   = (string) get_post_meta( $l->ID, 'message', true );
			$st    = get_post_meta( $l->ID, 'lead_status', true ) ?: 'new';
			$lbl   = self::statuses();

			echo '<tr class="carmel-lead-' . esc_attr( $st ) . '">';
			echo '<td>' . esc_html( get_the_date( 'n/j H:i', $l->ID ) ) . '</td>';
			echo '<td>' . esc_html( 'line_inquiry' === $type ? 'LINE' : '在庫問合せ' ) . '</td>';
			echo '<td>' . esc_html( $name ? $name : '—' ) . '</td>';
			echo '<td>' . esc_html( $phone ? $phone : '—' ) . '</td>';
			echo '<td>' . esc_html( $area ? $area : '—' ) . '</td>';
			echo '<td class="carmel-lead-msg" title="' . esc_attr( $msg ) . '">' . esc_html( mb_strimwidth( $msg, 0, 40, '…' ) ) . '</td>';
			echo '<td><span class="carmel-lead-st">' . esc_html( isset( $lbl[ $st ] ) ? $lbl[ $st ] : $st ) . '</span></td>';
			echo '<td>' . $this->toggle_button( $l->ID, $st ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	private function toggle_button( $lead_id, $st ) {
		$next  = self::next_status( $st );
		$lbl   = self::statuses();
		$nonce = wp_create_nonce( self::STATUS_ACTION . '_' . $lead_id );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::STATUS_ACTION ) . '">'
			. '<input type="hidden" name="lead_id" value="' . (int) $lead_id . '">'
			. '<input type="hidden" name="to" value="' . esc_attr( $next ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-btn carmel-btn-ghost">→ ' . esc_html( $lbl[ $next ] ) . '</button></form>';
	}

	/* --------------------------------------------------------------------- *
	 * 状況更新
	 * --------------------------------------------------------------------- */

	public function handle_status() {
		if ( ! $this->can_access() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$lead_id  = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
		$to       = isset( $_POST['to'] ) ? sanitize_key( $_POST['to'] ) : '';
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::STATUS_ACTION . '_' . $lead_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( 'carmel_support' !== get_post_type( $lead_id ) || ! isset( self::statuses()[ $to ] ) ) {
			wp_die( esc_html__( '対象が不正です。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		// 自店スコープ（本部はバイパス）。
		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			$my = $this->current_store_id();
			if ( ! $my || (int) get_post_meta( $lead_id, 'store_id', true ) !== $my ) {
				wp_die( esc_html__( '他店の反響は操作できません。', 'carmel-core' ), '', array( 'response' => 403 ) );
			}
		}

		update_post_meta( $lead_id, 'lead_status', $to );
		do_action( 'carmel_lead_status_changed', $lead_id, $to );
		wp_safe_redirect( add_query_arg( 'carmel_lead', 'ok', $redirect ) );
		exit;
	}

	private function banner() {
		$msg = isset( $_GET['carmel_lead'] ) ? sanitize_key( $_GET['carmel_lead'] ) : '';
		if ( 'ok' !== $msg ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-success">反響の対応状況を更新しました。</div>';
	}

	private function styles() {
		return '<style>
.carmel-leads{font-size:14px;margin:1em 0;border:1px solid #e7e2ef;border-radius:.6em;padding:1em 1.1em;background:#fff}
.carmel-leads h3{margin:.1em 0 .6em}
.carmel-leads-badge{background:#c0392b;color:#fff;border-radius:1em;padding:.1em .7em;font-size:.72em;margin-left:.5em;vertical-align:middle}
.carmel-leads .carmel-table{width:100%;border-collapse:collapse}
.carmel-leads th,.carmel-leads td{border:1px solid #eef0f4;padding:.45em .55em;text-align:left;font-size:.86em}
.carmel-leads th{background:#f4f6fb}
.carmel-lead-msg{max-width:220px}
.carmel-lead-new td{background:#fff8f4}
.carmel-lead-done td{color:#999}
.carmel-lead-st{font-weight:bold}
.carmel-btn-ghost{display:inline-block;background:#eef2fb;color:#2e86de;border:0;border-radius:.3em;padding:.3em .7em;cursor:pointer;font-size:.82em;text-decoration:none}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
</style>';
	}
}
