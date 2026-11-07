<?php
/**
 * AI営業リスト → 営業メール。
 *
 * 1) 下書き：調べたお店（公開メールあり・営業お断りなし）に、AIが1店ずつ違う文面を作る。
 * 2) 送る前の確認：一覧で読んで、直して、送る／送らない を決める（最初はこのやり方）。
 * 3) 慣れてきたら「自動で送る」に切り替え：1日の上限まで、平日の昼に送る。
 * 4) 記録：いつ誰に送ったか・リンクを開いたか・配信停止・お問い合わせにつながったか。
 *
 * 特定電子メール法に合わせて、送信者の名前・住所・連絡先・配信停止の方法を必ず本文の最後に入れる。
 * 送るのは「ホームページに公開されているアドレス」だけ。「営業お断り」の記載があるお店・配信停止したアドレスには送らない。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 設定
 * ====================================================================== */

function apprex_ldm_from() {
	$f = (string) get_option( 'apprex_ldm_from', 'info@aisjaltd.com' );
	return is_email( $f ) ? $f : 'info@aisjaltd.com';
}
function apprex_ldm_from_name() {
	$n = (string) get_option( 'apprex_ldm_from_name', '' );
	return '' !== $n ? $n : 'APPREX（合同会社アイズ）';
}
/** review＝送る前に確認する／auto＝自動で送る。 */
function apprex_ldm_mode() {
	return 'auto' === get_option( 'apprex_ldm_mode', 'review' ) ? 'auto' : 'review';
}
function apprex_ldm_daily() {
	return max( 1, min( 50, (int) get_option( 'apprex_ldm_daily', 10 ) ) );
}
function apprex_ldm_min() {
	return max( 0, (int) get_option( 'apprex_ldm_min', 50 ) );
}
/** 返事がないとき、もう1通送るまでの日数（0＝送らない）。 */
function apprex_ldm_follow_days() {
	return max( 0, (int) get_option( 'apprex_ldm_follow', 7 ) );
}
function apprex_ldm_sender_block() {
	$d = "合同会社アイズ（APPREX）\n福島県いわき市四倉町細谷字大町1番\nメール：" . apprex_ldm_from() . "\n" . home_url( '/' );
	$v = (string) get_option( 'apprex_ldm_sender', '' );
	return '' !== trim( $v ) ? $v : $d;
}

/** 配信停止したアドレス（小文字）。 */
function apprex_ldm_blocked( $email ) {
	$b = (array) get_option( 'apprex_ldm_block', array() );
	return isset( $b[ strtolower( trim( (string) $email ) ) ] );
}

/** 署名（リンクの改ざん防止）。 */
function apprex_ldm_sig( $id ) {
	return substr( hash_hmac( 'sha256', 'ldm' . (int) $id, wp_salt( 'auth' ) ), 0, 12 );
}
function apprex_ldm_link( $id ) {
	return add_query_arg( array( 'src' => 'mail', 'ldm' => (int) $id . '.' . apprex_ldm_sig( $id ) ), home_url( '/studio/' ) );
}
function apprex_ldm_unsub_link( $id ) {
	return add_query_arg( 'ldu', (int) $id . '.' . apprex_ldm_sig( $id ), home_url( '/' ) );
}

/** 本文の最後に必ず付ける部分。 */
function apprex_ldm_footer( $id ) {
	return "\n\n――――――――――\n" . apprex_ldm_sender_block()
		. "\n\nこのメールは、貴店のホームページで公開されているアドレスへお送りしています。"
		. "\n今後のご案内が不要な場合は、お手数ですが下のリンクを押すか、このメールに「配信停止」とご返信ください。\n" . apprex_ldm_unsub_link( $id );
}

/** 送った記録（壊れた行を除く）。 */
function apprex_ldm_hist( $id ) {
	return array_values( array_filter( apprex_ld_meta_list( $id, 'apprex_ld_mails' ), 'is_array' ) );
}
function apprex_ldm_is_form( $to ) {
	return 0 === strpos( (string) $to, 'form:' );
}

/** 今日送った数。 */
function apprex_ldm_sent_today( $add = 0 ) {
	$d = (array) get_option( 'apprex_ldm_day', array() );
	$t = wp_date( 'Y-m-d' );
	$n = ( ( $d[0] ?? '' ) === $t ) ? (int) ( $d[1] ?? 0 ) : 0;
	if ( $add ) {
		$n += $add;
		update_option( 'apprex_ldm_day', array( $t, $n ), false );
	}
	return $n;
}

/** これまでに送った数（自動に切り替える目安）。 */
function apprex_ldm_total_sent() {
	return (int) get_option( 'apprex_ldm_total', 0 );
}

/* =========================================================================
 * 下書きを作る
 * ====================================================================== */

/**
 * メールを送ってよいお店か。
 *
 * @return string|WP_Error 送り先アドレス。
 */
function apprex_ldm_target( $id, $kind = 'first' ) {
	if ( 'apprex_lead' !== get_post_type( $id ) ) {
		return new WP_Error( 'ldm', 'お店が見つかりません。' );
	}
	if ( get_post_meta( $id, 'apprex_ld_ng', true ) ) {
		return new WP_Error( 'ldm', '「営業お断り」の記載があるので送りません。' );
	}
	if ( get_post_meta( $id, 'apprex_ld_unsub', true ) ) {
		return new WP_Error( 'ldm', '配信停止になっています。' );
	}
	$st = (string) get_post_meta( $id, 'apprex_ld_status', true );
	if ( 'first' === $kind && ! in_array( $st, array( 'new', 'checked' ), true ) ) {
		return new WP_Error( 'ldm', '状態が「' . ( apprex_ld_statuses()[ $st ] ?? $st ) . '」なので、はじめのメールは作りません。' );
	}
	if ( 'follow' === $kind && 'sent' !== $st ) {
		return new WP_Error( 'ldm', '返事がまだのお店だけに、もう1通送れます。' );
	}
	$em = array_values( array_filter( apprex_ld_meta_list( $id, 'apprex_ld_emails' ), 'is_email' ) );
	if ( ! $em ) {
		$form = (string) get_post_meta( $id, 'apprex_ld_form', true );
		if ( '' !== $form ) {
			return 'form:' . $form; // メールはないが、問い合わせフォームはある（人がフォームに貼って送る）。
		}
		return new WP_Error( 'ldm', get_post_meta( $id, 'apprex_ld_researched', true ) ? '公開されているメールアドレスも、問い合わせフォームも見つかりませんでした。電話かSNSで連絡してください。' : '公開されているメールアドレスがありません（先に「ホームページを調べる」）。' );
	}
	foreach ( $em as $e ) {
		if ( apprex_ldm_blocked( $e ) ) {
			return new WP_Error( 'ldm', '配信停止のアドレスです。' );
		}
	}
	// ほかのお店（同じ会社の別店舗など）で、もう送っているアドレスには、はじめのメールを送らない。
	$to   = strtolower( $em[0] );
	$sent = (array) get_option( 'apprex_ldm_sent_to', array() );
	if ( 'first' === $kind && isset( $sent[ $to ] ) && (int) $sent[ $to ] !== (int) $id ) {
		return new WP_Error( 'ldm', 'このアドレスには、ほかのお店としてもう送っています。' );
	}
	return $to;
}

/**
 * AIで下書きを作る（AIが使えないときはひな形）。
 *
 * @return true|WP_Error
 */
function apprex_ldm_make_draft( $id, $kind = 'first' ) {
	$to = apprex_ldm_target( $id, $kind );
	if ( is_wp_error( $to ) ) {
		return $to;
	}
	$g = function_exists( 'apprex_ld_google' ) ? apprex_ld_google( $id ) : null;
	if ( ! $g ) {
		$g = apprex_ld_google( $id, true );
	}
	$name  = $g ? $g['name'] : get_the_title( $id );
	$gk    = (string) get_post_meta( $id, 'apprex_ld_genre', true );
	$gs    = apprex_ld_genres();
	$glab  = $gs[ $gk ][0] ?? '';
	$ghint = $gs[ $gk ][3] ?? '';
	$hook  = (string) get_post_meta( $id, 'apprex_ld_hook', true );
	$why   = implode( '・', (array) get_post_meta( $id, 'apprex_ld_why', true ) );
	$flg   = (array) get_post_meta( $id, 'apprex_ld_flags', true );
	$labs  = array( 'app' => 'もうアプリがある', 'line' => 'LINE公式あり', 'insta' => 'Instagramあり', 'web_book' => 'ネット予約あり', 'phone_only' => '予約は電話だけ', 'paper' => '紙のポイントカード' );
	$facts = array();
	foreach ( $labs as $k => $l ) {
		if ( ! empty( $flg[ $k ] ) ) {
			$facts[] = $l;
		}
	}
	$link = apprex_ldm_link( $id );
	// 運営会社のサイトで見つけたアドレスなら、運営会社あてにする。
	$src  = (array) get_post_meta( $id, 'apprex_ld_email_src', true );
	$op   = (string) get_post_meta( $id, 'apprex_ld_operator', true );
	$toop = ! apprex_ldm_is_form( $to ) && 'operator' === ( $src[ $to ][1] ?? '' );
	$dear = $toop ? ( '' !== $op ? $op : $name . 'の運営会社' ) . ' ご担当者様' : $name . ' ご担当者様';
	$subj = '';
	$body = '';
	if ( function_exists( 'apprex_ld_ai_ready' ) && apprex_ld_ai_ready() ) {
		$sys = 'あなたはアプリ制作サービス「APPREX」（合同会社アイズ・福島県いわき市）の営業担当です。お店に、はじめて送る短い営業メールを書きます。'
			. '守ること：事実として分かっていることだけを使う。褒めすぎない。「突然のご連絡失礼いたします」で始める。押し売りしない。誇張・断定・数字の約束をしない。'
			. '絵文字は使わない。本文は350〜500字。最後に、無料でアプリの見本を作れるページのURLを1回だけ入れる。署名や配信停止の文は書かない（こちらで付ける）。';
		$ask = 'follow' === $kind
			? "前に送ったメールに返事がないお店へ、2通目（最後のご案内）を書いてください。短く（200〜300字）、しつこくならないように。\n"
			: "はじめてのメールを書いてください。\n";
		$msg = $ask
			. "お店：{$name}（{$glab}" . ( $g ? '・' . $g['address'] : '' ) . "）\n"
			. ( $g ? "口コミ：{$g['reviews']}件・評価{$g['rating']}\n" : '' )
			. 'ホームページで分かったこと：' . ( $facts ? implode( '・', $facts ) : '特になし' ) . "\n"
			. 'アプリに向く理由：' . $why . "\n"
			. ( '' !== $hook ? '提案のきっかけ：' . $hook . "\n" : '' )
			. ( '' !== $ghint ? 'この業種でアプリが役立つこと：' . $ghint . "\n" : '' )
			. "無料でアプリの見本を作れるページ：{$link}\n\n"
			. ( $toop ? "このメールは、お店ではなく運営会社（{$op}）あてです。「{$name}」についてのご提案だと分かるように書いてください。\n" : '' )
			. ( apprex_ldm_is_form( $to ) ? "このメールは、お店の問い合わせフォームに貼って送ります。\n" : '' )
			. "形式：1行目に「件名：」から始めて件名（30字以内）、2行目から本文。宛名は「{$dear}」。";
		$r = apprex_openrouter_complete( array( array( 'role' => 'system', 'content' => $sys ), array( 'role' => 'user', 'content' => $msg ) ), apprex_ld_ai_args( array( 'max_tokens' => 900, 'temperature' => 0.5, 'timeout' => 60 ) ) );
		if ( ! is_wp_error( $r ) ) {
			$t = trim( str_replace( "\r", '', wp_strip_all_tags( (string) $r ) ) );
			if ( preg_match( '/^件名[:：]\s*(.+)\n+([\s\S]+)$/u', $t, $m ) ) {
				$subj = trim( $m[1] );
				$body = trim( $m[2] );
			}
		}
	}
	if ( '' === $body || false === strpos( $body, $link ) ) {
		// ひな形（AIが使えない・URLが抜けたとき）。
		$subj = 'follow' === $kind ? '【APPREX】先日のご案内について' : '【' . $name . '様】お店のアプリを無料で試せます';
		$body = $dear . "\n" . ( $toop ? '（' . $name . "について、ご案内です）\n" : '' ) . "\n"
			. ( 'follow' === $kind
				? "先日、お店のアプリについてご案内をお送りした APPREX（合同会社アイズ）です。\nお忙しいところ失礼いたします。最後にもう一度だけご案内させてください。\n\n"
				: "突然のご連絡失礼いたします。福島県いわき市でアプリ制作をしている APPREX（合同会社アイズ）です。\nホームページを拝見し、ご連絡いたしました。\n\n" )
			. ( '' !== $hook ? $hook . "\n\n" : '' )
			. ( '' !== $ghint ? $glab . 'でアプリが役立つところ：' . rtrim( $ghint, '。' ) . "。\n\n" : '' )
			. "下のページから、お店のアプリの見本を無料で作れます（登録だけで、費用はかかりません）。\n" . $link
			. "\n\nご興味があれば、このメールにご返信いただくだけで大丈夫です。";
	}
	if ( false === strpos( $body, $link ) ) {
		$body .= "\n\n" . $link;
	}
	update_post_meta( $id, 'apprex_ld_draft', array( 'to' => $to, 'subject' => mb_substr( $subj, 0, 80 ), 'body' => $body, 'kind' => $kind, 'created' => time() ) );
	apprex_ld_log( $id, ( 'follow' === $kind ? '2通目' : 'メール' ) . 'の下書きを作りました（' . ( apprex_ldm_is_form( $to ) ? '問い合わせフォーム用' : $to ) . '）' );
	return true;
}

/* =========================================================================
 * 送る
 * ====================================================================== */

/**
 * 下書きを送る。
 *
 * @param int   $id   お店。
 * @param array $edit 画面で直した件名・本文（なければ下書きのまま）。
 * @return true|WP_Error
 */
function apprex_ldm_send( $id, $edit = array() ) {
	$d = get_post_meta( $id, 'apprex_ld_draft', true );
	if ( ! is_array( $d ) || empty( $d['body'] ) ) {
		return new WP_Error( 'ldm', '下書きがありません。' );
	}
	$to = apprex_ldm_target( $id, $d['kind'] ?? 'first' );
	if ( is_wp_error( $to ) ) {
		delete_post_meta( $id, 'apprex_ld_draft' );
		return $to;
	}
	$isf = apprex_ldm_is_form( $to );
	if ( ! $isf && apprex_ldm_sent_today() >= apprex_ldm_daily() ) {
		return new WP_Error( 'ldm', '今日の上限（' . apprex_ldm_daily() . '通）に達しました。明日送れます。' );
	}
	$subj = isset( $edit['subject'] ) && '' !== trim( $edit['subject'] ) ? $edit['subject'] : $d['subject'];
	$body = isset( $edit['body'] ) && '' !== trim( $edit['body'] ) ? $edit['body'] : $d['body'];
	$body = str_replace( "\r", '', $body ) . apprex_ldm_footer( $id );
	$from = apprex_ldm_from();
	$hdr  = array(
		'From: ' . apprex_ldm_from_name() . ' <' . $from . '>',
		'Reply-To: ' . $from,
		'Content-Type: text/plain; charset=UTF-8',
		'List-Unsubscribe: <' . apprex_ldm_unsub_link( $id ) . '>, <mailto:' . $from . '?subject=' . rawurlencode( '配信停止' ) . '>',
	);
	$ok = $isf ? true : wp_mail( $to, $subj, $body, $hdr ); // フォームは、人が貼って送ったあとに記録するだけ。
	$h  = apprex_ldm_hist( $id );
	$h[] = array( time(), $to, $subj, $d['kind'] ?? 'first', $ok ? 1 : 0, get_current_user_id() );
	update_post_meta( $id, 'apprex_ld_mails', array_slice( $h, -20 ) );
	if ( ! $ok ) {
		apprex_ld_log( $id, 'メールを送れませんでした（' . $to . '）。メールの送信設定を確かめてください。' );
		return new WP_Error( 'ldm', 'メールを送れませんでした。「設定 → メール送信」の設定を確かめてください。' );
	}
	delete_post_meta( $id, 'apprex_ld_draft' );
	update_post_meta( $id, 'apprex_ld_last_mail', time() );
	if ( in_array( (string) get_post_meta( $id, 'apprex_ld_status', true ), array( 'new', 'checked' ), true ) ) {
		update_post_meta( $id, 'apprex_ld_status', 'sent' );
	}
	$s          = (array) get_option( 'apprex_ldm_sent_to', array() );
	$s[ $to ]   = (int) $id;
	update_option( 'apprex_ldm_sent_to', $s, false );
	if ( ! $isf ) {
		apprex_ldm_sent_today( 1 );
	}
	update_option( 'apprex_ldm_total', apprex_ldm_total_sent() + 1, false );
	apprex_ld_log( $id, ( 'follow' === ( $d['kind'] ?? '' ) ? '2通目' : 'メール' ) . ( $isf ? 'を問い合わせフォームから送りました（' . substr( $to, 5 ) : 'を送りました（' . $to ) . '・件名「' . $subj . '」）' );
	return true;
}

/** 下書きがあるお店（古い順）。 */
function apprex_ldm_drafts() {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_lead',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_ld_draft', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare'   => 'EXISTS',
		)
	);
	usort(
		$ids,
		function ( $a, $b ) {
			return (int) ( get_post_meta( $a, 'apprex_ld_draft', true )['created'] ?? 0 ) <=> (int) ( get_post_meta( $b, 'apprex_ld_draft', true )['created'] ?? 0 );
		}
	);
	return $ids;
}

/* =========================================================================
 * 自動（1時間ごと）：下書きを作る → 2通目の下書き → 自動モードなら送る → 確認待ちを知らせる
 * ====================================================================== */

function apprex_ldm_cron_run() {
	if ( ! get_option( 'apprex_ldm_on', 0 ) ) {
		return;
	}
	$made = 0;
	$cap  = apprex_ldm_daily();
	// 1) 調べ終わって、メールがあり、点数の高いお店の下書き（1日に「1日の上限」件まで）。
	$dm = (array) get_option( 'apprex_ldm_made', array() );
	$t  = wp_date( 'Y-m-d' );
	$mc = ( ( $dm[0] ?? '' ) === $t ) ? (int) $dm[1] : 0;
	if ( $mc < $cap ) {
		$ids = get_posts(
			array(
				'post_type'      => 'apprex_lead',
				'post_status'    => 'any',
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => 'apprex_ld_status', 'value' => 'checked' ),
					array( 'key' => 'apprex_ld_draft', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'apprex_ld_last_mail', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'apprex_ld_score', 'value' => apprex_ldm_min(), 'compare' => '>=', 'type' => 'NUMERIC' ),
				),
			)
		);
		usort(
			$ids,
			function ( $a, $b ) {
				return (int) get_post_meta( $b, 'apprex_ld_score', true ) <=> (int) get_post_meta( $a, 'apprex_ld_score', true );
			}
		);
		foreach ( $ids as $id ) {
			if ( $mc >= $cap ) {
				break;
			}
			if ( is_wp_error( apprex_ldm_target( $id ) ) ) {
				continue;
			}
			if ( true === apprex_ldm_make_draft( $id ) ) {
				$mc++;
				$made++;
			}
		}
	}
	// 2) 返事がないお店に、2通目の下書き（1回だけ）。
	$fd = apprex_ldm_follow_days();
	if ( $fd && $mc < $cap ) {
		$ids = get_posts(
			array(
				'post_type'      => 'apprex_lead',
				'post_status'    => 'any',
				'posts_per_page' => 30,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array( 'key' => 'apprex_ld_status', 'value' => 'sent' ),
					array( 'key' => 'apprex_ld_draft', 'compare' => 'NOT EXISTS' ),
					array( 'key' => 'apprex_ld_last_mail', 'value' => time() - $fd * DAY_IN_SECONDS, 'compare' => '<', 'type' => 'NUMERIC' ),
				),
			)
		);
		foreach ( $ids as $id ) {
			if ( $mc >= $cap ) {
				break;
			}
			$kinds = wp_list_pluck( array_map( function ( $m ) {
				return array( 'k' => $m[3] ?? '' );
			}, apprex_ldm_hist( $id ) ), 'k' );
			$apprex_h = apprex_ldm_hist( $id );
			if ( in_array( 'follow', $kinds, true ) || get_post_meta( $id, 'apprex_ld_click', true ) || ( $apprex_h && apprex_ldm_is_form( end( $apprex_h )[1] ?? '' ) ) ) {
				continue; // 2通目は1回だけ。リンクを開いた人には、人が連絡する。
			}
			if ( true === apprex_ldm_make_draft( $id, 'follow' ) ) {
				$mc++;
				$made++;
			}
		}
	}
	update_option( 'apprex_ldm_made', array( $t, $mc ), false );

	// 3) 自動で送る（平日の10〜17時だけ）。
	$sent = 0;
	$h    = (int) wp_date( 'G' );
	$w    = (int) wp_date( 'N' );
	if ( 'auto' === apprex_ldm_mode() && $w <= 5 && $h >= 10 && $h < 17 ) {
		foreach ( apprex_ldm_drafts() as $id ) {
			if ( apprex_ldm_sent_today() >= apprex_ldm_daily() ) {
				break;
			}
			if ( apprex_ldm_is_form( get_post_meta( $id, 'apprex_ld_draft', true )['to'] ?? '' ) ) {
				continue; // フォームは人が送る。
			}
			if ( true === apprex_ldm_send( $id ) ) {
				$sent++;
			}
		}
	}

	// 4) 知らせる。
	if ( function_exists( 'apprex_notify' ) ) {
		$url = admin_url( 'admin.php?page=apprex-leads#ldm' );
		if ( $sent ) {
			apprex_notify( 'sales', '📨 AI営業メールを自動で ' . $sent . '通送りました（今日 ' . apprex_ldm_sent_today() . '/' . apprex_ldm_daily() . '通）' . "\n" . $url );
		}
		$wait = count( apprex_ldm_drafts() );
		$last = (string) get_option( 'apprex_ldm_notified', '' );
		if ( 'review' === apprex_ldm_mode() && $wait && $last !== $t && $h >= 9 ) {
			update_option( 'apprex_ldm_notified', $t, false );
			apprex_notify( 'sales', '✉️ 送る前の確認を待っている営業メールが ' . $wait . '件あります' . ( $made ? '（新しく ' . $made . '件）' : '' ) . "\n" . $url );
		}
	}
}

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'apprex_ldm_cron' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'apprex_ldm_cron' );
		}
	}
);
add_action( 'apprex_ldm_cron', 'apprex_ldm_cron_run' );
add_filter(
	'apprex_cron_hooks',
	function ( $h ) {
		if ( get_option( 'apprex_ldm_on', 0 ) ) {
			$h['apprex_ldm_cron'] = 'AI営業メール';
		}
		return $h;
	}
);

/* =========================================================================
 * メールのリンク：開いた・配信停止・お問い合わせ
 * ====================================================================== */

/** 「ID.署名」を確かめてIDを返す。 */
function apprex_ldm_parse( $v ) {
	$p = explode( '.', (string) $v, 2 );
	$id = (int) ( $p[0] ?? 0 );
	return ( $id && 'apprex_lead' === get_post_type( $id ) && hash_equals( apprex_ldm_sig( $id ), (string) ( $p[1] ?? '' ) ) ) ? $id : 0;
}

add_action(
	'template_redirect',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['ldm'] ) ) {
			$id = apprex_ldm_parse( sanitize_text_field( wp_unslash( $_GET['ldm'] ) ) );
			if ( $id ) {
				setcookie( 'apprex_ldm', (string) $id, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
				$n = (int) get_post_meta( $id, 'apprex_ld_clicks', true ) + 1;
				update_post_meta( $id, 'apprex_ld_clicks', $n );
				if ( 1 === $n ) {
					update_post_meta( $id, 'apprex_ld_click', time() );
					apprex_ld_log( $id, 'メールのリンクを開きました' );
					$g = apprex_ld_google( $id );
					if ( function_exists( 'apprex_notify' ) ) {
						apprex_notify( 'sales', '👀 営業メールのリンクが開かれました：' . ( $g ? $g['name'] : get_the_title( $id ) ) . '（AIスタジオを見ています）' . "\n" . admin_url( 'admin.php?page=apprex-leads&lead=' . $id ) );
					}
				}
			}
			wp_safe_redirect( remove_query_arg( 'ldm' ) );
			exit;
		}
		if ( isset( $_GET['ldu'] ) ) {
			$id = apprex_ldm_parse( sanitize_text_field( wp_unslash( $_GET['ldu'] ) ) );
			if ( ! $id ) {
				wp_die( 'リンクが正しくありません。お手数ですが、メールに「配信停止」とご返信ください。', '配信停止', array( 'response' => 400 ) );
			}
			if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
				apprex_ldm_unsubscribe( $id, 'リンク' );
				wp_die( '<h1>配信を停止しました</h1><p>今後、合同会社アイズ（APPREX）からご案内のメールはお送りしません。ご迷惑をおかけしました。</p>', '配信停止', array( 'response' => 200 ) );
			}
			// メールソフトの自動チェックで止まらないよう、ボタンを押してから止める。
			$u = esc_url( add_query_arg( 'ldu', sanitize_text_field( wp_unslash( $_GET['ldu'] ) ), home_url( '/' ) ) );
			wp_die( '<h1>配信停止</h1><p>合同会社アイズ（APPREX）からのご案内メールを止めます。</p><form method="post" action="' . $u . '"><button type="submit" style="font-size:16px;padding:10px 22px">配信を停止する</button></form>', '配信停止', array( 'response' => 200 ) );
		}
		// phpcs:enable
	},
	1
);

/** 配信停止にする。 */
function apprex_ldm_unsubscribe( $id, $how = '' ) {
	$b = (array) get_option( 'apprex_ldm_block', array() );
	foreach ( apprex_ld_meta_list( $id, 'apprex_ld_emails' ) as $e ) {
		$b[ strtolower( trim( (string) $e ) ) ] = time();
	}
	update_option( 'apprex_ldm_block', $b, false );
	update_post_meta( $id, 'apprex_ld_unsub', time() );
	update_post_meta( $id, 'apprex_ld_status', 'skip' );
	delete_post_meta( $id, 'apprex_ld_draft' );
	apprex_ld_log( $id, '配信停止になりました' . ( $how ? '（' . $how . '）' : '' ) );
	if ( function_exists( 'apprex_notify' ) ) {
		$g = apprex_ld_google( $id );
		apprex_notify( 'sales', '🚫 営業メールの配信停止：' . ( $g ? $g['name'] : get_the_title( $id ) ) );
	}
}

// メールから来た人がお問い合わせ・申し込みをしたら、お店の記録に残して「返信あり」にする。
add_action(
	'apprex_inquiry_submitted',
	function ( $post_id ) {
		$id = isset( $_COOKIE['apprex_ldm'] ) ? (int) $_COOKIE['apprex_ldm'] : 0;
		if ( ! $id || 'apprex_lead' !== get_post_type( $id ) ) {
			return;
		}
		update_post_meta( $post_id, 'apprex_ldm_lead', $id );
		apprex_ld_log( $id, 'メールのあと、お問い合わせ・申し込みがありました（#' . (int) $post_id . '）' );
		if ( in_array( (string) get_post_meta( $id, 'apprex_ld_status', true ), array( 'new', 'checked', 'sent' ), true ) ) {
			update_post_meta( $id, 'apprex_ld_status', 'replied' );
		}
		if ( function_exists( 'apprex_notify' ) ) {
			$g = apprex_ld_google( $id );
			apprex_notify( 'sales', '🎉 営業メールからお問い合わせ：' . ( $g ? $g['name'] : get_the_title( $id ) ) . "\n" . admin_url( 'admin.php?page=apprex-leads&lead=' . $id ) );
		}
	},
	30
);

/* =========================================================================
 * 画面の操作
 * ====================================================================== */

add_action(
	'admin_init',
	function () {
		foreach ( array( 'apprex_ldm_on', 'apprex_ldm_daily', 'apprex_ldm_min', 'apprex_ldm_follow' ) as $k ) {
			register_setting( 'apprex_ldm', $k, array( 'sanitize_callback' => 'absint' ) );
		}
		register_setting( 'apprex_ldm', 'apprex_ldm_mode', array( 'sanitize_callback' => function ( $v ) {
			return 'auto' === $v ? 'auto' : 'review';
		} ) );
		register_setting( 'apprex_ldm', 'apprex_ldm_from', array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'apprex_ldm', 'apprex_ldm_from_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'apprex_ldm', 'apprex_ldm_sender', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	}
);

add_action(
	'admin_post_apprex_ldm',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_ldm' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$ids  = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
		$id   = absint( $_POST['id'] ?? 0 );
		$msg  = '';
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=apprex-leads' );
		if ( 'draft' === $do || 'redraft' === $do || 'follow' === $do ) {
			$list = $id ? array( $id ) : array_slice( $ids, 0, 10 );
			$ok   = 0;
			$errs = array();
			foreach ( $list as $i ) {
				$r = apprex_ldm_make_draft( $i, 'follow' === $do ? 'follow' : ( (array) get_post_meta( $i, 'apprex_ld_draft', true ) )['kind'] ?? 'first' );
				if ( true === $r ) {
					$ok++;
				} elseif ( is_wp_error( $r ) ) {
					$errs[ $r->get_error_message() ] = true;
				}
			}
			$msg = $ok . '件の下書きを作りました。下の「送る前の確認」で読んでから送ってください。' . ( $errs ? '（作れなかった理由：' . implode( '／', array_keys( $errs ) ) . '）' : '' );
			$back = admin_url( 'admin.php?page=apprex-leads#ldm' );
		} elseif ( 'send' === $do ) {
			$r   = apprex_ldm_send(
				$id,
				array(
					'subject' => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
					'body'    => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
				)
			);
			$msg = is_wp_error( $r ) ? '送れませんでした：' . $r->get_error_message() : 'メールを送りました。返事は ' . apprex_ldm_from() . ' に届きます。';
		} elseif ( 'save' === $do ) {
			$d = get_post_meta( $id, 'apprex_ld_draft', true );
			if ( is_array( $d ) ) {
				$d['subject'] = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
				$d['body']    = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
				update_post_meta( $id, 'apprex_ld_draft', $d );
				$msg = '下書きを保存しました。';
			}
		} elseif ( 'drop' === $do ) {
			delete_post_meta( $id, 'apprex_ld_draft' );
			if ( ! empty( $_POST['skip'] ) ) {
				update_post_meta( $id, 'apprex_ld_status', 'skip' );
			}
			apprex_ld_log( $id, 'メールの下書きを送らずに消しました' . ( ! empty( $_POST['skip'] ) ? '（対象外にしました）' : '' ) );
			$msg = '送らずに消しました。';
		} elseif ( 'unsub' === $do ) {
			apprex_ldm_unsubscribe( $id, '返信で依頼' );
			$msg = '配信停止にしました。このお店のアドレスには、もう送りません。';
		} elseif ( 'test' === $do ) {
			$to  = wp_get_current_user()->user_email;
			$ok  = wp_mail( $to, '【テスト】AI営業メールの送信テスト', "AI営業メールの送信テストです。\nこのメールが届けば、送る準備ができています。\n差出人：" . apprex_ldm_from_name() . ' <' . apprex_ldm_from() . '>' . apprex_ldm_footer( 0 ), array( 'From: ' . apprex_ldm_from_name() . ' <' . apprex_ldm_from() . '>', 'Reply-To: ' . apprex_ldm_from() ) );
			$msg = $ok ? 'テストメールを ' . $to . ' に送りました。届いたか、迷惑メールに入っていないか確かめてください。' : 'テストメールを送れませんでした。メールの送信設定（SMTP）を確かめてください。';
		} elseif ( 'runnow' === $do ) {
			apprex_ldm_cron_run();
			$msg = 'いまの状態で下書きづくりを動かしました。';
			$back = admin_url( 'admin.php?page=apprex-leads#ldm' );
		}
		wp_safe_redirect( add_query_arg( 'ldmsg', rawurlencode( $msg ), $back ) );
		exit;
	}
);

/* =========================================================================
 * 画面：AI営業リストの中に出す部分
 * ====================================================================== */

/** 一覧ページ：送る前の確認＋メールの設定。 */
function apprex_ldm_box() {
	$on     = (int) get_option( 'apprex_ldm_on', 0 );
	$mode   = apprex_ldm_mode();
	$drafts = apprex_ldm_drafts();
	$smtp   = function_exists( 'apprex_smtp_ready' ) && apprex_smtp_ready();
	$total  = apprex_ldm_total_sent();
	$act    = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<h2 id="ldm">営業メール <span style="font-size:13px;font-weight:400;color:#646970">（今日 <?php echo (int) apprex_ldm_sent_today(); ?> / <?php echo (int) apprex_ldm_daily(); ?>通・これまで <?php echo (int) $total; ?>通）</span></h2>
	<?php if ( ! $smtp ) : ?>
		<div class="notice notice-warning inline"><p>メールの送信設定（SMTP）がまだです。<a href="<?php echo esc_url( admin_url( 'options-general.php?page=apprex-smtp' ) ); ?>">設定 → メール送信</a>で、<?php echo esc_html( apprex_ldm_from() ); ?> のアプリパスワードを入れると、迷惑メールに入りにくくなります。</p></div>
	<?php endif; ?>
	<p>流れ：調べたお店（公開メールあり・<?php echo (int) apprex_ldm_min(); ?>点以上）に、AIが1店ずつ違う文面の<b>下書き</b>を作ります → ここで読んで、直して、<b>送る</b>。返事は <?php echo esc_html( apprex_ldm_from() ); ?> に届きます。届いたらお店の状態を「返信あり」にしてください。<br>
	メールのリンク（無料でアプリの見本を作れるページ）を開くと通知が届きます。メールのあとにお問い合わせがあると、自動で「返信あり」になります。</p>

	<h3>送る前の確認（<?php echo count( $drafts ); ?>件）</h3>
	<?php if ( ! $drafts ) : ?>
		<p class="description">いま確認を待っている下書きはありません。リストでお店にチェックを入れて「メールの下書きを作る」を押すか、下の設定で「下書きを自動で作る」をオンにしてください。</p>
	<?php endif; ?>
	<?php foreach ( $drafts as $id ) : ?>
		<?php
		$d  = (array) get_post_meta( $id, 'apprex_ld_draft', true );
		$g  = apprex_ld_google( $id );
		$nm = $g ? $g['name'] : get_the_title( $id );
		?>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px;margin:0 0 12px;max-width:1000px">
			<div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap;margin-bottom:6px">
				<b style="font-size:15px"><a href="<?php echo esc_url( admin_url( 'admin.php?page=apprex-leads&lead=' . $id ) ); ?>"><?php echo esc_html( $nm ); ?></a></b>
				<span style="color:#00a32a;font-weight:700"><?php echo (int) get_post_meta( $id, 'apprex_ld_score', true ); ?>点</span>
				<span><?php echo 'follow' === ( $d['kind'] ?? '' ) ? '<b style="color:#996800">2通目（最後のご案内）</b>' : 'はじめてのメール'; ?></span>
				<?php $apprex_isf = apprex_ldm_is_form( $d['to'] ?? '' ); ?>
				<?php if ( $apprex_isf ) : ?>
					<span style="color:#646970">宛先：<a href="<?php echo esc_url( substr( $d['to'], 5 ) ); ?>" target="_blank" rel="noopener"><b>問い合わせフォーム</b></a>（メールがないので、フォームに貼って送ります）</span>
				<?php else : ?>
					<span style="color:#646970">宛先：<?php echo esc_html( $d['to'] ?? '' ); ?><?php $apprex_sr = (array) get_post_meta( $id, 'apprex_ld_email_src', true ); echo 'operator' === ( $apprex_sr[ $d['to'] ?? '' ][1] ?? '' ) ? '（運営会社）' : ''; ?></span>
				<?php endif; ?>
				<?php if ( $g && $g['site'] ) : ?><a href="<?php echo esc_url( $g['site'] ); ?>" target="_blank" rel="noopener" style="font-size:12px">ホームページ</a><?php endif; ?>
			</div>
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>">
				<input type="hidden" name="action" value="apprex_ldm"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ldm' ); ?>
				<p style="margin:0 0 6px"><input name="subject" value="<?php echo esc_attr( $d['subject'] ?? '' ); ?>" style="width:100%" aria-label="件名"></p>
				<textarea name="body" rows="10" style="width:100%;font-size:13px;line-height:1.6" aria-label="本文"><?php echo esc_textarea( $d['body'] ?? '' ); ?></textarea>
				<details style="margin:4px 0 8px;color:#646970;font-size:12px"><summary style="cursor:pointer">最後に自動で付く部分（送信者・住所・配信停止）</summary><pre style="white-space:pre-wrap;font-size:12px"><?php echo esc_html( apprex_ldm_footer( $id ) ); ?></pre></details>
				<div style="display:flex;gap:6px;flex-wrap:wrap">
					<?php if ( $apprex_isf ) : ?>
						<button type="button" class="button" onclick="var f=this.form,t=f.subject.value+'\n\n'+f.body.value+f.querySelector('pre').textContent;navigator.clipboard.writeText(t).then(function(){alert('件名と本文をコピーしました。フォームに貼って送ってください。');});window.open('<?php echo esc_js( esc_url( substr( $d['to'], 5 ) ) ); ?>','_blank');">本文をコピーしてフォームを開く</button>
						<button class="button button-primary" name="do" value="send" onclick="return confirm('フォームから送りましたか？「送った」と記録します。')">フォームから送った（記録する）</button>
					<?php else : ?>
						<button class="button button-primary" name="do" value="send" onclick="return confirm('<?php echo esc_js( $nm ); ?> にこのメールを送ります。よろしいですか？')">この内容で送る</button>
					<?php endif; ?>
					<button class="button" name="do" value="save">直したところを保存</button>
					<button class="button" name="do" value="redraft">AIで書き直す</button>
					<button class="button" name="do" value="drop">送らない</button>
					<button class="button" name="do" value="drop" onclick="this.form.insertAdjacentHTML('beforeend','<input type=hidden name=skip value=1>')">送らない（このお店は対象外）</button>
				</div>
			</form>
		</div>
	<?php endforeach; ?>

	<h3>メールの設定</h3>
	<form method="post" action="options.php" style="max-width:1000px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:8px 16px">
		<?php settings_fields( 'apprex_ldm' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>下書きを自動で作る</th><td><label><input type="checkbox" name="apprex_ldm_on" value="1" <?php checked( $on ); ?>> 1時間ごとに、調べ終わったお店の下書きを作る（2通目も）</label></td></tr>
			<tr><th>送り方</th><td>
				<label style="display:block;margin-bottom:6px"><input type="radio" name="apprex_ldm_mode" value="review" <?php checked( $mode, 'review' ); ?>> <b>送る前に確認する</b>（おすすめ。下書きができたら通知が届きます）</label>
				<label style="display:block"><input type="radio" name="apprex_ldm_mode" value="auto" <?php checked( $mode, 'auto' ); ?>> <b>自動で送る</b>（平日10〜17時に、1日の上限まで）</label>
				<p class="description"><?php echo $total < 20 ? '目安：確認して20通ほど送り、AIの文面に直すところがほとんどなくなってから「自動で送る」にしてください（これまで ' . (int) $total . '通）。' : 'これまで ' . (int) $total . '通送りました。文面に慣れてきたら「自動で送る」にできます。'; ?></p>
			</td></tr>
			<tr><th>1日の上限</th><td><select name="apprex_ldm_daily"><?php foreach ( array( 3, 5, 10, 15, 20, 30 ) as $n ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( apprex_ldm_daily(), $n ); ?>><?php echo (int) $n; ?>通</option><?php endforeach; ?></select>
				<p class="description">一度にたくさん送ると迷惑メールに入りやすくなります。はじめは1日10通までがおすすめです。</p></td></tr>
			<tr><th>送るお店</th><td><select name="apprex_ldm_min"><?php for ( $n = 30; $n <= 90; $n += 10 ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( apprex_ldm_min(), $n ); ?>><?php echo (int) $n; ?>点以上</option><?php endfor; ?></select>　のお店（公開メールあり・「営業お断り」なし・配信停止なし）</td></tr>
			<tr><th>2通目</th><td><select name="apprex_ldm_follow"><?php foreach ( array( 0 => '送らない', 5 => '5日たっても返事がなければ', 7 => '7日たっても返事がなければ', 10 => '10日たっても返事がなければ', 14 => '14日たっても返事がなければ' ) as $n => $l ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( apprex_ldm_follow_days(), $n ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>　1回だけ短いご案内を送ります（リンクを開いたお店は、人が連絡するので送りません）</td></tr>
			<tr><th><label for="apprex_ldm_from">送信元</label></th><td><input id="apprex_ldm_from" name="apprex_ldm_from" value="<?php echo esc_attr( apprex_ldm_from() ); ?>" class="regular-text">　名前 <input name="apprex_ldm_from_name" value="<?php echo esc_attr( apprex_ldm_from_name() ); ?>" style="width:220px"></td></tr>
			<tr><th><label for="apprex_ldm_sender">送信者の情報</label></th><td><textarea id="apprex_ldm_sender" name="apprex_ldm_sender" rows="4" class="large-text"><?php echo esc_textarea( apprex_ldm_sender_block() ); ?></textarea>
				<p class="description">法律（特定電子メール法）で、営業メールには送信者の名前・住所・連絡先と、配信停止の方法を入れることになっています。毎回、本文の最後に自動で付けます。</p></td></tr>
		</table>
		<?php submit_button( 'メールの設定を保存' ); ?>
	</form>
	<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>"><input type="hidden" name="action" value="apprex_ldm"><input type="hidden" name="do" value="test"><?php wp_nonce_field( 'apprex_ldm' ); ?><?php submit_button( '自分あてにテストメールを送る', 'secondary', 'submit', false ); ?></form>
		<?php if ( $on ) : ?>
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>"><input type="hidden" name="action" value="apprex_ldm"><input type="hidden" name="do" value="runnow"><?php wp_nonce_field( 'apprex_ldm' ); ?><?php submit_button( 'いま下書きを作る', 'secondary', 'submit', false ); ?></form>
		<?php endif; ?>
	</div>
	<?php
}

/** お店の詳しい画面：メールの記録と操作。 */
function apprex_ldm_detail_box( $id ) {
	$h   = array_reverse( apprex_ldm_hist( $id ) );
	$d   = get_post_meta( $id, 'apprex_ld_draft', true );
	$act = esc_url( admin_url( 'admin-post.php' ) );
	$clk = (int) get_post_meta( $id, 'apprex_ld_click', true );
	?>
	<h3>営業メール</h3>
	<p>
		<?php echo $clk ? '👀 リンクを開いた：' . esc_html( wp_date( 'n/j G:i', $clk ) ) . '（' . (int) get_post_meta( $id, 'apprex_ld_clicks', true ) . '回）　' : ''; ?>
		<?php echo get_post_meta( $id, 'apprex_ld_unsub', true ) ? '<b style="color:#d63638">配信停止</b>　' : ''; ?>
		<?php echo is_array( $d ) && $d ? '<a href="' . esc_url( admin_url( 'admin.php?page=apprex-leads#ldm' ) ) . '">下書きがあります（送る前の確認へ）</a>' : ''; ?>
	</p>
	<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
		<?php if ( ! is_wp_error( apprex_ldm_target( $id ) ) ) : ?>
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>"><input type="hidden" name="action" value="apprex_ldm"><input type="hidden" name="do" value="draft"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ldm' ); ?><?php submit_button( 'メールの下書きを作る（AI）', 'secondary', 'submit', false ); ?></form>
		<?php elseif ( ! is_wp_error( apprex_ldm_target( $id, 'follow' ) ) ) : ?>
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>"><input type="hidden" name="action" value="apprex_ldm"><input type="hidden" name="do" value="follow"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ldm' ); ?><?php submit_button( '2通目の下書きを作る', 'secondary', 'submit', false ); ?></form>
		<?php else : ?>
			<span class="description"><?php echo esc_html( apprex_ldm_target( $id )->get_error_message() ); ?></span>
		<?php endif; ?>
		<?php if ( apprex_ld_meta_list( $id, 'apprex_ld_emails' ) && ! get_post_meta( $id, 'apprex_ld_unsub', true ) ) : ?>
			<form method="post" action="<?php echo $act; // phpcs:ignore ?>" onsubmit="return confirm('このお店のアドレスを配信停止にします。よろしいですか？')"><input type="hidden" name="action" value="apprex_ldm"><input type="hidden" name="do" value="unsub"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ldm' ); ?><?php submit_button( '配信停止にする（返信で頼まれたとき）', 'delete', 'submit', false ); ?></form>
		<?php endif; ?>
	</div>
	<?php if ( $h ) : ?>
		<table class="widefat striped" style="max-width:900px"><thead><tr><th>送った日</th><th>宛先</th><th>件名</th><th></th></tr></thead><tbody>
		<?php foreach ( $h as $m ) : ?>
			<tr><td style="width:120px"><?php echo esc_html( wp_date( 'Y/n/j G:i', (int) $m[0] ) ); ?></td><td><?php echo esc_html( $m[1] ); ?></td><td><?php echo esc_html( $m[2] ); ?><?php echo 'follow' === ( $m[3] ?? '' ) ? '（2通目）' : ''; ?></td><td><?php echo ! empty( $m[4] ) ? '送信済み' : '<b style="color:#d63638">失敗</b>'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>
	<?php
}
