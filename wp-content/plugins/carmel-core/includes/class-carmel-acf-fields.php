<?php
/**
 * ACF field group definitions (code-registered, version-controlled).
 *
 * Registers local field groups for every CPT via acf_add_local_field_group().
 * deal_type-specific fields on carmel_deal use ACF conditional logic so loan /
 * buyback / lease fields show only for the matching type. No-op unless ACF (Pro)
 * is active, so the plugin runs fine without it.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_ACF_Fields {

	/** @var Carmel_ACF_Fields|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_action( 'acf/init', array( $this, 'register_groups' ) );
	}

	/**
	 * Field factory to cut boilerplate.
	 *
	 * @param string $prefix Group prefix (keeps keys globally unique).
	 * @param string $name   Meta key.
	 * @param string $label  UI label.
	 * @param string $type   ACF field type.
	 * @param array  $extra  Extra field args.
	 * @return array
	 */
	private function f( $prefix, $name, $label, $type = 'text', array $extra = array() ) {
		return array_merge(
			array(
				'key'   => 'field_' . $prefix . '_' . $name,
				'label' => $label,
				'name'  => $name,
				'type'  => $type,
			),
			$extra
		);
	}

	/** Show a field only for a given deal_type. */
	private function when_type( $type ) {
		return array(
			array(
				array(
					'field'    => 'field_deal_deal_type',
					'operator' => '==',
					'value'    => $type,
				),
			),
		);
	}

	private function group( $key, $title, $post_type, array $fields, $order = 0 ) {
		acf_add_local_field_group(
			array(
				'key'      => 'group_' . $key,
				'title'    => $title,
				'fields'   => $fields,
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => $post_type,
						),
					),
				),
				'menu_order' => $order,
				'active'     => true,
			)
		);
	}

	public function register_groups() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return; // ACF not active.
		}
		$this->register_deal();
		$this->register_store();
		$this->register_vehicle();
		$this->register_repayment();
		$this->register_inspection();
		$this->register_insurance();
		$this->register_content();
	}

	/* ------------------------------------------------------------------ */

	private function register_deal() {
		$status_choices = Carmel_MyPage::status_labels();

		$common = array(
			$this->f( 'deal', 'deal_type', '業務種別', 'select', array(
				'choices' => array( 'loan' => 'ローン販売', 'buyback' => '車買取', 'lease' => '自社リース' ),
				'ui'      => 1,
			) ),
			$this->f( 'deal', 'deal_status', 'ステータス', 'select', array( 'choices' => $status_choices, 'ui' => 1 ) ),
			$this->f( 'deal', 'customer_id', '顧客ユーザーID', 'number' ),
			$this->f( 'deal', 'store_id', '加盟店', 'post_object', array( 'post_type' => array( 'carmel_store' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'deal', 'vehicle_id', '車両', 'post_object', array( 'post_type' => array( 'carmel_vehicle' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'deal', 'applicant_name', '申込者氏名' ),
			$this->f( 'deal', 'applicant_email', 'メールアドレス', 'email' ),
			$this->f( 'deal', 'applicant_phone', '電話番号' ),
			$this->f( 'deal', 'applicant_address', '住所（納車先）' ),
			$this->f( 'deal', 'application_note', '備考', 'textarea' ),
		);

		$loan = array(
			$this->f( 'deal', 'ai_score', 'AIスコア', 'number', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'score_rank', '判定ランク', 'text', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'credit_company', '信販会社', 'text', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'screening_result', '審査結果', 'select', array( 'choices' => array( 'OK' => 'OK', 'NG' => 'NG' ), 'allow_null' => 1, 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'screening_reason', 'NG理由', 'text', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'delivery_date', '納車予定日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d', 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'transport_fee', '陸送費', 'number', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'transport_distance_km', '陸送距離(km)', 'number', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
			$this->f( 'deal', 'mf_contract_status', 'マネーフォワード契約 状況', 'text', array( 'conditional_logic' => $this->when_type( 'loan' ) ) ),
		);

		$buyback = array(
			$this->f( 'deal', 'appraisal_amount', '査定額', 'number', array( 'conditional_logic' => $this->when_type( 'buyback' ) ) ),
			$this->f( 'deal', 'payout_status', '入金状況', 'text', array( 'conditional_logic' => $this->when_type( 'buyback' ) ) ),
		);

		$lease = array(
			$this->f( 'deal', 'monthly_payment', '月額', 'number', array( 'conditional_logic' => $this->when_type( 'lease' ) ) ),
			$this->f( 'deal', 'lease_term', 'リース期間(月)', 'number', array( 'conditional_logic' => $this->when_type( 'lease' ) ) ),
			$this->f( 'deal', 'gps_equipped', 'GPS搭載', 'true_false', array( 'conditional_logic' => $this->when_type( 'lease' ) ) ),
		);

		// 販売支援（保証）— 種別共通。Carmel_Sales_Support / Carmel_Billing が利用。
		$support = array(
			$this->f( 'deal', 'warranty_plan', '保証プラン' ),
			$this->f( 'deal', 'warranty_term', '保証期間' ),
			$this->f( 'deal', 'warranty_scope', '保証範囲' ),
			$this->f( 'deal', 'warranty_fee', '保証料', 'number' ),
			$this->f( 'deal', 'warranty_start', '保証開始日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			// 在庫共有の売上配分（手数料）。Carmel_Commission が利用。
			$this->f( 'deal', 'source_store_id', '在庫保有店（他店在庫の販売時）', 'post_object', array( 'post_type' => array( 'carmel_store' ), 'return_format' => 'id', 'ui' => 1, 'allow_null' => 1 ) ),
			$this->f( 'deal', 'commission_rate', '手数料率(%)', 'number' ),
			$this->f( 'deal', 'commission_amount', '手数料額', 'number' ),
			$this->f( 'deal', 'commission_settled', '手数料 精算済', 'true_false' ),
		);

		$this->group( 'deal', '案件情報', 'carmel_deal', array_merge( $common, $loan, $buyback, $lease, $support ) );
	}

	/** エリア選択肢（Carmel_LINE_Bot::regions と共通）。 */
	private function region_choices() {
		$regions = class_exists( 'Carmel_LINE_Bot' ) ? Carmel_LINE_Bot::regions() : array( '北海道', '東北', '関東', '中部', '近畿', '中国・四国', '九州・沖縄', 'その他' );
		$out = array();
		foreach ( $regions as $r ) {
			$out[ $r ] = $r;
		}
		return $out;
	}

	private function register_store() {
		$this->group( 'store', '加盟店情報', 'carmel_store', array(
			$this->f( 'store', 'store_name', '店舗名' ),
			$this->f( 'store', 'store_address', '店舗住所（陸送元・公開ページ地図）' ),
			$this->f( 'store', 'store_tel', '電話番号（公開ページ）' ),
			$this->f( 'store', 'store_hours', '営業時間（公開ページ）' ),
			$this->f( 'store', 'store_area', 'エリア（加盟店検索）', 'select', array(
				'choices'    => $this->region_choices(),
				'allow_null' => 1,
				'ui'         => 1,
			) ),
			$this->f( 'store', 'store_services', '取扱種別（加盟店検索）', 'checkbox', array(
				'choices' => array( 'loan' => 'ローン販売', 'buyback' => '車買取', 'lease' => '自社リース' ),
			) ),
			$this->f( 'store', 'owner_user_id', 'オーナーのユーザーID', 'number' ),
			$this->f( 'store', 'square_location_id', 'SquareロケーションID' ),
			$this->f( 'store', 'notion_url', 'Notion 学習URL', 'url' ),
			$this->f( 'store', 'current_deal_count', '担当案件数', 'number' ),
			$this->f( 'store', 'membership_status', '会費ステータス', 'select', array(
				'choices'    => array( 'active' => '有効', 'grace' => '猶予', 'expired' => '期限切れ', 'none' => '未加入' ),
				'allow_null' => 1,
				'ui'         => 1,
			) ),
			$this->f( 'store', 'membership_plan', '会費プラン' ),
			$this->f( 'store', 'membership_fee', '会費（月額）', 'number' ),
			$this->f( 'store', 'membership_next_billing', '次回請求日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
		) );
	}

	private function register_vehicle() {
		$this->group( 'vehicle', '在庫車両情報', 'carmel_vehicle', array(
			$this->f( 'vehicle', 'store_id', '保有加盟店', 'post_object', array( 'post_type' => array( 'carmel_store' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'vehicle', 'maker', 'メーカー' ),
			$this->f( 'vehicle', 'model', '車種' ),
			$this->f( 'vehicle', 'grade', 'グレード' ),
			$this->f( 'vehicle', 'year', '年式', 'number' ),
			$this->f( 'vehicle', 'mileage', '走行距離(km)', 'number' ),
			$this->f( 'vehicle', 'color', '色' ),
			$this->f( 'vehicle', 'vin', '車台番号' ),
			$this->f( 'vehicle', 'plate_no', 'ナンバー' ),
			$this->f( 'vehicle', 'price', '販売価格', 'number' ),
			$this->f( 'vehicle', 'cost', '仕入原価', 'number' ),
			$this->f( 'vehicle', 'vehicle_status', '在庫ステータス', 'select', array(
				'choices' => array( '販売中' => '販売中', '商談中' => '商談中', '売約済' => '売約済', '納車済' => '納車済', '抹消' => '抹消' ),
				'ui'      => 1,
			) ),
			$this->f( 'vehicle', 'location_address', '現車所在地' ),
			$this->f( 'vehicle', 'inspection_expiry', '車検満了日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'vehicle', 'linked_deal_id', '紐づく案件', 'post_object', array( 'post_type' => array( 'carmel_deal' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'vehicle', 'published', '在庫公開', 'true_false' ),
		) );
	}

	private function register_repayment() {
		$this->group( 'repayment', '返済情報', 'carmel_repayment', array(
			$this->f( 'repayment', 'deal_id', '案件', 'post_object', array( 'post_type' => array( 'carmel_deal' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'repayment', 'due_date', '支払期日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'repayment', 'amount', '支払額', 'number' ),
			$this->f( 'repayment', 'paid_flag', '入金済', 'true_false' ),
			$this->f( 'repayment', 'delay_days', '延滞日数', 'number' ),
			$this->f( 'repayment', 'delay_interest', '延滞利息', 'number' ),
		) );
	}

	private function register_inspection() {
		$this->group( 'inspection', '車検情報', 'carmel_inspection', array(
			$this->f( 'inspection', 'deal_id', '案件', 'post_object', array( 'post_type' => array( 'carmel_deal' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'inspection', 'vehicle_id', '車両', 'post_object', array( 'post_type' => array( 'carmel_vehicle' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'inspection', 'last_inspection_date', '前回車検日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'inspection', 'expiry_date', '車検満了日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'inspection', 'next_due_date', '次回車検予定日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'inspection', 'booking_status', '予約状況', 'select', array(
				'choices' => array( '未' => '未', '予約済' => '予約済', '入庫' => '入庫', '完了' => '完了' ),
				'ui'      => 1,
			) ),
			$this->f( 'inspection', 'quote_amount', '見積額', 'number' ),
			$this->f( 'inspection', 'assigned_store_id', '実施加盟店', 'post_object', array( 'post_type' => array( 'carmel_store' ), 'return_format' => 'id', 'ui' => 1 ) ),
		) );
	}

	private function register_content() {
		$this->group( 'content', '加盟店コンテンツ設定', 'carmel_content', array(
			$this->f( 'content', 'content_type', '種別', 'select', array(
				'choices' => array( 'guide' => 'スタートガイド（始め方）', 'notice' => 'お知らせ', 'manual' => 'マニュアル・資料', 'faq' => 'FAQ', 'promo' => '販促ツール' ),
				'ui'      => 1,
			) ),
			$this->f( 'content', 'step_order', '表示順（スタートガイド用）', 'number' ),
			$this->f( 'content', 'summary', '概要（一覧表示用）', 'text' ),
			$this->f( 'content', 'file_url', '添付ファイルURL（資料DL用）', 'url' ),
			$this->f( 'content', 'pinned', '重要（上部に固定）', 'true_false' ),
			$this->f( 'content', 'notify_stores', '公開時に加盟店へ通知する', 'true_false' ),
		) );
	}

	private function register_insurance() {
		$this->group( 'insurance', '保険情報', 'carmel_insurance', array(
			$this->f( 'insurance', 'deal_id', '案件', 'post_object', array( 'post_type' => array( 'carmel_deal' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'insurance', 'vehicle_id', '車両', 'post_object', array( 'post_type' => array( 'carmel_vehicle' ), 'return_format' => 'id', 'ui' => 1 ) ),
			$this->f( 'insurance', 'policy_type', '種別', 'select', array(
				'choices' => array( '任意保険' => '任意保険', '自賠責' => '自賠責', '車両保険' => '車両保険' ),
				'ui'      => 1,
			) ),
			$this->f( 'insurance', 'insurer', '保険会社' ),
			$this->f( 'insurance', 'policy_number', '証券番号' ),
			$this->f( 'insurance', 'start_date', '保険開始日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'insurance', 'end_date', '保険満了日', 'date_picker', array( 'display_format' => 'Y-m-d', 'return_format' => 'Y-m-d' ) ),
			$this->f( 'insurance', 'premium', '保険料', 'number' ),
			$this->f( 'insurance', 'assigned_store_id', '担当加盟店', 'post_object', array( 'post_type' => array( 'carmel_store' ), 'return_format' => 'id', 'ui' => 1 ) ),
		) );
	}
}
