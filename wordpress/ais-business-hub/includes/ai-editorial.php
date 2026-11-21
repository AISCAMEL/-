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
	echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_status=draft' ) ) . '">下書き（承認キュー）を見る</a>';
	echo ' <a class="button" href="' . esc_url( admin_url( 'edit.php?post_status=all&post_type=post' ) ) . '">記事一覧</a></p>';
	if ( isset( $_GET['samples_cleared'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>セットアップのサンプル記事をゴミ箱へ移動しました。</p></div>'; }
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:6px 0 14px" onsubmit="return confirm(\'初期セットアップで作成したサンプル記事（ais-sample-*）をゴミ箱へ移動します。よろしいですか？\')">';
	echo '<input type="hidden" name="action" value="ais_hub_ai_clear_samples">';
	wp_nonce_field( 'ais_hub_ai_clear_samples', 'ais_hub_ai_samples_nonce' );
	echo '<button class="button button-secondary">サンプル記事を削除（“これはサンプル”の初期記事を消す）</button>';
	echo ' <span class="description">AI記事や本物の記事に置き換える前の、ダミー4本を一括でゴミ箱へ。</span>';
	echo '</form>';

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
	echo '<tr><th>画像モデル（OpenRouter）</th><td><input type="text" name="image_model" value="' . esc_attr( $o['image_model'] ) . '" style="width:320px"> <label style="margin-left:10px"><input type="checkbox" name="image_enable" value="1" ' . checked( ! empty( $o['image_enable'] ), true, false ) . '> 画像生成を有効化</label><p class="description">※OpenRouterの<strong>画像出力対応</strong>モデルIDを指定（既定：<code>google/gemini-2.5-flash-image-preview</code>）。1枚あたり約$0.03前後。未対応モデルや失敗時は画像なしで下書きを作成します。対応モデルは openrouter.ai/collections/image-models を参照。</p></td></tr>';
	echo '</table>';

	echo '<h2>生成設定</h2><table class="form-table">';
	echo '<tr><th>キーワード（1行1件）</th><td><textarea name="keywords" rows="6" style="width:480px" placeholder="中古車 ローン 通らない&#10;カーリース 法人 メリット&#10;GPS 車両管理 導入">' . esc_textarea( $o['keywords'] ) . '</textarea></td></tr>';
	echo '<tr><th>投稿カテゴリ</th><td><input type="text" name="category" value="' . esc_attr( $o['category'] ) . '"> <span class="description">例：コラム</span></td></tr>';
	echo '<tr><th>文字数の目安</th><td><select name="length"><option value="short" ' . selected( $o['length'], 'short', false ) . '>短め（約800字）</option><option value="medium" ' . selected( $o['length'], 'medium', false ) . '>標準（約1,200字）</option><option value="long" ' . selected( $o['length'], 'long', false ) . '>長め（約1,800字）</option></select></td></tr><tr><th>トーン</th><td><select name="tone"><option value="friendly" ' . selected( $o['tone'], 'friendly', false ) . '>やさしく丁寧（一般ユーザー向け）</option><option value="professional" ' . selected( $o['tone'], 'professional', false ) . '>専門的・信頼感（法人/BtoB向け）</option><option value="casual" ' . selected( $o['tone'], 'casual', false ) . '>カジュアル・親しみやすい</option></select></td></tr><tr><th>自社紹介文（任意）</th><td><textarea name="company_context" rows="3" style="width:480px">' . esc_textarea( $o['company_context'] ) . '</textarea><p class="description">記事末尾の自然な導線に使います。空欄可・事実のみ簡潔に。</p></td></tr><tr><th>相談導線リンク</th><td><input type="text" name="cta_url" value="' . esc_attr( $o['cta_url'] ) . '" style="width:320px"> <span class="description">例：/contact/</span></td></tr><tr><th>公開状態</th><td><select name="post_status"><option value="draft"' . selected( $o['post_status'], 'draft', false ) . '>下書き（承認制・推奨）</option><option value="pending" ' . selected( $o['post_status'], 'pending', false ) . '>承認待ち</option><option value="publish" ' . selected( $o['post_status'], 'publish', false ) . '>即時公開（非推奨）</option></select></td></tr>';
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

	/** 一括リライト等の追加セクション（ai-rewrite.php） */
	do_action( 'ais_hub_ai_page_extra' );

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
		'image_model'     => 'google/gemini-2.5-flash-image-preview',
		'image_enable'    => 0,
		'keywords'        => ais_hub_ai_default_keywords(),
		'category'        => 'コラム',
		'length'          => 'medium',
		'tone'            => 'friendly',
		'company_context' => '合同会社アイズ（AIS）は福島・会津を拠点に、中古車販売カーメル・買取BUYMO・カーリースCARSHICO（カシコ）・車両セキュリティ天護（GPS/レッカー）・輸出AIS JAPAN AUTO EXPORT、さらにAI/デジタル事業を展開しています。',
		'cta_url'         => '/contact/',
		'post_status'     => 'draft',
		'monthly_cap'     => 20,
		'cron_enable'     => 0,
		'per_run'         => 1,
		'kill'            => 0,
	);
}

/** AIS向け 集客キーワード初期セット（検索意図ベース） */
function ais_hub_ai_default_keywords() {
	$kw = array(
		// 販売カーメル
		'中古車 ローン 審査 不安 対処法',
		'中古車 選び方 初心者 チェックポイント',
		'車 購入 自社ローン とは 仕組み',
		// 買取BUYMO
		'車 高く売る コツ 買取 査定',
		'車 買取 相場 調べ方',
		'動かない車 廃車 買取 流れ',
		// リース カシコ
		'カーリース 個人 メリット デメリット',
		'法人 カーリース 経費 仕組み',
		'マイカーリース 購入 比較 違い',
		// 天護 セキュリティ/GPS/レッカー
		'車両 盗難 対策 方法',
		'社用車 GPS 管理 導入 メリット',
		'車 トラブル レッカー 呼び方 費用',
		// 輸出・オークション代行
		'車 オークション代行 とは 流れ',
		'中古車 輸出 ビジネス 仕組み',
		// IT・AI
		'中小企業 AI 活用 事例 始め方',
		'業務アプリ 開発 外注 費用 相場',
	);
	return implode( "\n", $kw );
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
	$ln                     = sanitize_text_field( wp_unslash( $_POST['length'] ?? 'medium' ) );
	$out['length']          = in_array( $ln, array( 'short', 'medium', 'long' ), true ) ? $ln : 'medium';
	$tn                     = sanitize_text_field( wp_unslash( $_POST['tone'] ?? 'friendly' ) );
	$out['tone']            = in_array( $tn, array( 'friendly', 'professional', 'casual' ), true ) ? $tn : 'friendly';
	$out['company_context'] = sanitize_textarea_field( wp_unslash( $_POST['company_context'] ?? '' ) );
	$out['cta_url']         = esc_url_raw( wp_unslash( $_POST['cta_url'] ?? '' ) );
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

/* サンプル記事の一括削除（ゴミ箱へ） */
add_action( 'admin_post_ais_hub_ai_clear_samples', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_ai_samples_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_ai_samples_nonce'] ) ), 'ais_hub_ai_clear_samples' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$slugs = array( 'ais-sample-carshico', 'ais-sample-export', 'ais-sample-ai', 'ais-sample-gps' );
	foreach ( $slugs as $slug ) {
		$posts = get_posts( array( 'name' => $slug, 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1 ) );
		if ( $posts ) { wp_trash_post( $posts[0]->ID ); }
	}
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-ai&samples_cleared=1' ) );
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

/** 業種特化・トーン/文字数対応のSEOプロンプトを組み立て */
function ais_hub_ai_build_prompt( $keyword ) {
	$len_map = array(
		'short'  => '本文は約700〜900字。見出しは2〜3個。',
		'medium' => '本文は約1,100〜1,400字。見出しは3〜4個。',
		'long'   => '本文は約1,700〜2,000字。見出しは4〜6個。',
	);
	$tone_map = array(
		'friendly'     => 'やさしく丁寧な「です・ます」。専門用語は噛み砕き、初めての読者にも分かるように。',
		'professional' => '専門的で信頼感のある「です・ます」。法人・BtoB担当者を想定し、根拠と判断材料を明確に。',
		'casual'       => '親しみやすく会話的な「です・ます」。堅すぎず、読みやすいテンポで。',
	);
	$len     = ais_hub_ai_opt( 'length', 'medium' );
	$tone    = ais_hub_ai_opt( 'tone', 'friendly' );
	$context = trim( (string) ais_hub_ai_opt( 'company_context', '' ) );
	$cta_url = (string) ais_hub_ai_opt( 'cta_url', '/contact/' );
	$len_txt  = $len_map[ $len ] ?? $len_map['medium'];
	$tone_txt = $tone_map[ $tone ] ?? $tone_map['friendly'];

	$p  = "あなたは日本の自動車・モビリティ業界（中古車販売/買取/カーリース/車両セキュリティ・GPS/レッカー/輸出/オークション代行）と、中小企業のIT・AI活用に精通した、経験豊富な日本語のSEO編集者です。\n";
	$p .= "次の検索キーワードで上位表示を狙う、読者の検索意図に正面から応える解説記事を1本作成してください。\n\n";
	$p .= "■ 検索キーワード: {$keyword}\n\n";
	$p .= "■ 書き方（具体的で“本物”の記事にする）\n";
	$p .= "- 検索意図を見極め、結論→理由→具体策→注意点→まとめ の流れで構成する。\n";
	$p .= "- 導入は読者の悩みに共感する2〜3文。各見出しは検索語や関連語を自然に含める。\n";
	$p .= "- 一般論で逃げない。手順・判断基準・チェックリスト・具体例・比較表など、読者がすぐ行動できる実用情報を必ず入れる。\n";
	$p .= "- サンプル/ひな形/プレースホルダのような中身の薄い文章は禁止。『〜について解説します』だけで終わらせず、必ず中身を書き切る。\n";
	$p .= "- {$len_txt}\n- 文体：{$tone_txt}\n";
	$p .= "- 箇条書き(<ul><li>)や手順(<ol>)を適切に使い、スマホで読みやすく。\n";
	$p .= "- 最後の見出しは「よくある質問」にし、Q&Aを2〜3組入れる。\n\n";
	$p .= "■ 信頼性・コンプライアンス（守りつつ、断定回避で曖昧にしすぎない）\n";
	$p .= "- 一般的な知識・手順・考え方は、自信を持って具体的に言い切ってよい。\n";
	$p .= "- ただし『自社や特定業者の実績・順位・最新ニュース・個別の価格/金利/審査可否/納期』など検証できない固有情報は断定しない（『目安』『条件により異なる』『詳細は要確認』と添える）。\n";
	$p .= "- 法令・税務・融資の保証表現（必ず/絶対/誰でも通る/100% 等）は使わない。誇大表現・他社誹謗も書かない。\n";
	if ( '' !== $context ) {
		$p .= "\n■ 自社情報（記事末尾の自然な1文の導線にのみ使用。本文の主張の根拠には使わない）\n{$context}\n";
		$p .= "末尾に、押し付けない相談導線を1つだけ入れてよい（リンク先: {$cta_url}）。<a href=\"{$cta_url}\">…</a> の形で1リンクまで。\n";
	}
	$p .= "\n■ 出力形式（これ以外は一切出力しない。コードフェンスや前置き禁止）\n";
	$p .= 'JSONオブジェクトのみ: {"title":"32文字前後の魅力的なタイトル","meta_description":"110〜140字の説明","body_html":"<h2>…</h2><p>…</p>…(本文HTML。titleのh1は含めない)","tags":["関連タグ","3〜6個"]}';
	return $p;
}

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

	$prompt = ais_hub_ai_build_prompt( $keyword );

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
	/**
	 * AI下書き生成の完了通知フック。
	 * @param int    $post_id 生成した投稿ID
	 * @param string $keyword キーワード
	 * @param string $status  公開状態
	 */
	do_action( 'ais_hub_ai_generated', $post_id, $keyword, $status );
	return $post_id;
}

/** Claude API 呼び出し */
function ais_hub_ai_call_claude( $prompt ) {
	$key   = ais_hub_ai_anthropic_key();
	$model = ais_hub_ai_opt( 'anthropic_model', 'claude-sonnet-5-5' );
	$len   = ais_hub_ai_opt( 'length', 'medium' );
	$max   = array( 'short' => 2200, 'medium' => 3200, 'long' => 4500 );
	$resp  = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
		'timeout' => 120,
		'headers' => array(
			'x-api-key'         => $key,
			'anthropic-version' => '2023-06-01',
			'content-type'      => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'max_tokens' => $max[ $len ] ?? 3200,
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

/**
 * OpenRouter 画像生成（ベストエフォート）
 * chat/completions + modalities:["image","text"] 方式。
 * 画像は choices[0].message.images[0].image_url.url に base64 data URL で返る。
 * 対応モデル例：google/gemini-2.5-flash-image-preview（Nano Banana）
 */
function ais_hub_ai_openrouter_image( $prompt ) {
	$key   = ais_hub_ai_openrouter_key();
	$model = ais_hub_ai_opt( 'image_model', '' );
	if ( ! $key || ! $model ) { return new WP_Error( 'nokey', 'OpenRouter未設定' ); }
	$referer = home_url( '/' );
	$resp    = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
		'timeout' => 120,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => $referer,
			'X-Title'       => 'AIS Business Hub',
		),
		'body'    => wp_json_encode( array(
			'model'      => $model,
			'modalities' => array( 'image', 'text' ),
			'messages'   => array( array(
				'role'    => 'user',
				'content' => 'ブログ記事のアイキャッチ画像を1枚生成してください。要件：文字・ロゴ・透かしを入れない／写実的で明るい／濃紺(#092448)とティール(#0E9BA9)を基調。テーマ：' . $prompt,
			) ),
		) ),
	) );
	if ( is_wp_error( $resp ) ) { return $resp; }
	$code = (int) wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( 200 !== $code ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
		return new WP_Error( 'api', 'OpenRouter: ' . $msg );
	}
	$msg = isset( $body['choices'][0]['message'] ) ? $body['choices'][0]['message'] : array();
	// 代表的な形：message.images[].image_url.url（data URL もしくは http URL）
	if ( ! empty( $msg['images'] ) && is_array( $msg['images'] ) ) {
		foreach ( $msg['images'] as $im ) {
			if ( isset( $im['image_url']['url'] ) ) { return (string) $im['image_url']['url']; }
			if ( is_string( $im ) && $im ) { return $im; }
		}
	}
	// 一部モデルは content を配列で返す（{type:image_url,image_url:{url}}）
	if ( isset( $msg['content'] ) && is_array( $msg['content'] ) ) {
		foreach ( $msg['content'] as $part ) {
			if ( isset( $part['image_url']['url'] ) ) { return (string) $part['image_url']['url']; }
		}
	}
	// content が文字列で data: URL を含む場合
	$content = isset( $msg['content'] ) && is_string( $msg['content'] ) ? $msg['content'] : '';
	if ( $content && preg_match( '#(data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+)#i', $content, $m ) ) {
		return $m[1];
	}
	return new WP_Error( 'noimg', 'OpenRouter: 画像を取得できませんでした（画像生成対応モデルか確認してください）' );
}

/** 画像をメディアに取り込みアイキャッチに設定（URL または data: URL） */
function ais_hub_ai_attach_image( $src, $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = '';
	$ext = 'png';
	if ( 0 === strpos( $src, 'data:image/' ) && false !== strpos( $src, 'base64,' ) ) {
		if ( preg_match( '#^data:image/([a-z0-9.+-]+);base64,#i', $src, $mm ) ) {
			$ext = strtolower( $mm[1] );
			if ( 'jpeg' === $ext ) { $ext = 'jpg'; }
			if ( ! in_array( $ext, array( 'png', 'jpg', 'webp', 'gif' ), true ) ) { $ext = 'png'; }
		}
		$parts = explode( 'base64,', $src, 2 );
		$bin   = base64_decode( $parts[1] );
		if ( ! $bin ) { return false; }
		$tmp = wp_tempnam( 'ais-ai.' . $ext );
		file_put_contents( $tmp, $bin );
	} else {
		$tmp = download_url( esc_url_raw( $src ), 60 );
		if ( is_wp_error( $tmp ) ) { return false; }
		$path_ext = strtolower( pathinfo( wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( in_array( $path_ext, array( 'png', 'jpg', 'jpeg', 'webp', 'gif' ), true ) ) { $ext = ( 'jpeg' === $path_ext ) ? 'jpg' : $path_ext; }
	}
	$file  = array( 'name' => 'ais-ai-' . $post_id . '.' . $ext, 'tmp_name' => $tmp );
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
