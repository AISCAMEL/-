<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 学習機能：実際の会話から“よくある質問と理想回答”をAIで抽出 → 管理者が承認 → 学習データ(FAQ)に蓄積。
 * - 承認待ちの候補は option 'carmel_cb_faq_suggestions' に保存（最新50件）。
 */

/** プロバイダに応じてAIを1回だけ呼ぶ（Claude優先→OpenRouterフォールバック）。 */
function carmel_cb_ai_once( $s, $system, $user ) {
	$messages = array(
		array( 'role' => 'system', 'content' => (string) $system ),
		array( 'role' => 'user',   'content' => (string) $user ),
	);
	if ( ( $s['ai_provider'] ?? 'openrouter' ) === 'claude' && ! empty( $s['claude_api_key'] ) && function_exists( 'carmel_cb_call_claude' ) ) {
		$r = carmel_cb_call_claude( $s, $messages, (string) ( $s['claude_model'] ?? 'claude-sonnet-5-5' ) );
		if ( ! empty( $r['ok'] ) ) { return (string) $r['reply']; }
	}
	if ( ! empty( $s['api_key'] ) && function_exists( 'carmel_cb_call_openrouter' ) && function_exists( 'carmel_cb_model_fallback_chain' ) ) {
		foreach ( carmel_cb_model_fallback_chain( $s['model'] ) as $model ) {
			$r = carmel_cb_call_openrouter( $s, $messages, $model );
			if ( ! empty( $r['ok'] ) ) { return (string) $r['reply']; }
		}
	}
	return '';
}

/* ---------- 承認待ち候補の保存 ---------- */
function carmel_cb_faq_suggestions_get() {
	$v = get_option( 'carmel_cb_faq_suggestions', array() );
	return is_array( $v ) ? array_values( $v ) : array();
}
function carmel_cb_faq_suggestions_save( $a ) {
	update_option( 'carmel_cb_faq_suggestions', array_values( (array) $a ), false );
}

/**
 * 直近の会話から学習候補（Q&A）を生成して承認待ちに追加。
 * 戻り値: array('ok'=>bool, 'added'=>int, 'reason'=>string)
 */
function carmel_cb_generate_faq_suggestions( $max_sessions = 15 ) {
	$s = carmel_cb_get_settings();
	if ( ! function_exists( 'carmel_cb_get_log_sessions' ) || ! function_exists( 'carmel_cb_get_session_messages' ) ) {
		return array( 'ok' => false, 'reason' => 'no_logs' );
	}

	$sessions = carmel_cb_get_log_sessions( 40 );
	$existing = array();
	foreach ( carmel_cb_get_faqs() as $f ) { $existing[] = $f->question; }

	$transcripts = array();
	$used = 0;
	foreach ( (array) $sessions as $sx ) {
		if ( $used >= $max_sessions ) { break; }
		$msgs   = carmel_cb_get_session_messages( $sx->session_id );
		$lines  = array();
		$uturns = 0;
		foreach ( (array) $msgs as $m ) {
			$c = (string) $m->content;
			if ( $c === '' || strpos( $c, '[IMG]' ) === 0 || strpos( $c, '[FILE]' ) === 0 ) { continue; }
			if ( $m->role === 'user' )           { $lines[] = 'お客様: ' . $c; $uturns++; }
			elseif ( $m->role === 'assistant' )  { $lines[] = 'みほ: ' . $c; }
			elseif ( $m->role === 'operator' )   { $lines[] = '担当者: ' . $c; }
		}
		if ( $uturns < 1 || count( $lines ) < 2 ) { continue; }
		$transcripts[] = implode( "\n", array_slice( $lines, 0, 40 ) );
		$used++;
	}
	if ( empty( $transcripts ) ) { return array( 'ok' => false, 'reason' => 'no_convo' ); }

	$sys = "あなたは中古車ローン（信用回復ローン）相談チャットの品質担当です。以下の実際の会話から、今後の回答に再利用できる『よくある質問と、その理想的な回答』のペアを抽出してください。\n"
		. "ルール：\n"
		. "- お客様が実際に困っていた点・繰り返し出そうな質問を優先する。\n"
		. "- 回答は簡潔・正確・前向き。断定しない（『必ず通る』等は禁止）。審査で決まる点はそう書く。\n"
		. "- 個人情報（氏名・メール・電話・具体的な車両価格の約束）は含めない。\n"
		. "- 既存FAQと重複する質問は出さない。\n"
		. "- 本当に良いものだけ、最大8件。無ければ空配列。\n"
		. "出力は次のJSONのみ（前後に文章・コードブロックを付けない）：{\"faqs\":[{\"q\":\"質問\",\"a\":\"回答\",\"k\":\"キーワード（カンマ区切り・任意）\"}]}";
	$user = "【既存FAQの質問（重複回避の参考）】\n" . ( $existing ? implode( "\n", array_slice( $existing, 0, 60 ) ) : '(なし)' )
		. "\n\n【実際の会話（複数）】\n----\n" . implode( "\n----\n", $transcripts );

	$out = carmel_cb_ai_once( $s, $sys, $user );
	if ( $out === '' ) { return array( 'ok' => false, 'reason' => 'ai_failed' ); }

	$json = null;
	if ( preg_match( '/\{.*\}/s', $out, $mm ) ) { $json = json_decode( $mm[0], true ); }
	if ( ! is_array( $json ) || empty( $json['faqs'] ) || ! is_array( $json['faqs'] ) ) {
		return array( 'ok' => true, 'added' => 0 );
	}

	$pending = carmel_cb_faq_suggestions_get();
	$seen = array();
	foreach ( $existing as $q ) { $seen[ carmel_cb_norm_q( $q ) ] = 1; }
	foreach ( $pending as $p )  { $seen[ carmel_cb_norm_q( $p['q'] ?? '' ) ] = 1; }

	$added = 0;
	foreach ( $json['faqs'] as $f ) {
		$q = trim( wp_strip_all_tags( (string) ( $f['q'] ?? '' ) ) );
		$a = trim( wp_strip_all_tags( (string) ( $f['a'] ?? '' ) ) );
		$k = trim( wp_strip_all_tags( (string) ( $f['k'] ?? '' ) ) );
		if ( $q === '' || $a === '' ) { continue; }
		$nq = carmel_cb_norm_q( $q );
		if ( $nq === '' || isset( $seen[ $nq ] ) ) { continue; }
		$seen[ $nq ] = 1;
		$pending[] = array(
			'q'  => mb_substr( $q, 0, 300 ),
			'a'  => mb_substr( $a, 0, 1000 ),
			'k'  => mb_substr( $k, 0, 200 ),
			'ts' => current_time( 'mysql' ),
		);
		$added++;
	}
	if ( count( $pending ) > 50 ) { $pending = array_slice( $pending, -50 ); }
	carmel_cb_faq_suggestions_save( $pending );
	return array( 'ok' => true, 'added' => $added );
}
