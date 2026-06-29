<?php
/**
 * 在庫共有の成約に伴う売上配分（手数料）管理。
 *
 * 他店の在庫（保有店 source_store_id）を、別の加盟店（販売店 store_id）が
 * 販売して成約した場合に、本部の規定料率で手数料（保有店への配分）を算出・記録し、
 * 精算状況を管理する。
 *
 * 適用条件：案件に source_store_id が設定され、販売店（store_id）と異なること。
 * 手数料額 = 販売価格 × 料率（既定5%。carmel_commission_rate / フィルタで調整）。
 *
 * 案件の source_store_id は ACF（案件情報）で設定するか、在庫共有の取り寄せ
 * 依頼から案件化する運用で記録する。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Commission {

	/** @var Carmel_Commission|null */
	private static $instance = null;

	const SHORTCODE      = 'carmel_hq_commissions';
	const SETTLE_ACTION  = 'carmel_commission_settle';
	const NONCE          = 'carmel_commission_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::SETTLE_ACTION, array( $this, 'handle_settle' ) );
		// 成約系ステータスで自動再計算。
		add_action( 'carmel_deal_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/** 手数料率（％）。 */
	public static function rate() {
		$r = get_option( 'carmel_commission_rate', 5 );
		return (float) apply_filters( 'carmel_commission_rate', is_numeric( $r ) ? $r : 5 );
	}

	/** 配分対象となる成約系ステータス。 */
	private static function settled_statuses() {
		return apply_filters(
			'carmel_commission_trigger_statuses',
			array( 'contracted', 'delivery_prep', 'delivered', 'bb_agreed', 'lease_contracted', 'lease_delivered' )
		);
	}

	public function on_status_changed( $deal_id, $new, $old ) {
		if ( in_array( $new, self::settled_statuses(), true ) ) {
			$this->recompute( $deal_id );
		}
	}

	/* --------------------------------------------------------------------- *
	 * 計算
	 * --------------------------------------------------------------------- */

	/** 販売価格（査定額→車両価格の順）。 */
	private function sale_price( $deal_id ) {
		$price = get_post_meta( $deal_id, 'appraisal_amount', true );
		if ( '' === (string) $price || (float) $price <= 0 ) {
			$vehicle_id = (int) get_post_meta( $deal_id, 'vehicle_id', true );
			$price      = $vehicle_id ? get_post_meta( $vehicle_id, 'price', true ) : 0;
		}
		return (float) $price;
	}

	/**
	 * 案件の手数料情報を算出。
	 *
	 * @param int $deal_id
	 * @return array{applies:bool,holding:int,selling:int,price:float,rate:float,amount:int}
	 */
	public function compute( $deal_id ) {
		$holding = (int) get_post_meta( $deal_id, 'source_store_id', true );
		$selling = (int) get_post_meta( $deal_id, 'store_id', true );
		$applies = $holding > 0 && $selling > 0 && $holding !== $selling;
		$price   = $this->sale_price( $deal_id );
		$rate    = self::rate();
		$amount  = $applies ? (int) round( $price * $rate / 100 ) : 0;

		return array(
			'applies' => $applies,
			'holding' => $holding,
			'selling' => $selling,
			'price'   => $price,
			'rate'    => $rate,
			'amount'  => $amount,
		);
	}

	/** 算出結果を案件メタへ保存。 */
	public function recompute( $deal_id ) {
		$c = $this->compute( $deal_id );
		if ( ! $c['applies'] ) {
			return $c;
		}
		update_post_meta( $deal_id, 'commission_rate', $c['rate'] );
		update_post_meta( $deal_id, 'commission_amount', $c['amount'] );
		if ( '' === (string) get_post_meta( $deal_id, 'commission_settled', true ) ) {
			update_post_meta( $deal_id, 'commission_settled', 0 );
		}
		do_action( 'carmel_commission_recomputed', $deal_id, $c );
		return $c;
	}

	/* --------------------------------------------------------------------- *
	 * 精算トグル
	 * --------------------------------------------------------------------- */

	public function handle_settle() {
		$deal_id  = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );

		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::SETTLE_ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$now = in_array( (string) get_post_meta( $deal_id, 'commission_settled', true ), array( '1', 'yes', 'true' ), true );
		update_post_meta( $deal_id, 'commission_settled', $now ? 0 : 1 );
		update_post_meta( $deal_id, 'commission_settled_at', $now ? '' : current_time( 'mysql' ) );

		wp_safe_redirect( add_query_arg( 'carmel_comm_pay', $now ? 'unset' : 'set', $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 画面（本部＝全件、加盟店オーナー＝自店関与分の閲覧）
	 * --------------------------------------------------------------------- */

	public function render() {
		$is_hq = current_user_can( 'carmel_manage_stores' );
		$store = (int) get_user_meta( get_current_user_id(), 'store_id', true );
		if ( ! $is_hq && ! ( current_user_can( 'carmel_view_reports' ) && $store ) ) {
			return '<p class="carmel-notice">手数料管理を表示する権限がありません。</p>';
		}

		// source_store_id が設定された案件を抽出。
		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array(
					array( 'key' => 'source_store_id', 'value' => '', 'compare' => '!=' ),
				),
			)
		);

		$rows = array();
		foreach ( $deals as $deal ) {
			$c = $this->compute( $deal->ID );
			if ( ! $c['applies'] ) {
				continue;
			}
			if ( ! $is_hq && $store !== $c['holding'] && $store !== $c['selling'] ) {
				continue; // 加盟店は自店が関与する配分のみ。
			}
			$rows[] = array( 'deal' => $deal, 'c' => $c );
		}

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-comm-pay"><h2>在庫共有 売上配分（手数料）</h2>';
		echo '<p class="carmel-cp-lead">他店在庫を販売した成約の手数料配分です（料率 ' . esc_html( $this->num( self::rate() ) ) . '%）。</p>';

		if ( empty( $rows ) ) {
			echo '<p>対象の配分はありません。</p></div>';
			return ob_get_clean();
		}

		// サマリー。
		$total = 0;
		$unpaid = 0;
		foreach ( $rows as $r ) {
			$total += $r['c']['amount'];
			if ( ! $this->is_settled( $r['deal']->ID ) ) {
				$unpaid += $r['c']['amount'];
			}
		}
		echo '<div class="carmel-cp-cards">';
		echo '<div class="carmel-cp-card"><div class="carmel-cp-num">' . count( $rows ) . '</div><div>対象成約</div></div>';
		echo '<div class="carmel-cp-card"><div class="carmel-cp-num">¥' . esc_html( number_format( $total ) ) . '</div><div>手数料合計</div></div>';
		echo '<div class="carmel-cp-card"><div class="carmel-cp-num">¥' . esc_html( number_format( $unpaid ) ) . '</div><div>未精算</div></div>';
		echo '</div>';

		echo '<table class="carmel-table"><thead><tr><th>案件</th><th>保有店</th><th>販売店</th><th>販売価格</th><th>手数料</th><th>精算</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$deal = $r['deal'];
			$c    = $r['c'];
			$settled = $this->is_settled( $deal->ID );
			echo '<tr>';
			echo '<td>#' . (int) $deal->ID . '<br><small>' . esc_html( get_post_meta( $deal->ID, 'applicant_name', true ) ) . '</small></td>';
			echo '<td>' . esc_html( $this->store_name( $c['holding'] ) ) . '</td>';
			echo '<td>' . esc_html( $this->store_name( $c['selling'] ) ) . '</td>';
			echo '<td class="num">¥' . esc_html( number_format( $c['price'] ) ) . '</td>';
			echo '<td class="num">¥' . esc_html( number_format( $c['amount'] ) ) . '</td>';
			echo '<td>' . $this->settle_cell( $deal->ID, $settled, $is_hq ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	private function settle_cell( $deal_id, $settled, $is_hq ) {
		if ( ! $is_hq ) {
			return $settled ? '<span class="carmel-cp-done">精算済</span>' : '<span class="carmel-cp-wait">未精算</span>';
		}
		$nonce = wp_create_nonce( self::SETTLE_ACTION . '_' . $deal_id );
		$label = $settled ? '未精算に戻す' : '精算済にする';
		$cls   = $settled ? 'carmel-btn-ghost' : 'carmel-btn-green';
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::SETTLE_ACTION ) . '">'
			. '<input type="hidden" name="deal_id" value="' . (int) $deal_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-btn ' . $cls . '">' . esc_html( $label ) . '</button></form>';
	}

	private function is_settled( $deal_id ) {
		return in_array( (string) get_post_meta( $deal_id, 'commission_settled', true ), array( '1', 'yes', 'true' ), true );
	}

	private function store_name( $store_id ) {
		$n = $store_id ? (string) get_post_meta( $store_id, 'store_name', true ) : '';
		return $n ? $n : ( $store_id ? '#' . $store_id : '—' );
	}

	private function num( $n ) {
		$n = (float) $n;
		return ( floor( $n ) === $n ) ? (string) (int) $n : (string) $n;
	}

	private function banner() {
		$key = isset( $_GET['carmel_comm_pay'] ) ? sanitize_key( $_GET['carmel_comm_pay'] ) : '';
		$map = array(
			'set'   => array( 'success', '精算済に更新しました。' ),
			'unset' => array( 'success', '未精算に戻しました。' ),
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $key ][0] ) . '">' . esc_html( $map[ $key ][1] ) . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-comm-pay{font-size:14px}
.carmel-cp-lead{color:#7a7488}
.carmel-cp-cards{display:flex;gap:.7em;flex-wrap:wrap;margin:1em 0}
.carmel-cp-card{border:1px solid #e0e3ea;border-radius:.5em;padding:.7em 1.1em;min-width:110px;text-align:center;background:#fff}
.carmel-cp-num{font-size:1.4em;font-weight:bold;color:#6b4fbb}
.carmel-table{width:100%;border-collapse:collapse;margin-top:.6em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.55em .6em;text-align:left;font-size:.9em}
.carmel-table th{background:#f4f6fb}
.num{text-align:right;font-variant-numeric:tabular-nums}
.carmel-cp-done{color:#16a085;font-weight:bold}
.carmel-cp-wait{color:#c0392b}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.4em .8em;color:#fff;cursor:pointer;font-size:.82em}
.carmel-btn-green{background:#16a085}.carmel-btn-ghost{background:#eef2fb;color:#2e86de}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
