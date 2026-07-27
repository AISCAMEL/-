<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * テーブル作成（FAQ / 会話ログ）
 */
function carmel_cb_create_tables() {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$faq = $wpdb->prefix . CARMEL_CB_FAQ_TABLE;
	$log = $wpdb->prefix . CARMEL_CB_LOG_TABLE;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql_faq = "CREATE TABLE $faq (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		question TEXT NOT NULL,
		answer TEXT NOT NULL,
		keywords TEXT NULL,
		sort_order INT NOT NULL DEFAULT 0,
		enabled TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset;";

	$sql_log = "CREATE TABLE $log (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id VARCHAR(64) NOT NULL,
		role VARCHAR(16) NOT NULL,
		content TEXT NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY session_id (session_id)
	) $charset;";

	dbDelta( $sql_faq );
	dbDelta( $sql_log );

	carmel_cb_create_leads_table();
}

/**
 * 申込・お問い合わせ（リード）テーブル。
 * 古いMySQLで DATETIME DEFAULT CURRENT_TIMESTAMP が弾かれるため TIMESTAMP を使い、直接CREATEする。
 * プラグイン差し替え（再有効化なし）でも動くよう、必要時にオンデマンド作成もする。
 */
function carmel_cb_leads_table() {
	global $wpdb;
	return $wpdb->prefix . 'carmel_cb_leads';
}
function carmel_cb_create_leads_table() {
	global $wpdb;
	$t       = carmel_cb_leads_table();
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS $t (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		type VARCHAR(16) NOT NULL DEFAULT 'contact',
		name VARCHAR(120) NOT NULL DEFAULT '',
		tel VARCHAR(60) NOT NULL DEFAULT '',
		email VARCHAR(190) NOT NULL DEFAULT '',
		wish TEXT NULL,
		note TEXT NULL,
		page VARCHAR(255) NOT NULL DEFAULT '',
		transcript TEXT NULL,
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY type (type),
		KEY created_at (created_at)
	) $charset;";
	$wpdb->query( $sql );
}
/** テーブルが無ければ作る（オンデマンド）。 */
function carmel_cb_maybe_create_leads_table() {
	global $wpdb;
	$t = carmel_cb_leads_table();
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	if ( $exists !== $t ) { carmel_cb_create_leads_table(); }
}
/** リードを保存し、IDを返す。 */
function carmel_cb_insert_lead( $data ) {
	carmel_cb_maybe_create_leads_table();
	global $wpdb;
	$wpdb->insert( carmel_cb_leads_table(), array(
		'type'       => substr( (string) ( $data['type'] ?? 'contact' ), 0, 16 ),
		'name'       => substr( (string) ( $data['name'] ?? '' ), 0, 120 ),
		'tel'        => substr( (string) ( $data['tel'] ?? '' ), 0, 60 ),
		'email'      => substr( (string) ( $data['email'] ?? '' ), 0, 190 ),
		'wish'       => (string) ( $data['wish'] ?? '' ),
		'note'       => (string) ( $data['note'] ?? '' ),
		'page'       => substr( (string) ( $data['page'] ?? '' ), 0, 255 ),
		'transcript' => (string) ( $data['transcript'] ?? '' ),
	) );
	return (int) $wpdb->insert_id;
}
/** リード一覧を取得。 */
function carmel_cb_get_leads( $limit = 100 ) {
	carmel_cb_maybe_create_leads_table();
	global $wpdb;
	$t = carmel_cb_leads_table();
	$limit = (int) $limit;
	return $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT $limit" );
}
function carmel_cb_delete_lead( $id ) {
	global $wpdb;
	$wpdb->delete( carmel_cb_leads_table(), array( 'id' => (int) $id ) );
}

/**
 * 初期設定とサンプルFAQの投入（初回のみ）
 */
function carmel_cb_seed_defaults() {
	if ( get_option( 'carmel_cb_settings' ) === false ) {
		add_option( 'carmel_cb_settings', carmel_cb_default_settings() );
	}

	global $wpdb;
	$faq = $wpdb->prefix . CARMEL_CB_FAQ_TABLE;
	$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $faq" );
	if ( $count > 0 ) { return; }

	// carmelonline.jp の実際のFAQをシード
	$seed = array(
		array( '他社での審査結果に不安がある場合でも相談できますか？', 'はい、ご相談いただけます。過去の信用情報や他社での審査結果だけで判断せず、現在の収入状況や返済能力、ご希望の車両条件を確認したうえで購入方法をご案内します。審査結果や契約条件はお客様の状況により異なります。', '審査,他社,不安,信用情報' ),
		array( '頭金は必要ですか？', '頭金の有無は審査内容・車両条件・契約内容により異なります。月々のお支払い希望額やご予算を確認したうえで個別にご案内します。', '頭金,予算,支払い' ),
		array( '遠方からでも相談できますか？', 'はい、オンラインでご相談いただけます。全国納車に対応しており、納車対応エリア・陸送費用・必要書類・契約方法は地域や車両条件により異なるため事前に個別確認となります。', '遠方,全国,納車,オンライン' ),
		array( '申込みから納車までどのくらいかかりますか？', '審査結果、必要書類の提出状況、車両の在庫状況、整備や登録手続きにより異なります。目安はお申し込み後に個別にご案内します。', '納車,期間,日数,いつ' ),
		array( '必要な書類は何ですか？', '本人確認書類、収入確認書類、住所確認書類などが必要になる場合があります。必要書類は審査内容や契約条件により異なります。', '書類,必要,本人確認' ),
		array( 'どんな車を相談できますか？', '国産車を中心に、在庫車両や条件に合う車両をご提案します。ご案内できる車種・価格帯・支払条件は在庫状況や審査内容により異なります。現在の在庫は車両検索ページ(https://carmelonline.jp/search/)でご確認いただけます。', '車種,在庫,どんな車' ),
		array( '保証はありますか？', '対象車両には1年間の故障保証をご用意しています。保証内容・保証期間・対象部位・対応範囲は車両や契約内容により異なりますので、契約前に個別にご確認いただけます。', '保証,故障,1年' ),
	);

	$i = 0;
	foreach ( $seed as $row ) {
		$wpdb->insert( $faq, array(
			'question'   => $row[0],
			'answer'     => $row[1],
			'keywords'   => $row[2],
			'sort_order' => $i++,
			'enabled'    => 1,
		) );
	}
}

/**
 * デフォルト設定値
 */
function carmel_cb_default_settings() {
	return array(
		'enabled'        => 1,
		'api_key'        => '',
		'model'          => 'google/gemini-2.0-flash-001', // コスト最優先の既定
		'max_tokens'     => 500,
		'system_prompt'  => carmel_cb_default_prompt(),
		'welcome_msg'    => 'こんにちは！カーメルのAI相談窓口です。ローン審査・お支払い・車選びなど、お気軽にご質問ください。',
		'primary_color'  => '#0b5cab',
		'bot_name'       => 'カーメル相談AI',
		'auto_open_sec'  => 0, // 自動オープンは廃止（タップで開く仕様）。値は未使用。
		'line_url'       => 'https://lin.ee/y4QcSnq/',
		'stock_page_url' => 'https://carmelonline.jp/search/', // 在庫一覧ページ（在庫が無い時の案内に使用）
		'tel'            => '', // 電話番号（入力すると回答下に電話ボタンを表示）
		'apply_page_id'   => 7348, // 審査申込ページのID（CTA「審査を申し込む」）
		'contact_page_id' => 7361, // お問い合わせページのID（CTA「お問い合わせ」）
		'apply_url'       => '', // ↑IDが使えない場合のURL直指定（任意）
		'contact_url'     => '', // ↑IDが使えない場合のURL直指定（任意）
		// CTAの動き：'form'=チャット内フォームで受付（推奨） / 'link'=外部ページに飛ばす（従来）
		'cta_mode'          => 'form',
		'apply_form_on'     => 1, // 「かんたん審査」ボタンを出す
		'contact_form_on'   => 1, // 「お問い合わせ」ボタンを出す
		'consent_text'      => '入力内容をもとに担当者よりご連絡します。個人情報の取扱いに同意のうえ送信してください。',
		'consent_url'       => '', // プライバシーポリシー等のURL（任意）
		// 有人対応（D）
		'handoff_enabled' => 1,
		'biz_start'       => 10,        // 営業開始時(時)
		'biz_end'         => 19,        // 営業終了時(時)
		'biz_days'        => '0,1,2,3,4,5,6', // 営業曜日(日=0)
		'notify_email'    => '',        // 担当者通知先（空なら管理者メール）
		'operator_name'       => '担当者',  // チャットに表示する担当者名
		'operator_avatar_url' => '',        // 担当者アイコン画像URL（メディアのURLを貼る）
		'chat_start_notify'   => 1,         // チャット開始時にSlack等へ通知する
		// チャット開始時にお名前・メールを先に確認する（誰からの問い合わせか分かるように）
		'intake_on'           => 1,
		'intake_msg'          => 'ご相談ありがとうございます。まずお名前とメールアドレスをご入力ください。担当者からのご連絡やご案内に使わせていただきます😊',
		'intake_after_msg'    => 'ありがとうございます、{name}様。本日はどのようなご用件でしょうか？下のメッセージ入力にて、その場でお答えします😊',
		// 担当者につなぐ間の案内（ライブ待機）
		'handoff_wait_msg' => '担当者におつなぎしています。つながるまで少々お待ちください…',
		'handoff_busy_sec' => 15,       // この秒数つながらなければ「混雑」案内を出す
		'handoff_busy_msg' => '申し訳ございません、ただいま少し混み合っているようです🙏 もう少しだけお待ちください（お急ぎの場合はLINEやお電話もご利用いただけます）。',
		'handoff_wait_sec' => 45,       // 最終的にここでフォーム（連絡先）へ切り替え
		// 営業時間外に「担当者に相談」を押したときの案内（担当者は不在＝AIが自動対応）
		'handoff_offhours_msg' => 'ただいま営業時間外のため、担当者の対応ができません🙇 このままAIが自動で対応いたしますので、お気軽にご質問ください😊（ご希望の場合はご連絡先を残していただければ、翌営業日に担当者より折り返します）',
		'slack_webhook'   => '',        // 任意：Slack Incoming Webhook URL（一方向通知）
		// Slack 双方向（担当者がSlackで返信→チャット表示）
		'slack_bot_token' => '',        // xoxb-...（chat:write, channels:history）
		'slack_channel'   => '',        // 通知先チャンネルID（C0123...）
		// LINE WORKS Bot（API 2.0）
		'lw_client_id'       => '',
		'lw_client_secret'   => '',
		'lw_service_account' => '',
		'lw_private_key'     => '',     // PEM秘密鍵
		'lw_bot_id'          => '',
		'lw_channel_id'      => '',     // 送信先トークルームID
		'log_enabled'    => 1,
	);
}

/**
 * デフォルトのシステムプロンプト（人格・ルール）
 */
function carmel_cb_default_prompt() {
	return <<<PROMPT
あなたはカーメル(中古車購入・ローン相談サポート)の接客AIアシスタントです。

【役割】
お客様の質問にまず具体的に答え、会話を続けながら不安を解消する“相談相手”です。
中古車・自社ローン・買取・リースについて、分かりやすく丁寧に対応します。

【答え方（最重要）】
- まず質問に正面から答える。一般的な情報・手続きの流れ・考え方は、あなたの知識と参考情報(FAQ)を使ってきちんと説明する。
- すぐにLINEへ誘導しない。「お答えできません」「LINEでご相談ください」だけで終わらせない。
- 会話を重ねて状況を聞き取り、役に立つことを優先する。
- LINE・お電話・仮審査の案内は、お客様が「申込・査定をしたい」と示したとき、または個別条件の確定が必要なときだけ、自然に1回添える。毎回は案内しない。来店・来店予約は促さない。

【断定しないこと（ただし会話は止めない）】
- 審査の可否・金利・借入可能額・月々の確定額は断定しない。
  「一般的には〜」「目安として〜」と分かる範囲を説明したうえで、最終的な金額・可否は審査・面談によると伝える。
- 個人の信用情報や借金額を根掘り葉掘り聞かない。
- 在庫の具体的な車両は、参考情報や在庫データがあればそれを使って案内する。

【トーン】
不安に寄り添う／否定しない／専門用語を避ける／短く分かりやすく。
PROMPT;
}

/* ---------- FAQ CRUD ---------- */
function carmel_cb_get_faqs( $only_enabled = false ) {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_FAQ_TABLE;
	$where = $only_enabled ? 'WHERE enabled = 1' : '';
	return $wpdb->get_results( "SELECT * FROM $t $where ORDER BY sort_order ASC, id ASC" );
}
function carmel_cb_save_faq( $data ) {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_FAQ_TABLE;
	$fields = array(
		'question' => $data['question'],
		'answer'   => $data['answer'],
		'keywords' => isset($data['keywords']) ? $data['keywords'] : '',
		'enabled'  => isset($data['enabled']) ? 1 : 0,
	);
	if ( ! empty( $data['id'] ) ) {
		$wpdb->update( $t, $fields, array( 'id' => (int) $data['id'] ) );
	} else {
		$fields['sort_order'] = (int) $wpdb->get_var( "SELECT IFNULL(MAX(sort_order),0)+1 FROM $t" );
		$wpdb->insert( $t, $fields );
	}
}
function carmel_cb_delete_faq( $id ) {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_FAQ_TABLE;
	$wpdb->delete( $t, array( 'id' => (int) $id ) );
}
function carmel_cb_delete_all_faqs() {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_FAQ_TABLE;
	$wpdb->query( "DELETE FROM $t" );
}

/* ---------- 重複検出（似た質問が既に学習データにあるか） ---------- */
function carmel_cb_norm_q( $s ) {
	return preg_replace( '/[\s、。・,.!?！？「」『』（）()\-—~〜]/u', '', mb_strtolower( (string) $s ) );
}
function carmel_cb_bigrams_q( $s ) {
	$n = carmel_cb_norm_q( $s );
	$g = array();
	$len = mb_strlen( $n );
	if ( $len === 1 ) { $g[ $n ] = 1; }
	for ( $i = 0; $i < $len - 1; $i++ ) { $g[ mb_substr( $n, $i, 2 ) ] = 1; }
	return $g;
}
/** 2つの質問の類似度(0〜1)。バイグラムJaccard・包含率・部分一致(similar_text)の最大値。 */
function carmel_cb_q_similarity( $a, $b ) {
	$na = carmel_cb_norm_q( $a );
	$nb = carmel_cb_norm_q( $b );
	if ( $na === '' || $nb === '' ) { return 0.0; }

	$ga = carmel_cb_bigrams_q( $a );
	$gb = carmel_cb_bigrams_q( $b );
	$inter = count( array_intersect_key( $ga, $gb ) );
	$union = count( $ga + $gb );
	$jac   = $union ? $inter / $union : 0.0;                          // 全体の重なり
	$minc  = min( count( $ga ), count( $gb ) );
	$ov    = $minc ? ( $inter / $minc ) : 0.0;                        // 片方への包含率

	$pct = 0.0;
	similar_text( $na, $nb, $pct );                                   // 連続一致（言い換え検出に強い）

	return max( $jac, $ov * 0.9, $pct / 100 );
}
/**
 * 似た既存FAQを返す（無ければ null）。完全一致 or 類似度が閾値以上。
 */
function carmel_cb_find_similar_faq( $question, $exclude_id = 0, $threshold = 0.62 ) {
	$nq = carmel_cb_norm_q( $question );
	if ( $nq === '' ) { return null; }
	$best = null; $bestSim = 0;
	foreach ( carmel_cb_get_faqs( false ) as $f ) {
		if ( (int) $f->id === (int) $exclude_id ) { continue; }
		if ( carmel_cb_norm_q( $f->question ) === $nq ) { return $f; } // 完全一致
		$sim = carmel_cb_q_similarity( $question, $f->question );
		if ( $sim > $bestSim ) { $bestSim = $sim; $best = $f; }
	}
	return ( $bestSim >= $threshold ) ? $best : null;
}

/* ---------- ログ ---------- */
function carmel_cb_log( $session_id, $role, $content ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['log_enabled'] ) ) { return; }
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_LOG_TABLE;
	$wpdb->insert( $t, array(
		'session_id' => substr( $session_id, 0, 64 ),
		'role'       => $role,
		'content'    => $content,
	) );
}
function carmel_cb_get_log_sessions( $limit = 50 ) {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_LOG_TABLE;
	$limit = (int) $limit;
	return $wpdb->get_results(
		"SELECT session_id, MIN(created_at) AS started, COUNT(*) AS msgs
		 FROM $t GROUP BY session_id ORDER BY started DESC LIMIT $limit"
	);
}
function carmel_cb_get_session_messages( $session_id ) {
	global $wpdb;
	$t = $wpdb->prefix . CARMEL_CB_LOG_TABLE;
	return $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $t WHERE session_id = %s ORDER BY id ASC", $session_id )
	);
}
