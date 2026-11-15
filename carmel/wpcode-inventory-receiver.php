<?php
/**
 * カーメル：在庫受信口（外部システム → WP portfolio 反映）
 * ---------------------------------------------------------------------------
 * 外部「カーメルシステム」(Next.js/Supabase) から在庫データを受け取り、
 * WordPress の車両CPT(portfolio)へ作成/更新（upsert）する REST エンドポイント。
 *   POST /wp-json/carmel/v1/inventory   （アプリケーションパスワードで認証）
 *
 * v1.21.0 の決まり
 * ・車両の同一判定は「外部システムの在庫ID（ext_id）」で行う。管理番号だけでは判定しない。
 *   → 管理番号がたまたま同じ「別の車」（手入力の既存車両・他店の車）を上書きしない。
 *   → 同じ管理番号の手入力車両がある時は 409(needs_adopt) を返し、
 *      外部側で「この車両ページに紐づける」と確認した時（adopt=true）だけ引き継ぐ。
 * ・公開制御：published かつ status∈{在庫,商談中} → 公開／それ以外 → 下書き。
 *   action=unpublish で「下書きに戻すだけ」もできる（外部で在庫を削除した時用）。
 * ・「月々」(total) には支払総額を書かない。支払総額(est_total)から月々を概算して入れる
 *   （手入力の月々がある時は触らない）。
 * ・装備は ACF チェックボックス（選択肢＝表示ラベル）の形式で保存する。
 * ・写真はURLごとに取り込み済みを覚える（途中で失敗・時間切れでも、次回は続きから。二重取り込みなし）。
 * ・空の値では既存の入力を消さない。
 * 管理者/投稿権限ユーザーのみ。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_invrecv_set' ) ) {
	/** ACFがあれば update_field、無ければ post_meta に保存（null/空文字は書かない＝既存値を守る） */
	function carmelx_invrecv_set( $post_id, $name, $value ) {
		if ( $value === null || $value === '' ) { return; }
		if ( function_exists( 'update_field' ) ) { update_field( $name, $value, $post_id ); }
		else { update_post_meta( $post_id, $name, $value ); }
	}
}

if ( ! function_exists( 'carmelx_invrecv_find_by' ) ) {
	/** メタの値で portfolio を1件探す（ゴミ箱は除く） */
	function carmelx_invrecv_find_by( $key, $value ) {
		if ( '' === (string) $value ) { return 0; }
		$q = get_posts( array(
			'post_type'        => 'portfolio',
			'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'meta_key'         => $key,
			'meta_value'       => $value,
			'fields'           => 'ids',
			'numberposts'      => 1,
			'suppress_filters' => true,
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

if ( ! function_exists( 'carmelx_invrecv_equip_labels' ) ) {
	/** 装備フィールド名 => 選択肢の値（＝表示ラベル）。車両ページの装備表示と同じ定義を優先して使う */
	function carmelx_invrecv_equip_labels() {
		$out = array();
		if ( function_exists( 'carmel_equipment_map' ) ) {
			foreach ( (array) carmel_equipment_map() as $items ) {
				foreach ( (array) $items as $name => $label ) { $out[ $name ] = $label; }
			}
			if ( $out ) { return $out; }
		}
		return array(
			'nav5' => 'フルセグTV', 'monitar' => '後席モニター', 'junsei_nav' => '純正ナビ', 'shagai_nav' => '社外ナビ',
			'bluetooth' => 'Bluetooth', 'carplay' => 'Apple CarPlay', 'androidauto' => 'Android Auto',
			'airbag3' => 'サイドエアバッグ', 'airbag4' => 'カーテンエアバッグ', 'abs' => 'ABS', 'esc' => '横滑り防止装置',
			'shoutotu' => '衝突被害軽減ブレーキ', 'kamera3' => 'バックカメラ', 'kamera4' => '全周囲カメラ',
			'lane_assist' => 'レーンアシスト', 'acc' => 'アダプティブクルーズ', 'corner_sensor' => 'コーナーセンサー',
			'drive_recorder' => 'ドライブレコーダー',
			'aircon2' => 'Wエアコン', 'stea' => 'パワーステアリング', 'window' => 'パワーウィンドウ', 'keyless' => 'キーレス',
			'smartkey' => 'スマートキー', 'push_start' => 'プッシュスタート', 'controll' => 'クルーズコントロール',
			'stop' => 'アイドリングストップ', 'seat' => '運転席電動シート', 'heater' => 'シートヒーター', 'work' => 'ウォークスルー',
			'ventilation_seat' => 'ベンチレーションシート', 'leather_seat' => '革シート',
			'door' => '電動スライドドア', 'gate' => '電動トランク・リアゲート', 'almi' => 'アルミホイール', 'earo' => 'エアロパーツ',
			'sunroof' => 'サンルーフ', 'panorama_roof' => 'パノラマルーフ', 'led_light' => 'LEDヘッドライト',
			'fog_lamp' => 'フォグランプ', 'adaptive_light' => 'アダプティブライト',
			'etc' => 'ETC', 'etc2' => 'ETC2.0', 'kinen_sha' => '禁煙車', 'one_owner' => 'ワンオーナー',
			'kirokubo' => '記録簿あり', 'seibi_zumi' => '整備済み', 'shuufuku_nashi' => '修復歴なし',
		);
	}
}

if ( ! function_exists( 'carmelx_invrecv_monthly' ) ) {
	/** 月々の概算（車両ページのローン概算と同じ計算：実質年率13.6%・84回・100円単位切り上げ） */
	function carmelx_invrecv_monthly( $principal ) {
		$principal = (float) $principal;
		if ( $principal <= 0 ) { return 0; }
		if ( function_exists( 'carmelx_lg_monthly_v2' ) ) { return (int) carmelx_lg_monthly_v2( $principal, 13.6, 84 ); }
		$r = ( 13.6 / 100 ) / 12;
		$m = $principal * $r / ( 1 - pow( 1 + $r, -84 ) );
		return (int) ( ceil( $m / 100 ) * 100 );
	}
}

if ( ! function_exists( 'carmelx_invrecv_images' ) ) {
	/**
	 * 画像URL配列をWPメディアへ取り込む。URLごとに「取り込み済みの添付ID」を覚えるので、
	 * 何度送っても二重に取り込まない。途中で失敗・時間切れになっても次回は続きから。
	 * @return array{ids:int[],total:int,failed:int,pending:int}
	 */
	function carmelx_invrecv_images( $post_id, $urls, $deadline ) {
		$res = array( 'ids' => array(), 'total' => 0, 'failed' => 0, 'pending' => 0 );
		if ( empty( $urls ) || ! is_array( $urls ) ) { return $res; }
		$urls = array_slice( array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $urls ) ) ) ) ), 0, 30 );
		$res['total'] = count( $urls );

		$map = get_post_meta( $post_id, 'carmel_inv_img_map', true );
		if ( ! is_array( $map ) ) { $map = array(); }

		foreach ( $urls as $u ) {
			if ( ! preg_match( '#^https?://#i', $u ) ) { $res['failed']++; continue; }
			$k = md5( $u );
			if ( isset( $map[ $k ] ) && 'attachment' === get_post_type( (int) $map[ $k ] ) ) { $res['ids'][] = (int) $map[ $k ]; continue; }
			if ( time() >= $deadline ) { $res['pending']++; continue; } // 時間切れ → 次回に続きから
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$id = media_sideload_image( $u, $post_id, null, 'id' );
			if ( is_wp_error( $id ) || ! $id ) { $res['failed']++; continue; }
			$map[ $k ] = (int) $id;
			$res['ids'][] = (int) $id;
		}
		update_post_meta( $post_id, 'carmel_inv_img_map', $map );
		return $res;
	}
}

if ( ! function_exists( 'carmelx_invrecv_err' ) ) {
	function carmelx_invrecv_err( $http, $code, $msg, $extra = array() ) {
		return new WP_REST_Response( array_merge( array( 'ok' => false, 'code' => $code, 'error' => $msg ), $extra ), $http );
	}
}

if ( ! function_exists( 'carmelx_invrecv_handle' ) ) {
	function carmelx_invrecv_handle( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) { $p = $req->get_params(); }

		$str = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ? sanitize_text_field( (string) $p[ $k ] ) : ''; };
		$num = function ( $k ) use ( $p ) { return isset( $p[ $k ] ) && is_scalar( $p[ $k ] ) ? (int) preg_replace( '/[^0-9]/', '', (string) $p[ $k ] ) : 0; };

		$ext_id = $str( 'ext_id' );
		if ( '' === $ext_id ) {
			return carmelx_invrecv_err( 400, 'ext_id_required', '外部システムの在庫ID（ext_id）がありません。外部システム側を最新版にしてください' );
		}

		/* ---------- 下書きに戻すだけ（外部で在庫を削除した時など） ---------- */
		if ( 'unpublish' === $str( 'action' ) ) {
			$id = carmelx_invrecv_find_by( 'carmel_inv_ext_id', $ext_id );
			if ( ! $id ) { return new WP_REST_Response( array( 'ok' => true, 'wp_post_id' => 0, 'status' => 'none', 'url' => '' ), 200 ); }
			if ( ! current_user_can( 'edit_post', $id ) ) { return carmelx_invrecv_err( 403, 'forbidden', 'この車両ページを編集する権限がありません' ); }
			$r = wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ), true );
			if ( is_wp_error( $r ) ) { return carmelx_invrecv_err( 500, 'wp_error', $r->get_error_message() ); }
			update_post_meta( $id, 'carmel_inv_status', '削除' );
			update_post_meta( $id, 'carmel_inv_synced_at', current_time( 'mysql' ) );
			return new WP_REST_Response( array( 'ok' => true, 'wp_post_id' => $id, 'status' => 'draft', 'url' => get_permalink( $id ) ), 200 );
		}

		$stock_id = $str( 'stock_id' );
		if ( '' === $stock_id ) { return carmelx_invrecv_err( 400, 'stock_id_required', '管理ID（stock_id）は必須です' ); }

		/* ---------- どの車両ページか（ext_id が最優先。管理番号だけでは上書きしない） ---------- */
		$id      = carmelx_invrecv_find_by( 'carmel_inv_ext_id', $ext_id );
		$adopted = false;
		$same_no = carmelx_invrecv_find_by( 'kanri_bango', $stock_id );
		if ( $same_no && $same_no !== $id ) {
			$other_ext = (string) get_post_meta( $same_no, 'carmel_inv_ext_id', true );
			$existing  = array( 'id' => $same_no, 'title' => get_the_title( $same_no ), 'url' => get_permalink( $same_no ), 'status' => get_post_status( $same_no ) );
			if ( '' !== $other_ext ) {
				// 別の在庫（別の店舗の車など）が同じ管理番号で反映済み
				return carmelx_invrecv_err( 409, 'stock_id_taken', '管理ID「' . $stock_id . '」は、ホームページ上で別の車両（' . $existing['title'] . '）に使われています。管理IDを変えてから反映してください', array( 'existing' => $existing ) );
			}
			if ( $id ) {
				// すでに自分のページがあるのに、同じ管理番号の手入力ページが別にある
				return carmelx_invrecv_err( 409, 'stock_id_taken', '管理ID「' . $stock_id . '」は、ホームページ上の別の車両（' . $existing['title'] . '）と重なっています。管理IDを変えてから反映してください', array( 'existing' => $existing ) );
			}
			// 手入力（または旧版の反映）で作られた同じ管理番号の車両ページ
			$by_old_sync = '' !== (string) get_post_meta( $same_no, 'carmel_inv_synced_at', true );
			if ( ! $by_old_sync && empty( $p['adopt'] ) ) {
				return carmelx_invrecv_err( 409, 'needs_adopt', '管理ID「' . $stock_id . '」の車両ページが、ホームページにすでにあります（' . $existing['title'] . '）。同じ車なら「このページに紐づける」を選んでください', array( 'existing' => $existing ) );
			}
			$id = $same_no; $adopted = ! $by_old_sync;
		}
		if ( $id && ! current_user_can( 'edit_post', $id ) ) { return carmelx_invrecv_err( 403, 'forbidden', 'この車両ページを編集する権限がありません' ); }

		$maker      = $str( 'maker' );
		$model      = $str( 'model' );
		$grade      = $str( 'grade' );
		$year       = $num( 'model_year' );
		$mileage    = $num( 'mileage' );
		$status_in  = $str( 'status' );               // 在庫/商談中/売約済/納車済
		$published  = ! isset( $p['published'] ) || ! empty( $p['published'] );
		$price      = $num( 'price' );
		$total      = $num( 'total_payment' );
		$color      = $str( 'body_color' ) !== '' ? $str( 'body_color' ) : preg_replace( '/系$/u', '', $str( 'color' ) );

		// 車種（type）＝ 車種＋グレード（管理画面の手入力と同じ決まり）
		$type = trim( $model . ( $grade !== '' ? ' ' . $grade : '' ) );

		// 公開判定
		$public      = ( $published && in_array( $status_in, array( '在庫', '商談中', '' ), true ) );
		$post_status = $public ? 'publish' : 'draft';

		// タイトル（管理画面の自動生成と同じ並び：信用回復ローン メーカー 車種 グレード 年式 色 走行距離 管理番号）
		$title = $str( 'title' );
		if ( '' === $title ) {
			$parts = array( '信用回復ローン', $maker, $model, $grade, $year ? $year . '年' : '', $color, $mileage ? number_format( $mileage ) . 'km' : '', $stock_id );
			$title = trim( preg_replace( '/\s+/u', ' ', implode( ' ', array_filter( $parts, function ( $v ) { return '' !== trim( (string) $v ); } ) ) ) );
		}

		$arr = array( 'post_type' => 'portfolio', 'post_title' => $title, 'post_status' => $post_status );
		if ( $id ) { $arr['ID'] = $id; $created = false; $id = wp_update_post( $arr, true ); }
		else { $created = true; $id = wp_insert_post( $arr, true ); }
		if ( is_wp_error( $id ) ) { return carmelx_invrecv_err( 500, 'wp_error', $id->get_error_message() ); }
		$id = (int) $id;

		// 紐づけ（次回からは ext_id で同じページを見つける）
		update_post_meta( $id, 'carmel_inv_ext_id', $ext_id );
		update_post_meta( $id, 'carmel_inv_status', $status_in );
		update_post_meta( $id, 'carmel_inv_synced_at', current_time( 'mysql' ) );
		if ( $str( 'model_code' ) !== '' ) { update_post_meta( $id, 'carmel_inv_model_code', $str( 'model_code' ) ); }

		// 基本情報（ACF）※空の値は書かない＝既存の入力を消さない
		carmelx_invrecv_set( $id, 'kanri_bango', $stock_id );
		carmelx_invrecv_set( $id, 'marker', $maker );
		carmelx_invrecv_set( $id, 'type', $type );
		if ( $year )    { carmelx_invrecv_set( $id, 'year', (string) $year ); }
		if ( $mileage ) { carmelx_invrecv_set( $id, 'mileage', (string) $mileage ); }
		carmelx_invrecv_set( $id, 'displacement', $str( 'displacement' ) );
		carmelx_invrecv_set( $id, 'mission', $str( 'mission' ) );
		carmelx_invrecv_set( $id, 'kudou', $str( 'drive' ) );
		carmelx_invrecv_set( $id, 'color', $color );
		carmelx_invrecv_set( $id, 'inspection', $str( 'inspection_status' ) );
		$handle = $str( 'handle' );
		if ( '右' === $handle || '左' === $handle ) { $handle .= 'ハンドル'; }
		carmelx_invrecv_set( $id, 'handle', $handle );
		carmelx_invrecv_set( $id, 'repair_history', $str( 'repair_history' ) );
		carmelx_invrecv_set( $id, 'fuel_type', $str( 'fuel' ) );
		$seibi = $str( 'legal_maintenance' );
		if ( '' !== $seibi ) { carmelx_invrecv_set( $id, 'seibi', ( false !== mb_strpos( $seibi, 'なし' ) ) ? 'なし' : 'あり' ); }
		$recycle = $num( 'recycle_deposit' );
		if ( $recycle ) { carmelx_invrecv_set( $id, 'est_recycle', (string) $recycle ); carmelx_invrecv_set( $id, 'recicle', number_format( $recycle ) . '円' ); }

		// 販売店：WP側は shop 投稿のスラッグで持つ。店舗名（またはスラッグ）から解決できた時だけ設定
		$shop_slug = carmelx_invrecv_shop_slug( $str( 'shop' ) );
		if ( $shop_slug !== '' ) {
			update_post_meta( $id, 'shop', $shop_slug );
			carmelx_invrecv_set( $id, 'shop', $shop_slug );
		}
		carmelx_invrecv_set( $id, 'tel', $str( 'tel' ) );

		// 安心フラグ（ワンオーナー/禁煙車/記録簿）→ 追加スペックのバッジ
		if ( isset( $p['flags'] ) && is_array( $p['flags'] ) ) {
			foreach ( array( 'one_owner' => 'cx_one_owner', 'kinen' => 'cx_kinen', 'kirokubo' => 'cx_kirokubo' ) as $fk => $mk ) {
				if ( array_key_exists( $fk, $p['flags'] ) ) { update_post_meta( $id, $mk, ! empty( $p['flags'][ $fk ] ) ? '1' : '' ); }
			}
		}

		// 金額：本体（est_honntai・price）／支払総額（est_total）
		if ( $price ) { carmelx_invrecv_set( $id, 'est_honntai', (string) $price ); carmelx_invrecv_set( $id, 'price', (string) $price ); }
		if ( $total ) { carmelx_invrecv_set( $id, 'est_total', (string) $total ); }
		// 月々（total）：支払総額ではなく「月々の概算」。手入力の月々がある時は触らない
		$base = $total ? $total : $price;
		if ( $base ) {
			$atama   = (int) preg_replace( '/[^0-9]/', '', (string) get_post_meta( $id, 'est_atamakin', true ) );
			$monthly = carmelx_invrecv_monthly( max( 0, $base - $atama ) );
			$cur     = trim( (string) get_post_meta( $id, 'total', true ) );
			$auto    = trim( (string) get_post_meta( $id, 'carmel_inv_total_auto', true ) );
			if ( $monthly > 0 && ( '' === $cur || $cur === $auto ) ) {
				$val = number_format( $monthly ); // 「円」は表示側で付く
				carmelx_invrecv_set( $id, 'total', $val );
				update_post_meta( $id, 'carmel_inv_total_auto', $val );
			}
		}

		// 追加スペック（定員）・セールスポイント
		$cap = $num( 'capacity' ); if ( $cap ) { update_post_meta( $id, 'cx_teiin', (string) $cap ); }
		carmelx_invrecv_set( $id, 'sales_point', $str( 'sales_point' ) );

		// 装備：ACFチェックボックス（選択肢＝表示ラベル）。送られてきた項目だけ ON/OFF
		if ( isset( $p['equipment'] ) && is_array( $p['equipment'] ) ) {
			$labels = carmelx_invrecv_equip_labels();
			foreach ( $p['equipment'] as $ef => $on ) {
				if ( ! isset( $labels[ $ef ] ) ) { continue; }
				$val = ! empty( $on ) ? array( $labels[ $ef ] ) : array();
				if ( function_exists( 'update_field' ) ) { update_field( $ef, $val, $id ); }
				else { update_post_meta( $id, $ef, $val ? $labels[ $ef ] : '' ); }
			}
		}

		// 写真（1回の受信で使う時間に上限。残りは次回の反映で続きから）
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 150 ); }
		$imgs = isset( $p['images'] ) && is_array( $p['images'] ) ? $p['images'] : array();
		$im   = carmelx_invrecv_images( $id, $imgs, time() + 60 );
		if ( $im['ids'] ) {
			$csv = implode( ',', $im['ids'] );
			update_post_meta( $id, 'carmel_gallery', $csv );
			update_post_meta( $id, 'wpex_post_gallery_ids', $csv );
			update_post_meta( $id, 'vehicle_image_ids', $csv );
			set_post_thumbnail( $id, $im['ids'][0] ); // 1枚目＝アイキャッチ
		}

		// 表示用のミラー項目（修復歴→shuufuku 等）を既存の仕組みで更新
		if ( function_exists( 'carmel_mirror_detail_fields_one' ) ) { carmel_mirror_detail_fields_one( $id ); }

		return new WP_REST_Response( array(
			'ok'             => true,
			'wp_post_id'     => $id,
			'created'        => $created,
			'adopted'        => $adopted,
			'status'         => $post_status,
			'url'            => get_permalink( $id ),
			'images_total'   => $im['total'],
			'images_done'    => count( $im['ids'] ),
			'images_failed'  => $im['failed'],
			'images_pending' => $im['pending'],
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
			'callback'            => function () { return array( 'ok' => true, 'who' => wp_get_current_user()->user_login, 'version' => defined( 'CARMEL_SYS_VERSION' ) ? CARMEL_SYS_VERSION : '' ); },
			'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
	add_action( 'rest_api_init', 'carmelx_invrecv_routes' );
}
