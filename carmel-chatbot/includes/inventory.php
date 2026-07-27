<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 在庫連携（carmel-stock-ui の portfolio 投稿を都度読み取り）。
 * 公開中(publish)＆販売中の在庫だけを対象。新着は自動で提案に入り、売却・非公開は自動で外れる。
 *
 * 実データの項目名（在庫診断で確認）:
 *   marker=メーカー / type=車種 / year=年式(平成表記等) / mileage=走行 /
 *   total=月々のお支払い（例 35,000）/ price=本体価格(あれば) / stauts=販売状況(販売中 等)
 *   ※車の説明は post_title に「年式 メーカー 車種 走行」がまとまって入っている。
 */

/** 数字だけ取り出す（"35,000円"→35000）。全角数字も半角化。 */
function carmel_cb_num( $v ) {
	$v = (string) $v;
	$v = mb_convert_kana( $v, 'n' ); // 全角数字→半角
	return (int) preg_replace( '/[^0-9]/', '', $v );
}

/** 車・在庫・予算に関する話題か。 */
function carmel_cb_is_car_intent( $text ) {
	$text = (string) $text;
	if ( $text === '' ) { return false; }
	return (bool) preg_match(
		'/(車|在庫|予算|万円|円|月々|人乗|家族|ファミリー|軽|ミニバン|SUV|セダン|ワゴン|コンパクト|ハイブリッド|燃費|おすすめ|車種|探|乗りたい|買い|中古|台)/u',
		$text
	);
}

/** 1投稿を在庫アイテム配列にする（販売終了なら null）。 */
function carmel_cb_stock_item( $p ) {
	$st = (string) get_post_meta( $p->ID, 'stauts', true );
	if ( $st === '' ) { $st = (string) get_post_meta( $p->ID, 'status', true ); }
	if ( $st !== '' && preg_match( '/売約|商談|終了|SOLD/iu', $st ) ) { return null; }

	return array(
		'id'      => $p->ID,
		'title'   => get_the_title( $p ),
		'maker'   => (string) get_post_meta( $p->ID, 'marker', true ),
		'type'    => (string) get_post_meta( $p->ID, 'type', true ),
		'year'    => (string) get_post_meta( $p->ID, 'year', true ),
		'mileage' => carmel_cb_num( get_post_meta( $p->ID, 'mileage', true ) ),
		'monthly' => carmel_cb_num( get_post_meta( $p->ID, 'total', true ) ),  // 月々
		'price'   => carmel_cb_num( get_post_meta( $p->ID, 'price', true ) ),  // 本体(あれば)
		'url'     => get_permalink( $p->ID ),
		'thumb'   => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: '',
	);
}

/**
 * 在庫を取得。お客様の発話($query)で在庫全体を検索（車種名など）し、
 * さらに新着で補完する。これにより120台すべてから該当車を見つけられる。
 */
function carmel_cb_fetch_stock( $query = '', $limit = 20 ) {
	$items = array();
	$seen  = array();
	$add   = function ( $posts ) use ( &$items, &$seen, $limit ) {
		foreach ( $posts as $p ) {
			if ( count( $items ) >= $limit || isset( $seen[ $p->ID ] ) ) { continue; }
			$it = carmel_cb_stock_item( $p );
			if ( $it ) { $seen[ $p->ID ] = 1; $items[] = $it; }
		}
	};

	// 1) お客様の発話で在庫全体をキーワード検索（車種名・メーカー名などに一致）
	$query = trim( (string) $query );
	if ( $query !== '' ) {
		$sq = new WP_Query( array(
			'post_type'      => 'portfolio',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			's'              => $query,
			'no_found_rows'  => true,
		) );
		$add( $sq->posts );
		wp_reset_postdata();
	}

	// 2) 新着で補完
	if ( count( $items ) < $limit ) {
		$rq = new WP_Query( array(
			'post_type'      => 'portfolio',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		) );
		$add( $rq->posts );
		wp_reset_postdata();
	}

	return $items;
}

/**
 * お客様の発話に「在庫のメーカー名・車種名・タイトル語」が含まれるかで在庫を照合する。
 * 例：「プリウスありますか？」→ タイトル/車種に『プリウス』を含む在庫がヒット。
 * WP標準の全文検索（発話まるごと）だと日本語で一致しにくいため、語ベースで判定する。
 */
function carmel_cb_match_stock_by_text( $text, $limit = 20 ) {
	$text = (string) $text;
	if ( trim( $text ) === '' ) { return array(); }
	$hay = mb_convert_kana( $text, 'asKV' ); // 半角/全角のゆれを吸収

	$q = new WP_Query( array(
		'post_type'              => 'portfolio',
		'post_status'            => 'publish',
		'posts_per_page'         => 150,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => false,
	) );

	$matched = array();
	foreach ( $q->posts as $p ) {
		$st = (string) get_post_meta( $p->ID, 'stauts', true );
		if ( $st === '' ) { $st = (string) get_post_meta( $p->ID, 'status', true ); }
		if ( $st !== '' && preg_match( '/売約|商談|終了|SOLD/iu', $st ) ) { continue; }

		$maker = (string) get_post_meta( $p->ID, 'marker', true );
		$type  = (string) get_post_meta( $p->ID, 'type', true );
		$title = get_the_title( $p );

		$tokens = array();
		if ( mb_strlen( $maker ) >= 2 ) { $tokens[] = $maker; }
		if ( mb_strlen( $type ) >= 2 )  { $tokens[] = $type; }
		foreach ( preg_split( '/[\s　\/／,，、。・|｜]+/u', $title ) as $w ) {
			$w = trim( $w );
			if ( mb_strlen( $w ) >= 2 && ! is_numeric( $w ) ) { $tokens[] = $w; }
		}

		$hit = false;
		foreach ( $tokens as $tk ) {
			$tk = mb_convert_kana( trim( $tk ), 'asKV' );
			if ( $tk !== '' && mb_strpos( $hay, $tk ) !== false ) { $hit = true; break; }
		}
		if ( $hit ) {
			$it = carmel_cb_stock_item( $p );
			if ( $it ) { $matched[] = $it; if ( count( $matched ) >= $limit ) { break; } }
		}
	}
	wp_reset_postdata();
	return $matched;
}

/** システムプロンプトに渡す在庫リスト（IDで参照させる）。 */
function carmel_cb_inventory_block( $items ) {
	if ( empty( $items ) ) {
		return "\n\n【現在の在庫】現在ご紹介できる在庫データがありません。一般的なご案内とLINE/電話・在庫ページへ。";
	}
	$b = "\n\n【現在の在庫（必ずこの中からだけ提案する。提案した車は car_ids にIDを入れる）】\n";
	foreach ( $items as $it ) {
		$money = $it['monthly'] ? ( '月々' . number_format( $it['monthly'] ) . '円' ) : ( $it['price'] ? ( '本体' . number_format( $it['price'] ) . '円' ) : '価格応談' );
		$mile  = $it['mileage'] ? ( ' ' . number_format( $it['mileage'] ) . 'km' ) : '';
		$b    .= "#{$it['id']} {$it['title']}{$mile} ／ {$money}\n";
	}
	return $b;
}

/**
 * AIが car_ids を入れ忘れたときの保険：お客様の発話から予算を読み、在庫から自動で数台選ぶ。
 * 月々の予算（「月2万」等）があればそれ以下の車を、価格予算（「100万」等）があれば本体価格で絞る。
 */
function carmel_cb_auto_pick( $items, $query, $n = 3 ) {
	if ( empty( $items ) ) { return array(); }
	$q = mb_convert_kana( (string) $query, 'n' );

	$monthly_max = 0; $price_max = 0;
	if ( preg_match( '/月々?\s*([0-9]+)\s*万/u', $q, $m ) ) { $monthly_max = ( (int) $m[1] ) * 10000; }
	elseif ( preg_match( '/月々?\s*([0-9]{4,7})\s*円?/u', $q, $m ) ) { $monthly_max = (int) $m[1]; }
	if ( preg_match( '/([0-9]+)\s*万円?(?!.*月)/u', $q, $m ) ) { $price_max = ( (int) $m[1] ) * 10000; }

	$pool = $items;
	if ( $monthly_max > 0 ) {
		$f = array_values( array_filter( $items, function ( $it ) use ( $monthly_max ) {
			return $it['monthly'] > 0 && $it['monthly'] <= $monthly_max * 1.15;
		} ) );
		usort( $f, function ( $a, $b ) { return $b['monthly'] - $a['monthly']; } ); // 予算内で高めから
		if ( $f ) { $pool = $f; }
	} elseif ( $price_max > 0 ) {
		$f = array_values( array_filter( $items, function ( $it ) use ( $price_max ) {
			return $it['price'] > 0 && $it['price'] <= $price_max * 1.15;
		} ) );
		usort( $f, function ( $a, $b ) { return $b['price'] - $a['price']; } );
		if ( $f ) { $pool = $f; }
	}

	$ids = array();
	foreach ( array_slice( $pool, 0, $n ) as $it ) { $ids[] = $it['id']; }
	return $ids;
}

/** AIが選んだ car_ids を、実在庫のカード情報に変換。 */
function carmel_cb_cards_from_ids( $ids, $items ) {
	$by = array();
	foreach ( $items as $it ) { $by[ (int) $it['id'] ] = $it; }

	$cards = array();
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		if ( ! isset( $by[ $id ] ) ) { continue; }
		$it = $by[ $id ];

		if ( $it['monthly'] ) {
			$priceLabel = '月々 ' . number_format( $it['monthly'] ) . '円〜' . ( $it['price'] ? '（本体 ' . number_format( $it['price'] ) . '円）' : '' );
		} elseif ( $it['price'] ) {
			$priceLabel = '本体 ' . number_format( $it['price'] ) . '円';
		} else {
			$priceLabel = '価格は応談';
		}

		$cards[] = array(
			'title'   => $it['title'],
			'price'   => $priceLabel,   // chat.js はこの文字列をそのまま表示
			'monthly' => '',            // タイトルに情報があるため個別欄は未使用
			'year'    => '',
			'mileage' => $it['mileage'] ? number_format( $it['mileage'] ) . 'km' : '',
			'url'     => $it['url'],
			'thumb'   => $it['thumb'],
		);
	}
	return $cards;
}
