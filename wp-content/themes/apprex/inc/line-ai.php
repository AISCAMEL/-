<?php
/**
 * LINE AI 自動応答。
 *
 * LINE公式アカウントに届いたテキストメッセージへ、サイトのAIチャットと同じ頭脳
 * （OpenRouter＋APPREXシステムプロンプト）で自動返信する。
 *
 * 連携：
 *  - 受信：inc/line-steps.php の Webhook が発火する 'apprex_line_event' フック。
 *  - 返信：LINE reply API（replyToken）。
 *  - 頭脳：inc/openrouter-chat.php の apprex_openrouter_complete() / apprex_chat_system_prompt()。
 *  - 送信認証：inc/line-direct.php の apprex_line_channel_token()。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_line_ai_enabled() {
	return (bool) get_option( 'apprex_line_ai_enabled', 0 );
}

/** LINE reply API（replyTokenへ返信）。 */
function apprex_line_reply( $reply_token, $messages ) {
	$token = function_exists( 'apprex_line_channel_token' ) ? apprex_line_channel_token() : '';
	if ( '' === $token || '' === $reply_token ) {
		return new WP_Error( 'line', 'トークン/replyTokenがありません。' );
	}
	$res = wp_remote_post(
		'https://api.line.me/v2/bot/message/reply',
		array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'replyToken' => $reply_token,
					'messages'   => array_values( $messages ),
				)
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		$b = json_decode( wp_remote_retrieve_body( $res ), true );
		return new WP_Error( 'line', isset( $b['message'] ) ? $b['message'] : ( 'HTTP ' . $code ) );
	}
	return true;
}

/** メッセージイベントを受けてAIで返信。 */
add_action( 'apprex_line_event', function ( $ev, $uid, $type ) {
	if ( 'message' !== $type ) {
		return;
	}
	if ( ! apprex_line_ai_enabled() ) {
		return;
	}
	if ( empty( $ev['message']['type'] ) || 'text' !== $ev['message']['type'] ) {
		return; // テキスト以外（画像・スタンプ等）は対象外。
	}
	$reply_token = isset( $ev['replyToken'] ) ? sanitize_text_field( $ev['replyToken'] ) : '';
	$user_text   = isset( $ev['message']['text'] ) ? trim( (string) $ev['message']['text'] ) : '';
	if ( '' === $reply_token || '' === $user_text ) {
		return;
	}
	if ( ! function_exists( 'apprex_openrouter_complete' ) || '' === apprex_openrouter_key() ) {
		return; // AIキー未設定なら何もしない（公式アカウント側の自動応答に委ねる）。
	}

	// サイトのAIと同じ頭脳＋LINE向けの簡潔指示。
	$system = function_exists( 'apprex_chat_system_prompt' ) ? apprex_chat_system_prompt() : 'あなたはAPPREXのアシスタントです。';
	$system .= "\n\n# LINE応答ルール\n- LINEでの会話です。1〜3文で簡潔に、絵文字は控えめに。\n- 詳しい相談・見積もりは無料相談やフォームへ自然に誘導してよい。\n- 分からないことは無理に答えず、担当者へつなぐ旨を伝える。";

	$messages = array(
		array( 'role' => 'system', 'content' => $system ),
		array( 'role' => 'user', 'content' => mb_substr( $user_text, 0, 2000 ) ),
	);
	$reply = apprex_openrouter_complete( $messages, array( 'temperature' => 0.4, 'max_tokens' => 500, 'timeout' => 25 ) );
	if ( is_wp_error( $reply ) || '' === trim( (string) $reply ) ) {
		error_log( '[APPREX LINE AI] ' . ( is_wp_error( $reply ) ? $reply->get_error_message() : 'empty reply' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return;
	}
	apprex_line_reply( $reply_token, array( array( 'type' => 'text', 'text' => mb_substr( (string) $reply, 0, 4900 ) ) ) );
}, 10, 3 );

/** 設定（APPREX 配信(LINE) ページのグループに相乗り）。 */
add_action( 'admin_init', function () {
	register_setting( 'apprex_line_dist', 'apprex_line_ai_enabled', array( 'sanitize_callback' => 'absint' ) );
} );
