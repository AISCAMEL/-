<?php
/**
 * AI記事リライト（既存記事の書き直し）
 * - 1本ずつ（投稿一覧の行アクション）／選択一括（一括操作）／全記事一括（キュー＋バックグラウンド）
 * - Claude APIで“サンプルっぽい薄い記事”を、具体的で読み応えのある本物の記事に書き直す
 * - WPは自動でリビジョンを保存するため、書き直し前の内容へ復元可能
 * - ai-editorial.php の関数（ais_hub_ai_opt / ais_hub_ai_call_claude 等）に依存
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** リライト用プロンプト */
function ais_hub_ai_build_rewrite_prompt( $title, $content ) {
	$plain = trim( wp_strip_all_tags( $content ) );
	if ( mb_strlen( $plain ) > 4000 ) { $plain = mb_substr( $plain, 0, 4000 ); }

	// 文字数・トーン・自社情報は生成設定を流用
	$len_map = array(
		'short'  => '本文は約700〜900字。見出しは2〜3個。',
		'medium' => '本文は約1,100〜1,400字。見出しは3〜4個。',
		'long'   => '本文は約1,700〜2,000字。見出しは4〜6個。',
	);
	$tone_map = array(
		'friendly'     => 'やさしく丁寧な「です・ます」。',
		'professional' => '専門的で信頼感のある「です・ます」。',
		'casual'       => '親しみやすく会話的な「です・ます」。',
	);
	$len     = ais_hub_ai_opt( 'length', 'medium' );
	$tone    = ais_hub_ai_opt( 'tone', 'friendly' );
	$context = trim( (string) ais_hub_ai_opt( 'company_context', '' ) );
	$cta_url = (string) ais_hub_ai_opt( 'cta_url', '/contact/' );
	$len_txt  = $len_map[ $len ] ?? $len_map['medium'];
	$tone_txt = $tone_map[ $tone ] ?? $tone_map['friendly'];

	$p  = "あなたは日本の自動車・モビリティ業界と中小企業のIT/AI活用に精通した、経験豊富な日本語のSEO編集者です。\n";
	$p .= "次の既存記事を、検索意図に応える“本物の解説記事”にリライト（全面的に書き直し）してください。\n\n";
	$p .= "■ 現在のタイトル\n{$title}\n\n";
	$p .= "■ 現在の本文（抜粋）\n{$plain}\n\n";
	$p .= "■ リライト方針\n";
	$p .= "- 記事の主題・テーマは維持する（別テーマにしない）。タイトルはより具体的で魅力的に改善してよい。\n";
	$p .= "- 『〜について解説します』だけで中身が薄い、サンプル/ひな形的な文章を一掃し、実際に役立つ内容へ作り込む。\n";
	$p .= "- 手順・判断基準・チェックリスト・具体例・比較・FAQ など、読者がすぐ行動できる実用情報を必ず入れる。\n";
	$p .= "- 結論→理由→具体策→注意点→よくある質問 の流れ。<h2>/<h3>・<ul>/<ol>・<p> で構造化し、スマホで読みやすく。\n";
	$p .= "- {$len_txt}\n- 文体：{$tone_txt}\n\n";
	$p .= "■ 信頼性・コンプライアンス\n";
	$p .= "- 一般的な知識・手順・考え方は具体的に言い切ってよい。\n";
	$p .= "- 検証できない固有情報（自社や特定業者の実績・順位・最新ニュース・個別の価格/金利/審査可否/納期）は断定しない（『目安』『条件により異なる』等）。\n";
	$p .= "- 保証表現（必ず/絶対/誰でも通る/100% 等）・誇大表現・他社誹謗は書かない。\n";
	if ( '' !== $context ) {
		$p .= "\n■ 自社情報（末尾の自然な導線に1リンクのみ使用可。本文の根拠には使わない）\n{$context}\n";
		$p .= "末尾に押し付けない相談導線を1つだけ入れてよい（<a href=\"{$cta_url}\">…</a> の形で1リンクまで）。\n";
	}
	$p .= "\n■ 出力形式（これ以外は一切出力しない。コードフェンス・前置き禁止）\n";
	$p .= 'JSONオブジェクトのみ: {"title":"改善後タイトル(32字前後)","meta_description":"110〜140字","body_html":"<h2>…</h2><p>…</p>…(h1は含めない)","tags":["関連タグ","3〜6個"]}';
	return $p;
}

/**
 * 1本リライト
 * @param int  $post_id
 * @param bool $to_draft 書き直し後に下書きへ退避するか
 * @return int|WP_Error 投稿ID or エラー
 */
function ais_hub_ai_rewrite( $post_id, $to_draft = false ) {
	if ( ais_hub_ai_opt( 'kill', 0 ) ) { return new WP_Error( 'killed', '緊急停止がONです' ); }
	$usage = ais_hub_ai_usage();
	$cap   = (int) ais_hub_ai_opt( 'monthly_cap', 20 );
	if ( $cap > 0 && (int) $usage['count'] >= $cap ) { return new WP_Error( 'cap', '月間生成上限に達しています（上限を上げるか翌月に再開）' ); }
	if ( ! ais_hub_ai_anthropic_key() ) { return new WP_Error( 'nokey', 'Claude APIキーが未設定です' ); }

	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type ) { return new WP_Error( 'notfound', '対象記事が見つかりません' ); }

	@set_time_limit( 60 );
	$prompt = ais_hub_ai_build_rewrite_prompt( $post->post_title, $post->post_content );
	$text   = ais_hub_ai_call_claude( $prompt );
	if ( is_wp_error( $text ) ) { ais_hub_ai_log( 'リライト失敗(API) #' . $post_id . ' / ' . $text->get_error_message() ); return $text; }

	$json = ais_hub_ai_extract_json( $text );
	if ( ! $json || empty( $json['body_html'] ) ) { ais_hub_ai_log( 'リライト失敗(解析) #' . $post_id ); return new WP_Error( 'parse', 'AI応答の解析に失敗しました' ); }

	$update = array( 'ID' => $post_id, 'post_content' => wp_kses_post( $json['body_html'] ) );
	if ( ! empty( $json['title'] ) ) { $update['post_title'] = sanitize_text_field( $json['title'] ); }
	if ( ! empty( $json['meta_description'] ) ) { $update['post_excerpt'] = sanitize_text_field( $json['meta_description'] ); }
	if ( $to_draft ) { $update['post_status'] = 'draft'; }

	$r = wp_update_post( $update, true );
	if ( is_wp_error( $r ) ) { ais_hub_ai_log( 'リライト失敗(保存) #' . $post_id ); return $r; }

	if ( ! empty( $json['meta_description'] ) ) { update_post_meta( $post_id, '_ais_meta_description', sanitize_text_field( $json['meta_description'] ) ); }
	update_post_meta( $post_id, '_ais_ai_rewritten', current_time( 'mysql' ) );
	if ( ! empty( $json['tags'] ) && is_array( $json['tags'] ) ) {
		wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', $json['tags'] ), true );
	}

	ais_hub_ai_usage_inc();
	ais_hub_ai_log( 'リライト成功 #' . $post_id . '（' . get_the_title( $post_id ) . '）' );
	do_action( 'ais_hub_ai_rewritten', $post_id );
	return $post_id;
}

/* =======================================================
 *  投稿一覧：行アクション「AIで書き直す」
 * ======================================================= */
add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( 'post' === $post->post_type && current_user_can( 'edit_post', $post->ID ) && ais_hub_ai_anthropic_key() ) {
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=ais_hub_ai_rewrite_one&post=' . $post->ID ),
			'ais_hub_ai_rewrite_' . $post->ID
		);
		$actions['ais_rewrite'] = '<a href="' . esc_url( $url ) . '">AIで書き直す</a>';
	}
	return $actions;
}, 10, 2 );

add_action( 'admin_post_ais_hub_ai_rewrite_one', function () {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! current_user_can( 'edit_post', $post_id ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ais_hub_ai_rewrite_' . $post_id ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$res = ais_hub_ai_rewrite( $post_id );
	$arg = is_wp_error( $res ) ? array( 'ais_rw_err' => rawurlencode( $res->get_error_message() ) ) : array( 'ais_rw_ok' => 1 );
	wp_safe_redirect( add_query_arg( $arg, admin_url( 'edit.php' ) ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( isset( $_GET['ais_rw_ok'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>AIで記事を書き直しました（元の内容はリビジョンから復元できます）。</p></div>'; }
	if ( isset( $_GET['ais_rw_err'] ) ) { echo '<div class="notice notice-error is-dismissible"><p>書き直しに失敗：' . esc_html( wp_unslash( $_GET['ais_rw_err'] ) ) . '</p></div>'; }
	if ( isset( $_GET['ais_rw_queued'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . (int) $_GET['ais_rw_queued'] . ' 件を書き直しキューに追加しました。バックグラウンドで順次処理されます（AI記事生成ページで進捗を確認できます）。</p></div>'; }
}, 10 );

/* =======================================================
 *  投稿一覧：一括操作「AIで書き直す（キュー投入）」
 * ======================================================= */
add_filter( 'bulk_actions-edit-post', function ( $actions ) {
	if ( ais_hub_ai_anthropic_key() ) { $actions['ais_rewrite'] = 'AIで書き直す（AIS）'; }
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-post', function ( $redirect, $action, $ids ) {
	if ( 'ais_rewrite' !== $action ) { return $redirect; }
	if ( ! current_user_can( 'edit_posts' ) ) { return $redirect; }
	$n = ais_hub_ai_rewrite_enqueue( array_map( 'intval', (array) $ids ) );
	return add_query_arg( 'ais_rw_queued', $n, $redirect );
}, 10, 3 );

/* =======================================================
 *  全記事一括リライト（キュー＋バックグラウンド処理）
 * ======================================================= */
/** キューにIDを追加し、ワーカーを起動 */
function ais_hub_ai_rewrite_enqueue( $ids ) {
	$queue = get_option( 'ais_hub_ai_rw_queue', array() );
	if ( ! is_array( $queue ) ) { $queue = array(); }
	$ids   = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	$queue = array_values( array_unique( array_merge( $queue, $ids ) ) );
	update_option( 'ais_hub_ai_rw_queue', $queue );
	if ( $queue && ! wp_next_scheduled( 'ais_hub_ai_rw_worker' ) ) {
		wp_schedule_single_event( time() + 5, 'ais_hub_ai_rw_worker' );
	}
	return count( $ids );
}

/** 公開＋下書きの全記事IDを取得（サンプル含む） */
function ais_hub_ai_all_post_ids() {
	return get_posts( array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );
}

/** ワーカー：1回につき数件処理し、残っていれば次回を予約 */
add_action( 'ais_hub_ai_rw_worker', function () {
	if ( ais_hub_ai_opt( 'kill', 0 ) ) { return; }
	$queue = get_option( 'ais_hub_ai_rw_queue', array() );
	if ( ! is_array( $queue ) || ! $queue ) { return; }

	$batch = 2; // 1回あたりの処理数（タイムアウト回避）
	$done  = 0;
	while ( $queue && $done < $batch ) {
		$id = (int) array_shift( $queue );
		update_option( 'ais_hub_ai_rw_queue', $queue ); // 先に保存（失敗時の無限ループ防止）
		$res = ais_hub_ai_rewrite( $id );
		if ( is_wp_error( $res ) && in_array( $res->get_error_code(), array( 'cap', 'killed', 'nokey' ), true ) ) {
			// 続行不能：キューは保持したまま停止（上限解除後に再開ボタンで続きから）
			ais_hub_ai_log( 'リライト一括停止：' . $res->get_error_message() . '（残り' . count( $queue ) . '件）' );
			return;
		}
		$done++;
	}
	if ( $queue ) {
		wp_schedule_single_event( time() + 60, 'ais_hub_ai_rw_worker' );
	} else {
		ais_hub_ai_log( 'リライト一括：キュー完了' );
	}
} );

/** 「すべて書き直す」実行 */
add_action( 'admin_post_ais_hub_ai_rewrite_all', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_ai_rw_all_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_ai_rw_all_nonce'] ) ), 'ais_hub_ai_rewrite_all' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$n = ais_hub_ai_rewrite_enqueue( ais_hub_ai_all_post_ids() );
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&rw_all=' . $n ) );
	exit;
} );

/** キュー再開（上限引き上げ後など） */
add_action( 'admin_post_ais_hub_ai_rewrite_resume', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_ai_rw_resume_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_ai_rw_resume_nonce'] ) ), 'ais_hub_ai_rewrite_resume' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	if ( ! wp_next_scheduled( 'ais_hub_ai_rw_worker' ) ) { wp_schedule_single_event( time() + 5, 'ais_hub_ai_rw_worker' ); }
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&rw_resumed=1' ) );
	exit;
} );

/** キュー破棄 */
add_action( 'admin_post_ais_hub_ai_rewrite_clear', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_ai_rw_clear_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_ai_rw_clear_nonce'] ) ), 'ais_hub_ai_rewrite_clear' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	delete_option( 'ais_hub_ai_rw_queue' );
	wp_clear_scheduled_hook( 'ais_hub_ai_rw_worker' );
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&rw_cleared=1' ) );
	exit;
} );

/* =======================================================
 *  AI記事生成ページ内に「一括リライト」セクションを追加
 * ======================================================= */
register_deactivation_hook( AIS_HUB_FILE, function () { wp_clear_scheduled_hook( 'ais_hub_ai_rw_worker' ); } );

add_action( 'ais_hub_ai_page_extra', function () {
	$queue = get_option( 'ais_hub_ai_rw_queue', array() );
	$left  = is_array( $queue ) ? count( $queue ) : 0;
	$total = count( ais_hub_ai_all_post_ids() );

	if ( isset( $_GET['rw_all'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . (int) $_GET['rw_all'] . ' 件を書き直しキューに追加しました。バックグラウンドで順次処理されます。</p></div>'; }
	if ( isset( $_GET['rw_resumed'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>キュー処理を再開しました。</p></div>'; }
	if ( isset( $_GET['rw_cleared'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>書き直しキューを破棄しました。</p></div>'; }

	echo '<hr><h2>既存記事をAIで書き直す（リアル感を出す）</h2>';
	echo '<p>公開・下書きの記事（現在 <strong>' . (int) $total . '</strong> 本）を、具体的で読み応えのある本物の記事に書き直します。元の内容は各記事の<strong>リビジョンから復元可能</strong>です。</p>';
	echo '<p class="description">1本ずつ書き直す場合は「投稿 → 記事一覧」で各記事の「AIで書き直す」リンク、または複数選択して一括操作「AIで書き直す（AIS）」が使えます。</p>';

	if ( $left > 0 ) {
		echo '<div class="notice notice-info inline"><p>📝 書き直しキュー：残り <strong>' . (int) $left . '</strong> 件（バックグラウンドで順次処理中）。';
		echo ' ※WP-Cron方式のため、サイトにアクセスが無いと進みにくいことがあります。</p></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
		echo '<input type="hidden" name="action" value="ais_hub_ai_rewrite_resume">';
		wp_nonce_field( 'ais_hub_ai_rewrite_resume', 'ais_hub_ai_rw_resume_nonce' );
		echo '<button class="button">今すぐ続きを処理</button></form>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block" onsubmit="return confirm(\'残りのキューを破棄しますか？\')">';
		echo '<input type="hidden" name="action" value="ais_hub_ai_rewrite_clear">';
		wp_nonce_field( 'ais_hub_ai_rewrite_clear', 'ais_hub_ai_rw_clear_nonce' );
		echo '<button class="button button-link-delete">キューを破棄</button></form>';
	} else {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'全' . (int) $total . '本の記事をAIで書き直します。各記事はリビジョンから復元可能です。実行しますか？\')">';
		echo '<input type="hidden" name="action" value="ais_hub_ai_rewrite_all">';
		wp_nonce_field( 'ais_hub_ai_rewrite_all', 'ais_hub_ai_rw_all_nonce' );
		echo '<button class="button button-primary">すべての記事を書き直す（' . (int) $total . '本）</button>';
		echo ' <span class="description">※ 月間生成上限（現在の設定値）を消費します。上限に達したら停止し、上限引き上げ後に続きから再開できます。</span>';
		echo '</form>';
	}
} );
