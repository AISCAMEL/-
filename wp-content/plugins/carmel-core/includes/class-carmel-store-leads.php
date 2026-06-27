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

	const STATUS_ACTION  = 'carmel_lead_status';
	const CONVERT_ACTION = 'carmel_lead_convert';
	const NONCE          = 'carmel_lead_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'carmel_store_dashboard_top', array( $this, 'render_panel' ), 7 );
		add_action( 'admin_post_' . self::STATUS_ACTION, array( $this, 'handle_status' ) );
		add_action( 'admin_post_' . self::CONVERT_ACTION, array( $this, 'handle_convert' ) );

		// 未対応SLAエスカレーション（日次cron）＋通知ルーティング。
		add_action( 'carmel_daily_cron_done', array( $this, 'escalate_overdue' ) );
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );
	}

	/** 未対応とみなすSLA時間（既定24h）。 */
	public static function sla_hours() {
		return (int) apply_filters( 'carmel_lead_sla_hours', (int) get_option( 'carmel_lead_sla_hours', 24 ) );
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
			array( 'key' => 'support_type', 'value' => array( 'line_inquiry', 'inventory_inquiry', 'store_inquiry' ), 'compare' => 'IN' ),
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
			$tlabel = array( 'line_inquiry' => 'LINE', 'inventory_inquiry' => '在庫問合せ', 'store_inquiry' => '店舗問合せ' );
			echo '<td>' . esc_html( isset( $tlabel[ $type ] ) ? $tlabel[ $type ] : $type ) . '</td>';
			echo '<td>' . esc_html( $name ? $name : '—' ) . '</td>';
			echo '<td>' . esc_html( $phone ? $phone : '—' ) . '</td>';
			echo '<td>' . esc_html( $area ? $area : '—' ) . '</td>';
			echo '<td class="carmel-lead-msg" title="' . esc_attr( $msg ) . '">' . esc_html( mb_strimwidth( $msg, 0, 40, '…' ) ) . '</td>';
			echo '<td><span class="carmel-lead-st">' . esc_html( isset( $lbl[ $st ] ) ? $lbl[ $st ] : $st ) . '</span></td>';
			echo '<td class="carmel-lead-ops">' . $this->toggle_button( $l->ID, $st ) // phpcs:ignore WordPress.Security.EscapeOutput
				. $this->convert_cell( $l->ID ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
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

	/** 商談化（顧客確定）フォーム or 既存商談リンク。 */
	private function convert_cell( $lead_id ) {
		$deal_id = (int) get_post_meta( $lead_id, 'deal_id', true );
		if ( $deal_id ) {
			$url = home_url( '/' . ltrim( apply_filters( 'carmel_store_page_slug', 'store' ), '/' ) );
			return ' <a class="carmel-lead-deal" href="' . esc_url( $url ) . '">商談 #' . $deal_id . '</a>';
		}
		$name  = (string) get_post_meta( $lead_id, 'applicant_name', true );
		$phone = (string) get_post_meta( $lead_id, 'applicant_phone', true );
		$nonce = wp_create_nonce( self::CONVERT_ACTION . '_' . $lead_id );

		$out  = ' <details class="carmel-lead-conv"><summary>商談化</summary>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-lead-conv-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::CONVERT_ACTION ) . '">'
			. '<input type="hidden" name="lead_id" value="' . (int) $lead_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<input type="text" name="cust_name" value="' . esc_attr( $name ) . '" placeholder="氏名">'
			. '<input type="email" name="cust_email" placeholder="メール（任意・入れると会員発行）">'
			. '<input type="text" name="cust_phone" value="' . esc_attr( $phone ) . '" placeholder="電話">'
			. '<button type="submit" class="carmel-btn carmel-btn-green">案件を起票</button>'
			. '<p class="carmel-lead-conv-note">メールを入れると顧客アカウントを発行し、LINEと紐付けて通知・会員ページが有効になります。空欄なら「顧客未確定」で起票します。</p>'
			. '</form></details>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * 商談化（リード → 案件）
	 * --------------------------------------------------------------------- */

	public function handle_convert() {
		if ( ! $this->can_access() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$lead_id  = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::CONVERT_ACTION . '_' . $lead_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( 'carmel_support' !== get_post_type( $lead_id ) ) {
			wp_die( esc_html__( '対象が不正です。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}

		$lead_store = (int) get_post_meta( $lead_id, 'store_id', true );
		$my         = $this->current_store_id();
		$is_hq      = current_user_can( 'carmel_manage_stores' );
		if ( ! $is_hq && ( ! $my || $lead_store !== $my ) ) {
			wp_die( esc_html__( '他店の反響は操作できません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		// 既に商談化済みなら再利用。
		$existing = (int) get_post_meta( $lead_id, 'deal_id', true );
		if ( $existing ) {
			wp_safe_redirect( add_query_arg( 'carmel_lead', 'converted', $redirect ) );
			exit;
		}

		$store_id   = $lead_store ? $lead_store : $my;
		$message    = (string) get_post_meta( $lead_id, 'message', true );
		$vehicle_id = (int) get_post_meta( $lead_id, 'vehicle_id', true );
		$line_uid   = (string) get_post_meta( $lead_id, 'line_user_id', true );
		$customer   = (int) get_post_meta( $lead_id, 'customer_id', true );

		// フォーム入力（リード値を上書き可）。
		$name  = isset( $_POST['cust_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cust_name'] ) ) : (string) get_post_meta( $lead_id, 'applicant_name', true );
		$email = isset( $_POST['cust_email'] ) ? sanitize_email( wp_unslash( $_POST['cust_email'] ) ) : '';
		$phone = isset( $_POST['cust_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['cust_phone'] ) ) : (string) get_post_meta( $lead_id, 'applicant_phone', true );

		// メールがあれば顧客を発行/再利用し、LINE IDを紐付け（顧客確定）。
		if ( ! $customer && $email && is_email( $email ) && class_exists( 'Carmel_Application_Intake' ) ) {
			$acct = Carmel_Application_Intake::provision_user( $name ? $name : $email, $email, 'customer' );
			if ( ! is_wp_error( $acct ) ) {
				$customer = (int) $acct['user_id'];
				if ( $line_uid && '' === (string) get_user_meta( $customer, 'line_user_id', true ) ) {
					update_user_meta( $customer, 'line_user_id', $line_uid );
				}
				if ( ! empty( $acct['created'] ) && ! empty( $acct['set_password_url'] ) ) {
					wp_mail( $email, 'カーメル：会員ページのご案内', "お手続きを開始しました。会員ページのログイン設定はこちら：\n" . $acct['set_password_url'] );
				}
			}
		}

		$deal_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_deal',
				'post_status' => 'publish',
				'post_title'  => '反響商談：' . ( $name ? $name : '#' . $lead_id ),
				'meta_input'  => array(
					'deal_type'        => 'loan',
					'store_id'         => (int) $store_id,
					'vehicle_id'       => $vehicle_id,
					'customer_id'      => $customer,
					'lead_intent'      => 'inquiry',
					'is_lead'          => 1,
					'created_via'      => 'lead_convert',
					'applicant_name'   => $name ? $name : '（反響・お客様未確定）',
					'applicant_email'  => $email,
					'applicant_phone'  => $phone,
					'application_note' => '反響（' . get_post_meta( $lead_id, 'support_type', true ) . '）からの商談化：' . $message,
				),
			)
		);
		if ( is_wp_error( $deal_id ) || ! $deal_id ) {
			wp_safe_redirect( add_query_arg( 'carmel_lead', 'err', $redirect ) );
			exit;
		}

		// 担当店マッチング状態へ（在庫連動・履歴・通知が発火）。
		Carmel_Deal_Status::change( (int) $deal_id, 'matched', array( 'system' => true, 'note' => '反響から商談化' ) );

		update_post_meta( $lead_id, 'deal_id', (int) $deal_id );
		update_post_meta( $lead_id, 'lead_status', 'working' );
		do_action( 'carmel_lead_converted', $lead_id, (int) $deal_id );

		wp_safe_redirect( add_query_arg( 'carmel_lead', 'converted', $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 未対応SLAエスカレーション
	 * --------------------------------------------------------------------- */

	/** 一定時間（SLA）未対応の反響を本部＋担当店へエスカレーション（1回のみ）。 */
	public function escalate_overdue() {
		$threshold = gmdate( 'Y-m-d H:i:s', time() - self::sla_hours() * HOUR_IN_SECONDS );
		$leads = get_posts(
			array(
				'post_type'      => 'carmel_support',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'date_query'     => array( array( 'column' => 'post_date_gmt', 'before' => $threshold ) ),
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'support_type', 'value' => array( 'line_inquiry', 'inventory_inquiry', 'store_inquiry' ), 'compare' => 'IN' ),
					array( 'key' => '_escalated', 'compare' => 'NOT EXISTS' ),
				),
			)
		);

		foreach ( $leads as $l ) {
			$st = get_post_meta( $l->ID, 'lead_status', true ) ?: 'new';
			if ( 'done' === $st ) {
				continue;
			}
			$store_id = (int) get_post_meta( $l->ID, 'store_id', true );
			$ctx = array(
				'event_id' => 'lead_sla:' . $l->ID,
				'vars'     => array(
					'name'  => get_post_meta( $l->ID, 'applicant_name', true ),
					'hours' => self::sla_hours(),
				),
			);
			if ( $store_id ) {
				$ctx['store_id'] = $store_id;
			}
			Carmel_Notifier::notify( 'lead_sla_breach', $ctx );
			update_post_meta( $l->ID, '_escalated', 1 );
		}
	}

	public function add_routing( $table ) {
		$table['lead_sla_breach'] = array(
			array( 'audience' => 'store', 'channel' => 'lineworks', 'fallback' => 'mail' ),
			array( 'audience' => 'hq', 'channel' => 'lineworks', 'fallback' => 'mail' ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'lead_sla_breach' === $event_type ) {
			$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$message['subject'] = '【要対応】未対応の反響があります';
			$message['body']    = ( isset( $vars['hours'] ) ? $vars['hours'] : 24 ) . '時間以上 未対応の反響があります（'
				. ( isset( $vars['name'] ) ? $vars['name'] : 'お客様' ) . '）。/store の反響一覧からご対応ください。';
		}
		return $message;
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
		$map = array(
			'ok'        => array( 'success', '反響の対応状況を更新しました。' ),
			'converted' => array( 'success', '反響を商談化しました。案件一覧に表示されます。' ),
			'err'       => array( 'error', '処理できませんでした。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
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
.carmel-btn-green{display:inline-block;background:#16a085;color:#fff;border:0;border-radius:.3em;padding:.3em .7em;cursor:pointer;font-size:.82em;text-decoration:none}
.carmel-lead-ops{white-space:nowrap}
.carmel-lead-ops form{display:inline-block}
.carmel-lead-deal{display:inline-block;margin-left:.3em;color:#6b4fbb;text-decoration:none;font-size:.82em}
.carmel-lead-conv{display:inline-block;margin-left:.3em}
.carmel-lead-conv summary{cursor:pointer;color:#16a085;font-size:.82em;display:inline}
.carmel-lead-conv-form{display:flex;flex-direction:column;gap:.3em;margin-top:.4em;min-width:200px}
.carmel-lead-conv-form input{border:1px solid #ccc;border-radius:.3em;padding:.35em}
.carmel-lead-conv-note{font-size:.74em;color:#888;margin:.2em 0 0}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
</style>';
	}
}
