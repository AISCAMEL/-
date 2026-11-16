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
 *
 * v1.22.0 追加
 * ・GET /wp-json/carmel/v1/inventory/export … ホームページに載っている車両を、外部システムの
 *   在庫へ取り込むための一覧（読み取りだけ。サイトの表示・データは何も変えない）。
 * ・取り込んだ車両を外部から反映する時は、link_post_id（元の車両ページ番号）で同じページに
 *   紐づく（管理番号も一致している時だけ。確認の手間なし・別の車は上書きしない）。
 * ・写真がこのサイトのメディアに既にある時は、取り込み直さずそのまま使う。
 *
 * v1.23.0 追加
 * ・諸費用の内訳（値引き・下取り・税金/保険料・登録/諸費用・消費税）を受け取り／取り込みに対応。
 *   車両ページの「お支払総額の内訳」(est_*) と諸経費合計(keihi)に反映する。
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

if ( ! function_exists( 'carmelx_invrecv_fee_keys' ) ) {
	/** 諸費用の内訳で外部システムとやり取りする欄（WP側の欄名は est_ を頭に付けたもの） */
	function carmelx_invrecv_fee_keys() {
		return array(
			'nebiki', 'shitadori',                                   // 値引き・下取り
			'jidoshazei', 'jibaiseki', 'juuryouzei', 'inshi',        // 税金・保険料（非課税）
			'touroku_daiko', 'shako', 'nousha', 'kensa_daiko', 'seibi', 'hoshou', 'hanbai_tesuu', 'auction', // 登録・諸費用（課税）
			'shouhizei',                                             // 消費税
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

if ( ! function_exists( 'carmelx_invrecv_local_attachment' ) ) {
	/**
	 * 画像URLが、このサイトのメディアに既にあるものなら添付IDを返す（無ければ 0）。
	 * コピーサイト⇔本番でURLの頭が違っても、uploads 以下の場所が同じなら同じ画像とみなす。
	 */
	function carmelx_invrecv_local_attachment( $url ) {
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$id = (int) attachment_url_to_postid( $url );
			if ( $id ) { return $id; }
		}
		$pos = strpos( $url, '/wp-content/uploads/' );
		if ( false === $pos ) { return 0; }
		$rel = preg_replace( '/[?#].*$/', '', substr( $url, $pos + strlen( '/wp-content/uploads/' ) ) );
		if ( '' === $rel ) { return 0; }
		$q = get_posts( array(
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'meta_key'         => '_wp_attached_file',
			'meta_value'       => $rel,
			'fields'           => 'ids',
			'numberposts'      => 1,
			'suppress_filters' => true,
		) );
		return $q ? (int) $q[0] : 0;
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
			// このサイトのメディアに既にある画像（WPから取り込んだ車両の写真など）は取り込み直さない
			$local = carmelx_invrecv_local_attachment( $u );
			if ( $local ) { $map[ $k ] = $local; $res['ids'][] = $local; continue; }
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
			// WPから取り込んだ在庫は、元の車両ページ番号（link_post_id）が一致すれば確認なしで紐づく
			$by_import = isset( $p['link_post_id'] ) && (int) $p['link_post_id'] === $same_no;
			if ( ! $by_old_sync && ! $by_import && empty( $p['adopt'] ) ) {
				return carmelx_invrecv_err( 409, 'needs_adopt', '管理ID「' . $stock_id . '」の車両ページが、ホームページにすでにあります（' . $existing['title'] . '）。同じ車なら「このページに紐づける」を選んでください', array( 'existing' => $existing ) );
			}
			$id = $same_no; $adopted = ! $by_old_sync && ! $by_import;
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

		// 諸費用の内訳（送られてきた欄だけ更新。0 は「0円」として保存、欄が無ければ触らない）
		if ( isset( $p['fees'] ) && is_array( $p['fees'] ) ) {
			$keihi = 0; $any = false;
			foreach ( carmelx_invrecv_fee_keys() as $fk ) {
				if ( ! array_key_exists( $fk, $p['fees'] ) || ! is_scalar( $p['fees'][ $fk ] ) ) { continue; }
				$fv = (int) preg_replace( '/[^0-9]/', '', (string) $p['fees'][ $fk ] );
				if ( function_exists( 'update_field' ) ) { update_field( 'est_' . $fk, (string) $fv, $id ); }
				else { update_post_meta( $id, 'est_' . $fk, (string) $fv ); }
				$any = true;
				if ( ! in_array( $fk, array( 'nebiki', 'shitadori' ), true ) ) { $keihi += $fv; }
			}
			// 諸経費合計（税金・保険料＋登録・諸費用＋消費税＋リサイクル料）※値引き・下取りは含めない
			if ( $any ) {
				$keihi += $recycle ? $recycle : (int) preg_replace( '/[^0-9]/', '', (string) get_post_meta( $id, 'est_recycle', true ) );
				if ( $keihi > 0 ) { carmelx_invrecv_set( $id, 'keihi', number_format( $keihi ) . '円' ); }
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
		// セールスポイント → 車両ページの「担当者より」(comment)。改行を残す
		$sp = isset( $p['sales_point'] ) && is_scalar( $p['sales_point'] ) ? trim( sanitize_textarea_field( (string) $p['sales_point'] ) ) : '';
		carmelx_invrecv_set( $id, 'comment', $sp );

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

if ( ! function_exists( 'carmelx_invrecv_export_item' ) ) {
	/** 車両ページ1件を、外部システムの在庫に取り込める形にまとめる（読み取りだけ） */
	function carmelx_invrecv_export_item( $pid ) {
		$g = function ( $keys ) use ( $pid ) {
			foreach ( (array) $keys as $k ) {
				$v = get_post_meta( $pid, $k, true );
				if ( is_array( $v ) ) { $v = implode( '・', array_filter( array_map( 'strval', $v ) ) ); }
				$v = trim( (string) $v );
				if ( '' !== $v ) { return $v; }
			}
			return '';
		};
		$digits = function ( $v ) {
			$v = function_exists( 'mb_convert_kana' ) ? mb_convert_kana( (string) $v, 'n', 'UTF-8' ) : (string) $v;
			$d = preg_replace( '/[^0-9]/', '', $v );
			return '' === $d ? null : (int) $d;
		};

		// 装備：チェックの入っている欄の名前
		$equipment = array();
		foreach ( carmelx_invrecv_equip_labels() as $name => $label ) {
			$v = get_post_meta( $pid, $name, true );
			if ( is_array( $v ) ) { $v = array_filter( $v ); }
			if ( ! empty( $v ) && '0' !== $v ) { $equipment[ $name ] = true; }
		}

		// 写真：アイキャッチ → ギャラリーの順（重複なし）
		$ids = array();
		$th  = (int) get_post_thumbnail_id( $pid );
		if ( $th ) { $ids[] = $th; }
		foreach ( array( 'carmel_gallery', 'wpex_post_gallery_ids', 'vehicle_image_ids', '_easy_image_gallery' ) as $mk ) {
			$raw = get_post_meta( $pid, $mk, true );
			if ( is_array( $raw ) ) { $raw = implode( ',', $raw ); }
			foreach ( array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) ) as $aid ) { $ids[] = $aid; }
		}
		$images = array();
		foreach ( array_slice( array_values( array_unique( $ids ) ), 0, 30 ) as $aid ) {
			$u = wp_get_attachment_url( $aid );
			if ( $u ) { $images[] = $u; }
		}

		// 販売店（shop 投稿のスラッグ → 店舗名）
		$shop_slug = $g( array( 'shop' ) );
		$shop_name = '';
		if ( '' !== $shop_slug ) {
			$sp = get_posts( array( 'post_type' => 'shop', 'name' => $shop_slug, 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => true ) );
			if ( $sp ) { $shop_name = $sp[0]->post_title; }
		}

		$year = $digits( $g( array( 'year', 'nenshiki' ) ) );
		if ( $year && ( $year < 1950 || $year > 2100 ) ) { $year = null; }

		// 諸費用の内訳（金額が入っている欄だけ）
		$fees = array();
		foreach ( carmelx_invrecv_fee_keys() as $fk ) {
			$fv = $digits( $g( array( 'est_' . $fk ) ) );
			if ( $fv ) { $fees[ $fk ] = $fv; }
		}

		return array(
			'wp_post_id'     => (int) $pid,
			'ext_id'         => (string) get_post_meta( $pid, 'carmel_inv_ext_id', true ),
			'url'            => get_permalink( $pid ),
			'status'         => get_post_status( $pid ),
			'title'          => get_the_title( $pid ),
			'date'           => get_post_field( 'post_date', $pid ),
			'stock_id'       => $g( array( 'kanri_bango' ) ),
			'maker'          => $g( array( 'marker', 'maker' ) ),
			'type'           => $g( array( 'type', 'name', 'car_model', 'shashu' ) ),
			'year'           => $year,
			'mileage'        => $digits( $g( array( 'mileage', 'soukou', 'soukou_kyori', 'kyori' ) ) ),
			'displacement'   => $digits( $g( array( 'displacement', 'haikiryou', 'haiki' ) ) ),
			'mission'        => $g( array( 'mission', 'mt' ) ),
			'drive'          => $g( array( 'kudou', 'drive' ) ),
			'color'          => $g( array( 'color', 'body_color', 'iro' ) ),
			'inspection'     => $g( array( 'inspection', 'shaken' ) ),
			'handle'         => $g( array( 'handle' ) ),
			'repair_history' => $g( array( 'repair_history', 'shuufuku' ) ),
			'fuel'           => $g( array( 'fuel_type', 'fuel', 'nenryou' ) ),
			'seibi'          => $g( array( 'seibi' ) ),
			'recycle'        => $digits( $g( array( 'est_recycle', 'recicle', 'recycle' ) ) ),
			'price'          => $digits( $g( array( 'est_honntai', 'price' ) ) ),
			'total_payment'  => $digits( $g( array( 'est_total' ) ) ),
			'capacity'       => $digits( $g( array( 'cx_teiin' ) ) ),
			'fees'           => (object) $fees,
			'comment'        => $g( array( 'comment' ) ),
			'shop_slug'      => $shop_slug,
			'shop_name'      => $shop_name,
			'equipment'      => $equipment,
			'images'         => $images,
		);
	}
}

if ( ! function_exists( 'carmelx_invrecv_export' ) ) {
	/** GET carmel/v1/inventory/export?page=1&per_page=50&status=publish|all */
	function carmelx_invrecv_export( WP_REST_Request $req ) {
		$page = max( 1, (int) $req->get_param( 'page' ) );
		$per  = (int) $req->get_param( 'per_page' );
		$per  = $per > 0 ? min( 100, $per ) : 50;
		$statuses = ( 'all' === $req->get_param( 'status' ) ) ? array( 'publish', 'draft', 'pending', 'private' ) : array( 'publish' );
		$q = new WP_Query( array(
			'post_type'        => 'portfolio',
			'post_status'      => $statuses,
			'posts_per_page'   => $per,
			'paged'            => $page,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'suppress_filters' => true,
		) );
		$items = array();
		foreach ( (array) $q->posts as $pid ) { $items[] = carmelx_invrecv_export_item( (int) $pid ); }
		return new WP_REST_Response( array(
			'ok'    => true,
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
			'page'  => $page,
			'items' => $items,
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
		// ホームページの車両 → 外部システムの在庫へ取り込むための一覧（読み取りだけ）
		register_rest_route( 'carmel/v1', '/inventory/export', array(
			'methods'             => 'GET',
			'callback'            => 'carmelx_invrecv_export',
			'permission_callback' => function () { return current_user_can( 'edit_others_posts' ); },
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
