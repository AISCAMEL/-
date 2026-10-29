<?php
/**
 * APPREX LABO：公式LINEの中で開くミニアプリ（LIFF）。
 *
 * インスタ・SNS・LPから LINE に来た人を、LINEの中で「特典 → 学ぶ → 相談の予約」へ進める。
 *
 * - 入口：https://liff.line.me/{LABOのLIFF ID}?src=insta など。開いた人の流入元を、LINE友だちごとに記録する。
 * - つくる（特典）：業種と店名から、AIがアプリの画面案（キャッチ・色・メニュー）を作る。
 * - 学ぶ：管理画面「LABO → 記事・動画」で書いた記事と動画。見た記録を残す。
 * - 予約：サイトのミーティング予約（inc/meeting-booking.php）と代理店説明会（inc/seminar.php）。
 * - マイページ：進み具合・作った画面・予約。ご契約中の方は会員マイページへ。
 * - 管理画面：入口URLとQRコードの作成、流入元ごとの成果（友だち → 特典 → 学ぶ → 予約）。
 *
 * LINE Developers：APPREX（LINEログイン）チャネルに LIFF を追加（エンドポイント https://…/labo、Full、openid・profile、友だち追加 Aggressive）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** LABOの LIFF ID。 */
function apprex_labo_liff_id() {
	return trim( (string) get_option( 'apprex_labo_liff_id', '' ) );
}

/** LABOのエンドポイントURL（LINE Developers に登録する値）。 */
function apprex_labo_endpoint_url() {
	return home_url( '/labo' );
}

/** 入口URL（流入元つき）。LIFF未設定ならサイトのURL。 */
function apprex_labo_url( $src = '' ) {
	$id   = apprex_labo_liff_id();
	$base = '' !== $id ? 'https://liff.line.me/' . rawurlencode( $id ) : apprex_labo_endpoint_url();
	return '' !== $src ? add_query_arg( 'src', rawurlencode( $src ), $base ) : $base;
}

/* =========================================================================
 * 記事・動画（CPT）と、カテゴリ
 * ====================================================================== */
add_action( 'init', function () {
	register_post_type(
		'apprex_labo_post',
		array(
			'labels'       => array(
				'name'          => 'LABO',
				'singular_name' => 'LABOの記事・動画',
				'menu_name'     => 'LABO',
				'all_items'     => '記事・動画',
				'add_new'       => '記事・動画を追加',
				'add_new_item'  => '記事・動画を追加',
				'edit_item'     => '記事・動画を編集',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-smartphone',
			'menu_position' => 28,
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		)
	);
	register_taxonomy(
		'apprex_labo_cat',
		'apprex_labo_post',
		array(
			'labels'       => array( 'name' => 'カテゴリ', 'singular_name' => 'カテゴリ' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'hierarchical' => true,
			'show_admin_column' => true,
		)
	);
	add_rewrite_rule( '^labo/?$', 'index.php?apprex_labo=1', 'top' );
	if ( '1' !== get_option( 'apprex_labo_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_labo_rw', '1' );
		// はじめのカテゴリ。
		foreach ( array( 'アプリ集客の基本', '業種別の事例', '費用と補助金', '作り方の流れ' ) as $c ) {
			if ( ! term_exists( $c, 'apprex_labo_cat' ) ) {
				wp_insert_term( $c, 'apprex_labo_cat' );
			}
		}
	}
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'apprex_labo';
	return $v;
} );
add_filter( 'redirect_canonical', function ( $r ) {
	return get_query_var( 'apprex_labo' ) ? false : $r;
} );

/* 記事の編集画面：動画URLと、ボタン（次の一歩）。 */
add_action( 'add_meta_boxes_apprex_labo_post', function () {
	add_meta_box(
		'apprex_labo_box',
		'動画・次の一歩',
		function ( $post ) {
			wp_nonce_field( 'apprex_labo_box', 'apprex_labo_nonce' );
			$v   = (string) get_post_meta( $post->ID, 'apprex_labo_video', true );
			$cta = (string) get_post_meta( $post->ID, 'apprex_labo_cta', true );
			echo '<p><label>動画のURL（YouTube）<br><input type="url" name="apprex_labo_video" value="' . esc_attr( $v ) . '" class="large-text" placeholder="https://www.youtube.com/watch?v=..."></label></p>';
			echo '<p><label>記事の最後のボタン<br><select name="apprex_labo_cta">';
			foreach ( array( 'make' => 'AIでアプリ画面を作る（特典）', 'book' => '無料相談を予約する', 'seminar' => '代理店説明会を予約する', 'none' => 'なし' ) as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $cta ? $cta : 'make', $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select></label></p><p class="description">一覧の画像は「アイキャッチ画像」、短い説明は「抜粋」に入れます。</p>';
		},
		'apprex_labo_post',
		'side'
	);
} );
add_action( 'save_post_apprex_labo_post', function ( $id ) {
	if ( ! isset( $_POST['apprex_labo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_labo_nonce'] ), 'apprex_labo_box' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	update_post_meta( $id, 'apprex_labo_video', isset( $_POST['apprex_labo_video'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_labo_video'] ) ) : '' );
	update_post_meta( $id, 'apprex_labo_cta', isset( $_POST['apprex_labo_cta'] ) ? sanitize_key( $_POST['apprex_labo_cta'] ) : 'make' );
} );

/** YouTube の URL → 埋め込みURL（それ以外は空）。 */
function apprex_labo_embed( $url ) {
	if ( preg_match( '#(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#', (string) $url, $m ) ) {
		return 'https://www.youtube-nocookie.com/embed/' . $m[1];
	}
	return '';
}

/** 記事の一覧・詳細（画面に渡す形）。 */
function apprex_labo_post_out( $p, $full = false ) {
	$cats = wp_get_post_terms( $p->ID, 'apprex_labo_cat', array( 'fields' => 'names' ) );
	$out  = array(
		'id'    => $p->ID,
		'title' => get_the_title( $p ),
		'cat'   => $cats && ! is_wp_error( $cats ) ? $cats[0] : '',
		'thumb' => (string) get_the_post_thumbnail_url( $p, 'medium_large' ),
		'video' => '' !== apprex_labo_embed( get_post_meta( $p->ID, 'apprex_labo_video', true ) ),
		'desc'  => has_excerpt( $p ) ? get_the_excerpt( $p ) : wp_trim_words( wp_strip_all_tags( $p->post_content ), 40, '…' ),
	);
	if ( $full ) {
		$out['html']  = apply_filters( 'the_content', $p->post_content );
		$out['embed'] = apprex_labo_embed( get_post_meta( $p->ID, 'apprex_labo_video', true ) );
		$out['cta']   = (string) get_post_meta( $p->ID, 'apprex_labo_cta', true );
	}
	return $out;
}

/* =========================================================================
 * 友だちの記録（流入元・特典・見た記事・予約）
 * ====================================================================== */

/** IDトークンを確かめて LINE のユーザー情報を返す（10分覚えておく）。 */
function apprex_labo_who( $idt ) {
	$idt = (string) $idt;
	if ( '' === $idt ) {
		return null;
	}
	$key = 'apprex_labo_idt_' . md5( $idt );
	$c   = get_transient( $key );
	if ( is_array( $c ) ) {
		return $c;
	}
	$cid = function_exists( 'apprex_seminar_channel_id' ) ? apprex_seminar_channel_id() : '';
	if ( '' === $cid && preg_match( '/^(\d+)-/', apprex_labo_liff_id(), $m ) ) {
		$cid = $m[1];
	}
	if ( '' === $cid ) {
		return null;
	}
	$res = wp_remote_post( 'https://api.line.me/oauth2/v2.1/verify', array( 'timeout' => 15, 'body' => array( 'id_token' => $idt, 'client_id' => $cid ) ) );
	if ( is_wp_error( $res ) ) {
		return null;
	}
	$b = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $b['sub'] ) ) {
		return null;
	}
	$c = array(
		'uid'  => sanitize_text_field( $b['sub'] ),
		'name' => isset( $b['name'] ) ? sanitize_text_field( $b['name'] ) : '',
		'pic'  => isset( $b['picture'] ) ? esc_url_raw( $b['picture'] ) : '',
	);
	set_transient( $key, $c, 10 * MINUTE_IN_SECONDS );
	return $c;
}

/** 友だちの記録（LINE友だち）を取り出す。無ければ作る。 */
function apprex_labo_friend( $uid ) {
	return function_exists( 'apprex_line_friend_get' ) ? apprex_line_friend_get( $uid, true ) : 0;
}

/** できごとを記録する（open / make / view / book / seminar）。 */
function apprex_labo_log( $uid, $type, $data = '' ) {
	$fid = apprex_labo_friend( $uid );
	if ( ! $fid ) {
		return;
	}
	$ev   = get_post_meta( $fid, 'apprex_labo_events', true );
	$ev   = is_array( $ev ) ? $ev : array();
	$ev[] = array( $type, time(), mb_substr( (string) $data, 0, 80 ) );
	update_post_meta( $fid, 'apprex_labo_events', array_slice( $ev, -200 ) );
	if ( 'make' === $type ) {
		update_post_meta( $fid, 'apprex_labo_made', time() );
	} elseif ( 'view' === $type ) {
		update_post_meta( $fid, 'apprex_labo_views', (int) get_post_meta( $fid, 'apprex_labo_views', true ) + 1 );
	} elseif ( 'book' === $type || 'seminar' === $type ) {
		update_post_meta( $fid, 'apprex_labo_booked', time() );
	}
}

/* サイトの予約・説明会の予約がLINEから入ったら、LABOの記録にも残す。 */
add_action( 'apprex_inquiry_submitted', function ( $id, $type ) {
	$uid = (string) get_post_meta( $id, 'apprex_line_uid', true );
	if ( '' !== $uid && in_array( $type, array( 'meeting', 'seminar' ), true ) ) {
		apprex_labo_log( $uid, 'meeting' === $type ? 'book' : 'seminar' );
	}
}, 20, 2 );
add_action( 'updated_post_meta', function ( $mid, $post_id, $key, $value ) {
	// ミーティング予約は保存のあとに LINE の userId が付くので、そのときに記録する。
	if ( 'apprex_line_uid' === $key && 'apprex_inquiry' === get_post_type( $post_id ) && 'meeting' === get_post_meta( $post_id, 'apprex_type', true ) ) {
		apprex_labo_log( (string) $value, 'book' );
	}
}, 10, 4 );
add_action( 'added_post_meta', function ( $mid, $post_id, $key, $value ) {
	if ( 'apprex_line_uid' === $key && 'apprex_inquiry' === get_post_type( $post_id ) && 'meeting' === get_post_meta( $post_id, 'apprex_type', true ) ) {
		apprex_labo_log( (string) $value, 'book' );
	}
}, 10, 4 );

/* =========================================================================
 * つくる（特典）：業種・店名 → アプリの画面案
 * ====================================================================== */
function apprex_labo_presets() {
	// 業種名, メインカラー, 薄い色, キャッチ, ホームのボタン, おすすめ（名前・価格）, クーポン, お知らせ, 写真に写すもの（英語）。
	return array(
		'food'   => array( '飲食店', '#b91c1c', '#fef2f2', 'ご来店ごとにポイントが貯まる', array( 'ネット予約', 'メニュー', 'クーポン', 'スタンプ', 'お知らせ', 'アクセス' ), array( array( '本日のおすすめ', '1,480' ), array( '季節の限定メニュー', '1,280' ), array( '人気のセット', '1,980' ) ), array( 'アプリ限定', 'ドリンク1杯サービス' ), array( '今月の限定メニューが始まりました', '週末のご予約はお早めに' ), 'a beautifully plated signature dish on a wooden counter of a cozy Japanese restaurant, warm evening light, steam rising' ),
		'beauty' => array( '美容サロン', '#be185d', '#fdf2f8', '次回のご予約もアプリでかんたん', array( '予約', 'メニュー', 'スタイル', 'クーポン', '会員証', 'スタッフ' ), array( array( 'カット＋カラー', '8,800' ), array( '髪質改善トリートメント', '6,600' ), array( 'ヘッドスパ', '4,400' ) ), array( '初回限定', '全メニュー20%オフ' ), array( '秋の新色カラーのご案内', '平日限定ヘッドスパ割' ), 'a bright minimalist Japanese hair salon interior with styling chairs, mirrors, plants and soft natural daylight' ),
		'clinic' => array( 'クリニック', '#0e7490', '#ecfeff', '診療予約と順番待ちをスマホで', array( '診療予約', '順番待ち', '診療時間', 'お知らせ', 'Web問診', 'アクセス' ), array( array( '一般内科', '予約制' ), array( '健康診断', '予約制' ), array( '予防接種', '予約制' ) ), array( 'お知らせ', '待ち時間をアプリで確認' ), array( 'インフルエンザ予防接種の予約開始', '年末年始の診療時間' ), 'a clean bright modern Japanese clinic reception and waiting area, calm atmosphere, soft daylight, plants' ),
		'school' => array( '教室・スクール', '#c2410c', '#fff7ed', 'レッスン予約とお知らせを1つに', array( 'レッスン予約', 'コース', '講師紹介', 'お知らせ', '欠席連絡', '会員証' ), array( array( '体験レッスン', '無料' ), array( '月4回コース', '8,800' ), array( '集中講座', '15,000' ) ), array( '入会特典', '入会金0円' ), array( '春の体験レッスン受付中', '発表会のお知らせ' ), 'a bright cheerful classroom studio in Japan with desks, books and warm sunlight, inviting atmosphere' ),
		'retail' => array( '小売・EC', '#1e3a8a', '#eff6ff', '会員限定セールをいち早く', array( 'ストア', 'クーポン', '会員証', '新商品', 'ポイント', 'お知らせ' ), array( array( '新作アイテム', '4,980' ), array( '定番の人気商品', '2,980' ), array( 'ギフトセット', '3,800' ) ), array( '会員限定', '500円オフクーポン' ), array( '新作が入荷しました', '会員限定セールのお知らせ' ), 'a stylish Japanese select shop interior with neatly displayed products on wooden shelves, soft warm lighting' ),
		'other'  => array( 'その他', '#334155', '#f1f5f9', 'お客様とずっとつながる', array( 'お知らせ', '予約', '会員証', 'クーポン', '事例', 'お問い合わせ' ), array( array( '人気のサービス', '要相談' ), array( 'はじめての方へ', '無料' ), array( 'おすすめプラン', '要相談' ) ), array( 'アプリ限定', '初回特典あり' ), array( '新サービスのお知らせ', '営業日のご案内' ), 'a welcoming modern Japanese small business storefront interior, clean and bright, soft natural light' ),
	);
}

/** AIに頼む前の、業種ごとの画面案。 */
function apprex_labo_base_app( $ind, $shop ) {
	$ps  = apprex_labo_presets();
	$ind = isset( $ps[ $ind ] ) ? $ind : 'other';
	$p   = $ps[ $ind ];
	return array(
		'ind'    => $ind,
		'label'  => $p[0],
		'color'  => $p[1],
		'soft'   => $p[2],
		'catch'  => $p[3],
		'menus'  => $p[4],
		'items'  => array_map(
			function ( $x ) {
				return array( 'name' => $x[0], 'price' => $x[1] );
			},
			$p[5]
		),
		'coupon' => array( 'tag' => $p[6][0], 'title' => $p[6][1] ),
		'news'   => $p[7],
		'scene'  => $p[8],
		'shop'   => $shop,
		'photo'  => '',
		'ai'     => false,
	);
}

function apprex_labo_make( $ind, $shop, $mood ) {
	$app = apprex_labo_base_app( $ind, $shop );
	if ( function_exists( 'apprex_ai_text_ready' ) && apprex_ai_text_ready() && function_exists( 'apprex_studio_complete' ) ) {
		$sys = "あなたは日本のアプリ制作会社のUIデザイナーです。LABO用に、店舗アプリのトップ画面の案を作ります。必ずJSONだけを出力：\n"
			. '{"catch":"トップのキャッチコピー（全角16字以内。読点で区切ると2行になる長さ）","color":"#RRGGBB（業種と雰囲気に合うメインカラー。白文字が読める濃さ）","menus":["ホームのボタン6個（各6字以内）"],'
			. '"items":[{"name":"おすすめの商品・メニュー名（全角12字以内）","price":"税込の価格。数字とカンマだけ（例 1,480）。価格がない業種は「予約制」など"}×3],'
			. '"coupon":{"tag":"クーポンの種類（全角6字以内）","title":"特典の内容（全角14字以内）"},"news":["お知らせの見出し（全角18字以内）"×2],'
			. '"scene":"アプリのメイン写真に写すもの。英語で、店名と業種から想像できる具体的な料理・商品・店内の描写（25語以内。人の顔・文字・ロゴは入れない）"}' . "\n"
			. '店名から想像できる具体的な内容にする（例：焼肉店なら上カルビ・厚切りタン）。誇大表現（No.1、絶対など）は使わない。';
		$res = apprex_studio_complete(
			array(
				array( 'role' => 'system', 'content' => $sys ),
				array( 'role' => 'user', 'content' => '業種：' . $app['label'] . "\n店名：" . ( $shop ? $shop : '未定' ) . "\n雰囲気：" . ( $mood ? $mood : 'おまかせ' ) ),
			),
			array( 'max_tokens' => 700, 'timeout' => 45 )
		);
		$j = ( ! is_wp_error( $res ) && function_exists( 'apprex_studio_json' ) ) ? apprex_studio_json( $res ) : null;
		if ( is_array( $j ) ) {
			$t = function ( $v, $n ) {
				return mb_substr( sanitize_text_field( (string) $v ), 0, $n );
			};
			if ( ! empty( $j['catch'] ) ) {
				$app['catch'] = $t( $j['catch'], 22 );
			}
			if ( ! empty( $j['color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $j['color'] ) ) {
				$app['color'] = strtolower( $j['color'] );
				$app['soft']  = apprex_labo_soft( $app['color'] );
			}
			if ( ! empty( $j['menus'] ) && is_array( $j['menus'] ) && count( $j['menus'] ) >= 4 ) {
				$app['menus'] = array_map(
					function ( $m ) use ( $t ) {
						return $t( $m, 7 );
					},
					array_slice( array_values( $j['menus'] ), 0, 6 )
				);
			}
			if ( ! empty( $j['items'] ) && is_array( $j['items'] ) ) {
				$items = array();
				foreach ( array_slice( array_values( $j['items'] ), 0, 3 ) as $it ) {
					if ( is_array( $it ) && ! empty( $it['name'] ) ) {
						$items[] = array( 'name' => $t( $it['name'], 14 ), 'price' => $t( isset( $it['price'] ) ? $it['price'] : '', 10 ) );
					}
				}
				if ( 3 === count( $items ) ) {
					$app['items'] = $items;
				}
			}
			if ( ! empty( $j['coupon']['title'] ) ) {
				$app['coupon'] = array( 'tag' => $t( isset( $j['coupon']['tag'] ) ? $j['coupon']['tag'] : $app['coupon']['tag'], 8 ), 'title' => $t( $j['coupon']['title'], 16 ) );
			}
			if ( ! empty( $j['news'] ) && is_array( $j['news'] ) && count( $j['news'] ) >= 2 ) {
				$app['news'] = array( $t( $j['news'][0], 20 ), $t( $j['news'][1], 20 ) );
			}
			if ( ! empty( $j['scene'] ) && is_string( $j['scene'] ) ) {
				$app['scene'] = mb_substr( preg_replace( '/[^\x20-\x7E]/', '', $j['scene'] ), 0, 220 );
			}
			$app['ai'] = true;
		}
	}
	return $app;
}

/** メインカラーから、ボタンの下地に使う薄い色を作る。 */
function apprex_labo_soft( $hex ) {
	$c = sscanf( $hex, '#%02x%02x%02x' );
	$m = function ( $v ) {
		return (int) round( $v + ( 255 - $v ) * 0.9 );
	};
	return sprintf( '#%02x%02x%02x', $m( $c[0] ), $m( $c[1] ), $m( $c[2] ) );
}

/* =========================================================================
 * つくる：アプリのメイン写真をAIで作る（作るたびに1枚）
 * ====================================================================== */

/** AIで写真を作るか（LABOの設定）。 */
function apprex_labo_photo_on() {
	return 'off' !== get_option( 'apprex_labo_photo', 'on' ) && function_exists( 'apprex_openrouter_image' ) && function_exists( 'apprex_bulk_image_model' ) && function_exists( 'apprex_openrouter_key' ) && '' !== apprex_openrouter_key();
}

/** 1日に作る写真の上限（サイト全体）。 */
function apprex_labo_photo_cap() {
	$n = (int) get_option( 'apprex_labo_photo_cap', 100 );
	return $n > 0 ? $n : 100;
}

/** 今日作った枚数のキー。 */
function apprex_labo_photo_day_key() {
	return 'apprex_labo_photo_n_' . wp_date( 'Ymd' );
}

/** 写真の指示文。 */
function apprex_labo_photo_prompt( $app ) {
	return 'Photorealistic professional commercial photograph used as the hero banner of a Japanese '
		. $app['label'] . ' smartphone app'
		. ( '' !== $app['shop'] ? ' for a shop named "' . preg_replace( '/["\\\\]/', '', $app['shop'] ) . '"' : '' ) . '. '
		. 'Scene: ' . $app['scene'] . '. '
		. 'High-end magazine quality, natural lighting, shallow depth of field, rich but true-to-life colors, horizontal 4:3 composition with the main subject slightly right of center. '
		. 'No text, no letters, no signs with writing, no logos, no watermark, no frames, no smartphone, no close-up faces.';
}

/** 写真を作って保存し、URLを返す。 */
function apprex_labo_photo_make( $app ) {
	// 費用を抑えるため、枚数の多い画像用の安いモデル（1枚約1円）で作る。失敗したときだけ通常のモデルで。
	$prompt = apprex_labo_photo_prompt( $app );
	$img    = apprex_openrouter_image( $prompt, array( 'model' => apprex_bulk_image_model() ) );
	if ( is_wp_error( $img ) ) {
		$img = apprex_openrouter_image( $prompt );
	}
	if ( is_wp_error( $img ) ) {
		return $img;
	}
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'apprex-labo';
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'dir', '保存先を作れませんでした。' );
	}
	$name = wp_date( 'Ymd' ) . '-' . wp_generate_password( 10, false ) . '.jpg';
	$tmp  = $dir . '/tmp-' . $name;
	file_put_contents( $tmp, $img['data'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$ed = wp_get_image_editor( $tmp );
	if ( is_wp_error( $ed ) ) {
		wp_delete_file( $tmp );
		return $ed;
	}
	$ed->resize( 960, 720, true );
	$ed->set_quality( 80 );
	$saved = $ed->save( $dir . '/' . $name, 'image/jpeg' );
	wp_delete_file( $tmp );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	return trailingslashit( $up['baseurl'] ) . 'apprex-labo/' . $name;
}

/* =========================================================================
 * REST
 * ====================================================================== */
add_action( 'rest_api_init', function () {
	$open = '__return_true';
	register_rest_route( 'apprex/v1', '/labo/hello', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => 'apprex_labo_rest_hello' ) );
	register_rest_route( 'apprex/v1', '/labo/make', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => 'apprex_labo_rest_make' ) );
	register_rest_route( 'apprex/v1', '/labo/photo', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => 'apprex_labo_rest_photo' ) );
	register_rest_route( 'apprex/v1', '/labo/posts', array( 'methods' => 'GET', 'permission_callback' => $open, 'callback' => 'apprex_labo_rest_posts' ) );
	register_rest_route( 'apprex/v1', '/labo/post', array( 'methods' => 'POST', 'permission_callback' => $open, 'callback' => 'apprex_labo_rest_post' ) );
} );

/** 開いたとき：流入元を記録して、その人の状況を返す。 */
function apprex_labo_rest_hello( WP_REST_Request $req ) {
	$who = apprex_labo_who( $req->get_param( 'idt' ) );
	$out = array( 'ok' => true, 'line' => (bool) $who );
	if ( $who ) {
		$fid = apprex_labo_friend( $who['uid'] );
		$src = sanitize_key( (string) $req->get_param( 'src' ) );
		if ( $fid ) {
			if ( $who['name'] && 0 === strpos( get_the_title( $fid ), 'LINE ' ) ) {
				wp_update_post( array( 'ID' => $fid, 'post_title' => $who['name'] ) );
			}
			if ( '' !== $src && '' === (string) get_post_meta( $fid, 'apprex_labo_src', true ) ) {
				update_post_meta( $fid, 'apprex_labo_src', $src ); // 最初に来た入口。
			}
			if ( '' !== $src ) {
				update_post_meta( $fid, 'apprex_labo_src_last', $src );
			}
			if ( ! get_post_meta( $fid, 'apprex_labo_first', true ) ) {
				update_post_meta( $fid, 'apprex_labo_first', time() );
			}
			apprex_labo_log( $who['uid'], 'open', $src );
			$app            = get_post_meta( $fid, 'apprex_labo_app', true );
			$out['app']     = is_array( $app ) ? $app : null;
			$out['views']   = (int) get_post_meta( $fid, 'apprex_labo_views', true );
		}
		// 予約（ミーティング・説明会）。
		$bk = array();
		foreach ( get_posts( array( 'post_type' => 'apprex_inquiry', 'post_status' => 'any', 'posts_per_page' => 5, 'fields' => 'ids', 'meta_key' => 'apprex_line_uid', 'meta_value' => $who['uid'] ) ) as $iid ) {
			$t  = get_post_meta( $iid, 'apprex_type', true );
			$at = (int) get_post_meta( $iid, 'apprex_meeting_at', true );
			if ( 'meeting' === $t && $at > time() - DAY_IN_SECONDS && ! get_post_meta( $iid, 'apprex_mb_cancel', true ) ) {
				$bk[] = array( 'kind' => 'オンライン相談', 'when' => function_exists( 'apprex_mb_when' ) ? apprex_mb_when( $at ) : wp_date( 'n月j日 H:i', $at ) );
			} elseif ( 'seminar' === $t && ! get_post_meta( $iid, 'apprex_sem_cancel', true ) ) {
				$s    = function_exists( 'apprex_seminar_get' ) ? apprex_seminar_get( (int) get_post_meta( $iid, 'apprex_seminar_id', true ) ) : null;
				$bk[] = array( 'kind' => '代理店説明会', 'when' => $s ? apprex_seminar_when( $s ) : '次回のご案内待ち' );
			}
		}
		$out['bookings'] = $bk;
		// ご契約中（会員でLINE連携済み）。
		$u                = get_users( array( 'meta_key' => 'apprex_line_uid', 'meta_value' => $who['uid'], 'number' => 1, 'fields' => 'ID' ) );
		$out['member']    = (bool) $u;
		$out['name']      = $who['name'];
	}
	return rest_ensure_response( $out );
}

/**
 * 公式LINE（APPREX）の友だちか。確かめられないとき（トークン未設定・通信エラー）は通す。
 *
 * @param string $uid LINE userId.
 * @return bool
 */
function apprex_labo_is_friend( $uid ) {
	$ck = 'apprex_labo_fr_' . md5( $uid );
	if ( 'yes' === get_transient( $ck ) ) {
		return true;
	}
	$token = function_exists( 'apprex_line_channel_token' ) ? apprex_line_channel_token() : '';
	if ( '' === $token ) {
		return true;
	}
	$r = wp_remote_get( 'https://api.line.me/v2/bot/profile/' . rawurlencode( $uid ), array( 'timeout' => 10, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) ) );
	if ( is_wp_error( $r ) ) {
		return true;
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 200 === $code ) {
		set_transient( $ck, 'yes', DAY_IN_SECONDS );
		return true;
	}
	return 404 !== $code; // 404 = 友だちではない（未追加・ブロック）。
}

function apprex_labo_rest_make( WP_REST_Request $req ) {
	$who  = apprex_labo_who( $req->get_param( 'idt' ) );
	// 特典は、LINEでは公式LINEの友だち、ブラウザではサイトの無料会員（ログイン中）が使える。
	if ( ! is_user_logged_in() ) {
		if ( ! $who ) {
			return new WP_REST_Response( array( 'ok' => false, 'need' => 'line', 'message' => 'LINEの友だち、または無料会員の特典です。' ), 200 );
		}
		if ( ! apprex_labo_is_friend( $who['uid'] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'need' => 'friend', 'message' => 'APPREXの公式LINEを友だち追加すると使えます。' ), 200 );
		}
	}
	$shop = mb_substr( sanitize_text_field( (string) $req->get_param( 'shop' ) ), 0, 30 );
	$mood = mb_substr( sanitize_text_field( (string) $req->get_param( 'mood' ) ), 0, 40 );
	// 1日の回数（LINEの人ごと、無ければIPごと）。
	$key = 'apprex_labo_mk_' . md5( $who ? $who['uid'] : ( is_user_logged_in() ? 'u' . get_current_user_id() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ) ) );
	$n   = (int) get_transient( $key );
	if ( $n >= 10 ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => '本日の作成回数の上限です。明日またお試しください。' ), 200 );
	}
	set_transient( $key, $n + 1, DAY_IN_SECONDS );
	$app = apprex_labo_make( sanitize_key( (string) $req->get_param( 'ind' ) ), $shop, $mood );
	if ( $who ) {
		$fid = apprex_labo_friend( $who['uid'] );
		if ( $fid ) {
			update_post_meta( $fid, 'apprex_labo_app', $app );
		}
		apprex_labo_log( $who['uid'], 'make', $app['label'] . ' ' . $shop );
	}
	// 写真は次の呼び出しで作る（画面を先に見せる）。その1回だけ使える合言葉を渡す。
	$out = array( 'ok' => true, 'app' => $app );
	if ( apprex_labo_photo_on() && (int) get_option( apprex_labo_photo_day_key(), 0 ) < apprex_labo_photo_cap() ) {
		$pkey = 'apprex_labo_pk_' . md5( $who ? $who['uid'] : $key );
		if ( (int) get_transient( $pkey ) < 5 ) {
			$tok = wp_generate_password( 20, false );
			set_transient( 'apprex_labo_pt_' . $tok, array( 'app' => $app, 'uid' => $who ? $who['uid'] : '', 'pkey' => $pkey ), 10 * MINUTE_IN_SECONDS );
			$out['photoToken'] = $tok;
		}
	}
	return rest_ensure_response( $out );
}

/** つくる：メイン写真をAIで作る。 */
function apprex_labo_rest_photo( WP_REST_Request $req ) {
	$tok = preg_replace( '/[^A-Za-z0-9]/', '', (string) $req->get_param( 'token' ) );
	$t   = '' !== $tok ? get_transient( 'apprex_labo_pt_' . $tok ) : false;
	if ( ! is_array( $t ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => '写真を作れませんでした。' ), 200 );
	}
	delete_transient( 'apprex_labo_pt_' . $tok );
	$day = apprex_labo_photo_day_key();
	$n   = (int) get_option( $day, 0 );
	if ( ! apprex_labo_photo_on() || $n >= apprex_labo_photo_cap() ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => '本日の写真づくりは終了しました。' ), 200 );
	}
	update_option( $day, $n + 1, false );
	set_transient( $t['pkey'], (int) get_transient( $t['pkey'] ) + 1, DAY_IN_SECONDS );
	$url = apprex_labo_photo_make( $t['app'] );
	if ( is_wp_error( $url ) ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => '写真を作れませんでした。' ), 200 );
	}
	if ( '' !== $t['uid'] ) {
		$fid = apprex_labo_friend( $t['uid'] );
		$cur = $fid ? get_post_meta( $fid, 'apprex_labo_app', true ) : null;
		// 作ったあとに作り直していなければ、その画面に写真をつける。
		if ( is_array( $cur ) && $cur['shop'] === $t['app']['shop'] && $cur['catch'] === $t['app']['catch'] ) {
			$cur['photo'] = $url;
			update_post_meta( $fid, 'apprex_labo_app', $cur );
		}
	}
	return rest_ensure_response( array( 'ok' => true, 'photo' => $url ) );
}

function apprex_labo_rest_posts( WP_REST_Request $req ) {
	$args = array( 'post_type' => 'apprex_labo_post', 'post_status' => 'publish', 'posts_per_page' => 30 );
	$cat  = sanitize_text_field( (string) $req->get_param( 'cat' ) );
	if ( '' !== $cat ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'apprex_labo_cat', 'field' => 'name', 'terms' => $cat ) );
	}
	$cats = get_terms( array( 'taxonomy' => 'apprex_labo_cat', 'hide_empty' => true, 'fields' => 'names' ) );
	return rest_ensure_response(
		array(
			'ok'    => true,
			'cats'  => is_wp_error( $cats ) ? array() : array_values( $cats ),
			'posts' => array_map( 'apprex_labo_post_out', get_posts( $args ) ),
		)
	);
}

function apprex_labo_rest_post( WP_REST_Request $req ) {
	$p = get_post( (int) $req->get_param( 'id' ) );
	if ( ! $p || 'apprex_labo_post' !== $p->post_type || 'publish' !== $p->post_status ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => '記事が見つかりませんでした。' ), 200 );
	}
	$who = apprex_labo_who( $req->get_param( 'idt' ) );
	if ( $who ) {
		apprex_labo_log( $who['uid'], 'view', $p->ID . ' ' . get_the_title( $p ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'post' => apprex_labo_post_out( $p, true ) ) );
}

/* =========================================================================
 * LABOの画面 /labo
 * ====================================================================== */
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_labo' ) ) {
		return;
	}
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	$liff = apprex_labo_liff_id();
	$cfg  = array(
		'liff'     => $liff,
		'img'      => APPREX_URI . '/assets/images/',
		'open'     => apprex_labo_url( 'web' ),
		'friend'   => function_exists( 'apprex_line_url' ) ? apprex_line_url() : '',
		'member'   => is_user_logged_in(),
		'login'    => wp_login_url( home_url( '/labo' ) ),
		'rest'     => esc_url_raw( rest_url( 'apprex/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'inds'     => array_map(
			function ( $p ) {
				return $p[0];
			},
			apprex_labo_presets()
		),
		'seminar'  => function_exists( 'apprex_seminar_url' ) ? apprex_seminar_url() : '',
		'mypage'   => function_exists( 'apprex_liff_id' ) && '' !== apprex_liff_id() ? 'https://liff.line.me/' . rawurlencode( apprex_liff_id() ) : home_url( '/mypage/' ),
		'studio'   => function_exists( 'apprex_studio_url' ) ? apprex_studio_url() : home_url( '/studio/' ),
		'pricing'  => home_url( '/pricing/' ),
		'cases'    => home_url( '/cases/' ),
		'pp'       => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
		'meet'     => function_exists( 'apprex_mb_enabled' ) && apprex_mb_enabled(),
		'meetUrl'  => function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '',
	);
	?>
<!doctype html><html lang="ja"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>APPREX LABO</title>
<?php if ( '' !== $liff ) : ?><script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script><?php endif; ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( APPREX_URI . '/assets/css/labo.css?v=' . APPREX_VERSION ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( APPREX_URI . '/assets/css/meeting.css?v=' . APPREX_VERSION ); ?>">
</head><body>
<div class="lb" id="labo" data-cfg="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
	<header class="lb-top"><div class="lb-logo"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></svg></div><div><b>APPREX <span>LABO</span></b><small>公式LINE ミニアプリ</small></div></header>
	<main class="lb-main" id="lbMain"><div class="lb-loading"><span></span>読み込んでいます…</div></main>
	<nav class="lb-nav" id="lbNav">
		<button type="button" data-tab="home" class="on"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>ホーム</button>
		<button type="button" data-tab="make"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M12 7l1.2 2.4L16 10.6l-2.8 1.2L12 14l-1.2-2.2L8 10.6l2.8-1.2z"/></svg>つくる</button>
		<button type="button" data-tab="learn"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h11a3 3 0 0 1 3 3v11H7a3 3 0 0 1-3-3z"/><path d="M8 9h6M8 13h4"/></svg>学ぶ</button>
		<button type="button" data-tab="book"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>予約</button>
		<button type="button" data-tab="my"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>マイ</button>
	</nav>
</div>
<script src="<?php echo esc_url( APPREX_URI . '/assets/js/labo.js?v=' . APPREX_VERSION ); ?>"></script>
</body></html>
	<?php
	exit;
} );

/* =========================================================================
 * 管理画面：設定・入口URL・成果（LABO メニュー）
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=apprex_labo_post', 'LABOの成果', '成果（流入元ごと）', 'edit_posts', 'apprex-labo-report', 'apprex_labo_report_page' );
	add_submenu_page( 'edit.php?post_type=apprex_labo_post', 'LABOの入口URL', '入口URL・QRコード', 'edit_posts', 'apprex-labo-links', 'apprex_labo_links_page' );
	add_submenu_page( 'edit.php?post_type=apprex_labo_post', 'LABOの設定', 'LABOの設定', 'manage_options', 'apprex-labo', 'apprex_labo_settings_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_labo', 'apprex_labo_liff_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting(
		'apprex_labo',
		'apprex_labo_photo',
		array(
			'sanitize_callback' => function ( $v ) {
				return 'off' === $v ? 'off' : 'on';
			},
		)
	);
	register_setting( 'apprex_labo', 'apprex_labo_photo_cap', array( 'sanitize_callback' => 'absint' ) );
	register_setting(
		'apprex_labo',
		'apprex_labo_blog_cta',
		array(
			'sanitize_callback' => function ( $v ) {
				return 'off' === $v ? 'off' : 'on';
			},
		)
	);
	register_setting(
		'apprex_labo_links',
		'apprex_labo_srcs',
		array(
			'sanitize_callback' => function ( $v ) {
				$out = array();
				foreach ( preg_split( '/\R/', (string) $v ) as $line ) {
					$p   = array_map( 'trim', explode( ',', $line, 2 ) );
					$key = sanitize_key( $p[0] );
					if ( '' !== $key ) {
						$out[ $key ] = isset( $p[1] ) && '' !== $p[1] ? sanitize_text_field( $p[1] ) : $key;
					}
				}
				return $out;
			},
		)
	);
} );

/** 入口（流入元）の一覧。 */
function apprex_labo_srcs() {
	$o = get_option( 'apprex_labo_srcs', null );
	if ( ! is_array( $o ) ) {
		$o = array(
			'insta_profile' => 'インスタ プロフィール',
			'insta_story'   => 'インスタ ストーリー',
			'sns_ad'        => 'SNS広告',
			'lp_app'        => 'LP（アプリ開発）',
			'site'          => 'サイト',
			'richmenu'      => 'LINEのメニュー',
		);
	}
	// サイトが自動で使う入口は、一覧を書き換えていても残す。
	return $o + array(
		'richmenu' => 'LINEのメニュー',
		'blog'     => 'ブログ記事',
		'chat'     => 'AIチャット',
		'web'      => 'ブラウザから',
	);
}

/* =========================================================================
 * ブログ記事 → LINE（友だち限定の特典へ）
 * 記事の途中（2つ目の見出しの前）と最後に、LINEの友だち追加と特典の案内を入れる。
 * ====================================================================== */

/** 記事にLINEの案内を入れるか（LABOの設定）。 */
function apprex_labo_blog_cta_on() {
	return 'off' !== get_option( 'apprex_labo_blog_cta', 'on' );
}

/**
 * 記事に入れる案内。
 *
 * @param string $kind mid（途中の短いもの）／end（最後の大きいもの）。
 * @return string
 */
function apprex_labo_blog_cta( $kind ) {
	$url  = apprex_labo_url( 'blog' );
	$icon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.5 3 2 6.6 2 11c0 3.9 3.5 7.2 8.3 7.9.3.1.8.2.9.5.1.3.1.7 0 1l-.1.9c0 .3-.2 1 .9.6 1.1-.5 6-3.5 8.2-6 1.5-1.6 2.3-3.3 2.3-5C22.5 6.6 17.9 3 12 3z"/></svg>';
	if ( 'mid' === $kind ) {
		return '<aside class="ap-line ap-line--mid"><span class="ap-line-ic">' . $icon . '</span><p><b>あなたのお店のアプリ画面、AIで無料で作れます。</b><span>APPREXのLINE友だち限定の特典です。</span></p><a class="ap-line-btn" href="' . esc_url( $url ) . '" rel="nofollow">LINEで受け取る</a></aside>';
	}
	return '<aside class="ap-line ap-line--end"><div class="ap-line-t"><span class="ap-line-tag">LINE友だち限定・無料</span><h3>この記事の内容、あなたのお店ならどうなる？</h3>'
		. '<p>APPREXのLINEを友だち追加すると、業種と店名を入れるだけで、<b>写真入りのアプリ画面をAIが作ります</b>。アプリ集客の記事・動画や、無料のオンライン相談の予約もLINEの中でできます。</p>'
		. '<ul><li>AIでお店のアプリ画面を作る</li><li>アプリ集客の記事・動画</li><li>無料相談をLINEで予約</li></ul>'
		. '<a class="ap-line-btn" href="' . esc_url( $url ) . '" rel="nofollow">' . $icon . 'LINEで友だち追加して使う</a><small>友だち追加は無料です。いつでもブロックできます。</small></div></aside>';
}

add_filter(
	'the_content',
	function ( $content ) {
		if ( is_admin() || is_feed() || ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || ! apprex_labo_blog_cta_on() ) {
			return $content;
		}
		// 途中：2つ目の見出し（h2）の前。見出しが少ない記事は入れない。
		$pos = 0;
		$n   = 0;
		while ( false !== ( $pos = stripos( $content, '<h2', $pos ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$n++;
			if ( 2 === $n ) {
				$content = substr( $content, 0, $pos ) . apprex_labo_blog_cta( 'mid' ) . substr( $content, $pos );
				break;
			}
			$pos += 3;
		}
		return $content . apprex_labo_blog_cta( 'end' );
	},
	15
);

/* 記事のLINE案内の見た目（記事ページだけ）。 */
add_action(
	'wp_head',
	function () {
		if ( ! is_singular( 'post' ) || ! apprex_labo_blog_cta_on() ) {
			return;
		}
		echo '<style id="ap-line-css">'
			. '.ap-line{--lg:#06c755;margin:2em 0;border-radius:16px;font-size:15px;line-height:1.7}'
			. '.ap-line svg{width:22px;height:22px;fill:currentColor;flex:none}'
			. '.ap-line-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:0 22px;border-radius:999px;background:var(--lg);color:#fff!important;font-weight:800;text-decoration:none!important;box-shadow:0 10px 22px -10px rgba(6,199,85,.8);white-space:nowrap}'
			. '.ap-line-btn:hover{filter:brightness(1.05)}'
			. '.ap-line--mid{display:flex;align-items:center;gap:14px;padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0}'
			. '.ap-line--mid p{flex:1;margin:0}.ap-line--mid p b{display:block;color:#14532d}.ap-line--mid p span{font-size:13px;color:#166534}'
			. '.ap-line-ic{width:40px;height:40px;border-radius:12px;background:var(--lg);color:#fff;display:grid;place-items:center;flex:none}'
			. '.ap-line--end{position:relative;overflow:hidden;padding:26px 24px;color:#fff;background:radial-gradient(90% 120% at 100% 0,rgba(255,255,255,.18),transparent 55%),linear-gradient(135deg,#06c755,#048a3c)}'
			. '.ap-line--end h3{margin:.4em 0 .5em!important;padding:0!important;border:0!important;background:none!important;font-size:1.3em;color:#fff!important;line-height:1.45}.ap-line--end h3::before,.ap-line--end h3::after,.ap-line--end li::before{display:none!important;content:none!important}'
			. '.ap-line--end p{margin:0 0 .8em}.ap-line--end b{background:linear-gradient(transparent 60%,rgba(255,255,255,.3) 60%)}'
			. '.ap-line--end ul{margin:0 0 1.2em;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px}'
			. '.ap-line--end li{margin:0!important;font-size:13px;font-weight:700;padding:4px 12px!important;border-radius:999px;background:rgba(255,255,255,.18);color:#fff}'
			. '.ap-line--end .ap-line-btn{background:#fff;color:#048a3c!important;box-shadow:0 12px 24px -12px rgba(0,0,0,.4)}'
			. '.ap-line--end small{display:block;margin-top:10px;font-size:12px;opacity:.85}'
			. '.ap-line-tag{display:inline-block;font-size:12px;font-weight:800;padding:3px 10px;border-radius:999px;background:#fff;color:#048a3c}'
			. '@media (max-width:600px){.ap-line--mid{flex-wrap:wrap}.ap-line--mid .ap-line-btn{width:100%}.ap-line--end .ap-line-btn{width:100%}}'
			. '</style>';
	}
);

function apprex_labo_settings_page() {
	$ready = '' !== apprex_labo_liff_id();
	?>
	<div class="wrap">
		<h1>LABOの設定</h1>
		<div class="notice notice-info"><p>
			<strong>LINEの準備</strong>（1回だけ）：LINE Developers → チャネル「APPREX」（LINEログイン）→ LIFF →「追加」<br>
			・LIFFアプリ名：APPREX LABO／サイズ：<b>Full</b><br>
			・エンドポイントURL：<code><?php echo esc_html( apprex_labo_endpoint_url() ); ?></code><br>
			・Scope：<b>openid・profile</b>／友だち追加オプション：<b>On（Aggressive）</b><br>
			発行された LIFF ID を下に入れて保存。状態：<strong style="color:<?php echo $ready ? '#15803d' : '#b91c1c'; ?>"><?php echo $ready ? 'LINEで使えます' : '未設定'; ?></strong>
		</p></div>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_labo' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="apprex_labo_liff_id">LABOのLIFF ID</label></th><td><input type="text" id="apprex_labo_liff_id" name="apprex_labo_liff_id" value="<?php echo esc_attr( apprex_labo_liff_id() ); ?>" class="regular-text" placeholder="2010460182-xxxxxxxx">
				<p class="description">LABOのURL：<code><?php echo esc_html( apprex_labo_url() ); ?></code></p></td></tr>
				<tr><th><label for="apprex_labo_photo">アプリ画面の写真</label></th><td>
					<select id="apprex_labo_photo" name="apprex_labo_photo">
						<option value="on" <?php selected( 'off' !== get_option( 'apprex_labo_photo', 'on' ) ); ?>>お店ごとにAIで作る</option>
						<option value="off" <?php selected( 'off' === get_option( 'apprex_labo_photo', 'on' ) ); ?>>作らない（業種の色だけ）</option>
					</select>
					<p class="description">「つくる」のたびに、そのお店用のメイン写真を1枚AIで作ります（OpenRouterの安い画像モデル：<?php echo esc_html( function_exists( 'apprex_bulk_image_model' ) ? apprex_bulk_image_model() : '' ); ?>。1枚あたり約1円。作れなかったときだけ通常のモデルで約10円）。1人1日5枚まで。</p></td></tr>
				<tr><th><label for="apprex_labo_blog_cta">ブログ記事にLINEの案内</label></th><td>
					<select id="apprex_labo_blog_cta" name="apprex_labo_blog_cta">
						<option value="on" <?php selected( apprex_labo_blog_cta_on() ); ?>>入れる（記事の途中と最後）</option>
						<option value="off" <?php selected( ! apprex_labo_blog_cta_on() ); ?>>入れない</option>
					</select>
					<p class="description">記事を読んだ人を、LINEの友だち追加と特典（AIでアプリ画面を作る）へ案内します。入口は「ブログ記事」として「成果」で数えます。</p></td></tr>
				<tr><th><label for="apprex_labo_photo_cap">1日に作る写真の上限</label></th><td>
					<input type="number" min="1" id="apprex_labo_photo_cap" name="apprex_labo_photo_cap" value="<?php echo esc_attr( apprex_labo_photo_cap() ); ?>" class="small-text"> 枚（サイト全体）
					<p class="description">今日作った枚数：<?php echo esc_html( (int) get_option( apprex_labo_photo_day_key(), 0 ) ); ?>枚。上限をこえると、その日は写真なしの画面になります。</p></td></tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
		<p>次は「入口URL・QRコード」で、インスタやLPに置くURLを作ります。記事・動画は「LABO → 記事・動画を追加」から書けます。</p>
	</div>
	<?php
}

function apprex_labo_links_page() {
	$srcs = apprex_labo_srcs();
	$text = array();
	foreach ( $srcs as $k => $l ) {
		$text[] = $k . ',' . $l;
	}
	?>
	<div class="wrap">
		<h1>入口URL・QRコード</h1>
		<p>インスタのプロフィール、ストーリー、広告、LPのボタンなどに、入口ごとのURLを置きます。開いた人がどの入口から来たかが記録され、「成果」で比べられます。</p>
		<?php if ( '' === apprex_labo_liff_id() ) : ?><div class="notice notice-warning inline"><p>LABOの LIFF ID が未設定です。「LABOの設定」で入れると、URLがLINEの中で開く形になります。</p></div><?php endif; ?>
		<table class="widefat striped" style="max-width:1100px"><thead><tr><th>入口</th><th>URL</th><th>QRコード</th></tr></thead><tbody>
		<?php foreach ( $srcs as $k => $l ) : ?>
			<?php $u = apprex_labo_url( $k ); ?>
			<tr><td><b><?php echo esc_html( $l ); ?></b><br><code><?php echo esc_html( $k ); ?></code></td>
				<td><input type="text" readonly value="<?php echo esc_attr( $u ); ?>" class="large-text" onclick="this.select()"><button type="button" class="button" onclick="navigator.clipboard.writeText(<?php echo esc_attr( wp_json_encode( $u ) ); ?>);this.textContent='コピーしました'">コピー</button></td>
				<td><div class="apprex-qr" data-url="<?php echo esc_attr( $u ); ?>" style="width:110px;height:110px"></div></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<h2 style="margin-top:28px">入口を増やす・名前を変える</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_labo_links' ); ?>
			<textarea name="apprex_labo_srcs" rows="8" class="large-text code"><?php echo esc_textarea( implode( "\n", $text ) ); ?></textarea>
			<p class="description">1行に1つ、「英数字の名前,表示名」（例：<code>insta_reel,インスタ リール</code>）。英数字の名前がURLの <code>?src=</code> に入ります。</p>
			<?php submit_button( '保存' ); ?>
		</form>
	</div>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
	<script>document.querySelectorAll('.apprex-qr').forEach(function(el){ try{ new QRCode(el,{text:el.getAttribute('data-url'),width:110,height:110}); }catch(e){} });</script>
	<?php
}

function apprex_labo_report_page() {
	$srcs  = apprex_labo_srcs();
	$days  = isset( $_GET['days'] ) ? max( 0, (int) $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification
	$since = $days ? time() - $days * DAY_IN_SECONDS : 0;
	$ids   = get_posts( array( 'post_type' => 'apprex_line_friend', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'apprex_labo_first' ) );
	$rows  = array();
	foreach ( $ids as $id ) {
		if ( $since && (int) get_post_meta( $id, 'apprex_labo_first', true ) < $since ) {
			continue;
		}
		$s = (string) get_post_meta( $id, 'apprex_labo_src', true );
		$s = '' === $s ? '(不明)' : $s;
		if ( ! isset( $rows[ $s ] ) ) {
			$rows[ $s ] = array( 0, 0, 0, 0 );
		}
		$rows[ $s ][0]++;
		$rows[ $s ][1] += get_post_meta( $id, 'apprex_labo_made', true ) ? 1 : 0;
		$rows[ $s ][2] += (int) get_post_meta( $id, 'apprex_labo_views', true ) > 0 ? 1 : 0;
		$rows[ $s ][3] += get_post_meta( $id, 'apprex_labo_booked', true ) ? 1 : 0;
	}
	$tot = array( 0, 0, 0, 0 );
	foreach ( $rows as $r ) {
		foreach ( $r as $i => $n ) {
			$tot[ $i ] += $n;
		}
	}
	$pct = function ( $a, $b ) {
		return $b ? round( 100 * $a / $b ) . '%' : '—';
	};
	?>
	<div class="wrap">
		<h1>LABOの成果（流入元ごと）</h1>
		<p>期間：
			<?php foreach ( array( 7 => '7日', 30 => '30日', 90 => '90日', 0 => 'すべて' ) as $d => $l ) : ?>
				<a class="button<?php echo $days === $d ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $d ) ); ?>"><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
		</p>
		<table class="widefat striped" style="max-width:1000px"><thead><tr><th>入口</th><th>LABOを開いた人</th><th>特典を使った</th><th>記事・動画を見た</th><th>予約した</th><th>予約まで進んだ割合</th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="6">まだ記録がありません。</td></tr><?php endif; ?>
		<?php foreach ( $rows as $s => $r ) : ?>
			<tr><td><b><?php echo esc_html( isset( $srcs[ $s ] ) ? $srcs[ $s ] : $s ); ?></b></td><td><?php echo (int) $r[0]; ?></td><td><?php echo (int) $r[1]; ?>（<?php echo esc_html( $pct( $r[1], $r[0] ) ); ?>）</td><td><?php echo (int) $r[2]; ?>（<?php echo esc_html( $pct( $r[2], $r[0] ) ); ?>）</td><td><?php echo (int) $r[3]; ?></td><td><b><?php echo esc_html( $pct( $r[3], $r[0] ) ); ?></b></td></tr>
		<?php endforeach; ?>
		<?php if ( $rows ) : ?><tr><td><b>合計</b></td><td><b><?php echo (int) $tot[0]; ?></b></td><td><b><?php echo (int) $tot[1]; ?></b></td><td><b><?php echo (int) $tot[2]; ?></b></td><td><b><?php echo (int) $tot[3]; ?></b></td><td><b><?php echo esc_html( $pct( $tot[3], $tot[0] ) ); ?></b></td></tr><?php endif; ?>
		</tbody></table>
		<p class="description">LABOを開いた日で数えています。入口は、その人が最初に来た入口です。</p>
	</div>
	<?php
}
