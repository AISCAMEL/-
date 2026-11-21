<?php
/**
 * AI記事運用エンジン（SEO記事の下書き生成）
 * - 記事本文：Claude API（Anthropic）
 * - 画像：OpenRouter（任意・ベストエフォート）
 * - 既定は「下書き・承認制」。公開は管理者が確認して行う。
 * - APIキーはサーバー側（オプション／wp-config定数）に保存。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** 設定の取得 */
function ais_hub_ai_opt( $key, $default = '' ) {
	$o = get_option( 'ais_hub_ai', array() );
	return ( is_array( $o ) && isset( $o[ $key ] ) ) ? $o[ $key ] : $default;
}

/** APIキー（wp-config 定数があれば優先） */
function ais_hub_ai_anthropic_key() {
	if ( defined( 'AIS_ANTHROPIC_KEY' ) && AIS_ANTHROPIC_KEY ) { return AIS_ANTHROPIC_KEY; }
	return (string) ais_hub_ai_opt( 'anthropic_key', '' );
}
function ais_hub_ai_openrouter_key() {
	if ( defined( 'AIS_OPENROUTER_KEY' ) && AIS_OPENROUTER_KEY ) { return AIS_OPENROUTER_KEY; }
	return (string) ais_hub_ai_opt( 'openrouter_key', '' );
}

/** 当月の生成数（上限管理） */
function ais_hub_ai_usage() {
	$u = get_option( 'ais_hub_ai_usage', array( 'month' => '', 'count' => 0 ) );
	$m = gmdate( 'Y-m' );
	if ( ! is_array( $u ) || empty( $u['month'] ) || $u['month'] !== $m ) {
		$u = array( 'month' => $m, 'count' => 0 );
		update_option( 'ais_hub_ai_usage', $u );
	}
	return $u;
}
function ais_hub_ai_usage_inc() {
	$u = ais_hub_ai_usage();
	$u['count'] = (int) $u['count'] + 1;
	update_option( 'ais_hub_ai_usage', $u );
}

/** ログ（直近30件） */
function ais_hub_ai_log( $msg ) {
	$log = get_option( 'ais_hub_ai_log', array() );
	if ( ! is_array( $log ) ) { $log = array(); }
	array_unshift( $log, '[' . current_time( 'Y-m-d H:i' ) . '] ' . $msg );
	$log = array_slice( $log, 0, 30 );
	update_option( 'ais_hub_ai_log', $log );
}

/* =======================================================
 *  管理メニュー・設定画面
 * ======================================================= */
add_action( 'admin_menu', function () {
	add_submenu_page( 'ais-hub', 'AI記事生成', 'AI記事生成', 'manage_options', 'ais-hub-ai', 'ais_hub_ai_page' );
}, 30 );

function ais_hub_ai_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$o     = wp_parse_args( get_option( 'ais_hub_ai', array() ), ais_hub_ai_defaults() );
	$usage = ais_hub_ai_usage();
	$log   = get_option( 'ais_hub_ai_log', array() );
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>'; }
	if ( isset( $_GET['generated'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . (int) $_GET['generated'] . ' 件の下書きを生成しました。下書きを確認して公開してください。</p></div>'; }
	if ( isset( $_GET['gen_error'] ) ) { echo '<div class="notice notice-error is-dismissible"><p>生成に失敗しました：' . esc_html( wp_unslash( $_GET['gen_error'] ) ) . '</p></div>'; }

	echo '<div class="wrap"><h1>AI記事生成（SEO）</h1>';
	echo '<p>キーワードから<strong>下書き</strong>を生成します（既定は承認制＝自動公開しません）。本文は Claude API、画像は OpenRouter を使用します。</p>';
	echo '<p>当月の生成数：<strong>' . (int) $usage['count'] . '</strong> / 上限 ' . (int) $o['monthly_cap'] . '　｜　緊急停止：' . ( ! empty( $o['kill'] ) ? '<span style="color:#b32d2e">ON（停止中）</span>' : 'OFF' ) . '</p>';
	echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_status=draft' ) ) . '">下書き（承認キュー）を見る</a></p>';

	// 設定フォーム
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ais_hub_ai_save">';
	wp_nonce_field( 'ais_hub_ai_save', 'ais_hub_ai_nonce' );
	echo '<h2>API設定</h2><table class="form-table">';
	$ak_const = defined( 'AIS_ANTHROPIC_KEY' );
	$ok_const = defined( 'AIS_OPENROUTER_KEY' );
	echo '<tr><th>Claude APIキー</th><td>' . ( $ak_const ? '<em>wp-config の定数 AIS_ANTHROPIC_KEY を使用中</em>' : '<input type="password" name="anthropic_key" value="' . esc_attr( $o['anthropic_key'] ) . '" style="width:420px" autocomplete="off"> <span class="description">sk-ant-...</span>' ) . '</td></tr>';
	echo '<tr><th>Claude モデルID</th><td><input type="text" name="anthropic_model" value="' . esc_attr( $o['anthropic_model'] ) . '" style="width:320px"> <span class="description">お使いのアカウントで利用可能なモデルIDに合わせてください</span></td></tr>';
	echo '<tr><th>OpenRouter APIキー</th><td>' . ( $ok_const ? '<em>wp-config の定数 AIS_OPENROUTER_KEY を使用中</em>' : '<input type="password" name="openrouter_key" value="' . esc_attr( $o['openrouter_key'] ) . '" style="width:420px" autocomplete="off"></td></tr>' );
	echo '<tr><th>画像モデル（OpenRouter）</th><td><input type="text" name="image_model" value="' . esc_attr( $o['image_model'] ) . '" style="width:320px"> <label style="margin-left:10px"><input type="checkbox" name="image_enable" value="1" ' . checked( ! empty( $o['image_enable'] ), true, false ) . '> 画像生成を有効化</label><p class="description">※OpenRouterで画像生成可能なモデルIDを指定。未対応時は画像なしで下書きを作成します。</p></td></tr>';
	echo '</table>';

	echo '<h2>生成設定</h2><table class="form-table">';
	echo '<tr><th>キーワード（1行1件）</th><td><textarea name="keywords" rows="6" style="width:480px" placeholder="中古車 ローン 通らない&#10;カーリース 法人 メリット&#10;GPS 車両管理 導入">' . esc_textarea( $o['keywords'] ) . '</textarea></td></tr>';
	echo '<tr><th>投稿カテゴリ</th><td><input type="text" name="category" value="' . esc_attr( $o['category'] ) . '"> <span class="description">例：コラム</span></td></tr>';
	echo '<tr><th>公開状態</th><td><select name="post_status"><option value="draft" ' . selected( $o['post_status'], 'draft', false ) . '>下書き（承認制・推奨）</option><option value="pending" ' . selected( $o['post_status'], 'pending', false ) . '>承認待ち</option><option value="publish" ' . selected( $o['post_status'], 'publish', false ) . '>即時公開（非推奨）</option></select></td></tr>';
	echo '<tr><th>月間生成上限</th><td><input type="number" name="monthly_cap" value="' . (int) $o['monthly_cap'] . '" min="0" style="width:100px"> 件/月</td></tr>';
	echo '<tr><th>自動スケジュール</th><td><label><input type="checkbox" name="cron_enable" value="1" ' . checked( ! empty( $o['cron_enable'] ), true, false ) . '> 毎日自動で生成する</label>　1回あたり <input type="number" name="per_run" value="' . (int) $o['per_run'] . '" min="1" max="5" style="width:60px"> 件（下書き）</td></tr>';
	echo '<tr><th>緊急停止</th><td><label style="color:#b32d2e"><input type="checkbox" name="kill" value="1" ' . checked( ! empty( $o['kill'] ), true, false ) . '> すべての生成を停止する</label></td></tr>';
	echo '</table>';
	submit_button( '設定を保存' );
	echo '</form>';

	// 手動生成
	echo '<hr><h2>今すぐ生成（下書き）</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ais_hub_ai_generate_now">';
	wp_nonce_field( 'ais_hub_ai_generate', 'ais_hub_ai_gen_nonce' );
	echo '<p>キーワード（任意・空ならリスト先頭から）：<input type="text" name="keyword" style="width:360px"></p>';
	submit_button( '1件生成する', 'primary', 'submit', false );
	echo ' <span class="description">※APIキーとモデル設定が必要です。生成には数十秒かかります。</span>';
	echo '</form>';

	// ログ
	echo '<hr><h2>実行ログ</h2><textarea rows="8" style="width:100%" readonly>' . esc_textarea( is_array( $log ) ? implode( "\n", $log ) : '' ) . '</textarea>';

	echo '<hr><h2>注意（公開前に必ず）</h2><ul style="list-style:disc;padding-left:22px">';
	echo '<li>AI生成記事は<strong>人が内容・出典を確認してから公開</strong>してください（既定は下書き）。</li>';
	echo '<li>断定的な実績・価格・審査可否は記載しない方針でプロンプトを構成していますが、最終確認は必須です。</li>';
	echo '<li>APIキーはこのサーバーのデータベースに保存されます。より安全にするには wp-config.php に AIS_ANTHROPIC_KEY / AIS_OPENROUTER_KEY を定義してください。</li>';
	echo '</ul></div>';
}

function ais_hub_ai_defaults() {
	return array(
		'anthropic_key'   => '',
		'anthropic_model' => 'claude-sonnet-5-5',
		'openrouter_key'  => '',
		'image_model'     => '',
		'image_enable'    => 0,
		'keywords'        => '',
		'category'        => 'コラム',
		'post_status'     => 'draft',
		'monthly_cap'     => 20,
		'cron_enable'     => 0,
		'per_run'         => 1,
		'kill'            => 0,
	);
}

/* 設定保存 */
add_action( 'admin_post_ais_hub_ai_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_ai_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_ai_nonce'] ) ), 'ais_hub_ai_save' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$d   = ais_hub_ai_defaults();
	$cur = wp_parse_args( get_option( 'ais_hub_ai', array() ), $d );
	$out = $cur;
	if ( ! defined( 'AIS_ANTHROPIC_KEY' ) && isset( $_POST['anthropic_key'] ) ) { $out['anthropic_key'] = trim( sanitize_text_field( wp_unslash( $_POST['anthropic_key'] ) ) ); }
	if ( ! defined( 'AIS_OPENROUTER_KEY' ) && isset( $_POST['openrouter_key'] ) ) { $out['openrouter_key'] = trim( sanitize_text_field( wp_unslash( $_POST['openrouter_key'] ) ) ); }
	$out['anthropic_model'] = sanitize_text_field( wp_unslash( $_POST['anthropic_model'] ?? $d['anthropic_model'] ) );
	$out['image_model']     = sanitize_text_field( wp_unslash( $_POST['image_model'] ?? '' ) );
	$out['image_enable']    = empty( $_POST['image_enable'] ) ? 0 : 1;
	$out['keywords']        = sanitize_textarea_field( wp_unslash( $_POST['keywords'] ?? '' ) );
	$out['category']        = sanitize_text_field( wp_unslash( $_POST['category'] ?? 'コラム' ) );
	$st                     = sanitize_text_field( wp_unslash( $_POST['post_status'] ?? 'draft' ) );
	$out['post_status']     = in_array( $st, array( 'draft', 'pending', 'publish' ), true ) ? $st : 'draft';
	$out['monthly_cap']     = max( 0, (int) ( $_POST['monthly_cap'] ?? 20 ) );
	$out['cron_enable']     = empty( $_POST['cron_enable'] ) ? 0 : 1;
	$out['per_run']         = min( 5, max( 1, (int) ( $_POST['per_run'] ?? 1 ) ) );
	$out['kill']            = empty( $_POST['kill'] ) ? 0 : 1;
	update_option( 'ais_hub_ai', $out );

	// Cron 再設定
	wp_clear_scheduled_hook( 'ais_hub_ai_cron' );
	if ( $out['cron_enable'] ) { wp_schedule_event( time() + 3600, 'daily', 'ais_hub_ai_cron' ); }

	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&saved=1' ) );
	exit;
} );

/* 手動生成 */
add_action( 'admin_post_ais_hub_ai_generate_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_ai_gen_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_ai_gen_nonce'] ) ), 'ais_hub_ai_generate' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$kw  = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
	if ( '' === $kw ) {
		$list = array_filter( array_map( 'trim', explode( "\n", (string) ais_hub_ai_opt( 'keywords', '' ) ) ) );
		$kw   = $list ? $list[0] : '';
	}
	if ( '' === $kw ) { wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&gen_error=' . rawurlencode( 'キーワードがありません' ) ) ); exit; }
	$res = ais_hub_ai_generate( $kw );
	if ( is_wp_error( $res ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&gen_error=' . rawurlencode( $res->get_error_message() ) ) );
	} else {
		wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&generated=1' ) );
	}
	exit;
} );

/* Cron ハンドラ */
add_action( 'ais_hub_ai_cron', function () {
	if ( ais_hub_ai_opt( 'kill', 0 ) ) { return; }
	$per  = (int) ais_hub_ai_opt( 'per_run', 1 );
	$list = array_values( array_filter( array_map( 'trim', explode( "\n", (string) ais_hub_ai_opt( 'keywords', '' ) ) ) ) );
	if ( ! $list ) { return; }
	// 回転オフセット
	$off  = (int) get_option( 'ais_hub_ai_offset', 0 );
	for ( $i = 0; $i < $per; $i++ ) {
		$kw = $list[ ( $off + $i ) % count( $list ) ];
		ais_hub_ai_generate( $kw );
	}
	update_option( 'ais_hub_ai_offset', ( $off + $per ) % count( $list ) );
} );

/* =======================================================
 *  生成本体
 * ======================================================= */
function ais_hub_ai_generate( $keyword ) {
	if ( ais_hub_ai_opt( 'kill', 0 ) ) { return new WP_Error( 'killed', '緊急停止がONです' ); }
	$usage = ais_hub_ai_usage();
	$cap   = (int) ais_hub_ai_opt( 'monthly_cap', 20 );
	if ( $cap > 0 && (int) $usage['count'] >= $cap ) { return new WP_Error( 'cap', '月間生成上限に達しています' ); }
	$key = ais_hub_ai_anthropic_key();
	if ( ! $key ) { return new WP_Error( 'nokey', 'Claude APIキーが未設定です' ); }

	$prompt = "あなたは自動車・モビリティ業界に詳しい日本語のSEOライターです。次のキーワードで、読者の検索意図に応える解説記事を作成してください。\n"
		. "キーワード: {$keyword}\n\n"
		. "制約:\n- 事実が不確実な固有の数値・実績・ニュース・審査可否・価格は断定しない（一般的な解説に留める）。\n"
		. "- 特定企業の宣伝は最小限（末尾に自然な相談導線を1つまで）。\n- 800〜1200字程度。<h2>/<h3> で構造化し、<p> と <ul> を用いる。\n\n"
		. "出力は次のJSONのみ（前後に説明やコードフェンスを付けない）:\n"
		. '{"title":"記事タイトル","meta_description":"120字程度の説明","body_html":"<h2>..</h2><p>..</p>","tags":["タグ1","タグ2"]}';

	$text = ais_hub_ai_call_claude( $prompt );
	if ( is_wp_error( $text ) ) { ais_hub_ai_log( '失敗(Claude): ' . $keyword . ' / ' . $text->get_error_message() ); return $text; }

	$json = ais_hub_ai_extract_json( $text );
	if ( ! $json || empty( $json['title'] ) || empty( $json['body_html'] ) ) {
		ais_hub_ai_log( '失敗(解析): ' . $keyword );
		return new WP_Error( 'parse', 'AI応答の解析に失敗しました' );
	}

	$status  = ais_hub_ai_opt( 'post_status', 'draft' );
	$post_id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_status'  => in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ? $status : 'draft',
		'post_title'   => sanitize_text_field( $json['title'] ),
		'post_content' => wp_kses_post( $json['body_html'] ),
		'post_excerpt' => isset( $json['meta_description'] ) ? sanitize_text_field( $json['meta_description'] ) : '',
	), true );
	if ( is_wp_error( $post_id ) ) { ais_hub_ai_log( '失敗(保存): ' . $keyword ); return $post_id; }

	update_post_meta( $post_id, '_ais_ai_generated', 1 );
	if ( ! empty( $json['meta_description'] ) ) { update_post_meta( $post_id, '_ais_meta_description', sanitize_text_field( $json['meta_description'] ) ); }
	// カテゴリ
	$cat = ais_hub_ai_opt( 'category', 'コラム' );
	if ( $cat ) {
		$term = get_term_by( 'name', $cat, 'category' );
		if ( ! $term ) { $t = wp_insert_term( $cat, 'category' ); if ( ! is_wp_error( $t ) ) { $term = get_term( $t['term_id'], 'category' ); } }
		if ( $term && ! is_wp_error( $term ) ) { wp_set_post_categories( $post_id, array( (int) $term->term_id ) ); }
	}
	// タグ
	if ( ! empty( $json['tags'] ) && is_array( $json['tags'] ) ) {
		wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $json['tags'] ) );
	}

	// 画像（任意・ベストエフォート）
	if ( ais_hub_ai_opt( 'image_enable', 0 ) && ais_hub_ai_openrouter_key() && ais_hub_ai_opt( 'image_model', '' ) ) {
		$img = ais_hub_ai_openrouter_image( $json['title'] . ' / ' . $keyword );
		if ( ! is_wp_error( $img ) && $img ) { ais_hub_ai_attach_image( $img, $post_id ); }
	}

	ais_hub_ai_usage_inc();
	ais_hub_ai_log( '生成: ' . $keyword . ' → 投稿#' . $post_id . '（' . $status . '）' );
	return $post_id;
}

/** Claude API 呼び出し */
function ais_hub_ai_call_claude( $prompt ) {
	$key   = ais_hub_ai_anthropic_key();
	$model = ais_hub_ai_opt( 'anthropic_model', 'claude-sonnet-5-5' );
	$resp  = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 90,
		'headers' => array(
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'max_tokens' => 2500,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) ),
	) );
	if ( is_wp_error( $resp ) ) { return $resp; }
	$code = wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( 200 !== (int) $code ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
		return new WP_Error( 'api', 'Claude API: ' . $msg );
	}
	if ( isset( $body['content'][0]['text'] ) ) { return (string) $body['content'][0]['text']; }
	return new WP_Error( 'empty', 'Claude API: 応答が空です' );
}

/** OpenRouter 画像生成（ベストエフォート） */
function ais_hub_ai_openrouter_image( $prompt ) {
	$key   = ais_hub_ai_openrouter_key();
	$model = ais_hub_ai_opt( 'image_model', '' );
	if ( ! $key || ! $model ) { return new WP_Error( 'nokey', 'OpenRouter未設定' ); }
	$resp = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
		'timeout' => 90,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'content-type'  => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'    => $model,
			'messages' => array( array( 'role' => 'user', 'content' => 'この記事のアイキャッチ画像を生成してください（文字なし・写実的・濃紺とティール基調）: ' . $prompt ) ),
		) ),
	) );
	if ( is_wp_error( $resp ) ) { return $resp; }
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	// OpenRouter の画像出力モデルは choices[].message.images[].image_url.url に返すことがある
	if ( isset( $body['choices'][0]['message']['images'][0]['image_url']['url'] ) ) {
		return (string) $body['choices'][0]['message']['images'][0]['image_url']['url'];
	}
	// content 内の data: URL も試す
	$content = $body['choices'][0]['message']['content'] ?? '';
	if ( is_string( $content ) && preg_match( '#(data:image/[a-z]+;base64,[A-Za-z0-9+/=]+)#', $content, $m ) ) {
		return $m[1];
	}
	return new WP_Error( 'noimg', 'OpenRouter: 画像を取得できませんでした（モデル要確認）' );
}

/** 画像をメディアに取り込みアイキャッチに設定（URL または data: URL） */
function ais_hub_ai_attach_image( $src, $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = '';
	if ( 0 === strpos( $src, 'data:image/' ) && false !== strpos( $src, 'base64,' ) ) {
		$parts = explode( 'base64,', $src, 2 );
		$bin   = base64_decode( $parts[1] );
		if ( ! $bin ) { return false; }
		$tmp = wp_tempnam( 'ais-ai.png' );
		file_put_contents( $tmp, $bin );
	} else {
		$tmp = download_url( esc_url_raw( $src ), 60 );
		if ( is_wp_error( $tmp ) ) { return false; }
	}
	$file  = array( 'name' => 'ais-ai-' . $post_id . '.png', 'tmp_name' => $tmp );
	$id    = media_handle_sideload( $file, $post_id );
	if ( is_wp_error( $id ) ) { @unlink( $tmp ); return false; }
	set_post_thumbnail( $post_id, $id );
	return $id;
}

/** AI応答からJSONを抽出 */
function ais_hub_ai_extract_json( $text ) {
	$text = trim( (string) $text );
	$text = preg_replace( '/^```(json)?|```$/m', '', $text );
	$s = strpos( $text, '{' );
	$e = strrpos( $text, '}' );
	if ( false === $s || false === $e || $e <= $s ) { return null; }
	$json = substr( $text, $s, $e - $s + 1 );
	$data = json_decode( $json, true );
	return is_array( $data ) ? $data : null;
}

/** SEO: メタディスクリプション出力（他SEOプラグインが無い場合のみ） */
add_action( 'wp_head', function () {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) { return; }
	if ( ! is_singular() ) { return; }
	$id   = get_queried_object_id();
	$desc = get_post_meta( $id, '_ais_meta_description', true );
	if ( ! $desc ) { $desc = get_the_excerpt( $id ); }
	$desc = trim( wp_strip_all_tags( (string) $desc ) );
	if ( $desc ) { echo '<meta name="description" content="' . esc_attr( mb_substr( $desc, 0, 140 ) ) . '">' . "\n"; }
}, 1 );

/* 無効化時に Cron を解除 */
register_deactivation_hook( AIS_HUB_FILE, function () { wp_clear_scheduled_hook( 'ais_hub_ai_cron' ); } );
