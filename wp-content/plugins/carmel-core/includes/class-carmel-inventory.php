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
	const NONCE            = 'carmel_inv_nonce';

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
		$out .= '<h4>' . esc_html( $title ) . '</h4>';
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
		}

		$out .= '</div>';
		return $out;
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

		wp_safe_redirect( add_query_arg( 'carmel_inv', 'inquiry_ok', $redirect ) );
		exit;
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
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'inventory_inquiry' === $event_type ) {
			$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$car  = isset( $vars['car'] ) ? $vars['car'] : '車両';
			$from = isset( $vars['from_store'] ) ? $vars['from_store'] : '他店';
			$message['subject'] = '在庫の取り寄せ・商談依頼';
			$message['body']    = $from . ' より「' . $car . '」の取り寄せ・商談依頼が届きました。ご対応をお願いします。';
		}
		return $message;
	}

	/* --------------------------------------------------------------------- *
	 * バナー・CSS
	 * --------------------------------------------------------------------- */

	private function banner() {
		$key = isset( $_GET['carmel_inv'] ) ? sanitize_key( $_GET['carmel_inv'] ) : '';
		$map = array(
			'published'   => array( 'success', '在庫を掲載しました（共有開始）。' ),
			'unpublished' => array( 'success', '在庫の掲載を停止しました。' ),
			'inquiry_ok'  => array( 'success', '取り寄せ・商談を依頼しました。保有店・本部へ通知しました。' ),
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $key ][0] ) . '">' . esc_html( $map[ $key ][1] ) . '</div>';
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
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
