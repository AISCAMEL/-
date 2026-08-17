<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 審査フォーム離脱者への後追いメール
 * - チャットで「審査」CTAを押した瞬間、email+session+時刻を保留として記録
 * - /shinsa-2 のフォーム送信 or /apply-complete エンドポイント到達で「完了」に更新
 * - WP-Cron(10分間隔) が回り、指定分/時間経過した未完了の人に段階的にメール送信
 * - 送信元は設定 apply_followup_from（既定 carmelbuzzzzz@aisjaltd.com）を使用
 */

/* ========================= テーブル ========================= */

function carmel_cb_apply_table() {
	global $wpdb;
	return $wpdb->prefix . 'carmel_cb_apply_pending';
}

function carmel_cb_apply_ensure_table() {
	global $wpdb;
	$t = carmel_cb_apply_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $t (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		email VARCHAR(190) NOT NULL,
		name VARCHAR(190) NULL,
		session_id VARCHAR(64) NULL,
		page TEXT NULL,
		clicked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		stage TINYINT(1) NOT NULL DEFAULT 0,
		completed TINYINT(1) NOT NULL DEFAULT 0,
		last_sent_at TIMESTAMP NULL DEFAULT NULL,
		PRIMARY KEY (id),
		KEY email (email),
		KEY status (completed, stage)
	) $charset;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action( 'plugins_loaded', 'carmel_cb_apply_ensure_table' );

/* ========================= 記録 / 完了 ========================= */

function carmel_cb_apply_record_click( $email, $name = '', $sid = '', $page = '' ) {
	global $wpdb;
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) { return false; }
	carmel_cb_apply_ensure_table();
	$t = carmel_cb_apply_table();

	// 直近5分に同じメアドの記録があれば重複追加しない（連打対策）
	$recent = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $t WHERE email=%s AND completed=0 AND clicked_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
		$email
	) );
	if ( $recent > 0 ) { return true; }

	return (bool) $wpdb->insert( $t, array(
		'email'      => $email,
		'name'       => (string) $name,
		'session_id' => (string) $sid,
		'page'       => (string) $page,
		'stage'      => 0,
		'completed'  => 0,
	) );
}

function carmel_cb_apply_mark_complete( $email ) {
	global $wpdb;
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) { return 0; }
	carmel_cb_apply_ensure_table();
	$t = carmel_cb_apply_table();
	return (int) $wpdb->update( $t, array( 'completed' => 1 ), array( 'email' => $email, 'completed' => 0 ) );
}

/* ========================= REST ========================= */

add_action( 'rest_api_init', function () {
	$ns = 'carmel-cb/v1';
	register_rest_route( $ns, '/apply-click',    array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_apply_click',    'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/apply-complete', array( 'methods' => 'POST', 'callback' => 'carmel_cb_handle_apply_complete', 'permission_callback' => '__return_true' ) );
} );

function carmel_cb_handle_apply_click( WP_REST_Request $r ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['apply_followup_on'] ) ) { return new WP_REST_Response( array( 'ok' => false, 'skipped' => 'off' ), 200 ); }
	$b = $r->get_json_params();
	$sid   = sanitize_text_field( $b['session_id'] ?? '' );
	$name  = sanitize_text_field( $b['name'] ?? '' );
	$email = sanitize_email( $b['email'] ?? '' );
	$page  = esc_url_raw( $b['page'] ?? '' );

	// intake で保存済みなら、email が空でもそちらから拾う
	if ( $email === '' && function_exists( 'carmel_cb_visitor_key' ) && $sid !== '' ) {
		$v = get_transient( carmel_cb_visitor_key( $sid ) );
		if ( is_array( $v ) ) {
			$email = sanitize_email( $v['email'] ?? '' );
			if ( $name === '' ) { $name = sanitize_text_field( $v['name'] ?? '' ); }
		}
	}
	if ( ! is_email( $email ) ) { return new WP_REST_Response( array( 'ok' => false, 'skipped' => 'no_email' ), 200 ); }

	$ok = carmel_cb_apply_record_click( $email, $name, $sid, $page );
	// 会話離脱後追いは、審査ボタンを押した時点で「行動を起こした」→ 解決扱いにして送らない
	if ( function_exists( 'carmel_cb_convo_fu_resolve' ) ) { carmel_cb_convo_fu_resolve( $sid, $email ); }
	return new WP_REST_Response( array( 'ok' => (bool) $ok ), 200 );
}

function carmel_cb_handle_apply_complete( WP_REST_Request $r ) {
	$b = $r->get_json_params();
	$email = sanitize_email( $b['email'] ?? '' );
	if ( ! is_email( $email ) ) { return new WP_REST_Response( array( 'ok' => false ), 200 ); }
	$n = carmel_cb_apply_mark_complete( $email );
	return new WP_REST_Response( array( 'ok' => true, 'updated' => $n ), 200 );
}

/**
 * リード（lead）フォームが本審査で送信されたときにも完了扱いにする。
 * carmel_cb_handle_lead() の直後に呼べるフックが無いので、独自アクションを用意し、
 * leads.php 側で発火するよう連携（フック未登録でも動作に影響なし）。
 */
add_action( 'carmel_cb_lead_submitted', function ( $type, $data ) {
	if ( ( $type === 'apply' ) && ! empty( $data['email'] ) ) {
		carmel_cb_apply_mark_complete( $data['email'] );
	}
}, 10, 2 );

/* ========================= 配信停止（オプトアウト） ========================= */

function carmel_cb_optout_list() {
	$v = get_option( 'carmel_cb_optout', array() );
	return is_array( $v ) ? $v : array();
}
function carmel_cb_is_opted_out( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( $email === '' ) { return false; }
	return in_array( $email, carmel_cb_optout_list(), true );
}
function carmel_cb_opt_out( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! is_email( $email ) ) { return false; }
	$list = carmel_cb_optout_list();
	if ( ! in_array( $email, $list, true ) ) {
		$list[] = $email;
		update_option( 'carmel_cb_optout', array_values( $list ), false );
	}
	// 未送信の後追いも全部止める
	if ( function_exists( 'carmel_cb_apply_mark_complete' ) ) { carmel_cb_apply_mark_complete( $email ); }
	if ( function_exists( 'carmel_cb_convo_fu_resolve' ) )    { carmel_cb_convo_fu_resolve( '', $email ); }
	return true;
}
function carmel_cb_unsub_token( $email ) {
	return substr( hash_hmac( 'sha256', strtolower( trim( (string) $email ) ), wp_salt( 'auth' ) ), 0, 32 );
}
function carmel_cb_unsub_url( $email ) {
	$e = rawurlencode( strtolower( trim( (string) $email ) ) );
	$t = carmel_cb_unsub_token( $email );
	return rest_url( 'carmel-cb/v1/unsubscribe' ) . '?e=' . $e . '&t=' . $t;
}

/* 配信停止エンドポイント（GETで表示、POSTで確定） */
add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/unsubscribe', array( 'methods' => array( 'GET', 'POST' ), 'callback' => 'carmel_cb_handle_unsubscribe', 'permission_callback' => '__return_true' ) );
} );

function carmel_cb_handle_unsubscribe( WP_REST_Request $r ) {
	$email = sanitize_email( $r->get_param( 'e' ) );
	$token = (string) $r->get_param( 't' );
	if ( ! is_email( $email ) || $token === '' || ! hash_equals( carmel_cb_unsub_token( $email ), $token ) ) {
		return new WP_REST_Response( '<html><body style="font-family:sans-serif;padding:24px"><h2>リンクが正しくありません</h2><p>お手数ですがメール本文のリンクをそのまま開いてください。</p></body></html>', 400, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
	}
	$confirmed = ( $r->get_method() === 'POST' ) || ( $r->get_param( 'go' ) === '1' );
	if ( $confirmed ) {
		carmel_cb_opt_out( $email );
		$html = '<html><body style="font-family:sans-serif;padding:24px;max-width:640px;margin:auto"><h2>配信停止しました</h2><p><strong>' . esc_html( $email ) . '</strong> 宛への今後のご案内メールを停止しました。ご不便をおかけしました。</p><p>もう一度ご相談いただける際は、いつでもチャットからお声がけください。</p><p><a href="' . esc_url( home_url( '/' ) ) . '">カーメル トップページへ →</a></p></body></html>';
		return new WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
	}
	$post_url = esc_url( rest_url( 'carmel-cb/v1/unsubscribe' ) ) . '?e=' . rawurlencode( $email ) . '&t=' . urlencode( $token ) . '&go=1';
	$html = '<html><body style="font-family:sans-serif;padding:24px;max-width:640px;margin:auto"><h2>配信停止の確認</h2><p><strong>' . esc_html( $email ) . '</strong> 宛への案内メールの配信を停止しますか？</p><p><a href="' . $post_url . '" style="display:inline-block;padding:12px 20px;background:#0b5cab;color:#fff;text-decoration:none;border-radius:6px">配信を停止する</a></p><p><a href="' . esc_url( home_url( '/' ) ) . '">キャンセルしてサイトに戻る</a></p></body></html>';
	return new WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
}

/* ========================= 送信元メール ========================= */

/**
 * 後追いメール送信中だけ From を差し替える（ほかのwp_mailに影響させない）
 */
$GLOBALS['carmel_cb_apply_sending'] = false;

add_filter( 'wp_mail_from', function ( $from ) {
	if ( empty( $GLOBALS['carmel_cb_apply_sending'] ) ) { return $from; }
	$s = carmel_cb_get_settings();
	$addr = trim( (string) ( $s['apply_followup_from'] ?? '' ) );
	return $addr !== '' ? $addr : $from;
} );

add_filter( 'wp_mail_from_name', function ( $name ) {
	if ( empty( $GLOBALS['carmel_cb_apply_sending'] ) ) { return $name; }
	$s = carmel_cb_get_settings();
	$nm = trim( (string) ( $s['apply_followup_from_name'] ?? '' ) );
	return $nm !== '' ? $nm : ( 'カーメル' );
} );

/* ========================= Cron ========================= */

add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['carmel_cb_10min'] ) ) {
		$s['carmel_cb_10min'] = array( 'interval' => 600, 'display' => 'Every 10 minutes (Carmel CB)' );
	}
	return $s;
} );

function carmel_cb_apply_maybe_schedule() {
	if ( ! wp_next_scheduled( 'carmel_cb_apply_followup_tick' ) ) {
		wp_schedule_event( time() + 60, 'carmel_cb_10min', 'carmel_cb_apply_followup_tick' );
	}
}
add_action( 'init', 'carmel_cb_apply_maybe_schedule' );

add_action( 'carmel_cb_apply_followup_tick', 'carmel_cb_apply_run_followup' );

/**
 * Cron本体：未完了で「経過分>=しきい値」かつ「stage < 対象」の人にメール送信
 */
function carmel_cb_apply_run_followup() {
	$s = carmel_cb_get_settings();
	if ( empty( $s['apply_followup_on'] ) ) { return; }

	global $wpdb;
	$t = carmel_cb_apply_table();
	if ( ! carmel_cb_apply_table_exists() ) { return; }

	$stages = carmel_cb_apply_stage_defs( $s );
	foreach ( $stages as $stage_num => $def ) {
		if ( empty( $def['on'] ) ) { continue; }
		$delay_min = max( 1, (int) $def['delay_min'] );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, email, name, page FROM $t
			 WHERE completed=0 AND stage=%d AND clicked_at <= DATE_SUB(NOW(), INTERVAL %d MINUTE)
			 LIMIT 50",
			$stage_num - 1, $delay_min
		) );
		if ( ! $rows ) { continue; }

		foreach ( $rows as $row ) {
			$sent = carmel_cb_apply_send_stage_mail( $s, $row, $stage_num, $def );
			if ( $sent ) {
				$wpdb->update( $t, array( 'stage' => $stage_num, 'last_sent_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
			}
		}
	}
}

function carmel_cb_apply_table_exists() {
	global $wpdb;
	$t = carmel_cb_apply_table();
	return $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $t ) . "'" ) === $t;
}

function carmel_cb_apply_send_stage_mail( $s, $row, $stage_num, $def ) {
	// オプトアウト済みなら送らない
	if ( carmel_cb_is_opted_out( $row->email ) ) { return true; /* 送らず完了扱い */ }

	$name    = $row->name !== '' ? $row->name : 'お客様';
	$apply_url = ! empty( $s['apply_url'] ) ? $s['apply_url'] : home_url( '/shinsa-2/' );
	$line_url  = (string) ( $s['line_url'] ?? '' );
	$tel       = (string) ( $s['tel'] ?? '' );
	$stock_url = (string) ( $s['stock_page_url'] ?? '' );
	$site_url  = home_url( '/' );
	$signature = (string) ( $s['followup_signature'] ?? '' );
	$unsub_url = carmel_cb_unsub_url( $row->email );
	$unsub_note = (string) ( $s['followup_unsub_note'] ?? '' );

	$vars = array(
		'{name}'            => $name,
		'{apply_url}'       => $apply_url,
		'{line_url}'        => $line_url,
		'{tel}'             => $tel,
		'{stock_url}'       => $stock_url,
		'{site_url}'        => $site_url,
		'{signature}'       => $signature,
		'{unsubscribe_url}' => $unsub_url,
		'{unsub_note}'      => $unsub_note,
	);
	$subject = strtr( (string) ( $def['subject'] ?? '' ), $vars );
	$body    = strtr( (string) ( $def['body'] ?? '' ),    $vars );
	if ( $subject === '' || $body === '' ) { return false; }

	$GLOBALS['carmel_cb_apply_sending'] = true;
	$ok = wp_mail(
		$row->email,
		$subject,
		$body,
		array( 'Content-Type: text/plain; charset=UTF-8' )
	);
	$GLOBALS['carmel_cb_apply_sending'] = false;
	return (bool) $ok;
}

/**
 * 3段階のデフォルト定義。管理画面設定があれば上書き。
 */
function carmel_cb_apply_stage_defs( $s ) {
	$defaults = array(
		1 => array(
			'on'        => true,
			'delay_min' => 30,
			'subject'   => '【カーメル】審査フォームの入力途中ではありませんか？',
			'body'      => "{name} 様\n\nカーメルの みほ です😊\n先ほど審査フォームをお開きいただき、ありがとうございました。\n入力の途中で分からないところや、気になる点はございませんでしたか？\n\nもしよろしければ、続きから入力いただけます。\n▼ 続きから審査\n{apply_url}\n\nご相談だけでも大丈夫です。お気軽にどうぞ。\n・LINE：{line_url}\n・お電話：{tel}\n\n{signature}\n\n──────────────\n{unsub_note}\n▶ {unsubscribe_url}\n※ このメールは自動送信です。ご返信いただいても対応できない場合があります。\n",
		),
		2 => array(
			'on'        => true,
			'delay_min' => 60 * 24,
			'subject'   => '【カーメル】審査のご相談、いつでもお受けいたします',
			'body'      => "{name} 様\n\nお世話になっております、カーメル相談窓口の みほ です。\n昨日は当店の審査フォームにお越しいただき、ありがとうございました。\n\nお車のご購入は大きなご決断です。ご不安な点があれば、まずはお話だけでもお聞かせください。\n他社様で審査が通らなかった方でも、当店の低与信ローンでご案内できるケースが多くあります。\n\n▼ 審査の続きはこちら\n{apply_url}\n\n・LINEで気軽に相談：{line_url}\n・お電話：{tel}\n\n{signature}\n\n──────────────\n{unsub_note}\n▶ {unsubscribe_url}\n※ このメールは自動送信です。ご返信いただいても対応できない場合があります。\n",
		),
		3 => array(
			'on'        => true,
			'delay_min' => 60 * 24 * 3,
			'subject'   => '【カーメル】その後、お車のご検討はいかがでしょうか？',
			'body'      => "{name} 様\n\nカーメルの みほ です。\n先日は審査フォームにお越しいただきましたが、その後お車のご検討はいかがでしょうか？\n\nご希望の予算・車種などをお聞かせいただければ、当店で最適なプランをご提案いたします。\n無理な営業は一切いたしませんので、ご相談だけでもお気軽にどうぞ😊\n\n▼ 審査を続ける\n{apply_url}\n▼ 在庫を見る\n{stock_url}\n・LINE：{line_url}\n・お電話：{tel}\n\n{signature}\n\n──────────────\n{unsub_note}\n▶ {unsubscribe_url}\n※ このメールは自動送信です。ご返信いただいても対応できない場合があります。\n",
		),
	);

	// 保存済み設定で上書き（各stageの on/delay_min/subject/body）
	$user = is_array( $s['apply_followup_stages'] ?? null ) ? $s['apply_followup_stages'] : array();
	foreach ( $defaults as $i => $d ) {
		if ( isset( $user[ $i ] ) && is_array( $user[ $i ] ) ) {
			foreach ( array( 'on', 'delay_min', 'subject', 'body' ) as $k ) {
				if ( isset( $user[ $i ][ $k ] ) ) { $defaults[ $i ][ $k ] = $user[ $i ][ $k ]; }
			}
		}
	}
	return $defaults;
}
