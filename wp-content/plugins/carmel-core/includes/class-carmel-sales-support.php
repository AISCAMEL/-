<?php
/**
 * 販売支援ハブ（加盟店向け）。
 *
 * 加盟店が販売・成約を支援するためのツール群を1画面に集約する：
 *   - 保証プラン      … プランを案件に適用し、保証書を発行
 *   - 陸送            … 案件の陸送費を自動見積（Carmel_Transport 連携）
 *   - オートローン     … 元利均等の月々支払いを試算し、見積書を発行
 *   - 自社リース       … 残価設定リースの月額を試算し、見積書を発行
 *   - 販促ツール      … 本部配布の販促物（POP・チラシ等）をダウンロード
 *
 * 試算結果は Carmel_Billing を通じて見積書として「引き出せる」。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Sales_Support {

	/** @var Carmel_Sales_Support|null */
	private static $instance = null;

	const SHORTCODE        = 'carmel_sales_support';
	const WARRANTY_ACTION  = 'carmel_ss_warranty';
	const TRANSPORT_ACTION = 'carmel_ss_transport';
	const LOAN_ACTION      = 'carmel_ss_loan';
	const LEASE_ACTION     = 'carmel_ss_lease';
	const NONCE            = 'carmel_ss_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::WARRANTY_ACTION, array( $this, 'handle_warranty' ) );
		add_action( 'admin_post_' . self::TRANSPORT_ACTION, array( $this, 'handle_transport' ) );
		add_action( 'admin_post_' . self::LOAN_ACTION, array( $this, 'handle_loan' ) );
		add_action( 'admin_post_' . self::LEASE_ACTION, array( $this, 'handle_lease' ) );
	}

	/* --------------------------------------------------------------------- *
	 * 設定（保証プラン・販促ツール）
	 * --------------------------------------------------------------------- */

	/**
	 * 保証プランのカタログ（本部設定・既定値あり）。
	 *
	 * @return array<string,array{label:string,term:string,scope:string,fee:int}>
	 */
	public static function warranty_plans() {
		$defaults = array(
			'basic'   => array( 'label' => 'ベーシック保証', 'term' => '3ヶ月 / 3,000km', 'scope' => 'エンジン・ミッション等 主要機構', 'fee' => 0 ),
			'standard'=> array( 'label' => 'スタンダード保証', 'term' => '1年 / 2万km', 'scope' => '主要機構＋電装・エアコン', 'fee' => 39800 ),
			'premium' => array( 'label' => 'プレミアム保証', 'term' => '2年 / 4万km', 'scope' => '幅広い部位をカバー（全国対応）', 'fee' => 79800 ),
		);
		$opt = get_option( 'carmel_warranty_plans', array() );
		return apply_filters( 'carmel_warranty_plans', is_array( $opt ) && $opt ? $opt : $defaults );
	}

	/**
	 * 金利・リースの既定パラメータ（本部設定可能）。
	 *
	 * @return array
	 */
	public static function finance_defaults() {
		$defaults = array(
			'loan_rate'      => 8.9,  // オートローン実質年率(%)
			'loan_months'    => 60,
			'lease_rate'     => 6.0,  // 自社リース 年率(%)
			'lease_months'   => 60,
			'lease_residual' => 20,   // 残価率(%)
		);
		$opt = get_option( 'carmel_finance_defaults', array() );
		return apply_filters( 'carmel_finance_defaults', wp_parse_args( is_array( $opt ) ? $opt : array(), $defaults ) );
	}

	/* --------------------------------------------------------------------- *
	 * 計算ロジック（再利用可能）
	 * --------------------------------------------------------------------- */

	/**
	 * 元利均等の月々支払い。
	 *
	 * @param float $principal 元金（車両価格 − 頭金）。
	 * @param int   $months    支払回数。
	 * @param float $annual    実質年率(%)。
	 * @return int 月々支払額（円）。
	 */
	public static function monthly_payment( $principal, $months, $annual ) {
		$principal = max( 0, (float) $principal );
		$months    = max( 1, (int) $months );
		$r         = (float) $annual / 100 / 12;
		if ( $r <= 0 ) {
			return (int) round( $principal / $months );
		}
		$pow = pow( 1 + $r, $months );
		return (int) round( $principal * $r * $pow / ( $pow - 1 ) );
	}

	/**
	 * 残価設定リースの月額（残価を割賦対象から除外し、残価にも金利を載せる簡易式）。
	 *
	 * @param float $price    車両価格。
	 * @param float $residual 残価（円）。
	 * @param int   $months   リース期間。
	 * @param float $annual   年率(%)。
	 * @return int 月額（円）。
	 */
	public static function lease_monthly( $price, $residual, $months, $annual ) {
		$price    = max( 0, (float) $price );
		$residual = min( max( 0, (float) $residual ), $price );
		$months   = max( 1, (int) $months );
		$r        = (float) $annual / 100 / 12;
		$depreciation = ( $price - $residual ) / $months;          // 月割りの減価分
		$interest     = ( $price + $residual ) * $r;               // 平均残高にかかる金利（簡易）
		return (int) round( $depreciation + $interest );
	}

	/* --------------------------------------------------------------------- *
	 * 共通ガード
	 * --------------------------------------------------------------------- */

	private function guard( $deal_id, $action ) {
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', $action . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! Carmel_Billing::can_issue( $deal_id ) ) {
			wp_die( esc_html__( 'この案件を操作する権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
	}

	private function back( $args ) {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store' );
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 保証
	 * --------------------------------------------------------------------- */

	public function handle_warranty() {
		$deal_id = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$this->guard( $deal_id, self::WARRANTY_ACTION );

		$plan_key = isset( $_POST['plan'] ) ? sanitize_key( $_POST['plan'] ) : '';
		$plans    = self::warranty_plans();
		if ( ! isset( $plans[ $plan_key ] ) ) {
			$this->back( array( 'carmel_ss' => 'err' ) );
		}
		$plan = $plans[ $plan_key ];

		// 案件へ保証情報を記録。
		update_post_meta( $deal_id, 'warranty_plan', $plan['label'] );
		update_post_meta( $deal_id, 'warranty_term', $plan['term'] );
		update_post_meta( $deal_id, 'warranty_scope', $plan['scope'] );
		update_post_meta( $deal_id, 'warranty_fee', (int) $plan['fee'] );
		update_post_meta( $deal_id, 'warranty_start', current_time( 'Y-m-d' ) );

		do_action( 'carmel_warranty_applied', $deal_id, $plan_key, $plan );

		// 保証書を発行。
		$doc = Carmel_Billing::instance()->create_contract( $deal_id, 'warranty', array(
			'warranty_term'  => $plan['term'],
			'warranty_scope' => $plan['scope'],
		) );
		$args = is_wp_error( $doc ) ? array( 'carmel_ss' => 'err' ) : array( 'carmel_ss' => 'warranty_ok', 'issued' => (int) $doc );
		$this->back( $args );
	}

	/* --------------------------------------------------------------------- *
	 * 陸送
	 * --------------------------------------------------------------------- */

	public function handle_transport() {
		$deal_id = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$this->guard( $deal_id, self::TRANSPORT_ACTION );

		$result = Carmel_Transport::instance()->calculate( $deal_id );
		if ( is_wp_error( $result ) ) {
			$this->back( array( 'carmel_ss' => 'transport_err', 'msg' => rawurlencode( $result->get_error_message() ) ) );
		}
		$this->back( array(
			'carmel_ss' => 'transport_ok',
			'fee'       => (int) $result['fee'],
			'km'        => $result['distance_km'],
		) );
	}

	/* --------------------------------------------------------------------- *
	 * オートローン
	 * --------------------------------------------------------------------- */

	public function handle_loan() {
		$deal_id = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$this->guard( $deal_id, self::LOAN_ACTION );

		$price  = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0;
		$down   = isset( $_POST['down'] ) ? (float) $_POST['down'] : 0;
		$months = isset( $_POST['months'] ) ? (int) $_POST['months'] : 60;
		$rate   = isset( $_POST['rate'] ) ? (float) $_POST['rate'] : self::finance_defaults()['loan_rate'];

		$principal = max( 0, $price - $down );
		$monthly   = self::monthly_payment( $principal, $months, $rate );
		$total     = $monthly * $months + $down;

		$items = array(
			array( 'name' => '車両価格', 'qty' => 1, 'unit_price' => $price ),
			array( 'name' => '頭金', 'qty' => 1, 'unit_price' => -1 * $down ),
			array( 'name' => sprintf( '月々お支払い（%d回 / 実質年率%s%%）', $months, $this->num( $rate ) ), 'qty' => $months, 'unit_price' => $monthly ),
		);
		$note = sprintf(
			"オートローン支払シミュレーション\n・分割払手数料を含む概算です。最終条件は信販会社の審査により決定します。\n・月々お支払い：%s円 × %d回\n・お支払総額（頭金含む）：%s円",
			number_format( $monthly ),
			$months,
			number_format( $total )
		);

		$doc = Carmel_Billing::instance()->create_billing(
			$deal_id,
			'loan_quote',
			$items,
			array( 'note' => $note, 'tax_rate' => 0, 'title' => 'オートローン支払シミュレーション #' . $deal_id )
		);
		$args = is_wp_error( $doc ) ? array( 'carmel_ss' => 'err' ) : array( 'carmel_ss' => 'loan_ok', 'issued' => (int) $doc );
		$this->back( $args );
	}

	/* --------------------------------------------------------------------- *
	 * 自社リース
	 * --------------------------------------------------------------------- */

	public function handle_lease() {
		$deal_id = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$this->guard( $deal_id, self::LEASE_ACTION );

		$d        = self::finance_defaults();
		$price    = isset( $_POST['price'] ) ? (float) $_POST['price'] : 0;
		$months   = isset( $_POST['months'] ) ? (int) $_POST['months'] : $d['lease_months'];
		$rate     = isset( $_POST['rate'] ) ? (float) $_POST['rate'] : $d['lease_rate'];
		$res_pct  = isset( $_POST['residual_pct'] ) ? (float) $_POST['residual_pct'] : $d['lease_residual'];
		$residual = round( $price * $res_pct / 100 );

		$monthly = self::lease_monthly( $price, $residual, $months, $rate );
		$total   = $monthly * $months;

		// 案件のリース情報を更新（マイページ・契約書に反映）。
		update_post_meta( $deal_id, 'monthly_payment', $monthly );
		update_post_meta( $deal_id, 'lease_term', $months );

		$items = array(
			array( 'name' => sprintf( 'リース料（月額・%dヶ月）', $months ), 'qty' => $months, 'unit_price' => $monthly ),
		);
		$note = sprintf(
			"自社リース見積\n・車両価格：%s円　残価：%s円（%s%%）\n・リース期間：%dヶ月　年率：%s%%\n・月額：%s円（税込目安）\n・リース総額：%s円",
			number_format( $price ),
			number_format( $residual ),
			$this->num( $res_pct ),
			$months,
			$this->num( $rate ),
			number_format( $monthly ),
			number_format( $total )
		);

		$doc = Carmel_Billing::instance()->create_billing(
			$deal_id,
			'lease_quote',
			$items,
			array( 'note' => $note, 'tax_rate' => 0, 'title' => '自社リース見積書 #' . $deal_id )
		);
		$args = is_wp_error( $doc ) ? array( 'carmel_ss' => 'err' ) : array( 'carmel_ss' => 'lease_ok', 'issued' => (int) $doc );
		$this->back( $args );
	}

	private function num( $n ) {
		$n = (float) $n;
		return ( floor( $n ) === $n ) ? (string) (int) $n : (string) $n;
	}

	/* --------------------------------------------------------------------- *
	 * 画面
	 * --------------------------------------------------------------------- */

	public function render() {
		if ( ! is_user_logged_in() || ! current_user_can( 'carmel_change_deal_status' ) ) {
			return '<p class="carmel-notice">販売支援を表示する権限がありません。</p>';
		}
		$deals = $this->accessible_deals();

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-ss"><h2>販売支援</h2>';
		echo '<p class="carmel-ss-lead">保証・陸送・オートローン・自社リースの見積、販促ツールをまとめて利用できます。試算結果はそのまま見積書として発行できます。</p>';

		if ( empty( $deals ) ) {
			echo '<p>対象の案件がありません。先に案件を作成してください。</p>';
			echo $this->promo_tools(); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			return ob_get_clean();
		}

		echo '<div class="carmel-ss-grid">';
		echo $this->warranty_card( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->transport_card( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->loan_card( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->lease_card( $deals ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		echo $this->promo_tools(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->loan_lease_js(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		return ob_get_clean();
	}

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

	/** 案件 select（指定 action の nonce を data 属性に付与）。 */
	private function deal_select( array $deals, $action ) {
		$out = '<select name="deal_id" class="carmel-ss-deal" data-action="' . esc_attr( $action ) . '" required>';
		foreach ( $deals as $deal ) {
			$name       = get_post_meta( $deal->ID, 'applicant_name', true );
			$label      = '#' . $deal->ID . ' ' . ( $name ? $name : $deal->post_title );
			$vehicle_id = (int) get_post_meta( $deal->ID, 'vehicle_id', true );
			// 申込時の希望条件（extra_loan_*）があれば優先、無ければ車両価格。
			$ep    = (float) get_post_meta( $deal->ID, 'extra_loan_price', true );
			$price = $ep > 0 ? $ep : ( $vehicle_id ? (float) get_post_meta( $vehicle_id, 'price', true ) : 0 );
			$down  = (float) get_post_meta( $deal->ID, 'extra_loan_down', true );
			$mon   = (int) get_post_meta( $deal->ID, 'extra_loan_months', true );
			$nonce = wp_create_nonce( $action . '_' . $deal->ID );
			$out  .= '<option value="' . (int) $deal->ID . '" data-nonce="' . esc_attr( $nonce ) . '" data-price="' . esc_attr( $price ) . '" data-down="' . esc_attr( $down ) . '" data-months="' . esc_attr( $mon ) . '">' . esc_html( $label ) . '</option>';
		}
		$out .= '</select>';
		return $out;
	}

	private function form_open( $action ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-ss-form" data-ss-action="' . esc_attr( $action ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="">';
	}

	private function warranty_card( array $deals ) {
		$out  = '<div class="carmel-ss-card"><h3>🛡 保証プラン</h3>';
		$out .= '<p class="carmel-hint">プランを選んで適用すると、案件に保証情報を記録し保証書を発行します。</p>';
		$out .= $this->form_open( self::WARRANTY_ACTION );
		$out .= '<label class="carmel-block">案件 ' . $this->deal_select( $deals, self::WARRANTY_ACTION ) . '</label>';
		$out .= '<label class="carmel-block">プラン<select name="plan">';
		foreach ( self::warranty_plans() as $key => $p ) {
			$fee  = (int) $p['fee'] > 0 ? '（¥' . number_format( $p['fee'] ) . '）' : '（無償）';
			$out .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $p['label'] . ' / ' . $p['term'] . ' ' . $fee ) . '</option>';
		}
		$out .= '</select></label>';
		$out .= '<button type="submit" class="carmel-btn carmel-btn-purple">適用して保証書を発行</button>';
		$out .= '</form></div>';
		return $out;
	}

	private function transport_card( array $deals ) {
		$out  = '<div class="carmel-ss-card"><h3>🚚 陸送見積</h3>';
		$out .= '<p class="carmel-hint">店舗住所〜納車先の距離から陸送費を自動計算します（Google Maps連携）。</p>';
		$out .= $this->form_open( self::TRANSPORT_ACTION );
		$out .= '<label class="carmel-block">案件 ' . $this->deal_select( $deals, self::TRANSPORT_ACTION ) . '</label>';
		$out .= '<button type="submit" class="carmel-btn carmel-btn-blue">陸送費を計算</button>';
		$out .= '</form>';
		if ( ! Carmel_Transport::instance()->is_ready() ) {
			$out .= '<p class="carmel-hint">※ Maps APIキー未設定のため概算が出ない場合があります（本部設定）。</p>';
		}
		$out .= '</div>';
		return $out;
	}

	private function loan_card( array $deals ) {
		$d    = self::finance_defaults();
		$out  = '<div class="carmel-ss-card"><h3>💳 オートローン試算</h3>';
		$out .= $this->form_open( self::LOAN_ACTION );
		$out .= '<label class="carmel-block">案件 ' . $this->deal_select( $deals, self::LOAN_ACTION ) . '</label>';
		$out .= '<div class="carmel-ss-row">';
		$out .= '<label>車両価格<input type="number" name="price" class="carmel-fin-price" value="0" min="0" step="1000"></label>';
		$out .= '<label>頭金<input type="number" name="down" class="carmel-fin-down" value="0" min="0" step="1000"></label>';
		$out .= '<label>回数<input type="number" name="months" class="carmel-fin-months" value="' . (int) $d['loan_months'] . '" min="1" step="1"></label>';
		$out .= '<label>実質年率(%)<input type="number" name="rate" class="carmel-fin-rate" value="' . esc_attr( $d['loan_rate'] ) . '" min="0" step="0.1"></label>';
		$out .= '</div>';
		$out .= '<div class="carmel-fin-result">月々：<strong class="carmel-loan-monthly">¥0</strong> × <span class="carmel-loan-months">' . (int) $d['loan_months'] . '</span>回　／　総額：<strong class="carmel-loan-total">¥0</strong></div>';
		$out .= '<button type="submit" class="carmel-btn carmel-btn-purple">見積書を発行</button>';
		$out .= '</form></div>';
		return $out;
	}

	private function lease_card( array $deals ) {
		$d    = self::finance_defaults();
		$out  = '<div class="carmel-ss-card"><h3>🔑 自社リース試算</h3>';
		$out .= $this->form_open( self::LEASE_ACTION );
		$out .= '<label class="carmel-block">案件 ' . $this->deal_select( $deals, self::LEASE_ACTION ) . '</label>';
		$out .= '<div class="carmel-ss-row">';
		$out .= '<label>車両価格<input type="number" name="price" class="carmel-lease-price" value="0" min="0" step="1000"></label>';
		$out .= '<label>残価率(%)<input type="number" name="residual_pct" class="carmel-lease-res" value="' . esc_attr( $d['lease_residual'] ) . '" min="0" max="90" step="1"></label>';
		$out .= '<label>期間(月)<input type="number" name="months" class="carmel-lease-months" value="' . (int) $d['lease_months'] . '" min="1" step="1"></label>';
		$out .= '<label>年率(%)<input type="number" name="rate" class="carmel-lease-rate" value="' . esc_attr( $d['lease_rate'] ) . '" min="0" step="0.1"></label>';
		$out .= '</div>';
		$out .= '<div class="carmel-fin-result">月額：<strong class="carmel-lease-monthly">¥0</strong>　／　総額：<strong class="carmel-lease-total">¥0</strong></div>';
		$out .= '<button type="submit" class="carmel-btn carmel-btn-purple">見積書を発行</button>';
		$out .= '</form></div>';
		return $out;
	}

	/**
	 * 販促ツール（本部が carmel_content の「販促ツール」種別で配布したもの）。
	 */
	private function promo_tools() {
		$items = get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( array( 'key' => 'content_type', 'value' => 'promo' ) ),
			)
		);

		$out  = '<div class="carmel-ss-card carmel-ss-promo"><h3>📣 販促ツール</h3>';
		if ( empty( $items ) ) {
			$out .= '<p class="carmel-hint">現在ダウンロードできる販促ツールはありません（本部が公開すると表示されます）。</p></div>';
			return $out;
		}
		$out .= '<ul class="carmel-promo-list">';
		foreach ( $items as $item ) {
			$summary = (string) get_post_meta( $item->ID, 'summary', true );
			$url     = (string) get_post_meta( $item->ID, 'file_url', true );
			$out    .= '<li><div class="carmel-promo-title">' . esc_html( get_the_title( $item ) ) . '</div>';
			if ( $summary ) {
				$out .= '<div class="carmel-promo-sum">' . esc_html( $summary ) . '</div>';
			}
			if ( $url ) {
				$out .= '<a class="carmel-btn carmel-btn-blue" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">ダウンロード</a>';
			}
			$out .= '</li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	private function loan_lease_js() {
		ob_start();
		?>
<script>
(function(){
	function yen(n){return '¥'+(Math.round(n)).toLocaleString('ja-JP');}
	function pmt(principal,months,annual){
		principal=Math.max(0,principal);months=Math.max(1,months);
		var r=annual/100/12;
		if(r<=0)return Math.round(principal/months);
		var p=Math.pow(1+r,months);
		return Math.round(principal*r*p/(p-1));
	}
	function leasePmt(price,residual,months,annual){
		price=Math.max(0,price);residual=Math.min(Math.max(0,residual),price);months=Math.max(1,months);
		var r=annual/100/12;
		return Math.round((price-residual)/months+(price+residual)*r);
	}
	// 案件選択 → nonce + 車両価格を反映。
	document.querySelectorAll('.carmel-ss-form').forEach(function(form){
		var sel=form.querySelector('.carmel-ss-deal');
		var hidden=form.querySelector('input[name="<?php echo esc_js( self::NONCE ); ?>"]');
		function sync(){
			var opt=sel.options[sel.selectedIndex];if(!opt)return;
			if(hidden&&opt.getAttribute('data-nonce'))hidden.value=opt.getAttribute('data-nonce');
			var price=parseFloat(opt.getAttribute('data-price'))||0;
			if(price>0){
				var p=form.querySelector('.carmel-fin-price')||form.querySelector('.carmel-lease-price');
				if(p)p.value=price;
			}
			// 申込時の希望（頭金・回数）を反映（ローンカード）。
			var down=parseFloat(opt.getAttribute('data-down'))||0;
			var mon=parseInt(opt.getAttribute('data-months'))||0;
			var dEl=form.querySelector('.carmel-fin-down');if(dEl&&down>0)dEl.value=down;
			var mEl=form.querySelector('.carmel-fin-months');if(mEl&&mon>0)mEl.value=mon;
			recalc();
		}
		function recalc(){
			var lp=form.querySelector('.carmel-fin-price');
			if(lp){
				var price=parseFloat(lp.value)||0,down=parseFloat(form.querySelector('.carmel-fin-down').value)||0;
				var m=parseInt(form.querySelector('.carmel-fin-months').value)||1,rate=parseFloat(form.querySelector('.carmel-fin-rate').value)||0;
				var mo=pmt(price-down,m,rate);
				form.querySelector('.carmel-loan-monthly').textContent=yen(mo);
				form.querySelector('.carmel-loan-months').textContent=m;
				form.querySelector('.carmel-loan-total').textContent=yen(mo*m+down);
			}
			var xp=form.querySelector('.carmel-lease-price');
			if(xp){
				var pr=parseFloat(xp.value)||0,respct=parseFloat(form.querySelector('.carmel-lease-res').value)||0;
				var lm=parseInt(form.querySelector('.carmel-lease-months').value)||1,lr=parseFloat(form.querySelector('.carmel-lease-rate').value)||0;
				var resid=Math.round(pr*respct/100);
				var mo=leasePmt(pr,resid,lm,lr);
				form.querySelector('.carmel-lease-monthly').textContent=yen(mo);
				form.querySelector('.carmel-lease-total').textContent=yen(mo*lm);
			}
		}
		if(sel){sel.addEventListener('change',sync);}
		form.addEventListener('input',recalc);
		sync();
	});
})();
</script>
		<?php
		return ob_get_clean();
	}

	private function banner() {
		$key = isset( $_GET['carmel_ss'] ) ? sanitize_key( $_GET['carmel_ss'] ) : '';
		if ( '' === $key ) {
			return '';
		}
		$issued_link = '';
		if ( isset( $_GET['issued'] ) ) {
			$doc_id = (int) $_GET['issued'];
			if ( Carmel_Billing::can_view( (int) get_post_meta( $doc_id, 'deal_id', true ) ) ) {
				$issued_link = ' <a href="' . esc_url( Carmel_Billing::view_url( $doc_id ) ) . '" target="_blank" rel="noopener">発行した書類を開く</a>';
			}
		}
		$map = array(
			'warranty_ok'  => array( 'success', '保証を適用し、保証書を発行しました。' ),
			'loan_ok'      => array( 'success', 'オートローン支払シミュレーションを見積書として発行しました。' ),
			'lease_ok'     => array( 'success', '自社リース見積書を発行しました。' ),
			'transport_ok' => array( 'success', sprintf( '陸送費を計算しました：¥%s（約%skm）', number_format( (float) ( isset( $_GET['fee'] ) ? $_GET['fee'] : 0 ) ), isset( $_GET['km'] ) ? sanitize_text_field( wp_unslash( $_GET['km'] ) ) : '?' ) ),
			'transport_err'=> array( 'error', '陸送費を計算できませんでした：' . ( isset( $_GET['msg'] ) ? esc_html( sanitize_text_field( rawurldecode( wp_unslash( $_GET['msg'] ) ) ) ) : '' ) ),
			'err'          => array( 'error', '処理できませんでした。入力をご確認ください。' ),
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		$type = $map[ $key ][0];
		$text = $map[ $key ][1];
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $type ) . '">' . esc_html( $text ) . ( 'success' === $type ? $issued_link : '' ) . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-ss{font-size:14px;max-width:860px}
.carmel-ss-lead{color:#555}
.carmel-ss-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:1em;margin:1em 0}
.carmel-ss-card{border:1px solid #e0e3ea;border-radius:.6em;padding:1.1em 1.2em;background:#fff}
.carmel-ss-card h3{margin:.1em 0 .6em}
.carmel-ss-form{display:flex;flex-direction:column;gap:.5em}
.carmel-block{display:block;font-size:.82em;color:#555}
.carmel-ss-card select,.carmel-ss-card input{border:1px solid #ccc;border-radius:.3em;padding:.4em;width:100%}
.carmel-ss-row{display:flex;flex-wrap:wrap;gap:.6em}
.carmel-ss-row label{display:flex;flex-direction:column;font-size:.8em;color:#555;gap:.2em;flex:1;min-width:90px}
.carmel-fin-result{background:#f4f6fb;border-radius:.4em;padding:.5em .8em;font-size:.95em}
.carmel-fin-result strong{color:#6b4fbb}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.5em 1.1em;color:#fff;cursor:pointer;font-size:.9em;text-decoration:none;align-self:flex-start}
.carmel-btn-purple{background:#6b4fbb}.carmel-btn-blue{background:#2e86de}
.carmel-hint{font-size:.82em;color:#888}
.carmel-ss-promo{margin-top:1em}
.carmel-promo-list{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.8em}
.carmel-promo-list li{border:1px solid #eef0f4;border-radius:.5em;padding:.7em .9em}
.carmel-promo-title{font-weight:bold}
.carmel-promo-sum{font-size:.84em;color:#666;margin:.2em 0 .5em}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
