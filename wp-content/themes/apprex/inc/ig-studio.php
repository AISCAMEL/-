<?php
/**
 * インスタグラム：記事から「複数枚の画像（カルーセル）」と「リール動画」を作って投稿する。
 *
 * - 文章（表紙の見出し・4つのポイント・キャプション・ハッシュタグ）は AI（Claude が使えれば Claude）が記事から作る。
 * - 画像は、記事の写真（アイキャッチ・本文の写真）を背景に、サイトが文字を入れて作る（1080×1350、6枚）。
 *   画像に文字を入れるのはサイト側なので、AIの画像代はかからない。
 * - リール動画は、その6枚をつないだ短い動画（約18秒）。サーバーで動画を作れる（ffmpeg がある）ときは自動、
 *   作れないときは管理画面のボタンで、ブラウザが動画を作ってアップロードする。
 * - 記事を公開すると、2分後にカルーセルを自動で投稿する（設定 → APPREX 配信(SNS) の Instagram が設定済みのとき）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const APPREX_GRAPH = 'https://graph.facebook.com/v23.0/';

/* =========================================================================
 * 1) 文章：記事 → スライドの文とキャプション
 * ====================================================================== */

/**
 * 記事からインスタ用の文章を作る。
 *
 * @param WP_Post $post 記事。
 * @return array|WP_Error { cover, points:[{h,t}], caption, tags:[] }
 */
function apprex_ig_copy( $post ) {
	$text = mb_substr( trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_content ) ) ), 0, 6000 );
	$sys  = ( function_exists( 'apprex_ai_article_system' ) ? apprex_ai_article_system() : '' )
		. "\nあなたはAPPREXのインスタグラム担当でもあります。店舗・中小企業の経営者が保存したくなる投稿を作ります。";
	$user = "次のブログ記事から、インスタグラムのカルーセル投稿（6枚）とキャプションを作ってください。必ずJSONだけを出力：\n"
		. '{"cover":"1枚目の大きな見出し（全角22字以内。読む人の悩みや得をはっきり。2行で読める）",'
		. '"points":[{"h":"2〜5枚目の見出し（全角14字以内）","t":"説明（全角60〜80字。具体的に）"} を4つ],'
		. '"caption":"キャプション（300〜500字。1行目で興味を引く。改行で読みやすく。絵文字は2〜4個まで。最後は「プロフィールのリンクから、LINEで『AIでお店のアプリ画面を作る』を無料で試せます」で締める。URLは書かない）",'
		. '"tags":["ハッシュタグを12〜15個。#から始める。日本語中心で、店舗・アプリ・集客・業種など検索されるもの"]}' . "\n"
		. "誇大表現・確かでない数字は使わない。\n\n記事のタイトル：" . get_the_title( $post ) . "\n記事：" . $text;
	$res = apprex_openrouter_complete(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => $user ),
		),
		array( 'max_tokens' => 1600, 'timeout' => 90, 'article' => true )
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$j = function_exists( 'apprex_studio_json' ) ? apprex_studio_json( $res ) : json_decode( $res, true );
	if ( ! is_array( $j ) || empty( $j['cover'] ) || empty( $j['points'] ) || ! is_array( $j['points'] ) ) {
		return new WP_Error( 'ig_copy', 'インスタ用の文章を作れませんでした。' );
	}
	$pts = array();
	foreach ( array_slice( $j['points'], 0, 4 ) as $p ) {
		if ( is_array( $p ) && ! empty( $p['h'] ) ) {
			$pts[] = array( 'h' => mb_substr( sanitize_text_field( $p['h'] ), 0, 18 ), 't' => mb_substr( sanitize_text_field( isset( $p['t'] ) ? $p['t'] : '' ), 0, 100 ) );
		}
	}
	$tags = array();
	foreach ( isset( $j['tags'] ) && is_array( $j['tags'] ) ? $j['tags'] : array() as $t ) {
		$t = preg_replace( '/[\s#＃]+/u', '', (string) $t );
		if ( '' !== $t ) {
			$tags[] = '#' . $t;
		}
	}
	return array(
		'cover'   => mb_substr( sanitize_text_field( $j['cover'] ), 0, 26 ),
		'points'  => $pts,
		'caption' => trim( sanitize_textarea_field( isset( $j['caption'] ) ? $j['caption'] : '' ) ),
		'tags'    => array_slice( array_unique( $tags ), 0, 15 ),
	);
}

/* =========================================================================
 * 2) 画像：文字を入れたスライドを作る（GD）
 * ====================================================================== */

/** 文字のフォント。 */
function apprex_ig_font( $bold ) {
	return APPREX_DIR . '/assets/fonts/' . ( $bold ? 'NotoSansJP-Black.ttf' : 'NotoSansJP-Medium.ttf' );
}

/** 画像を作れるか（GD と FreeType）。 */
function apprex_ig_can_draw() {
	return function_exists( 'imagettftext' ) && function_exists( 'imagecreatetruecolor' ) && is_readable( apprex_ig_font( true ) );
}

/**
 * 日本語の文を、幅に合わせて行に分ける（行頭に「、。」などが来ないように）。
 *
 * @return string[]
 */
function apprex_ig_wrap( $text, $size, $font, $width, $max_lines = 0 ) {
	$no_head = '、。，．・：；？！ー」』）】〉》〕％…ぁぃぅぇぉっゃゅょゎァィゥェォッャュョヮ';
	$chars   = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	$lines   = array();
	$line    = '';
	foreach ( $chars as $ch ) {
		if ( "\n" === $ch ) {
			$lines[] = $line;
			$line    = '';
			continue;
		}
		$try = $line . $ch;
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( $box[2] - $box[0] > $width && '' !== $line ) {
			if ( false !== mb_strpos( $no_head, $ch ) ) {
				// 行頭禁則：前の行の最後の1文字と一緒に送る。
				$last    = mb_substr( $line, -1 );
				$lines[] = mb_substr( $line, 0, -1 );
				$line    = $last . $ch;
			} else {
				$lines[] = $line;
				$line    = $ch;
			}
		} else {
			$line = $try;
		}
	}
	if ( '' !== $line ) {
		$lines[] = $line;
	}
	if ( $max_lines && count( $lines ) > $max_lines ) {
		$lines                   = array_slice( $lines, 0, $max_lines );
		$lines[ $max_lines - 1 ] = mb_substr( $lines[ $max_lines - 1 ], 0, -1 ) . '…';
	}
	return $lines;
}

/** 色（#RRGGBB と透明度0〜127）。 */
function apprex_ig_color( $im, $hex, $alpha = 0 ) {
	$c = sscanf( ltrim( $hex, '#' ), '%02x%02x%02x' );
	return imagecolorallocatealpha( $im, $c[0], $c[1], $c[2], $alpha );
}

/** 写真を枠いっぱいに切り抜いて貼る。 */
function apprex_ig_cover( $im, $file, $x, $y, $w, $h ) {
	$src = $file && is_readable( $file ) ? @imagecreatefromstring( (string) file_get_contents( $file ) ) : false; // phpcs:ignore
	if ( ! $src ) {
		return false;
	}
	$sw = imagesx( $src );
	$sh = imagesy( $src );
	$r  = max( $w / $sw, $h / $sh );
	$cw = (int) round( $w / $r );
	$ch = (int) round( $h / $r );
	imagecopyresampled( $im, $src, $x, $y, (int) ( ( $sw - $cw ) / 2 ), (int) ( ( $sh - $ch ) / 2 ), $w, $h, $cw, $ch );
	imagedestroy( $src );
	return true;
}

/** 上から下へ暗くなる重ね（写真の上の文字を読みやすく）。 */
function apprex_ig_shade( $im, $y0, $y1, $from, $to, $hex = '#0b1220' ) {
	for ( $y = $y0; $y < $y1; $y += 2 ) {
		$t = ( $y - $y0 ) / max( 1, $y1 - $y0 );
		$a = (int) round( $from + ( $to - $from ) * $t );
		imagefilledrectangle( $im, 0, $y, imagesx( $im ), $y + 1, apprex_ig_color( $im, $hex, max( 0, min( 127, $a ) ) ) );
	}
}

/** 文字を書く（行の配列）。戻り値は最後の行の下端。 */
function apprex_ig_text( $im, $lines, $size, $font, $x, $y, $color, $lh = 1.45 ) {
	foreach ( $lines as $i => $l ) {
		imagettftext( $im, $size, 0, $x, (int) ( $y + $size + $i * $size * $lh ), $color, $font, $l );
	}
	return (int) ( $y + $size + ( count( $lines ) - 1 ) * $size * $lh + $size * 0.4 );
}

/** 蛍光ペンで線を引いたような文字（行の下半分に色）。戻り値は最後の行の下端。 */
function apprex_ig_marker_text( $im, $lines, $size, $font, $x, $y, $ink, $mark, $lh = 1.4 ) {
	foreach ( $lines as $i => $l ) {
		$b    = imagettfbbox( $size, 0, $font, $l );
		$base = (int) ( $y + $size + $i * $size * $lh );
		imagefilledrectangle( $im, $x - 10, (int) ( $base - $size * 0.42 ), $x + ( $b[2] - $b[0] ) + 10, (int) ( $base + $size * 0.18 ), $mark );
		imagettftext( $im, $size, 0, $x, $base, $ink, $font, $l );
	}
	return (int) ( $y + $size + ( count( $lines ) - 1 ) * $size * $lh + $size * 0.4 );
}

/** 帯の上に文字（1行ずつ色の帯）。戻り値は最後の帯の下端。 */
function apprex_ig_band_text( $im, $lines, $size, $font, $x, $y, $ink, $band, $gap = 18 ) {
	foreach ( $lines as $l ) {
		$b = imagettfbbox( $size, 0, $font, $l );
		$h = (int) ( $size * 1.55 );
		imagefilledrectangle( $im, $x - 22, $y, $x + ( $b[2] - $b[0] ) + 22, $y + $h, $band );
		imagettftext( $im, $size, 0, $x, (int) ( $y + $h / 2 + $size * 0.45 ), $ink, $font, $l );
		$y += $h + $gap;
	}
	return $y;
}

/** 角丸の四角。 */
function apprex_ig_round( $im, $x, $y, $w, $h, $r, $col ) {
	imagefilledrectangle( $im, $x + $r, $y, $x + $w - $r, $y + $h, $col );
	imagefilledrectangle( $im, $x, $y + $r, $x + $w, $y + $h - $r, $col );
	foreach ( array( array( $x + $r, $y + $r ), array( $x + $w - $r, $y + $r ), array( $x + $r, $y + $h - $r ), array( $x + $w - $r, $y + $h - $r ) ) as $c ) {
		imagefilledellipse( $im, $c[0], $c[1], $r * 2, $r * 2, $col );
	}
}

/** 記事の写真（アイキャッチ→本文の写真の順）のファイル。 */
function apprex_ig_photos( $post ) {
	$files = array();
	$tid   = get_post_thumbnail_id( $post );
	if ( $tid ) {
		$files[] = get_attached_file( $tid );
	}
	foreach ( get_attached_media( 'image', $post->ID ) as $a ) {
		if ( (int) $a->ID !== (int) $tid ) {
			$files[] = get_attached_file( $a->ID );
		}
	}
	return array_values( array_filter( $files ) );
}

/**
 * スライドを作って保存する。
 *
 * @param WP_Post $post 記事。
 * @param array   $copy apprex_ig_copy() の結果。
 * @return array|WP_Error 画像のURL（1枚目→6枚目）。
 */
/**
 * デザインのパターン。
 *
 * bg＝ポイントの地の色／ink＝見出し／sub＝本文／a1＝番号・アクセント／a2＝線・ラベル／cover＝表紙の形（photo・split・type）／cbg＝表紙の地の色／dark＝ポイントが暗い地か。
 */
function apprex_ig_patterns() {
	return array(
		'navy'    => array( 'label' => 'ネイビー（写真＋白い文字）', 'bg' => '#f5f7fb', 'ink' => '#0b1f44', 'sub' => '#334155', 'a1' => '#1d4ed8', 'a2' => '#f97316', 'cover' => 'photo', 'cbg' => '#0b1f44', 'dark' => false ),
		'white'   => array( 'label' => 'ホワイト（上に写真・下に黒い文字）', 'bg' => '#ffffff', 'ink' => '#111827', 'sub' => '#374151', 'a1' => '#e60012', 'a2' => '#111827', 'cover' => 'split', 'cbg' => '#ffffff', 'dark' => false ),
		'pop'     => array( 'label' => 'ポップ（黄色・文字だけの表紙）', 'bg' => '#fffbeb', 'ink' => '#1f2937', 'sub' => '#374151', 'a1' => '#f97316', 'a2' => '#d97706', 'cover' => 'type', 'cbg' => '#facc15', 'dark' => false ),
		'natural' => array( 'label' => 'ナチュラル（ベージュ・グリーン）', 'bg' => '#f6f1e9', 'ink' => '#3b352c', 'sub' => '#57514a', 'a1' => '#4d7c57', 'a2' => '#c49a5a', 'cover' => 'split', 'cbg' => '#f6f1e9', 'dark' => false ),
		'red'     => array( 'label' => '★インパクト赤（大きな数字）', 'bg' => '#ffffff', 'ink' => '#111111', 'sub' => '#333333', 'a1' => '#e60012', 'a2' => '#ffd400', 'cover' => 'bignum', 'cbg' => '#e60012', 'dark' => false, 'pt' => 'bignum' ),
		'ticker'  => array( 'label' => '★黒×黄色（帯の見出し）', 'bg' => '#ffd400', 'ink' => '#111111', 'sub' => '#222222', 'a1' => '#111111', 'a2' => '#111111', 'cover' => 'ticker', 'cbg' => '#111111', 'dark' => false, 'pt' => 'band' ),
		'diagonal' => array( 'label' => '★ななめ（写真＋色の帯）', 'bg' => '#ffffff', 'ink' => '#0b1f44', 'sub' => '#334155', 'a1' => '#ff3b30', 'a2' => '#0b1f44', 'cover' => 'diagonal', 'cbg' => '#0b1f44', 'dark' => false, 'pt' => 'band' ),
		'marker'  => array( 'label' => '★蛍光ペン（白×マーカー）', 'bg' => '#ffffff', 'ink' => '#111111', 'sub' => '#374151', 'a1' => '#ff2d87', 'a2' => '#fff200', 'cover' => 'marker', 'cbg' => '#ffffff', 'dark' => false, 'pt' => 'marker' ),
		'dark'    => array( 'label' => 'ダーク（黒×ゴールド・上に写真）', 'bg' => '#111111', 'ink' => '#ffffff', 'sub' => '#d1d5db', 'a1' => '#c9a227', 'a2' => '#c9a227', 'cover' => 'split', 'cbg' => '#111111', 'dark' => true ),
	);
}

/** この記事のパターン（記事で選んだもの → 設定 → 順番）。 */
function apprex_ig_pattern_for( $post_id ) {
	$all = apprex_ig_patterns();
	$p   = (string) get_post_meta( $post_id, '_apprex_ig_pattern', true );
	if ( isset( $all[ $p ] ) ) {
		return $p;
	}
	$set = (string) get_option( 'apprex_ig_pattern', 'block9' );
	if ( isset( $all[ $set ] ) ) {
		return $set;
	}
	// 順番に使う（記事ごとに決めて、作り直しても同じにする）。
	if ( 'block9' === $set ) {
		$b = apprex_ig_block9();
		update_option( 'apprex_ig_pat_b', $b['i'] + 1, false );
		update_post_meta( $post_id, '_apprex_ig_pattern', $b['key'] );
		return $b['key'];
	}
	// インパクト系だけ／全部を、1投稿ずつ。
	$keys = 'rotate' === $set ? array_keys( $all ) : array_keys( array_filter( $all, function ( $x ) {
		return ! empty( $x['pt'] );
	} ) );
	$i    = (int) get_option( 'apprex_ig_pat_i', 0 );
	update_option( 'apprex_ig_pat_i', $i + 1, false );
	$p = $keys[ $i % count( $keys ) ];
	update_post_meta( $post_id, '_apprex_ig_pattern', $p );
	return $p;
}

/** 9投稿ずつ同じデザインの順番（インパクト系から）。 */
function apprex_ig_block9_order() {
	$all   = apprex_ig_patterns();
	$order = array( 'red', 'ticker', 'diagonal', 'marker', 'navy', 'white', 'pop', 'natural', 'dark' );
	return array_values( array_intersect( $order, array_keys( $all ) ) );
}

/**
 * 9投稿ずつ：いまのデザインと、あと何投稿か。
 *
 * @return array key（次に使うデザイン）・i（これまでの数）・left（このデザインであと何投稿）・next（その次のデザイン）。
 */
function apprex_ig_block9() {
	$order = apprex_ig_block9_order();
	$i     = (int) get_option( 'apprex_ig_pat_b', 0 );
	$blk   = intdiv( $i, 9 );
	return array(
		'key'  => $order[ $blk % count( $order ) ],
		'i'    => $i,
		'left' => 9 - $i % 9,
		'next' => $order[ ( $blk + 1 ) % count( $order ) ],
	);
}

function apprex_ig_render( $post, $copy ) {
	if ( ! apprex_ig_can_draw() ) {
		return new WP_Error( 'ig_draw', 'このサーバーでは画像に文字を入れられません（GD・FreeType が必要）。' );
	}
	$W      = 1080;
	$H      = 1350;
	$bold   = apprex_ig_font( true );
	$med    = apprex_ig_font( false );
	$photos = apprex_ig_photos( $post );
	$up     = wp_upload_dir();
	$dir    = trailingslashit( $up['basedir'] ) . 'apprex-ig/' . $post->ID;
	wp_mkdir_p( $dir );
	foreach ( array_merge( (array) glob( $dir . '/*.jpg' ), (array) glob( $dir . '/*.mp4' ), (array) glob( $dir . '/*.webm' ) ) as $old ) {
		wp_delete_file( $old );
	}
	$ver   = wp_generate_password( 6, false );
	$urls  = array();
	$total = count( $copy['points'] ) + 2;
	$pk    = apprex_ig_pattern_for( $post->ID );
	$pal   = apprex_ig_patterns()[ $pk ];
	$navy  = $pal['ink'];
	$blue  = $pal['a1'];
	$orng  = $pal['a2'];

	$save = function ( $im, $n ) use ( $dir, $up, $post, $ver, &$urls ) {
		$f = $dir . '/' . $n . '-' . $ver . '.jpg';
		imagejpeg( $im, $f, 88 );
		imagedestroy( $im );
		$urls[] = trailingslashit( $up['baseurl'] ) . 'apprex-ig/' . $post->ID . '/' . $n . '-' . $ver . '.jpg';
	};
	// 文字の幅に合わせた白い角丸のラベル（複数の色の文字を並べる）。
	$pill = function ( $im, $x, $y, $parts, $size, $bg = '#ffffff' ) use ( $bold ) {
		$w = 0;
		foreach ( $parts as $pt ) {
			$b  = imagettfbbox( $size, 0, $bold, $pt[0] );
			$w += $b[2] - $b[0] + 14;
		}
		$h = (int) ( $size * 2.4 );
		apprex_ig_round( $im, $x, $y, $w + 46, $h, (int) ( $h / 2 ), apprex_ig_color( $im, $bg ) );
		$cx = $x + 30;
		foreach ( $parts as $pt ) {
			imagettftext( $im, $size, 0, $cx, (int) ( $y + $h / 2 + $size * 0.42 ), apprex_ig_color( $im, $pt[1] ), $bold, $pt[0] );
			$b   = imagettfbbox( $size, 0, $bold, $pt[0] );
			$cx += $b[2] - $b[0] + 14;
		}
	};
	$footer = function ( $im, $n, $dark ) use ( $W, $H, $med, $bold, $total, $pal ) {
		$c = apprex_ig_color( $im, $dark ? '#ffffff' : $pal['ink'], $dark ? 20 : 0 );
		imagettftext( $im, 26, 0, 72, $H - 64, $c, $bold, 'APPREX' );
		imagettftext( $im, 22, 0, 232, $H - 66, apprex_ig_color( $im, $dark ? '#ffffff' : '#64748b', $dark ? 40 : 0 ), $med, 'ノーコードアプリ開発' );
		$t   = $n . ' / ' . $total;
		$box = imagettfbbox( 24, 0, $med, $t );
		imagettftext( $im, 24, 0, $W - 72 - ( $box[2] - $box[0] ), $H - 64, apprex_ig_color( $im, $dark ? '#ffffff' : '#64748b', $dark ? 40 : 0 ), $med, $t );
	};

	// 1枚目：表紙（パターンで形が変わる）。
	$im = imagecreatetruecolor( $W, $H );
	imagealphablending( $im, true );
	imagefilledrectangle( $im, 0, 0, $W, $H, apprex_ig_color( $im, $pal['cbg'] ) );
	$photo0 = isset( $photos[0] ) ? $photos[0] : '';
	if ( 'split' === $pal['cover'] ) {
		// 上に写真、下に地の色と大きな見出し。
		$ph = 760;
		if ( ! apprex_ig_cover( $im, $photo0, 0, 0, $W, $ph ) ) {
			imagefilledrectangle( $im, 0, 0, $W, $ph, apprex_ig_color( $im, $blue ) );
			imagefilledellipse( $im, 860, 220, 700, 700, apprex_ig_color( $im, '#ffffff', 100 ) );
		}
		apprex_ig_shade( $im, 0, 260, 80, 127 );
		$pill( $im, 72, 72, array( array( 'APPREX', $blue ), array( '保存版', $pal['dark'] ? '#111111' : $navy ) ), 26 );
		imagefilledrectangle( $im, 72, $ph + 64, 172, $ph + 74, apprex_ig_color( $im, $blue ) );
		$lines = apprex_ig_wrap( $copy['cover'], 64, $bold, $W - 144, 3 );
		apprex_ig_text( $im, $lines, 64, $bold, 72, $ph + 96, apprex_ig_color( $im, $navy ), 1.38 );
		imagettftext( $im, 28, 0, 72, $H - 120, apprex_ig_color( $im, $pal['sub'] ), $med, '→ スワイプして読む' );
		$footer( $im, 1, false );
	} elseif ( 'bignum' === $pal['cover'] ) {
		// 大きな数字（見出しの数字。なければポイントの数）。
		$big = preg_match( '/[0-9０-９]+/u', $copy['cover'], $mm ) ? mb_convert_kana( $mm[0], 'n' ) : (string) count( $copy['points'] );
		$bsz = strlen( $big ) > 1 ? 420 : 560;
		$bb  = imagettfbbox( $bsz, 0, $bold, $big );
		imagettftext( $im, $bsz, 0, $W - 60 - ( $bb[2] - $bb[0] ), 40 + $bsz, apprex_ig_color( $im, '#ffffff', 18 ), $bold, $big );
		imagefilledrectangle( $im, 0, $H - 470, $W, $H, apprex_ig_color( $im, '#111111', 0 ) );
		$lines = apprex_ig_wrap( $copy['cover'], 72, $bold, $W - 144, 3 );
		$y     = $H - 430;
		apprex_ig_text( $im, $lines, 72, $bold, 72, $y, apprex_ig_color( $im, '#ffffff' ), 1.32 );
		// ななめのステッカー「保存版」。
		imagefilledellipse( $im, 200, 210, 250, 250, apprex_ig_color( $im, $orng ) );
		imagettftext( $im, 50, 14, 112, 248, apprex_ig_color( $im, '#111111' ), $bold, '保存版' );
		imagettftext( $im, 28, 0, $W - 360, $H - 120, apprex_ig_color( $im, '#ffffff', 30 ), $bold, 'スワイプして読む →' );
		$footer( $im, 1, true );
	} elseif ( 'ticker' === $pal['cover'] ) {
		// 黒の地に、黄色の帯の見出し。
		for ( $k = -2; $k < 14; $k++ ) {
			imagefilledpolygon( $im, array( $k * 140, 0, $k * 140 + 60, 0, $k * 140 - 340, 400, $k * 140 - 400, 400 ), apprex_ig_color( $im, '#ffd400', 112 ) );
		}
		$pill( $im, 72, 96, array( array( 'APPREX', '#111111' ), array( '保存版', '#e60012' ) ), 26, '#ffd400' );
		$lines = apprex_ig_wrap( $copy['cover'], 78, $bold, $W - 200, 4 );
		$y     = (int) max( 330, ( $H - count( $lines ) * ( 78 * 1.55 + 18 ) ) / 2 );
		$y     = apprex_ig_band_text( $im, $lines, 78, $bold, 94, $y, apprex_ig_color( $im, '#111111' ), apprex_ig_color( $im, '#ffd400' ) );
		imagettftext( $im, 30, 0, 72, $H - 130, apprex_ig_color( $im, '#ffd400' ), $bold, 'スワイプして読む →' );
		$footer( $im, 1, true );
	} elseif ( 'diagonal' === $pal['cover'] ) {
		// 写真の上に、ななめの色の帯と見出し。
		if ( ! apprex_ig_cover( $im, $photo0, 0, 0, $W, $H ) ) {
			imagefilledellipse( $im, 860, 260, 900, 900, apprex_ig_color( $im, $blue, 60 ) );
		}
		apprex_ig_shade( $im, 0, 300, 70, 127 );
		imagefilledpolygon( $im, array( 0, 640, $W, 500, $W, 540, 0, 680 ), apprex_ig_color( $im, $blue ) );
		imagefilledpolygon( $im, array( 0, 680, $W, 540, $W, $H, 0, $H ), apprex_ig_color( $im, $pal['cbg'] ) );
		$pill( $im, 72, 80, array( array( 'APPREX', $pal['cbg'] ), array( '保存版', $blue ) ), 26 );
		$lines = apprex_ig_wrap( $copy['cover'], 76, $bold, $W - 144, 3 );
		apprex_ig_text( $im, $lines, 76, $bold, 72, 760, apprex_ig_color( $im, '#ffffff' ), 1.34 );
		imagettftext( $im, 28, 0, 72, $H - 120, apprex_ig_color( $im, '#ffffff', 25 ), $bold, 'スワイプして読む →' );
		$footer( $im, 1, true );
	} elseif ( 'marker' === $pal['cover'] ) {
		// 白い地に、蛍光ペンの見出し。
		if ( apprex_ig_cover( $im, $photo0, 0, 0, $W, 470 ) ) {
			apprex_ig_shade( $im, 0, 220, 80, 127 );
		} else {
			imagefilledrectangle( $im, 0, 0, $W, 470, apprex_ig_color( $im, $blue ) );
		}
		$pill( $im, 72, 72, array( array( 'APPREX', '#111111' ), array( '保存版', $blue ) ), 26 );
		imagefilledellipse( $im, $W - 170, 470, 230, 230, apprex_ig_color( $im, $blue ) );
		imagettftext( $im, 42, -12, $W - 248, 460, apprex_ig_color( $im, '#ffffff' ), $bold, '必見!' );
		$lines = apprex_ig_wrap( $copy['cover'], 80, $bold, $W - 144, 3 );
		apprex_ig_marker_text( $im, $lines, 80, $bold, 72, 640, apprex_ig_color( $im, '#111111' ), apprex_ig_color( $im, $orng ), 1.42 );
		imagettftext( $im, 28, 0, 72, $H - 120, apprex_ig_color( $im, $pal['sub'] ), $bold, 'スワイプして読む →' );
		$footer( $im, 1, false );
	} elseif ( 'type' === $pal['cover'] ) {
		// 文字だけ。地の色に大きな見出し。
		imagefilledellipse( $im, 960, 140, 640, 640, apprex_ig_color( $im, '#ffffff', 80 ) );
		imagefilledellipse( $im, 80, 1240, 520, 520, apprex_ig_color( $im, $blue, 96 ) );
		$pill( $im, 72, 96, array( array( 'APPREX', $blue ), array( '保存版', $navy ) ), 26 );
		$lines = apprex_ig_wrap( $copy['cover'], 86, $bold, $W - 144, 4 );
		$y     = (int) max( 260, ( $H - count( $lines ) * 86 * 1.36 ) / 2 - 40 );
		imagefilledrectangle( $im, 72, $y - 34, 220, $y - 20, apprex_ig_color( $im, $navy ) );
		apprex_ig_text( $im, $lines, 86, $bold, 72, $y, apprex_ig_color( $im, $navy ), 1.36 );
		apprex_ig_round( $im, 72, $H - 190, 420, 74, 37, apprex_ig_color( $im, $navy ) );
		imagettftext( $im, 28, 0, 112, $H - 142, apprex_ig_color( $im, '#ffffff' ), $bold, 'スワイプして読む →' );
		$footer( $im, 1, false );
	} else {
		// 写真いっぱい＋白い大きな見出し。
		if ( ! apprex_ig_cover( $im, $photo0, 0, 0, $W, $H ) ) {
			imagefilledellipse( $im, 900, 260, 900, 900, apprex_ig_color( $im, $blue, 70 ) );
			imagefilledellipse( $im, 120, 1200, 700, 700, apprex_ig_color( $im, $orng, 95 ) );
		}
		apprex_ig_shade( $im, 0, 360, 70, 127 );
		apprex_ig_shade( $im, 520, $H, 127, 18, $pal['dark'] ? '#000000' : '#0b1220' );
		$pill( $im, 72, 80, array( array( 'APPREX', $pal['dark'] ? '#111111' : $blue ), array( '保存版', $pal['dark'] ? $blue : $orng ) ), 26 );
		$lines = apprex_ig_wrap( $copy['cover'], 74, $bold, $W - 144, 3 );
		$y     = $H - 210 - count( $lines ) * 74 * 1.4;
		imagefilledrectangle( $im, 72, (int) $y - 26, 172, (int) $y - 16, apprex_ig_color( $im, $orng ) );
		apprex_ig_text( $im, $lines, 74, $bold, 72, (int) $y, apprex_ig_color( $im, '#ffffff' ), 1.4 );
		imagettftext( $im, 28, 0, 72, $H - 120, apprex_ig_color( $im, '#ffffff', 25 ), $med, '→ スワイプして読む' );
		$footer( $im, 1, true );
	}
	$save( $im, 1 );

	// 2〜5枚目：ポイント（偶数枚目は上に写真）。
	foreach ( $copy['points'] as $i => $p ) {
		$n  = $i + 2;
		$im = imagecreatetruecolor( $W, $H );
		imagealphablending( $im, true );
		imagefilledrectangle( $im, 0, 0, $W, $H, apprex_ig_color( $im, $pal['bg'] ) );
		$mode = $pal['pt'] ?? 'std';
		if ( 'std' !== $mode ) {
			$num = sprintf( '%02d', $i + 1 );
			$hl  = apprex_ig_wrap( $p['h'], 68, $bold, $W - 160, 2 );
			if ( 'bignum' === $mode ) {
				// 大きな数字を背景に。
				$bb = imagettfbbox( 400, 0, $bold, $num );
				imagettftext( $im, 400, 0, $W - 40 - ( $bb[2] - $bb[0] ), 440, apprex_ig_color( $im, $blue, 100 ), $bold, $num );
				imagettftext( $im, 34, 0, 72, 200, apprex_ig_color( $im, $blue ), $bold, 'POINT ' . $num );
				imagefilledrectangle( $im, 72, 226, 220, 234, apprex_ig_color( $im, $blue ) );
				$y = apprex_ig_marker_text( $im, $hl, 68, $bold, 72, 470, apprex_ig_color( $im, $navy ), apprex_ig_color( $im, $orng ), 1.42 );
				$tl = apprex_ig_wrap( $p['t'], 48, $med, $W - 144, 8 );
				$y  = apprex_ig_text( $im, $tl, 48, $med, 72, $y + 56, apprex_ig_color( $im, $pal['sub'] ), 1.75 );
				// 下に大きく「→ 次へ」。
				if ( $i < count( $copy['points'] ) - 1 ) {
					apprex_ig_round( $im, 72, $H - 230, 380, 80, 40, apprex_ig_color( $im, $blue ) );
					imagettftext( $im, 32, 0, 112, $H - 178, apprex_ig_color( $im, '#ffffff' ), $bold, '次のポイント →' );
				}
			} elseif ( 'band' === $mode ) {
				// 上に色の帯「POINT 01」、写真、見出し。
				imagefilledrectangle( $im, 0, 0, $W, 210, apprex_ig_color( $im, $blue ) );
				imagettftext( $im, 84, 0, 72, 150, apprex_ig_color( $im, 'ticker' === $pk ? '#ffd400' : '#ffffff' ), $bold, 'POINT ' . $num );
				$top   = 210;
				$photo = isset( $photos[ 1 + $i ] ) ? $photos[ 1 + $i ] : ( isset( $photos[0] ) ? $photos[0] : '' );
				if ( $photo && apprex_ig_cover( $im, $photo, 0, 210, $W, 420 ) ) {
					$top = 630;
				}
				$y = 'ticker' === $pk
					? apprex_ig_band_text( $im, $hl, 64, $bold, 94, $top + 60, apprex_ig_color( $im, '#ffd400' ), apprex_ig_color( $im, '#111111' ), 14 ) - 10
					: apprex_ig_text( $im, $hl, 66, $bold, 72, $top + 50, apprex_ig_color( $im, $navy ), 1.35 );
				imagefilledrectangle( $im, 72, $y + 18, 200, $y + 28, apprex_ig_color( $im, 'ticker' === $pk ? '#111111' : $blue ) );
				$tl = apprex_ig_wrap( $p['t'], 40, $med, $W - 144, $top > 300 ? 5 : 9 );
				apprex_ig_text( $im, $tl, 40, $med, 72, $y + 58, apprex_ig_color( $im, $pal['sub'] ), 1.7 );
			} else {
				// 蛍光ペン：写真、番号、マーカーの見出し。
				$top   = 140;
				$photo = isset( $photos[ 1 + $i ] ) ? $photos[ 1 + $i ] : ( isset( $photos[0] ) && 1 === $i % 2 ? $photos[0] : '' );
				if ( $photo && apprex_ig_cover( $im, $photo, 0, 0, $W, 480 ) ) {
					$top = 540;
				}
				apprex_ig_round( $im, 72, $top, 250, 70, 35, apprex_ig_color( $im, $blue ) );
				imagettftext( $im, 32, 0, 104, $top + 48, apprex_ig_color( $im, '#ffffff' ), $bold, 'POINT ' . $num );
				$y  = apprex_ig_marker_text( $im, $hl, 68, $bold, 72, $top + 110, apprex_ig_color( $im, $navy ), apprex_ig_color( $im, $orng ), 1.42 );
				$tl = apprex_ig_wrap( $p['t'], 42, $med, $W - 144, $top > 300 ? 6 : 9 );
				apprex_ig_text( $im, $tl, 42, $med, 72, $y + 50, apprex_ig_color( $im, $pal['sub'] ), 1.75 );
			}
			$footer( $im, $n, $pal['dark'] );
			$save( $im, $n );
			continue;
		}
		$top   = 120;
		$photo = isset( $photos[ 1 + $i ] ) ? $photos[ 1 + $i ] : ( isset( $photos[0] ) && 1 === $i % 2 ? $photos[0] : '' );
		if ( $photo && apprex_ig_cover( $im, $photo, 0, 0, $W, 520 ) ) {
			apprex_ig_shade( $im, 380, 520, 127, 0, $pal['bg'] );
			$top = 470;
		} else {
			imagefilledellipse( $im, $W - 60, 80, 420, 420, apprex_ig_color( $im, $blue, 112 ) );
		}
		// 番号。
		imagefilledellipse( $im, 132, $top + 60, 120, 120, apprex_ig_color( $im, $blue ) );
		$num = sprintf( '%02d', $i + 1 );
		$box = imagettfbbox( 40, 0, $bold, $num );
		imagettftext( $im, 40, 0, (int) ( 132 - ( $box[2] - $box[0] ) / 2 ), $top + 80, apprex_ig_color( $im, $pal['dark'] ? '#111111' : '#ffffff' ), $bold, $num );
		imagettftext( $im, 26, 0, 220, $top + 74, apprex_ig_color( $im, $orng ), $bold, 'POINT' );
		$hl = apprex_ig_wrap( $p['h'], 64, $bold, $W - 144, 2 );
		$tl = apprex_ig_wrap( $p['t'], 42, $med, $W - 144, $top > 200 ? 6 : 9 );
		// 見出し＋本文のかたまりを、残りの高さの中ほどに置く。
		$blk = count( $hl ) * 64 * 1.35 + 70 + count( $tl ) * 42 * 1.75;
		$y   = max( $top + 150, (int) ( $top + 150 + ( ( $H - 180 ) - ( $top + 150 ) - $blk ) * 0.35 ) );
		$y   = apprex_ig_text( $im, $hl, 64, $bold, 72, $y, apprex_ig_color( $im, $navy ), 1.35 );
		imagefilledrectangle( $im, 72, $y + 18, 160, $y + 26, apprex_ig_color( $im, $orng ) );
		apprex_ig_text( $im, $tl, 42, $med, 72, $y + 60, apprex_ig_color( $im, $pal['sub'] ), 1.75 );
		$footer( $im, $n, $pal['dark'] );
		$save( $im, $n );
	}

	// 最後：LINEへの案内。
	$im = imagecreatetruecolor( $W, $H );
	imagealphablending( $im, true );
	imagefilledrectangle( $im, 0, 0, $W, $H, apprex_ig_color( $im, '#06c755' ) );
	imagefilledellipse( $im, 980, 160, 760, 760, apprex_ig_color( $im, '#ffffff', 112 ) );
	imagefilledellipse( $im, 60, 1260, 640, 640, apprex_ig_color( $im, '#048a3c', 60 ) );
	$pill( $im, 72, 196, array( array( 'LINE友だち限定・無料', '#048a3c' ) ), 28 );
	$y = apprex_ig_text( $im, array( 'あなたのお店の', 'アプリ画面を、', 'AIで作ってみる。' ), 80, $bold, 72, 330, apprex_ig_color( $im, '#ffffff' ), 1.35 );
	$y = apprex_ig_text( $im, apprex_ig_wrap( '業種と店名を入れるだけで、写真入りのアプリ画面ができます。アプリ集客の記事・動画や、無料相談の予約もLINEで。', 36, $med, $W - 144 ), 36, $med, 72, $y + 50, apprex_ig_color( $im, '#ffffff', 10 ), 1.7 );
	apprex_ig_round( $im, 72, $y + 70, $W - 144, 120, 60, apprex_ig_color( $im, '#ffffff' ) );
	$t   = 'プロフィールのリンクから';
	$box = imagettfbbox( 40, 0, $bold, $t );
	imagettftext( $im, 40, 0, (int) ( ( $W - ( $box[2] - $box[0] ) ) / 2 ), $y + 148, apprex_ig_color( $im, '#048a3c' ), $bold, $t );
	imagettftext( $im, 28, 0, 72, $y + 260, apprex_ig_color( $im, '#ffffff', 20 ), $med, '保存・シェアもよろしくお願いします' );
	$footer( $im, $total, true );
	$save( $im, $total );

	return $urls;
}

/* =========================================================================
 * 3) リール動画（6枚をつないだ約18秒の縦動画）
 * ====================================================================== */

/** サーバーで動画を作れるか（ffmpeg）。 */
function apprex_ig_ffmpeg() {
	static $bin = null;
	if ( null !== $bin ) {
		return $bin;
	}
	$bin = '';
	if ( ! function_exists( 'exec' ) || false !== stripos( (string) ini_get( 'disable_functions' ), 'exec' ) ) {
		return $bin;
	}
	foreach ( array( 'ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg' ) as $b ) {
		$out = array();
		$rc  = 1;
		@exec( escapeshellcmd( $b ) . ' -version 2>&1', $out, $rc ); // phpcs:ignore
		if ( 0 === $rc ) {
			$bin = $b;
			break;
		}
	}
	$bin = (string) apply_filters( 'apprex_ig_ffmpeg', $bin );
	return $bin;
}

/**
 * スライド画像から縦動画（1080×1920）を作る（サーバー）。
 *
 * @return string|WP_Error 動画のURL。
 */
function apprex_ig_make_video( $post_id, $urls ) {
	$ff = apprex_ig_ffmpeg();
	if ( '' === $ff ) {
		return new WP_Error( 'ig_video', 'サーバーで動画を作れません。管理画面の「リール動画を作る」をお使いください。' );
	}
	$up    = wp_upload_dir();
	$base  = trailingslashit( $up['basedir'] );
	$files = array();
	foreach ( $urls as $u ) {
		$files[] = $base . ltrim( str_replace( trailingslashit( $up['baseurl'] ), '', $u ), '/' );
	}
	$out  = $base . 'apprex-ig/' . $post_id . '/reel-' . wp_generate_password( 6, false ) . '.mp4';
	$args = '';
	$fc   = '';
	foreach ( $files as $i => $f ) {
		$args .= ' -i ' . escapeshellarg( $f );
		// 縦長（9:16）の真ん中に置き、ゆっくり寄る。
		$fc .= '[' . $i . ':v]scale=1080:1350,zoompan=z=\'min(zoom+0.0008,1.06)\':d=96:s=1080x1350:fps=30,pad=1080:1920:0:285:color=0x0b1f44,setsar=1,format=yuv420p[v' . $i . '];';
	}
	$n   = count( $files );
	$fc .= implode( '', array_map( function ( $i ) {
		return '[v' . $i . ']';
	}, range( 0, $n - 1 ) ) ) . 'concat=n=' . $n . ':v=1:a=0[v]';
	$cmd = escapeshellcmd( $ff ) . ' -y' . $args . ' -f lavfi -t ' . ( $n * 3.2 ) . ' -i anullsrc=r=44100:cl=stereo -filter_complex ' . escapeshellarg( $fc ) . ' -map "[v]" -map ' . $n . ':a -c:v libx264 -preset veryfast -crf 23 -pix_fmt yuv420p -c:a aac -shortest -movflags +faststart ' . escapeshellarg( $out ) . ' 2>&1';
	$o   = array();
	$rc  = 1;
	@exec( $cmd, $o, $rc ); // phpcs:ignore
	if ( 0 !== $rc || ! file_exists( $out ) ) {
		return new WP_Error( 'ig_video', '動画を作れませんでした。' );
	}
	return trailingslashit( $up['baseurl'] ) . ltrim( str_replace( $base, '', $out ), '/' );
}

/* =========================================================================
 * 4) Instagram へ投稿（Graph API）
 * ====================================================================== */

function apprex_ig_api( $path, $body = null ) {
	$args = array( 'timeout' => 40 );
	if ( null === $body ) {
		$r = wp_remote_get( APPREX_GRAPH . $path, $args );
	} else {
		$args['body'] = $body;
		$r            = wp_remote_post( APPREX_GRAPH . $path, $args );
	}
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$b = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $b ) || isset( $b['error'] ) ) {
		return new WP_Error( 'ig_api', 'Instagram：' . ( isset( $b['error']['message'] ) ? $b['error']['message'] : 'HTTP ' . wp_remote_retrieve_response_code( $r ) ) );
	}
	return $b;
}

/** 作った入れ物（コンテナ）の準備ができるのを待つ。 */
function apprex_ig_wait( $id, $tok, $tries = 20 ) {
	for ( $i = 0; $i < $tries; $i++ ) {
		$s = apprex_ig_api( rawurlencode( $id ) . '?fields=status_code&access_token=' . rawurlencode( $tok ) );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		if ( 'FINISHED' === ( isset( $s['status_code'] ) ? $s['status_code'] : '' ) ) {
			return true;
		}
		if ( 'ERROR' === ( isset( $s['status_code'] ) ? $s['status_code'] : '' ) ) {
			return new WP_Error( 'ig_api', 'Instagram側で処理に失敗しました。' );
		}
		sleep( 6 );
	}
	return new WP_Error( 'ig_api', 'Instagram側の処理が時間内に終わりませんでした。' );
}

/** キャプション＋ハッシュタグ。 */
function apprex_ig_caption( $copy ) {
	return mb_substr( trim( $copy['caption'] ) . "\n\n" . implode( ' ', $copy['tags'] ), 0, 2150 );
}

/** カルーセル（複数枚）を投稿。 */
function apprex_ig_post_carousel( $urls, $caption ) {
	$ig  = get_option( 'apprex_ig_user_id' );
	$tok = get_option( 'apprex_ig_token' );
	$ids = array();
	foreach ( $urls as $u ) {
		$c = apprex_ig_api( rawurlencode( $ig ) . '/media', array( 'image_url' => $u, 'is_carousel_item' => 'true', 'access_token' => $tok ) );
		if ( is_wp_error( $c ) ) {
			return $c;
		}
		$ids[] = $c['id'];
	}
	$box = apprex_ig_api( rawurlencode( $ig ) . '/media', array( 'media_type' => 'CAROUSEL', 'children' => implode( ',', $ids ), 'caption' => $caption, 'access_token' => $tok ) );
	if ( is_wp_error( $box ) ) {
		return $box;
	}
	$w = apprex_ig_wait( $box['id'], $tok, 10 );
	if ( is_wp_error( $w ) ) {
		return $w;
	}
	$p = apprex_ig_api( rawurlencode( $ig ) . '/media_publish', array( 'creation_id' => $box['id'], 'access_token' => $tok ) );
	return is_wp_error( $p ) ? $p : $p['id'];
}

/** リールを投稿。 */
function apprex_ig_post_reel( $video_url, $caption, $cover_url = '' ) {
	$ig   = get_option( 'apprex_ig_user_id' );
	$tok  = get_option( 'apprex_ig_token' );
	$body = array( 'media_type' => 'REELS', 'video_url' => $video_url, 'caption' => $caption, 'share_to_feed' => 'true', 'access_token' => $tok );
	if ( $cover_url ) {
		$body['cover_url'] = $cover_url;
	}
	$box = apprex_ig_api( rawurlencode( $ig ) . '/media', $body );
	if ( is_wp_error( $box ) ) {
		return $box;
	}
	$w = apprex_ig_wait( $box['id'], $tok, 30 );
	if ( is_wp_error( $w ) ) {
		return $w;
	}
	$p = apprex_ig_api( rawurlencode( $ig ) . '/media_publish', array( 'creation_id' => $box['id'], 'access_token' => $tok ) );
	return is_wp_error( $p ) ? $p : $p['id'];
}

/* =========================================================================
 * 5) まとめ：記事 → 文章 → 画像（→ 動画）→ 投稿
 * ====================================================================== */

/**
 * インスタ用の文章と画像を作る（投稿はしない）。
 *
 * @return array|WP_Error { copy, urls }
 */
function apprex_ig_prepare( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'ig', '記事が見つかりません。' );
	}
	$copy = apprex_ig_copy( $post );
	if ( is_wp_error( $copy ) ) {
		return $copy;
	}
	$urls = apprex_ig_render( $post, $copy );
	if ( is_wp_error( $urls ) ) {
		return $urls;
	}
	update_post_meta( $post_id, '_apprex_ig_copy', $copy );
	update_post_meta( $post_id, '_apprex_ig_slides', $urls );
	delete_post_meta( $post_id, '_apprex_ig_video' );
	return array( 'copy' => $copy, 'urls' => $urls );
}

/** 今日インスタに投稿した数（1日の上限のため）。 */
function apprex_ig_today_count( $add = 0 ) {
	$d = get_option( 'apprex_ig_today', array( 'd' => '', 'n' => 0 ) );
	if ( ! is_array( $d ) || wp_date( 'Ymd' ) !== ( isset( $d['d'] ) ? $d['d'] : '' ) ) {
		$d = array( 'd' => wp_date( 'Ymd' ), 'n' => 0 );
	}
	if ( $add ) {
		$d['n'] += $add;
		update_option( 'apprex_ig_today', $d, false );
	}
	return (int) $d['n'];
}

/**
 * 記事をインスタに投稿（カルーセル、できればリールも）。
 *
 * @param int  $post_id 記事。
 * @param bool $reel    リールも投稿するか。
 * @return array|WP_Error { carousel, reel }
 */
function apprex_ig_publish( $post_id, $reel = null ) {
	if ( ! function_exists( 'apprex_ig_ready' ) || ! apprex_ig_ready() ) {
		return new WP_Error( 'ig', 'Instagramが未設定です（設定 → APPREX 配信(SNS)）。' );
	}
	$slides = get_post_meta( $post_id, '_apprex_ig_slides', true );
	$copy   = get_post_meta( $post_id, '_apprex_ig_copy', true );
	if ( ! is_array( $slides ) || ! $slides || ! is_array( $copy ) ) {
		$r = apprex_ig_prepare( $post_id );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$slides = $r['urls'];
		$copy   = $r['copy'];
	}
	$caption = apprex_ig_caption( $copy );
	$out     = array();
	$c       = apprex_ig_post_carousel( $slides, $caption );
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$out['carousel'] = $c;
	do_action( 'apprex_ig_published', $post_id, 'carousel', $c );
	update_post_meta( $post_id, 'apprex_ig_sent', current_time( 'mysql' ) );
	apprex_ig_today_count( 1 );

	if ( null === $reel ) {
		$reel = 'on' === get_option( 'apprex_ig_reel', 'on' );
	}
	if ( $reel ) {
		$video = get_post_meta( $post_id, '_apprex_ig_video', true );
		if ( ! $video ) {
			$video = apprex_ig_make_video( $post_id, $slides );
		}
		if ( ! is_wp_error( $video ) && $video ) {
			update_post_meta( $post_id, '_apprex_ig_video', $video );
			$rr = apprex_ig_post_reel( $video, $caption, $slides[0] );
			$out['reel'] = is_wp_error( $rr ) ? $rr->get_error_message() : $rr;
			if ( ! is_wp_error( $rr ) ) {
				update_post_meta( $post_id, 'apprex_ig_reel_sent', current_time( 'mysql' ) );
				do_action( 'apprex_ig_published', $post_id, 'reel', $rr );
			}
		} else {
			$out['reel'] = is_wp_error( $video ) ? $video->get_error_message() : '';
		}
	}
	return $out;
}

/* 記事の公開 → 2分後に自動で作って投稿（時間のかかる処理を公開ボタンから切り離す）。 */
add_action(
	'apprex_ig_auto',
	function ( $post_id ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( get_post_meta( $post_id, 'apprex_ig_sent', true ) || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}
		if ( apprex_ig_today_count() >= max( 1, (int) get_option( 'apprex_ig_per_day', 2 ) ) ) {
			update_post_meta( $post_id, '_apprex_ig_error', '今日の投稿数の上限のため、投稿しませんでした。' );
			return;
		}
		$r = apprex_ig_publish( $post_id );
		if ( is_wp_error( $r ) ) {
			update_post_meta( $post_id, '_apprex_ig_error', $r->get_error_message() );
		} else {
			delete_post_meta( $post_id, '_apprex_ig_error' );
			if ( isset( $r['reel'] ) && ! ctype_digit( (string) $r['reel'] ) && '' !== $r['reel'] ) {
				update_post_meta( $post_id, '_apprex_ig_error', 'リール：' . $r['reel'] );
			}
		}
	}
);

/** 記事の公開時に、カルーセルで投稿するか（今までの1枚の投稿の代わり）。 */
function apprex_ig_use_carousel() {
	return 'off' !== get_option( 'apprex_ig_carousel', 'on' ) && apprex_ig_can_draw();
}

/* =========================================================================
 * 6) 管理画面：記事の「インスタ」ボックス
 * ====================================================================== */
add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'apprex_ig_box', 'インスタグラム（画像・リール）', 'apprex_ig_box', 'post', 'normal', 'default' );
	}
);

function apprex_ig_box( $post ) {
	$slides = get_post_meta( $post->ID, '_apprex_ig_slides', true );
	$copy   = get_post_meta( $post->ID, '_apprex_ig_copy', true );
	$video  = get_post_meta( $post->ID, '_apprex_ig_video', true );
	$err    = get_post_meta( $post->ID, '_apprex_ig_error', true );
	$sent   = get_post_meta( $post->ID, 'apprex_ig_sent', true );
	$rsent  = get_post_meta( $post->ID, 'apprex_ig_reel_sent', true );
	$act    = function ( $do, $label, $primary = false, $confirm = '' ) use ( $post ) {
		$u = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_ig&do=' . $do . '&post=' . $post->ID ), 'apprex_ig_' . $post->ID );
		return '<a class="button' . ( $primary ? ' button-primary' : '' ) . '" href="' . esc_url( $u ) . '"' . ( $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\')"' : '' ) . '>' . esc_html( $label ) . '</a> ';
	};
	echo '<p>記事から、インスタ用の画像6枚（カルーセル）とキャプションをAIで作ります。' . ( apprex_ig_ready() ? '' : '<b style="color:#b45309">Instagramの連携が未設定です（設定 → APPREX 配信(SNS)）。画像の作成・確認はできます。</b>' ) . '</p>';
	if ( $err ) {
		echo '<p style="color:#b91c1c">' . esc_html( $err ) . '</p>';
	}
	if ( $sent ) {
		echo '<p style="color:#15803d">カルーセルを投稿しました（' . esc_html( $sent ) . '）' . ( $rsent ? '／リールも投稿しました（' . esc_html( $rsent ) . '）' : '' ) . '</p>';
	}
	if ( is_array( $slides ) && $slides ) {
		echo '<div style="display:flex;gap:8px;overflow-x:auto;padding:6px 0">';
		foreach ( $slides as $u ) {
			echo '<a href="' . esc_url( $u ) . '" target="_blank"><img src="' . esc_url( $u ) . '" style="width:150px;height:auto;border-radius:6px;border:1px solid #ddd" alt=""></a>';
		}
		echo '</div>';
		if ( is_array( $copy ) ) {
			echo '<details><summary>キャプションを見る</summary><pre style="white-space:pre-wrap;background:#f6f7f7;padding:10px;border-radius:6px">' . esc_html( apprex_ig_caption( $copy ) ) . '</pre></details>';
		}
		if ( $video ) {
			echo '<p>リール動画：<a href="' . esc_url( $video ) . '" target="_blank">見る</a>／<a href="' . esc_url( $video ) . '" download>ダウンロード</a></p>';
		}
	}
	// デザインのパターンを選んで作り直す。
	$cur  = (string) get_post_meta( $post->ID, '_apprex_ig_pattern', true );
	$base = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_ig&do=make&post=' . $post->ID ), 'apprex_ig_' . $post->ID );
	echo '<p style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">デザイン：<select id="apIgPat">';
	foreach ( apprex_ig_patterns() as $k => $pt ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $pt['label'] ) . '</option>';
	}
	echo '</select> <a class="button" href="#" onclick="location.href=' . esc_attr( wp_json_encode( $base ) ) . '+\'&pat=\'+document.getElementById(\'apIgPat\').value;return false;">このデザインで作り直す</a>';
	echo '<span style="display:inline-flex;gap:3px;margin-left:4px">';
	foreach ( apprex_ig_patterns() as $k => $pt ) {
		echo '<span title="' . esc_attr( $pt['label'] ) . '" style="width:18px;height:18px;border-radius:4px;border:1px solid #ccc;background:linear-gradient(135deg,' . esc_attr( $pt['cbg'] ) . ' 50%,' . esc_attr( $pt['a1'] ) . ' 50%)"></span>';
	}
	echo '</span></p>';
	echo '<p>';
	echo $act( 'make', is_array( $slides ) && $slides ? '画像を作り直す' : 'インスタ用の画像を作る', ! $slides ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( is_array( $slides ) && $slides && apprex_ig_ready() ) {
		echo $act( 'post', 'カルーセルを投稿', true, 'インスタに今すぐ投稿します。よろしいですか？' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</p>';
	if ( is_array( $slides ) && $slides ) {
		$ff = '' !== apprex_ig_ffmpeg();
		if ( $ff ) {
			echo '<p>' . $act( 'reel', 'リール動画を作って投稿', false, 'リール動画を作ってインスタに投稿します（1〜3分）。よろしいですか？' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			// サーバーで動画を作れない：ブラウザで作ってアップロード。
			echo '<p><button type="button" class="button" id="apIgReel" data-slides="' . esc_attr( wp_json_encode( $slides ) ) . '" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '" data-rest="' . esc_url( rest_url( 'apprex/v1/ig-video' ) ) . '">リール動画を作る（このブラウザで約20秒）</button> <span id="apIgReelMsg" class="description"></span></p>';
			echo '<script>' . apprex_ig_browser_video_js() . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}

/** ブラウザで、スライド画像から動画を作ってアップロードする（サーバーに ffmpeg がないとき）。 */
function apprex_ig_browser_video_js() {
	return <<<'JS'
(function(){var b=document.getElementById('apIgReel');if(!b)return;var m=document.getElementById('apIgReelMsg');
b.addEventListener('click',function(){
 var types=['video/mp4;codecs=avc1.640028','video/mp4;codecs=avc1.4D401F','video/mp4;codecs=avc1.42E01E','video/mp4','video/webm;codecs=vp9','video/webm'];var type=types.find(function(t){return window.MediaRecorder&&MediaRecorder.isTypeSupported(t);});
 if(!type){m.textContent='このブラウザでは動画を作れません（Chrome か Safari の新しい版でお試しください）。';return;}
 b.disabled=true;m.textContent='画像を読み込んでいます…';
 var urls=JSON.parse(b.dataset.slides);var imgs=urls.map(function(u){var i=new Image();i.crossOrigin='anonymous';i.src=u;return i;});
 Promise.all(imgs.map(function(i){return new Promise(function(r,j){i.onload=r;i.onerror=j;});})).then(function(){
  var c=document.createElement('canvas');c.width=1080;c.height=1920;var x=c.getContext('2d');var st=c.captureStream(30);var rec=new MediaRecorder(st,{mimeType:type,videoBitsPerSecond:3500000});var parts=[];
  rec.ondataavailable=function(e){if(e.data.size)parts.push(e.data);};
  var per=3200,t0=performance.now(),n=imgs.length;m.textContent='動画を作っています…';
  rec.onstop=function(){var blob=new Blob(parts,{type:type.split(';')[0]});m.textContent='アップロードしています…';
   var fd=new FormData();fd.append('post',b.dataset.post);fd.append('h264',type.indexOf('avc1')>-1?'1':'0');fd.append('video',blob,'reel.'+(type.indexOf('mp4')>-1?'mp4':'webm'));
   fetch(b.dataset.rest,{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':b.dataset.nonce},body:fd}).then(function(r){return r.json();}).then(function(r){m.textContent=r&&r.ok?r.message:((r&&r.message)||'アップロードできませんでした。');b.disabled=false;if(r&&r.ok){setTimeout(function(){location.reload();},1500);}}).catch(function(){m.textContent='アップロードできませんでした。';b.disabled=false;});};
  rec.start(500);
  (function draw(){var t=performance.now()-t0,k=Math.min(n-1,Math.floor(t/per)),p=(t-k*per)/per;x.fillStyle='#0b1f44';x.fillRect(0,0,1080,1920);
   var z=1+0.06*p,w=1080*z,h=1350*z;x.globalAlpha=Math.min(1,p*4);x.drawImage(imgs[k],(1080-w)/2,285+(1350-h)/2,w,h);x.globalAlpha=1;
   if(t<per*n){requestAnimationFrame(draw);}else{rec.stop();}})();
 }).catch(function(){m.textContent='画像を読み込めませんでした。';b.disabled=false;});
});})();
JS;
}

/* ブラウザで作った動画を受け取る → 保存（→ MP4 ならリールで投稿）。 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'apprex/v1',
			'/ig-video',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$pid   = absint( $req->get_param( 'post' ) );
					$files = $req->get_file_params();
					if ( ! $pid || ! current_user_can( 'edit_post', $pid ) || empty( $files['video'] ) ) {
						return new WP_REST_Response( array( 'ok' => false, 'message' => '動画を受け取れませんでした。' ), 200 );
					}
					if ( ! empty( $files['video']['error'] ) || empty( $files['video']['tmp_name'] ) ) {
						$big = in_array( (int) $files['video']['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
						return new WP_REST_Response( array( 'ok' => false, 'message' => $big ? '動画が大きすぎてアップロードできませんでした（サーバーのアップロード上限：' . size_format( wp_max_upload_size() ) . '）。' : '動画を受け取れませんでした。' ), 200 );
					}
					$mp4  = false !== strpos( (string) $files['video']['type'], 'mp4' );
					$h264 = '1' === (string) $req->get_param( 'h264' ); // インスタが受け付けるのは H.264 の動画。
					$up  = wp_upload_dir();
					$dir = trailingslashit( $up['basedir'] ) . 'apprex-ig/' . $pid;
					wp_mkdir_p( $dir );
					$name = 'reel-' . wp_generate_password( 6, false ) . ( $mp4 ? '.mp4' : '.webm' );
					if ( ! @move_uploaded_file( $files['video']['tmp_name'], $dir . '/' . $name ) ) { // phpcs:ignore
						return new WP_REST_Response( array( 'ok' => false, 'message' => '動画を保存できませんでした。' ), 200 );
					}
					$url = trailingslashit( $up['baseurl'] ) . 'apprex-ig/' . $pid . '/' . $name;
					update_post_meta( $pid, '_apprex_ig_video', $url );
					if ( ! $mp4 || ! $h264 ) {
						return new WP_REST_Response( array( 'ok' => true, 'message' => '動画を保存しました。このブラウザの動画の形式はインスタが受け付けないため、自動投稿はしていません（パソコンのChromeかSafariの新しい版で作り直すか、動画をダウンロードしてインスタのアプリから投稿してください）。' ), 200 );
					}
					if ( ! apprex_ig_ready() ) {
						return new WP_REST_Response( array( 'ok' => true, 'message' => '動画を保存しました（Instagram未設定のため投稿はしていません）。' ), 200 );
					}
					$copy = get_post_meta( $pid, '_apprex_ig_copy', true );
					$sl   = get_post_meta( $pid, '_apprex_ig_slides', true );
					if ( function_exists( 'set_time_limit' ) ) {
						@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					}
					$r = apprex_ig_post_reel( $url, is_array( $copy ) ? apprex_ig_caption( $copy ) : get_the_title( $pid ), is_array( $sl ) ? $sl[0] : '' );
					if ( is_wp_error( $r ) ) {
						return new WP_REST_Response( array( 'ok' => false, 'message' => '動画は保存しました。リールの投稿に失敗：' . $r->get_error_message() ), 200 );
					}
					update_post_meta( $pid, 'apprex_ig_reel_sent', current_time( 'mysql' ) );
					return new WP_REST_Response( array( 'ok' => true, 'message' => 'リールを投稿しました。' ), 200 );
				},
			)
		);
	}
);

add_action(
	'admin_post_apprex_ig',
	function () {
		$pid = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$do  = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) || ! check_admin_referer( 'apprex_ig_' . $pid ) ) {
			wp_die( '権限がありません。' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$err = '';
		if ( 'make' === $do ) {
			$pat = isset( $_GET['pat'] ) ? sanitize_key( wp_unslash( $_GET['pat'] ) ) : '';
			if ( isset( apprex_ig_patterns()[ $pat ] ) ) {
				update_post_meta( $pid, '_apprex_ig_pattern', $pat );
			}
			$r   = apprex_ig_prepare( $pid );
			$err = is_wp_error( $r ) ? $r->get_error_message() : '';
		} elseif ( 'post' === $do ) {
			$r   = apprex_ig_publish( $pid, false );
			$err = is_wp_error( $r ) ? $r->get_error_message() : '';
		} elseif ( 'reel' === $do ) {
			$sl = get_post_meta( $pid, '_apprex_ig_slides', true );
			$v  = is_array( $sl ) ? apprex_ig_make_video( $pid, $sl ) : new WP_Error( 'ig', '先に画像を作ってください。' );
			if ( is_wp_error( $v ) ) {
				$err = $v->get_error_message();
			} else {
				update_post_meta( $pid, '_apprex_ig_video', $v );
				if ( apprex_ig_ready() ) {
					$copy = get_post_meta( $pid, '_apprex_ig_copy', true );
					$r    = apprex_ig_post_reel( $v, is_array( $copy ) ? apprex_ig_caption( $copy ) : get_the_title( $pid ), $sl[0] );
					$err  = is_wp_error( $r ) ? 'リール：' . $r->get_error_message() : '';
					if ( ! $err ) {
						update_post_meta( $pid, 'apprex_ig_reel_sent', current_time( 'mysql' ) );
					}
				}
			}
		}
		if ( $err ) {
			update_post_meta( $pid, '_apprex_ig_error', $err );
		} else {
			delete_post_meta( $pid, '_apprex_ig_error' );
		}
		wp_safe_redirect( get_edit_post_link( $pid, 'raw' ) . '#apprex_ig_box' );
		exit;
	}
);

// 9投稿ずつ：次のデザインに切り替える。
add_action(
	'admin_post_apprex_ig_b9_next',
	function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'apprex_ig_b9_next' ) ) {
			wp_die( '権限がありません。' );
		}
		$i = (int) get_option( 'apprex_ig_pat_b', 0 );
		update_option( 'apprex_ig_pat_b', ( intdiv( $i, 9 ) + 1 ) * 9, false );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
);
