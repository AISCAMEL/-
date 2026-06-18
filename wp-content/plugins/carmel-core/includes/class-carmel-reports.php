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
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
