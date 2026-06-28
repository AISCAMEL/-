<?php
/**
 * Reports / sales dashboard.
 *
 * Shortcode [carmel_hq_reports]. Aggregates deals & recorded payments into KPI
 * cards, a status breakdown, revenue by payment type, and (for HQ) a per-store
 * comparison. Scope follows the carmel_view_reports cap: HQ sees all stores,
 * a store_owner sees only their own store. CSV export via admin-post.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Reports {

	/** @var Carmel_Reports|null */
	private static $instance = null;

	const SHORTCODE   = 'carmel_hq_reports';
	const CSV_ACTION  = 'carmel_reports_csv';

	/** Statuses counted as "won" (成約以降) for the conversion rate. */
	const WON = array(
		'contracted', 'delivery_prep', 'delivered', 'after_support', 'closed',
		'bb_agreed', 'bb_doc_prep', 'bb_collected', 'bb_closed',
		'lease_contracted', 'lease_delivered', 'lease_active', 'lease_completed', 'lease_closed',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::CSV_ACTION, array( $this, 'export_csv' ) );
	}

	private function can_view() {
		return is_user_logged_in() && current_user_can( 'carmel_view_reports' );
	}

	/**
	 * The store_id this user is scoped to: 0 = all stores (HQ).
	 *
	 * @return int
	 */
	private function scope_store_id() {
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return 0; // HQ: all stores
		}
		return (int) get_user_meta( get_current_user_id(), 'store_id', true );
	}

	/**
	 * Fetch in-scope deal IDs.
	 *
	 * @param int $store_id
	 * @return int[]
	 */
	private function deal_ids( $store_id ) {
		$args = array(
			'post_type'      => 'carmel_deal',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $store_id ) {
			$args['meta_query'] = array( array( 'key' => 'store_id', 'value' => $store_id ) );
		}
		return get_posts( $args );
	}

	/**
	 * Aggregate metrics over a set of deals.
	 *
	 * @param int[] $ids
	 * @return array
	 */
	private function aggregate( array $ids ) {
		$by_type   = array( 'loan' => 0, 'buyback' => 0, 'lease' => 0 );
		$by_status = array();
		$by_store  = array();
		$revenue   = array_fill_keys( array_keys( Carmel_Payments::payment_types() ), 0.0 );
		$won       = 0;

		foreach ( $ids as $id ) {
			$type   = get_post_meta( $id, 'deal_type', true );
			$status = get_post_meta( $id, 'deal_status', true );
			$store  = (int) get_post_meta( $id, 'store_id', true );

			if ( isset( $by_type[ $type ] ) ) {
				$by_type[ $type ]++;
			}
			$by_status[ $status ] = isset( $by_status[ $status ] ) ? $by_status[ $status ] + 1 : 1;
			$by_store[ $store ]   = isset( $by_store[ $store ] ) ? $by_store[ $store ] + 1 : 1;
			if ( in_array( $status, self::WON, true ) ) {
				$won++;
			}

			$payments = get_post_meta( $id, '_carmel_payments', true );
			if ( is_array( $payments ) ) {
				foreach ( $payments as $p ) {
					if ( isset( $p['status'], $p['type'], $p['amount'] ) && 'paid' === $p['status'] && isset( $revenue[ $p['type'] ] ) ) {
						$revenue[ $p['type'] ] += (float) $p['amount'];
					}
				}
			}
		}

		$total = count( $ids );
		return array(
			'total'      => $total,
			'won'        => $won,
			'conversion' => $total ? round( $won / $total * 100, 1 ) : 0,
			'by_type'    => $by_type,
			'by_status'  => $by_status,
			'by_store'   => $by_store,
			'revenue'    => $revenue,
			'revenue_total' => array_sum( $revenue ),
		);
	}

	/**
	 * Render the dashboard.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! $this->can_view() ) {
			return '<p class="carmel-notice">レポートを表示する権限がありません。</p>';
		}

		$store_id = $this->scope_store_id();
		$ids      = $this->deal_ids( $store_id );
		$m        = $this->aggregate( $ids );
		$is_hq    = current_user_can( 'carmel_manage_stores' );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-reports">';
		echo '<h2>レポート' . ( $is_hq ? '（全店）' : '（自店）' ) . '</h2>';

		// KPI cards.
		echo '<div class="carmel-kpis">';
		echo $this->kpi( '案件総数', number_format( $m['total'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->kpi( '成約数', number_format( $m['won'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->kpi( '転換率', $m['conversion'] . '%' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->kpi( '売上合計', '¥' . number_format( $m['revenue_total'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		// Type breakdown.
		echo '<h3>業務種別</h3><div class="carmel-kpis">';
		echo $this->kpi( 'ローン販売', number_format( $m['by_type']['loan'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->kpi( '車買取', number_format( $m['by_type']['buyback'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->kpi( '自社リース', number_format( $m['by_type']['lease'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		// Revenue by payment type.
		echo '<h3>売上内訳（決済種別）</h3>';
		echo '<table class="carmel-table"><thead><tr><th>種別</th><th>金額</th></tr></thead><tbody>';
		foreach ( Carmel_Payments::payment_types() as $key => $label ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td>¥' . number_format( $m['revenue'][ $key ] ) . '</td></tr>';
		}
		echo '</tbody></table>';

		// Status breakdown.
		echo '<h3>ステータス分布</h3>';
		echo '<table class="carmel-table"><thead><tr><th>ステータス</th><th>件数</th></tr></thead><tbody>';
		$labels = Carmel_MyPage::status_labels();
		arsort( $m['by_status'] );
		foreach ( $m['by_status'] as $status => $n ) {
			$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . (int) $n . '</td></tr>';
		}
		echo '</tbody></table>';

		// 在庫・問い合わせKPI。
		echo $this->inventory_section( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		// 反響→商談→成約 ファネル。
		echo $this->funnel_section( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		// Per-store comparison (HQ only).
		if ( $is_hq ) {
			echo '<h3>加盟店別 案件数</h3>';
			echo '<table class="carmel-table"><thead><tr><th>加盟店</th><th>案件数</th></tr></thead><tbody>';
			arsort( $m['by_store'] );
			foreach ( $m['by_store'] as $sid => $n ) {
				$name = $sid ? get_the_title( $sid ) : '（未割当）';
				echo '<tr><td>' . esc_html( $name ? $name : '#' . $sid ) . '</td><td>' . (int) $n . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		// CSV export.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-csv">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::CSV_ACTION ) . '">';
		echo wp_nonce_field( self::CSV_ACTION, '_carmel_csv_nonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="submit" class="carmel-btn carmel-btn-grey">案件CSVをダウンロード</button></form>';

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Stream a CSV of in-scope deals.
	 */
	public function export_csv() {
		if ( ! $this->can_view() || ! isset( $_POST['_carmel_csv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_carmel_csv_nonce'] ) ), self::CSV_ACTION ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$ids    = $this->deal_ids( $this->scope_store_id() );
		$labels = Carmel_MyPage::status_labels();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename=carmel-deals-' . gmdate( 'Ymd' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fprintf( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM for Excel
		fputcsv( $out, array( '案件ID', '種別', 'ステータス', '申込者', '加盟店ID', '作成日' ) );

		foreach ( $ids as $id ) {
			$status = get_post_meta( $id, 'deal_status', true );
			fputcsv(
				$out,
				array(
					$id,
					get_post_meta( $id, 'deal_type', true ),
					isset( $labels[ $status ] ) ? $labels[ $status ] : $status,
					get_post_meta( $id, 'applicant_name', true ),
					get_post_meta( $id, 'store_id', true ),
					get_the_date( 'Y-m-d', $id ),
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * 在庫・問い合わせKPIセクション。
	 *
	 * @param int $store_id 0=全店（HQ）。
	 * @return string
	 */
	private function inventory_section( $store_id ) {
		// 在庫集計。
		$args = array(
			'post_type'      => 'carmel_vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		if ( $store_id ) {
			$args['meta_query'] = array( array( 'key' => 'store_id', 'value' => $store_id ) );
		}
		$vids       = get_posts( $args );
		$total      = count( $vids );
		$published  = 0;
		$by_status  = array();
		foreach ( $vids as $vid ) {
			if ( in_array( (string) get_post_meta( $vid, 'published', true ), array( '1', 'yes', 'true' ), true ) ) {
				$published++;
			}
			$st = (string) get_post_meta( $vid, 'vehicle_status', true );
			$st = '' !== $st ? $st : '未設定';
			$by_status[ $st ] = isset( $by_status[ $st ] ) ? $by_status[ $st ] + 1 : 1;
		}

		// 問い合わせ集計（在庫問い合わせ：carmel_support）。
		$inq_args = array(
			'post_type'      => 'carmel_support',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => 'support_type', 'value' => 'inventory_inquiry' ),
			),
		);
		if ( $store_id ) {
			$inq_args['meta_query'][] = array( 'key' => 'store_id', 'value' => $store_id );
		}
		$inq_ids   = get_posts( $inq_args );
		$inq_total = count( $inq_ids );
		$since     = strtotime( '-30 days', current_time( 'timestamp' ) );
		$inq_30    = 0;
		foreach ( $inq_ids as $iid ) {
			if ( strtotime( get_post_field( 'post_date', $iid ) ) >= $since ) {
				$inq_30++;
			}
		}

		$out  = '<h3>在庫・問い合わせ</h3>';
		$out .= '<div class="carmel-kpis">';
		$out .= $this->kpi( '在庫総数', number_format( $total ) );
		$out .= $this->kpi( '公開（掲載中）', number_format( $published ) );
		$out .= $this->kpi( '在庫問い合わせ', number_format( $inq_total ) );
		$out .= $this->kpi( '直近30日の問い合わせ', number_format( $inq_30 ) );
		$out .= '</div>';

		if ( ! empty( $by_status ) ) {
			arsort( $by_status );
			$out .= '<table class="carmel-table"><thead><tr><th>在庫ステータス</th><th>台数</th></tr></thead><tbody>';
			foreach ( $by_status as $st => $n ) {
				$out .= '<tr><td>' . esc_html( $st ) . '</td><td>' . (int) $n . '</td></tr>';
			}
			$out .= '</tbody></table>';
		}
		return $out;
	}

	/**
	 * 反響 → 商談化 → 成約 のファネル。
	 *
	 * @param int $store_id 0=全店。
	 * @return string
	 */
	private function funnel_section( $store_id ) {
		$mq = array(
			'relation' => 'AND',
			array( 'key' => 'support_type', 'value' => array( 'line_inquiry', 'inventory_inquiry', 'store_inquiry' ), 'compare' => 'IN' ),
		);
		if ( $store_id ) {
			$mq[] = array( 'key' => 'store_id', 'value' => $store_id );
		}
		$leads = get_posts(
			array(
				'post_type'      => 'carmel_support',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $mq,
			)
		);
		$lead_count = count( $leads );
		$converted  = 0;
		$won        = 0;
		foreach ( $leads as $lid ) {
			$deal = (int) get_post_meta( $lid, 'deal_id', true );
			if ( ! $deal ) {
				continue;
			}
			$converted++;
			if ( in_array( get_post_meta( $deal, 'deal_status', true ), self::WON, true ) ) {
				$won++;
			}
		}
		$cv_rate = $lead_count ? round( $converted / $lead_count * 100, 1 ) : 0;
		$wn_rate = $converted ? round( $won / $converted * 100, 1 ) : 0;

		$out  = '<h3>反響 → 商談 → 成約 ファネル</h3>';
		$out .= '<div class="carmel-funnel">';
		$out .= $this->funnel_step( '反響受付', $lead_count, '' );
		$out .= '<span class="carmel-funnel-arrow">→ ' . esc_html( $cv_rate ) . '%</span>';
		$out .= $this->funnel_step( '商談化', $converted, '' );
		$out .= '<span class="carmel-funnel-arrow">→ ' . esc_html( $wn_rate ) . '%</span>';
		$out .= $this->funnel_step( '成約', $won, '' );
		$out .= '</div>';
		$out .= '<p class="carmel-funnel-note">反響（LINE/在庫/店舗問い合わせ）からの商談化率・成約率。商談化＝反響から起票された案件、成約＝その案件が成約以降ステータス。</p>';
		return $out;
	}

	private function funnel_step( $label, $value, $extra ) {
		return '<div class="carmel-funnel-step"><div class="carmel-funnel-num">' . esc_html( number_format( (int) $value ) ) . '</div>'
			. '<div class="carmel-funnel-lbl">' . esc_html( $label ) . '</div></div>';
	}

	private function kpi( $label, $value ) {
		return '<div class="carmel-kpi"><div class="carmel-kpi-val">' . esc_html( $value ) . '</div>'
			. '<div class="carmel-kpi-label">' . esc_html( $label ) . '</div></div>';
	}

	private function styles() {
		return '<style>
.carmel-reports{font-size:14px}
.carmel-reports h3{margin-top:1.5em}
.carmel-kpis{display:flex;gap:.8em;flex-wrap:wrap;margin:1em 0}
.carmel-kpi{flex:1;min-width:130px;border:1px solid #e0e3ea;border-radius:.6em;padding:1em;text-align:center;background:#fff}
.carmel-kpi-val{font-size:1.7em;font-weight:bold;color:#1a1a2e}
.carmel-kpi-label{font-size:.8em;color:#666;margin-top:.3em}
.carmel-table{width:100%;border-collapse:collapse;margin-top:.6em;max-width:520px}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.5em .7em;text-align:left}
.carmel-table th{background:#f4f6fb}
.carmel-csv{margin-top:1.5em}
.carmel-btn{border:0;border-radius:.3em;padding:.5em 1em;color:#fff;cursor:pointer}
.carmel-btn-grey{background:#34495e}
.carmel-funnel{display:flex;align-items:center;gap:.6em;flex-wrap:wrap;margin:.6em 0}
.carmel-funnel-step{border:1px solid #e0e3ea;border-radius:.6em;padding:.8em 1.2em;text-align:center;background:#fff;min-width:90px}
.carmel-funnel-num{font-size:1.5em;font-weight:bold;color:#6b4fbb}
.carmel-funnel-lbl{font-size:.8em;color:#666}
.carmel-funnel-arrow{color:#16a085;font-weight:bold;font-size:.9em}
.carmel-funnel-note{font-size:.8em;color:#888}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
