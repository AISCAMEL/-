<?php
/**
 * 公開「加盟店（店舗）ページ」。
 *
 * ショートコード [carmel_store_profile] を公開ページ（例 /stores）に設置：
 *   - ?store=ID なし … 加盟店ディレクトリ（公開店舗の一覧＋在庫数＋地図リンク）
 *   - ?store=ID あり … 店舗紹介（名称・住所・紹介文・地図）＋その店の公開在庫一覧
 * 一般公開（未ログインでも閲覧可）。内部情報（原価・オーナーID・会費等）は出さない。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store_Profile {

	/** @var Carmel_Store_Profile|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_store_profile';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_head', array( $this, 'seo_head' ) );
	}

	/** 店舗ページのスラッグ（フィルタ可）。 */
	public static function page_slug() {
		return ltrim( apply_filters( 'carmel_store_profile_page_slug', 'stores' ), '/' );
	}

	/** 指定店舗の公開ページURL。 */
	public static function url( $store_id ) {
		return add_query_arg( 'store', (int) $store_id, home_url( '/' . self::page_slug() ) );
	}

	private function inventory_url() {
		return home_url( '/' . ltrim( apply_filters( 'carmel_inventory_page_slug', 'inventory' ), '/' ) );
	}

	private function maps_api_key() {
		return defined( 'CARMEL_MAPS_API_KEY' ) ? CARMEL_MAPS_API_KEY : get_option( 'carmel_maps_api_key', '' );
	}

	/** 販売可能な在庫ステータス。 */
	private function sellable() {
		return class_exists( 'Carmel_Inventory' ) ? Carmel_Inventory::sellable_statuses() : array( '販売中', '商談中' );
	}

	/* --------------------------------------------------------------------- *
	 * ルーティング
	 * --------------------------------------------------------------------- */

	public function render() {
		$store_id = isset( $_GET['store'] ) ? (int) $_GET['store'] : 0;
		if ( $store_id && 'carmel_store' === get_post_type( $store_id ) && 'publish' === get_post_status( $store_id ) ) {
			return $this->render_single( $store_id );
		}
		return $this->render_directory();
	}

	/* --------------------------------------------------------------------- *
	 * ディレクトリ（加盟店一覧）
	 * --------------------------------------------------------------------- */

	private function render_directory() {
		$stores = get_posts(
			array(
				'post_type'      => 'carmel_store',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-sp"><h2>加盟店一覧</h2>';
		if ( empty( $stores ) ) {
			echo '<p>現在ご案内できる加盟店はありません。</p></div>';
			return ob_get_clean();
		}
		echo '<div class="carmel-sp-grid">';
		foreach ( $stores as $s ) {
			$name  = $this->store_name( $s );
			$addr  = (string) get_post_meta( $s->ID, 'store_address', true );
			$count = $this->stock_count( $s->ID );
			echo '<a class="carmel-sp-card" href="' . esc_url( self::url( $s->ID ) ) . '">';
			echo '<div class="carmel-sp-name">' . esc_html( $name ) . '</div>';
			if ( $addr ) {
				echo '<div class="carmel-sp-addr">' . esc_html( $addr ) . '</div>';
			}
			echo '<div class="carmel-sp-stock">公開在庫 ' . (int) $count . ' 台</div>';
			echo '</a>';
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * 店舗ページ（個別）
	 * --------------------------------------------------------------------- */

	private function render_single( $store_id ) {
		$store = get_post( $store_id );
		$name  = $this->store_name( $store );
		$addr  = (string) get_post_meta( $store_id, 'store_address', true );
		$tel   = (string) get_post_meta( $store_id, 'store_tel', true );
		$hours = (string) get_post_meta( $store_id, 'store_hours', true );
		$desc  = (string) get_post_field( 'post_content', $store_id );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-sp">';
		echo '<p class="carmel-sp-back"><a href="' . esc_url( home_url( '/' . self::page_slug() ) ) . '">← 加盟店一覧</a></p>';
		echo '<h2>' . esc_html( $name ) . '</h2>';

		// 店舗情報。
		echo '<table class="carmel-sp-info">';
		if ( $addr ) {
			echo '<tr><th>住所</th><td>' . esc_html( $addr ) . '</td></tr>';
		}
		if ( $tel ) {
			echo '<tr><th>電話</th><td>' . esc_html( $tel ) . '</td></tr>';
		}
		if ( $hours ) {
			echo '<tr><th>営業時間</th><td>' . esc_html( $hours ) . '</td></tr>';
		}
		echo '</table>';

		if ( $desc ) {
			echo '<div class="carmel-sp-desc">' . wp_kses_post( wpautop( $desc ) ) . '</div>';
		}

		// 地図。
		echo $this->map( $addr, $name ); // phpcs:ignore WordPress.Security.EscapeOutput

		// この店舗の公開在庫。
		$cars = $this->stock( $store_id, 12 );
		echo '<h3>取扱在庫</h3>';
		if ( empty( $cars ) ) {
			echo '<p>現在公開中の在庫はありません。</p>';
		} else {
			echo '<div class="carmel-sp-cars">';
			foreach ( $cars as $car ) {
				echo $this->car_card( $car ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div>';
			echo '<p><a class="carmel-btn carmel-btn-purple" href="' . esc_url( add_query_arg( 'store_id', (int) $store_id, $this->inventory_url() ) ) . '">この店舗の在庫をもっと見る →</a></p>';
		}

		// 問い合わせ導線。
		$apply = home_url( '/' . ltrim( apply_filters( 'carmel_apply_page_slug', 'apply' ), '/' ) );
		echo '<div class="carmel-sp-cta"><a class="carmel-btn carmel-btn-blue" href="' . esc_url( $apply ) . '">この店舗に相談・お問い合わせ</a></div>';

		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * 補助
	 * --------------------------------------------------------------------- */

	private function store_name( $store ) {
		$n = (string) get_post_meta( $store->ID, 'store_name', true );
		return $n ? $n : get_the_title( $store );
	}

	/** 店舗の公開在庫を取得。 */
	private function stock( $store_id, $limit = 12 ) {
		return get_posts(
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'store_id', 'value' => (int) $store_id ),
					array( 'key' => 'published', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
					array( 'key' => 'vehicle_status', 'value' => $this->sellable(), 'compare' => 'IN' ),
				),
			)
		);
	}

	private function stock_count( $store_id ) {
		return count( get_posts( array_merge(
			array( 'fields' => 'ids' ),
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'store_id', 'value' => (int) $store_id ),
					array( 'key' => 'published', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
					array( 'key' => 'vehicle_status', 'value' => $this->sellable(), 'compare' => 'IN' ),
				),
			)
		) ) );
	}

	private function car_card( $car ) {
		$maker = (string) get_post_meta( $car->ID, 'maker', true );
		$model = (string) get_post_meta( $car->ID, 'model', true );
		$title = trim( $maker . ' ' . $model );
		$title = '' !== $title ? $title : get_the_title( $car );
		$price = (float) get_post_meta( $car->ID, 'price', true );
		$url   = add_query_arg( 'vehicle', (int) $car->ID, $this->inventory_url() );
		$thumb = has_post_thumbnail( $car->ID )
			? get_the_post_thumbnail( $car->ID, 'medium', array( 'class' => 'carmel-sp-car-img' ) )
			: '<div class="carmel-sp-car-noimg">NO IMAGE</div>';

		return '<a class="carmel-sp-car" href="' . esc_url( $url ) . '">'
			. '<div class="carmel-sp-car-thumb">' . $thumb . '</div>'
			. '<div class="carmel-sp-car-name">' . esc_html( $title ) . '</div>'
			. '<div class="carmel-sp-car-price">¥' . esc_html( number_format( $price ) ) . '</div></a>';
	}

	private function map( $address, $name ) {
		if ( '' === $address ) {
			return '';
		}
		$key = $this->maps_api_key();
		$out = '<div class="carmel-sp-map">';
		if ( $key ) {
			$src = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode( $key ) . '&q=' . rawurlencode( $address );
			$out .= '<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . esc_url( $src ) . '"></iframe>';
		}
		$out .= '<p><a href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ) . '" target="_blank" rel="noopener">🗺 Googleマップで開く</a></p></div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * SEO（店舗ページの AutoDealer 構造化データ）
	 * --------------------------------------------------------------------- */

	public function seo_head() {
		$store_id = isset( $_GET['store'] ) ? (int) $_GET['store'] : 0;
		if ( ! $store_id || 'carmel_store' !== get_post_type( $store_id ) || 'publish' !== get_post_status( $store_id ) ) {
			return;
		}
		$store = get_post( $store_id );
		$name  = $this->store_name( $store );
		$addr  = (string) get_post_meta( $store_id, 'store_address', true );
		$tel   = (string) get_post_meta( $store_id, 'store_tel', true );

		$ld = array(
			'@context' => 'https://schema.org',
			'@type'    => 'AutoDealer',
			'name'     => $name,
			'url'      => self::url( $store_id ),
		);
		if ( $addr ) {
			$ld['address'] = $addr;
		}
		if ( $tel ) {
			$ld['telephone'] = $tel;
		}
		echo "\n<!-- Carmel store SEO -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<meta property="og:type" content="business.business">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $name ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( self::url( $store_id ) ) . '">' . "\n";
	}

	private function styles() {
		return '<style>
.carmel-sp{font-size:14px;max-width:880px}
.carmel-sp-back a{color:#6b4fbb;text-decoration:none}
.carmel-sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1em;margin-top:1em}
.carmel-sp-card{display:block;border:1px solid #e7e2ef;border-radius:12px;padding:1em;background:#fff;text-decoration:none;color:#1a1a2e}
.carmel-sp-card:hover{border-color:#6b4fbb}
.carmel-sp-name{font-weight:bold;font-size:1.05em}
.carmel-sp-addr{color:#7a7488;font-size:.85em;margin:.3em 0}
.carmel-sp-stock{color:#6b4fbb;font-size:.85em}
.carmel-sp-info{border-collapse:collapse;margin:.6em 0}
.carmel-sp-info th,.carmel-sp-info td{border:1px solid #eef0f4;padding:.5em .7em;text-align:left;font-size:.92em}
.carmel-sp-info th{background:#f4f6fb;white-space:nowrap}
.carmel-sp-desc{line-height:1.85;margin:.8em 0}
.carmel-sp-map{margin:1em 0}
.carmel-sp-map iframe{width:100%;height:280px;border:0;border-radius:10px}
.carmel-sp-cars{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.8em;margin:.6em 0}
.carmel-sp-car{display:block;border:1px solid #e7e2ef;border-radius:10px;overflow:hidden;background:#fff;text-decoration:none;color:#1a1a2e}
.carmel-sp-car-thumb{aspect-ratio:4/3;background:#f4f6fb;display:flex;align-items:center;justify-content:center;overflow:hidden}
.carmel-sp-car-img{width:100%;height:100%;object-fit:cover}
.carmel-sp-car-noimg{color:#aab;font-size:.8em}
.carmel-sp-car-name{padding:.5em .6em 0;font-size:.88em;font-weight:600}
.carmel-sp-car-price{padding:0 .6em .6em;color:#6b4fbb;font-weight:bold}
.carmel-sp-cta{margin:1.4em 0}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.6em 1.2em;color:#fff;text-decoration:none}
.carmel-btn-purple{background:#6b4fbb}.carmel-btn-blue{background:#2e86de}
</style>';
	}
}
