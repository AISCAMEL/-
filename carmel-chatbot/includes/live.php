<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * ライブ動向：いま来ているお客様・進行中の対応をリアルタイムで把握する。
 * - /live（管理者のみ）：直近◯分に動きのあったセッション一覧をJSONで返す
 * - 管理画面ホームの「🟢 いま来ている人」セクションが15秒ごとに取得して表示
 */

add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/live', array(
		'methods'             => 'GET',
		'callback'            => 'carmel_cb_live_feed',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
	) );
} );

/** 直近 $minutes 分に動きのあったセッションの要約を返す。 */
function carmel_cb_live_sessions( $minutes = 30, $limit = 25 ) {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_LOG_TABLE;
	if ( $wpdb->get_var( "SHOW TABLES LIKE '" . esc_sql( $t ) . "'" ) !== $t ) { return array(); }

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT session_id, MIN(created_at) AS started, MAX(created_at) AS last_at, COUNT(*) AS cnt
		 FROM $t
		 WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)
		 GROUP BY session_id
		 ORDER BY last_at DESC
		 LIMIT %d",
		(int) $minutes, (int) $limit
	) );

	$active_sids = array();
	if ( function_exists( 'carmel_cb_lw_active_get' ) ) {
		foreach ( carmel_cb_lw_active_get() as $code => $row ) {
			if ( ! empty( $row['sid'] ) ) { $active_sids[ $row['sid'] ] = $code; }
		}
	}

	$out = array();
	foreach ( (array) $rows as $r ) {
		$sid = $r->session_id;

		// 最新メッセージ（訪問者メタ行は除く）
		$last = $wpdb->get_row( $wpdb->prepare(
			"SELECT role, content FROM $t WHERE session_id=%s AND role<>'visitor' ORDER BY id DESC LIMIT 1", $sid
		) );
		// お名前・メール（訪問者メタ行）
		$name = ''; $email = '';
		$vis = $wpdb->get_var( $wpdb->prepare(
			"SELECT content FROM $t WHERE session_id=%s AND role='visitor' ORDER BY id DESC LIMIT 1", $sid
		) );
		if ( $vis ) {
			if ( preg_match( '/お名前:\s*(.+?)\s*(?:\/|$)/u', $vis, $m ) ) { $name = trim( $m[1] ); }
			if ( preg_match( '/メール:\s*([^\s\/]+)/u', $vis, $m ) )        { $email = trim( $m[1] ); }
		}

		$lastmsg = (string) ( $last->content ?? '' );
		$lastmsg = preg_replace( '/\s+/u', ' ', $lastmsg );
		if ( strpos( $lastmsg, '[IMG]' ) === 0 ) { $lastmsg = '📷 画像'; }
		elseif ( strpos( $lastmsg, '[FILE]' ) === 0 ) { $lastmsg = '📎 ファイル'; }

		$out[] = array(
			'sid'      => $sid,
			'name'     => $name,
			'email'    => $email,
			'started'  => mysql2date( 'H:i', $r->started ),
			'last'     => mysql2date( 'H:i', $r->last_at ),
			'ago'      => carmel_cb_live_ago( $r->last_at ),
			'cnt'      => (int) $r->cnt,
			'lastRole' => $last->role ?? '',
			'lastMsg'  => mb_substr( $lastmsg, 0, 70 ),
			'handoff'  => isset( $active_sids[ $sid ] ) ? $active_sids[ $sid ] : '',
		);
	}
	return $out;
}

/** 「◯分前」表記。 */
function carmel_cb_live_ago( $mysql_time ) {
	$ts = strtotime( get_gmt_from_date( $mysql_time ) );
	$diff = max( 0, time() - $ts );
	if ( $diff < 60 ) { return 'たった今'; }
	if ( $diff < 3600 ) { return (int) ( $diff / 60 ) . '分前'; }
	return (int) ( $diff / 3600 ) . '時間前';
}

function carmel_cb_live_feed( WP_REST_Request $request ) {
	return new WP_REST_Response( array(
		'sessions' => carmel_cb_live_sessions( 30, 25 ),
		'now'      => current_time( 'H:i:s' ),
	), 200 );
}
