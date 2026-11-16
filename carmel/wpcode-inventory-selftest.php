<?php
/**
 * カーメル：在庫連携 診断（管理画面「ツール → 在庫連携 診断」）
 * ---------------------------------------------------------------------------
 * 在庫連携の受け口（wpcode-inventory-receiver.php）を、このサイトの中で実際に動かして確かめる。
 * 外部システムやログイン情報なしで、本物の WordPress・ACF・テーマの保存時の仕組みの上で試せる。
 *
 * やること（ボタンを押した時だけ）：
 *   ・テスト用の車両ページを1台作る（下書きのまま。「公開の動きも試す」を選んだ時だけ数秒公開）
 *   ・基本情報／金額・月々／装備／諸費用／写真／再送／更新／管理IDの重なり／取り下げ／取り込み用の一覧／目印 を確認
 *   ・最後にテスト用の車両ページを完全に削除する（既存の車両ページ・画像には触らない）
 * 管理者のみ。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_invtest_menu' ) ) {
	function carmelx_invtest_menu() {
		add_management_page( '在庫連携 診断', '在庫連携 診断', 'manage_options', 'carmel-inv-selftest', 'carmelx_invtest_render' );
	}
	add_action( 'admin_menu', 'carmelx_invtest_menu' );
}

if ( ! function_exists( 'carmelx_invtest_call' ) ) {
	/** 受け口を、外部システムから届いた時と同じ形（JSON）で呼ぶ */
	function carmelx_invtest_call( $params ) {
		$req = new WP_REST_Request( 'POST', '/carmel/v1/inventory' );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( wp_json_encode( $params ) );
		$res = carmelx_invrecv_handle( $req );
		return array( (int) $res->get_status(), (array) $res->get_data() );
	}
}

if ( ! function_exists( 'carmelx_invtest_cleanup' ) ) {
	/** 診断で作ったテスト用の車両ページを完全に削除する（目印が selftest- で始まるものだけ） */
	function carmelx_invtest_cleanup() {
		$ids = get_posts( array(
			'post_type'        => 'portfolio',
			'post_status'      => 'any',
			'numberposts'      => 50,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'meta_query'       => array( array( 'key' => 'carmel_inv_ext_id', 'value' => 'selftest-', 'compare' => 'LIKE' ) ),
		) );
		$n = 0;
		foreach ( (array) $ids as $id ) {
			if ( 0 === strpos( (string) get_post_meta( $id, 'carmel_inv_ext_id', true ), 'selftest-' ) ) { wp_delete_post( (int) $id, true ); $n++; }
		}
		return $n;
	}
}

if ( ! function_exists( 'carmelx_invtest_run' ) ) {
	/**
	 * 診断を実行する。
	 * @return array{rows:array<int,array{0:?bool,1:string,2:string}>,info:array<string,string>}
	 */
	function carmelx_invtest_run( $with_publish ) {
		$rows = array();
		$add  = function ( $ok, $label, $detail = '' ) use ( &$rows ) { $rows[] = array( $ok, $label, is_scalar( $detail ) ? (string) $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE ) ); };
		$meta = function ( $id, $k ) { $v = get_post_meta( $id, $k, true ); return is_array( $v ) ? implode( ',', array_map( 'strval', $v ) ) : trim( (string) $v ); };
		$info = array();

		if ( ! function_exists( 'carmelx_invrecv_handle' ) ) {
			$add( false, '受け口が読み込まれていません', 'プラグインを有効化し直してください' );
			return array( 'rows' => $rows, 'info' => $info );
		}
		carmelx_invtest_cleanup();

		$ext   = 'selftest-' . wp_generate_password( 10, false, false );
		$stock = 'ZZTEST-' . wp_rand( 1000, 9999 );
		$shops = post_type_exists( 'shop' ) ? get_posts( array( 'post_type' => 'shop', 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => true ) ) : array();
		$imgs  = get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => 2, 'fields' => 'ids', 'suppress_filters' => true ) );
		$urls  = array();
		foreach ( (array) $imgs as $aid ) { $u = wp_get_attachment_url( $aid ); if ( $u ) { $urls[] = $u; } }
		$count_media = function () { $c = wp_count_posts( 'attachment' ); return isset( $c->inherit ) ? (int) $c->inherit : 0; };

		$base = array(
			'ext_id' => $ext, 'stock_id' => $stock, 'maker' => 'トヨタ', 'model' => 'アクア', 'grade' => 'S', 'model_code' => 'DAA-NHP10',
			'model_year' => 2018, 'mileage' => 42000, 'displacement' => '1500cc', 'mission' => 'CVT', 'drive' => '2WD',
			'color' => '', 'body_color' => 'ホワイト', 'handle' => '右', 'repair_history' => 'なし', 'fuel' => 'ハイブリッド',
			'legal_maintenance' => '定期点検整備なし', 'recycle_deposit' => 9800, 'inspection_status' => '車検整備付', 'capacity' => 5,
			'price' => 980000, 'total_payment' => 1224350, 'status' => '在庫', 'published' => false,
			'shop' => $shops ? $shops[0]->post_title : '', 'sales_point' => "診断用のテスト車両です。\n2行目のコメント。",
			'equipment' => array( 'kamera3' => true, 'smartkey' => true, 'push_start' => true, 'led_light' => false ),
			'flags' => array( 'one_owner' => true, 'kinen' => false, 'kirokubo' => true ),
			'fees' => array( 'nebiki' => 0, 'shitadori' => 0, 'jidoshazei' => 30500, 'jibaiseki' => 17650, 'juuryouzei' => 24600, 'inshi' => 0, 'touroku_daiko' => 33000, 'shako' => 15000, 'nousha' => 10000, 'kensa_daiko' => 0, 'seibi' => 0, 'hoshou' => 0, 'hanbai_tesuu' => 0, 'auction' => 0, 'shouhizei' => 103800 ),
			'images' => $urls,
		);
		$media0 = $count_media();

		/* ---------- 1) 作成（下書き） ---------- */
		list( $http, $r ) = carmelx_invtest_call( $base );
		$id = isset( $r['wp_post_id'] ) ? (int) $r['wp_post_id'] : 0;
		$add( 200 === $http && ! empty( $r['ok'] ) && ! empty( $r['created'] ) && $id > 0, 'テスト用の車両ページを作成', 200 === $http ? 'ページ番号 ' . $id : $r );
		if ( ! $id ) { return array( 'rows' => $rows, 'info' => $info ); }
		$add( 'portfolio' === get_post_type( $id ) && 'draft' === get_post_status( $id ), '掲載しない設定なら下書きのまま（公開されない）', get_post_status( $id ) );
		$want_title = '信用回復ローン トヨタ アクア S 2018年 ホワイト 42,000km ' . $stock;
		$add( get_the_title( $id ) === $want_title, 'タイトルが既存車両と同じ並び', get_the_title( $id ) );

		/* ---------- 2) 基本情報 ---------- */
		$expect = array(
			'kanri_bango' => $stock, 'marker' => 'トヨタ', 'type' => 'アクア S', 'year' => '2018', 'mileage' => '42000', 'displacement' => '1500cc',
			'mission' => 'CVT', 'kudou' => '2WD', 'color' => 'ホワイト', 'inspection' => '車検整備付', 'handle' => '右ハンドル',
			'repair_history' => 'なし', 'fuel_type' => 'ハイブリッド', 'seibi' => 'なし', 'recicle' => '9,800円', 'cx_teiin' => '5',
		);
		$bad = array();
		foreach ( $expect as $k => $v ) { if ( $meta( $id, $k ) !== $v ) { $bad[] = $k . '=' . $meta( $id, $k ) . '（期待 ' . $v . '）'; } }
		$add( ! $bad, '基本情報（メーカー・車種・年式・走行距離・色・ハンドル・修復歴など16項目）', $bad ? implode( ' / ', $bad ) : '16項目とも一致' );
		if ( $shops ) { $add( $meta( $id, 'shop' ) === $shops[0]->post_name, '販売店を店舗名から割り当て', $shops[0]->post_title . ' → ' . $meta( $id, 'shop' ) ); }
		$add( false !== strpos( (string) get_post_meta( $id, 'comment', true ), "\n" ) && 0 === strpos( (string) get_post_meta( $id, 'comment', true ), '診断用' ), 'セールスポイントが「担当者より」欄に改行つきで入る', mb_substr( (string) get_post_meta( $id, 'comment', true ), 0, 20 ) );

		/* ---------- 3) 金額・月々 ---------- */
		$monthly = number_format( carmelx_invrecv_monthly( 1224350 - (int) preg_replace( '/[^0-9]/', '', (string) get_post_meta( $id, 'est_atamakin', true ) ) ) );
		$add( $meta( $id, 'est_honntai' ) === '980000' && $meta( $id, 'price' ) === '980000' && $meta( $id, 'est_total' ) === '1224350', '本体価格・支払総額', '本体 ' . $meta( $id, 'est_honntai' ) . ' / 総額 ' . $meta( $id, 'est_total' ) );
		$add( $meta( $id, 'total' ) === $monthly && $meta( $id, 'total' ) !== '1224350', '「月々」欄は月々の概算（支払総額ではない）', '月々 ' . $meta( $id, 'total' ) . '円' );

		/* ---------- 4) 装備・安心フラグ ---------- */
		$checked = function ( $name ) use ( $id ) {
			if ( function_exists( 'carmel_equip_checked' ) ) { return (bool) carmel_equip_checked( $name, $id ); }
			$v = get_post_meta( $id, $name, true ); if ( is_array( $v ) ) { $v = array_filter( $v ); }
			return ! empty( $v ) && '0' !== $v;
		};
		$add( $checked( 'kamera3' ) && $checked( 'smartkey' ) && $checked( 'push_start' ) && ! $checked( 'led_light' ), '装備：付けた3項目がチェック済み、付けていない1項目は空', 'バックカメラ=' . wp_json_encode( get_post_meta( $id, 'kamera3', true ), JSON_UNESCAPED_UNICODE ) );
		if ( function_exists( 'carmel_render_equipment' ) ) {
			$html = (string) carmel_render_equipment( $id );
			$add( false !== strpos( $html, 'バックカメラ' ) && false !== strpos( $html, 'スマートキー' ), '車両ページの装備一覧に表示される', '' );
		}
		$add( $meta( $id, 'cx_one_owner' ) === '1' && $meta( $id, 'cx_kirokubo' ) === '1' && $meta( $id, 'cx_kinen' ) === '', '安心フラグ（ワンオーナー・記録簿）', '' );

		/* ---------- 5) 諸費用 ---------- */
		$add( $meta( $id, 'est_jidoshazei' ) === '30500' && $meta( $id, 'est_touroku_daiko' ) === '33000' && $meta( $id, 'est_shouhizei' ) === '103800' && $meta( $id, 'est_nebiki' ) === '0', '諸費用の内訳が見積もりの欄に入る', '自動車税 ' . $meta( $id, 'est_jidoshazei' ) . ' / 消費税 ' . $meta( $id, 'est_shouhizei' ) );
		$add( $meta( $id, 'keihi' ) === '244,350円', '諸経費合計', $meta( $id, 'keihi' ) );
		if ( function_exists( 'carmelx_breakdown_render' ) ) { $info['内訳表示'] = '車両ページの「お支払総額の内訳」モジュールあり'; }

		/* ---------- 6) 写真（このサイトにある画像を使う＝取り込み直さない） ---------- */
		if ( $urls ) {
			$add( (int) $r['images_done'] === count( $urls ) && 0 === (int) $r['images_failed'], '写真 ' . count( $urls ) . '枚を車両ページに設定', '設定 ' . (int) $r['images_done'] . ' / 失敗 ' . (int) $r['images_failed'] );
			$add( $count_media() === $media0, '既にある画像は取り込み直さない（メディアが増えない）', 'メディア ' . $media0 . ' → ' . $count_media() );
			$add( (int) get_post_thumbnail_id( $id ) === (int) $imgs[0], '1枚目がアイキャッチ', '' );
			$add( $meta( $id, 'vehicle_image_ids' ) === implode( ',', array_map( 'intval', $imgs ) ), '写真の一覧（全画像）', $meta( $id, 'vehicle_image_ids' ) );
			$info['スライダー用の欄'] = 'wpex_post_gallery_ids=' . $meta( $id, 'wpex_post_gallery_ids' ) . '（サイト側の仕組みが決めた値）';
		} else {
			$add( null, '写真：メディアに画像が無いため省略', '' );
		}

		/* ---------- 7) 同じ内容をもう一度送る（二重にならない） ---------- */
		list( $http, $r2 ) = carmelx_invtest_call( $base );
		$add( 200 === $http && empty( $r2['created'] ) && (int) $r2['wp_post_id'] === $id, '同じ車をもう一度送っても同じページ（二重にならない）', '' );
		$add( $count_media() === $media0, '再送でも画像が増えない', '' );
		$bad = array();
		foreach ( $expect as $k => $v ) { if ( $meta( $id, $k ) !== $v ) { $bad[] = $k; } }
		$add( ! $bad && $meta( $id, 'total' ) === $monthly, '保存時の仕組み（タイトル保護・データ保護など）を通っても値が消えない', $bad ? implode( ',', $bad ) : '' );

		/* ---------- 8) 更新 ---------- */
		$upd = $base; $upd['mileage'] = 43000; $upd['equipment']['kamera3'] = false; $upd['fees']['jidoshazei'] = 0; $upd['fees']['nebiki'] = 20000;
		list( $http, $r3 ) = carmelx_invtest_call( $upd );
		$add( get_the_title( $id ) === str_replace( '42,000km', '43,000km', $want_title ) && $meta( $id, 'mileage' ) === '43000', '走行距離を変えるとタイトルも追従', get_the_title( $id ) );
		$add( ! $checked( 'kamera3' ) && $checked( 'smartkey' ), '装備を外すとチェックが外れる（他は残る）', '' );
		$add( $meta( $id, 'est_jidoshazei' ) === '0' && $meta( $id, 'est_nebiki' ) === '20000', '諸費用を消すと0円に、値引きを入れると反映', '' );

		/* ---------- 9) 管理IDの重なり（別の在庫が同じ管理ID） ---------- */
		$before = wp_count_posts( 'portfolio' );
		$other  = $base; $other['ext_id'] = $ext . '-b';
		list( $http, $r4 ) = carmelx_invtest_call( $other );
		$after = wp_count_posts( 'portfolio' );
		$add( 409 === $http && isset( $r4['code'] ) && 'stock_id_taken' === $r4['code'] && (array) $before == (array) $after, '同じ管理IDの別の在庫は受け付けない（上書きしない・増えない）', isset( $r4['code'] ) ? $r4['code'] : $http );

		/* ---------- 10) 公開 → 売約済で非公開（選んだ時だけ） ---------- */
		if ( $with_publish ) {
			$pub = $upd; $pub['published'] = true;
			list( $http, $r5 ) = carmelx_invtest_call( $pub );
			$add( 'publish' === get_post_status( $id ) && isset( $r5['status'] ) && 'publish' === $r5['status'], '在庫＋掲載する → 公開', get_post_status( $id ) );
			$pub['status'] = '売約済';
			list( $http, $r6 ) = carmelx_invtest_call( $pub );
			$add( 'draft' === get_post_status( $id ), '売約済 → 非公開（下書き）', get_post_status( $id ) );
			$pub['status'] = '商談中';
			carmelx_invtest_call( $pub );
			$add( 'publish' === get_post_status( $id ), '商談中 → 公開', get_post_status( $id ) );
		} else {
			$add( null, '公開の動き：選ばれていないため省略（テスト車両は一度も公開されていません）', '' );
		}

		/* ---------- 11) 取り下げ・取り込み用の一覧・目印 ---------- */
		list( $http, $r7 ) = carmelx_invtest_call( array( 'ext_id' => $ext, 'action' => 'unpublish' ) );
		$add( 200 === $http && 'draft' === get_post_status( $id ), '取り下げ（下書きに戻す）', get_post_status( $id ) );
		if ( function_exists( 'carmelx_invrecv_export_item' ) ) {
			$ex = carmelx_invrecv_export_item( $id );
			$eq = (array) $ex['equipment'];
			$add( $ex['stock_id'] === $stock && 2018 === $ex['year'] && 43000 === $ex['mileage'] && 1500 === $ex['displacement'] && 980000 === $ex['price'] && 1224350 === $ex['total_payment'] && ! empty( $eq['smartkey'] ) && empty( $eq['kamera3'] ) && ! empty( $eq['one_owner'] ), '取り込み用の一覧：同じ内容が読み出せる（往復しても変わらない）', '年式 ' . $ex['year'] . ' / 走行 ' . $ex['mileage'] . ' / 装備 ' . implode( ',', array_keys( $eq ) ) );
			$fees = (array) $ex['fees'];
			$add( isset( $fees['nebiki'] ) && 20000 === $fees['nebiki'] && ! isset( $fees['jidoshazei'] ) && count( (array) $ex['images'] ) === count( $urls ), '取り込み用の一覧：諸費用・写真', '写真 ' . count( (array) $ex['images'] ) . '枚' );
		}
		if ( function_exists( 'carmelx_invrecv_link' ) ) {
			$lq = new WP_REST_Request( 'POST', '/carmel/v1/inventory/link' );
			$lq->set_header( 'content-type', 'application/json' );
			$lq->set_body( wp_json_encode( array( 'links' => array( array( 'wp_post_id' => $id, 'ext_id' => $ext ), array( 'wp_post_id' => $id, 'ext_id' => 'selftest-other' ) ) ) ) );
			$ld = (array) carmelx_invrecv_link( $lq )->get_data();
			$add( 1 === (int) $ld['already'] && 0 === (int) $ld['linked'] && 1 === count( (array) $ld['skipped'] ), '目印：付いているページの目印は付け替えない', '' );
		}

		/* ---------- 12) ACFの欄の割り当て状況（参考） ---------- */
		if ( function_exists( 'carmelx_invrecv_field_key' ) && function_exists( 'update_field' ) ) {
			$names = array_merge( array_keys( $expect ), array( 'shop', 'tel', 'comment', 'total', 'price', 'est_honntai', 'est_total', 'est_jidoshazei', 'est_shouhizei', 'keihi', 'kamera3', 'smartkey', 'push_start' ) );
			$as_acf = array(); $as_meta = array();
			foreach ( $names as $n ) { if ( '' !== carmelx_invrecv_field_key( $id, $n ) ) { $as_acf[] = $n; } else { $as_meta[] = $n; } }
			$info['ACFの欄として保存'] = count( $as_acf ) . '項目：' . implode( ', ', $as_acf );
			$info['メタとして保存（ACFの欄が見つからない）'] = $as_meta ? count( $as_meta ) . '項目：' . implode( ', ', $as_meta ) : 'なし';
		} else {
			$info['ACF'] = function_exists( 'update_field' ) ? '有効' : '無効（すべてメタとして保存）';
		}

		/* ---------- 13) 後片付け ---------- */
		wp_delete_post( $id, true );
		$add( null === get_post( $id ), 'テスト用の車両ページを削除（後片付け）', '' );
		$add( $count_media() === $media0, '既存の画像はそのまま', 'メディア ' . $count_media() );

		/* ---------- 14) 既存の車両の読み出し（読むだけ） ---------- */
		if ( function_exists( 'carmelx_invrecv_export_item' ) ) {
			$latest = get_posts( array( 'post_type' => 'portfolio', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'suppress_filters' => true ) );
			if ( $latest ) {
				$e = carmelx_invrecv_export_item( (int) $latest[0] );
				$info['取り込まれる内容の例（最新の掲載車両・読むだけ）'] = $e['title'] . ' ／ 管理ID ' . $e['stock_id'] . ' ／ ' . $e['maker'] . ' ' . $e['type'] . ' ／ 年式 ' . $e['year'] . ' ／ 走行 ' . $e['mileage'] . ' ／ 本体 ' . $e['price'] . ' ／ 総額 ' . $e['total_payment'] . ' ／ 販売店 ' . $e['shop_name'] . ' ／ 装備 ' . count( (array) $e['equipment'] ) . '項目 ／ 諸費用 ' . count( (array) $e['fees'] ) . '欄 ／ 写真 ' . count( (array) $e['images'] ) . '枚';
			}
			$c = wp_count_posts( 'portfolio' );
			$info['掲載中の車両'] = ( isset( $c->publish ) ? (int) $c->publish : 0 ) . '台';
		}
		return array( 'rows' => $rows, 'info' => $info );
	}
}

if ( ! function_exists( 'carmelx_invtest_render' ) ) {
	function carmelx_invtest_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$result = null; $fatal = '';
		if ( isset( $_POST['carmelx_invtest_run'] ) && check_admin_referer( 'carmelx_invtest' ) ) {
			try { $result = carmelx_invtest_run( ! empty( $_POST['with_publish'] ) ); }
			catch ( \Throwable $e ) { $fatal = $e->getMessage() . '（' . basename( $e->getFile() ) . ':' . $e->getLine() . '）'; carmelx_invtest_cleanup(); }
		}
		$ver = defined( 'CARMEL_SYS_VERSION' ) ? CARMEL_SYS_VERSION : ( defined( 'CARMEL_INV_BRIDGE_VERSION' ) ? CARMEL_INV_BRIDGE_VERSION : '' );
		echo '<div class="wrap"><h1>在庫連携 診断</h1>';
		echo '<p>在庫連携の受け口を、このサイトの中で実際に動かして確かめます。テスト用の車両ページを1台作り、確認が終わったら完全に削除します。<strong>既存の車両ページと画像は変更しません。</strong></p>';
		echo '<form method="post">'; wp_nonce_field( 'carmelx_invtest' );
		echo '<p><label><input type="checkbox" name="with_publish" value="1"> 公開の動きも試す（テスト用の車両ページが数秒だけ公開されます。コピーサイト向け）</label></p>';
		echo '<p><button type="submit" name="carmelx_invtest_run" value="1" class="button button-primary">診断を実行</button> <span style="color:#666">バージョン ' . esc_html( $ver ) . '</span></p></form>';

		if ( '' !== $fatal ) {
			echo '<div class="notice notice-error"><p>診断の途中でエラーが起きました：' . esc_html( $fatal ) . '</p></div>';
		}
		if ( is_array( $result ) ) {
			$ok = 0; $ng = 0; $text = '在庫連携 診断 v' . $ver . ' / ' . home_url() . "\n";
			echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th style="width:60px">結果</th><th>確認したこと</th><th>くわしく</th></tr></thead><tbody>';
			foreach ( $result['rows'] as $row ) {
				list( $s, $label, $detail ) = $row;
				if ( true === $s ) { $ok++; $mark = '<span style="color:#0a7d33;font-weight:700">OK</span>'; $t = 'OK'; }
				elseif ( false === $s ) { $ng++; $mark = '<span style="color:#c00;font-weight:700">NG</span>'; $t = 'NG'; }
				else { $mark = '<span style="color:#666">—</span>'; $t = '--'; }
				echo '<tr><td>' . $mark . '</td><td>' . esc_html( $label ) . '</td><td style="color:#555">' . esc_html( $detail ) . '</td></tr>';
				$text .= $t . ' ' . $label . ( '' !== $detail ? ' | ' . $detail : '' ) . "\n";
			}
			echo '</tbody></table>';
			$sum = 0 === $ng ? 'すべて OK（' . $ok . '項目）' : 'NG が ' . $ng . '件あります（OK ' . $ok . '項目）';
			echo '<h2 style="color:' . ( 0 === $ng ? '#0a7d33' : '#c00' ) . '">' . esc_html( $sum ) . '</h2>';
			$text .= $sum . "\n";
			if ( ! empty( $result['info'] ) ) {
				echo '<h3>参考</h3><ul style="list-style:disc;margin-left:20px;max-width:1000px">';
				foreach ( $result['info'] as $k => $v ) { echo '<li><strong>' . esc_html( $k ) . '</strong>：' . esc_html( $v ) . '</li>'; $text .= '[' . $k . '] ' . $v . "\n"; }
				echo '</ul>';
			}
			echo '<h3>この結果を送る</h3><p>下の文字をすべてコピーして、そのまま貼り付けて送ってください。</p>';
			echo '<textarea readonly rows="12" style="width:100%;max-width:1000px;font-family:monospace" onclick="this.select()">' . esc_textarea( $text ) . '</textarea>';
		}
		echo '</div>';
	}
}
