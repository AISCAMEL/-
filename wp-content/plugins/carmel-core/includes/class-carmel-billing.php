<?php
/**
 * 帳票・契約書テンプレート発行エンジン（加盟店 → ユーザー）。
 *
 * 加盟店オーナー/スタッフが、自店の案件に対して以下を発行できる：
 *   - 見積書 / 請求書（明細・数量・単価・消費税・合計つき）
 *   - オートローン支払シミュレーション / 自社リース見積（販売支援から連携）
 *   - 売買契約書 / 自社リース契約書 / 保証書 / 委任状 / 譲渡証明書（テンプレート）
 *
 * 発行された帳票は carmel_document として保存され（doc_type に種別）、案件の
 * 顧客・担当加盟店・本部だけがアクセスできる印刷用HTMLとして「引き出せる」。
 * 顧客はマイページから閲覧・印刷できる。
 *
 * 本部の電子契約（マネーフォワード契約・署名）は Carmel_MF_Contract が担当し、
 * 本クラスは「加盟店が手元で作成・印刷するテンプレート帳票」を担う（役割分担）。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Billing {

	/** @var Carmel_Billing|null */
	private static $instance = null;

	const SHORTCODE_STORE = 'carmel_store_billing';
	const SHORTCODE_MY    = 'carmel_my_documents';
	const ISSUE_ACTION    = 'carmel_billing_issue';
	const VIEW_ACTION     = 'carmel_billing_view';
	const DELETE_ACTION   = 'carmel_billing_delete';
	const NONCE           = 'carmel_billing_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 明細型の帳票（品目・数量・単価を持つ）。
	 *
	 * @return array<string,string>
	 */
	public static function billing_kinds() {
		return array(
			'quote'       => '見積書',
			'invoice'     => '請求書',
			'loan_quote'  => 'オートローン支払シミュレーション',
			'lease_quote' => '自社リース見積書',
		);
	}

	/**
	 * テンプレート型の契約書（差し込み本文を持つ）。
	 *
	 * @return array<string,string>
	 */
	public static function contract_kinds() {
		return array(
			'sales_contract' => '売買契約書',
			'lease_contract' => '自社リース契約書',
			'warranty'       => '保証書',
			'mandate'        => '委任状',
			'transfer_cert'  => '譲渡証明書',
		);
	}

	/** すべての帳票種別 => ラベル。 */
	public static function all_kinds() {
		return self::billing_kinds() + self::contract_kinds();
	}

	public static function kind_label( $kind ) {
		$all = self::all_kinds();
		return isset( $all[ $kind ] ) ? $all[ $kind ] : $kind;
	}

	/** 消費税率（％・本部設定）。 */
	public static function tax_rate() {
		$rate = get_option( 'carmel_tax_rate', 10 );
		return (float) apply_filters( 'carmel_tax_rate', is_numeric( $rate ) ? $rate : 10 );
	}

	/** 発行元の会社情報（本部設定・帳票ヘッダー）。 */
	public static function company_info() {
		$defaults = array(
			'name'    => '株式会社カーメル',
			'address' => '',
			'tel'     => '',
			'email'   => '',
			'reg_no'  => '', // 適格請求書発行事業者登録番号（インボイス）
		);
		$opt = get_option( 'carmel_company_info', array() );
		return apply_filters( 'carmel_company_info', wp_parse_args( is_array( $opt ) ? $opt : array(), $defaults ) );
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE_STORE, array( $this, 'render_store' ) );
		add_shortcode( self::SHORTCODE_MY, array( $this, 'render_my' ) );
		add_action( 'admin_post_' . self::ISSUE_ACTION, array( $this, 'handle_issue' ) );
		add_action( 'admin_post_' . self::VIEW_ACTION, array( $this, 'handle_view' ) );
		add_action( 'admin_post_' . self::DELETE_ACTION, array( $this, 'handle_delete' ) );

		// 帳票発行イベントを通知ルーティングへ追加（notifier 本体は触らない）。
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );

		// 加盟店ダッシュボードに導線を表示。
		add_action( 'carmel_store_dashboard_top', array( $this, 'dashboard_nav' ) );
	}

	/**
	 * 加盟店ダッシュボード上部に帳票・販売支援への導線を出す。
	 * リンク先ページのスラッグはフィルタで変更可能。
	 */
	public function dashboard_nav() {
		$links = array(
			array( apply_filters( 'carmel_billing_page_slug', 'store-billing' ), '📄 帳票・契約書を発行', '#6b4fbb' ),
			array( apply_filters( 'carmel_sales_support_page_slug', 'sales-support' ), '🤝 販売支援', '#2e86de' ),
			array( apply_filters( 'carmel_store_inventory_page_slug', 'store-inventory' ), '🚗 在庫共有', '#16a085' ),
			array( apply_filters( 'carmel_community_page_slug', 'community' ), '💬 コミュニティ', '#e67e22' ),
		);
		echo '<div class="carmel-dash-nav" style="display:flex;gap:.6em;flex-wrap:wrap;margin:.4em 0 1em">';
		foreach ( $links as $l ) {
			$url = home_url( '/' . ltrim( $l[0], '/' ) );
			echo '<a class="carmel-btn" style="text-decoration:none;background:' . esc_attr( $l[2] ) . ';color:#fff;border-radius:.3em;padding:.5em 1.1em" href="' . esc_url( $url ) . '">' . esc_html( $l[1] ) . '</a>';
		}
		echo '</div>';
	}

	/* --------------------------------------------------------------------- *
	 * アクセス制御
	 * --------------------------------------------------------------------- */

	/**
	 * 帳票を発行できるか（自店の案件 or 本部）。
	 *
	 * @param int $deal_id
	 * @return bool
	 */
	public static function can_issue( $deal_id ) {
		if ( ! is_user_logged_in() || 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return false;
		}
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return true; // 本部
		}
		if ( ! current_user_can( 'carmel_change_deal_status' ) ) {
			return false;
		}
		$my_store   = (int) get_user_meta( get_current_user_id(), 'store_id', true );
		$deal_store = (int) get_post_meta( $deal_id, 'store_id', true );
		return $my_store && $my_store === $deal_store;
	}

	/**
	 * 帳票を閲覧できるか（発行側＋宛先の顧客）。
	 *
	 * @param int $deal_id
	 * @return bool
	 */
	public static function can_view( $deal_id ) {
		if ( self::can_issue( $deal_id ) ) {
			return true;
		}
		// 宛先となる顧客本人。
		return (int) get_post_meta( $deal_id, 'customer_id', true ) === get_current_user_id();
	}

	/* --------------------------------------------------------------------- *
	 * 帳票の作成（プログラム API）
	 * --------------------------------------------------------------------- */

	/**
	 * 明細型の帳票（見積書/請求書 等）を作成する。
	 *
	 * @param int    $deal_id
	 * @param string $kind  billing_kinds() のいずれか。
	 * @param array  $items [ [name, qty, unit_price], ... ]
	 * @param array  $opts  [ note, issue_date, due_date, tax_rate, title ]
	 * @return int|WP_Error 書類ID。
	 */
	public function create_billing( $deal_id, $kind, array $items, array $opts = array() ) {
		$deal_id = (int) $deal_id;
		if ( ! isset( self::billing_kinds()[ $kind ] ) ) {
			return new WP_Error( 'carmel_bad_kind', '帳票種別が不正です。' );
		}
		$clean    = array();
		$subtotal = 0.0;
		foreach ( $items as $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$qty  = isset( $row['qty'] ) ? (float) $row['qty'] : 0;
			$unit = isset( $row['unit_price'] ) ? (float) $row['unit_price'] : 0;
			if ( '' === $name && 0 === (int) $qty && 0.0 === $unit ) {
				continue; // 空行は無視。
			}
			$line     = round( $qty * $unit );
			$subtotal += $line;
			$clean[]  = array( 'name' => $name, 'qty' => $qty, 'unit_price' => $unit, 'amount' => $line );
		}
		if ( empty( $clean ) ) {
			return new WP_Error( 'carmel_no_items', '明細がありません。' );
		}

		$tax_rate = isset( $opts['tax_rate'] ) && is_numeric( $opts['tax_rate'] ) ? (float) $opts['tax_rate'] : self::tax_rate();
		$tax      = round( $subtotal * $tax_rate / 100 );
		$total    = $subtotal + $tax;

		$meta = array(
			'deal_id'      => $deal_id,
			'doc_type'     => $kind,
			'doc_group'    => 'billing',
			'store_id'     => (int) get_post_meta( $deal_id, 'store_id', true ),
			'customer_id'  => (int) get_post_meta( $deal_id, 'customer_id', true ),
			'bill_items'   => $clean,
			'bill_subtotal'=> $subtotal,
			'bill_tax'     => $tax,
			'bill_tax_rate'=> $tax_rate,
			'bill_total'   => $total,
			'bill_note'    => isset( $opts['note'] ) ? sanitize_textarea_field( $opts['note'] ) : '',
			'issue_date'   => isset( $opts['issue_date'] ) && $opts['issue_date'] ? sanitize_text_field( $opts['issue_date'] ) : current_time( 'Y-m-d' ),
			'due_date'     => isset( $opts['due_date'] ) ? sanitize_text_field( $opts['due_date'] ) : '',
			'addressee'    => $this->addressee_name( $deal_id ),
			'issued_by'    => get_current_user_id(),
			'generated_at' => current_time( 'mysql' ),
		);

		$title  = isset( $opts['title'] ) && $opts['title'] ? $opts['title'] : self::kind_label( $kind ) . ' #' . $deal_id;
		$doc_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_document',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $title ),
				'meta_input'  => $meta,
			),
			true
		);
		if ( is_wp_error( $doc_id ) ) {
			return $doc_id;
		}

		$this->after_issue( $deal_id, (int) $doc_id, $kind );
		return (int) $doc_id;
	}

	/**
	 * テンプレート型の契約書を作成する。
	 *
	 * @param int    $deal_id
	 * @param string $kind     contract_kinds() のいずれか。
	 * @param array  $extra    追加差し込み値（保証期間など）。
	 * @return int|WP_Error 書類ID。
	 */
	public function create_contract( $deal_id, $kind, array $extra = array() ) {
		$deal_id = (int) $deal_id;
		if ( ! isset( self::contract_kinds()[ $kind ] ) ) {
			return new WP_Error( 'carmel_bad_kind', '契約書種別が不正です。' );
		}
		$vars = array_merge( $this->contract_vars( $deal_id ), array_map( 'sanitize_text_field', $extra ) );

		$meta = array(
			'deal_id'       => $deal_id,
			'doc_type'      => $kind,
			'doc_group'     => 'contract',
			'store_id'      => (int) get_post_meta( $deal_id, 'store_id', true ),
			'customer_id'   => (int) get_post_meta( $deal_id, 'customer_id', true ),
			'template_key'  => $kind,
			'contract_vars' => $vars,
			'addressee'     => $this->addressee_name( $deal_id ),
			'issue_date'    => current_time( 'Y-m-d' ),
			'issued_by'     => get_current_user_id(),
			'generated_at'  => current_time( 'mysql' ),
		);

		$doc_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_document',
				'post_status' => 'publish',
				'post_title'  => self::kind_label( $kind ) . ' #' . $deal_id,
				'meta_input'  => $meta,
			),
			true
		);
		if ( is_wp_error( $doc_id ) ) {
			return $doc_id;
		}

		$this->after_issue( $deal_id, (int) $doc_id, $kind );
		return (int) $doc_id;
	}

	/** 発行後の共通処理（通知＋フック）。 */
	private function after_issue( $deal_id, $doc_id, $kind ) {
		Carmel_Notifier::notify(
			'document_issued',
			array(
				'event_id' => 'document_issued:' . $doc_id,
				'deal_id'  => $deal_id,
				'vars'     => array(
					'name'     => $this->addressee_name( $deal_id ),
					'doc_name' => self::kind_label( $kind ),
				),
			)
		);
		do_action( 'carmel_billing_issued', $deal_id, $doc_id, $kind );
	}

	private function addressee_name( $deal_id ) {
		$name = (string) get_post_meta( $deal_id, 'applicant_name', true );
		if ( '' === $name ) {
			$cid = (int) get_post_meta( $deal_id, 'customer_id', true );
			$u   = $cid ? get_userdata( $cid ) : null;
			$name = $u ? $u->display_name : '';
		}
		return $name;
	}

	/* --------------------------------------------------------------------- *
	 * 発行ハンドラ（フォーム POST）
	 * --------------------------------------------------------------------- */

	public function handle_issue() {
		$deal_id  = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$kind     = isset( $_POST['kind'] ) ? sanitize_key( $_POST['kind'] ) : '';
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ISSUE_ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! self::can_issue( $deal_id ) ) {
			wp_die( esc_html__( 'この案件の帳票を発行する権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		if ( isset( self::billing_kinds()[ $kind ] ) ) {
			$items = $this->collect_items_from_post();
			$opts  = array(
				'note'       => isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '',
				'issue_date' => isset( $_POST['issue_date'] ) ? sanitize_text_field( wp_unslash( $_POST['issue_date'] ) ) : '',
				'due_date'   => isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '',
			);
			$result = $this->create_billing( $deal_id, $kind, $items, $opts );
		} elseif ( isset( self::contract_kinds()[ $kind ] ) ) {
			$extra = array();
			foreach ( array( 'warranty_term', 'warranty_scope', 'special_terms' ) as $k ) {
				if ( isset( $_POST[ $k ] ) ) {
					$extra[ $k ] = wp_unslash( $_POST[ $k ] );
				}
			}
			$result = $this->create_contract( $deal_id, $kind, $extra );
		} else {
			$result = new WP_Error( 'carmel_bad_kind', '種別が不正です。' );
		}

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_bill', 'err', $redirect ) );
			exit;
		}
		wp_safe_redirect( add_query_arg(
			array( 'carmel_bill' => 'ok', 'issued' => (int) $result ),
			$redirect
		) );
		exit;
	}

	/** POST の item_name[]/qty[]/unit_price[] を行配列へ。 */
	private function collect_items_from_post() {
		$names = isset( $_POST['item_name'] ) ? (array) wp_unslash( $_POST['item_name'] ) : array();
		$qtys  = isset( $_POST['qty'] ) ? (array) wp_unslash( $_POST['qty'] ) : array();
		$units = isset( $_POST['unit_price'] ) ? (array) wp_unslash( $_POST['unit_price'] ) : array();
		$items = array();
		foreach ( $names as $i => $name ) {
			$items[] = array(
				'name'       => sanitize_text_field( $name ),
				'qty'        => isset( $qtys[ $i ] ) ? (float) $qtys[ $i ] : 0,
				'unit_price' => isset( $units[ $i ] ) ? (float) $units[ $i ] : 0,
			);
		}
		return $items;
	}

	public function handle_delete() {
		$doc_id   = isset( $_POST['doc'] ) ? (int) $_POST['doc'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::DELETE_ACTION . '_' . $doc_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$deal_id = (int) get_post_meta( $doc_id, 'deal_id', true );
		if ( 'carmel_document' !== get_post_type( $doc_id ) || ! self::can_issue( $deal_id ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		wp_trash_post( $doc_id );
		wp_safe_redirect( add_query_arg( 'carmel_bill', 'deleted', $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 表示（印刷用HTML）
	 * --------------------------------------------------------------------- */

	public function handle_view() {
		$doc_id = isset( $_GET['doc'] ) ? (int) $_GET['doc'] : 0;

		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', self::VIEW_ACTION . '_' . $doc_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( 'carmel_document' !== get_post_type( $doc_id ) ) {
			wp_die( esc_html__( '書類が見つかりません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}
		$deal_id = (int) get_post_meta( $doc_id, 'deal_id', true );
		if ( ! self::can_view( $deal_id ) ) {
			wp_die( esc_html__( 'この書類を閲覧する権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo $this->render_printable( $doc_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function view_url( $doc_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::VIEW_ACTION . '&doc=' . (int) $doc_id ),
			self::VIEW_ACTION . '_' . (int) $doc_id
		);
	}

	/**
	 * 帳票1件分の完全なHTML（印刷用）を生成。
	 *
	 * @param int $doc_id
	 * @return string
	 */
	private function render_printable( $doc_id ) {
		$group = (string) get_post_meta( $doc_id, 'doc_group', true );
		$kind  = (string) get_post_meta( $doc_id, 'doc_type', true );
		$body  = ( 'contract' === $group ) ? $this->render_contract_body( $doc_id ) : $this->render_billing_body( $doc_id );

		$title = esc_html( self::kind_label( $kind ) );
		ob_start();
		?>
<!DOCTYPE html>
<html lang="ja"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput ?></title>
<style>
*{box-sizing:border-box}
body{font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;color:#222;margin:0;background:#f0f1f5}
.carmel-sheet{background:#fff;max-width:760px;margin:1.5em auto;padding:3em 3.2em;box-shadow:0 1px 6px rgba(0,0,0,.12)}
.carmel-doc-title{text-align:center;font-size:1.7em;letter-spacing:.5em;margin:0 0 1.4em;border-bottom:3px double #333;padding-bottom:.3em}
.carmel-head{display:flex;justify-content:space-between;gap:2em;margin-bottom:1.6em}
.carmel-to{font-size:1.15em;border-bottom:1px solid #333;padding-bottom:.2em;min-width:50%}
.carmel-to small{font-size:.6em;color:#666}
.carmel-from{font-size:.85em;text-align:right;line-height:1.6}
.carmel-from strong{font-size:1.15em}
.carmel-meta{font-size:.85em;color:#555;margin:.2em 0}
.carmel-total-box{font-size:1.3em;font-weight:bold;border:2px solid #333;display:inline-block;padding:.4em 1em;margin:.6em 0 1.2em}
table.carmel-items{width:100%;border-collapse:collapse;margin:1em 0;font-size:.92em}
table.carmel-items th,table.carmel-items td{border:1px solid #999;padding:.55em .7em}
table.carmel-items th{background:#eef2fb}
table.carmel-items td.num{text-align:right;font-variant-numeric:tabular-nums}
.carmel-sum{width:46%;margin-left:auto;border-collapse:collapse;font-size:.95em}
.carmel-sum td{padding:.45em .7em;border:1px solid #999}
.carmel-sum td.num{text-align:right}
.carmel-sum tr.grand td{background:#eef2fb;font-weight:bold;font-size:1.05em}
.carmel-note{white-space:pre-wrap;border:1px solid #ccc;padding:.8em 1em;margin-top:1.4em;font-size:.9em;background:#fafbfe}
.carmel-body{line-height:2;font-size:.95em;white-space:pre-wrap}
.carmel-seal{display:flex;gap:1.5em;justify-content:flex-end;margin-top:2.4em}
.carmel-seal .box{border:1px solid #999;width:80px;height:80px;display:flex;align-items:center;justify-content:center;font-size:.7em;color:#999}
.carmel-actions{text-align:center;margin:1.2em 0}
.carmel-print-btn{background:#6b4fbb;color:#fff;border:0;border-radius:.3em;padding:.6em 1.6em;font-size:1em;cursor:pointer}
@media print{body{background:#fff}.carmel-sheet{box-shadow:none;margin:0;max-width:none}.carmel-actions{display:none}}
</style></head>
<body>
<div class="carmel-actions"><button class="carmel-print-btn" onclick="window.print()">この書類を印刷 / PDF保存</button></div>
<div class="carmel-sheet">
<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
</body></html>
		<?php
		return ob_get_clean();
	}

	/** 明細型（見積/請求）本文。 */
	private function render_billing_body( $doc_id ) {
		$kind     = (string) get_post_meta( $doc_id, 'doc_type', true );
		$items    = (array) get_post_meta( $doc_id, 'bill_items', true );
		$subtotal = (float) get_post_meta( $doc_id, 'bill_subtotal', true );
		$tax      = (float) get_post_meta( $doc_id, 'bill_tax', true );
		$tax_rate = (float) get_post_meta( $doc_id, 'bill_tax_rate', true );
		$total    = (float) get_post_meta( $doc_id, 'bill_total', true );
		$note     = (string) get_post_meta( $doc_id, 'bill_note', true );
		$issue    = (string) get_post_meta( $doc_id, 'issue_date', true );
		$due      = (string) get_post_meta( $doc_id, 'due_date', true );
		$to       = (string) get_post_meta( $doc_id, 'addressee', true );
		$co       = self::company_info();
		$is_inv   = ( 'invoice' === $kind );

		$out  = '<h1 class="carmel-doc-title">' . esc_html( self::kind_label( $kind ) ) . '</h1>';
		$out .= '<div class="carmel-head">';
		$out .= '<div><div class="carmel-to">' . esc_html( $to ? $to : 'お客様' ) . ' <small>様</small></div>';
		$out .= '<div class="carmel-meta">発行日：' . esc_html( $issue ) . '</div>';
		if ( $is_inv && $due ) {
			$out .= '<div class="carmel-meta">お支払期限：' . esc_html( $due ) . '</div>';
		} elseif ( ! $is_inv && $due ) {
			$out .= '<div class="carmel-meta">有効期限：' . esc_html( $due ) . '</div>';
		}
		$out .= '<div class="carmel-meta">案件番号：#' . (int) get_post_meta( $doc_id, 'deal_id', true ) . '</div></div>';
		$out .= '<div class="carmel-from"><strong>' . esc_html( $this->issuer_name( $doc_id, $co ) ) . '</strong><br>';
		$out .= nl2br( esc_html( $this->issuer_block( $doc_id, $co ) ) ) . '</div>';
		$out .= '</div>';

		$lead = $is_inv ? '下記の通りご請求申し上げます。' : '下記の通りお見積り申し上げます。';
		$out .= '<p>' . esc_html( $lead ) . '</p>';
		$out .= '<div class="carmel-total-box">合計金額　¥' . esc_html( number_format( $total ) ) . '（税込）</div>';

		$out .= '<table class="carmel-items"><thead><tr><th>品目</th><th>数量</th><th>単価</th><th>金額</th></tr></thead><tbody>';
		foreach ( $items as $row ) {
			$out .= '<tr><td>' . esc_html( $row['name'] ) . '</td>'
				. '<td class="num">' . esc_html( $this->num( $row['qty'] ) ) . '</td>'
				. '<td class="num">¥' . esc_html( number_format( (float) $row['unit_price'] ) ) . '</td>'
				. '<td class="num">¥' . esc_html( number_format( (float) $row['amount'] ) ) . '</td></tr>';
		}
		$out .= '</tbody></table>';

		$out .= '<table class="carmel-sum">';
		$out .= '<tr><td>小計</td><td class="num">¥' . esc_html( number_format( $subtotal ) ) . '</td></tr>';
		$out .= '<tr><td>消費税（' . esc_html( $this->num( $tax_rate ) ) . '%）</td><td class="num">¥' . esc_html( number_format( $tax ) ) . '</td></tr>';
		$out .= '<tr class="grand"><td>合計</td><td class="num">¥' . esc_html( number_format( $total ) ) . '</td></tr>';
		$out .= '</table>';

		if ( '' !== $note ) {
			$out .= '<div class="carmel-note">' . esc_html( $note ) . '</div>';
		}
		if ( ! empty( $co['reg_no'] ) ) {
			$out .= '<p class="carmel-meta">登録番号：' . esc_html( $co['reg_no'] ) . '</p>';
		}
		return $out;
	}

	/** テンプレート型（契約書）本文。 */
	private function render_contract_body( $doc_id ) {
		$kind = (string) get_post_meta( $doc_id, 'doc_type', true );
		$vars = (array) get_post_meta( $doc_id, 'contract_vars', true );
		$tpls = self::templates();
		$tpl  = isset( $tpls[ $kind ] ) ? $tpls[ $kind ] : array( 'body' => '' );

		$body = $tpl['body'];
		foreach ( $vars as $k => $v ) {
			$body = str_replace( '{{' . $k . '}}', (string) $v, $body );
		}
		// 未設定のプレースホルダは空白に。
		$body = preg_replace( '/\{\{[a-z_]+\}\}/', '＿＿＿＿', $body );

		$out  = '<h1 class="carmel-doc-title">' . esc_html( self::kind_label( $kind ) ) . '</h1>';
		$out .= '<div class="carmel-body">' . esc_html( $body ) . '</div>';
		$out .= '<div class="carmel-seal"><div class="box">甲 印</div><div class="box">乙 印</div></div>';
		return $out;
	}

	private function issuer_name( $doc_id, array $co ) {
		$store_id = (int) get_post_meta( $doc_id, 'store_id', true );
		$sname    = $store_id ? (string) get_post_meta( $store_id, 'store_name', true ) : '';
		return $sname ? $sname : $co['name'];
	}

	private function issuer_block( $doc_id, array $co ) {
		$store_id = (int) get_post_meta( $doc_id, 'store_id', true );
		$lines    = array();
		if ( $store_id ) {
			$addr = (string) get_post_meta( $store_id, 'store_address', true );
			if ( $addr ) {
				$lines[] = $addr;
			}
		}
		if ( ! empty( $co['tel'] ) ) {
			$lines[] = 'TEL: ' . $co['tel'];
		}
		if ( ! empty( $co['email'] ) ) {
			$lines[] = $co['email'];
		}
		return implode( "\n", $lines );
	}

	/** 整数なら整数、端数あれば小数で表示。 */
	private function num( $n ) {
		$n = (float) $n;
		return ( floor( $n ) === $n ) ? (string) (int) $n : (string) $n;
	}

	/* --------------------------------------------------------------------- *
	 * 契約テンプレート
	 * --------------------------------------------------------------------- */

	/**
	 * 契約書テンプレート本文（差し込みは {{key}}）。フィルタで上書き可能。
	 *
	 * @return array<string,array{title:string,body:string}>
	 */
	public static function templates() {
		$tpls = array(
			'sales_contract' => array(
				'title' => '売買契約書',
				'body'  =>
"売主（以下「甲」という。）{{store_name}} と、買主（以下「乙」という。）{{customer_name}} は、下記自動車の売買について、次のとおり契約を締結する。\n\n"
. "第1条（売買物件）\n甲は乙に対し、下記自動車を売り渡し、乙はこれを買い受ける。\n　車名：{{vehicle_maker}} {{vehicle_model}}\n　年式：{{vehicle_year}}　走行距離：{{vehicle_mileage}}km\n　車台番号：{{vehicle_vin}}　登録番号：{{vehicle_plate}}\n\n"
. "第2条（売買代金）\n売買代金は金 {{price}} 円（税込）とする。\n\n"
. "第3条（引渡し）\n甲は乙に対し、{{delivery_date}} までに本車両を引き渡す。\n\n"
. "第4条（所有権の移転）\n所有権は売買代金完済の時に乙へ移転する。\n\n"
. "第5条（瑕疵担保・保証）\n本車両の保証内容は別途保証書の定めによる。\n\n"
. "本契約成立の証として本書2通を作成し、甲乙記名押印のうえ各1通を保有する。\n\n"
. "{{date}}\n\n甲（売主）：{{store_name}}\n乙（買主）：{{customer_name}}",
			),
			'lease_contract' => array(
				'title' => '自社リース契約書',
				'body'  =>
"貸主（以下「甲」という。）{{store_name}} と、借主（以下「乙」という。）{{customer_name}} は、下記自動車の賃貸借（自社リース）について、次のとおり契約を締結する。\n\n"
. "第1条（リース物件）\n　車名：{{vehicle_maker}} {{vehicle_model}}　年式：{{vehicle_year}}\n　車台番号：{{vehicle_vin}}　登録番号：{{vehicle_plate}}\n\n"
. "第2条（リース期間）\nリース期間は {{lease_term}} ヶ月とする。\n\n"
. "第3条（リース料）\n乙は甲に対し、月額 {{monthly_payment}} 円を毎月支払う。\n\n"
. "第4条（中途解約）\n乙は原則として中途解約できないものとする。\n\n"
. "第5条（管理）\n本車両には所在管理のためGPSを装着する場合がある。\n\n"
. "本契約成立の証として本書2通を作成し、甲乙記名押印のうえ各1通を保有する。\n\n"
. "{{date}}\n\n甲（貸主）：{{store_name}}\n乙（借主）：{{customer_name}}",
			),
			'warranty' => array(
				'title' => '保証書',
				'body'  =>
"保証書\n\n{{store_name}}（以下「当社」）は、下記のとおり購入車両を保証します。\n\n"
. "お客様：{{customer_name}} 様\n車名：{{vehicle_maker}} {{vehicle_model}}　登録番号：{{vehicle_plate}}\n車台番号：{{vehicle_vin}}\n\n"
. "保証期間：{{warranty_term}}\n保証範囲：{{warranty_scope}}\n保証開始日：{{date}}\n\n"
. "本保証は、取扱説明書および注意事項に従って正常に使用された場合に適用されます。\n消耗品、事故・改造・天災等に起因する故障は保証対象外です。\n\n"
. "{{date}}\n保証者：{{store_name}}",
			),
			'mandate' => array(
				'title' => '委任状',
				'body'  =>
"委任状\n\n私（委任者）{{customer_name}} は、下記の者を代理人と定め、下記自動車に関する登録（移転・変更・抹消等）の手続き一切の権限を委任します。\n\n"
. "代理人：{{store_name}}\n\n"
. "対象自動車\n　車名：{{vehicle_maker}} {{vehicle_model}}\n　車台番号：{{vehicle_vin}}　登録番号：{{vehicle_plate}}\n\n"
. "{{date}}\n\n委任者：{{customer_name}}　印",
			),
			'transfer_cert' => array(
				'title' => '譲渡証明書',
				'body'  =>
"譲渡証明書\n\n下記自動車を譲渡したことを証明します。\n\n"
. "　車台番号：{{vehicle_vin}}\n　登録番号：{{vehicle_plate}}\n　車名：{{vehicle_maker}} {{vehicle_model}}\n\n"
. "譲渡人：{{store_name}}\n譲受人：{{customer_name}}\n\n"
. "{{date}}\n譲渡人　記名押印：{{store_name}}　印",
			),
		);
		return apply_filters( 'carmel_contract_templates', $tpls );
	}

	/**
	 * 案件から契約テンプレートの差し込み値を構築。
	 *
	 * @param int $deal_id
	 * @return array<string,string>
	 */
	public function contract_vars( $deal_id ) {
		$store_id   = (int) get_post_meta( $deal_id, 'store_id', true );
		$vehicle_id = (int) get_post_meta( $deal_id, 'vehicle_id', true );
		$price      = get_post_meta( $deal_id, 'appraisal_amount', true );
		if ( '' === (string) $price && $vehicle_id ) {
			$price = get_post_meta( $vehicle_id, 'price', true );
		}

		$vars = array(
			'date'            => date_i18n( 'Y年n月j日' ),
			'customer_name'   => $this->addressee_name( $deal_id ),
			'store_name'      => $store_id ? (string) get_post_meta( $store_id, 'store_name', true ) : self::company_info()['name'],
			'price'           => '' !== (string) $price ? number_format( (float) $price ) : '',
			'delivery_date'   => (string) get_post_meta( $deal_id, 'delivery_date', true ),
			'monthly_payment' => '' !== (string) get_post_meta( $deal_id, 'monthly_payment', true ) ? number_format( (float) get_post_meta( $deal_id, 'monthly_payment', true ) ) : '',
			'lease_term'      => (string) get_post_meta( $deal_id, 'lease_term', true ),
			'warranty_term'   => (string) get_post_meta( $deal_id, 'warranty_term', true ),
			'warranty_scope'  => (string) get_post_meta( $deal_id, 'warranty_scope', true ),
			'vehicle_maker'   => $vehicle_id ? (string) get_post_meta( $vehicle_id, 'maker', true ) : '',
			'vehicle_model'   => $vehicle_id ? (string) get_post_meta( $vehicle_id, 'model', true ) : '',
			'vehicle_year'    => $vehicle_id ? (string) get_post_meta( $vehicle_id, 'year', true ) : '',
			'vehicle_mileage' => $vehicle_id ? (string) get_post_meta( $vehicle_id, 'mileage', true ) : '',
			'vehicle_vin'     => $vehicle_id ? (string) get_post_meta( $vehicle_id, 'vin', true ) : '',
			'vehicle_plate'   => $vehicle_id ? (string) get_post_meta( $vehicle_id, 'plate_no', true ) : '',
		);
		return apply_filters( 'carmel_contract_vars', $vars, $deal_id );
	}

	/* --------------------------------------------------------------------- *
	 * 通知連携
	 * --------------------------------------------------------------------- */

	public function add_routing( $table ) {
		$table['document_issued'] = array(
			array( 'audience' => 'customer', 'channel' => 'proline', 'fallback' => 'mail' ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'document_issued' === $event_type ) {
			$vars            = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$doc             = isset( $vars['doc_name'] ) ? $vars['doc_name'] : '書類';
			$name            = isset( $vars['name'] ) ? $vars['name'] : 'お客様';
			$message['subject'] = $doc . 'を発行しました';
			$message['body']    = $name . " 様\n" . $doc . "を発行しました。マイページよりご確認・印刷いただけます。";
		}
		return $message;
	}

	/* --------------------------------------------------------------------- *
	 * 加盟店UI
	 * --------------------------------------------------------------------- */

	/**
	 * 加盟店向け帳票発行画面。
	 *
	 * @return string
	 */
	public function render_store() {
		if ( ! is_user_logged_in() || ! current_user_can( 'carmel_change_deal_status' ) ) {
			return '<p class="carmel-notice">帳票発行を表示する権限がありません。</p>';
		}
		$deals = $this->accessible_deals();

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-billing"><h2>帳票・契約書の発行</h2>';
		echo '<p class="carmel-bill-lead">案件を選び、ユーザー向けの見積書・請求書・各種契約書を発行できます。発行した書類はユーザーのマイページにも表示されます。</p>';

		if ( empty( $deals ) ) {
			echo '<p>対象の案件がありません。</p></div>';
			return ob_get_clean();
		}

		echo $this->billing_form( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->contract_form( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->issued_list( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		return ob_get_clean();
	}

	/** 自店（本部は全店）の案件一覧。 */
	private function accessible_deals() {
		$args = array(
			'post_type'      => 'carmel_deal',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			$store_id = (int) get_user_meta( get_current_user_id(), 'store_id', true );
			if ( ! $store_id ) {
				return array();
			}
			$args['meta_query'] = array( array( 'key' => 'store_id', 'value' => $store_id ) );
		}
		return get_posts( $args );
	}

	/** 見積書/請求書の作成フォーム（明細＋自動計算）。 */
	private function billing_form( array $deals ) {
		$tax    = self::tax_rate();
		$action = esc_url( admin_url( 'admin-post.php' ) );

		ob_start();
		?>
<div class="carmel-bill-card">
<h3>見積書・請求書を作成</h3>
<form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput ?>" class="carmel-bill-form" data-carmel-billing="1">
	<input type="hidden" name="action" value="<?php echo esc_attr( self::ISSUE_ACTION ); ?>">
	<input type="hidden" name="<?php echo esc_attr( self::NONCE ); ?>" value="">
	<div class="carmel-bill-row">
		<label>案件
			<select name="deal_id" class="carmel-deal-select" required><?php echo $this->deal_options_with_nonce( $deals, self::ISSUE_ACTION ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
		</label>
		<label>帳票種別
			<select name="kind">
				<option value="quote">見積書</option>
				<option value="invoice">請求書</option>
			</select>
		</label>
		<label>発行日 <input type="date" name="issue_date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></label>
		<label>期限 <input type="date" name="due_date"></label>
	</div>

	<table class="carmel-line-table">
		<thead><tr><th>品目</th><th>数量</th><th>単価</th><th>金額</th><th></th></tr></thead>
		<tbody class="carmel-lines">
			<?php for ( $i = 0; $i < 3; $i++ ) : ?>
			<tr class="carmel-line">
				<td><input type="text" name="item_name[]" placeholder="例）車両本体価格 / 整備費用 / 陸送費"></td>
				<td><input type="number" name="qty[]" class="carmel-qty" value="1" min="0" step="1"></td>
				<td><input type="number" name="unit_price[]" class="carmel-unit" value="0" min="0" step="1"></td>
				<td class="carmel-line-amt num">¥0</td>
				<td><button type="button" class="carmel-row-del" title="行を削除">×</button></td>
			</tr>
			<?php endfor; ?>
		</tbody>
	</table>
	<button type="button" class="carmel-add-row carmel-btn carmel-btn-ghost">＋ 明細を追加</button>

	<div class="carmel-bill-totals">
		<div>小計：<span class="carmel-subtotal">¥0</span></div>
		<div>消費税（<span class="carmel-taxrate"><?php echo esc_html( $this->num( $tax ) ); ?></span>%）：<span class="carmel-tax">¥0</span></div>
		<div class="carmel-grand">合計：<span class="carmel-total">¥0</span></div>
	</div>
	<label class="carmel-block">備考<textarea name="note" rows="2" placeholder="お振込先・有効期限などの補足"></textarea></label>
	<button type="submit" class="carmel-btn carmel-btn-purple">発行する</button>
	<input type="hidden" class="carmel-taxrate-val" value="<?php echo esc_attr( $tax ); ?>">
</form>
</div>
<?php echo $this->billing_js(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * 案件ごとに発行nonceを data 属性で持たせた option 群。
	 * JS が選択値に応じて hidden nonce を差し替える。
	 */
	private function deal_options_with_nonce( array $deals, $action ) {
		$out = '';
		foreach ( $deals as $deal ) {
			$name  = get_post_meta( $deal->ID, 'applicant_name', true );
			$label = '#' . $deal->ID . ' ' . ( $name ? $name : $deal->post_title );
			$nonce = wp_create_nonce( $action . '_' . $deal->ID );
			$out  .= '<option value="' . (int) $deal->ID . '" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html( $label ) . '</option>';
		}
		return $out;
	}

	/** 契約書テンプレート発行フォーム。 */
	private function contract_form( array $deals ) {
		$action = esc_url( admin_url( 'admin-post.php' ) );
		ob_start();
		?>
<div class="carmel-bill-card">
<h3>契約書テンプレートを発行</h3>
<form method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( self::ISSUE_ACTION ); ?>">
	<input type="hidden" name="<?php echo esc_attr( self::NONCE ); ?>" value="">
	<div class="carmel-bill-row">
		<label>案件
			<select name="deal_id" class="carmel-deal-select" required><?php echo $this->deal_options_with_nonce( $deals, self::ISSUE_ACTION ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
		</label>
		<label>書類
			<select name="kind">
				<?php foreach ( self::contract_kinds() as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
	<div class="carmel-bill-row">
		<label>保証期間（保証書）<input type="text" name="warranty_term" placeholder="例）3ヶ月 または 3,000km"></label>
		<label class="carmel-block">保証範囲（保証書）<input type="text" name="warranty_scope" placeholder="例）エンジン・ミッション 主要機構"></label>
	</div>
	<p class="carmel-hint">車両・金額・お客様情報は案件データから自動で差し込まれます。発行後の書類は印刷画面で内容をご確認ください。</p>
	<button type="submit" class="carmel-btn carmel-btn-purple">発行する</button>
</form>
</div>
		<?php
		return ob_get_clean();
	}

	/** 発行済み帳票の一覧（自店分）。 */
	private function issued_list( array $deals ) {
		$deal_ids = wp_list_pluck( $deals, 'ID' );
		if ( empty( $deal_ids ) ) {
			return '';
		}
		$docs = get_posts(
			array(
				'post_type'      => 'carmel_document',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'deal_id', 'value' => $deal_ids, 'compare' => 'IN' ),
					array( 'key' => 'doc_group', 'value' => array( 'billing', 'contract' ), 'compare' => 'IN' ),
				),
			)
		);
		if ( empty( $docs ) ) {
			return '<div class="carmel-bill-card"><h3>発行済みの帳票</h3><p class="carmel-hint">まだ発行された帳票はありません。</p></div>';
		}

		$out  = '<div class="carmel-bill-card"><h3>発行済みの帳票</h3>';
		$out .= '<table class="carmel-table"><thead><tr><th>発行日</th><th>種別</th><th>宛先</th><th>金額</th><th>操作</th></tr></thead><tbody>';
		foreach ( $docs as $doc ) {
			$kind  = get_post_meta( $doc->ID, 'doc_type', true );
			$to    = get_post_meta( $doc->ID, 'addressee', true );
			$total = get_post_meta( $doc->ID, 'bill_total', true );
			$date  = get_post_meta( $doc->ID, 'issue_date', true );
			$del   = wp_create_nonce( self::DELETE_ACTION . '_' . $doc->ID );
			$out  .= '<tr>';
			$out  .= '<td>' . esc_html( $date ) . '</td>';
			$out  .= '<td>' . esc_html( self::kind_label( $kind ) ) . '</td>';
			$out  .= '<td>' . esc_html( $to ) . '</td>';
			$out  .= '<td class="num">' . ( '' !== (string) $total ? '¥' . esc_html( number_format( (float) $total ) ) : '—' ) . '</td>';
			$out  .= '<td class="carmel-doc-ops">';
			$out  .= '<a class="carmel-btn carmel-btn-blue" href="' . esc_url( self::view_url( $doc->ID ) ) . '" target="_blank" rel="noopener">表示・印刷</a>';
			$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'この帳票を削除しますか？\');">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::DELETE_ACTION ) . '">'
				. '<input type="hidden" name="doc" value="' . (int) $doc->ID . '">'
				. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $del ) . '">'
				. '<button type="submit" class="carmel-btn carmel-btn-red">削除</button></form>';
			$out  .= '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * 顧客UI
	 * --------------------------------------------------------------------- */

	/**
	 * 顧客向け：自分の案件に発行された帳票一覧（ショートコード）。
	 *
	 * @return string
	 */
	public function render_my() {
		if ( ! is_user_logged_in() ) {
			return '<p class="carmel-notice">ログインすると発行書類をご確認いただけます。</p>';
		}
		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => 'customer_id', 'value' => get_current_user_id() ) ),
			)
		);
		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-billing"><h2>発行書類</h2>';
		if ( empty( $deals ) ) {
			echo '<p>発行された書類はありません。</p></div>';
			return ob_get_clean();
		}
		$html = '';
		foreach ( $deals as $deal_id ) {
			$html .= self::customer_deal_documents_html( $deal_id, false );
		}
		echo '' === $html ? '<p>発行された書類はありません。</p>' : $html; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * 1案件分の発行書類リスト（マイページ埋め込み用）。
	 *
	 * @param int  $deal_id
	 * @param bool $with_heading セクション見出しを付けるか。
	 * @return string 書類が無ければ空文字。
	 */
	public static function customer_deal_documents_html( $deal_id, $with_heading = true ) {
		$docs = get_posts(
			array(
				'post_type'      => 'carmel_document',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'deal_id', 'value' => (int) $deal_id ),
					array( 'key' => 'doc_group', 'value' => array( 'billing', 'contract' ), 'compare' => 'IN' ),
				),
			)
		);
		if ( empty( $docs ) ) {
			return '';
		}
		$out = '<div class="carmel-mydocs">';
		if ( $with_heading ) {
			$out .= '<h3>発行書類（案件 #' . (int) $deal_id . '）</h3>';
		} else {
			$out .= '<h4>発行書類</h4>';
		}
		$out .= '<ul class="carmel-mydocs-list">';
		foreach ( $docs as $doc ) {
			$kind  = get_post_meta( $doc->ID, 'doc_type', true );
			$date  = get_post_meta( $doc->ID, 'issue_date', true );
			$total = get_post_meta( $doc->ID, 'bill_total', true );
			$amt   = '' !== (string) $total ? '（¥' . number_format( (float) $total ) . '）' : '';
			$out  .= '<li><span class="carmel-doc-kind">' . esc_html( self::kind_label( $kind ) ) . '</span> '
				. esc_html( $date ) . ' ' . esc_html( $amt ) . ' '
				. '<a href="' . esc_url( self::view_url( $doc->ID ) ) . '" target="_blank" rel="noopener">表示・印刷</a></li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * ビュー補助（バナー・JS・CSS）
	 * --------------------------------------------------------------------- */

	private function banner() {
		$msg = isset( $_GET['carmel_bill'] ) ? sanitize_key( $_GET['carmel_bill'] ) : '';
		$map = array(
			'ok'      => array( 'success', '帳票を発行しました。' ),
			'deleted' => array( 'success', '帳票を削除しました。' ),
			'err'     => array( 'error', '発行できませんでした。入力内容をご確認ください。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		$out = '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] );
		if ( 'ok' === $msg && isset( $_GET['issued'] ) ) {
			$doc_id = (int) $_GET['issued'];
			if ( self::can_view( (int) get_post_meta( $doc_id, 'deal_id', true ) ) ) {
				$out .= ' <a href="' . esc_url( self::view_url( $doc_id ) ) . '" target="_blank" rel="noopener">発行した書類を開く</a>';
			}
		}
		$out .= '</div>';
		return $out;
	}

	private function billing_js() {
		ob_start();
		?>
<script>
document.addEventListener('DOMContentLoaded',function(){
	function yen(n){return '¥'+(Math.round(n)).toLocaleString('ja-JP');}
	document.querySelectorAll('form[data-carmel-billing]').forEach(function(form){
		var rate=parseFloat(form.querySelector('.carmel-taxrate-val').value)||0;
		function recalc(){
			var sub=0;
			form.querySelectorAll('.carmel-line').forEach(function(tr){
				var q=parseFloat(tr.querySelector('.carmel-qty').value)||0;
				var u=parseFloat(tr.querySelector('.carmel-unit').value)||0;
				var amt=Math.round(q*u);
				tr.querySelector('.carmel-line-amt').textContent=yen(amt);
				sub+=amt;
			});
			var tax=Math.round(sub*rate/100);
			form.querySelector('.carmel-subtotal').textContent=yen(sub);
			form.querySelector('.carmel-tax').textContent=yen(tax);
			form.querySelector('.carmel-total').textContent=yen(sub+tax);
		}
		form.addEventListener('input',recalc);
		form.querySelector('.carmel-add-row').addEventListener('click',function(){
			var tb=form.querySelector('.carmel-lines');
			var tr=tb.querySelector('.carmel-line').cloneNode(true);
			tr.querySelectorAll('input').forEach(function(inp){
				if(inp.name==='qty[]')inp.value='1';else if(inp.name==='unit_price[]')inp.value='0';else inp.value='';
			});
			tr.querySelector('.carmel-line-amt').textContent='¥0';
			tb.appendChild(tr);
		});
		form.addEventListener('click',function(e){
			if(e.target.classList.contains('carmel-row-del')){
				var rows=form.querySelectorAll('.carmel-line');
				if(rows.length>1)e.target.closest('.carmel-line').remove();
				recalc();
			}
		});
		recalc();
	});
	// 案件選択に応じて nonce を差し替え（見積/請求・契約書 両フォーム）。
	document.querySelectorAll('.carmel-deal-select').forEach(function(sel){
		var form=sel.closest('form');if(!form)return;
		function syncNonce(){
			var opt=sel.options[sel.selectedIndex];
			var n=opt?opt.getAttribute('data-nonce'):'';
			var hidden=form.querySelector('input[name="<?php echo esc_js( self::NONCE ); ?>"]');
			if(hidden&&n)hidden.value=n;
		}
		sel.addEventListener('change',syncNonce);syncNonce();
	});
});
</script>
		<?php
		return ob_get_clean();
	}

	private function styles() {
		return '<style>
.carmel-billing{font-size:14px;max-width:820px}
.carmel-bill-lead{color:#555}
.carmel-bill-card{border:1px solid #e0e3ea;border-radius:.6em;padding:1.2em 1.3em;margin:1.1em 0;background:#fff}
.carmel-bill-card h3{margin:.1em 0 .8em}
.carmel-bill-row{display:flex;flex-wrap:wrap;gap:.8em;margin-bottom:.8em}
.carmel-bill-row label{display:flex;flex-direction:column;font-size:.82em;color:#555;gap:.2em}
.carmel-bill-form label.carmel-block,.carmel-block{display:block;width:100%;font-size:.82em;color:#555}
.carmel-bill-row input,.carmel-bill-row select,.carmel-block textarea,.carmel-block input{border:1px solid #ccc;border-radius:.3em;padding:.4em}
.carmel-block textarea{width:100%}
.carmel-line-table{width:100%;border-collapse:collapse;margin:.4em 0}
.carmel-line-table th,.carmel-line-table td{border:1px solid #eef0f4;padding:.35em .4em;text-align:left}
.carmel-line-table th{background:#f4f6fb;font-size:.82em}
.carmel-line-table input{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.35em}
.carmel-line-table .carmel-qty,.carmel-line-table .carmel-unit{max-width:90px}
.carmel-row-del{border:0;background:#f0f1f5;border-radius:.3em;cursor:pointer;padding:.2em .6em;color:#a5281b}
.num{text-align:right;font-variant-numeric:tabular-nums}
.carmel-bill-totals{display:flex;gap:1.4em;justify-content:flex-end;align-items:center;margin:.8em 0;font-size:.95em;flex-wrap:wrap}
.carmel-bill-totals .carmel-grand{font-weight:bold;font-size:1.15em;color:#6b4fbb}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.5em 1.1em;color:#fff;cursor:pointer;font-size:.9em;text-decoration:none}
.carmel-btn-purple{background:#6b4fbb}
.carmel-btn-blue{background:#2e86de}
.carmel-btn-red{background:#c0392b}
.carmel-btn-ghost{background:#eef2fb;color:#2e86de}
.carmel-table{width:100%;border-collapse:collapse;margin-top:.6em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.55em .6em;text-align:left;font-size:.9em}
.carmel-table th{background:#f4f6fb}
.carmel-doc-ops{display:flex;gap:.4em;align-items:center;flex-wrap:wrap}
.carmel-doc-ops form{margin:0}
.carmel-hint{font-size:.82em;color:#888}
.carmel-mydocs{margin-top:1em;border-top:1px dashed #e0e3ea;padding-top:.8em}
.carmel-mydocs h4{margin:0 0 .4em}
.carmel-mydocs-list{list-style:none;padding:0;margin:0}
.carmel-mydocs-list li{padding:.3em 0;border-top:1px solid #f0f1f5;font-size:.92em}
.carmel-doc-kind{display:inline-block;background:#eee9fb;color:#6b4fbb;border-radius:.3em;padding:.1em .6em;font-size:.85em;margin-right:.4em}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
