<?php
/**
 * 代理店説明会（セミナー）：LINEで予約 → 説明会で報酬・加盟金をご説明。
 *
 * - 説明会の日程（CPT apprex_seminar）… 管理画面「代理店 → 説明会」で日時・参加URL・定員を登録。
 * - 予約ページ /line-seminar … LINEのLIFFアプリとして開く。LINEログインで名前を自動入力し、
 *   日程を選ぶだけで予約。LINEの外で開いたときはメールアドレスで予約できる。
 * - 予約は「お問い合わせ」に種類「説明会の予約」で保存。LINE（またはメール）で予約確認、
 *   前日・1時間前のリマインド、終了後のお礼（申し込みの案内）を自動で送る。
 * - 報酬・加盟金の金額はサイトに出さず、説明会でご説明する。
 *
 * 設定：代理店 → 説明会の設定（LIFF ID・LINEで送る文面）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 説明会用 LIFF ID（未設定なら空）。 */
function apprex_seminar_liff_id() {
	return (string) get_option( 'apprex_seminar_liff_id', '' );
}

/** IDトークン検証に使うLINEログインのチャネルID（LIFF IDの先頭の数字でも可）。 */
function apprex_seminar_channel_id() {
	$id = function_exists( 'apprex_liff_login_channel_id' ) ? apprex_liff_login_channel_id() : '';
	if ( '' === $id && preg_match( '/^(\d+)-/', apprex_seminar_liff_id(), $m ) ) {
		$id = $m[1];
	}
	return $id;
}

/** LIFFアプリのエンドポイントURL（LINE Developersに登録する値）。 */
function apprex_seminar_endpoint_url() {
	return home_url( '/line-seminar' );
}

/** 予約ボタンのリンク先。LIFF設定済みならLINEで開くURL、未設定ならWebの予約ページ。 */
function apprex_seminar_url() {
	$liff = apprex_seminar_liff_id();
	return '' !== $liff ? 'https://liff.line.me/' . rawurlencode( $liff ) : apprex_seminar_endpoint_url();
}

/* =========================================================================
 * 説明会の日程（CPT）
 * ====================================================================== */
add_action( 'init', function () {
	register_post_type(
		'apprex_seminar',
		array(
			'labels'       => array(
				'name'          => '説明会',
				'singular_name' => '説明会',
				'add_new'       => '説明会を追加',
				'add_new_item'  => '説明会の日程を追加',
				'edit_item'     => '説明会の日程',
				'all_items'     => '説明会',
				'menu_name'     => '説明会',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=apprex_agency',
			'supports'     => array( 'title' ),
		)
	);
} );

/** 説明会の情報。 */
function apprex_seminar_get( $id ) {
	$id = (int) $id;
	if ( ! $id || 'apprex_seminar' !== get_post_type( $id ) ) {
		return null;
	}
	$at  = (int) get_post_meta( $id, 'apprex_sem_at', true );
	$min = (int) get_post_meta( $id, 'apprex_sem_min', true );
	return array(
		'id'    => $id,
		'title' => get_the_title( $id ),
		'at'    => $at,
		'min'   => $min > 0 ? $min : 60,
		'url'   => (string) get_post_meta( $id, 'apprex_sem_url', true ),
		'cap'   => (int) get_post_meta( $id, 'apprex_sem_cap', true ),
		'note'  => (string) get_post_meta( $id, 'apprex_sem_note', true ),
		'open'  => 'publish' === get_post_status( $id ),
	);
}

/** 日時の表示（例：2026年11月5日(木) 14:00〜15:00）。 */
function apprex_seminar_when( $s ) {
	if ( ! $s || ! $s['at'] ) {
		return '日程未定';
	}
	$wd = array( '日', '月', '火', '水', '木', '金', '土' );
	return wp_date( 'Y年n月j日', $s['at'] ) . '(' . $wd[ (int) wp_date( 'w', $s['at'] ) ] . ') ' . wp_date( 'H:i', $s['at'] ) . '〜' . wp_date( 'H:i', $s['at'] + $s['min'] * 60 );
}

/** これからの説明会（公開中・開始前）。 */
function apprex_seminar_upcoming( $limit = 10 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_seminar',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_sem_at',
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => 'apprex_sem_at', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ),
			),
		)
	);
	return array_values( array_filter( array_map( 'apprex_seminar_get', $ids ) ) );
}

/** 説明会の予約（お問い合わせID）一覧。$seminar_id=0 は「次回の案内を希望」。 */
function apprex_seminar_entries( $seminar_id ) {
	return get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => 'apprex_type', 'value' => 'seminar' ),
				array( 'key' => 'apprex_seminar_id', 'value' => (int) $seminar_id ),
			),
		)
	);
}

/** 残りの席（定員なしは null）。キャンセル済みは数えない。 */
function apprex_seminar_left( $s ) {
	if ( ! $s['cap'] ) {
		return null;
	}
	$n = 0;
	foreach ( apprex_seminar_entries( $s['id'] ) as $e ) {
		if ( ! get_post_meta( $e, 'apprex_sem_cancel', true ) ) {
			++$n;
		}
	}
	return max( 0, $s['cap'] - $n );
}

/* 編集画面：日時・参加URL・定員・予約者の一覧。 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_sem_info', '説明会の内容', 'apprex_seminar_box', 'apprex_seminar', 'normal', 'high' );
	add_meta_box( 'apprex_sem_list', '予約した方', 'apprex_seminar_list_box', 'apprex_seminar', 'normal', 'default' );
} );

function apprex_seminar_box( $post ) {
	$s = apprex_seminar_get( $post->ID );
	wp_nonce_field( 'apprex_sem_save', 'apprex_sem_nonce' );
	$at = $s['at'] ? wp_date( 'Y-m-d\TH:i', $s['at'] ) : '';
	?>
	<p class="description">タイトルは予約ページとLINEに出ます（例：代理店説明会（オンライン））。「公開」にすると予約ページに並びます。</p>
	<table class="form-table" role="presentation"><tbody>
		<tr><th><label for="apprex_sem_at">日時</label></th>
			<td><input type="datetime-local" id="apprex_sem_at" name="apprex_sem_at" value="<?php echo esc_attr( $at ); ?>" required>
				所要 <input type="number" name="apprex_sem_min" value="<?php echo esc_attr( $s['min'] ); ?>" min="15" step="5" style="width:80px"> 分</td></tr>
		<tr><th><label for="apprex_sem_url">参加URL</label></th>
			<td><input type="url" id="apprex_sem_url" name="apprex_sem_url" class="large-text" value="<?php echo esc_attr( $s['url'] ); ?>" placeholder="https://meet.google.com/… または Zoom のURL">
				<p class="description">前日と1時間前のお知らせで、予約した方に届きます（予約ページには出ません）。</p></td></tr>
		<tr><th><label for="apprex_sem_cap">定員</label></th>
			<td><input type="number" id="apprex_sem_cap" name="apprex_sem_cap" value="<?php echo esc_attr( $s['cap'] ); ?>" min="0" style="width:90px"> 名　<span class="description">0 = 定員なし</span></td></tr>
		<tr><th><label for="apprex_sem_note">ひとこと</label></th>
			<td><textarea id="apprex_sem_note" name="apprex_sem_note" rows="3" class="large-text" placeholder="例：報酬・加盟金・代理店の始め方をご説明します。質問の時間もあります。"><?php echo esc_textarea( $s['note'] ); ?></textarea></td></tr>
	</tbody></table>
	<?php
}

add_action( 'save_post_apprex_seminar', function ( $id ) {
	if ( ! isset( $_POST['apprex_sem_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_sem_nonce'] ), 'apprex_sem_save' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$raw = isset( $_POST['apprex_sem_at'] ) ? sanitize_text_field( wp_unslash( $_POST['apprex_sem_at'] ) ) : '';
	$dt  = $raw ? date_create( $raw, wp_timezone() ) : false;
	$old = (int) get_post_meta( $id, 'apprex_sem_at', true );
	$new = $dt ? $dt->getTimestamp() : 0;
	update_post_meta( $id, 'apprex_sem_at', $new );
	update_post_meta( $id, 'apprex_sem_min', max( 15, isset( $_POST['apprex_sem_min'] ) ? (int) $_POST['apprex_sem_min'] : 60 ) );
	update_post_meta( $id, 'apprex_sem_url', isset( $_POST['apprex_sem_url'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_sem_url'] ) ) : '' );
	update_post_meta( $id, 'apprex_sem_cap', max( 0, isset( $_POST['apprex_sem_cap'] ) ? (int) $_POST['apprex_sem_cap'] : 0 ) );
	update_post_meta( $id, 'apprex_sem_note', isset( $_POST['apprex_sem_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apprex_sem_note'] ) ) : '' );
	// 日時を変えたら、お知らせを送り直せるようにする。
	if ( $old && $old !== $new ) {
		foreach ( apprex_seminar_entries( $id ) as $e ) {
			delete_post_meta( $e, 'apprex_sem_sent' );
		}
	}
} );

function apprex_seminar_list_box( $post ) {
	$ids = apprex_seminar_entries( $post->ID );
	if ( ! $ids ) {
		echo '<p>まだ予約はありません。予約ページ：<a href="' . esc_url( apprex_seminar_url() ) . '" target="_blank" rel="noopener">' . esc_html( apprex_seminar_url() ) . '</a></p>';
		return;
	}
	echo '<p>' . count( $ids ) . '件　<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apprex_seminar_csv&id=' . $post->ID ), 'apprex_seminar_csv' ) ) . '">CSVで書き出す</a></p>';
	echo '<table class="widefat striped"><thead><tr><th>お名前</th><th>会社名</th><th>興味のある代理店</th><th>連絡</th><th>予約日</th></tr></thead><tbody>';
	foreach ( $ids as $e ) {
		$cancel = get_post_meta( $e, 'apprex_sem_cancel', true );
		$uid    = get_post_meta( $e, 'apprex_line_uid', true );
		$email  = get_post_meta( $e, 'apprex_email', true );
		echo '<tr' . ( $cancel ? ' style="opacity:.5"' : '' ) . '><td><a href="' . esc_url( get_edit_post_link( $e ) ) . '">' . esc_html( get_post_meta( $e, 'apprex_name', true ) ) . '</a>' . ( $cancel ? '（キャンセル）' : '' ) . '</td>'
			. '<td>' . esc_html( get_post_meta( $e, 'apprex_company', true ) ) . '</td>'
			. '<td>' . esc_html( get_post_meta( $e, 'apprex_agency_type', true ) ) . '</td>'
			. '<td>' . ( $uid ? 'LINE' : '' ) . ( $uid && $email ? '・' : '' ) . esc_html( $email ) . '</td>'
			. '<td>' . esc_html( get_the_date( 'n/j H:i', $e ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/* 一覧の列：日時・予約数。 */
add_filter( 'manage_apprex_seminar_posts_columns', function ( $c ) {
	return array(
		'cb'    => isset( $c['cb'] ) ? $c['cb'] : '',
		'title' => '説明会',
		'when'  => '日時',
		'count' => '予約',
	);
} );
add_action( 'manage_apprex_seminar_posts_custom_column', function ( $col, $id ) {
	$s = apprex_seminar_get( $id );
	if ( 'when' === $col ) {
		echo esc_html( apprex_seminar_when( $s ) );
		if ( $s['at'] && $s['at'] < time() ) {
			echo ' <span style="color:#6b7280">（終了）</span>';
		}
	} elseif ( 'count' === $col ) {
		$n = count( apprex_seminar_entries( $id ) );
		echo esc_html( $n . ( $s['cap'] ? ' / ' . $s['cap'] : '' ) . '名' );
	}
}, 10, 2 );

/* 予約者のCSV。 */
add_action( 'admin_post_apprex_seminar_csv', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_seminar_csv' ) ) {
		wp_die( '権限がありません。' );
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$s  = apprex_seminar_get( $id );
	if ( ! $s ) {
		wp_die( '説明会が見つかりません。' );
	}
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="seminar-' . $id . '.csv"' );
	$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fputcsv( $out, array( 'お名前', '会社名', 'メール', '電話', '興味のある代理店', 'LINE', '紹介元コード', 'キャンセル', '予約日時', 'ご質問' ) );
	foreach ( apprex_seminar_entries( $id ) as $e ) {
		fputcsv(
			$out,
			array(
				get_post_meta( $e, 'apprex_name', true ),
				get_post_meta( $e, 'apprex_company', true ),
				get_post_meta( $e, 'apprex_email', true ),
				get_post_meta( $e, 'apprex_phone', true ),
				get_post_meta( $e, 'apprex_agency_type', true ),
				get_post_meta( $e, 'apprex_line_uid', true ) ? 'あり' : '',
				get_post_meta( $e, 'apprex_ref', true ),
				get_post_meta( $e, 'apprex_sem_cancel', true ) ? 'キャンセル' : '',
				get_the_date( 'Y-m-d H:i', $e ),
				get_post_meta( $e, 'apprex_question', true ),
			)
		);
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
} );

/* =========================================================================
 * LINEで送る文面
 * ====================================================================== */

/** 初期の文面。{name} {title} {when} {url} {apply} {meeting} が置き換わる。 */
function apprex_seminar_default_messages() {
	return array(
		'confirm' => "{name} 様\n\n代理店説明会のご予約ありがとうございます。\n\n■ {title}\n■ {when}\n■ オンライン開催\n\n参加URLは、前日と1時間前にこのLINEでお送りします。\n報酬・加盟金・代理店の始め方は、説明会でくわしくご説明します。ご質問もその場でどうぞ。",
		'waiting' => "{name} 様\n\n代理店説明会の「次回のご案内」を受け付けました。\n日程が決まりしだい、このLINEでお知らせします。",
		'd1'      => "{name} 様\n\n明日は代理店説明会です。\n\n■ {title}\n■ {when}\n■ 参加URL：{url}\n\n当日はこのURLからご参加ください。",
		'h1'      => "{name} 様\n\nまもなく代理店説明会が始まります（{when}）。\n\n▼ 参加URL\n{url}",
		'after'   => "{name} 様\n\n本日は代理店説明会にご参加いただき、ありがとうございました。\n\n代理店のお申し込みは、こちらからどうぞ。\n{apply}\n\n個別に相談したい方は、オンライン面談もご予約いただけます。\n{meeting}",
		'invite'  => "{name} 様\n\nお待たせしました。代理店説明会の日程が決まりました。\n\n■ {title}\n■ {when}\n\n▼ ご予約はこちら\n{reserve}",
	);
}

/** 文面のラベル（設定画面用）。 */
function apprex_seminar_message_labels() {
	return array(
		'confirm' => '予約したとき',
		'waiting' => '「次回の案内を希望」のとき',
		'd1'      => '前日のお知らせ',
		'h1'      => '1時間前のお知らせ',
		'after'   => '終了後のお礼（申し込みの案内）',
		'invite'  => '次回の日程のお知らせ（説明会の画面から送る）',
	);
}

/** 文面（設定で変えた内容を優先）。 */
function apprex_seminar_message( $key ) {
	$o = get_option( 'apprex_seminar_msgs', array() );
	if ( is_array( $o ) && isset( $o[ $key ] ) && '' !== trim( $o[ $key ] ) ) {
		return $o[ $key ];
	}
	$d = apprex_seminar_default_messages();
	return isset( $d[ $key ] ) ? $d[ $key ] : '';
}

/** 予約1件に文面を送る（LINE優先、LINEが無ければメール）。 */
function apprex_seminar_send( $entry_id, $key, $s = null ) {
	$s    = $s ? $s : apprex_seminar_get( (int) get_post_meta( $entry_id, 'apprex_seminar_id', true ) );
	$name = (string) get_post_meta( $entry_id, 'apprex_name', true );
	$meet = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '';
	$text = strtr(
		apprex_seminar_message( $key ),
		array(
			'{name}'    => $name,
			'{title}'   => $s ? $s['title'] : '代理店説明会',
			'{when}'    => apprex_seminar_when( $s ),
			'{url}'     => ( $s && $s['url'] ) ? $s['url'] : '（参加URLは追ってお送りします）',
			'{apply}'   => home_url( '/partner/#apply' ),
			'{meeting}' => $meet,
			'{reserve}' => apprex_seminar_url(),
		)
	);
	if ( '' === trim( $text ) ) {
		return false;
	}
	$uid = (string) get_post_meta( $entry_id, 'apprex_line_uid', true );
	if ( '' !== $uid && function_exists( 'apprex_line_push' ) ) {
		$r = apprex_line_push( $uid, array( array( 'type' => 'text', 'text' => mb_substr( $text, 0, 4900 ) ) ) );
		if ( ! is_wp_error( $r ) ) {
			return true;
		}
		update_post_meta( $entry_id, 'apprex_sem_line_err', $r->get_error_message() );
	}
	$email = (string) get_post_meta( $entry_id, 'apprex_email', true );
	if ( is_email( $email ) && function_exists( 'apprex_render_email' ) ) {
		$labels  = apprex_seminar_message_labels();
		$subject = '【APPREX】代理店説明会' . ( 'confirm' === $key ? 'のご予約ありがとうございます' : '：' . $labels[ $key ] );
		$html    = apprex_render_email( $subject, $text, array( 'heading' => '代理店説明会' ) );
		return wp_mail( $email, $subject, $html, apprex_mail_headers() );
	}
	return false;
}

/* =========================================================================
 * 前日・1時間前・終了後のお知らせ（15分ごと）
 * ====================================================================== */
add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['apprex_15min'] ) ) {
		$s['apprex_15min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => '15分ごと' );
	}
	return $s;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_seminar_cron' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'apprex_15min', 'apprex_seminar_cron' );
	}
} );
add_action( 'apprex_seminar_cron', 'apprex_seminar_process' );

function apprex_seminar_process() {
	$now = time();
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_seminar',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => 'apprex_sem_at', 'value' => array( $now - 3 * DAY_IN_SECONDS, $now + DAY_IN_SECONDS + HOUR_IN_SECONDS ), 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ),
			),
		)
	);
	foreach ( $ids as $sid ) {
		$s = apprex_seminar_get( $sid );
		if ( ! $s || ! $s['at'] ) {
			continue;
		}
		$end = $s['at'] + $s['min'] * 60;
		if ( $now >= $end + 30 * MINUTE_IN_SECONDS ) {
			$key = 'after';
		} elseif ( $now >= $s['at'] ) {
			continue;
		} elseif ( $now >= $s['at'] - 75 * MINUTE_IN_SECONDS ) {
			$key = 'h1';
		} elseif ( $now >= $s['at'] - DAY_IN_SECONDS ) {
			$key = 'd1';
		} else {
			continue;
		}
		foreach ( apprex_seminar_entries( $sid ) as $e ) {
			if ( get_post_meta( $e, 'apprex_sem_cancel', true ) ) {
				continue;
			}
			$sent = (array) get_post_meta( $e, 'apprex_sem_sent', true );
			// 直前に予約した方へ、前日のお知らせを重ねて送らない。
			if ( in_array( $key, $sent, true ) || ( 'd1' === $key && in_array( 'h1', $sent, true ) ) ) {
				continue;
			}
			if ( 'd1' === $key && strtotime( get_post_field( 'post_date_gmt', $e ) . ' UTC' ) > $s['at'] - DAY_IN_SECONDS ) {
				$sent[] = 'd1';
				update_post_meta( $e, 'apprex_sem_sent', $sent );
				continue;
			}
			apprex_seminar_send( $e, $key, $s );
			$sent[] = $key;
			update_post_meta( $e, 'apprex_sem_sent', $sent );
		}
	}
}

/* 説明会の画面：「次回の案内を希望」の方へ、この日程をお知らせする。 */
add_action( 'post_submitbox_misc_actions', function ( $post ) {
	if ( 'apprex_seminar' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}
	$n = count( apprex_seminar_waiting() );
	echo '<div class="misc-pub-section">次回の案内を希望：<b>' . (int) $n . '名</b>';
	if ( $n ) {
		echo '<br><a class="button" style="margin-top:6px" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apprex_seminar_invite&id=' . $post->ID ), 'apprex_seminar_invite' ) ) . '" onclick="return confirm(\'この日程をLINE（またはメール）でお知らせします。よろしいですか？\');">この日程をお知らせする</a>';
	}
	if ( isset( $_GET['apprex_invited'] ) ) {
		echo '<p style="color:#15803d">' . (int) $_GET['apprex_invited'] . '名にお知らせしました。</p>';
	}
	echo '</div>';
} );

/** 「次回の案内を希望」でまだ案内していない予約。 */
function apprex_seminar_waiting() {
	return array_values(
		array_filter(
			apprex_seminar_entries( 0 ),
			function ( $e ) {
				return ! get_post_meta( $e, 'apprex_sem_invited', true );
			}
		)
	);
}

add_action( 'admin_post_apprex_seminar_invite', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_seminar_invite' ) ) {
		wp_die( '権限がありません。' );
	}
	$s = apprex_seminar_get( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
	if ( ! $s ) {
		wp_die( '説明会が見つかりません。' );
	}
	$n = 0;
	foreach ( apprex_seminar_waiting() as $e ) {
		if ( apprex_seminar_send( $e, 'invite', $s ) ) {
			++$n;
		}
		update_post_meta( $e, 'apprex_sem_invited', $s['id'] );
	}
	wp_safe_redirect( add_query_arg( 'apprex_invited', $n, get_edit_post_link( $s['id'], 'url' ) ) );
	exit;
} );

/* =========================================================================
 * 設定（LIFF ID・文面）
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=apprex_agency', '説明会の設定', '説明会の設定', 'manage_options', 'apprex-seminar', 'apprex_seminar_settings_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_seminar', 'apprex_seminar_liff_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting(
		'apprex_seminar',
		'apprex_seminar_msgs',
		array(
			'sanitize_callback' => function ( $v ) {
				$d   = apprex_seminar_default_messages();
				$out = array();
				foreach ( (array) $v as $k => $t ) {
					$t = sanitize_textarea_field( (string) $t );
					// 初期の文面と同じなら保存しない（あとで初期の文面を直したとき反映されるように）。
					if ( isset( $d[ $k ] ) && '' !== trim( $t ) && trim( $t ) !== trim( $d[ $k ] ) ) {
						$out[ $k ] = $t;
					}
				}
				return $out;
			},
		)
	);
} );

function apprex_seminar_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ready = '' !== apprex_seminar_liff_id();
	?>
	<div class="wrap">
		<h1>説明会の設定（LINEで予約）</h1>
		<div class="notice notice-info"><p>
			<strong>LINEの準備</strong>（1回だけ）<br>
			① LINE Developers で、公式アカウントと同じプロバイダーの<strong>LINEログインチャネル</strong>を開く（会員マイページ用と同じチャネルでOK）<br>
			② <strong>LIFF</strong> タブ →「追加」：サイズ <b>Full</b>／エンドポイントURL <code><?php echo esc_html( apprex_seminar_endpoint_url() ); ?></code>／Scope <b>openid・profile</b>／友だち追加オプション <b>On（Aggressive）</b><br>
			③ 発行された <strong>LIFF ID</strong> を下に入れて保存<br>
			④ リッチメニューやあいさつメッセージのリンクに <code><?php echo esc_html( 'https://liff.line.me/' . ( $ready ? apprex_seminar_liff_id() : '（LIFF ID）' ) ); ?></code> を設定<br>
			状態：<strong style="color:<?php echo $ready ? '#15803d' : '#b91c1c'; ?>;"><?php echo $ready ? 'LINEで予約できます' : '未設定（今はWebの予約ページだけ動きます）'; ?></strong>
		</p></div>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_seminar' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="apprex_seminar_liff_id">説明会のLIFF ID</label></th>
					<td><input type="text" id="apprex_seminar_liff_id" name="apprex_seminar_liff_id" class="regular-text" value="<?php echo esc_attr( apprex_seminar_liff_id() ); ?>" placeholder="1234567890-abcdEFGH">
						<p class="description">予約ボタンのリンク先：<code><?php echo esc_html( apprex_seminar_url() ); ?></code></p></td></tr>
				<?php
				$o = get_option( 'apprex_seminar_msgs', array() );
				$d = apprex_seminar_default_messages();
				foreach ( apprex_seminar_message_labels() as $k => $label ) :
					?>
					<tr><th><label for="apprex_sm_<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><textarea id="apprex_sm_<?php echo esc_attr( $k ); ?>" name="apprex_seminar_msgs[<?php echo esc_attr( $k ); ?>]" rows="6" class="large-text"><?php echo esc_textarea( isset( $o[ $k ] ) ? $o[ $k ] : $d[ $k ] ); ?></textarea></td></tr>
				<?php endforeach; ?>
			</tbody></table>
			<p class="description">置き換わる言葉：{name}＝お名前／{title}＝説明会名／{when}＝日時／{url}＝参加URL／{apply}＝代理店の申し込みページ／{meeting}＝面談の予約ページ／{reserve}＝説明会の予約ページ。LINEが無い方にはメールで届きます。</p>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* =========================================================================
 * LINEで「説明会」と送られたら、予約ページを返す（AIの自動返信より先に）。
 * ====================================================================== */
add_action( 'apprex_line_event', function ( $ev, $uid, $type ) {
	if ( 'message' !== $type || empty( $ev['message']['text'] ) || empty( $ev['replyToken'] ) ) {
		return;
	}
	$t = (string) $ev['message']['text'];
	if ( false === mb_strpos( $t, '説明会' ) && false === mb_strpos( $t, 'セミナー' ) && false === mb_strpos( $t, '代理店' ) ) {
		return;
	}
	if ( ! function_exists( 'apprex_line_reply' ) ) {
		return;
	}
	$list = '';
	foreach ( apprex_seminar_upcoming( 3 ) as $s ) {
		$list .= "\n・" . apprex_seminar_when( $s );
	}
	$text = "代理店説明会（オンライン・無料）のご案内です。\n報酬・加盟金・代理店の始め方を、説明会でくわしくご説明します。\n" . ( $list ? "\n次回の日程：" . $list . "\n" : "\n日程は準備中です。予約ページから次回のご案内を受け取れます。\n" ) . "\n▼ ご予約はこちら\n" . apprex_seminar_url();
	$r    = apprex_line_reply( sanitize_text_field( $ev['replyToken'] ), array( array( 'type' => 'text', 'text' => $text ) ) );
	if ( ! is_wp_error( $r ) ) {
		$GLOBALS['apprex_line_replied'] = true;
	}
}, 5, 3 );

/* =========================================================================
 * 予約ページ /line-seminar（LIFF）
 * ====================================================================== */
add_action( 'init', function () {
	add_rewrite_rule( '^line-seminar/?$', 'index.php?apprex_seminar_page=1', 'top' );
	if ( '1' !== get_option( 'apprex_seminar_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_seminar_rw', '1' );
	}
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'apprex_seminar_page';
	return $v;
} );
add_filter( 'redirect_canonical', function ( $r ) {
	return get_query_var( 'apprex_seminar_page' ) ? false : $r;
} );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_seminar_page' ) ) {
		return;
	}
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	$list = array();
	foreach ( apprex_seminar_upcoming( 8 ) as $s ) {
		$left   = apprex_seminar_left( $s );
		$list[] = array(
			'id'    => $s['id'],
			'title' => $s['title'],
			'when'  => apprex_seminar_when( $s ),
			'note'  => $s['note'],
			'left'  => $left,
		);
	}
	$cfg = array(
		'liff'   => apprex_seminar_liff_id(),
		'rest'   => esc_url_raw( rest_url( 'apprex/v1/seminar-entry' ) ),
		'nonce'  => wp_create_nonce( 'wp_rest' ),
		'line'   => function_exists( 'apprex_line_url' ) ? apprex_line_url() : '',
		'open'   => apprex_seminar_url(),
		'list'   => $list,
		'types'  => array( '紹介代理店', '販売代理店', 'OEM代理店', 'まだ決めていない' ),
		'policy' => function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '',
	);
	$logo = function_exists( 'apprex_brand_name' ) ? apprex_brand_name() : 'APPREX';
	?>
<!doctype html><html lang="ja"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>代理店説明会のご予約｜<?php echo esc_html( $logo ); ?></title>
<?php if ( '' !== $cfg['liff'] ) : ?><script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script><?php endif; ?>
<style>
:root{--g:#06c755;--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--bg:#f1f5f9;--pri:#1d4ed8}
*{box-sizing:border-box}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Hiragino Kaku Gothic ProN","Hiragino Sans",Meiryo,sans-serif;background:var(--bg);color:var(--ink);line-height:1.7;-webkit-text-size-adjust:100%}
.hd{background:linear-gradient(150deg,#0b1d3f,#1d4ed8);color:#fff;padding:26px 18px 54px}
.hd small{display:inline-block;background:rgba(255,255,255,.16);border-radius:99px;padding:2px 12px;font-size:12px;letter-spacing:.06em}
.hd h1{font-size:22px;line-height:1.45;margin:10px 0 6px}.hd p{margin:0;font-size:14px;opacity:.9}
.wrap{max-width:520px;margin:-34px auto 40px;padding:0 14px}
.card{background:#fff;border-radius:16px;box-shadow:0 6px 24px rgba(15,23,42,.08);padding:18px;margin-bottom:14px}
.card h2{font-size:15px;margin:0 0 10px}
.pts{list-style:none;margin:0;padding:0;display:grid;gap:8px}.pts li{display:flex;gap:10px;font-size:14px}.pts b{flex:0 0 22px;height:22px;border-radius:50%;background:#dbeafe;color:var(--pri);font-size:12px;display:grid;place-items:center}
.sem{display:block;border:2px solid var(--line);border-radius:12px;padding:12px 14px;margin-bottom:10px;cursor:pointer;position:relative}
.sem input{position:absolute;opacity:0}.sem.on{border-color:var(--pri);background:#eff6ff}.sem.full{opacity:.45;cursor:not-allowed}
.sem .w{font-weight:700;font-size:15px}.sem .t{font-size:13px;color:var(--mut)}.sem .l{display:inline-block;margin-top:6px;font-size:12px;color:var(--pri);font-weight:700;background:#dbeafe;border-radius:99px;padding:1px 10px}.sem .l:empty{display:none}.sem.full .l{background:#fee2e2;color:#b91c1c}
.sem .n{font-size:12.5px;color:var(--mut);margin-top:4px}
label.f{display:block;margin-bottom:12px;font-size:13px;font-weight:700}
label.f span.r{color:#dc2626;margin-left:4px}label.f em{font-style:normal;font-weight:400;color:var(--mut);margin-left:4px}
input.i,select.i,textarea.i{display:block;width:100%;margin-top:5px;padding:12px;border:1px solid #cbd5e1;border-radius:10px;font-size:16px;font-family:inherit;background:#fff}
.who{display:flex;align-items:center;gap:10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:10px 12px;margin-bottom:12px;font-size:13px}
.who img{width:36px;height:36px;border-radius:50%}
.btn{display:block;width:100%;border:0;border-radius:12px;padding:15px;font-size:16px;font-weight:700;text-align:center;text-decoration:none;cursor:pointer;font-family:inherit}
.btn.p{background:var(--pri);color:#fff}.btn.g{background:var(--g);color:#fff}.btn.o{background:#fff;color:var(--ink);border:1px solid var(--line)}
.btn[disabled]{opacity:.6}
.note{font-size:12px;color:var(--mut);margin:8px 0 0}.err{color:#b91c1c;font-size:13px;margin:8px 0 0}
.ok{text-align:center;padding:26px 18px}.ok .c{width:62px;height:62px;border-radius:50%;background:#dcfce7;color:#16a34a;font-size:34px;display:grid;place-items:center;margin:0 auto 10px}
.ok h2{font-size:19px;margin:0 0 6px}.ok p{font-size:14px;margin:0 0 12px}
.box{background:#f8fafc;border-radius:10px;padding:10px 12px;font-size:14px;text-align:left;margin:0 0 14px}
.gap{height:10px}
</style>
</head><body>
<div class="hd">
	<small>オンライン・無料</small>
	<h1>代理店説明会のご予約</h1>
	<p>報酬・加盟金・代理店の始め方は、説明会でくわしくご説明します。</p>
</div>
<div class="wrap" id="app">
	<div class="card">
		<h2>説明会でわかること</h2>
		<ul class="pts">
			<li><b>1</b><span>紹介・販売・OEM、3つの代理店の違いと選び方</span></li>
			<li><b>2</b><span>報酬のしくみと加盟金（金額は説明会でご案内）</span></li>
			<li><b>3</b><span>AIスタジオを使った、売りやすい商談の進め方</span></li>
			<li><b>4</b><span>お申し込みから活動を始めるまでの流れ</span></li>
		</ul>
	</div>
	<form class="card" id="fm" novalidate>
		<h2>日程を選ぶ</h2>
		<div id="sems"></div>
		<div class="gap"></div>
		<div id="who"></div>
		<label class="f">お名前<span class="r">*</span><input class="i" name="name" autocomplete="name" required></label>
		<label class="f">会社名・屋号<em>（任意）</em><input class="i" name="company" autocomplete="organization"></label>
		<label class="f" id="em">メールアドレス<span class="r" id="emr">*</span><em id="emo" hidden>（任意）</em><input class="i" type="email" name="email" autocomplete="email" inputmode="email"></label>
		<label class="f">電話番号<em>（任意）</em><input class="i" type="tel" name="phone" autocomplete="tel" inputmode="tel"></label>
		<label class="f">興味のある代理店<select class="i" name="agency_type" id="ty"></select></label>
		<label class="f">聞きたいこと<em>（任意）</em><textarea class="i" name="question" rows="3"></textarea></label>
		<button class="btn p" id="sb" type="submit">この日程で予約する</button>
		<p class="err" id="er" hidden></p>
		<p class="note" id="pol"></p>
	</form>
	<div class="card" id="out" hidden>
		<p style="margin:0 0 10px;font-size:14px">LINEで予約すると、参加URLやお知らせがLINEに届きます。</p>
		<a class="btn g" id="openline" href="#">LINEで予約する</a>
	</div>
</div>
<script>
(function(){
	var C=<?php echo wp_json_encode( $cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
	var S={idt:'',friend:null,inLine:false,sel:0};
	function $(id){return document.getElementById(id);}
	function esc(t){return String(t==null?'':t).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
	// 日程
	var h='';
	C.list.forEach(function(s,i){
		var full=s.left===0;
		h+='<label class="sem'+(full?' full':'')+'" data-id="'+s.id+'"><input type="radio" name="seminar" value="'+s.id+'"'+(full?' disabled':'')+'>'
			+'<div class="w">'+esc(s.when)+'</div><div class="t">'+esc(s.title)+'</div>'
			+(s.note?'<div class="n">'+esc(s.note)+'</div>':'')
			+'<span class="l">'+(full?'満席':(s.left!=null?'残り'+s.left+'席':''))+'</span></label>';
	});
	h+='<label class="sem" data-id="0"><input type="radio" name="seminar" value="0"><div class="w">'+(C.list.length?'都合の合う日程がない':'日程は準備中です')+'</div><div class="t">次回の日程が決まったらお知らせします</div></label>';
	$('sems').innerHTML=h;
	function pick(id){S.sel=id;Array.prototype.forEach.call(document.querySelectorAll('.sem'),function(el){el.classList.toggle('on',String(el.getAttribute('data-id'))===String(id));});
		$('sb').textContent=String(id)==='0'?'次回の案内を受け取る':'この日程で予約する';}
	$('sems').addEventListener('change',function(e){if(e.target.name==='seminar')pick(e.target.value);});
	var first=C.list.filter(function(s){return s.left!==0;})[0];
	pick(first?first.id:0);
	var r=document.querySelector('input[name=seminar][value="'+(first?first.id:0)+'"]');if(r)r.checked=true;
	$('ty').innerHTML=C.types.map(function(t){return '<option>'+esc(t)+'</option>';}).join('');
	if(C.policy)$('pol').innerHTML='予約すると<a href="'+esc(C.policy)+'" target="_blank" rel="noopener">プライバシーポリシー</a>に同意したものとします。';
	function mailNeed(need){$('emr').hidden=!need;$('emo').hidden=need;}
	mailNeed(true);
	// LIFF
	function webOnly(){
		if(C.liff){$('out').hidden=false;$('openline').href=C.open;}
	}
	if(C.liff&&window.liff){
		liff.init({liffId:C.liff}).then(function(){
			S.inLine=liff.isInClient();
			if(!liff.isLoggedIn()){
				if(S.inLine){liff.login();}else{webOnly();}
				return;
			}
			S.idt=liff.getIDToken()||'';
			if(S.idt)mailNeed(false);
			liff.getProfile().then(function(p){
				var n=document.querySelector('input[name=name]');if(n&&!n.value)n.value=p.displayName||'';
				$('who').innerHTML='<div class="who">'+(p.pictureUrl?'<img src="'+esc(p.pictureUrl)+'" alt="">':'')+'<div><b>'+esc(p.displayName)+'</b> さんのLINEで予約します。<br>お知らせはこのLINEに届きます。</div></div>';
			}).catch(function(){});
			if(liff.getFriendship){liff.getFriendship().then(function(f){S.friend=!!(f&&f.friendFlag);}).catch(function(){});}
		}).catch(function(){webOnly();});
	}else{webOnly();}
	// 送信
	$('fm').addEventListener('submit',function(e){
		e.preventDefault();
		var f=e.target,er=$('er');er.hidden=true;
		var d={seminar:S.sel,name:f.name.value.trim(),company:f.company.value.trim(),email:f.email.value.trim(),phone:f.phone.value.trim(),agency_type:f.agency_type.value,question:f.question.value.trim(),id_token:S.idt};
		if(!d.name){er.textContent='お名前をご入力ください。';er.hidden=false;return;}
		if(!S.idt&&!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email)){er.textContent='メールアドレスをご入力ください（LINEで予約する場合は不要です）。';er.hidden=false;return;}
		$('sb').disabled=true;$('sb').textContent='送信しています…';
		fetch(C.rest,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':C.nonce},body:JSON.stringify(d)})
		.then(function(r){return r.json();}).then(function(res){
			if(!res||!res.ok){throw new Error(res&&res.message?res.message:'予約できませんでした。');}
			done(res);
		}).catch(function(x){er.textContent=x.message||'通信エラーが発生しました。';er.hidden=false;$('sb').disabled=false;pick(S.sel);});
	});
	function done(res){
		var lineOk=res.line;
		var h='<div class="card ok"><div class="c">✓</div><h2>'+(res.waiting?'次回のご案内を受け付けました':'ご予約ありがとうございます')+'</h2>';
		if(!res.waiting)h+='<div class="box"><b>'+esc(res.title)+'</b><br>'+esc(res.when)+'<br>オンライン開催</div>';
		var msg=lineOk?'確認のメッセージをLINEにお送りしました。':(S.idt&&S.friend===false?'公式アカウントを友だち追加すると、お知らせがLINEに届きます。':(S.idt?'ご予約を受け付けました。':'確認のメールをお送りしました。'));
		h+='<p>'+msg+(res.waiting?'':'<br>参加URLは前日と1時間前に'+(lineOk?'LINE':'メール')+'でお届けします。')+'</p>';
		if(S.inLine&&S.friend===false&&C.line)h+='<p style="font-size:13px">お知らせを受け取るには、公式アカウントの友だち追加が必要です。</p><a class="btn g" href="'+esc(C.line)+'">友だち追加する</a><div class="gap"></div>';
		else if(!S.inLine&&C.line)h+='<a class="btn g" href="'+esc(C.line)+'" target="_blank" rel="noopener">LINEの友だち追加はこちら</a><div class="gap"></div>';
		if(S.inLine)h+='<button class="btn o" id="cl" type="button">LINEに戻る</button>';
		h+='</div>';
		$('app').innerHTML=h;
		var cl=$('cl');if(cl)cl.onclick=function(){try{liff.closeWindow();}catch(e){}};
		window.scrollTo(0,0);
	}
})();
</script>
</body></html>
	<?php
	exit;
} );

/* =========================================================================
 * REST：予約の受付
 * ====================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/seminar-entry',
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => 'apprex_seminar_rest_entry',
		)
	);
} );

/** LINEのIDトークンを検証して userId を返す（失敗は空）。 */
function apprex_seminar_verify_idt( $idt ) {
	$cid = apprex_seminar_channel_id();
	if ( '' === $idt || '' === $cid ) {
		return array( '', '' );
	}
	$res = wp_remote_post(
		'https://api.line.me/oauth2/v2.1/verify',
		array(
			'timeout' => 15,
			'body'    => array( 'id_token' => $idt, 'client_id' => $cid ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return array( '', '' );
	}
	$c = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $c['sub'] ) ) {
		return array( '', '' );
	}
	return array( sanitize_text_field( $c['sub'] ), isset( $c['email'] ) ? sanitize_email( $c['email'] ) : '' );
}

function apprex_seminar_rest_entry( WP_REST_Request $req ) {
	$fail  = function ( $m ) {
		return new WP_REST_Response( array( 'ok' => false, 'message' => $m ), 200 );
	};
	$nonce = $req->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return $fail( 'ページの有効期限が切れました。開き直してください。' );
	}
	$name     = sanitize_text_field( (string) $req->get_param( 'name' ) );
	$company  = sanitize_text_field( (string) $req->get_param( 'company' ) );
	$email    = sanitize_email( (string) $req->get_param( 'email' ) );
	$phone    = sanitize_text_field( (string) $req->get_param( 'phone' ) );
	$question = sanitize_textarea_field( (string) $req->get_param( 'question' ) );
	$atype    = mb_substr( sanitize_text_field( (string) $req->get_param( 'agency_type' ) ), 0, 40 );
	$sid      = (int) $req->get_param( 'seminar' );

	list( $uid, $line_email ) = apprex_seminar_verify_idt( (string) $req->get_param( 'id_token' ) );
	if ( '' === $email && $line_email ) {
		$email = $line_email;
	}
	if ( '' === $name ) {
		return $fail( 'お名前をご入力ください。' );
	}
	if ( '' === $uid && ! is_email( $email ) ) {
		return $fail( 'メールアドレスをご入力ください。' );
	}

	$s = null;
	if ( $sid ) {
		$s = apprex_seminar_get( $sid );
		if ( ! $s || ! $s['open'] || $s['at'] <= time() ) {
			return $fail( 'この日程は受付を終了しました。ほかの日程をお選びください。' );
		}
		if ( 0 === apprex_seminar_left( $s ) ) {
			return $fail( 'この日程は満席になりました。ほかの日程をお選びください。' );
		}
	}

	// 同じ方の二重予約を防ぐ（LINE または メールが同じ）。
	foreach ( apprex_seminar_entries( $sid ) as $e ) {
		if ( get_post_meta( $e, 'apprex_sem_cancel', true ) ) {
			continue;
		}
		if ( ( '' !== $uid && get_post_meta( $e, 'apprex_line_uid', true ) === $uid ) || ( '' !== $email && get_post_meta( $e, 'apprex_email', true ) === $email ) ) {
			if ( $sid ) {
				return $fail( 'この日程はすでにご予約済みです。' );
			}
			return new WP_REST_Response( array( 'ok' => true, 'waiting' => true, 'line' => '' !== $uid ), 200 );
		}
	}

	$when    = $s ? apprex_seminar_when( $s ) : '次回の案内を希望';
	$message = '【説明会】' . ( $s ? $s['title'] . '／' . $when : '次回の日程の案内を希望' ) . "\n【興味のある代理店】" . $atype . ( '' !== $question ? "\n【聞きたいこと】" . $question : '' ) . ( '' !== $uid ? "\n【連絡】LINE" : '' );
	$ref     = function_exists( 'apprex_agency_ref' ) ? apprex_agency_ref() : '';
	if ( '' !== $ref ) {
		$message .= "\n【紹介元の代理店コード】" . $ref;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'apprex_inquiry',
			'post_status' => 'publish',
			'post_title'  => '説明会の予約：' . $name . '（' . $when . '）',
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $fail( '予約できませんでした。時間をおいてお試しください。' );
	}
	$type = 'seminar';
	foreach ( compact( 'type', 'name', 'company', 'email', 'phone', 'message' ) as $k => $v ) {
		update_post_meta( $id, 'apprex_' . $k, $v );
	}
	update_post_meta( $id, 'apprex_submitted_at', current_time( 'mysql' ) );
	update_post_meta( $id, 'apprex_seminar_id', $sid );
	update_post_meta( $id, 'apprex_agency_type', $atype );
	update_post_meta( $id, 'apprex_question', $question );
	if ( '' !== $uid ) {
		update_post_meta( $id, 'apprex_line_uid', $uid );
		$fid = function_exists( 'apprex_line_friend_get' ) ? apprex_line_friend_get( $uid, false ) : 0;
		if ( $fid ) {
			wp_update_post( array( 'ID' => $fid, 'post_title' => $name . '（説明会）' ) );
			update_post_meta( $fid, 'apprex_seminar_entry', $id );
		}
	}
	if ( '' !== $ref ) {
		update_post_meta( $id, 'apprex_ref', $ref );
	}

	$sent   = apprex_seminar_send( $id, $sid ? 'confirm' : 'waiting', $s );
	$fields = array(
		'type'    => $type,
		'name'    => $name,
		'company' => $company,
		'email'   => '' !== $email ? $email : '（LINEで予約）',
		'phone'   => $phone,
		'message' => $message,
	);
	if ( function_exists( 'apprex_notify_inquiry' ) ) {
		apprex_notify_inquiry( $id, '説明会の予約', $fields );
	}
	if ( function_exists( 'apprex_dispatch_event' ) ) {
		apprex_dispatch_event(
			'inquiry',
			array(
				'id'         => $id,
				'type'       => $type,
				'type_label' => '説明会の予約',
				'name'       => $name,
				'company'    => $company,
				'email'      => $email,
				'phone'      => $phone,
				'message'    => $message,
				'meeting_at' => $s ? wp_date( 'Y-m-d H:i', $s['at'] ) : '',
				'admin_url'  => admin_url( 'post.php?post=' . $id . '&action=edit' ),
			)
		);
	}
	do_action( 'apprex_inquiry_submitted', $id, $type, $fields );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'waiting' => ! $sid,
			'title'   => $s ? $s['title'] : '',
			'when'    => $when,
			'line'    => '' !== $uid && ! get_post_meta( $id, 'apprex_sem_line_err', true ) && $sent,
		),
		200
	);
}

/* お問い合わせの画面：説明会の予約ならキャンセルの切り替え。 */
add_action( 'add_meta_boxes_apprex_inquiry', function ( $post ) {
	if ( 'seminar' !== get_post_meta( $post->ID, 'apprex_type', true ) ) {
		return;
	}
	add_meta_box(
		'apprex_sem_entry',
		'説明会の予約',
		function ( $post ) {
			$s = apprex_seminar_get( (int) get_post_meta( $post->ID, 'apprex_seminar_id', true ) );
			wp_nonce_field( 'apprex_sem_entry', 'apprex_sem_entry_nonce' );
			echo '<p>' . ( $s ? '<a href="' . esc_url( get_edit_post_link( $s['id'] ) ) . '">' . esc_html( $s['title'] ) . '</a><br>' . esc_html( apprex_seminar_when( $s ) ) : '次回の日程の案内を希望' ) . '</p>';
			echo '<p>連絡：' . ( get_post_meta( $post->ID, 'apprex_line_uid', true ) ? 'LINE' : 'メール' ) . '</p>';
			$err = get_post_meta( $post->ID, 'apprex_sem_line_err', true );
			if ( $err ) {
				echo '<p style="color:#b91c1c">LINEで送れませんでした：' . esc_html( $err ) . '</p>';
			}
			echo '<label><input type="checkbox" name="apprex_sem_cancel" value="1"' . checked( (bool) get_post_meta( $post->ID, 'apprex_sem_cancel', true ), true, false ) . '> キャンセル（お知らせを止める）</label>';
		},
		'apprex_inquiry',
		'side'
	);
} );
add_action( 'save_post_apprex_inquiry', function ( $id ) {
	if ( ! isset( $_POST['apprex_sem_entry_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_sem_entry_nonce'] ), 'apprex_sem_entry' ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	update_post_meta( $id, 'apprex_sem_cancel', empty( $_POST['apprex_sem_cancel'] ) ? 0 : 1 );
} );
