<?php
/**
 * オリジナルアプリをAIで作る（イメージ＝触れる試作＋仕様定義書）。
 *
 * - 会員が希望を書くと、AIがアプリの企画（画面・機能・データ・権限・管理画面）をJSONで作る
 * - その画面定義から、デモアプリ（/app-demo/{トークン}/?v=orig）で触れる試作を表示
 * - AIスタジオの「仕様定義書」で、開発にそのまま渡せる書類にする
 * - APIキーが無い場合は、業種別のひな形（マッチング・業務・学習・予約/イベント）から作る
 *
 * あくまで「イメージ」を早く共有して、ミーティングと成約までの時間を短くするための機能です。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 画面の部品として使える種類（app-demo.js の試作表示と同じ）。 */
function apprex_orig_part_types() {
	return array( 'hero', 'search', 'list', 'cards', 'form', 'button', 'tabs', 'chat', 'map', 'calendar', 'text', 'profile', 'stats', 'image', 'swipe' );
}

/**
 * 概算の考え方（人日と単価）。フィルター apprex_orig_estimate_rules で変更できる。
 *
 * @return array
 */
function apprex_orig_estimate_rules() {
	return apply_filters(
		'apprex_orig_estimate_rules',
		array(
			'day_rate'  => (int) get_option( 'apprex_orig_day_rate', 60000 ), // 1人日あたり（税抜）。
			'size_days' => array( 'S' => 3, 'M' => 8, 'L' => 18 ),          // 機能の大きさごとの人日。
			'base_days' => 1,                                              // APPREXの基盤で対応できる機能。
			'fixed'     => array(
				array( '要件定義・画面設計・デザイン', 12 ),
				array( '管理画面（基本）', 6 ),
				array( 'テスト・ストア申請', 8 ),
			),
			'range'     => array( 0.85, 1.3 ),
			'floor'     => array( 'マッチング' => 3000000 ), // カテゴリー別の最低金額。
			'team'      => 2,                                // 体制（人）→ 期間の目安に使う。
		)
	);
}

/* -------------------------------------------------------------------------
 * ひな形（APIキーが無いとき・AIが失敗したとき）
 * ---------------------------------------------------------------------- */

/** 画面定義の短縮ヘルパー。 */
function apprex_orig_screen( $id, $name, $role, $purpose, $parts, $to = array() ) {
	return array( 'id' => $id, 'name' => $name, 'role' => $role, 'purpose' => $purpose, 'parts' => $parts, 'to' => $to );
}
/** 部品の短縮ヘルパー。 */
function apprex_orig_part( $t, $label = '', $items = array() ) {
	return array( 't' => $t, 'label' => $label, 'items' => $items );
}

/**
 * 希望文から近いひな形を選んで、アプリの企画を作る。
 *
 * @param string $idea  希望。
 * @param string $name  アプリ名（任意）。
 * @param string $genre マッチングのジャンル（任意。inc/orig-genre.php）。
 * @return array
 */
function apprex_orig_template( $idea, $name = '', $genre = '' ) {
	$p = 'apprex_orig_part';
	$s = 'apprex_orig_screen';
	$kind = 'event';
	if ( ( $genre && 'other' !== $genre ) || preg_match( '/マッチング|スキル|仲介|フリマ|売り買い|求人|人材|出品|依頼|恋活|婚活|出会い/u', $idea ) ) {
		$kind = 'matching';
	} elseif ( preg_match( '/社内|業務|日報|在庫|点検|勤怠|現場|報告|管理アプリ/u', $idea ) ) {
		$kind = 'business';
	} elseif ( preg_match( '/動画|講座|レッスン|学習|サブスク|オンラインサロン|教材/u', $idea ) ) {
		$kind = 'learning';
	}
	$T = array(
		'matching' => array(
			'name'     => 'SkillLink',
			'summary'  => 'スキルを持つ人と、頼みたい人をつなぐマッチングアプリ',
			'category' => 'マッチング',
			'goals'    => array( '依頼したい人がすぐに頼める相手を見つけられる', '提供者が空き時間でスキルを収益化できる', '取引の安全性を運営が担保する' ),
			'kpi'      => array( '月間の成約件数', '提供者の登録数', '依頼から成約までの日数', 'リピート率' ),
			'roles'    => array( array( 'name' => '依頼者', 'desc' => 'スキルを探して依頼・支払いをする' ), array( 'name' => '提供者', 'desc' => 'スキルを出品し、依頼を受けて納品する' ), array( 'name' => '運営', 'desc' => '会員審査・取引の監視・売上管理' ) ),
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNSで登録。依頼者/提供者を切り替え', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'プロフィール・本人確認', 'desc' => '経歴・実績・身分証の提出と審査', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'スキル出品', 'desc' => 'タイトル・価格・納期・写真を登録', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '検索・絞り込み', 'desc' => 'カテゴリー・価格・評価・エリアで検索', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'メッセージ', 'desc' => '依頼前の相談と取引中のやり取り', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '決済（エスクロー）', 'desc' => '依頼時に支払い、完了後に提供者へ', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '評価・レビュー', 'desc' => '取引後に双方を評価', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => '新着メッセージ・取引状況の通知', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'お気に入り', 'desc' => '気になる提供者を保存', 'phase' => '将来', 'size' => 'S', 'base' => true ),
				array( 'name' => 'ビデオ通話', 'desc' => 'オンラインで打ち合わせ', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'screens'  => array(
				$s( 'home', 'ホーム', '依頼者', '人気のスキルとカテゴリーを見せて、探し始めてもらう', array( $p( 'search', 'スキルを検索' ), $p( 'tabs', '', array( 'デザイン', '動画', '翻訳', '相談' ) ), $p( 'cards', '人気のスキル', array( 'ロゴデザイン ¥5,000〜', '動画編集 ¥8,000〜', '英文翻訳 ¥3,000〜', 'キャリア相談 ¥4,000〜' ) ) ), array( 'detail', 'search' ) ),
				$s( 'search', 'さがす', '依頼者', '条件で絞り込んで提供者を探す', array( $p( 'search', 'キーワード' ), $p( 'tabs', '', array( '評価順', '価格順', '新着' ) ), $p( 'list', '検索結果', array( '佐藤 ★4.9 ロゴデザイン', '鈴木 ★4.8 動画編集', '高橋 ★4.7 翻訳' ) ) ), array( 'detail' ) ),
				$s( 'detail', 'スキル詳細', '依頼者', '内容・価格・評価を確認して依頼する', array( $p( 'image', 'ロゴデザイン（3案）' ), $p( 'profile', '佐藤 ★4.9（120件）', array( '納期 5日', '修正 2回まで', '¥5,000' ) ), $p( 'button', 'メッセージで相談する' ), $p( 'button', 'このスキルを依頼する' ) ), array( 'messages', 'order' ) ),
				$s( 'order', '依頼・支払い', '依頼者', '依頼内容を入力して支払う（完了まで運営が預かる）', array( $p( 'form', '依頼内容', array( '依頼の内容', '希望納期', '支払い方法' ) ), $p( 'stats', '', array( '小計 ¥5,000', '手数料 ¥500', '合計 ¥5,500' ) ), $p( 'button', '支払って依頼する' ) ), array( 'mypage' ) ),
				$s( 'messages', 'メッセージ', '依頼者', '提供者とやり取りする', array( $p( 'list', 'やり取り中', array( '佐藤：ラフ案をお送りしました', '鈴木：素材を受け取りました' ) ), $p( 'chat', '佐藤さん', array( 'ロゴの方向性を相談したいです', 'ありがとうございます。参考画像はありますか？' ) ) ), array() ),
				$s( 'sell', '出品する', '提供者', 'スキルを登録して販売を始める', array( $p( 'form', 'スキルの登録', array( 'タイトル', 'カテゴリー', '価格', '納期', '説明', '写真' ) ), $p( 'button', '審査に出す' ) ), array( 'mypage' ) ),
				$s( 'mypage', 'マイページ', '共通', '取引の状況と売上を確認する', array( $p( 'profile', 'あなた', array( '依頼中 2件', '出品中 3件' ) ), $p( 'stats', '', array( '今月の売上 ¥42,000', '評価 ★4.8', '成約 9件' ) ), $p( 'list', '取引の状況', array( 'ロゴデザイン：制作中', '翻訳：納品済み・評価待ち' ) ), $p( 'button', 'スキルを出品する' ) ), array( 'sell' ) ),
			),
			'tabs'     => array( 'home', 'search', 'messages', 'mypage' ),
			'data'     => array( array( 'entity' => '会員', 'fields' => array( '名前', 'メール', '種別（依頼者/提供者）', '本人確認の状態', '評価' ) ), array( 'entity' => 'スキル', 'fields' => array( 'タイトル', 'カテゴリー', '価格', '納期', '説明', '写真', '公開状態' ) ), array( 'entity' => '取引', 'fields' => array( '依頼者', '提供者', 'スキル', '金額', '手数料', '状態', '期限' ) ), array( 'entity' => 'メッセージ', 'fields' => array( '取引', '送信者', '本文', '添付', '既読' ) ), array( 'entity' => '評価', 'fields' => array( '取引', '点数', 'コメント' ) ) ),
			'admin'    => array( '会員の一覧・本人確認の承認', 'スキルの審査・非公開', '取引の監視・トラブル対応', '売上・手数料・振込の管理', 'お知らせ・プッシュ通知の配信', '通報の確認' ),
			'integrations' => array( '決済サービス（Stripe など）', '本人確認サービス', 'プッシュ通知', 'メール配信' ),
			'nonfunctional' => array( 'iOS・Android（Flutterで同時開発）', '管理画面はWebブラウザ', '個人情報・決済情報の保護（SSL・権限管理）', '利用規約・特定商取引法の表記' ),
			'questions' => array( '手数料率と、提供者への振込のタイミング', '本人確認をどこまで必須にするか', 'キャンセル・返金のルール', '公開前に集めたい提供者の人数' ),
		),
		'business' => array(
			'name'     => '現場レポート',
			'summary'  => '日報・点検・在庫を、現場のスマホから記録できる社内アプリ',
			'category' => '業務',
			'goals'    => array( '紙とExcelの転記をなくす', '現場の状況を本部がすぐ把握できる', '報告の抜け漏れを防ぐ' ),
			'kpi'      => array( '日報の提出率', '報告にかかる時間', '在庫の差異件数' ),
			'roles'    => array( array( 'name' => 'スタッフ', 'desc' => '日報・点検・在庫を入力する' ), array( 'name' => 'リーダー', 'desc' => '内容を確認・承認する' ), array( 'name' => '管理者', 'desc' => 'マスタ・権限・集計を管理する' ) ),
			'features' => array(
				array( 'name' => 'ログイン（社員ID）', 'desc' => '社員ごとのアカウントと権限', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '日報', 'desc' => '作業内容・時間・写真を登録', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '点検チェックリスト', 'desc' => '項目ごとにOK/NGと写真', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '在庫の入出庫', 'desc' => 'バーコード読み取りで数量を更新', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '承認フロー', 'desc' => 'リーダーが確認・差し戻し', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'お知らせ・プッシュ通知', 'desc' => '本部からの連絡', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '集計・CSV出力', 'desc' => '日別・現場別の集計', 'phase' => '将来', 'size' => 'M', 'base' => false ),
				array( 'name' => 'オフライン入力', 'desc' => '電波がない現場でも入力し後で送信', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'screens'  => array(
				$s( 'home', 'ホーム', 'スタッフ', '今日やることと未提出を一目で確認', array( $p( 'stats', '', array( '未提出 1件', '点検 2件', '承認待ち 3件' ) ), $p( 'button', '日報を書く' ), $p( 'button', '点検をはじめる' ), $p( 'list', 'お知らせ', array( '明日は安全点検の日です', '在庫棚卸のお願い' ) ) ), array( 'report', 'check' ) ),
				$s( 'report', '日報', 'スタッフ', '今日の作業を記録して提出', array( $p( 'form', '日報', array( '日付', '現場', '作業内容', '作業時間', '写真' ) ), $p( 'button', '提出する' ) ), array( 'home' ) ),
				$s( 'check', '点検', 'スタッフ', 'チェックリストに沿って点検', array( $p( 'list', '点検項目', array( '消火器 OK', '非常口 OK', '照明 NG（写真あり）' ) ), $p( 'button', '点検を完了する' ) ), array( 'home' ) ),
				$s( 'stock', '在庫', 'スタッフ', '入出庫を記録して在庫数を確認', array( $p( 'search', '品名・バーコード' ), $p( 'list', '在庫一覧', array( 'ボルトM8 120個', '手袋L 40双', '養生テープ 18巻' ) ), $p( 'button', 'バーコードで入出庫' ) ), array() ),
				$s( 'approve', '承認', 'リーダー', '提出された日報・点検を確認', array( $p( 'list', '承認待ち', array( '山田：日報（本社現場）', '佐藤：点検（倉庫A）' ) ), $p( 'button', '承認する' ), $p( 'button', '差し戻す' ) ), array() ),
			),
			'tabs'     => array( 'home', 'report', 'stock', 'approve' ),
			'data'     => array( array( 'entity' => '社員', 'fields' => array( '社員ID', '名前', '所属', '権限' ) ), array( 'entity' => '日報', 'fields' => array( '日付', '社員', '現場', '作業内容', '時間', '写真', '承認状態' ) ), array( 'entity' => '点検', 'fields' => array( '日時', '場所', '項目ごとの結果', '写真' ) ), array( 'entity' => '在庫', 'fields' => array( '品名', 'バーコード', '数量', '保管場所', '入出庫の履歴' ) ) ),
			'admin'    => array( '社員・権限の管理', '現場・点検項目・品目のマスタ', '日報・点検の一覧と検索', '集計とCSV出力' ),
			'integrations' => array( 'プッシュ通知', 'バーコード読み取り（カメラ）', '既存システムへのCSV連携' ),
			'nonfunctional' => array( 'iOS・Android（Flutter）', '社内だけで使う配布方法の検討', '権限ごとの閲覧制限', '写真の保存期間' ),
			'questions' => array( '利用する人数と端末（会社支給か個人か）', '既存システムとの連携の有無', '承認の段階数' ),
		),
		'learning' => array(
			'name'     => 'まなびPLUS',
			'summary'  => '動画レッスンを月額で見放題にできる、会員制の学習アプリ',
			'category' => '学習',
			'goals'    => array( '会員が続けて学べる仕組みをつくる', '月額課金で安定した売上にする' ),
			'kpi'      => array( '有料会員数', '継続率（3か月）', '1人あたりの視聴時間' ),
			'roles'    => array( array( 'name' => '会員', 'desc' => '動画を視聴し、課題を提出する' ), array( 'name' => '講師', 'desc' => '動画・課題を登録し、質問に答える' ), array( 'name' => '運営', 'desc' => '会員・売上・コンテンツの管理' ) ),
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNS', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '月額課金（サブスク）', 'desc' => 'アプリ内課金・Web決済', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '動画レッスン', 'desc' => 'コース・章ごとの動画、続きから再生', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '学習の進み具合', 'desc' => '受講率・連続日数', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '質問・コメント', 'desc' => '講師への質問', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'お知らせ・プッシュ通知', 'desc' => '新着レッスン・学習リマインド', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'ライブ配信', 'desc' => 'リアルタイムの授業', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'screens'  => array(
				$s( 'home', 'ホーム', '会員', '続きの動画とおすすめを表示', array( $p( 'hero', '続きから見る：第3回 基本のフォーム' ), $p( 'stats', '', array( '連続 5日', '受講率 42%', '今週 80分' ) ), $p( 'cards', 'おすすめのコース', array( '初級コース', '中級コース', '1日10分トレーニング' ) ) ), array( 'course' ) ),
				$s( 'course', 'コース', '会員', 'コースの章と動画を選ぶ', array( $p( 'image', '初級コース（全12回）' ), $p( 'list', 'レッスン', array( '第1回 はじめに ✓', '第2回 道具の準備 ✓', '第3回 基本のフォーム', '第4回 応用' ) ) ), array( 'player' ) ),
				$s( 'player', '動画を見る', '会員', '動画を見て、質問や課題を送る', array( $p( 'image', '▶ 第3回 基本のフォーム（12:30）' ), $p( 'text', 'このレッスンでは基本のフォームを身につけます。' ), $p( 'chat', '質問', array( '手首の角度が分かりません', '2:15あたりを参考にしてください' ) ) ), array() ),
				$s( 'plan', '料金プラン', '会員', '月額プランに登録する', array( $p( 'cards', 'プラン', array( 'ベーシック ¥980/月', 'プレミアム ¥1,980/月' ) ), $p( 'button', 'プレミアムに登録する' ) ), array( 'mypage' ) ),
				$s( 'mypage', 'マイページ', '会員', '学習の記録と契約を確認', array( $p( 'profile', 'あなた', array( 'プレミアム会員', '次回更新 11/30' ) ), $p( 'stats', '', array( '受講済み 14本', '学習時間 6時間' ) ), $p( 'list', '設定', array( '通知', 'プランの変更', 'ログアウト' ) ) ), array( 'plan' ) ),
			),
			'tabs'     => array( 'home', 'course', 'plan', 'mypage' ),
			'data'     => array( array( 'entity' => '会員', 'fields' => array( '名前', 'メール', 'プラン', '更新日' ) ), array( 'entity' => 'コース', 'fields' => array( 'タイトル', '説明', '講師', '公開状態' ) ), array( 'entity' => 'レッスン', 'fields' => array( 'コース', '順番', '動画', '長さ', '無料/有料' ) ), array( 'entity' => '受講記録', 'fields' => array( '会員', 'レッスン', '視聴位置', '完了日' ) ) ),
			'admin'    => array( 'コース・動画の登録', '会員と契約の管理', '売上の確認', '質問への回答', 'お知らせ・プッシュ通知' ),
			'integrations' => array( 'アプリ内課金（App Store／Google Play）', '動画配信サービス', 'プッシュ通知' ),
			'nonfunctional' => array( 'iOS・Android（Flutter）', '動画の不正ダウンロード対策', '通信量に配慮した画質切り替え' ),
			'questions' => array( 'アプリ内課金かWeb決済か（手数料が異なります）', '動画の本数と1本の長さ', '無料で見せる範囲' ),
		),
		'event'    => array(
			'name'     => 'まちイベ',
			'summary'  => '地域のイベントを探して予約・チケットまでできるアプリ',
			'category' => '予約',
			'goals'    => array( 'イベントの集客を増やす', '受付をスムーズにする' ),
			'kpi'      => array( '予約数', '来場率', 'アプリ経由の新規参加者' ),
			'roles'    => array( array( 'name' => '参加者', 'desc' => 'イベントを探して予約する' ), array( 'name' => '主催者', 'desc' => 'イベントを登録して受付する' ), array( 'name' => '運営', 'desc' => '主催者と掲載の管理' ) ),
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNS', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'イベント一覧・検索', 'desc' => '日付・エリア・ジャンル', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '予約・電子チケット', 'desc' => 'QRコードのチケット', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '受付（QR読み取り）', 'desc' => '主催者がチケットを読み取る', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'お知らせ・プッシュ通知', 'desc' => '前日リマインド', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '事前決済', 'desc' => '有料イベントの支払い', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'screens'  => array(
				$s( 'home', 'ホーム', '参加者', '今週のイベントとおすすめ', array( $p( 'hero', '今週末のおすすめイベント' ), $p( 'tabs', '', array( '今日', '今週', '来週' ) ), $p( 'cards', 'イベント', array( '朝市マルシェ 10/26', '親子ワークショップ 10/27', 'ナイトラン 11/1' ) ) ), array( 'detail', 'search' ) ),
				$s( 'search', 'さがす', '参加者', '条件でイベントを探す', array( $p( 'search', 'キーワード・エリア' ), $p( 'map', '近くのイベント' ), $p( 'list', '検索結果', array( '朝市マルシェ', 'ジャズライブ' ) ) ), array( 'detail' ) ),
				$s( 'detail', 'イベント詳細', '参加者', '内容を確認して予約', array( $p( 'image', '朝市マルシェ' ), $p( 'text', '地元の野菜とパンが集まる朝市です。' ), $p( 'calendar', '参加日時' ), $p( 'button', '予約する' ) ), array( 'ticket' ) ),
				$s( 'ticket', 'チケット', '参加者', '当日に見せるQRチケット', array( $p( 'profile', '朝市マルシェ', array( '10/26（土）9:00', '大人2名' ) ), $p( 'image', 'QRコード' ), $p( 'button', 'カレンダーに追加' ) ), array() ),
				$s( 'mypage', 'マイページ', '参加者', '予約の一覧と設定', array( $p( 'list', '予約中', array( '朝市マルシェ 10/26', 'ナイトラン 11/1' ) ), $p( 'list', '設定', array( '通知', 'お気に入りのジャンル' ) ) ), array( 'ticket' ) ),
			),
			'tabs'     => array( 'home', 'search', 'ticket', 'mypage' ),
			'data'     => array( array( 'entity' => '会員', 'fields' => array( '名前', 'メール', '好きなジャンル' ) ), array( 'entity' => 'イベント', 'fields' => array( '名前', '日時', '場所', '定員', '料金', '主催者' ) ), array( 'entity' => '予約', 'fields' => array( '会員', 'イベント', '人数', 'チケット番号', '受付状態' ) ) ),
			'admin'    => array( '主催者・イベントの管理', '予約と受付の状況', 'お知らせ・プッシュ通知' ),
			'integrations' => array( 'プッシュ通知', '地図', 'カレンダー' ),
			'nonfunctional' => array( 'iOS・Android（Flutter）', '当日の同時アクセスへの備え' ),
			'questions' => array( '有料イベントの決済の有無', '主催者が自分で登録するか、運営が登録するか' ),
		),
	);
	$spec = $T[ $kind ];
	if ( 'matching' === $kind && $genre && function_exists( 'apprex_orig_genre_template' ) ) {
		$spec = apprex_orig_genre_template( $spec, $genre );
	}
	if ( $genre && function_exists( 'apprex_orig_genres' ) ) {
		$all            = apprex_orig_genres();
		$spec['genre']  = $genre;
		$spec['design'] = array( 'key' => $genre ) + $all[ $genre ]['design'];
	}
	if ( $name ) {
		$spec['name'] = $name;
	}
	$spec['idea'] = $idea;
	return $spec;
}

/* -------------------------------------------------------------------------
 * 検証
 * ---------------------------------------------------------------------- */

/**
 * AIの出力（またはひな形）を安全な形に整える。
 *
 * @param mixed $in 入力。
 * @return array|null
 */
function apprex_orig_sanitize( $in ) {
	if ( ! is_array( $in ) || empty( $in['screens'] ) || ! is_array( $in['screens'] ) ) {
		return null;
	}
	$str  = function ( $v, $n ) {
		return is_scalar( $v ) ? mb_substr( sanitize_text_field( (string) $v ), 0, $n ) : '';
	};
	$list = function ( $v, $max, $n ) use ( $str ) {
		return is_array( $v ) ? array_values( array_filter( array_map( function ( $x ) use ( $str, $n ) {
			return $str( $x, $n );
		}, array_slice( $v, 0, $max ) ) ) ) : array();
	};
	$id    = function ( $v ) {
		$v = strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $v ) );
		return $v ? substr( $v, 0, 24 ) : '';
	};
	$types = apprex_orig_part_types();
	$out   = array(
		'name'     => $str( isset( $in['name'] ) ? $in['name'] : 'アプリ', 30 ),
		'summary'  => $str( isset( $in['summary'] ) ? $in['summary'] : '', 120 ),
		'category' => $str( isset( $in['category'] ) ? $in['category'] : 'その他', 20 ),
		'genre'    => function_exists( 'apprex_orig_genre_key' ) ? apprex_orig_genre_key( isset( $in['genre'] ) ? $in['genre'] : '' ) : '',
		'design'   => function_exists( 'apprex_orig_design_clean' ) ? apprex_orig_design_clean( isset( $in['design'] ) ? $in['design'] : null ) : null,
		'idea'     => $str( isset( $in['idea'] ) ? $in['idea'] : '', 600 ),
		'goals'    => $list( isset( $in['goals'] ) ? $in['goals'] : array(), 6, 80 ),
		'kpi'      => $list( isset( $in['kpi'] ) ? $in['kpi'] : array(), 6, 60 ),
		'admin'    => $list( isset( $in['admin'] ) ? $in['admin'] : array(), 12, 60 ),
		'integrations'  => $list( isset( $in['integrations'] ) ? $in['integrations'] : array(), 10, 60 ),
		'nonfunctional' => $list( isset( $in['nonfunctional'] ) ? $in['nonfunctional'] : array(), 10, 80 ),
		'questions'     => $list( isset( $in['questions'] ) ? $in['questions'] : array(), 10, 100 ),
		'roles'    => array(),
		'features' => array(),
		'screens'  => array(),
		'data'     => array(),
		'tabs'     => array(),
	);
	foreach ( array_slice( (array) ( isset( $in['roles'] ) ? $in['roles'] : array() ), 0, 6 ) as $r ) {
		if ( is_array( $r ) && ! empty( $r['name'] ) ) {
			$out['roles'][] = array( 'name' => $str( $r['name'], 20 ), 'desc' => $str( isset( $r['desc'] ) ? $r['desc'] : '', 80 ) );
		}
	}
	foreach ( array_slice( (array) ( isset( $in['features'] ) ? $in['features'] : array() ), 0, 30 ) as $f ) {
		if ( is_array( $f ) && ! empty( $f['name'] ) ) {
			$size              = isset( $f['size'] ) && in_array( strtoupper( (string) $f['size'] ), array( 'S', 'M', 'L' ), true ) ? strtoupper( $f['size'] ) : 'M';
			$out['features'][] = array(
				'name'  => $str( $f['name'], 30 ),
				'desc'  => $str( isset( $f['desc'] ) ? $f['desc'] : '', 100 ),
				'phase' => ( isset( $f['phase'] ) && false !== mb_strpos( (string) $f['phase'], '将来' ) ) ? '将来' : 'MVP',
				'size'  => $size,
				'base'  => ! empty( $f['base'] ) && 'false' !== $f['base'],
			);
		}
	}
	$ids = array();
	foreach ( array_slice( $in['screens'], 0, 14 ) as $i => $sc ) {
		if ( ! is_array( $sc ) ) {
			continue;
		}
		$sid = $id( isset( $sc['id'] ) ? $sc['id'] : '' );
		$sid = ( $sid && ! in_array( $sid, $ids, true ) ) ? $sid : 's' . $i;
		$ids[] = $sid;
		$parts = array();
		foreach ( array_slice( (array) ( isset( $sc['parts'] ) ? $sc['parts'] : array() ), 0, 8 ) as $pt ) {
			if ( is_array( $pt ) && isset( $pt['t'] ) && in_array( $pt['t'], $types, true ) ) {
				$parts[] = array( 't' => $pt['t'], 'label' => $str( isset( $pt['label'] ) ? $pt['label'] : '', 40 ), 'items' => $list( isset( $pt['items'] ) ? $pt['items'] : array(), 8, 40 ) );
			}
		}
		$out['screens'][] = array(
			'id'      => $sid,
			'name'    => $str( isset( $sc['name'] ) ? $sc['name'] : $sid, 16 ),
			'role'    => $str( isset( $sc['role'] ) ? $sc['role'] : '', 16 ),
			'purpose' => $str( isset( $sc['purpose'] ) ? $sc['purpose'] : '', 100 ),
			'parts'   => $parts,
			'to'      => array_values( array_filter( array_map( $id, array_slice( (array) ( isset( $sc['to'] ) ? $sc['to'] : array() ), 0, 4 ) ) ) ),
		);
	}
	if ( ! $out['screens'] ) {
		return null;
	}
	// 遷移先・タブは実在する画面だけ。
	foreach ( $out['screens'] as &$sc ) {
		$sc['to'] = array_values( array_intersect( $sc['to'], $ids ) );
	}
	unset( $sc );
	$tabs        = array_values( array_intersect( array_map( $id, (array) ( isset( $in['tabs'] ) ? $in['tabs'] : array() ) ), $ids ) );
	$out['tabs'] = array_slice( $tabs ? $tabs : array_slice( $ids, 0, 4 ), 0, 5 );
	foreach ( array_slice( (array) ( isset( $in['data'] ) ? $in['data'] : array() ), 0, 12 ) as $d ) {
		if ( is_array( $d ) && ! empty( $d['entity'] ) ) {
			$out['data'][] = array( 'entity' => $str( $d['entity'], 20 ), 'fields' => $list( isset( $d['fields'] ) ? $d['fields'] : array(), 14, 30 ) );
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * REST：AIでオリジナルアプリを作る／作り直す
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/studio/ai-orig',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_orig_rest',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
} );

/**
 * 希望（と、今の企画への修正指示）から、アプリの企画を作る。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_orig_rest( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$uid   = get_current_user_id();
	$idea  = mb_substr( sanitize_textarea_field( (string) $request->get_param( 'idea' ) ), 0, 1000 );
	$instr = mb_substr( sanitize_textarea_field( (string) $request->get_param( 'instruction' ) ), 0, 500 );
	$name  = mb_substr( sanitize_text_field( (string) $request->get_param( 'name' ) ), 0, 30 );
	$cur   = apprex_orig_sanitize( json_decode( (string) $request->get_param( 'current' ), true ) );
	$genre = function_exists( 'apprex_orig_genre_key' ) ? apprex_orig_genre_key( $request->get_param( 'genre' ) ) : '';
	if ( $cur && ! $genre ) {
		$genre = $cur['genre'];
	}
	if ( '' === $idea && ! $cur ) {
		return new WP_Error( 'bad_request', '作りたいアプリの内容を書いてください。', array( 'status' => 400 ) );
	}

	// 1日の回数制限。
	$use   = get_user_meta( $uid, 'apprex_orig_usage', true );
	$n     = ( is_array( $use ) && isset( $use['d'] ) && wp_date( 'Ymd' ) === $use['d'] ) ? (int) $use['n'] : 0;
	$limit = (int) apply_filters( 'apprex_orig_daily_limit', 20 );
	if ( $n >= $limit ) {
		return new WP_Error( 'limit', '本日の作成回数の上限に達しました。明日またお試しください。', array( 'status' => 429 ) );
	}
	update_user_meta( $uid, 'apprex_orig_usage', array( 'd' => wp_date( 'Ymd' ), 'n' => $n + 1 ) );

	$spec   = null;
	$source = 'template';
	if ( apprex_ai_text_ready() ) {
		$schema = '{"name":"アプリ名（20字以内）","summary":"一言の説明（60字以内）","category":"マッチング|EC|予約|業務|コミュニティ|学習|メディア|その他",'
			. '"goals":["目的（3〜4個）"],"kpi":["指標（3〜4個）"],"roles":[{"name":"利用者の種類","desc":"役割"}],'
			. '"features":[{"name":"機能名","desc":"説明（40字以内）","phase":"MVP|将来","size":"S|M|L","base":true/false}],'
			. '"screens":[{"id":"英小文字のID","name":"画面名（8字以内）","role":"使う人","purpose":"画面の目的","parts":[{"t":"' . implode( '|', apprex_orig_part_types() ) . '","label":"見出し","items":["中身の例（具体的に）"]}],"to":["遷移先の画面ID"]}],'
			. '"tabs":["下のタブにする画面ID（4つ）"],"data":[{"entity":"データ名","fields":["項目"]}],'
			. '"admin":["管理画面の機能"],"integrations":["外部連携"],"nonfunctional":["非機能要件"],"questions":["お客様に確認すべきこと"]}';
		$system = "あなたは日本のアプリ開発会社のプロダクトマネージャー兼UIデザイナーです。お客様の希望から、モバイルアプリ（iOS/Android、Flutter想定）の企画を作ります。\n"
			. "目的は、お客様にイメージを早く共有して商談を進めることです。現実的で、MVP（最初に公開する最小限）を中心にします。\n"
			. "ルール：画面は5〜9個。各画面の parts は2〜5個で、items には実際に表示されそうな具体的な例を入れる（金額・件数・名前など）。"
			. "features の size は工数の目安（S=小、M=中、L=大）。会員登録・プッシュ通知・お知らせ・クーポン・予約・ポイントなど一般的な機能は base=true（APPREXの基盤で対応できる）。"
			. "誇大な表現や法的な断定はしない。questions には見積り前に確認すべき点を入れる。\n"
			. "swipe は、相手のプロフィールを1枚ずつ見て「いいね／スキップ」する部品（恋活・婚活など。items は「名前 年齢 地域・ひとこと」）。\n"
			. ( function_exists( 'apprex_orig_genre_prompt' ) ? apprex_orig_genre_prompt( $genre ) : '' )
			. "必ずJSONだけを出力（説明やコードブロックは不要）。形式：\n" . $schema;
		$user = $cur
			? "今の企画：" . wp_json_encode( $cur, JSON_UNESCAPED_UNICODE ) . "\n\n修正の指示：" . ( $instr ? $instr : '全体をより良くしてください' ) . "\n（指示に関係しない部分はなるべく変えない）"
			: "お客様の希望：" . $idea . ( $name ? "\nアプリ名の希望：" . $name : '' );
		$res = apprex_studio_complete(
			array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $user ) ),
			array( 'temperature' => 0.5, 'max_tokens' => 3800, 'timeout' => 90 )
		);
		if ( ! is_wp_error( $res ) ) {
			$spec = apprex_orig_sanitize( apprex_studio_json( $res ) );
			if ( $spec ) {
				$source       = 'ai';
				$spec['idea'] = $cur ? $cur['idea'] : $idea;
			}
		}
	}
	if ( ! $spec ) {
		if ( $cur && $instr ) {
			// ひな形モードでは、修正指示は申し送りとして残す。
			$spec                = $cur;
			$spec['questions'][] = 'ご要望：' . mb_substr( $instr, 0, 90 );
		} else {
			$spec = apprex_orig_sanitize( apprex_orig_template( $idea ? $idea : $cur['idea'], $name, $genre ) );
		}
	}
	// ジャンルとデザイン：作り直しでは今のデザインを残す。新しく作るときはジャンルのデザイン。
	if ( $genre && function_exists( 'apprex_orig_genres' ) ) {
		$all           = apprex_orig_genres();
		$spec['genre'] = $genre;
		if ( 'other' !== $genre ) {
			$spec['category'] = 'マッチング';
		}
		$spec['design'] = ( $cur && $cur['design'] ) ? $cur['design'] : apprex_orig_design_clean( array( 'key' => $genre ) + $all[ $genre ]['design'] );
	} elseif ( $cur && $cur['design'] ) {
		$spec['design'] = $cur['design'];
	}
	update_user_meta( $uid, 'apprex_orig_spec', wp_slash( wp_json_encode( $spec, JSON_UNESCAPED_UNICODE ) ) );
	return rest_ensure_response( array( 'ok' => true, 'spec' => $spec, 'source' => $source, 'demo' => apprex_demo_url( apprex_demo_token( $uid ), array( 'v' => 'orig' ) ) ) );
}

/* -------------------------------------------------------------------------
 * 試作に写真を入れる（OpenRouter の画像モデル。文字は入れず、写真・イラストだけ）
 * ---------------------------------------------------------------------- */

/**
 * 写真を入れる場所（画面ID_部品番号[_項目番号]）。app-demo.js と同じ並び。
 *
 * @param array $spec 企画。
 * @param int   $max  最大数。
 * @return array<int,array{key:string,label:string,wide:bool}>
 */
function apprex_orig_image_slots( $spec, $max = 4 ) {
	$out = array();
	foreach ( $spec['screens'] as $sc ) {
		foreach ( $sc['parts'] as $pi => $pt ) {
			if ( in_array( $pt['t'], array( 'hero', 'image' ), true ) ) {
				$out[] = array( 'key' => $sc['id'] . '_' . $pi, 'label' => preg_replace( '/^▶\s*/u', '', $pt['label'] ), 'wide' => true );
			} elseif ( in_array( $pt['t'], array( 'cards', 'swipe' ), true ) ) {
				foreach ( array_slice( $pt['items'], 0, 3 ) as $ii => $it ) {
					$out[] = array( 'key' => $sc['id'] . '_' . $pi . '_' . $ii, 'label' => $it, 'wide' => false );
				}
			}
			if ( count( $out ) >= $max ) {
				return array_slice( $out, 0, $max );
			}
		}
	}
	return $out;
}

/** 試作に入れた写真（キー => URL）。 */
function apprex_orig_images_out( $user_id ) {
	$m   = get_user_meta( $user_id, 'apprex_orig_images', true );
	$out = array();
	foreach ( is_array( $m ) ? $m : array() as $k => $id ) {
		$url = wp_get_attachment_url( (int) $id );
		if ( $url ) {
			$out[ $k ] = $url;
		}
	}
	return (object) $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/studio/orig-image',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_orig_image_rest',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
} );

/**
 * slot が空なら写真を入れる場所の一覧を返し、指定があればその1枚を作る（1回の通信で1枚）。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_orig_image_rest( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$uid  = get_current_user_id();
	$spec = apprex_orig_sanitize( json_decode( (string) get_user_meta( $uid, 'apprex_orig_spec', true ), true ) );
	if ( ! $spec ) {
		return new WP_Error( 'bad_request', '先にアプリの試作を作ってください。', array( 'status' => 400 ) );
	}
	$slots = apprex_orig_image_slots( $spec );
	$use   = get_user_meta( $uid, 'apprex_orig_img_usage', true );
	$n     = ( is_array( $use ) && isset( $use['d'] ) && wp_date( 'Ymd' ) === $use['d'] ) ? (int) $use['n'] : 0;
	$limit = (int) apply_filters( 'apprex_orig_image_daily_limit', 12 );
	$key   = sanitize_text_field( (string) $request->get_param( 'slot' ) );
	if ( '' === $key ) {
		return rest_ensure_response( array( 'ok' => true, 'slots' => $slots, 'left' => max( 0, $limit - $n ), 'ready' => '' !== apprex_openrouter_key() ) );
	}
	if ( '' === apprex_openrouter_key() ) {
		return new WP_Error( 'not_configured', '写真の生成は準備中です。担当者が設定すると使えるようになります。', array( 'status' => 503 ) );
	}
	$slot = null;
	foreach ( $slots as $s ) {
		if ( $s['key'] === $key ) {
			$slot = $s;
		}
	}
	if ( ! $slot ) {
		return new WP_Error( 'bad_request', '写真を入れる場所が見つかりません。', array( 'status' => 400 ) );
	}
	if ( $n >= $limit ) {
		return new WP_Error( 'limit', '本日の写真の生成回数の上限に達しました。明日またお試しください。', array( 'status' => 429 ) );
	}
	update_user_meta( $uid, 'apprex_orig_img_usage', array( 'd' => wp_date( 'Ymd' ), 'n' => $n + 1 ) );

	$prompt = sprintf(
		'High-quality %1$s for a Japanese mobile app (%2$s: %3$s). Subject: "%4$s". Clean, modern, natural light, realistic Japanese setting where people appear. %5$s. Absolutely no text, letters, numbers, logos, watermarks or UI elements in the image.',
		'photograph or polished illustration',
		$spec['name'],
		$spec['summary'],
		$slot['label'],
		$slot['wide'] ? 'Landscape 16:9 composition' : 'Square 1:1 composition, subject centered'
	);
	$img = apprex_openrouter_image( $prompt, array( 'model' => apprex_bulk_image_model() ) );
	if ( is_wp_error( $img ) && apprex_bulk_image_model() !== apprex_image_model() ) {
		$img = apprex_openrouter_image( $prompt ); // 予備：通常の画像モデルでやり直す。
	}
	if ( is_wp_error( $img ) ) {
		return new WP_Error( 'failed', '写真を作れませんでした。少し時間をおいてお試しください。', array( 'status' => 502 ) );
	}
	$att = apprex_save_generated_image( $img, 'AIスタジオ-' . $uid . '-試作-' . $key );
	if ( is_wp_error( $att ) ) {
		return new WP_Error( 'failed', '写真を保存できませんでした。', array( 'status' => 500 ) );
	}
	update_post_meta( $att, '_apprex_studio_user', $uid );
	$map = get_user_meta( $uid, 'apprex_orig_images', true );
	$map = is_array( $map ) ? $map : array();
	if ( ! empty( $map[ $key ] ) ) {
		wp_delete_attachment( (int) $map[ $key ], true );
	}
	$map[ $key ] = $att;
	update_user_meta( $uid, 'apprex_orig_images', $map );
	return rest_ensure_response( array( 'ok' => true, 'images' => apprex_orig_images_out( $uid ), 'left' => max( 0, $limit - $n - 1 ) ) );
}
