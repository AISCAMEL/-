<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 会話離脱者への後追いメール
 * - お名前・メール入力後にチャットが始まった時点で保留を作成
 * - 各AI返答で「最終発言時刻」を更新
 * - 未解決 && 最終発言から一定時間経過で段階的に自動メール送信
 * - 解決条件：
 *   ・審査ボタンを押した / 審査フォームを送信
 *   ・お問い合わせを送信
 *   ・担当者に相談を選んだ
 *   ・チャットに戻ってきて再度メッセージを送った（=セッション継続 → 送信対象から一時的に外す）
 */

/* ========================= テーブル ========================= */

function carmel_cb_convo_fu_table() {
	global $wpdb;
	return $wpdb->prefix . 'carmel_cb_convo_pending';
}

function carmel_cb_convo_fu_ensure_table() {
	global $wpdb;
	$t = carmel_cb_convo_fu_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $t (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id VARCHAR(64) NOT NULL,
		email VARCHAR(190) NOT NULL,
		name VARCHAR(190) NULL,
		page TEXT NULL,
		first_message_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		last_message_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		stage TINYINT(1) NOT NULL DEFAULT 0,
		resolved TINYINT(1) NOT NULL DEFAULT 0,
		last_sent_at TIMESTAMP NULL DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY session_id (session_id),
		KEY email (email),
		KEY status (resolved, stage)
	) $charset;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action( 'plugins_loaded', 'carmel_cb_convo_fu_ensure_table' );

function carmel_cb_convo_fu_table_exists() {
	global $wpdb;
	$t = carmel_cb_convo_fu_table();
	return $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $t ) . "'" ) === $t;
}

/* ========================= 記録 / 完了 ========================= */

/**
 * セッションの「最終発言時刻」を更新（無ければ作成）。
 * intake で name/email が保存済みならそこから拾って埋める。
 */
function carmel_cb_convo_fu_touch( $sid ) {
	global $wpdb;
	$sid = (string) $sid;
	if ( $sid === '' ) { return false; }
	$s = carmel_cb_get_settings();
	if ( empty( $s['convo_followup_on'] ) ) { return false; }

	// メール情報：intake から取得
	$email = ''; $name = '';
	if ( function_exists( 'carmel_cb_visitor_key' ) ) {
		$v = get_transient( carmel_cb_visitor_key( $sid ) );
		if ( is_array( $v ) ) {
			$email = sanitize_email( $v['email'] ?? '' );
			$name  = sanitize_text_field( $v['name'] ?? '' );
		}
	}
	if ( ! is_email( $email ) ) { return false; } // メール未取得なら送りようがない → スキップ

	carmel_cb_convo_fu_ensure_table();
	$t = carmel_cb_convo_fu_table();

	$now = current_time( 'mysql' );
	$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE session_id=%s", $sid ) );
	if ( $exists ) {
		// 既存 → last_message_at を進める、resolved は据え置き（会話が続いていれば送信対象から一時的に外れる）
		$wpdb->update( $t, array( 'last_message_at' => $now ), array( 'id' => $exists ) );
	} else {
		$wpdb->insert( $t, array(
			'session_id'       => $sid,
			'email'            => $email,
			'name'             => $name,
			'page'             => '',
			'first_message_at' => $now,
			'last_message_at'  => $now,
			'stage'            => 0,
			'resolved'         => 0,
		) );
	}
	return true;
}

/**
 * セッション or メールで完了にする（審査申込/問い合わせ送信/担当者接続時）。
 */
function carmel_cb_convo_fu_resolve( $sid = '', $email = '' ) {
	global $wpdb;
	if ( ! carmel_cb_convo_fu_table_exists() ) { return 0; }
	$t = carmel_cb_convo_fu_table();
	$n = 0;
	if ( $sid !== '' ) {
		$n += (int) $wpdb->update( $t, array( 'resolved' => 1 ), array( 'session_id' => $sid, 'resolved' => 0 ) );
	}
	if ( is_email( $email ) ) {
		$n += (int) $wpdb->update( $t, array( 'resolved' => 1 ), array( 'email' => strtolower( $email ), 'resolved' => 0 ) );
	}
	return $n;
}

/* ========================= 解決フック（既存機能から発火） ========================= */

// リード送信（かんたん審査/お問い合わせフォーム）
add_action( 'carmel_cb_lead_submitted', function ( $type, $data ) {
	if ( ! empty( $data['email'] ) ) { carmel_cb_convo_fu_resolve( '', $data['email'] ); }
}, 20, 2 );

/* ========================= Cron（既存の10分tickに相乗り） ========================= */

add_action( 'carmel_cb_apply_followup_tick', 'carmel_cb_convo_fu_run', 20 );

function carmel_cb_convo_fu_run() {
	$s = carmel_cb_get_settings();
	if ( empty( $s['convo_followup_on'] ) ) { return; }
	if ( ! carmel_cb_convo_fu_table_exists() ) { return; }

	global $wpdb;
	$t = carmel_cb_convo_fu_table();
	$stages = carmel_cb_convo_fu_stage_defs( $s );

	foreach ( $stages as $stage_num => $def ) {
		if ( empty( $def['on'] ) ) { continue; }
		$delay_min = max( 1, (int) $def['delay_min'] );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, email, name, page FROM $t
			 WHERE resolved=0 AND stage=%d AND last_message_at <= DATE_SUB(NOW(), INTERVAL %d MINUTE)
			 LIMIT 50",
			$stage_num - 1, $delay_min
		) );
		if ( ! $rows ) { continue; }
		foreach ( $rows as $row ) {
			$sent = carmel_cb_convo_fu_send_stage_mail( $s, $row, $stage_num, $def );
			if ( $sent ) {
				$wpdb->update( $t, array( 'stage' => $stage_num, 'last_sent_at' => current_time( 'mysql' ) ), array( 'id' => $row->id ) );
			}
		}
	}
}

function carmel_cb_convo_fu_send_stage_mail( $s, $row, $stage_num, $def ) {
	$name      = $row->name !== '' ? $row->name : 'お客様';
	$apply_url = ! empty( $s['apply_url'] ) ? $s['apply_url'] : home_url( '/shinsa-2/' );
	$line_url  = (string) ( $s['line_url'] ?? '' );
	$tel       = (string) ( $s['tel'] ?? '' );
	$stock_url = (string) ( $s['stock_page_url'] ?? '' );
	$site_url  = home_url( '/' );

	$vars = array(
		'{name}'      => $name,
		'{apply_url}' => $apply_url,
		'{line_url}'  => $line_url,
		'{tel}'       => $tel,
		'{stock_url}' => $stock_url,
		'{site_url}'  => $site_url,
	);
	$subject = strtr( (string) ( $def['subject'] ?? '' ), $vars );
	$body    = strtr( (string) ( $def['body'] ?? '' ),    $vars );
	if ( $subject === '' || $body === '' ) { return false; }

	$GLOBALS['carmel_cb_apply_sending'] = true; // 差出人フィルタを共通で使う（apply-followup.php 側で登録済み）
	$ok = wp_mail( $row->email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	$GLOBALS['carmel_cb_apply_sending'] = false;
	return (bool) $ok;
}

/**
 * 会話離脱後追いの3段階デフォルト。管理画面設定で上書き可。
 */
function carmel_cb_convo_fu_stage_defs( $s ) {
	$defaults = array(
		1 => array(
			'on'        => true,
			'delay_min' => 120, // 2時間
			'subject'   => '【カーメル】ご相談の続き、お答え足りていましたでしょうか？',
			'body'      => "{name} 様\n\n先ほどはカーメルにご相談いただき、ありがとうございました。\nみほ（カーメル相談窓口）です。\n\nご質問への回答は十分でしたか？\nもし途中で気になる点や、聞き足りなかったことがあれば、いつでも続きからご相談いただけます。\n\n▼ 続きから相談する\n{site_url}\n\nお車のご検討はもちろん、審査の不安や頭金のことなど、どんな小さなことでもお気軽にどうぞ。\n・LINEでも承ります：{line_url}\n・お電話：{tel}\n\n────────────\nカーメル\n{site_url}\n",
		),
		2 => array(
			'on'        => true,
			'delay_min' => 60 * 24, // 24時間
			'subject'   => '【カーメル】その後、お車のご検討はいかがでしょうか？',
			'body'      => "{name} 様\n\nお世話になっております、カーメルのみほです。\n昨日はご相談いただき、ありがとうございました。\n\nその後、お車のご検討は進んでいらっしゃいますか？\nご予算や車種のご希望、支払い方法など、\nお決まりの部分だけでも教えていただければ、\n最適なプランをご提案いたします。\n\n▼ 在庫を見てみる\n{stock_url}\n\n▼ 続きから相談する\n{site_url}\n\n・LINEでも気軽にどうぞ：{line_url}\n\n────────────\nカーメル\n",
		),
		3 => array(
			'on'        => true,
			'delay_min' => 60 * 24 * 3, // 3日
			'subject'   => '【カーメル】お手伝いできることがあれば、いつでもご相談ください',
			'body'      => "{name} 様\n\nカーメルのみほです。\n先日はご相談いただき、ありがとうございました。\n\nもしまだお車探しでお困りのことがあれば、いつでもお声がけください。\n他社様で審査が通らなかった方でも、当店の低与信ローンでご案内できるケースが多くあります。\n\n▼ 仮審査を申し込む\n{apply_url}\n\n▼ もう一度相談する\n{site_url}\n\n・LINE：{line_url}\n\n無理な営業は一切いたしません。ご相談だけでも大歓迎です😊\n\n────────────\nカーメル\n\n※ このメールは自動送信です。返信は不要です。\n　今後のご案内が不要な場合はお手数ですが LINE または上記フォームからご連絡ください。\n",
		),
	);
	$user = is_array( $s['convo_followup_stages'] ?? null ) ? $s['convo_followup_stages'] : array();
	foreach ( $defaults as $i => $d ) {
		if ( isset( $user[ $i ] ) && is_array( $user[ $i ] ) ) {
			foreach ( array( 'on', 'delay_min', 'subject', 'body' ) as $k ) {
				if ( isset( $user[ $i ][ $k ] ) ) { $defaults[ $i ][ $k ] = $user[ $i ][ $k ]; }
			}
		}
	}
	return $defaults;
}
