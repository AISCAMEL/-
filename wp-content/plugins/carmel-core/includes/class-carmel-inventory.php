<?php
/**
 * 在庫の掲載・共有（カーメル在庫ページ／加盟店在庫ネットワーク）。
 *
 * ひとつの在庫データ（carmel_vehicle）を、見る人によって表示を変える（ログイン分け）：
 *   - 未ログイン / お客様 … [carmel_inventory] 公開在庫（カーメル在庫ページ）。
 *       公開フラグ ON・販売可能な車両のみ。仕入原価・車台番号などの内部情報は出さない。
 *       お客様はログイン状態でお問い合わせ／お申込み導線が出る。
 *   - 加盟店オーナー/スタッフ … [carmel_store_inventory] 自店在庫の管理＋
 *       「在庫共有」＝他店の公開在庫をネットワーク横断で閲覧し、取り寄せ・商談を依頼。
 *       他店の在庫には小売価格のみ表示（原価は出さない）。自店在庫は原価・公開切替も可。
 *   - 本部 … すべて閲覧可（原価含む）。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Inventory {

	/** @var Carmel_Inventory|null */
	private static $instance = null;

	const PUBLIC_SHORTCODE = 'carmel_inventory';
	const STORE_SHORTCODE  = 'carmel_store_inventory';
	const PUBLISH_ACTION   = 'carmel_inv_publish';
	const INQUIRY_ACTION   = 'carmel_inv_inquiry';
	const IMPORT_ACTION    = 'carmel_inv_import';
	const TEMPLATE_ACTION  = 'carmel_inv_template';
	const CINQUIRY_ACTION  = 'carmel_inv_cust_inquiry';
	const NONCE            = 'carmel_inv_nonce';
	const IMPORT_MAX_ROWS  = 500;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** 公開在庫として掲載できる在庫ステータス。 */
	public static function sellable_statuses() {
		return apply_filters( 'carmel_inventory_sellable_statuses', array( '販売中', '商談中' ) );
	}

	public function register_hooks() {
		add_shortcode( self::PUBLIC_SHORTCODE, array( $this, 'render_public' ) );
		add_shortcode( self::STORE_SHORTCODE, array( $this, 'render_store' ) );
		add_action( 'admin_post_' . self::PUBLISH_ACTION, array( $this, 'handle_publish' ) );
		add_action( 'admin_post_' . self::INQUIRY_ACTION, array( $this, 'handle_inquiry' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
		add_action( 'admin_post_' . self::TEMPLATE_ACTION, array( $this, 'handle_template' ) );
		add_action( 'admin_post_' . self::CINQUIRY_ACTION, array( $this, 'handle_customer_inquiry' ) );

		// 在庫取り寄せ依頼の通知ルーティング／文面。
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );

		// サムネイル対応（テーマ非依存で在庫画像を使う）。
		add_action( 'after_setup_theme', array( $this, 'ensure_thumbnail_support' ) );
	}

	public function ensure_thumbnail_support() {
		add_theme_support( 'post-thumbnails' );
	}

	/* --------------------------------------------------------------------- *
	 * 表示権限スコープ（ログイン分け）
	 * --------------------------------------------------------------------- */

	/**
	 * 現在の閲覧者の区分。
	 *
	 * @return string guest|customer|store|hq
	 */
	private function viewer_scope() {
		if ( ! is_user_logged_in() ) {
			return 'guest';
		}
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return 'hq';
		}
		if ( current_user_can( 'carmel_change_deal_status' ) ) {
			return 'store';
		}
		return 'customer';
	}

	private function current_store_id() {
		return (int) get_user_meta( get_current_user_id(), 'store_id', true );
	}

	/* --------------------------------------------------------------------- *
	 * クエリ
	 * --------------------------------------------------------------------- */

	/**
	 * 公開在庫を取得（フィルタ条件つき）。
	 *
	 * @param array $filters [ maker, q, price_max, store_id ]
	 * @return WP_Post[]
	 */
	private function query_public( array $filters = array() ) {
		$meta = array(
			'relation' => 'AND',
			array( 'key' => 'published', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
			array( 'key' => 'vehicle_status', 'value' => self::sellable_statuses(), 'compare' => 'IN' ),
		);
		if ( ! empty( $filters['maker'] ) ) {
			$meta[] = array( 'key' => 'maker', 'value' => $filters['maker'] );
		}
		if ( ! empty( $filters['price_max'] ) ) {
			$meta[] = array( 'key' => 'price', 'value' => (int) $filters['price_max'], 'type' => 'NUMERIC', 'compare' => '<=' );
		}
		if ( ! empty( $filters['store_id'] ) ) {
			$meta[] = array( 'key' => 'store_id', 'value' => (int) $filters['store_id'] );
		}

		$args = array(
			'post_type'      => 'carmel_vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta,
		);
		if ( ! empty( $filters['q'] ) ) {
			$args['s'] = sanitize_text_field( $filters['q'] );
		}
		return get_posts( $args );
	}

	/** 自店の在庫（全ステータス・未公開含む）。 */
	private function query_store( $store_id ) {
		return get_posts(
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_query'     => array( array( 'key' => 'store_id', 'value' => (int) $store_id ) ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * カーメル在庫ページ（公開・ログイン分け）
	 * --------------------------------------------------------------------- */

	public function render_public() {
		$scope   = $this->viewer_scope();

		// 在庫詳細ページ（?vehicle=ID）。
		$vid = isset( $_GET['vehicle'] ) ? (int) $_GET['vehicle'] : 0;
		if ( $vid && 'carmel_vehicle' === get_post_type( $vid ) ) {
			return $this->render_detail( $vid, $scope );
		}

		$filters = $this->read_filters();
		$cars    = $this->query_public( $filters );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-inv"><h2>カーメル認定在庫</h2>';

		// ログイン分けの案内帯。
		if ( 'guest' === $scope ) {
			echo '<div class="carmel-inv-login">気になるお車は<a href="' . esc_url( home_url( '/login' ) ) . '">ログイン</a>するとお問い合わせ・お申込みいただけます。</div>';
		} elseif ( 'store' === $scope || 'hq' === $scope ) {
			echo '<div class="carmel-inv-login">加盟店の方は<a href="' . esc_url( home_url( '/' . ltrim( apply_filters( 'carmel_store_inventory_page_slug', 'store-inventory' ), '/' ) ) ) . '">加盟店在庫ページ</a>で在庫共有・管理ができます。</div>';
		}

		echo $this->filter_bar( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( empty( $cars ) ) {
			echo '<p>条件に合うお車が見つかりませんでした。</p></div>';
			return ob_get_clean();
		}

		echo '<div class="carmel-car-grid">';
		foreach ( $cars as $car ) {
			echo $this->car_card( $car, $scope, 'public' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/**
	 * 在庫詳細ページ。公開車両は誰でも、未公開は加盟店/本部のみ。
	 *
	 * @param int    $vid
	 * @param string $scope
	 * @return string
	 */
	private function render_detail( $vid, $scope ) {
		$published = in_array( (string) get_post_meta( $vid, 'published', true ), array( '1', 'yes', 'true' ), true );
		$is_staff  = in_array( $scope, array( 'store', 'hq' ), true );
		if ( ! $published && ! $is_staff ) {
			return '<p class="carmel-notice">この車両は現在ご覧いただけません。</p>';
		}

		$g = function ( $k ) use ( $vid ) {
			return get_post_meta( $vid, $k, true );
		};
		$maker = $g( 'maker' );
		$model = $g( 'model' );
		$title = trim( $maker . ' ' . $model );
		if ( '' === $title ) {
			$title = get_the_title( $vid );
		}
		$store_id = (int) $g( 'store_id' );
		$back     = esc_url( remove_query_arg( 'vehicle' ) );

		$thumb = has_post_thumbnail( $vid )
			? get_the_post_thumbnail( $vid, 'large', array( 'class' => 'carmel-detail-img' ) )
			: '<div class="carmel-car-noimg carmel-detail-img">NO IMAGE</div>';

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-inv"><a class="carmel-comm-back" style="color:#6b4fbb;text-decoration:none" href="' . $back . '">← 在庫一覧へ戻る</a>';
		echo '<div class="carmel-detail">';
		echo '<div class="carmel-detail-media">' . $thumb . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-detail-body">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( $g( 'grade' ) ) {
			echo '<div class="carmel-car-grade">' . esc_html( $g( 'grade' ) ) . '</div>';
		}
		echo '<div class="carmel-car-price" style="font-size:1.6em">¥' . esc_html( number_format( (float) $g( 'price' ) ) ) . '<small>（税込）</small></div>';

		// スペック表。
		$rows = array(
			'メーカー'   => $maker,
			'車種'       => $model,
			'年式'       => $g( 'year' ) ? $g( 'year' ) . '年' : '',
			'走行距離'   => '' !== (string) $g( 'mileage' ) ? number_format( (float) $g( 'mileage' ) ) . 'km' : '',
			'色'         => $g( 'color' ),
			'在庫状況'   => $g( 'vehicle_status' ),
			'車検満了'   => $g( 'inspection_expiry' ),
		);
		// 内部情報は加盟店/本部のみ。
		if ( $is_staff ) {
			$rows['車台番号'] = $g( 'vin' );
			$rows['ナンバー'] = $g( 'plate_no' );
			$rows['所在地']   = $g( 'location_address' );
		}
		if ( 'hq' === $scope || ( 'store' === $scope && $store_id === $this->current_store_id() ) ) {
			if ( '' !== (string) $g( 'cost' ) ) {
				$rows['仕入原価'] = '¥' . number_format( (float) $g( 'cost' ) );
			}
		}
		echo '<table class="carmel-detail-spec">';
		foreach ( $rows as $k => $v ) {
			if ( '' === (string) $v ) {
				continue;
			}
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
		echo '</table>';

		if ( $store_id && in_array( $scope, array( 'store', 'hq', 'customer' ), true ) ) {
			$sname = get_post_meta( $store_id, 'store_name', true );
			if ( $sname ) {
				echo '<div class="carmel-car-store">取扱店：' . esc_html( $sname ) . '</div>';
			}
		}

		// 説明本文。
		$desc = get_post_field( 'post_content', $vid );
		if ( $desc ) {
			echo '<div class="carmel-detail-desc">' . wp_kses_post( wpautop( $desc ) ) . '</div>';
		}

		// CTA（ログイン分け）。
		$post = get_post( $vid );
		echo $this->card_actions( $post, $scope, 'public', $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		// SNSシェア。
		echo $this->share_buttons( $vid, $title ); // phpcs:ignore WordPress.Security.EscapeOutput

		// お客様向け：この車両への問い合わせフォーム。
		if ( 'customer' === $scope ) {
			echo $this->customer_inquiry_form( $vid ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		// 取扱店の地図。
		echo $this->store_map( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '</div></div></div>';
		return ob_get_clean();
	}

	/** Google Maps APIキー。 */
	private function maps_api_key() {
		return defined( 'CARMEL_MAPS_API_KEY' ) ? CARMEL_MAPS_API_KEY : get_option( 'carmel_maps_api_key', '' );
	}

	/** この在庫詳細の公開URL。 */
	private function detail_url( $vid ) {
		$base = get_permalink();
		if ( ! $base ) {
			$base = home_url( '/' . ltrim( apply_filters( 'carmel_inventory_page_slug', 'inventory' ), '/' ) );
		}
		return add_query_arg( 'vehicle', (int) $vid, $base );
	}

	/** SNSシェアボタン（LINE / X / Facebook / URLコピー）。 */
	private function share_buttons( $vid, $title ) {
		$url  = $this->detail_url( $vid );
		$enc  = rawurlencode( $url );
		$text = rawurlencode( $title . '｜カーメル認定在庫' );
		$line = 'https://social-plugins.line.me/lineit/share?url=' . $enc;
		$x    = 'https://twitter.com/intent/tweet?url=' . $enc . '&text=' . $text;
		$fb   = 'https://www.facebook.com/sharer/sharer.php?u=' . $enc;

		$out  = '<div class="carmel-share"><span class="carmel-share-label">シェア：</span>';
		$out .= '<a class="carmel-share-btn carmel-sh-line" href="' . esc_url( $line ) . '" target="_blank" rel="noopener">LINE</a>';
		$out .= '<a class="carmel-share-btn carmel-sh-x" href="' . esc_url( $x ) . '" target="_blank" rel="noopener">X</a>';
		$out .= '<a class="carmel-share-btn carmel-sh-fb" href="' . esc_url( $fb ) . '" target="_blank" rel="noopener">Facebook</a>';
		$out .= '<button type="button" class="carmel-share-btn carmel-sh-copy" data-url="' . esc_attr( $url ) . '" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.getAttribute(\'data-url\'));this.textContent=\'コピー済\'">URLコピー</button>';
		$out .= '</div>';
		return $out;
	}

	/** 取扱店の所在地マップ（Embed APIキーがあれば埋め込み、無ければ外部リンク）。 */
	private function store_map( $store_id ) {
		$address = $store_id ? (string) get_post_meta( $store_id, 'store_address', true ) : '';
		if ( '' === $address ) {
			return '';
		}
		$sname = (string) get_post_meta( $store_id, 'store_name', true );
		$key   = $this->maps_api_key();
		$out   = '<div class="carmel-map"><h3>取扱店の所在地' . ( $sname ? '（' . esc_html( $sname ) . '）' : '' ) . '</h3>';
		if ( $key ) {
			$src  = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode( $key ) . '&q=' . rawurlencode( $address );
			$out .= '<iframe class="carmel-map-frame" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . esc_url( $src ) . '"></iframe>';
		}
		$out .= '<p><a href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ) . '" target="_blank" rel="noopener">🗺 Googleマップで開く</a></p>';
		$out .= '</div>';
		return $out;
	}

	private function read_filters() {
		return array(
			'maker'     => isset( $_GET['maker'] ) ? sanitize_text_field( wp_unslash( $_GET['maker'] ) ) : '',
			'q'         => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'price_max' => isset( $_GET['price_max'] ) ? (int) $_GET['price_max'] : 0,
		);
	}

	private function filter_bar( array $filters ) {
		$makers = $this->known_makers();
		$out  = '<form method="get" class="carmel-inv-filter">';
		$out .= '<input type="text" name="q" value="' . esc_attr( $filters['q'] ) . '" placeholder="車種・キーワード">';
		$out .= '<select name="maker"><option value="">メーカー（すべて）</option>';
		foreach ( $makers as $m ) {
			$out .= '<option value="' . esc_attr( $m ) . '"' . selected( $filters['maker'], $m, false ) . '>' . esc_html( $m ) . '</option>';
		}
		$out .= '</select>';
		$out .= '<select name="price_max"><option value="">価格上限</option>';
		foreach ( array( 500000, 1000000, 1500000, 2000000, 3000000, 5000000 ) as $p ) {
			$out .= '<option value="' . $p . '"' . selected( $filters['price_max'], $p, false ) . '>¥' . number_format( $p ) . '以下</option>';
		}
		$out .= '</select>';
		$out .= '<button type="submit" class="carmel-btn carmel-btn-purple">絞り込む</button>';
		$out .= '</form>';
		return $out;
	}

	/** 在庫に登録されているメーカー一覧（重複除去）。 */
	private function known_makers() {
		global $wpdb;
		$rows = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key='maker' AND meta_value<>'' ORDER BY meta_value ASC LIMIT 50" );
		return is_array( $rows ) ? $rows : array();
	}

	/* --------------------------------------------------------------------- *
	 * 加盟店在庫（自店管理＋在庫共有）
	 * --------------------------------------------------------------------- */

	public function render_store() {
		$scope = $this->viewer_scope();
		if ( ! in_array( $scope, array( 'store', 'hq' ), true ) ) {
			return '<p class="carmel-notice">加盟店在庫を表示する権限がありません。</p>';
		}
		$store_id = $this->current_store_id();

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-inv"><h2>加盟店在庫</h2>';

		// CSV一括取込。
		echo $this->import_form(); // phpcs:ignore WordPress.Security.EscapeOutput

		// 自店在庫（本部はスキップ案内）。
		if ( $store_id ) {
			echo '<section><h3>🚗 自店の在庫</h3>';
			echo '<p class="carmel-inv-hint"><a href="' . esc_url( admin_url( 'post-new.php?post_type=carmel_vehicle' ) ) . '">＋ 在庫を新規登録</a>（詳細はWP管理画面のフォームで入力）</p>';
			$own = $this->query_store( $store_id );
			echo empty( $own ) ? '<p>登録在庫はありません。</p>' : '<div class="carmel-car-grid">';
			foreach ( $own as $car ) {
				echo $this->car_card( $car, $scope, 'own' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			if ( ! empty( $own ) ) {
				echo '</div>';
			}
			echo '</section>';
		}

		// 在庫共有（他店の公開在庫）。
		echo '<section><h3>🔁 在庫共有（ネットワーク）</h3>';
		echo '<p class="carmel-inv-hint">他の加盟店が公開している在庫です。お客様にご提案でき、取り寄せ・商談を依頼できます（小売価格のみ表示）。</p>';
		$network = $this->query_public( array() );
		$network = array_filter(
			$network,
			function ( $car ) use ( $store_id ) {
				return (int) get_post_meta( $car->ID, 'store_id', true ) !== $store_id;
			}
		);
		if ( empty( $network ) ) {
			echo '<p>共有されている他店在庫はありません。</p>';
		} else {
			echo '<div class="carmel-car-grid">';
			foreach ( $network as $car ) {
				echo $this->car_card( $car, $scope, 'network' ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div>';
		}
		echo '</section>';

		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * 車両カード（コンテキスト別の表示分け）
	 * --------------------------------------------------------------------- */

	/**
	 * @param WP_Post $car
	 * @param string  $scope   guest|customer|store|hq
	 * @param string  $context public|own|network
	 * @return string
	 */
	private function car_card( WP_Post $car, $scope, $context ) {
		$maker   = get_post_meta( $car->ID, 'maker', true );
		$model   = get_post_meta( $car->ID, 'model', true );
		$grade   = get_post_meta( $car->ID, 'grade', true );
		$year    = get_post_meta( $car->ID, 'year', true );
		$mileage = get_post_meta( $car->ID, 'mileage', true );
		$color   = get_post_meta( $car->ID, 'color', true );
		$price   = get_post_meta( $car->ID, 'price', true );
		$cost    = get_post_meta( $car->ID, 'cost', true );
		$status  = get_post_meta( $car->ID, 'vehicle_status', true );
		$store_id= (int) get_post_meta( $car->ID, 'store_id', true );

		$title = trim( $maker . ' ' . $model );
		if ( '' === $title ) {
			$title = get_the_title( $car );
		}

		$thumb = has_post_thumbnail( $car->ID )
			? get_the_post_thumbnail( $car->ID, 'medium', array( 'class' => 'carmel-car-img' ) )
			: '<div class="carmel-car-noimg">NO IMAGE</div>';

		$out  = '<article class="carmel-car-card">';
		$out .= '<div class="carmel-car-thumb">' . $thumb . '<span class="carmel-car-badge">' . esc_html( $status ? $status : '販売中' ) . '</span></div>';
		$out .= '<div class="carmel-car-info">';
		if ( 'public' === $context ) {
			$detail = esc_url( add_query_arg( 'vehicle', (int) $car->ID, remove_query_arg( array( 'maker', 'q', 'price_max' ) ) ) );
			$out   .= '<h4><a class="carmel-car-link" href="' . $detail . '">' . esc_html( $title ) . '</a></h4>';
		} else {
			$out .= '<h4>' . esc_html( $title ) . '</h4>';
		}
		if ( $grade ) {
			$out .= '<div class="carmel-car-grade">' . esc_html( $grade ) . '</div>';
		}
		$specs = array();
		if ( $year ) {
			$specs[] = esc_html( $year ) . '年';
		}
		if ( '' !== (string) $mileage ) {
			$specs[] = number_format( (float) $mileage ) . 'km';
		}
		if ( $color ) {
			$specs[] = esc_html( $color );
		}
		if ( $specs ) {
			$out .= '<div class="carmel-car-specs">' . implode( '｜', $specs ) . '</div>';
		}
		$out .= '<div class="carmel-car-price">¥' . esc_html( number_format( (float) $price ) ) . '<small>（税込）</small></div>';

		// 原価は「本部」または「自店在庫」のみ。
		if ( 'hq' === $scope || ( 'own' === $context && 'store' === $scope ) ) {
			if ( '' !== (string) $cost ) {
				$out .= '<div class="carmel-car-cost">仕入原価：¥' . esc_html( number_format( (float) $cost ) ) . '</div>';
			}
		}

		// 取扱店名（本部・加盟店向け、または公開でも表示）。
		if ( $store_id && in_array( $scope, array( 'store', 'hq', 'customer' ), true ) ) {
			$sname = get_post_meta( $store_id, 'store_name', true );
			if ( $sname ) {
				$out .= '<div class="carmel-car-store">取扱店：' . esc_html( $sname ) . '</div>';
			}
		}

		$out .= $this->card_actions( $car, $scope, $context, $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		$out .= '</div></article>';
		return $out;
	}

	/** カードの操作ボタン群（コンテキスト別）。 */
	private function card_actions( WP_Post $car, $scope, $context, $holding_store ) {
		$out = '<div class="carmel-car-actions">';

		if ( 'own' === $context ) {
			// 公開切替 ＋ 編集。
			$published = in_array( (string) get_post_meta( $car->ID, 'published', true ), array( '1', 'yes', 'true' ), true );
			$nonce     = wp_create_nonce( self::PUBLISH_ACTION . '_' . $car->ID );
			$label     = $published ? '掲載を停止' : '在庫を掲載（共有）';
			$cls       = $published ? 'carmel-btn-ghost' : 'carmel-btn-green';
			$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::PUBLISH_ACTION ) . '">'
				. '<input type="hidden" name="vehicle_id" value="' . (int) $car->ID . '">'
				. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
				. '<button type="submit" class="carmel-btn ' . $cls . '">' . esc_html( $label ) . '</button></form>';
			$out .= '<a class="carmel-btn carmel-btn-blue" href="' . esc_url( get_edit_post_link( $car->ID ) ) . '">編集</a>';
		} elseif ( 'network' === $context ) {
			// 在庫共有：取り寄せ・商談を依頼。
			$nonce = wp_create_nonce( self::INQUIRY_ACTION . '_' . $car->ID );
			$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::INQUIRY_ACTION ) . '">'
				. '<input type="hidden" name="vehicle_id" value="' . (int) $car->ID . '">'
				. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
				. '<button type="submit" class="carmel-btn carmel-btn-purple">取り寄せ・商談を依頼</button></form>';
		} elseif ( 'public' === $context ) {
			if ( 'customer' === $scope ) {
				$apply = home_url( '/' . ltrim( apply_filters( 'carmel_apply_page_slug', 'apply' ), '/' ) );
				$apply = add_query_arg( 'vehicle', (int) $car->ID, $apply );
				$out  .= '<a class="carmel-btn carmel-btn-purple" href="' . esc_url( $apply ) . '">このお車を相談する</a>';
			} elseif ( 'guest' === $scope ) {
				$out .= '<a class="carmel-btn carmel-btn-ghost" href="' . esc_url( home_url( '/login' ) ) . '">ログインして相談</a>';
			}
			// 取扱店の地図リンク。
			$addr = $holding_store ? (string) get_post_meta( $holding_store, 'store_address', true ) : '';
			if ( $addr ) {
				$out .= '<a class="carmel-btn carmel-btn-ghost" href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $addr ) ) . '" target="_blank" rel="noopener">🗺 地図</a>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * CSV一括取込
	 * --------------------------------------------------------------------- */

	/** 取込対象の列（CSVヘッダ名）。 */
	public static function import_columns() {
		return array( 'maker', 'model', 'grade', 'year', 'mileage', 'color', 'vin', 'plate_no', 'price', 'cost', 'vehicle_status', 'published' );
	}

	private function import_form() {
		$is_hq = current_user_can( 'carmel_manage_stores' );
		$nonce = wp_create_nonce( self::IMPORT_ACTION );
		$cols  = implode( ',', self::import_columns() ) . ( $is_hq ? ',store_id' : '' );

		$out  = '<details class="carmel-inv-import"><summary>📥 在庫をCSVで一括取込</summary>';
		$out .= '<p class="carmel-inv-hint">1行目にヘッダ、2行目以降に車両データ。UTF-8またはShift_JIS（最大' . (int) self::IMPORT_MAX_ROWS . '行）。</p>';
		$out .= '<p class="carmel-inv-hint">列：<code>' . esc_html( $cols ) . '</code>';
		$out .= $is_hq ? '（本部は store_id 列で店舗指定可。未指定はご自身の店舗）' : '（自店の在庫として取り込みます）';
		$out .= '</p>';
		$tpl_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TEMPLATE_ACTION ), self::TEMPLATE_ACTION );
		$out .= '<p class="carmel-inv-hint"><a href="' . esc_url( $tpl_url ) . '">⬇ テンプレCSVをダウンロード</a>（記入例つき・Excel対応UTF-8）</p>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::IMPORT_ACTION ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<label><input type="checkbox" name="publish_all" value="1"> 取込時にすべて公開（在庫共有）する</label><br>'
			. '<input type="file" name="csv" accept=".csv,text/csv" required> '
			. '<button type="submit" class="carmel-btn carmel-btn-green">取り込む</button>'
			. '</form></details>';
		return $out;
	}

	/** 記入例つきテンプレCSVを出力（Excel対応のUTF-8 BOM付き）。 */
	public function handle_template() {
		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', self::TEMPLATE_ACTION ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! in_array( $this->viewer_scope(), array( 'store', 'hq' ), true ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$is_hq   = current_user_can( 'carmel_manage_stores' );
		$columns = self::import_columns();
		if ( $is_hq ) {
			$columns[] = 'store_id';
		}
		$sample = array(
			'maker' => 'トヨタ', 'model' => 'アクア', 'grade' => 'S', 'year' => '2019', 'mileage' => '45000',
			'color' => 'ホワイト', 'vin' => 'XXX-1234567', 'plate_no' => '品川 300 あ 12-34', 'price' => '1280000',
			'cost' => '980000', 'vehicle_status' => '販売中', 'published' => '1', 'store_id' => '',
		);
		$row = array();
		foreach ( $columns as $c ) {
			$row[] = isset( $sample[ $c ] ) ? $sample[ $c ] : '';
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="carmel-inventory-template.csv"' );
		echo "\xEF\xBB\xBF"; // BOM
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, $columns );
		fputcsv( $out, $row );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public function handle_import() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store-inventory' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::IMPORT_ACTION ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! in_array( $this->viewer_scope(), array( 'store', 'hq' ), true ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_inv', 'import_nofile', $redirect ) );
			exit;
		}

		$is_hq        = current_user_can( 'carmel_manage_stores' );
		$my_store     = $this->current_store_id();
		$publish_all  = ! empty( $_POST['publish_all'] );
		$raw          = file_get_contents( $_FILES['csv']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			wp_safe_redirect( add_query_arg( 'carmel_inv', 'import_err', $redirect ) );
			exit;
		}
		// 文字コード補正（Shift_JIS等→UTF-8）。
		if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$raw = mb_convert_encoding( $raw, 'UTF-8', 'SJIS-win,SJIS,EUC-JP,UTF-8' );
		}
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw ); // BOM除去

		$lines = preg_split( '/\r\n|\r|\n/', trim( $raw ) );
		if ( count( $lines ) < 2 ) {
			wp_safe_redirect( add_query_arg( 'carmel_inv', 'import_empty', $redirect ) );
			exit;
		}

		$header  = str_getcsv( array_shift( $lines ) );
		$header  = array_map( 'trim', $header );
		$allowed = array_merge( self::import_columns(), array( 'store_id' ) );
		$created = 0;
		$updated = 0;
		$rows    = 0;

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) || $rows >= self::IMPORT_MAX_ROWS ) {
				continue;
			}
			$rows++;
			$values = str_getcsv( $line );
			$data   = array();
			foreach ( $header as $i => $col ) {
				if ( in_array( $col, $allowed, true ) ) {
					$data[ $col ] = isset( $values[ $i ] ) ? trim( $values[ $i ] ) : '';
				}
			}
			if ( empty( $data['maker'] ) && empty( $data['model'] ) ) {
				continue; // 必須最低限が無い行はスキップ。
			}

			// 店舗の決定（加盟店は自店固定、本部は列指定可）。
			$store_id = $my_store;
			if ( $is_hq && ! empty( $data['store_id'] ) ) {
				$store_id = (int) $data['store_id'];
			}

			$meta = array(
				'store_id'       => (int) $store_id,
				'maker'          => sanitize_text_field( isset( $data['maker'] ) ? $data['maker'] : '' ),
				'model'          => sanitize_text_field( isset( $data['model'] ) ? $data['model'] : '' ),
				'grade'          => sanitize_text_field( isset( $data['grade'] ) ? $data['grade'] : '' ),
				'year'           => isset( $data['year'] ) ? (int) $data['year'] : '',
				'mileage'        => isset( $data['mileage'] ) ? (int) $data['mileage'] : '',
				'color'          => sanitize_text_field( isset( $data['color'] ) ? $data['color'] : '' ),
				'vin'            => sanitize_text_field( isset( $data['vin'] ) ? $data['vin'] : '' ),
				'plate_no'       => sanitize_text_field( isset( $data['plate_no'] ) ? $data['plate_no'] : '' ),
				'price'          => isset( $data['price'] ) ? (int) preg_replace( '/[^0-9]/', '', $data['price'] ) : '',
				'cost'           => isset( $data['cost'] ) ? (int) preg_replace( '/[^0-9]/', '', $data['cost'] ) : '',
				'vehicle_status' => sanitize_text_field( ! empty( $data['vehicle_status'] ) ? $data['vehicle_status'] : '販売中' ),
				'published'      => $publish_all || ( isset( $data['published'] ) && in_array( strtolower( $data['published'] ), array( '1', 'yes', 'true', '公開' ), true ) ) ? 1 : 0,
			);

			$title = trim( $meta['maker'] . ' ' . $meta['model'] . ' ' . $meta['grade'] );

			// VIN重複チェック → 既存があれば更新（upsert）。
			$existing_id = '' !== $meta['vin'] ? $this->find_by_vin( $meta['vin'], $store_id, $is_hq ) : 0;
			if ( $existing_id ) {
				wp_update_post( array( 'ID' => $existing_id, 'post_title' => $title ? $title : '車両' ) );
				foreach ( $meta as $k => $v ) {
					update_post_meta( $existing_id, $k, $v );
				}
				$updated++;
				continue;
			}

			$id = wp_insert_post(
				array(
					'post_type'   => 'carmel_vehicle',
					'post_status' => 'publish',
					'post_title'  => $title ? $title : '車両',
					'meta_input'  => $meta,
				)
			);
			if ( ! is_wp_error( $id ) && $id ) {
				$created++;
			}
		}

		do_action( 'carmel_inventory_imported', $created, $updated );
		wp_safe_redirect( add_query_arg( array( 'carmel_inv' => 'import_ok', 'n' => $created, 'u' => $updated ), $redirect ) );
		exit;
	}

	/**
	 * VINで既存車両を検索（加盟店は自店内、本部は全体）。
	 *
	 * @param string $vin
	 * @param int    $store_id 取込先店舗
	 * @param bool   $is_hq
	 * @return int 見つかった車両ID（無ければ0）
	 */
	private function find_by_vin( $vin, $store_id, $is_hq ) {
		$meta_query = array(
			'relation' => 'AND',
			array( 'key' => 'vin', 'value' => $vin ),
		);
		// 加盟店は自店の在庫のみ更新対象（他店の在庫は触れない）。
		if ( ! $is_hq && $store_id ) {
			$meta_query[] = array( 'key' => 'store_id', 'value' => (int) $store_id );
		}
		$found = get_posts(
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			)
		);
		return ! empty( $found ) ? (int) $found[0] : 0;
	}

	/* --------------------------------------------------------------------- *
	 * お客様問い合わせ（在庫詳細）
	 * --------------------------------------------------------------------- */

	private function customer_inquiry_form( $vehicle_id ) {
		$nonce = wp_create_nonce( self::CINQUIRY_ACTION . '_' . $vehicle_id );
		$user  = wp_get_current_user();
		$out  = '<div class="carmel-inq-form"><h3>このお車について問い合わせる</h3>';
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::CINQUIRY_ACTION ) . '">'
			. '<input type="hidden" name="vehicle_id" value="' . (int) $vehicle_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<p class="carmel-inv-hint">' . esc_html( $user->display_name ) . ' 様としてお問い合わせします。</p>'
			. '<textarea name="message" rows="3" placeholder="ご質問・ご希望（試乗希望、見積り希望、在庫確認など）" required></textarea>'
			. '<button type="submit" class="carmel-btn carmel-btn-purple">問い合わせる</button>'
			. '</form></div>';
		return $out;
	}

	public function handle_customer_inquiry() {
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? (int) $_POST['vehicle_id'] : 0;
		$redirect   = wp_get_referer() ? wp_get_referer() : home_url( '/inventory' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::CINQUIRY_ACTION . '_' . $vehicle_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! is_user_logged_in() || 'carmel_vehicle' !== get_post_type( $vehicle_id ) ) {
			wp_die( esc_html__( 'ログインが必要です。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		if ( '' === $message ) {
			wp_safe_redirect( add_query_arg( array( 'vehicle' => $vehicle_id, 'carmel_inv' => 'cust_err' ), $redirect ) );
			exit;
		}

		$user          = wp_get_current_user();
		$holding_store = (int) get_post_meta( $vehicle_id, 'store_id', true );
		$car_title     = trim( get_post_meta( $vehicle_id, 'maker', true ) . ' ' . get_post_meta( $vehicle_id, 'model', true ) );
		$car_title     = $car_title ? $car_title : get_the_title( $vehicle_id );

		// サポートチケット（問い合わせ）として記録。
		wp_insert_post(
			array(
				'post_type'   => 'carmel_support',
				'post_status' => 'publish',
				'post_title'  => '在庫問い合わせ：' . $car_title,
				'meta_input'  => array(
					'support_type' => 'inventory_inquiry',
					'vehicle_id'   => (int) $vehicle_id,
					'customer_id'  => (int) $user->ID,
					'store_id'     => (int) $holding_store,
					'message'      => $message,
					'created_at'   => current_time( 'mysql' ),
				),
			)
		);

		// 保有店＋本部へ通知。
		Carmel_Notifier::notify(
			'inventory_customer_inquiry',
			array(
				'event_id' => 'inv_cust_inquiry:' . $vehicle_id . ':' . $user->ID . ':' . time(),
				'store_id' => $holding_store,
				'vars'     => array(
					'car'      => $car_title,
					'customer' => $user->display_name,
					'message'  => $message,
				),
			)
		);
		do_action( 'carmel_inventory_customer_inquiry', $vehicle_id, $user->ID );

		wp_safe_redirect( add_query_arg( array( 'vehicle' => $vehicle_id, 'carmel_inv' => 'cust_ok' ), $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 操作ハンドラ
	 * --------------------------------------------------------------------- */

	public function handle_publish() {
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? (int) $_POST['vehicle_id'] : 0;
		$redirect   = wp_get_referer() ? wp_get_referer() : home_url( '/store-inventory' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::PUBLISH_ACTION . '_' . $vehicle_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! $this->can_manage_vehicle( $vehicle_id ) ) {
			wp_die( esc_html__( 'この在庫を操作する権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$now = in_array( (string) get_post_meta( $vehicle_id, 'published', true ), array( '1', 'yes', 'true' ), true );
		update_post_meta( $vehicle_id, 'published', $now ? 0 : 1 );
		do_action( 'carmel_inventory_published', $vehicle_id, ! $now );

		wp_safe_redirect( add_query_arg( 'carmel_inv', $now ? 'unpublished' : 'published', $redirect ) );
		exit;
	}

	public function handle_inquiry() {
		$vehicle_id = isset( $_POST['vehicle_id'] ) ? (int) $_POST['vehicle_id'] : 0;
		$redirect   = wp_get_referer() ? wp_get_referer() : home_url( '/store-inventory' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::INQUIRY_ACTION . '_' . $vehicle_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! in_array( $this->viewer_scope(), array( 'store', 'hq' ), true ) || 'carmel_vehicle' !== get_post_type( $vehicle_id ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$holding_store = (int) get_post_meta( $vehicle_id, 'store_id', true );
		$from_store    = $this->current_store_id();
		$from_name     = $from_store ? (string) get_post_meta( $from_store, 'store_name', true ) : '本部';
		$car_title     = trim( get_post_meta( $vehicle_id, 'maker', true ) . ' ' . get_post_meta( $vehicle_id, 'model', true ) );

		// 依頼を在庫メタに追記（簡易ログ）。
		$log = get_post_meta( $vehicle_id, '_carmel_inquiries', true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'from_store' => $from_store,
			'user_id'    => get_current_user_id(),
			'time'       => current_time( 'mysql' ),
		);
		update_post_meta( $vehicle_id, '_carmel_inquiries', $log );

		// 保有店＋本部へ通知。
		Carmel_Notifier::notify(
			'inventory_inquiry',
			array(
				'event_id' => 'inventory_inquiry:' . $vehicle_id . ':' . get_current_user_id() . ':' . time(),
				'store_id' => $holding_store, // 通知の宛先（保有店）
				'vars'     => array(
					'car'        => $car_title ? $car_title : get_the_title( $vehicle_id ),
					'from_store' => $from_name,
				),
			)
		);
		do_action( 'carmel_inventory_inquiry', $vehicle_id, $from_store );

		// 依頼元の店舗で商談（案件）を自動起票し、在庫保有店をセット（手数料が自動連動）。
		$deal_id = $this->create_prospect_deal( $vehicle_id, $from_store, $holding_store );

		$args = array( 'carmel_inv' => 'inquiry_ok' );
		if ( $deal_id ) {
			$args['deal'] = $deal_id;
		}
		wp_safe_redirect( add_query_arg( $args, $redirect ) );
		exit;
	}

	/**
	 * 在庫共有の依頼から、依頼元店舗の商談（案件）を起票する。
	 * 既に同一の未クローズ商談があれば再利用する。
	 *
	 * @param int $vehicle_id
	 * @param int $selling_store 依頼元（販売店）
	 * @param int $holding_store 在庫保有店
	 * @return int 起票/再利用した案件ID（失敗時0）
	 */
	private function create_prospect_deal( $vehicle_id, $selling_store, $holding_store ) {
		if ( ! $selling_store ) {
			return 0; // 本部からの依頼など、販売店が定まらない場合は起票しない。
		}

		// 重複ガード：同じ車両×販売店の商談が既にあれば再利用。
		$existing = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'vehicle_id', 'value' => (int) $vehicle_id ),
					array( 'key' => 'store_id', 'value' => (int) $selling_store ),
					array( 'key' => 'source_store_id', 'value' => (int) $holding_store ),
				),
			)
		);
		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$car_title = trim( get_post_meta( $vehicle_id, 'maker', true ) . ' ' . get_post_meta( $vehicle_id, 'model', true ) );
		$deal_id   = wp_insert_post(
			array(
				'post_type'   => 'carmel_deal',
				'post_status' => 'publish',
				'post_title'  => '在庫共有商談：' . ( $car_title ? $car_title : get_the_title( $vehicle_id ) ),
				'meta_input'  => array(
					'deal_type'       => 'loan',
					'store_id'        => (int) $selling_store,
					'source_store_id' => (int) $holding_store,
					'vehicle_id'      => (int) $vehicle_id,
					'applicant_name'  => '（在庫共有・お客様未確定）',
					'application_note'=> '在庫共有ネットワークから起票された商談です。',
				),
			)
		);
		if ( is_wp_error( $deal_id ) || ! $deal_id ) {
			return 0;
		}

		// ステータスを「加盟店マッチング」に（在庫連動・履歴・通知が発火）。
		Carmel_Deal_Status::change( (int) $deal_id, 'matched', array( 'system' => true, 'note' => '在庫共有から商談起票' ) );
		do_action( 'carmel_inventory_prospect_created', (int) $deal_id, $vehicle_id, $selling_store, $holding_store );
		return (int) $deal_id;
	}

	/** 自店の在庫か（本部は全件可）。 */
	private function can_manage_vehicle( $vehicle_id ) {
		if ( 'carmel_vehicle' !== get_post_type( $vehicle_id ) ) {
			return false;
		}
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return true;
		}
		if ( ! current_user_can( 'carmel_change_deal_status' ) ) {
			return false;
		}
		$my = $this->current_store_id();
		return $my && $my === (int) get_post_meta( $vehicle_id, 'store_id', true );
	}

	/* --------------------------------------------------------------------- *
	 * 通知連携
	 * --------------------------------------------------------------------- */

	public function add_routing( $table ) {
		$table['inventory_inquiry'] = array(
			array( 'audience' => 'store', 'channel' => 'lineworks', 'fallback' => 'mail' ),
			array( 'audience' => 'hq', 'channel' => 'lineworks', 'fallback' => null ),
		);
		$table['inventory_customer_inquiry'] = array(
			array( 'audience' => 'store', 'channel' => 'lineworks', 'fallback' => 'mail' ),
			array( 'audience' => 'hq', 'channel' => 'lineworks', 'fallback' => null ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
		if ( 'inventory_inquiry' === $event_type ) {
			$car  = isset( $vars['car'] ) ? $vars['car'] : '車両';
			$from = isset( $vars['from_store'] ) ? $vars['from_store'] : '他店';
			$message['subject'] = '在庫の取り寄せ・商談依頼';
			$message['body']    = $from . ' より「' . $car . '」の取り寄せ・商談依頼が届きました。ご対応をお願いします。';
		} elseif ( 'inventory_customer_inquiry' === $event_type ) {
			$car  = isset( $vars['car'] ) ? $vars['car'] : '車両';
			$cust = isset( $vars['customer'] ) ? $vars['customer'] : 'お客様';
			$msg  = isset( $vars['message'] ) ? $vars['message'] : '';
			$message['subject'] = '在庫へのお問い合わせ';
			$message['body']    = $cust . ' 様より「' . $car . '」へのお問い合わせがありました。' . ( '' !== $msg ? "\n内容：" . $msg : '' );
		}
		return $message;
	}

	/* --------------------------------------------------------------------- *
	 * バナー・CSS
	 * --------------------------------------------------------------------- */

	private function banner() {
		$key = isset( $_GET['carmel_inv'] ) ? sanitize_key( $_GET['carmel_inv'] ) : '';
		$n   = isset( $_GET['n'] ) ? (int) $_GET['n'] : 0;
		$u   = isset( $_GET['u'] ) ? (int) $_GET['u'] : 0;
		$map = array(
			'published'    => array( 'success', '在庫を掲載しました（共有開始）。' ),
			'unpublished'  => array( 'success', '在庫の掲載を停止しました。' ),
			'inquiry_ok'   => array( 'success', '取り寄せ・商談を依頼し、商談（案件）を起票しました。保有店・本部へ通知しました。' ),
			'cust_ok'      => array( 'success', 'お問い合わせを送信しました。担当店舗よりご連絡します。' ),
			'cust_err'     => array( 'error', '内容を入力してください。' ),
			'import_ok'    => array( 'success', sprintf( '在庫を取り込みました（新規%d件・更新%d件）。', $n, $u ) ),
			'import_nofile'=> array( 'error', 'CSVファイルが選択されていません。' ),
			'import_empty' => array( 'error', 'データ行がありません。' ),
			'import_err'   => array( 'error', 'CSVの読み込みに失敗しました。' ),
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		$extra = '';
		if ( 'inquiry_ok' === $key && isset( $_GET['deal'] ) ) {
			$deal = (int) $_GET['deal'];
			$url  = home_url( '/' . ltrim( apply_filters( 'carmel_store_page_slug', 'store' ), '/' ) );
			$extra = ' <a href="' . esc_url( $url ) . '">起票した商談 #' . $deal . ' を見る</a>';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $key ][0] ) . '">' . esc_html( $map[ $key ][1] ) . $extra . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-inv{font-size:14px}
.carmel-inv section{margin-bottom:1.6em}
.carmel-inv-login{background:#f1ecfb;border:1px solid #ddd2f5;border-radius:.5em;padding:.6em 1em;margin:.6em 0}
.carmel-inv-hint{color:#7a7488;font-size:.88em}
.carmel-inv-filter{display:flex;gap:.5em;flex-wrap:wrap;margin:.8em 0}
.carmel-inv-filter input,.carmel-inv-filter select{border:1px solid #ccc;border-radius:.3em;padding:.45em}
.carmel-car-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1em}
.carmel-car-card{border:1px solid #e7e2ef;border-radius:12px;overflow:hidden;background:#fff;display:flex;flex-direction:column}
.carmel-car-thumb{position:relative;background:#f4f6fb;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;overflow:hidden}
.carmel-car-img{width:100%;height:100%;object-fit:cover}
.carmel-car-noimg{color:#aab;font-size:.85em;letter-spacing:.1em}
.carmel-car-badge{position:absolute;top:.5em;left:.5em;background:rgba(107,79,187,.92);color:#fff;border-radius:.3em;padding:.1em .6em;font-size:.78em}
.carmel-car-info{padding:.7em .85em;display:flex;flex-direction:column;gap:.25em;flex:1}
.carmel-car-info h4{margin:0;font-size:1em}
.carmel-car-grade{color:#7a7488;font-size:.82em}
.carmel-car-specs{font-size:.82em;color:#555}
.carmel-car-price{font-size:1.25em;font-weight:bold;color:#6b4fbb;margin-top:.2em}
.carmel-car-price small{font-size:.55em;color:#888;font-weight:normal}
.carmel-car-cost{font-size:.8em;color:#a5281b}
.carmel-car-store{font-size:.8em;color:#666}
.carmel-car-actions{margin-top:auto;padding-top:.5em;display:flex;gap:.4em;flex-wrap:wrap}
.carmel-car-actions form{margin:0}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.45em .9em;color:#fff;cursor:pointer;font-size:.82em;text-decoration:none}
.carmel-btn-purple{background:#6b4fbb}.carmel-btn-blue{background:#2e86de}.carmel-btn-green{background:#16a085}
.carmel-btn-ghost{background:#eef2fb;color:#2e86de}
.carmel-car-link{color:#5b2a86;text-decoration:none}
.carmel-inv-import{border:1px solid #e7e2ef;border-radius:10px;padding:.6em 1em;margin:.8em 0;background:#fff}
.carmel-inv-import summary{cursor:pointer;font-weight:700}
.carmel-inv-import code{background:#f4f6fb;padding:.1em .4em;border-radius:.2em;font-size:.85em}
.carmel-detail{display:grid;grid-template-columns:minmax(280px,1fr) 1fr;gap:1.4em;margin-top:.6em}
.carmel-detail-img{width:100%;border-radius:12px;object-fit:cover}
.carmel-detail-body h2{margin:.1em 0}
.carmel-detail-spec{width:100%;border-collapse:collapse;margin:.8em 0;font-size:.92em}
.carmel-detail-spec th,.carmel-detail-spec td{border:1px solid #eef0f4;padding:.5em .7em;text-align:left}
.carmel-detail-spec th{background:#f4f6fb;width:35%;white-space:nowrap}
.carmel-detail-desc{line-height:1.85;margin:.8em 0}
.carmel-inq-form{margin-top:1.2em;border-top:1px dashed #e7e2ef;padding-top:1em}
.carmel-inq-form h3{margin:.2em 0 .5em}
.carmel-inq-form textarea{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.5em;margin-bottom:.5em}
.carmel-share{display:flex;align-items:center;gap:.4em;flex-wrap:wrap;margin:1em 0}
.carmel-share-label{font-size:.85em;color:#7a7488}
.carmel-share-btn{border:0;cursor:pointer;text-decoration:none;color:#fff;border-radius:.3em;padding:.35em .8em;font-size:.82em}
.carmel-sh-line{background:#06c755}.carmel-sh-x{background:#000}.carmel-sh-fb{background:#1877f2}.carmel-sh-copy{background:#6b4fbb}
.carmel-map{margin-top:1.2em;border-top:1px dashed #e7e2ef;padding-top:1em}
.carmel-map h3{margin:.2em 0 .5em}
.carmel-map-frame{width:100%;height:300px;border:0;border-radius:10px}
@media(max-width:640px){.carmel-detail{grid-template-columns:1fr}}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
