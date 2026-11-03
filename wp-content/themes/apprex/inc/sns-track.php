<?php
/**
 * SNSの成果を測る。
 *
 * 1) 投稿ごとの専用リンク：https://サイト/go/x123（X の記事123の投稿）など。
 *    クリックを数えて、行き先に ?src=x_123 を付けて送る。サイトに来た人には、その入口を30日覚えておき、
 *    記事の中の「LINEで受け取る」ボタン（LABO）や、お問い合わせ・予約にも同じ入口を付ける。
 *    → どの投稿から、LABOを開いた・アプリ画面を作った・予約した・問い合わせたかが分かる。
 *    コード：x=X、fb=Facebook、igs=インスタのストーリー（リンクスタンプ用）、ig=インスタのプロフィール欄。
 * 2) インスタの数字：投稿した記事のカルーセル・リールの「届いた人数・保存・シェア・いいね・コメント・再生」と、
 *    フォロワー数を毎日取り込む。
 * 3) 画面：業務ナビ → SNSの成果。
 * 4) 毎週月曜：先週のまとめを社内通知（Slack・LINE WORKS）に送る。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 媒体の名前。 */
function apprex_trk_nets() {
	return array(
		'x'   => 'X',
		'fb'  => 'Facebook',
		'ig'  => 'Instagram',
		'igs' => 'インスタ ストーリー',
		'th'  => 'Threads',
	);
}

/** 投稿ごとの専用リンク。 */
function apprex_trk_url( $net, $post_id ) {
	return home_url( '/go/' . $net . (int) $post_id );
}

/** インスタのプロフィール欄に載せるリンク（LABOへ）。 */
function apprex_trk_profile_url() {
	return home_url( '/go/ig' );
}

/** 入口（src）の表示名。x_123 → 「X：記事のタイトル」。 */
function apprex_trk_src_label( $src ) {
	if ( preg_match( '/^(x|fb|ig|igs|th)_(\d+)$/', (string) $src, $m ) ) {
		$nets = apprex_trk_nets();
		return $nets[ $m[1] ] . '：' . mb_substr( get_the_title( (int) $m[2] ), 0, 30 );
	}
	$srcs = function_exists( 'apprex_labo_srcs' ) ? apprex_labo_srcs() : array();
	return isset( $srcs[ $src ] ) ? $srcs[ $src ] : (string) $src;
}

/* =========================================================================
 * /go/{コード}：数えて、送る
 * ====================================================================== */
add_action(
	'init',
	function () {
		add_rewrite_rule( '^go/([a-z]{1,4}[0-9]*)/?$', 'index.php?apprex_go=$matches[1]', 'top' );
	}
);
add_filter(
	'query_vars',
	function ( $v ) {
		$v[] = 'apprex_go';
		return $v;
	}
);
add_filter(
	'redirect_canonical',
	function ( $r ) {
		return get_query_var( 'apprex_go' ) ? false : $r;
	}
);

/** コード → [行き先URL, src]。 */
function apprex_trk_resolve( $code ) {
	if ( 'ig' === $code ) {
		return array( function_exists( 'apprex_labo_url' ) ? apprex_labo_url( 'insta_profile' ) : home_url( '/labo' ), 'insta_profile' );
	}
	if ( ! preg_match( '/^(x|fb|igs|th)(\d+)$/', $code, $m ) ) {
		return null;
	}
	$post = get_post( (int) $m[2] );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return array( home_url( '/' ), '' );
	}
	$src = $m[1] . '_' . $post->ID;
	if ( 'igs' === $m[1] && function_exists( 'apprex_labo_url' ) ) {
		return array( apprex_labo_url( $src ), $src ); // ストーリー：そのままLINEの特典へ。
	}
	return array( add_query_arg( 'src', $src, get_permalink( $post ) ), $src );
}

/** クリックを数える（ロボットは数えない）。 */
function apprex_trk_count( $code ) {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|facebot|preview|whatsapp|slack|discord|line-poker|curl|wget|python/', $ua ) ) {
		return;
	}
	$c = get_option( 'apprex_trk_clicks', array() );
	$c = is_array( $c ) ? $c : array();
	$d = wp_date( 'Ymd' );
	if ( ! isset( $c[ $code ] ) ) {
		$c[ $code ] = array( 'n' => 0, 'd' => array() );
	}
	$c[ $code ]['n']++;
	$c[ $code ]['d'][ $d ] = (int) ( $c[ $code ]['d'][ $d ] ?? 0 ) + 1;
	$c[ $code ]['d']       = array_slice( $c[ $code ]['d'], -120, null, true );
	update_option( 'apprex_trk_clicks', $c, false );
}

add_action(
	'template_redirect',
	function () {
		$code = (string) get_query_var( 'apprex_go' );
		if ( '' === $code ) {
			return;
		}
		$r = apprex_trk_resolve( sanitize_key( $code ) );
		if ( ! $r ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
		apprex_trk_count( $code );
		nocache_headers();
		if ( '' !== $r[1] ) {
			setcookie( 'apprex_src', $r[1], time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		}
		wp_redirect( $r[0], 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- LIFF（liff.line.me）へも送るため。
		exit;
	},
	1
);

/** クリック数（コード、期間の始まり Ymd）。 */
function apprex_trk_clicks( $code, $since = '' ) {
	$c = get_option( 'apprex_trk_clicks', array() );
	if ( empty( $c[ $code ] ) ) {
		return 0;
	}
	if ( '' === $since ) {
		return (int) $c[ $code ]['n'];
	}
	$n = 0;
	foreach ( (array) $c[ $code ]['d'] as $d => $v ) {
		if ( (string) $d >= $since ) {
			$n += (int) $v;
		}
	}
	return $n;
}

/* =========================================================================
 * サイトに来た人の入口を覚えて、LINEボタン・お問い合わせに付ける
 * ====================================================================== */

/* 入口を覚える／LINEボタンのリンクに入口を付ける（ページがキャッシュされていても動くよう、ブラウザで）。 */
add_action(
	'wp_footer',
	function () {
		?>
<script>(function(){try{var q=new URLSearchParams(location.search).get('src');if(q&&/^[a-z0-9_-]{2,40}$/.test(q)&&q!=='blog'&&q!=='web'){localStorage.setItem('apprex_src',q);localStorage.setItem('apprex_src_t',String(Date.now()));document.cookie='apprex_src='+q+';path=/;max-age=2592000;samesite=lax';}var s=localStorage.getItem('apprex_src'),t=+localStorage.getItem('apprex_src_t')||0;if(!s||Date.now()-t>2592000000)return;document.querySelectorAll('a[href*="liff.line.me/"],a[href*="/labo"]').forEach(function(a){try{var u=new URL(a.href,location.href),v=u.searchParams.get('src');if(!v||v==='blog'||v==='web'){u.searchParams.set('src',s);a.href=u.toString();}}catch(e){}});}catch(e){}})();</script>
		<?php
	},
	99
);

/* お問い合わせ・予約に入口を残す。 */
add_action(
	'apprex_inquiry_submitted',
	function ( $post_id ) {
		$src = isset( $_COOKIE['apprex_src'] ) ? sanitize_key( wp_unslash( $_COOKIE['apprex_src'] ) ) : '';
		if ( '' !== $src && ! get_post_meta( $post_id, 'apprex_src', true ) ) {
			update_post_meta( $post_id, 'apprex_src', $src );
		}
	},
	5
);

/* =========================================================================
 * インスタの数字を毎日取り込む
 * ====================================================================== */

/** Graph API から値を読む（values[0].value か total_value.value）。 */
function apprex_trk_ins_values( $data ) {
	$out = array();
	foreach ( (array) ( $data['data'] ?? array() ) as $m ) {
		$v = $m['values'][0]['value'] ?? ( $m['total_value']['value'] ?? null );
		if ( null !== $v && ! is_array( $v ) ) {
			$out[ $m['name'] ] = (int) $v;
		}
	}
	return $out;
}

/** 1つの投稿の数字（使えない指標があれば、少ない指標でもう一度）。 */
function apprex_trk_media_insights( $media, $reel ) {
	$tok   = get_option( 'apprex_ig_token' );
	$tries = $reel
		? array( 'reach,saved,shares,likes,comments,views,total_interactions', 'reach,saved,likes,comments' )
		: array( 'reach,saved,shares,likes,comments,views,total_interactions', 'reach,saved,likes,comments' );
	foreach ( $tries as $metrics ) {
		$r = wp_remote_get( 'https://graph.facebook.com/v23.0/' . rawurlencode( $media ) . '/insights?' . http_build_query( array( 'metric' => $metrics, 'access_token' => $tok ) ), array( 'timeout' => 20 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$b = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( is_array( $b ) && empty( $b['error'] ) ) {
			return apprex_trk_ins_values( $b );
		}
		$err = $b['error']['message'] ?? ( 'HTTP ' . wp_remote_retrieve_response_code( $r ) );
	}
	return new WP_Error( 'ig_ins', $err ?? '数字を取れませんでした' );
}

/** 毎日の取り込み。 */
function apprex_trk_daily() {
	if ( ! function_exists( 'apprex_ig_ready' ) || ! apprex_ig_ready() ) {
		return;
	}
	// フォロワー数。
	$ig  = get_option( 'apprex_ig_user_id' );
	$tok = get_option( 'apprex_ig_token' );
	$r   = wp_remote_get( 'https://graph.facebook.com/v23.0/' . rawurlencode( $ig ) . '?' . http_build_query( array( 'fields' => 'followers_count,media_count', 'access_token' => $tok ) ), array( 'timeout' => 20 ) );
	if ( ! is_wp_error( $r ) ) {
		$b = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( isset( $b['followers_count'] ) ) {
			$f                      = get_option( 'apprex_trk_followers', array() );
			$f                      = is_array( $f ) ? $f : array();
			$f[ wp_date( 'Ymd' ) ] = (int) $b['followers_count'];
			update_option( 'apprex_trk_followers', array_slice( $f, -400, null, true ), false );
		} elseif ( ! empty( $b['error']['message'] ) ) {
			update_option( 'apprex_trk_last_err', array( time(), 'フォロワー数：' . $b['error']['message'] ), false );
		}
	}
	// この45日に投稿したもの。
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 60,
			'fields'         => 'ids',
			'date_query'     => array( array( 'after' => '45 days ago' ) ),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array( 'key' => 'apprex_ig_media', 'compare' => 'EXISTS' ),
				array( 'key' => 'apprex_ig_reel_media', 'compare' => 'EXISTS' ),
			),
		)
	);
	foreach ( $ids as $id ) {
		$out = array( 't' => time() );
		foreach ( array( 'carousel' => 'apprex_ig_media', 'reel' => 'apprex_ig_reel_media' ) as $k => $meta ) {
			$m = (string) get_post_meta( $id, $meta, true );
			if ( '' === $m ) {
				continue;
			}
			$v = apprex_trk_media_insights( $m, 'reel' === $k );
			if ( is_wp_error( $v ) ) {
				update_option( 'apprex_trk_last_err', array( time(), mb_substr( get_the_title( $id ), 0, 20 ) . '：' . $v->get_error_message() ), false );
				continue;
			}
			$out[ $k ] = $v;
		}
		if ( count( $out ) > 1 ) {
			update_post_meta( $id, 'apprex_ig_ins', $out );
		}
	}
	update_option( 'apprex_trk_last_run', time(), false );
	// 月曜は先週のまとめを送る。
	if ( '1' === wp_date( 'N' ) && get_option( 'apprex_trk_week_sent' ) !== wp_date( 'oW' ) ) {
		update_option( 'apprex_trk_week_sent', wp_date( 'oW' ), false );
		apprex_trk_weekly_report();
	}
}
add_action( 'apprex_trk_cron', 'apprex_trk_daily' );
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'apprex_trk_cron' ) ) {
			// 毎朝6時台（日本時間）に取り込む。
			$next = strtotime( wp_date( 'Y-m-d' ) . ' 06:20:00 ' . wp_timezone_string() );
			wp_schedule_event( $next > time() ? $next : $next + DAY_IN_SECONDS, 'daily', 'apprex_trk_cron' );
		}
	}
);
add_filter(
	'apprex_cron_hooks',
	function ( $h ) {
		$h['apprex_trk_cron'] = 'SNSの数字の取り込み';
		return $h;
	}
);

/* インスタに投稿したときの番号を残す（ig-studio.php から）。 */
add_action(
	'apprex_ig_published',
	function ( $post_id, $kind, $media_id ) {
		update_post_meta( $post_id, 'reel' === $kind ? 'apprex_ig_reel_media' : 'apprex_ig_media', (string) $media_id );
	},
	10,
	3
);

/* =========================================================================
 * 集計
 * ====================================================================== */

/**
 * 入口（src）ごとの数：LABOを開いた・画面を作った・予約した（LINEの友だち）、問い合わせ（サイト）。
 *
 * @param int $since この時刻より後（0ならすべて）。
 * @return array src => [open, made, booked, inquiry]
 */
function apprex_trk_funnel( $since = 0 ) {
	$rows = array();
	$add  = function ( $s, $i ) use ( &$rows ) {
		if ( ! isset( $rows[ $s ] ) ) {
			$rows[ $s ] = array( 0, 0, 0, 0 );
		}
		$rows[ $s ][ $i ]++;
	};
	$fr = get_posts( array( 'post_type' => 'apprex_line_friend', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'apprex_labo_src' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $fr as $id ) {
		if ( $since && (int) get_post_meta( $id, 'apprex_labo_first', true ) < $since ) {
			continue;
		}
		$s = (string) get_post_meta( $id, 'apprex_labo_src', true );
		$add( $s, 0 );
		if ( get_post_meta( $id, 'apprex_labo_made', true ) ) {
			$add( $s, 1 );
		}
		if ( get_post_meta( $id, 'apprex_labo_booked', true ) ) {
			$add( $s, 2 );
		}
	}
	$iq = get_posts( array( 'post_type' => 'apprex_inquiry', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => 'apprex_src' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	foreach ( $iq as $id ) {
		if ( $since && (int) get_post_time( 'U', true, $id ) < $since ) {
			continue;
		}
		$add( (string) get_post_meta( $id, 'apprex_src', true ), 3 );
	}
	return $rows;
}

/** SNSに出した記事（新しい順）。 */
function apprex_trk_posts( $n = 30, $since = 0 ) {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => $n,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array( 'key' => 'apprex_ig_sent', 'compare' => 'EXISTS' ),
			array( 'key' => 'apprex_x_sent', 'compare' => 'EXISTS' ),
			array( 'key' => 'apprex_fb_sent', 'compare' => 'EXISTS' ),
		),
	);
	if ( $since ) {
		$args['date_query'] = array( array( 'after' => gmdate( 'Y-m-d H:i:s', $since ), 'column' => 'post_date_gmt' ) );
	}
	return get_posts( $args );
}

/** 1つの記事の成果。 */
function apprex_trk_post_stats( $post_id, $funnel ) {
	$ins = get_post_meta( $post_id, 'apprex_ig_ins', true );
	$ins = is_array( $ins ) ? $ins : array();
	$c   = $ins['carousel'] ?? array();
	$r   = $ins['reel'] ?? array();
	$st  = array(
		'x'       => apprex_trk_clicks( 'x' . $post_id ),
		'fb'      => apprex_trk_clicks( 'fb' . $post_id ),
		'igs'     => apprex_trk_clicks( 'igs' . $post_id ),
		'reach'   => (int) ( $c['reach'] ?? 0 ) + (int) ( $r['reach'] ?? 0 ),
		'saved'   => (int) ( $c['saved'] ?? 0 ) + (int) ( $r['saved'] ?? 0 ),
		'shares'  => (int) ( $c['shares'] ?? 0 ) + (int) ( $r['shares'] ?? 0 ),
		'likes'   => (int) ( $c['likes'] ?? 0 ) + (int) ( $r['likes'] ?? 0 ),
		'views'   => (int) ( $r['views'] ?? 0 ),
		'has_ins' => (bool) $ins,
		'open'    => 0,
		'made'    => 0,
		'booked'  => 0,
		'inquiry' => 0,
	);
	foreach ( array( 'x', 'fb', 'ig', 'igs', 'th' ) as $n ) {
		$f = $funnel[ $n . '_' . $post_id ] ?? null;
		if ( $f ) {
			$st['open']    += $f[0];
			$st['made']    += $f[1];
			$st['booked']  += $f[2];
			$st['inquiry'] += $f[3];
		}
	}
	// 成果の点数：予約・問い合わせ > 画面を作った > LABOを開いた > 保存・シェア > クリック > 届いた人数。
	$st['score'] = $st['booked'] * 100 + $st['inquiry'] * 100 + $st['made'] * 30 + $st['open'] * 15 + ( $st['saved'] + $st['shares'] ) * 3 + $st['x'] + $st['fb'] + $st['igs'] * 2 + $st['reach'] / 100;
	return $st;
}

/* =========================================================================
 * 毎週のまとめ（社内通知）
 * ====================================================================== */
function apprex_trk_weekly_report() {
	if ( ! function_exists( 'apprex_notify' ) ) {
		return false;
	}
	$since  = time() - 7 * DAY_IN_SECONDS;
	$sinced = wp_date( 'Ymd', $since );
	$funnel = apprex_trk_funnel( $since );
	$tot    = array( 0, 0, 0, 0 );
	$sns    = array( 0, 0, 0, 0 );
	foreach ( $funnel as $s => $f ) {
		foreach ( $f as $i => $n ) {
			$tot[ $i ] += $n;
			if ( preg_match( '/^(x|fb|ig|igs|th)_\d+$|^insta/', $s ) ) {
				$sns[ $i ] += $n;
			}
		}
	}
	$clicks = 0;
	foreach ( array_keys( (array) get_option( 'apprex_trk_clicks', array() ) ) as $code ) {
		$clicks += apprex_trk_clicks( $code, $sinced );
	}
	$f      = (array) get_option( 'apprex_trk_followers', array() );
	$fnow   = $f ? (int) end( $f ) : 0;
	$fprev  = 0;
	foreach ( $f as $d => $n ) {
		if ( (string) $d <= $sinced ) {
			$fprev = (int) $n;
		}
	}
	$all    = apprex_trk_funnel( 0 );
	$posts  = apprex_trk_posts( 40, time() - 30 * DAY_IN_SECONDS );
	$scored = array();
	foreach ( $posts as $p ) {
		$scored[ $p->ID ] = apprex_trk_post_stats( $p->ID, $all );
	}
	uasort(
		$scored,
		function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);
	$lines   = array( '📊 *SNSの成果（先週 ' . wp_date( 'n/j', $since ) . '〜' . wp_date( 'n/j' ) . '）*' );
	$lines[] = '・専用リンクのクリック：' . $clicks . '回';
	if ( $fnow ) {
		$lines[] = '・インスタのフォロワー：' . number_format( $fnow ) . '人（' . ( $fprev ? sprintf( '%+d', $fnow - $fprev ) : '比べる前の数字なし' ) . '）';
	}
	$lines[] = '・SNSから LABOを開いた人：' . $sns[0] . '人（画面を作った ' . $sns[1] . '・予約 ' . $sns[2] . '・問い合わせ ' . $sns[3] . '）';
	$lines[] = '・全体の LABOを開いた人：' . $tot[0] . '人（予約 ' . $tot[2] . '）';
	if ( $scored ) {
		$lines[] = "\n🏆 *この30日でよかった投稿*";
		$i       = 0;
		foreach ( $scored as $id => $s ) {
			if ( ++$i > 3 ) {
				break;
			}
			$lines[] = $i . '. ' . mb_substr( get_the_title( $id ), 0, 28 ) . '（届いた ' . $s['reach'] . '・保存 ' . $s['saved'] . '・クリック ' . ( $s['x'] + $s['fb'] + $s['igs'] ) . '・LABO ' . $s['open'] . '・予約 ' . $s['booked'] . '）';
		}
	}
	$lines[] = "\n詳しく：" . admin_url( 'admin.php?page=apprex-sns-report' );
	return apprex_notify( 'report', implode( "\n", $lines ) );
}

/* =========================================================================
 * 画面：業務ナビ → SNSの成果
 * ====================================================================== */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'apprex-nav', 'SNSの成果', 'SNSの成果', 'manage_options', 'apprex-sns-report', 'apprex_trk_page' );
	},
	20
);

add_action(
	'admin_post_apprex_trk_now',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_trk_now' );
		$what = isset( $_POST['what'] ) ? sanitize_key( $_POST['what'] ) : '';
		if ( 'report' === $what ) {
			apprex_trk_weekly_report();
		} else {
			apprex_trk_daily();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=apprex-sns-report&done=' . $what ) );
		exit;
	}
);

function apprex_trk_page() {
	$days   = isset( $_GET['days'] ) ? max( 0, (int) $_GET['days'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification
	$since  = $days ? time() - $days * DAY_IN_SECONDS : 0;
	$all    = apprex_trk_funnel( 0 );
	$posts  = apprex_trk_posts( 40, $since );
	$err    = get_option( 'apprex_trk_last_err' );
	$last   = (int) get_option( 'apprex_trk_last_run', 0 );
	$f      = (array) get_option( 'apprex_trk_followers', array() );
	$period = apprex_trk_funnel( $since );
	$sum    = array( 0, 0, 0, 0 );
	foreach ( $period as $s => $r ) {
		if ( preg_match( '/^(x|fb|ig|igs|th)_\d+$|^insta/', $s ) ) {
			foreach ( $r as $i => $n ) {
				$sum[ $i ] += $n;
			}
		}
	}
	$clicks = 0;
	foreach ( array_keys( (array) get_option( 'apprex_trk_clicks', array() ) ) as $code ) {
		$clicks += apprex_trk_clicks( $code, $since ? wp_date( 'Ymd', $since ) : '' );
	}
	$cell = function ( $n ) {
		return $n ? '<b>' . (int) $n . '</b>' : '<span style="color:#aaa">0</span>';
	};
	?>
	<div class="wrap">
		<h1>SNSの成果</h1>
		<p>SNSの投稿から、どれだけの人が <b>LINE（LABO）に来て、アプリ画面を作り、予約・問い合わせまで進んだか</b> を見る画面です。インスタの数字は毎朝取り込みます。</p>
		<?php if ( isset( $_GET['done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success inline"><p><?php echo 'report' === $_GET['done'] ? '先週のまとめを社内通知に送りました。' : 'インスタの数字を取り込みました。'; // phpcs:ignore ?></p></div>
		<?php endif; ?>
		<p>期間：
			<?php foreach ( array( 7 => '7日', 30 => '30日', 90 => '90日', 0 => 'すべて' ) as $d => $l ) : ?>
				<a class="button<?php echo $days === $d ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'days', $d ) ); ?>"><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
		</p>
		<div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 20px">
			<?php
			$cards = array(
				array( '専用リンクのクリック', $clicks . '回' ),
				array( 'インスタのフォロワー', $f ? number_format( (int) end( $f ) ) . '人' : '—' ),
				array( 'SNSから LABOを開いた', $sum[0] . '人' ),
				array( 'アプリ画面を作った', $sum[1] . '人' ),
				array( '予約した', $sum[2] . '人' ),
				array( '問い合わせた', $sum[3] . '件' ),
			);
			foreach ( $cards as $c ) {
				echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;min-width:150px"><div style="color:#646970;font-size:12px">' . esc_html( $c[0] ) . '</div><div style="font-size:22px;font-weight:700;margin-top:4px">' . esc_html( $c[1] ) . '</div></div>';
			}
			?>
		</div>

		<h2>投稿ごとの成果</h2>
		<table class="widefat striped" style="max-width:1300px">
			<thead><tr>
				<th>公開日</th><th>記事</th>
				<th title="インスタ：カルーセルとリールの合計">インスタ<br>届いた人</th><th>保存</th><th>シェア</th><th>いいね</th><th>リール<br>再生</th>
				<th>Xの<br>クリック</th><th>FBの<br>クリック</th><th>ストーリーの<br>クリック</th>
				<th style="background:#f0f6fc">LABOを<br>開いた</th><th style="background:#f0f6fc">画面を<br>作った</th><th style="background:#f0f6fc">予約</th><th style="background:#f0f6fc">問い合わせ</th>
			</tr></thead>
			<tbody>
			<?php if ( ! $posts ) : ?>
				<tr><td colspan="14">この期間に SNS に出した記事はまだありません。</td></tr>
			<?php endif; ?>
			<?php foreach ( $posts as $p ) : ?>
				<?php $s = apprex_trk_post_stats( $p->ID, $all ); ?>
				<tr>
					<td style="white-space:nowrap"><?php echo esc_html( get_the_date( 'n/j', $p ) ); ?></td>
					<td><a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>"><?php echo esc_html( mb_substr( get_the_title( $p ), 0, 34 ) ); ?></a>
						<br><small>ストーリー用リンク：<code><?php echo esc_html( apprex_trk_url( 'igs', $p->ID ) ); ?></code></small></td>
					<td><?php echo $s['has_ins'] ? $cell( $s['reach'] ) : '<span style="color:#aaa">—</span>'; // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['saved'] ); // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['shares'] ); // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['likes'] ); // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['views'] ); // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['x'] ); // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['fb'] ); // phpcs:ignore ?></td>
					<td><?php echo $cell( $s['igs'] ); // phpcs:ignore ?></td>
					<td style="background:#f6f9fc"><?php echo $cell( $s['open'] ); // phpcs:ignore ?></td>
					<td style="background:#f6f9fc"><?php echo $cell( $s['made'] ); // phpcs:ignore ?></td>
					<td style="background:#f6f9fc"><?php echo $cell( $s['booked'] ); // phpcs:ignore ?></td>
					<td style="background:#f6f9fc"><?php echo $cell( $s['inquiry'] ); // phpcs:ignore ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">青い列は、その投稿のリンクから来た人が、そのあと LINE（LABO）や問い合わせでどこまで進んだかです（最初に来た入口で数えます）。</p>

		<h2>入口ごとの成果（LINE・問い合わせ）</h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>入口</th><th>LABOを開いた</th><th>画面を作った</th><th>予約</th><th>問い合わせ</th></tr></thead>
			<tbody>
			<?php
			uasort(
				$period,
				function ( $a, $b ) {
					return $b[0] <=> $a[0];
				}
			);
			if ( ! $period ) {
				echo '<tr><td colspan="5">まだ記録がありません。</td></tr>';
			}
			foreach ( $period as $s => $r ) {
				echo '<tr><td>' . esc_html( apprex_trk_src_label( $s ) ) . '</td><td>' . $cell( $r[0] ) . '</td><td>' . $cell( $r[1] ) . '</td><td>' . $cell( $r[2] ) . '</td><td>' . $cell( $r[3] ) . '</td></tr>'; // phpcs:ignore
			}
			?>
			</tbody>
		</table>

		<h2>インスタのプロフィールに載せるリンク</h2>
		<p><code style="font-size:14px"><?php echo esc_html( apprex_trk_profile_url() ); ?></code></p>
		<p class="description">インスタのプロフィールの「リンク」をこれにすると、プロフィールから LINE（LABO）に来た人を数えられます。<br>
		ストーリーで記事を紹介するときは、上の表の「ストーリー用リンク」をリンクスタンプに貼ると、その投稿から来た人として数えます（そのままLINEの特典へ進みます）。</p>

		<h2>取り込み・まとめ</h2>
		<p>最後にインスタの数字を取り込んだ日時：<?php echo $last ? esc_html( wp_date( 'n/j G:i', $last ) ) : 'まだ'; ?>
		<?php if ( is_array( $err ) && time() - (int) $err[0] < 3 * DAY_IN_SECONDS ) : ?><br><span style="color:#b32d2e">最近のエラー：<?php echo esc_html( $err[1] ); ?></span><?php endif; ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
			<input type="hidden" name="action" value="apprex_trk_now"><input type="hidden" name="what" value="ins"><?php wp_nonce_field( 'apprex_trk_now' ); ?>
			<?php submit_button( 'インスタの数字をいま取り込む', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
			<input type="hidden" name="action" value="apprex_trk_now"><input type="hidden" name="what" value="report"><?php wp_nonce_field( 'apprex_trk_now' ); ?>
			<?php submit_button( '先週のまとめをいま送る', 'secondary', 'submit', false ); ?>
		</form>
		<p class="description">毎週月曜の朝に、先週のまとめを社内通知（Slack・LINE WORKS）に送ります。送り先は「社内への通知」の「成果のまとめ」で選べます。<br>
		X の数字（表示回数など）は、X の有料プランでないと読めないため、X は専用リンクのクリック数で測ります。</p>
	</div>
	<?php
}
