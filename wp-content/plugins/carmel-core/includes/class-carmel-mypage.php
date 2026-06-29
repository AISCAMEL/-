<?php
/**
 * Customer "My Page".
 *
 * Renders via the [carmel_mypage] shortcode on the /mypage page (gated to the
 * customer role by Carmel_Access_Control). Shows the logged-in customer's own
 * deals with a status-driven PHASE display (§7.1). For loan deals it renders
 * the 7-phase stepper; buyback/lease use a simplified status view. Delivered
 * deals also surface the after-service countdown (車検 / 保険).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_MyPage {

	/** @var Carmel_MyPage|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_mypage';
	const GUIDE_SHORTCODE = 'carmel_customer_guide';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Comprehensive deal status labels. */
	public static function status_labels() {
		return array(
			// loan
			'provisional'   => '仮申込', 'scored' => 'AIスコア済', 'screening' => '信販審査中',
			'approved'      => '審査OK', 'rejected' => '審査NG', 'matched' => '加盟店マッチング',
			'doc_prep'      => '書類準備中', 'contracted' => '契約完了', 'delivery_prep' => '納車準備中',
			'delivered'     => '納車済', 'after_support' => 'アフターサポート中', 'closed' => '案件クローズ',
			// buyback
			'appraisal_request' => '査定申込', 'appraising' => '査定中', 'quoted' => '査定額提示',
			'bb_agreed' => '成約', 'bb_declined' => '不成立', 'bb_doc_prep' => '書類準備中',
			'bb_collected' => '引取完了', 'bb_closed' => 'クローズ',
			// lease
			'lease_request' => 'リース申込', 'lease_screening' => 'リース審査', 'lease_contracted' => '契約完了',
			'lease_delivered' => '納車済', 'lease_active' => 'リース中', 'lease_completed' => '満了/完済',
			'lease_closed' => 'クローズ',
		);
	}

	/** loan: deal_status => PHASE number (1-7). */
	public static function loan_phase_map() {
		return array(
			'provisional' => 1,
			'scored' => 2, 'screening' => 2,
			'approved' => 3, 'rejected' => 3, 'matched' => 3,
			'doc_prep' => 4, 'contracted' => 4,
			'delivery_prep' => 5,
			'delivered' => 6,
			'after_support' => 7, 'closed' => 7,
		);
	}

	/** PHASE => [ title, description ]. */
	public static function phase_info() {
		return array(
			1 => array( '仮申込完了', '申込内容を受け付けました。必要書類のご準備をお願いします。' ),
			2 => array( '審査中', '審査を進めています。結果が出るまでお待ちください。' ),
			3 => array( '審査結果', '審査結果をご確認ください。' ),
			4 => array( '契約・書類', 'ご契約手続きを進めています。署名のご案内をお待ちください。' ),
			5 => array( '納車準備', '納車に向けた準備・陸送手配を進めています。' ),
			6 => array( '納車済', '納車が完了しました。各種書類をご確認いただけます。' ),
			7 => array( 'アフターサポート', '車検・保険・サポートのご案内はこちらから。' ),
		);
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_shortcode( self::GUIDE_SHORTCODE, array( $this, 'render_guide' ) );
	}

	/**
	 * 顧客向け 使い方ガイド（静的・アコーディオン）。
	 *
	 * @return string
	 */
	public function render_guide() {
		$items = apply_filters(
			'carmel_customer_guide_items',
			array(
				array(
					'お手続き状況の確認',
					'マイページでは、お申込みからご納車までの進み具合（フェーズ）をいつでもご確認いただけます。審査結果やご契約のご案内もこちらに表示されます。',
				),
				array(
					'必要書類の提出',
					'本人確認書類・収入証明などは、マイページの「書類のご提出」からJPG/PNG/PDF（各10MBまで）でアップロードできます。提出済みの書類も一覧で確認できます。',
				),
				array(
					'発行書類の確認（見積書・請求書・契約書）',
					'担当店が発行した見積書・請求書・各種契約書は、案件カードの「発行書類」または「発行書類」ページから表示・印刷（PDF保存）いただけます。',
				),
				array(
					'お車を探す・問い合わせる',
					'在庫ページで気になるお車を探し、詳細ページの「このお車について問い合わせる」からご質問いただけます。お問い合わせ後は担当店よりご連絡します。',
				),
				array(
					'お気に入り・比較',
					'在庫の♥でお気に入り登録、「比較に追加」で最大4台を並べて比較できます。「保存した検索条件」を登録すると、条件に合う新着が入荷した際にお知らせします。',
				),
				array(
					'お支払い・返済状況',
					'お申込金や保証などのお支払い、ローン/リースの返済予定と入金状況はマイページでご確認いただけます。お支払い期日が近づくとご案内が届きます。',
				),
				array(
					'納車後（車検・保険）',
					'ご納車後は、車検満了日・保険満了日が近づくと自動でご案内します。マイページの「愛車情報」に残り日数が表示されます。',
				),
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-mypage"><h2>カーメルの使い方ガイド</h2>';
		echo '<p class="carmel-guide-lead">はじめての方へ。よくあるご利用の流れをまとめています。</p>';
		echo '<div class="carmel-cguide">';
		foreach ( $items as $i => $it ) {
			$open = ( 0 === $i ) ? ' open' : '';
			echo '<details class="carmel-cguide-item"' . esc_attr( $open ) . '><summary>' . esc_html( $it[0] ) . '</summary>';
			echo '<div class="carmel-cguide-body">' . esc_html( $it[1] ) . '</div></details>';
		}
		echo '</div></div>';
		echo '<style>
.carmel-guide-lead{color:#666}
.carmel-cguide-item{border:1px solid #e0e3ea;border-radius:.5em;margin:.5em 0;background:#fff;padding:.3em .9em}
.carmel-cguide-item summary{cursor:pointer;font-weight:700;padding:.5em 0}
.carmel-cguide-body{color:#46414f;line-height:1.85;padding:.2em 0 .7em}
</style>';
		return ob_get_clean();
	}

	/**
	 * Render the current customer's deals.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p class="carmel-notice">ログインするとお手続き状況をご確認いただけます。</p>';
		}

		$user_id = get_current_user_id();
		$deals   = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'meta_query'     => array(
					array(
						'key'   => 'customer_id',
						'value' => $user_id,
					),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-mypage">';
		echo '<h2>お手続き状況</h2>';

		if ( empty( $deals ) ) {
			echo '<p>現在お手続き中の案件はありません。</p></div>';
			return ob_get_clean();
		}

		foreach ( $deals as $deal ) {
			echo $this->render_deal( $deal ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render a single deal card.
	 *
	 * @param WP_Post $deal
	 * @return string
	 */
	private function render_deal( WP_Post $deal ) {
		$type   = get_post_meta( $deal->ID, 'deal_type', true );
		$status = get_post_meta( $deal->ID, 'deal_status', true );
		$labels = self::status_labels();
		$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

		$out  = '<div class="carmel-card">';
		$out .= '<div class="carmel-card-head"><span class="carmel-type">' . esc_html( $this->type_label( $type ) ) . '</span>';
		$out .= '<span class="carmel-deal-id">案件 #' . (int) $deal->ID . '</span></div>';

		if ( 'loan' === $type ) {
			$out .= $this->render_phases( $deal, $status );
		} else {
			$out .= '<p class="carmel-status-line">現在の状況：<strong>' . esc_html( $label ) . '</strong></p>';
		}

		// After-service countdown once delivered.
		if ( in_array( $status, array( 'delivered', 'after_support', 'lease_delivered', 'lease_active' ), true ) ) {
			$out .= $this->render_after_service( $deal );
		}

		// Repayment schedule / status (loan & lease).
		$out .= $this->render_repayments( $deal );

		// 発行書類（見積書・請求書・各種契約書）。
		if ( class_exists( 'Carmel_Billing' ) ) {
			$out .= Carmel_Billing::customer_deal_documents_html( $deal->ID, false );
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Render the deal's repayment schedule with paid / upcoming / overdue state.
	 *
	 * @param WP_Post $deal
	 * @return string
	 */
	private function render_repayments( WP_Post $deal ) {
		$rows = get_posts(
			array(
				'post_type'      => 'carmel_repayment',
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'meta_query'     => array(
					array( 'key' => 'deal_id', 'value' => $deal->ID ),
				),
				'meta_key'       => 'due_date',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
			)
		);
		if ( empty( $rows ) ) {
			return '';
		}

		$today      = strtotime( current_time( 'Y-m-d' ) );
		$paid_count = 0;
		$next_due   = null;
		$body       = '';

		foreach ( $rows as $r ) {
			$due    = get_post_meta( $r->ID, 'due_date', true );
			$amount = (float) get_post_meta( $r->ID, 'amount', true );
			$paid    = (string) get_post_meta( $r->ID, 'paid_flag', true );
			$is_paid = in_array( $paid, array( '1', 'yes', 'true' ), true );

			$state_cls = 'carmel-rp-upcoming';
			$state_txt = '予定';
			if ( $is_paid ) {
				$state_cls = 'carmel-rp-paid';
				$state_txt = '入金済';
				$paid_count++;
			} elseif ( $due && strtotime( $due ) < $today ) {
				$state_cls = 'carmel-rp-overdue';
				$delay     = (int) get_post_meta( $r->ID, 'delay_days', true );
				$state_txt = '延滞' . ( $delay ? '（' . $delay . '日）' : '' );
			} elseif ( null === $next_due && $due ) {
				$next_due = $due;
			}

			$body .= '<tr class="' . $state_cls . '"><td>' . esc_html( $due ) . '</td>'
				. '<td>¥' . esc_html( number_format( $amount ) ) . '</td>'
				. '<td>' . esc_html( $state_txt ) . '</td></tr>';
		}

		$out  = '<div class="carmel-repay"><h4>返済状況</h4>';
		$out .= '<p class="carmel-repay-sum">入金済 ' . (int) $paid_count . ' / ' . count( $rows ) . ' 回';
		if ( $next_due ) {
			$out .= '　次回お支払い：<strong>' . esc_html( $next_due ) . '</strong>';
		}
		$out .= '</p>';
		$out .= '<table class="carmel-repay-table"><thead><tr><th>支払期日</th><th>金額</th><th>状況</th></tr></thead><tbody>'
			. $body . '</tbody></table></div>';
		return $out;
	}

	/**
	 * Render the 7-phase stepper + the current phase message for loan deals.
	 *
	 * @param WP_Post $deal
	 * @param string  $status
	 * @return string
	 */
	private function render_phases( WP_Post $deal, $status ) {
		$map     = self::loan_phase_map();
		$current = isset( $map[ $status ] ) ? $map[ $status ] : 1;
		$info    = self::phase_info();

		$out = '<div class="carmel-stepper">';
		for ( $p = 1; $p <= 7; $p++ ) {
			$cls = 'carmel-step';
			if ( $p < $current ) {
				$cls .= ' done';
			} elseif ( $p === $current ) {
				$cls .= ' active';
			}
			$out .= '<div class="' . $cls . '"><span class="carmel-step-num">' . $p . '</span>'
				. '<span class="carmel-step-label">' . esc_html( $info[ $p ][0] ) . '</span></div>';
		}
		$out .= '</div>';

		// Phase message box (special-cases the screening result).
		$out .= '<div class="carmel-phase-msg">';
		if ( 'rejected' === $status ) {
			$reason = get_post_meta( $deal->ID, 'screening_reason', true );
			$out   .= '<p class="carmel-ng"><strong>審査結果：今回はご希望に添えませんでした。</strong></p>';
			if ( $reason ) {
				$out .= '<p>理由：' . esc_html( $reason ) . '</p>';
			}
			$out .= '<p>条件を見直して再度お申込みいただけます。</p>';
		} elseif ( 'approved' === $status || 'matched' === $status ) {
			$out .= '<p class="carmel-ok"><strong>審査に通過しました。</strong>次のお手続きをご案内します。</p>';
		} else {
			$out .= '<p>' . esc_html( $info[ $current ][1] ) . '</p>';
		}

		// 納車準備中は確定した納車日・陸送費を表示。
		if ( 'delivery_prep' === $status ) {
			$ddate = get_post_meta( $deal->ID, 'delivery_date', true );
			$fee   = get_post_meta( $deal->ID, 'transport_fee', true );
			$km    = get_post_meta( $deal->ID, 'transport_distance_km', true );
			if ( $ddate ) {
				$out .= '<p>納車予定日：<strong>' . esc_html( $ddate ) . '</strong></p>';
			}
			if ( '' !== $fee ) {
				$out .= '<p>陸送費：<strong>¥' . esc_html( number_format( (float) $fee ) ) . '</strong>'
					. ( '' !== $km ? '（約' . esc_html( $km ) . 'km）' : '' ) . '</p>';
			}
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Render car inspection / insurance countdowns.
	 *
	 * @param WP_Post $deal
	 * @return string
	 */
	private function render_after_service( WP_Post $deal ) {
		$items = array();

		// 車検満了日（紐付け車両 or 車検レコード）
		$vehicle_id = (int) get_post_meta( $deal->ID, 'vehicle_id', true );
		$expiry     = $vehicle_id ? get_post_meta( $vehicle_id, 'inspection_expiry', true ) : '';
		if ( $expiry ) {
			$items[] = array( '車検満了', $expiry, $this->days_left( $expiry ) );
		}

		// 保険満了日（carmel_insurance レコード）
		$ins = get_posts(
			array(
				'post_type'      => 'carmel_insurance',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array( 'key' => 'deal_id', 'value' => $deal->ID ),
				),
			)
		);
		if ( ! empty( $ins ) ) {
			$end = get_post_meta( $ins[0], 'end_date', true );
			if ( $end ) {
				$items[] = array( '保険満了', $end, $this->days_left( $end ) );
			}
		}

		if ( empty( $items ) ) {
			return '';
		}

		$out = '<div class="carmel-after"><h4>愛車情報</h4><div class="carmel-after-grid">';
		foreach ( $items as $it ) {
			list( $title, $date, $days ) = $it;
			$state = ( null !== $days && $days <= 30 ) ? ' carmel-soon' : '';
			$out  .= '<div class="carmel-after-item' . $state . '">';
			$out  .= '<div class="carmel-after-title">' . esc_html( $title ) . '</div>';
			$out  .= '<div class="carmel-after-date">' . esc_html( $date ) . '</div>';
			if ( null !== $days ) {
				$out .= '<div class="carmel-after-days">' . ( $days >= 0 ? 'あと' . (int) $days . '日' : (int) abs( $days ) . '日超過' ) . '</div>';
			}
			$out .= '</div>';
		}
		$out .= '</div></div>';
		return $out;
	}

	/**
	 * Whole days from today until $date (Y-m-d). Null if unparseable.
	 *
	 * @param string $date
	 * @return int|null
	 */
	private function days_left( $date ) {
		$ts = strtotime( $date );
		if ( false === $ts ) {
			return null;
		}
		$today = strtotime( current_time( 'Y-m-d' ) );
		return (int) floor( ( $ts - $today ) / DAY_IN_SECONDS );
	}

	private function type_label( $type ) {
		$labels = array( 'loan' => 'ローン販売', 'buyback' => '車買取', 'lease' => '自社リース' );
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ( $type ? $type : '案件' );
	}

	private function styles() {
		return '<style>
.carmel-mypage{font-size:14px;max-width:760px}
.carmel-card{border:1px solid #e0e3ea;border-radius:.6em;padding:1.2em;margin:1em 0;background:#fff}
.carmel-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1em}
.carmel-type{background:#2e86de;color:#fff;border-radius:.3em;padding:.2em .8em;font-weight:bold}
.carmel-deal-id{color:#888;font-size:.9em}
.carmel-status-line{font-size:1.05em}
.carmel-stepper{display:flex;gap:.2em;margin:1em 0;overflow-x:auto}
.carmel-step{flex:1;min-width:64px;text-align:center;position:relative;opacity:.45}
.carmel-step.done{opacity:.8}.carmel-step.active{opacity:1}
.carmel-step-num{display:inline-flex;width:1.9em;height:1.9em;align-items:center;justify-content:center;border-radius:50%;background:#cdd2dc;color:#fff;font-weight:bold}
.carmel-step.done .carmel-step-num{background:#16a085}
.carmel-step.active .carmel-step-num{background:#2e86de;box-shadow:0 0 0 3px rgba(46,134,222,.25)}
.carmel-step-label{display:block;font-size:.72em;margin-top:.3em;line-height:1.2}
.carmel-phase-msg{background:#f4f6fb;border-radius:.4em;padding:.8em 1em}
.carmel-ok{color:#0e6e58}.carmel-ng{color:#a5281b}
.carmel-after{margin-top:1.2em;border-top:1px dashed #e0e3ea;padding-top:1em}
.carmel-after h4{margin:0 0 .6em}
.carmel-after-grid{display:flex;gap:.8em;flex-wrap:wrap}
.carmel-after-item{border:1px solid #e0e3ea;border-radius:.5em;padding:.7em 1em;min-width:120px}
.carmel-after-item.carmel-soon{border-color:#e67e22;background:#fff6ec}
.carmel-after-title{font-size:.8em;color:#888}
.carmel-after-date{font-weight:bold}
.carmel-after-days{font-size:.85em;color:#e67e22;font-weight:bold}
.carmel-repay{margin-top:1.2em;border-top:1px dashed #e0e3ea;padding-top:1em}
.carmel-repay h4{margin:0 0 .4em}
.carmel-repay-sum{font-size:.9em;margin:.2em 0 .6em}
.carmel-repay-table{width:100%;border-collapse:collapse;font-size:.9em}
.carmel-repay-table th,.carmel-repay-table td{border:1px solid #eef0f4;padding:.35em .6em;text-align:left}
.carmel-repay-table th{background:#f4f6fb}
.carmel-rp-paid td{color:#0e6e58}
.carmel-rp-overdue td{color:#a5281b;font-weight:bold;background:#fdecea}
.carmel-rp-upcoming td{color:#555}
.carmel-notice{padding:1em;background:#f4f6fb;border:1px solid #cdd2dc;border-radius:.4em}
</style>';
	}
}
