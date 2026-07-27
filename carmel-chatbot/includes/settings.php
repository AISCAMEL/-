<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 設定取得（デフォルトとマージ）
 */
function carmel_cb_get_settings() {
	$saved = get_option( 'carmel_cb_settings', array() );
	if ( ! is_array( $saved ) ) { $saved = array(); }
	return array_merge( carmel_cb_default_settings(), $saved );
}

/**
 * 設定保存
 */
function carmel_cb_update_settings( $new ) {
	$current = carmel_cb_get_settings();
	$merged  = array_merge( $current, $new );
	update_option( 'carmel_cb_settings', $merged );
}

/**
 * コスト重視で選びやすいモデル候補
 */
function carmel_cb_model_choices() {
	return array(
		'deepseek/deepseek-chat-v3-0324:free' => 'DeepSeek V3（無料・高品質）',
		'google/gemini-2.0-flash-exp:free'    => 'Gemini 2.0 Flash（無料）',
		'google/gemini-2.0-flash-001' => 'Gemini 2.0 Flash（最安・高速・日本語良好）',
		'google/gemini-flash-1.5'     => 'Gemini 1.5 Flash（安価）',
		'anthropic/claude-3.5-haiku'  => 'Claude 3.5 Haiku（安価・自然な日本語）',
		'openai/gpt-4o-mini'          => 'GPT-4o mini（安価・バランス）',
		'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B（格安）',
	);
}
