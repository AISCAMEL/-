<?php
/**
 * 本部統合ダッシュボード。
 *
 * ショートコード [carmel_hq_dashboard] を /hq トップに設置すると、案件・在庫・
 * 問い合わせ・手数料・コミュニティの主要KPIを1画面に集約し、各管理画面への
 * 導線を表示する。スコープは carmel_view_reports（本部=全店 / 加盟店=自店）。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_HQ_Dashboard {

	/** @var Carmel_HQ_Dashboard|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_hq_dashboard';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	private function can_view() {
		return is_user_logged_in() && current_user_can( 'carmel_view_reports' );
	}

	/** 0 = 全店（本部）。 */
	private function scope_store_id() {
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return 0;
		}
		return (int) get_user_meta( get_current_user_id(), 'store_id', true );
	}

	/** store_id でスコープした meta_query 断片。 */
	private function scope_meta( $store_id ) {
		return $store_id ? array( array( 'key' => 'store_id', 'value' => $store_id ) ) : array();
	}

	public function render() {
		if ( ! $this->can_view() ) {
			return '<p class="carmel-notice">ダッシュボードを表示する権限がありません。</p>';
		}
		$store_id = $this->scope_store_id();
		$is_hq    = current_user_can( 'carmel_manage_stores' );

		$deals = $this->deal_stats( $store_id );
		$inv   = $this->inventory_stats( $store_id );
		$inq   = $this->inquiry_stats( $store_id );
		$comm  = $this->commission_stats( $store_id );
		$cmty  = $this->community_stats();

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-dash">';
		echo '<h2>ダッシュボード' . ( $is_hq ? '（全店）' : '（自店）' ) . '</h2>';

		// 案件KPI。
		echo '<h3>案件</h3><div class="carmel-dash-cards">';
		echo $this->card( '案件総数', number_format( $deals['total'] ), '#2e86de' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '成約', number_format( $deals['won'] ), '#16a085' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '転換率', $deals['conversion'] . '%', '#6b4fbb' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '進行中', number_format( $deals['open'] ), '#e67e22' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '売上合計', '¥' . number_format( $deals['revenue'] ), '#1a1a2e' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		// 在庫・問い合わせKPI。
		echo '<h3>在庫・問い合わせ</h3><div class="carmel-dash-cards">';
		echo $this->card( '在庫総数', number_format( $inv['total'] ), '#2e86de' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '掲載中', number_format( $inv['published'] ), '#16a085' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '問い合わせ(累計)', number_format( $inq['total'] ), '#6b4fbb' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '問い合わせ(30日)', number_format( $inq['recent'] ), '#e67e22' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		// 手数料・コミュニティ。
		echo '<h3>在庫共有・コミュニティ</h3><div class="carmel-dash-cards">';
		echo $this->card( '手数料 未精算', number_format( $comm['unpaid'] ), '#c0392b' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '未精算額', '¥' . number_format( $comm['unpaid_amount'] ), '#c0392b' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( 'トピック', number_format( $cmty['topics'] ), '#2e86de' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->card( '未回答', number_format( $cmty['unanswered'] ), '#e67e22' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		echo $this->nav( $is_hq ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * 集計
	 * --------------------------------------------------------------------- */

	private function deal_stats( $store_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $this->scope_meta( $store_id ),
			)
		);
		$won     = 0;
		$revenue = 0.0;
		$wonset  = class_exists( 'Carmel_Reports' ) ? Carmel_Reports::WON : array( 'contracted', 'delivered', 'closed' );
		foreach ( $ids as $id ) {
			if ( in_array( get_post_meta( $id, 'deal_status', true ), $wonset, true ) ) {
				$won++;
			}
			$payments = get_post_meta( $id, '_carmel_payments', true );
			if ( is_array( $payments ) ) {
				foreach ( $payments as $p ) {
					if ( isset( $p['status'], $p['amount'] ) && 'paid' === $p['status'] ) {
						$revenue += (float) $p['amount'];
					}
				}
			}
		}
		$total = count( $ids );
		return array(
			'total'      => $total,
			'won'        => $won,
			'open'       => max( 0, $total - $won ),
			'conversion' => $total ? round( $won / $total * 100, 1 ) : 0,
			'revenue'    => $revenue,
		);
	}

	private function inventory_stats( $store_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $this->scope_meta( $store_id ),
			)
		);
		$published = 0;
		foreach ( $ids as $id ) {
			if ( in_array( (string) get_post_meta( $id, 'published', true ), array( '1', 'yes', 'true' ), true ) ) {
				$published++;
			}
		}
		return array( 'total' => count( $ids ), 'published' => $published );
	}

	private function inquiry_stats( $store_id ) {
		$mq = array(
			'relation' => 'AND',
			array( 'key' => 'support_type', 'value' => 'inventory_inquiry' ),
		);
		if ( $store_id ) {
			$mq[] = array( 'key' => 'store_id', 'value' => $store_id );
		}
		$ids   = get_posts(
			array(
				'post_type'      => 'carmel_support',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $mq,
			)
		);
		$since  = strtotime( '-30 days', current_time( 'timestamp' ) );
		$recent = 0;
		foreach ( $ids as $id ) {
			if ( strtotime( get_post_field( 'post_date', $id ) ) >= $since ) {
				$recent++;
			}
		}
		return array( 'total' => count( $ids ), 'recent' => $recent );
	}

	private function commission_stats( $store_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => 'source_store_id', 'value' => '', 'compare' => '!=' ),
				),
			)
		);
		$unpaid  = 0;
		$amount  = 0.0;
		$has_cls = class_exists( 'Carmel_Commission' );
		foreach ( $ids as $id ) {
			$holding = (int) get_post_meta( $id, 'source_store_id', true );
			$selling = (int) get_post_meta( $id, 'store_id', true );
			if ( ! $holding || $holding === $selling ) {
				continue;
			}
			// 加盟店スコープなら自店が関与する配分のみ。
			if ( $store_id && $store_id !== $holding && $store_id !== $selling ) {
				continue;
			}
			$settled = in_array( (string) get_post_meta( $id, 'commission_settled', true ), array( '1', 'yes', 'true' ), true );
			if ( $settled ) {
				continue;
			}
			$unpaid++;
			if ( $has_cls ) {
				$c       = Carmel_Commission::instance()->compute( $id );
				$amount += (float) $c['amount'];
			} else {
				$amount += (float) get_post_meta( $id, 'commission_amount', true );
			}
		}
		return array( 'unpaid' => $unpaid, 'unpaid_amount' => $amount );
	}

	private function community_stats() {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_community',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$unanswered = 0;
		foreach ( $ids as $id ) {
			if ( (int) get_comments_number( $id ) === 0 ) {
				$unanswered++;
			}
		}
		return array( 'topics' => count( $ids ), 'unanswered' => $unanswered );
	}

	/* --------------------------------------------------------------------- *
	 * 表示補助
	 * --------------------------------------------------------------------- */

	private function nav( $is_hq ) {
		$links = array(
			array( 'hq', 'carmel_hq_page_slug', '本部管理（審査・契約・レポート）', $is_hq ),
			array( 'store-inventory', 'carmel_store_inventory_page_slug', '在庫管理・共有', true ),
			array( 'store-billing', 'carmel_billing_page_slug', '帳票・契約書', true ),
			array( 'community', 'carmel_community_page_slug', 'コミュニティ', true ),
		);
		$out = '<div class="carmel-dash-nav">';
		foreach ( $links as $l ) {
			if ( ! $l[3] ) {
				continue;
			}
			$url  = home_url( '/' . ltrim( apply_filters( $l[1], $l[0] ), '/' ) );
			$out .= '<a href="' . esc_url( $url ) . '">' . esc_html( $l[2] ) . ' →</a>';
		}
		$out .= '</div>';
		return $out;
	}

	private function card( $label, $value, $color ) {
		return '<div class="carmel-dash-card" style="border-top:3px solid ' . esc_attr( $color ) . '">'
			. '<div class="carmel-dash-val">' . esc_html( $value ) . '</div>'
			. '<div class="carmel-dash-label">' . esc_html( $label ) . '</div></div>';
	}

	private function styles() {
		return '<style>
.carmel-dash{font-size:14px}
.carmel-dash h3{margin:1.4em 0 .4em}
.carmel-dash-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:.7em}
.carmel-dash-card{border:1px solid #e0e3ea;border-radius:.6em;padding:.9em;text-align:center;background:#fff}
.carmel-dash-val{font-size:1.5em;font-weight:bold;color:#1a1a2e}
.carmel-dash-label{font-size:.78em;color:#666;margin-top:.3em}
.carmel-dash-nav{display:flex;gap:1em;flex-wrap:wrap;margin:1.6em 0 .5em}
.carmel-dash-nav a{text-decoration:none;color:#6b4fbb;font-weight:600}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
