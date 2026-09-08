<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 🎯 キャンペーン誘導
 * - 管理画面で有効期間・タイトル・本文・URLを設定
 * - 期間内なら AIプロンプトにキャンペーン情報を注入して会話中で自然に案内させる
 * - チャット冒頭にバナー表示するオプション
 */

/** キャンペーンが現在アクティブか（有効かつ期間内） */
function carmel_cb_campaign_is_active( $s = null ) {
	$s = $s ?: carmel_cb_get_settings();
	if ( empty( $s['campaign_on'] ) ) { return false; }
	if ( trim( (string) ( $s['campaign_title'] ?? '' ) ) === '' ) { return false; }

	$today = current_time( 'Y-m-d' );
	$start = trim( (string) ( $s['campaign_start'] ?? '' ) );
	$end   = trim( (string) ( $s['campaign_end'] ?? '' ) );
	if ( $start !== '' && $today < $start ) { return false; }
	if ( $end   !== '' && $today > $end   ) { return false; }
	return true;
}

/** キャンペーン情報を配列で返す。無ければ null。 */
function carmel_cb_campaign_data( $s = null ) {
	$s = $s ?: carmel_cb_get_settings();
	if ( ! carmel_cb_campaign_is_active( $s ) ) { return null; }
	return array(
		'title' => (string) ( $s['campaign_title'] ?? '' ),
		'body'  => (string) ( $s['campaign_body']  ?? '' ),
		'url'   => (string) ( $s['campaign_url']   ?? '' ),
		'start' => (string) ( $s['campaign_start'] ?? '' ),
		'end'   => (string) ( $s['campaign_end']   ?? '' ),
		'hint'  => (string) ( $s['campaign_ai_hint'] ?? '' ),
		'showBanner' => ! empty( $s['campaign_show_banner'] ),
	);
}

/**
 * 🤖 メモ/キーワードから、キャンペーンのタイトル・本文・AI案内方針を自動生成する。
 * 戻り値: ['ok'=>bool, 'title'=>..., 'body'=>..., 'hint'=>..., 'error'=>...]
 */
function carmel_cb_campaign_ai_draft( $seed ) {
	$seed = trim( (string) $seed );
	if ( $seed === '' ) { return array( 'ok' => false, 'error' => 'メモ・キーワードを入力してください。' ); }
	if ( ! function_exists( 'carmel_cb_call_openrouter' ) ) { return array( 'ok' => false, 'error' => 'AI呼び出し関数が見つかりません。' ); }

	$s = carmel_cb_get_settings();
	if ( empty( $s['api_key'] ) ) { return array( 'ok' => false, 'error' => 'OpenRouter APIキーが未設定です（応答・人格）。' ); }

	$system = <<<PROMPT
あなたはカーメル（中古車販売・低与信ローン）のマーケティング担当です。
「キャンペーンのメモ・キーワード」を受け取り、AIチャットボットで使うキャンペーン情報を作成します。

【必ず守るルール】
- カーメルのターゲットは「他社で審査が通らなかった方」「頭金がない方」「初めての車購入で不安な方」
- 押し売り厳禁。共感と安心感を最優先。
- タイトルは短く力強く（20文字以内）。ただし過剰な絵文字・記号は使わない。
- 本文は2〜3行、120文字以内。改行は\\nで表現。誰でも分かる平易な日本語。
- AI案内方針は、AIチャットボットが会話中でこのキャンペーンをどう扱うかの指示。
  「どんな話題の時に触れるか」「触れるトーン」「触れる頻度（多用禁止）」を1〜2文で。

【出力形式】必ずこのJSONだけを返す。前後に説明文を書かない。
{
  "title": "...",
  "body": "...",
  "hint": "..."
}
PROMPT;

	$messages = array(
		array( 'role' => 'system', 'content' => $system ),
		array( 'role' => 'user',   'content' => "メモ・キーワード：\n" . $seed ),
	);

	$model = ! empty( $s['model'] ) ? $s['model'] : 'google/gemini-2.0-flash-001';
	$res = carmel_cb_call_openrouter( $s, $messages, $model );
	if ( empty( $res['ok'] ) ) { return array( 'ok' => false, 'error' => 'AI呼び出しに失敗：' . ( $res['error'] ?? '' ) ); }

	$reply = (string) $res['reply'];
	// JSONブロックを抽出
	if ( preg_match( '/\{.*\}/su', $reply, $m ) ) { $reply = $m[0]; }
	$data = json_decode( $reply, true );
	if ( ! is_array( $data ) ) { return array( 'ok' => false, 'error' => 'AI応答をJSONとして解釈できませんでした。もう一度お試しください。' ); }

	return array(
		'ok'    => true,
		'title' => (string) ( $data['title'] ?? '' ),
		'body'  => (string) ( $data['body']  ?? '' ),
		'hint'  => (string) ( $data['hint']  ?? '' ),
	);
}

/** AIシステムプロンプトに追記する文字列（アクティブなら返す、無ければ空）。 */
function carmel_cb_campaign_prompt_block( $s = null ) {
	$c = carmel_cb_campaign_data( $s );
	if ( ! $c ) { return ''; }
	$period = '';
	if ( $c['start'] !== '' || $c['end'] !== '' ) {
		$period = "（期間: " . ( $c['start'] !== '' ? $c['start'] : '〜' ) . '〜' . ( $c['end'] !== '' ? $c['end'] : '無期限' ) . '）';
	}
	$url = $c['url'] !== '' ? "\n詳細URL（本文には貼らず、必要ならボタンで案内される）: {$c['url']}" : '';
	$hint = $c['hint'] !== '' ? "\n案内方針: {$c['hint']}" : '';
	return "\n\n【現在のキャンペーン】{$period}\nタイトル: {$c['title']}\n内容: {$c['body']}{$url}{$hint}\n※お客様の相談内容と関連しない場面では無理に案内しない。過度な連呼は禁止。";
}
