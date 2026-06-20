<?php
/**
 * HQ store management — the franchise lifecycle hub.
 *
 * Shortcode [carmel_hq_stores] (HQ only — carmel_manage_stores). Lists pending
 * franchise applications and active stores. Approving an application activates
 * the store and provisions the owner account (store_owner, linked via
 * owner_user_id + the owner's user_meta store_id), then notifies the owner with
 * a password-set link. Rejecting marks the application closed.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_HQ_Stores {

	/** @var Carmel_HQ_Stores|null */
	private static $instance = null;

	const SHORTCODE       = 'carmel_hq_stores';
	const APPROVE_ACTION  = 'carmel_store_approve';
	const REJECT_ACTION   = 'carmel_store_reject';
	const NONCE           = 'carmel_hq_stores';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::APPROVE_ACTION, array( $this, 'handle_approve' ) );
		add_action( 'admin_post_' . self::REJECT_ACTION, array( $this, 'handle_reject' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Approve / reject
	 * --------------------------------------------------------------------- */

	public function handle_approve() {
		$store_id = $this->guard_request();

		$email = (string) get_post_meta( $store_id, 'applicant_email', true );
		$owner = (string) get_post_meta( $store_id, 'applicant_owner', true );

		$account = Carmel_Application_Intake::provision_user( $owner ? $owner : $email, $email, 'store_owner' );
		if ( is_wp_error( $account ) ) {
			$this->redirect_back( 'err' );
		}

		$owner_id = (int) $account['user_id'];

		// Activate the store and link the owner both ways.
		wp_update_post( array( 'ID' => $store_id, 'post_status' => 'publish' ) );
		update_post_meta( $store_id, 'application_status', 'approved' );
		update_post_meta( $store_id, 'owner_user_id', $owner_id );
		update_user_meta( $owner_id, 'store_id', $store_id );

		// Welcome the owner with a login (password-set) link.
		Carmel_Notifier::notify(
			'franchise_approved',
			array(
				'event_id' => 'franchise_approved:' . $store_id,
				'store_id' => $store_id,
				'vars'     => array(
					'store'            => get_the_title( $store_id ),
					'set_password_url' => $account['set_password_url'] ? $account['set_password_url'] : '（既存アカウントでログインしてください）',
				),
			)
		);

		do_action( 'carmel_store_approved', $store_id, $owner_id );
		$this->redirect_back( 'approved' );
	}

	public function handle_reject() {
		$store_id = $this->guard_request();
		update_post_meta( $store_id, 'application_status', 'rejected' );
		do_action( 'carmel_store_rejected', $store_id );
		$this->redirect_back( 'rejected' );
	}

	/**
	 * Shared cap/nonce guard for approve & reject. Returns the store ID.
	 *
	 * @return int
	 */
	private function guard_request() {
		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$store_id = isset( $_POST['store_id'] ) ? (int) $_POST['store_id'] : 0;
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::NONCE . '_' . $store_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( 'carmel_store' !== get_post_type( $store_id ) ) {
			wp_die( esc_html__( '加盟店が見つかりません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}
		return $store_id;
	}

	private function redirect_back( $msg ) {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );
		wp_safe_redirect( add_query_arg( 'carmel_st', $msg, $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Render
	 * --------------------------------------------------------------------- */

	public function render() {
		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			return '<p class="carmel-notice">加盟店管理を表示する権限がありません。</p>';
		}

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-hqstores">';

		echo $this->pending_section(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->active_section();  // phpcs:ignore WordPress.Security.EscapeOutput

		echo '</div>';
		return ob_get_clean();
	}

	private function pending_section() {
		$pending = get_posts(
			array(
				'post_type'      => 'carmel_store',
				'post_status'    => array( 'draft', 'pending' ),
				'posts_per_page' => 50,
				'meta_query'     => array( array( 'key' => 'application_status', 'value' => 'pending' ) ),
			)
		);

		$out = '<h2>加盟店 応募 <span class="carmel-count">' . count( $pending ) . '</span></h2>';
		if ( empty( $pending ) ) {
			return $out . '<p>新規の応募はありません。</p>';
		}

		$out .= '<table class="carmel-table"><thead><tr><th>店舗</th><th>担当者</th><th>連絡先</th><th>操作</th></tr></thead><tbody>';
		foreach ( $pending as $st ) {
			$owner = get_post_meta( $st->ID, 'applicant_owner', true );
			$email = get_post_meta( $st->ID, 'applicant_email', true );
			$phone = get_post_meta( $st->ID, 'applicant_phone', true );
			$out  .= '<tr>';
			$out  .= '<td>' . esc_html( get_the_title( $st->ID ) ) . '</td>';
			$out  .= '<td>' . esc_html( $owner ) . '</td>';
			$out  .= '<td>' . esc_html( $email ) . ( $phone ? '<br>' . esc_html( $phone ) : '' ) . '</td>';
			$out  .= '<td>' . $this->action_buttons( $st->ID ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$out  .= '</tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function active_section() {
		$stores = get_posts(
			array(
				'post_type'      => 'carmel_store',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = '<h2 class="carmel-mt">加盟店一覧 <span class="carmel-count">' . count( $stores ) . '</span></h2>';
		if ( empty( $stores ) ) {
			return $out . '<p>登録済みの加盟店はありません。</p>';
		}

		$out .= '<table class="carmel-table"><thead><tr><th>店舗</th><th>オーナー</th><th>会費</th><th>担当案件</th></tr></thead><tbody>';
		foreach ( $stores as $st ) {
			$owner_id = (int) get_post_meta( $st->ID, 'owner_user_id', true );
			$owner    = $owner_id ? get_userdata( $owner_id ) : null;
			$mem      = get_post_meta( $st->ID, 'membership_status', true );
			$count    = $this->deal_count( $st->ID );
			$out .= '<tr>';
			$out .= '<td>' . esc_html( get_the_title( $st->ID ) ) . '</td>';
			$out .= '<td>' . esc_html( $owner ? $owner->display_name : '—' ) . '</td>';
			$out .= '<td>' . esc_html( $this->membership_label( $mem ) ) . '</td>';
			$out .= '<td>' . (int) $count . '</td>';
			$out .= '</tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function action_buttons( $store_id ) {
		$url   = esc_url( admin_url( 'admin-post.php' ) );
		$nonce = wp_create_nonce( self::NONCE . '_' . $store_id );
		$hidden = '<input type="hidden" name="store_id" value="' . (int) $store_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">';

		$out  = '<div class="carmel-actions">';
		$out .= '<form method="post" action="' . $url . '" onsubmit="return confirm(\'承認して加盟店を登録し、オーナーアカウントを発行します。よろしいですか？\');">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::APPROVE_ACTION ) . '">' . $hidden
			. '<button type="submit" class="carmel-btn carmel-btn-green">承認</button></form>';
		$out .= '<form method="post" action="' . $url . '" onsubmit="return confirm(\'この応募を却下します。よろしいですか？\');">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::REJECT_ACTION ) . '">' . $hidden
			. '<button type="submit" class="carmel-btn carmel-btn-red">却下</button></form>';
		$out .= '</div>';
		return $out;
	}

	private function deal_count( $store_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'meta_query'     => array( array( 'key' => 'store_id', 'value' => (int) $store_id ) ),
			)
		);
		return count( $ids );
	}

	private function membership_label( $status ) {
		$map = array( 'active' => '有効', 'grace' => '猶予', 'expired' => '期限切れ', 'none' => '未加入', '' => '—' );
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	private function banner() {
		$msg = isset( $_GET['carmel_st'] ) ? sanitize_key( $_GET['carmel_st'] ) : '';
		$map = array(
			'approved' => array( 'success', '加盟店を承認し、オーナーアカウントを発行しました。' ),
			'rejected' => array( 'success', '応募を却下しました。' ),
			'err'      => array( 'error', '処理に失敗しました（メールアドレス等をご確認ください）。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-hqstores{font-size:14px}
.carmel-hqstores h2{display:flex;align-items:center;gap:.5em}
.carmel-mt{margin-top:1.8em}
.carmel-count{font-size:.7em;background:#1a1a2e;color:#fff;border-radius:1em;padding:.1em .8em}
.carmel-table{width:100%;border-collapse:collapse;margin-top:.8em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.6em .7em;text-align:left;vertical-align:middle}
.carmel-table th{background:#f4f6fb}
.carmel-actions{display:flex;gap:.4em}
.carmel-actions form{margin:0}
.carmel-btn{border:0;border-radius:.3em;padding:.4em .9em;color:#fff;cursor:pointer;font-size:.85em}
.carmel-btn-green{background:#16a085}.carmel-btn-red{background:#c0392b}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
