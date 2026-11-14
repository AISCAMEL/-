<?php
/**
 * カーメル：在庫受信口（外部システム → WP portfolio 反映）
 * ---------------------------------------------------------------------------
 * 外部「カーメルシステム」(Next.js/Supabase) から在庫データを受け取り、
 * WordPress の車両CPT(portfolio)へ作成/更新（upsert）する REST エンドポイント。
 *   POST /wp-json/carmel/v1/inventory   （アプリケーションパスワードで認証）
 *
 * ・stock_id(=管理番号 kanri_bango) で重複判定し、作成 or 更新（冪等）。二重投稿なし。
 * ・公開制御：published かつ status∈{在庫,商談中} → 公開／売約済・納車済・published=false → 下書き。
 * ・基本情報はACFフィールドへ（marker/type/year/mileage/displacement/mission/kudou/color/
 *   inspection/kanri_bango/shop/tel/status/est_honntai/est_total）。装備・写真も反映。
 * ・写真URL（Supabase公開URL）はWPメディアへ取り込み、ギャラリー＋アイキャッチに設定。
 * 管理者/投稿権限ユーザーのみ。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_invrecv_set' ) ) {
	/** ACFがあれば update_field、無ければ post_meta に保存 */
	function carmelx_invrecv_set( $post_id, $name, $value ) {
		if ( $value === null ) { return; }
		if ( function_exists( 'update_field' ) ) { update_field( $name, $value, $post_id ); }
		else { update_post_meta( $post_id, $name, $value ); }
	}
}

if ( ! function_exists( 'carmelx_invrecv_find' ) ) {
	/** kanri_bango(=stock_id) で既存 portfolio を探す */
	function carmelx_invrecv_find( $stock_id ) {
		if ( '' === $stock_id ) { return 0; }
		$q = get_posts( array(
			'post_type'   => 'portfolio',
			'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'meta_key'    => 'kanri_bango',
			'meta_value'  => $stock_id,
			'fields'      => 'ids',
			'numberposts' => 1,
		) );
		return $q ? (int) $q[0] : 0;
	}
}

if ( ! function_exists( 'carmelx_invrecv_shop_slug' ) ) {
	/** 店舗名 or スラッグ → shop 投稿のスラッグ（見つからなければ空） */
	function carmelx_invrecv_shop_slug( $name ) {
		$name = trim( (string) $name );
		if ( '' === $name || ! post_type_exists( 'shop' ) ) { return ''; }
		$shops = get_posts( array( 'post_type' => 'shop', 'post_status' => 'publish', 'posts_per_page' => 100, 'suppress_filters' => true ) );
		$norm = function ( $s ) { return preg_replace( '/[\s　]+/u', '', (string) $s ); };
		foreach ( $shops as $s ) {
			if ( $s->post_name === $name || $norm( $s->post_title ) === $norm( $name ) ) { return $s->post_name; }
		}
		// 部分一致（「カーメル福島本店」⇔「福島本店」など）
		foreach ( $shops as $s ) {
			$t = $norm( $s->post_title ); $n = $norm( $name );
			if ( $t !== '' && $n !== '' && ( false !== mb_strpos( $t, $n ) || false !== mb_strpos( $n, $t ) ) ) { return $s->post_name; }
		}
		return '';
	}
}

if ( ! function_exists( 'carmelx_invrecv_images' ) ) {
	/** 画像URL配列をWPメディアへ取り込み（既に取り込み済みなら再利用）→ 添付ID配列 */
	function carmelx_invrecv_images( $post_id, $urls ) {
		if ( empty( $urls ) || ! is_array( $urls ) ) { return array(); }
		$urls = array_values( array_filter( array_map( 'trim', $urls ) ) );
		$sig_now  = md5( implode( '|', $urls ) );
		$sig_prev = get_post_meta( $post_id, 'carmel_inv_img_sig', true );
		$prev_ids = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post_id, 'carmel_gallery', true ) ) ) );
		if ( $sig_now === $sig_prev && $prev_ids ) { return $prev_ids; } // 変化なし → 再DLしない

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$ids = array();
		foreach ( $urls as $i => $u ) {
			if ( ! preg_match( '#^https?://#i', $u ) ) { continue; }
			if ( $i >= 30 ) { break; } // 上限
			$id = media_sideload_image( $u, $post_id, null, 'id' );
			if ( ! is_wp_error( $id ) ) { $ids[] = (int) $id; }
		}
		if ( $ids ) {
			update_post_meta( $post_id, 'carmel_inv_img_sig', $sig_now );
		}
		return $ids;
	}
}

if ( ! function_exists( 'carmelx_invrecv_handle' ) ) {
	function carmelx_invrecv_handle( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) { $p = $req->get_params(); }

		$stock_id = isset( $p['stock_id'] ) ? sanitize_text_field( (string) $p['stock_id'] ) : '';
		if ( '' === $stock_id ) { return new WP_REST_Response( array( 'ok' => false, 'error' => 'stock_id は必須です' ), 400 ); }

		$str = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? sanitize_text_field( (string) $p[ $k ] ) : ''; };
		$num = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) ? (int) preg_replace( '/[^0-9\-]/', '', (string) $p[ $k ] ) : 0; };

		$maker  = $str( 'maker' );
		$model  = $str( 'model' );
		$grade  = $str( 'grade' );
		$model_code = $str( 'model_code' );
		$year   = $num( 'model_year' );
		$mileage = $num( 'mileage' );
		$status_in = $str( 'status' );               // 在庫/商談中/売約済/納車済
		$published = ! isset( $p['published'] ) || ! empty( $p['published'] );
		$price   = $num( 'price' );
		$total   = $num( 'total_payment' );

		// 車種・型式（type）を合成
		$type = trim( $model . ( $grade !== '' ? ' ' . $grade : '' ) );
		if ( $model_code !== '' ) { $type = trim( $type . ' / ' . $model_code ); }

		// 公開判定
		$public = ( $published && in_array( $status_in, array( '在庫', '商談中', '' ), true ) );
		$post_status = $public ? 'publish' : 'draft';

		// タイトル（指定が無ければ合成）
		$title = $str( 'title' );
		if ( '' === $title ) {
			$title = trim( $maker . ' ' . $model . ' ' . $grade . ( $year ? ' ' . $year . '年' : '' ) . ( $mileage ? ' ' . number_format( $mileage ) . 'km' : '' ) . ' ' . $stock_id );
		}

		$id = carmelx_invrecv_find( $stock_id );
		$arr = array( 'post_type' => 'portfolio', 'post_title' => $title, 'post_status' => $post_status );
		if ( $id ) { $arr['ID'] = $id; $created = false; $id = wp_update_post( $arr, true ); }
		else { $created = true; $id = wp_insert_post( $arr, true ); }
		if ( is_wp_error( $id ) ) { return new WP_REST_Response( array( 'ok' => false, 'error' => $id->get_error_message() ), 500 ); }
		$id = (int) $id;

		// 基本情報（ACF）
		carmelx_invrecv_set( $id, 'kanri_bango', $stock_id );
		carmelx_invrecv_set( $id, 'marker', $maker );
		carmelx_invrecv_set( $id, 'type', $type );
		if ( $year )    { carmelx_invrecv_set( $id, 'year', (string) $year ); }
		if ( $mileage ) { carmelx_invrecv_set( $id, 'mileage', (string) $mileage ); }
		carmelx_invrecv_set( $id, 'displacement', $str( 'displacement' ) );
		carmelx_invrecv_set( $id, 'mission', $str( 'mission' ) );
		carmelx_invrecv_set( $id, 'kudou', $str( 'drive' ) );
		carmelx_invrecv_set( $id, 'color', $str( 'body_color' ) !== '' ? $str( 'body_color' ) : $str( 'color' ) );
		carmelx_invrecv_set( $id, 'inspection', $str( 'inspection_status' ) );
		// 販売店：WP側は shop 投稿のスラッグで持つ。店舗名（またはスラッグ）から解決できた時だけ設定
		$shop_slug = carmelx_invrecv_shop_slug( $str( 'shop' ) );
		if ( $shop_slug !== '' ) {
			update_post_meta( $id, 'shop', $shop_slug );
			carmelx_invrecv_set( $id, 'shop', $shop_slug );
		}
		if ( $str( 'tel' ) !== '' ) { carmelx_invrecv_set( $id, 'tel', $str( 'tel' ) ); }
		// 外部の在庫状態は専用メタに保存（WP側の既存ステータス欄は触らない。公開/下書きで制御）
		update_post_meta( $id, 'carmel_inv_status', $status_in );
		update_post_meta( $id, 'carmel_inv_synced_at', current_time( 'mysql' ) );

		// 安心フラグ（ワンオーナー/禁煙車/記録簿）→ 追加スペックのバッジ
		if ( isset( $p['flags'] ) && is_array( $p['flags'] ) ) {
			foreach ( array( 'one_owner' => 'cx_one_owner', 'kinen' => 'cx_kinen', 'kirokubo' => 'cx_kirokubo' ) as $fk => $mk ) {
				if ( array_key_exists( $fk, $p['flags'] ) ) { update_post_meta( $id, $mk, ! empty( $p['flags'][ $fk ] ) ? '1' : '' ); }
			}
		}

		// 金額（本体・支払総額）
		if ( $price ) { carmelx_invrecv_set( $id, 'est_honntai', (string) $price ); }
		if ( $total ) { carmelx_invrecv_set( $id, 'est_total', (string) $total ); carmelx_invrecv_set( $id, 'total', (string) $total ); }

		// 追加スペック（定員）
		$cap = $num( 'capacity' ); if ( $cap ) { update_post_meta( $id, 'cx_teiin', (string) $cap ); }
		if ( $str( 'sales_point' ) !== '' ) { carmelx_invrecv_set( $id, 'sales_point', $str( 'sales_point' ) ); }

		// 装備（JSONB: {fieldname:true}）→ 一致するACF装備フィールドを 1/0
		$equip_fields = array( 'junsei_nav','shagai_nav','carplay','androidauto','lane_assist','acc','corner_sensor','drive_recorder','ventilation_seat','leather_seat','panorama_roof','push_start','led_light','fog_lamp','adaptive_light','kinen_sha','one_owner','kirokubo','seibi_zumi','shuufuku_nashi','etc2' );
		if ( isset( $p['equipment'] ) && is_array( $p['equipment'] ) ) {
			foreach ( $equip_fields as $ef ) {
				if ( array_key_exists( $ef, $p['equipment'] ) ) {
					carmelx_invrecv_set( $id, $ef, ! empty( $p['equipment'][ $ef ] ) ? 1 : 0 );
				}
			}
		}

		// 写真
		$imgs = isset( $p['images'] ) ? $p['images'] : ( isset( $p['photo_url'] ) ? explode( ',', (string) $p['photo_url'] ) : array() );
		$ids = carmelx_invrecv_images( $id, $imgs );
		if ( $ids ) {
			$csv = implode( ',', $ids );
			update_post_meta( $id, 'carmel_gallery', $csv );
			update_post_meta( $id, 'wpex_post_gallery_ids', $csv );
			update_post_meta( $id, 'vehicle_image_ids', $csv );
			if ( ! has_post_thumbnail( $id ) ) { set_post_thumbnail( $id, $ids[0] ); }
		}

		return new WP_REST_Response( array(
			'ok'       => true,
			'wp_post_id' => $id,
			'created'  => $created,
			'status'   => $post_status,
			'url'      => get_permalink( $id ),
		), 200 );
	}
}

if ( ! function_exists( 'carmelx_invrecv_routes' ) ) {
	function carmelx_invrecv_routes() {
		register_rest_route( 'carmel/v1', '/inventory', array(
			'methods'             => 'POST',
			'callback'            => 'carmelx_invrecv_handle',
			'permission_callback' => function () { return current_user_can( 'publish_posts' ); },
		) );
		// 疎通確認用
		register_rest_route( 'carmel/v1', '/ping', array(
			'methods'             => 'GET',
			'callback'            => function () { return array( 'ok' => true, 'who' => wp_get_current_user()->user_login ); },
			'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
	add_action( 'rest_api_init', 'carmelx_invrecv_routes' );
}
