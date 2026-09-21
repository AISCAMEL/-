<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🔎 審査見込み 自己診断
 * - スコアリング自体はフロント(chat.js)で即時計算・表示する（軽快・オフライン風）
 * - このエンドポイントは「診断結果＋回答をリードとして保存＋管理者通知」する役割
 * - お名前・メールは intake(visitor) から取得
 */

add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/shinsa-check', array(
		'methods'             => 'POST',
		'callback'            => 'carmel_cb_handle_shinsa_check',
		'permission_callback' => '__return_true',
	) );
} );

function carmel_cb_handle_shinsa_check( WP_REST_Request $r ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['shinsa_on'] ) ) { return new WP_REST_Response( array( 'ok' => false, 'skipped' => 'off' ), 200 ); }

	$b     = $r->get_json_params();
	$sid   = sanitize_text_field( $b['session_id'] ?? '' );
	$score = (int) ( $b['score'] ?? 0 );
	$rank  = sanitize_text_field( $b['rank'] ?? '' );      // 'high' | 'mid' | 'low'
	$rate  = sanitize_text_field( $b['rate'] ?? '' );      // 表示した金利目安
	$ans   = is_array( $b['answers'] ?? null ) ? $b['answers'] : array(); // ラベルの連想配列

	// intake からお名前・メールを取得
	$name = ''; $email = ''; $tel = '';
	if ( function_exists( 'carmel_cb_visitor_key' ) && $sid !== '' ) {
		$v = get_transient( carmel_cb_visitor_key( $sid ) );
		if ( is_array( $v ) ) {
			$name  = sanitize_text_field( $v['name'] ?? '' );
			$email = sanitize_email( $v['email'] ?? '' );
		}
	}

	// 回答を読みやすいテキストに整形
	$rank_ja = $rank === 'high' ? '高い' : ( $rank === 'mid' ? '中' : '要相談' );
	$lines = array();
	$lines[] = '【審査見込み 自己診断】';
	$lines[] = '判定: ' . $rank_ja . '（スコア ' . $score . '/100・金利目安 ' . $rate . '）';
	if ( $ans ) {
		$lines[] = '── 回答 ──';
		foreach ( $ans as $k => $v ) {
			$lines[] = sanitize_text_field( (string) $k ) . '：' . sanitize_text_field( (string) $v );
		}
	}
	$note = implode( "\n", $lines );

	// リードとして保存（種別 shinsa）
	if ( function_exists( 'carmel_cb_insert_lead' ) ) {
		carmel_cb_insert_lead( array(
			'type'  => 'shinsa',
			'name'  => $name,
			'tel'   => $tel,
			'email' => $email,
			'wish'  => '審査見込み診断',
			'note'  => $note,
			'page'  => esc_url_raw( $b['page'] ?? '' ),
		) );
	}

	// 管理者通知（notify.php があれば）
	if ( function_exists( 'carmel_cb_notify_event' ) ) {
		carmel_cb_notify_event( 'shinsa_check', '審査見込み診断が行われました', array(
			'お名前: ' . ( $name !== '' ? $name : '(未取得)' ),
			'メール: ' . ( $email !== '' ? $email : '(未取得)' ),
			'判定  : ' . $rank_ja . '（' . $score . '/100）',
			'金利  : ' . $rate,
		), array( 'sid' => 'shinsa-' . $sid ) );
	}

	// 会話離脱後追い・審査後追いの観点では「行動を起こした」＝解決寄り。ここでは中立（保存のみ）。
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
