<?php
/**
 * ミーティング予約（オンライン相談）をサイトの中で受け付ける。
 *
 * - 空き枠：設定（曜日・時間・何日先まで・休みの日）から作り、予約済みの枠は出さない。
 * - 予約：お問い合わせ（種類「ミーティング予約」）として保存。管理画面の「お問い合わせ → ミーティング予約」で一覧。
 * - 確認・リマインド：メール（前日・1時間前・翌日のお礼）。LINEから予約した方にはLINEにも送る。
 * - 予約ページ：/meeting/（固定ページ）と、LINEの中で開く /line-seminar/meeting（説明会のLIFFを使う）。
 * - AIスタジオのミーティング予約も、同じ空き枠を使う。
 *
 * 設定：お問い合わせ → ミーティング予約。「サイトで予約を受け付ける」を外すと、これまでの Google カレンダーの予約ページに戻る。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** サイトで予約を受け付けるか。 */
function apprex_mb_enabled() {
	return (bool) get_option( 'apprex_mb_enabled', 1 );
}

/** 予約の設定。 */
function apprex_mb_settings() {
	$o = get_option( 'apprex_mb_settings', array() );
	$o = is_array( $o ) ? $o : array();
	return array_merge(
		array(
			'days'     => array( 1, 2, 3, 4, 5 ),
			'times'    => '10:00,11:00,13:00,14:00,15:00,16:00,17:00',
			'min'      => 60,
			'ahead'    => 21,
			'lead'     => 12,
			'holidays' => '',
			'url'      => '',
			'cap'      => 1,
		),
		$o
	);
}

/** 時間枠（HH:MM の配列）。 */
function apprex_mb_times() {
	$out = array();
	foreach ( preg_split( '/[\s,、]+/u', (string) apprex_mb_settings()['times'] ) as $t ) {
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $t ), $m ) && (int) $m[1] < 24 && (int) $m[2] < 60 ) {
			$out[] = sprintf( '%02d:%02d', $m[1], $m[2] );
		}
	}
	$out = array_values( array_unique( $out ) );
	sort( $out );
	return $out;
}

/** 予約済みの数（開始時刻 => 件数）。キャンセルは数えない。 */
function apprex_mb_booked( $from, $to ) {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => 'apprex_type', 'value' => 'meeting' ),
				array( 'key' => 'apprex_meeting_at', 'value' => array( $from, $to ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
		)
	);
	$out = array();
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, 'apprex_mb_cancel', true ) || 'trash' === get_post_status( $id ) ) {
			continue;
		}
		$at         = (int) get_post_meta( $id, 'apprex_meeting_at', true );
		$out[ $at ] = isset( $out[ $at ] ) ? $out[ $at ] + 1 : 1;
	}
	return $out;
}

/** 日付の表示（例：11月5日(水)）。 */
function apprex_mb_day_label( $ts ) {
	$wd = array( '日', '月', '火', '水', '木', '金', '土' );
	return wp_date( 'n月j日', $ts ) . '(' . $wd[ (int) wp_date( 'w', $ts ) ] . ')';
}

/** 日時の表示（例：2026年11月5日(水) 14:00〜15:00）。 */
function apprex_mb_when( $ts ) {
	$s = apprex_mb_settings();
	return wp_date( 'Y年', $ts ) . apprex_mb_day_label( $ts ) . ' ' . wp_date( 'H:i', $ts ) . '〜' . wp_date( 'H:i', $ts + (int) $s['min'] * 60 );
}

/**
 * これからの空き枠。
 *
 * @return array [ { date, label, times: [ { t, at, ok } ] } ]
 */
function apprex_mb_slots() {
	$s     = apprex_mb_settings();
	$tz    = wp_timezone();
	$now   = time();
	$first = $now + (int) $s['lead'] * HOUR_IN_SECONDS;
	$times = apprex_mb_times();
	$hol   = array_filter( array_map( 'trim', preg_split( '/[\s,]+/', (string) $s['holidays'] ) ) );
	$days  = array_map( 'intval', (array) $s['days'] );
	$cap   = max( 1, (int) $s['cap'] );
	$book  = apprex_mb_booked( $now, $now + ( (int) $s['ahead'] + 2 ) * DAY_IN_SECONDS );
	$out   = array();
	for ( $i = 0; $i <= (int) $s['ahead']; $i++ ) {
		$d    = ( new DateTimeImmutable( 'today', $tz ) )->modify( '+' . $i . ' day' );
		$date = $d->format( 'Y-m-d' );
		if ( ! in_array( (int) $d->format( 'w' ), $days, true ) || in_array( $date, $hol, true ) ) {
			continue;
		}
		$list = array();
		foreach ( $times as $t ) {
			$at = ( new DateTimeImmutable( $date . ' ' . $t, $tz ) )->getTimestamp();
			if ( $at < $first ) {
				continue;
			}
			$list[] = array(
				't'  => $t,
				'at' => $at,
				'ok' => ( isset( $book[ $at ] ) ? $book[ $at ] : 0 ) < $cap,
			);
		}
		if ( $list ) {
			$out[] = array(
				'date'  => $date,
				'label' => apprex_mb_day_label( $d->getTimestamp() ),
				'times' => $list,
			);
		}
	}
	return $out;
}

/** その時刻が予約できるか。 */
function apprex_mb_is_open( $at ) {
	foreach ( apprex_mb_slots() as $d ) {
		foreach ( $d['times'] as $t ) {
			if ( (int) $t['at'] === (int) $at ) {
				return $t['ok'];
			}
		}
	}
	return false;
}

/** 予約ページのURL。 */
function apprex_mb_page_url() {
	return function_exists( 'apprex_page_url' ) ? apprex_page_url( 'meeting' ) : home_url( '/meeting/' );
}

/** LINEの中で開く予約ページ（説明会のLIFFに /meeting を付ける）。LIFF未設定なら空。 */
function apprex_mb_liff_url() {
	$id = function_exists( 'apprex_seminar_liff_id' ) ? apprex_seminar_liff_id() : '';
	return '' !== $id ? 'https://liff.line.me/' . rawurlencode( $id ) . '/meeting' : '';
}

/* サイトで受け付けるときは、各所の「ミーティング予約」のリンク先をサイトの予約ページに。 */
add_filter( 'apprex_meeting_url', function ( $url ) {
	return apprex_mb_enabled() ? apprex_mb_page_url() : $url;
} );

/* =========================================================================
 * REST：空き枠・予約
 * ====================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/meeting/slots',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				return rest_ensure_response( array( 'ok' => true, 'days' => apprex_mb_slots() ) );
			},
		)
	);
	register_rest_route(
		'apprex/v1',
		'/meeting/book',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'apprex_mb_rest_book',
		)
	);
} );

/** 予約を受け付ける。 */
function apprex_mb_rest_book( WP_REST_Request $req ) {
	$fail  = function ( $m ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => $m ), 200 );
	};
	$nonce = $req->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return $fail( 'ページの有効期限が切れました。開き直してください。' );
	}
	$at = (int) $req->get_param( 'at' );
	if ( ! apprex_mb_is_open( $at ) ) {
		return $fail( 'その時間はご予約が入りました。ほかの時間をお選びください。' );
	}
	$uid = '';
	if ( function_exists( 'apprex_seminar_verify_idt' ) ) {
		list( $uid ) = apprex_seminar_verify_idt( (string) $req->get_param( 'id_token' ) );
	}
	// これまでのお問い合わせと同じ流れ（自動返信・通知・連携・計測）で保存する。
	$inq = new WP_REST_Request( 'POST', '/apprex/v1/inquiry' );
	$inq->set_header( 'x_wp_nonce', wp_create_nonce( 'wp_rest' ) );
	$inq->set_body_params(
		array(
			'type'       => 'meeting',
			'name'       => (string) $req->get_param( 'name' ),
			'company'    => (string) $req->get_param( 'company' ),
			'email'      => (string) $req->get_param( 'email' ),
			'phone'      => (string) $req->get_param( 'phone' ),
			'message'    => (string) $req->get_param( 'message' ),
			'meeting_at' => wp_date( 'Y-m-d\TH:i', $at ),
		)
	);
	$res = apprex_rest_inquiry( $inq );
	if ( is_wp_error( $res ) ) {
		return $fail( $res->get_error_message() );
	}
	$data = $res->get_data();
	$id   = isset( $data['lead']['event_id'] ) ? (int) str_replace( 'lead_', '', $data['lead']['event_id'] ) : 0;
	$line = false;
	if ( $id && '' !== $uid ) {
		update_post_meta( $id, 'apprex_line_uid', $uid );
		$line = apprex_mb_line( $id, 'confirm' );
	}
	return new WP_REST_Response(
		array(
			'ok'   => true,
			'when' => apprex_mb_when( $at ),
			'at'   => $at,
			'min'  => (int) apprex_mb_settings()['min'],
			'url'  => (string) apprex_mb_settings()['url'],
			'line' => $line,
		),
		200
	);
}

/** LINEで送る（予約の確認・前日・1時間前・お礼）。 */
function apprex_mb_line( $id, $key ) {
	$uid = (string) get_post_meta( $id, 'apprex_line_uid', true );
	if ( '' === $uid || ! function_exists( 'apprex_line_push' ) ) {
		return false;
	}
	$at   = (int) get_post_meta( $id, 'apprex_meeting_at', true );
	$name = (string) get_post_meta( $id, 'apprex_name', true );
	$url  = (string) apprex_mb_settings()['url'];
	$when = apprex_mb_when( $at );
	$u    = $url ? "\n▼ 参加URL（Google Meet）\n" . $url : "\n参加URLは、メールでお送りします。";
	$msgs = array(
		'confirm'   => "{$name} 様\n\nオンライン相談のご予約ありがとうございます。\n\n■ {$when}\n■ オンライン（Google Meet）{$u}\n\n前日と1時間前にも、このLINEでお知らせします。",
		'before_1d' => "{$name} 様\n\n明日はオンライン相談のお約束です。\n■ {$when}{$u}",
		'before_1h' => "{$name} 様\n\nまもなくオンライン相談のお時間です（{$when}）。{$u}",
		'after_1d'  => "{$name} 様\n\n先日はお時間をいただき、ありがとうございました。正式なお見積りは担当者からお送りします。ご不明な点は、このLINEでお気軽にどうぞ。",
	);
	if ( empty( $msgs[ $key ] ) ) {
		return false;
	}
	$r = apprex_line_push( $uid, array( array( 'type' => 'text', 'text' => $msgs[ $key ] ) ) );
	return ! is_wp_error( $r );
}

/* =========================================================================
 * 管理画面：一覧と設定（お問い合わせ → ミーティング予約）
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=apprex_inquiry', 'ミーティング予約', 'ミーティング予約', 'edit_posts', 'apprex-meeting', 'apprex_mb_admin_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_mb', 'apprex_mb_enabled', array( 'sanitize_callback' => 'absint' ) );
	register_setting(
		'apprex_mb',
		'apprex_mb_settings',
		array(
			'sanitize_callback' => function ( $v ) {
				$v = (array) $v;
				return array(
					'days'     => array_values( array_map( 'intval', isset( $v['days'] ) ? (array) $v['days'] : array() ) ),
					'times'    => sanitize_text_field( isset( $v['times'] ) ? $v['times'] : '' ),
					'min'      => max( 15, (int) ( isset( $v['min'] ) ? $v['min'] : 60 ) ),
					'ahead'    => min( 90, max( 1, (int) ( isset( $v['ahead'] ) ? $v['ahead'] : 21 ) ) ),
					'lead'     => max( 0, (int) ( isset( $v['lead'] ) ? $v['lead'] : 12 ) ),
					'holidays' => sanitize_textarea_field( isset( $v['holidays'] ) ? $v['holidays'] : '' ),
					'url'      => esc_url_raw( isset( $v['url'] ) ? $v['url'] : '' ),
					'cap'      => max( 1, (int) ( isset( $v['cap'] ) ? $v['cap'] : 1 ) ),
				);
			},
		)
	);
} );

function apprex_mb_admin_page() {
	$s   = apprex_mb_settings();
	$wd  = array( '日', '月', '火', '水', '木', '金', '土' );
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_meeting_at',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => 'apprex_type', 'value' => 'meeting' ),
				array( 'key' => 'apprex_meeting_at', 'value' => time() - DAY_IN_SECONDS, 'compare' => '>', 'type' => 'NUMERIC' ),
			),
		)
	);
	?>
	<div class="wrap">
		<h1>ミーティング予約</h1>
		<p>予約ページ：<a href="<?php echo esc_url( apprex_mb_page_url() ); ?>" target="_blank" rel="noopener"><?php echo esc_html( apprex_mb_page_url() ); ?></a>
		<?php if ( apprex_mb_liff_url() ) : ?>／LINEの中で開く：<code><?php echo esc_html( apprex_mb_liff_url() ); ?></code><?php endif; ?></p>

		<h2>これからの予約</h2>
		<?php if ( ! $ids ) : ?>
			<p>まだ予約はありません。</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th>日時</th><th>お名前</th><th>会社名</th><th>連絡</th><th>内容</th></tr></thead><tbody>
			<?php foreach ( $ids as $id ) : ?>
				<?php $c = get_post_meta( $id, 'apprex_mb_cancel', true ); ?>
				<tr<?php echo $c ? ' style="opacity:.5"' : ''; ?>>
					<td><b><?php echo esc_html( apprex_mb_when( (int) get_post_meta( $id, 'apprex_meeting_at', true ) ) ); ?></b><?php echo $c ? '（キャンセル）' : ''; ?></td>
					<td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_post_meta( $id, 'apprex_name', true ) ); ?></a></td>
					<td><?php echo esc_html( get_post_meta( $id, 'apprex_company', true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $id, 'apprex_email', true ) ); ?><?php echo get_post_meta( $id, 'apprex_line_uid', true ) ? '・LINE' : ''; ?></td>
					<td style="max-width:320px"><?php echo esc_html( mb_substr( (string) get_post_meta( $id, 'apprex_message', true ), 0, 60 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2 style="margin-top:28px">受け付けの設定</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_mb' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>受け付け方</th><td><label><input type="checkbox" name="apprex_mb_enabled" value="1" <?php checked( apprex_mb_enabled() ); ?>> サイトで予約を受け付ける（管理画面・メール・LINEに届きます）</label>
					<p class="description">外すと、これまでの Google カレンダーの予約ページ（APPREX 連携の「ミーティング予約URL」）に戻ります。</p></td></tr>
				<tr><th>受け付ける曜日</th><td><?php foreach ( $wd as $i => $w ) : ?><label style="margin-right:10px"><input type="checkbox" name="apprex_mb_settings[days][]" value="<?php echo (int) $i; ?>" <?php checked( in_array( $i, array_map( 'intval', (array) $s['days'] ), true ) ); ?>> <?php echo esc_html( $w ); ?></label><?php endforeach; ?></td></tr>
				<tr><th>時間</th><td><input type="text" name="apprex_mb_settings[times]" value="<?php echo esc_attr( $s['times'] ); ?>" class="regular-text"><p class="description">始まりの時刻をカンマで区切ります（例：10:00,11:00,13:00,14:00）。</p></td></tr>
				<tr><th>1回の時間</th><td><input type="number" name="apprex_mb_settings[min]" value="<?php echo esc_attr( $s['min'] ); ?>" min="15" step="5" style="width:80px"> 分</td></tr>
				<tr><th>予約できる期間</th><td>今から <input type="number" name="apprex_mb_settings[lead]" value="<?php echo esc_attr( $s['lead'] ); ?>" min="0" style="width:70px"> 時間後〜 <input type="number" name="apprex_mb_settings[ahead]" value="<?php echo esc_attr( $s['ahead'] ); ?>" min="1" max="90" style="width:70px"> 日先まで</td></tr>
				<tr><th>同じ時間に受ける数</th><td><input type="number" name="apprex_mb_settings[cap]" value="<?php echo esc_attr( $s['cap'] ); ?>" min="1" style="width:70px"> 件</td></tr>
				<tr><th>お休みの日</th><td><textarea name="apprex_mb_settings[holidays]" rows="3" class="regular-text" placeholder="2026-12-29&#10;2026-12-30"><?php echo esc_textarea( $s['holidays'] ); ?></textarea><p class="description">1行に1日（年-月-日）。</p></td></tr>
				<tr><th>参加URL（Google Meet）</th><td><input type="url" name="apprex_mb_settings[url]" value="<?php echo esc_attr( $s['url'] ); ?>" class="large-text" placeholder="https://meet.google.com/xxx-xxxx-xxx"><p class="description">いつも使う Google Meet のURL。予約の確認・前日・1時間前のお知らせに入ります。空なら「参加URLはメールでお送りします」と案内します。</p></td></tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* お問い合わせの画面：ミーティング予約ならキャンセルの切り替え。 */
add_action( 'add_meta_boxes_apprex_inquiry', function ( $post ) {
	if ( 'meeting' !== get_post_meta( $post->ID, 'apprex_type', true ) ) {
		return;
	}
	add_meta_box(
		'apprex_mb_box',
		'ミーティング予約',
		function ( $post ) {
			$at = (int) get_post_meta( $post->ID, 'apprex_meeting_at', true );
			wp_nonce_field( 'apprex_mb_box', 'apprex_mb_nonce' );
			echo '<p><b>' . esc_html( $at ? apprex_mb_when( $at ) : '日時未定' ) . '</b></p>';
			echo '<p>連絡：' . ( get_post_meta( $post->ID, 'apprex_line_uid', true ) ? 'LINE・' : '' ) . 'メール</p>';
			echo '<label><input type="checkbox" name="apprex_mb_cancel" value="1"' . checked( (bool) get_post_meta( $post->ID, 'apprex_mb_cancel', true ), true, false ) . '> キャンセル（枠を空けて、お知らせを止める）</label>';
		},
		'apprex_inquiry',
		'side'
	);
} );
add_action( 'save_post_apprex_inquiry', function ( $id ) {
	if ( ! isset( $_POST['apprex_mb_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_mb_nonce'] ), 'apprex_mb_box' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$c = empty( $_POST['apprex_mb_cancel'] ) ? 0 : 1;
	update_post_meta( $id, 'apprex_mb_cancel', $c );
	if ( $c ) {
		update_post_meta( $id, 'apprex_drip_active', 0 );
	}
} );

/* =========================================================================
 * LINEの中で開く予約ページ /line-seminar/meeting（説明会のLIFFに /meeting を付けて開く）
 * ====================================================================== */
add_action( 'init', function () {
	add_rewrite_rule( '^line-seminar/meeting/?$', 'index.php?apprex_mb_liff=1', 'top' );
	if ( '1' !== get_option( 'apprex_mb_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_mb_rw', '1' );
	}
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'apprex_mb_liff';
	return $v;
} );
add_filter( 'redirect_canonical', function ( $r ) {
	return get_query_var( 'apprex_mb_liff' ) ? false : $r;
} );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_mb_liff' ) ) {
		return;
	}
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	$liff = function_exists( 'apprex_seminar_liff_id' ) ? apprex_seminar_liff_id() : '';
	?>
<!doctype html><html lang="ja"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>オンライン相談のご予約｜APPREX</title>
<?php if ( '' !== $liff ) : ?><script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script><?php endif; ?>
<link rel="stylesheet" href="<?php echo esc_url( APPREX_URI . '/assets/css/meeting.css?v=' . APPREX_VERSION ); ?>">
<style>body{margin:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,"Hiragino Kaku Gothic ProN","Hiragino Sans",Meiryo,sans-serif;color:#0f172a}.mb-hd{background:linear-gradient(150deg,#0b1d3f,#1d4ed8);color:#fff;padding:24px 18px 50px}.mb-hd h1{font-size:21px;margin:8px 0 4px}.mb-hd p{margin:0;font-size:13.5px;opacity:.9}.mb-wrap{max-width:560px;margin:-32px auto 40px;padding:0 14px}</style>
</head><body>
<div class="mb-hd"><small style="background:rgba(255,255,255,.16);border-radius:99px;padding:2px 12px;font-size:12px">無料・オンライン（Google Meet）</small><h1>オンライン相談のご予約</h1><p>空いている日時を選んで、お名前とメールを入れるだけ。</p></div>
<div class="mb-wrap"><?php apprex_mb_booking_ui( $liff ); ?></div>
</body></html>
	<?php
	exit;
} );

/**
 * 予約の画面（日にち → 時間 → お名前・メール）。固定ページとLINEの両方で使う。
 *
 * @param string $liff LIFF ID（LINEの中で開くとき）。
 */
function apprex_mb_booking_ui( $liff = '' ) {
	static $done = false;
	$cfg = array(
		'slots' => esc_url_raw( rest_url( 'apprex/v1/meeting/slots' ) ),
		'book'  => esc_url_raw( rest_url( 'apprex/v1/meeting/book' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'liff'  => $liff,
		'line'  => function_exists( 'apprex_line_url' ) ? apprex_line_url() : '',
		'pp'    => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
	);
	?>
	<div class="mb" id="apprexMeeting" data-cfg="<?php echo esc_attr( wp_json_encode( $cfg ) ); ?>">
		<div class="mb-card">
			<div class="mb-h"><span>1</span>日にちを選ぶ</div>
			<div class="mb-days" id="mbDays"><p class="mb-note">空き枠を読み込んでいます…</p></div>
			<div class="mb-h"><span>2</span>時間を選ぶ</div>
			<div class="mb-times" id="mbTimes"><p class="mb-note">先に日にちを選んでください。</p></div>
		</div>
		<form class="mb-card" id="mbForm" novalidate>
			<div class="mb-h"><span>3</span>お名前・ご連絡先</div>
			<div class="mb-sel" id="mbSel">日時を選んでください</div>
			<div id="mbWho"></div>
			<label>お名前<em>*</em><input name="name" autocomplete="name" required></label>
			<label>会社名・屋号<small>（任意）</small><input name="company" autocomplete="organization"></label>
			<label>メールアドレス<em>*</em><input type="email" name="email" autocomplete="email" inputmode="email" required></label>
			<label>電話番号<small>（任意）</small><input type="tel" name="phone" autocomplete="tel" inputmode="tel"></label>
			<label>相談したいこと<small>（任意）</small><textarea name="message" rows="3" placeholder="例：飲食店のアプリを作りたい。予約とクーポンを入れたい"></textarea></label>
			<button class="mb-btn" type="submit" id="mbBtn" disabled>この日時で予約する</button>
			<p class="mb-err" id="mbErr" hidden></p>
			<?php if ( $cfg['pp'] ) : ?><p class="mb-note">予約すると<a href="<?php echo esc_url( $cfg['pp'] ); ?>" target="_blank" rel="noopener">プライバシーポリシー</a>に同意したものとします。</p><?php endif; ?>
		</form>
	</div>
	<?php if ( ! $done ) : ?>
		<script src="<?php echo esc_url( APPREX_URI . '/assets/js/meeting.js?v=' . APPREX_VERSION ); ?>"></script>
	<?php endif; ?>
	<?php
	$done = true;
}

/* =========================================================================
 * 前日・1時間前・お礼のお知らせ（15分ごとに確認。1時間前のお知らせが遅れないように）
 * ====================================================================== */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_mb_cron' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS * 2, 'apprex_15min', 'apprex_mb_cron' );
	}
} );
add_action( 'apprex_mb_cron', function () {
	if ( ! apprex_mb_enabled() || ! function_exists( 'apprex_run_meeting_reminders' ) ) {
		return;
	}
	$now = time();
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => 'apprex_type', 'value' => 'meeting' ),
				array( 'key' => 'apprex_drip_active', 'value' => '1' ),
				array( 'key' => 'apprex_meeting_at', 'value' => array( $now - 3 * DAY_IN_SECONDS, $now + DAY_IN_SECONDS + HOUR_IN_SECONDS ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
		)
	);
	foreach ( $ids as $id ) {
		$sent = get_post_meta( $id, 'apprex_drip_sent', true );
		apprex_run_meeting_reminders( $id, (string) get_post_meta( $id, 'apprex_email', true ), (string) get_post_meta( $id, 'apprex_name', true ), is_array( $sent ) ? $sent : array(), $now );
	}
} );
