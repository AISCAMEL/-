<?php
/**
 * マッチングアプリのジャンル（AIスタジオ・マッチング）。
 *
 * - ジャンルを選ぶと、AIがそのジャンルに合った画面・機能・届出の確認事項で試作を作る
 * - ジャンルごとのデザイン（色・文字・角の丸み・見せ方）で試作を表示する
 * - 試作を作ったあとも、デザインだけを別のジャンルの見た目に切り替えられる
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ジャンルの一覧。design.tone は studio-data.js の TONES（luxury|pop|natural|sporty|trust）。
 *
 * @return array<string,array>
 */
function apprex_orig_genres() {
	return apply_filters(
		'apprex_orig_genres',
		array(
			'skill'    => array(
				'label'  => 'スキルマッチング',
				'icon'   => '🎨',
				'desc'   => '得意なことを売り買い（デザイン・翻訳・相談など）',
				'idea'   => 'デザインや翻訳など、スキルを売り買いできるマッチングアプリ。依頼・メッセージ・決済まで',
				'design' => array( 'tone' => 'trust', 'c1' => '#1e3a8a', 'c2' => '#3b82f6', 'name' => 'クール・信頼感' ),
				'hint'   => 'スキルの出品・検索・依頼・メッセージ・決済（エスクロー）・評価が中心。ホームは人気のスキルをカード（cards）で見せる。',
				'must'   => array( '手数料と振込のタイミング（資金決済のルールの確認）', '利用規約・特定商取引法の表記' ),
			),
			'business' => array(
				'label'  => 'ビジネスマッチング',
				'icon'   => '🤝',
				'desc'   => '発注・受注、提携先や取引先さがし（BtoB）',
				'idea'   => '仕事を発注したい会社と、受注したい会社をつなぐビジネスマッチングアプリ。案件の掲載・提案・商談まで',
				'design' => array( 'tone' => 'trust', 'c1' => '#0f2a44', 'c2' => '#2b6cb0', 'name' => '落ち着いた紺・ビジネス' ),
				'hint'   => '会社プロフィール・案件の掲載・提案（見積り）・商談のメッセージ・契約の状況が中心。一覧（list）で案件を探しやすく。',
				'must'   => array( '会社の審査（法人確認）の方法', '成約手数料か月額掲載料か（収益の形）' ),
			),
			'dating'   => array(
				'label'  => '恋活・婚活',
				'icon'   => '💞',
				'desc'   => '恋人・結婚相手・出会いをさがす',
				'idea'   => '真剣な出会いをさがす恋活・婚活のマッチングアプリ。プロフィールを見て「いいね」し、マッチングしたらメッセージ',
				'design' => array( 'tone' => 'pop', 'c1' => '#9d174d', 'c2' => '#ec4899', 'name' => 'やわらかいピンク・ポップ' ),
				'hint'   => 'さがす画面は、相手のプロフィールを1枚ずつ見て「いいね／スキップ」する swipe 部品（items は「名前 年齢 地域・ひとこと」の形で3〜5人）。マッチング成立→メッセージ。本人確認・年齢確認・通報とブロック・有料会員（月額）を必ず入れる。',
				'must'   => array( 'インターネット異性紹介事業の届出（事業所を管轄する公安委員会）', '18歳未満の利用を禁止し、公的書類で年齢確認', '通報・ブロック・投稿の監視の体制', '有料会員の料金と、男女で料金を分けるか' ),
			),
			'job'      => array(
				'label'  => '求人・人材',
				'icon'   => '💼',
				'desc'   => '働きたい人と会社・お店をつなぐ',
				'idea'   => '地域のお店と働きたい人をつなぐ求人マッチングアプリ。求人の検索・応募・面接の日程調整まで',
				'design' => array( 'tone' => 'natural', 'c1' => '#065f46', 'c2' => '#10b981', 'name' => 'さわやかな緑・ナチュラル' ),
				'hint'   => '求人の一覧（list：時給・勤務地・条件）・求人詳細・応募フォーム・面接の日程（calendar）・会社とのメッセージが中心。',
				'must'   => array( '職業紹介にあたるか（有料職業紹介事業の許可が必要か）の確認', '求人情報の掲載ルール（募集情報等提供事業の届出の要否）' ),
			),
			'local'    => array(
				'label'  => '家事代行・地域サービス',
				'icon'   => '🏠',
				'desc'   => '家事・ペット・送迎など、地域の手伝いを頼む',
				'idea'   => '家事代行やペットシッターを近所の人にお願いできる地域のマッチングアプリ。地図で探して予約・支払いまで',
				'design' => array( 'tone' => 'natural', 'c1' => '#9a3412', 'c2' => '#f97316', 'name' => 'あたたかいオレンジ・ナチュラル' ),
				'hint'   => '近くの人を地図（map）と一覧で探す・プロフィールと口コミ・日時を選んで予約（calendar）・支払い・作業完了の報告が中心。',
				'must'   => array( '提供者の本人確認と、事故・トラブル時の保険', 'キャンセル料と返金のルール' ),
			),
			'flea'     => array(
				'label'  => 'フリマ・売り買い',
				'icon'   => '🛍',
				'desc'   => 'モノを出品して売る・買う',
				'idea'   => '使わなくなったモノを出品して売り買いできるフリマアプリ。写真で出品・購入・発送の連絡まで',
				'design' => array( 'tone' => 'pop', 'c1' => '#991b1b', 'c2' => '#ef4444', 'name' => '元気な赤・ポップ' ),
				'hint'   => 'ホームは商品をカード（cards：商品名と価格）で並べる。出品（写真・価格）・購入と支払い・発送の連絡・評価が中心。',
				'must'   => array( '古物営業の許可が必要な形か（運営が買い取るかどうか）の確認', '出品禁止物のルールと監視', '特定商取引法の表記' ),
			),
			'hobby'    => array(
				'label'  => '趣味・仲間さがし',
				'icon'   => '🎾',
				'desc'   => 'スポーツ・趣味の仲間やサークルを見つける',
				'idea'   => 'テニスやランニングなど、趣味の仲間とイベントを見つけられるマッチングアプリ。募集・参加・グループチャットまで',
				'design' => array( 'tone' => 'sporty', 'c1' => '#4c1d95', 'c2' => '#8b5cf6', 'name' => '鮮やかな紫・スポーティ' ),
				'hint'   => '募集（日時・場所・人数）の一覧と地図・参加ボタン・グループのチャット・プロフィールが中心。',
				'must'   => array( '恋愛目的の利用をどう扱うか（異性紹介にあたらない運営の確認）', '当日のケガ・トラブルの責任の範囲' ),
			),
			'other'    => array(
				'label'  => 'その他（自由に書く）',
				'icon'   => '✏️',
				'desc'   => '上にないマッチング・オリジナルアプリ',
				'idea'   => '',
				'design' => array( 'tone' => 'trust', 'c1' => '#1f2937', 'c2' => '#2f66f5', 'name' => 'シンプル・信頼感' ),
				'hint'   => '',
				'must'   => array(),
			),
		)
	);
}

/** ジャンルのキーとして正しいか。 */
function apprex_orig_genre_key( $g ) {
	$g   = sanitize_key( (string) $g );
	$all = apprex_orig_genres();
	return isset( $all[ $g ] ) ? $g : '';
}

/** 画面に渡す形（studio.js）。 */
function apprex_orig_genres_js() {
	$out = array();
	foreach ( apprex_orig_genres() as $k => $g ) {
		$out[] = array( 'k' => $k, 'label' => $g['label'], 'icon' => $g['icon'], 'desc' => $g['desc'], 'idea' => $g['idea'], 'design' => $g['design'] );
	}
	return $out;
}

/**
 * デザインを安全な形にする。
 *
 * @param mixed $d デザイン。
 * @return array|null
 */
function apprex_orig_design_clean( $d ) {
	if ( ! is_array( $d ) ) {
		return null;
	}
	$hex  = function ( $v ) {
		return is_string( $v ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? strtolower( $v ) : '';
	};
	$tone = isset( $d['tone'] ) && in_array( $d['tone'], array( 'luxury', 'pop', 'natural', 'sporty', 'trust' ), true ) ? $d['tone'] : 'trust';
	$c1   = $hex( isset( $d['c1'] ) ? $d['c1'] : '' );
	$c2   = $hex( isset( $d['c2'] ) ? $d['c2'] : '' );
	if ( ! $c1 || ! $c2 ) {
		return null;
	}
	return array(
		'key'  => apprex_orig_genre_key( isset( $d['key'] ) ? $d['key'] : '' ),
		'tone' => $tone,
		'c1'   => $c1,
		'c2'   => $c2,
		'name' => isset( $d['name'] ) ? mb_substr( sanitize_text_field( (string) $d['name'] ), 0, 30 ) : '',
	);
}

/**
 * AIへの指示に足す文（ジャンルの典型と、必ず入れる確認事項）。
 *
 * @param string $genre ジャンル。
 * @return string
 */
function apprex_orig_genre_prompt( $genre ) {
	$all = apprex_orig_genres();
	if ( ! $genre || empty( $all[ $genre ] ) || 'other' === $genre ) {
		return '';
	}
	$g = $all[ $genre ];
	$t = "\nこのアプリのジャンル：マッチングアプリ（" . $g['label'] . '：' . $g['desc'] . "）。category は「マッチング」。\n";
	if ( $g['hint'] ) {
		$t .= 'このジャンルらしい画面と機能：' . $g['hint'] . "\n";
	}
	if ( $g['must'] ) {
		$t .= 'questions または nonfunctional に必ず入れること：' . implode( '／', $g['must'] ) . "\n";
	}
	return $t;
}

/**
 * ジャンルのひな形（AIが使えないとき）。スキルマッチングのひな形をもとに、ジャンルの中身に差し替える。
 *
 * @param array  $base  スキルマッチングのひな形。
 * @param string $genre ジャンル。
 * @return array
 */
function apprex_orig_genre_template( $base, $genre ) {
	$p = 'apprex_orig_part';
	$s = 'apprex_orig_screen';
	$G = array(
		'business' => array(
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNSで登録', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '会社プロフィール・法人確認', 'desc' => '会社情報・実績・登記の確認', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '案件の掲載', 'desc' => '予算・納期・内容を掲載', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '案件の検索', 'desc' => '業種・地域・予算で検索', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '提案・見積り', 'desc' => '案件への提案と見積りの提出', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '商談メッセージ', 'desc' => '提案後のやり取り・資料の添付', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => '新着・メッセージ・状況の通知', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '成約の管理・手数料', 'desc' => '成約の記録と手数料の請求', 'phase' => '将来', 'size' => 'M', 'base' => false ),
				array( 'name' => 'オンライン商談', 'desc' => 'ビデオ通話', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'data'    => array( array( 'entity' => '会社', 'fields' => array( '会社名', '業種', '所在地', '実績', '法人確認の状態' ) ), array( 'entity' => '案件', 'fields' => array( '案件名', '業種', '予算', '納期', '内容', '締切', '状態' ) ), array( 'entity' => '提案', 'fields' => array( '案件', '提案した会社', '見積り金額', '内容', '状態' ) ), array( 'entity' => 'メッセージ', 'fields' => array( '商談', '送信者', '本文', '添付' ) ) ),
			'word'    => '案件',
			'goals'   => array( '発注したい会社が、条件に合う取引先をすぐ見つけられる', '受注したい会社が、営業の手間をかけずに案件を得られる' ),
			'kpi'     => array( '掲載案件数', '提案数', '成約件数', '成約までの日数' ),
			'name'    => 'BizLink',
			'summary' => '仕事を発注したい会社と、受注したい会社をつなぐビジネスマッチングアプリ',
			'roles'   => array( array( 'name' => '発注企業', 'desc' => '案件を掲載し、提案を比べて発注する' ), array( 'name' => '受注企業', 'desc' => '案件を探して提案・見積りを出す' ), array( 'name' => '運営', 'desc' => '企業の審査・取引の監視' ) ),
			'screens' => array(
				$s( 'home', 'ホーム', '受注企業', '新着の案件と、自社に合う案件を見せる', array( $p( 'search', '案件・業種で検索' ), $p( 'tabs', '', array( 'Web制作', '製造', '物流', '採用' ) ), $p( 'list', '新着の案件', array( 'ECサイトの改修（予算 80万円）', '部品の試作 100個（予算 30万円）', '倉庫の配送委託（月額）' ) ) ), array( 'detail', 'search' ) ),
				$s( 'search', 'さがす', '受注企業', '条件で案件を探す', array( $p( 'search', 'キーワード・地域' ), $p( 'tabs', '', array( '新着', '予算順', '締切が近い' ) ), $p( 'list', '検索結果', array( '株式会社A：アプリ開発の相談', 'B製作所：金型の製作', 'C商事：販路の提携先' ) ) ), array( 'detail' ) ),
				$s( 'detail', '案件詳細', '受注企業', '内容と条件を見て提案する', array( $p( 'profile', '株式会社A（東京都・IT）', array( '予算 80万円', '納期 2か月', '提案 4社' ) ), $p( 'text', 'ECサイトの決済と会員機能の改修をお願いできる会社を探しています。' ), $p( 'button', '提案・見積りを送る' ), $p( 'button', '質問する' ) ), array( 'propose', 'messages' ) ),
				$s( 'propose', '提案', '受注企業', '見積りと提案内容を送る', array( $p( 'form', '提案内容', array( '提案の内容', '見積り金額', '納期', '実績の資料' ) ), $p( 'button', '提案を送る' ) ), array( 'mypage' ) ),
				$s( 'messages', '商談', '共通', '提案のあとの商談', array( $p( 'list', '商談中', array( '株式会社A：オンライン面談の日程', 'B製作所：図面を受け取りました' ) ), $p( 'chat', '株式会社A', array( '提案ありがとうございます。来週お話しできますか？', '火曜の15時はいかがでしょうか' ) ) ), array() ),
				$s( 'post', '案件を出す', '発注企業', '案件を掲載して提案を集める', array( $p( 'form', '案件の掲載', array( '案件名', '業種', '予算', '納期', '内容' ) ), $p( 'button', '審査に出す' ) ), array( 'mypage' ) ),
				$s( 'mypage', 'マイページ', '共通', '会社情報と取引の状況', array( $p( 'profile', '自社', array( '法人確認済み', '提案中 3件' ) ), $p( 'stats', '', array( '提案 12件', '成約 4件', '評価 ★4.7' ) ), $p( 'button', '案件を出す' ) ), array( 'post' ) ),
			),
			'tabs'    => array( 'home', 'search', 'messages', 'mypage' ),
		),
		'dating'   => array(
			'name'    => 'Hoshi',
			'summary' => '真剣な出会いをさがす人のための、恋活・婚活マッチングアプリ',
			'roles'   => array( array( 'name' => '会員', 'desc' => 'プロフィールを見て「いいね」し、マッチングした相手とメッセージする' ), array( 'name' => '運営', 'desc' => '本人確認・年齢確認・通報への対応' ) ),
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => '電話番号・SNSで登録', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '本人確認・年齢確認', 'desc' => '公的書類で18歳以上を確認', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'プロフィール', 'desc' => '写真・自己紹介・趣味・結婚への考え', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'さがす・いいね', 'desc' => 'カードを見ていいね／スキップ', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'マッチング・メッセージ', 'desc' => '両思いになったらメッセージ', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '通報・ブロック', 'desc' => '不適切な相手を通報・非表示', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => '有料会員（月額）', 'desc' => 'メッセージ無制限などの有料プラン', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => 'いいね・マッチング・新着メッセージ', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'ビデオ通話', 'desc' => '会う前にアプリ内で通話', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'screens' => array(
				$s( 'home', 'さがす', '会員', 'プロフィールを1人ずつ見て、気になる人に「いいね」する', array( $p( 'tabs', '', array( 'おすすめ', '近く', '新着' ) ), $p( 'swipe', '今日のおすすめ', array( 'あおい 28歳 東京都・カフェ巡りが好き', 'みさき 31歳 神奈川県・休日は山登り', 'ゆい 26歳 埼玉県・料理と映画', 'さくら 29歳 千葉県・旅行が趣味' ) ) ), array( 'detail', 'match' ) ),
				$s( 'detail', 'プロフィール', '会員', '相手のプロフィールを詳しく見る', array( $p( 'image', 'あおいさんの写真' ), $p( 'profile', 'あおい 28歳（東京都）', array( '本人確認済み', '会社員', '結婚：2〜3年のうちに' ) ), $p( 'text', 'はじめまして。休日はカフェ巡りをしています。' ), $p( 'button', 'いいね！' ) ), array( 'match' ) ),
				$s( 'match', 'マッチング', '会員', 'おたがいに「いいね」した相手', array( $p( 'cards', 'マッチングした人', array( 'あおい 28歳', 'みさき 31歳' ) ), $p( 'list', 'あなたへのいいね', array( 'ゆい 26歳 から いいね', 'さくら 29歳 から いいね' ) ) ), array( 'messages' ) ),
				$s( 'messages', 'メッセージ', '会員', 'マッチングした相手とやり取りする', array( $p( 'list', 'やり取り中', array( 'あおい：はじめまして！', 'みさき：山登りいいですね' ) ), $p( 'chat', 'あおいさん', array( 'マッチングありがとうございます！', 'こちらこそ。カフェ巡りがお好きなんですね' ) ) ), array() ),
				$s( 'verify', '本人確認', '会員', '公的書類で本人確認と年齢確認をする', array( $p( 'text', '安心して使っていただくため、18歳以上であることを確認しています。' ), $p( 'form', '本人確認', array( '書類の種類', '書類の写真', '顔の写真' ) ), $p( 'button', '提出する' ) ), array( 'mypage' ) ),
				$s( 'mypage', 'マイページ', '会員', 'プロフィールと会員の状態', array( $p( 'profile', 'あなた', array( '本人確認済み', '無料会員' ) ), $p( 'stats', '', array( 'もらったいいね 12', 'マッチング 3', '足あと 28' ) ), $p( 'button', '有料会員になる（月額）' ), $p( 'list', '設定', array( 'ブロックした人', '通報の履歴', '退会' ) ) ), array( 'verify' ) ),
			),
			'tabs'    => array( 'home', 'match', 'messages', 'mypage' ),
			'data'    => array( array( 'entity' => '会員', 'fields' => array( 'ニックネーム', '生年月日', '性別', '地域', '写真', '自己紹介', '本人確認の状態', '会員種別' ) ), array( 'entity' => 'いいね', 'fields' => array( '送った人', '受けた人', '日時' ) ), array( 'entity' => 'マッチング', 'fields' => array( '会員A', '会員B', '成立日時' ) ), array( 'entity' => 'メッセージ', 'fields' => array( 'マッチング', '送信者', '本文', '既読' ) ), array( 'entity' => '通報', 'fields' => array( '通報した人', '相手', '理由', '対応状況' ) ) ),
			'admin'   => array( '本人確認・年齢確認の審査', 'プロフィール写真・自己紹介の監視', '通報への対応・利用停止', '有料会員と売上の管理', 'お知らせ・プッシュ通知' ),
			'integrations' => array( '本人確認サービス（eKYC）', 'アプリ内課金（App Store／Google Play）', 'プッシュ通知' ),
			'nonfunctional' => array( 'iOS・Android（Flutterで同時開発）', '18歳未満は利用できない仕組み（年齢確認）', 'インターネット異性紹介事業の届出と表示', '個人情報・写真の保護と、不適切な投稿の監視' ),
			'questions' => array( 'インターネット異性紹介事業の届出の準備（事業所を管轄する公安委員会）', '有料会員の料金と、男女で料金を分けるか', '本人確認をどのサービスで行うか', '公開前に集めたい会員数と地域' ),
		),
		'job'      => array(
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNSで登録', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'プロフィール・履歴書', 'desc' => '経歴・資格・希望条件', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '求人の掲載', 'desc' => '時給・勤務地・条件を掲載', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '求人の検索', 'desc' => '職種・駅・地図で検索', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '応募', 'desc' => 'ワンタップで応募', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '面接の日程調整', 'desc' => '候補日を選んで確定', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'メッセージ', 'desc' => '採用担当とのやり取り', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => '新着・メッセージ・状況の通知', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'スカウト', 'desc' => '会社から求職者へ声かけ', 'phase' => '将来', 'size' => 'M', 'base' => false ),
			),
			'data'    => array( array( 'entity' => '求職者', 'fields' => array( '名前', '連絡先', '経歴', '資格', '希望条件' ) ), array( 'entity' => '会社', 'fields' => array( '会社名', '所在地', '業種', '担当者' ) ), array( 'entity' => '求人', 'fields' => array( '職種', '給与', '勤務地', '勤務時間', '仕事内容', '公開状態' ) ), array( 'entity' => '応募', 'fields' => array( '求人', '求職者', '状態', '面接日時' ) ) ),
			'word'    => '求人',
			'goals'   => array( '地域で働きたい人が、条件に合う仕事をすぐ見つけられる', 'お店・会社が、採用にかかる手間と費用を減らせる' ),
			'kpi'     => array( '掲載求人数', '応募数', '採用数', '応募から採用までの日数' ),
			'name'    => 'シゴトマッチ',
			'summary' => '地域のお店・会社と、働きたい人をつなぐ求人マッチングアプリ',
			'roles'   => array( array( 'name' => '求職者', 'desc' => '求人を探して応募する' ), array( 'name' => '採用担当', 'desc' => '求人を掲載し、応募者とやり取りする' ), array( 'name' => '運営', 'desc' => '求人の審査・会社の確認' ) ),
			'screens' => array(
				$s( 'home', 'ホーム', '求職者', '近くの新着求人とおすすめを見せる', array( $p( 'search', '職種・駅・キーワード' ), $p( 'tabs', '', array( 'アルバイト', '正社員', '週1からOK' ) ), $p( 'list', '新着の求人', array( 'カフェスタッフ 時給1,150円〜（駅近）', '倉庫の軽作業 時給1,200円〜', '介護スタッフ 月給24万円〜' ) ) ), array( 'detail', 'search' ) ),
				$s( 'search', 'さがす', '求職者', '条件と地図で求人を探す', array( $p( 'search', '勤務地・職種' ), $p( 'map', '近くの求人' ), $p( 'list', '検索結果', array( 'カフェスタッフ（駅から3分）', '書店スタッフ（駅から5分）' ) ) ), array( 'detail' ) ),
				$s( 'detail', '求人詳細', '求職者', '仕事内容と条件を確認して応募する', array( $p( 'image', 'カフェの店内' ), $p( 'profile', 'カフェ〇〇（駅前店）', array( '時給1,150円〜', '週2日・1日4時間〜', '交通費あり' ) ), $p( 'text', 'ドリンク作りと接客をお願いします。未経験の方も歓迎です。' ), $p( 'button', 'この求人に応募する' ) ), array( 'apply' ) ),
				$s( 'apply', '応募', '求職者', '応募して面接の日程を決める', array( $p( 'form', '応募内容', array( 'お名前', '連絡先', '希望の勤務日数', '自己PR' ) ), $p( 'calendar', '面接の希望日' ), $p( 'button', '応募する' ) ), array( 'messages' ) ),
				$s( 'messages', 'メッセージ', '共通', '採用担当とやり取りする', array( $p( 'list', 'やり取り中', array( 'カフェ〇〇：面接の日程について', '書店△△：応募ありがとうございます' ) ), $p( 'chat', 'カフェ〇〇', array( 'ご応募ありがとうございます。面接は火曜14時でいかがですか？', 'よろしくお願いします' ) ) ), array() ),
				$s( 'mypage', 'マイページ', '求職者', '応募の状況とプロフィール', array( $p( 'profile', 'あなた', array( '応募中 2件', '面接予定 1件' ) ), $p( 'list', '応募の状況', array( 'カフェ〇〇：面接 10/28 14:00', '書店△△：書類確認中' ) ) ), array() ),
			),
			'tabs'    => array( 'home', 'search', 'messages', 'mypage' ),
			'questions' => array( '職業紹介にあたるか（有料職業紹介事業の許可が必要か）の確認', '掲載料・成功報酬など、会社からの料金の形', '応募者の個人情報の取り扱い' ),
		),
		'local'    => array(
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNSで登録', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '提供者の登録・本人確認', 'desc' => '身分証・保険の確認と審査', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => 'サービスの登録', 'desc' => '内容・料金・対応エリア', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '地図で探す', 'desc' => '近くの提供者を地図と一覧で', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '予約', 'desc' => '日時を選んで予約', 'phase' => 'MVP', 'size' => 'M', 'base' => true ),
				array( 'name' => '事前決済', 'desc' => '予約時に支払い、完了後に提供者へ', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => 'メッセージ', 'desc' => '提供者とのやり取り', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '口コミ・評価', 'desc' => '利用後に評価', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => '新着・メッセージ・状況の通知', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
			),
			'data'    => array( array( 'entity' => '会員', 'fields' => array( '名前', '住所', '連絡先', '種別（利用者/提供者）', '本人確認の状態' ) ), array( 'entity' => 'サービス', 'fields' => array( '内容', '料金', '対応エリア', '空き日時' ) ), array( 'entity' => '予約', 'fields' => array( '利用者', '提供者', '日時', '金額', '状態' ) ), array( 'entity' => '口コミ', 'fields' => array( '予約', '点数', 'コメント' ) ) ),
			'word'    => 'サービス',
			'goals'   => array( 'ちょっとした手伝いを、近所で安心して頼める', '提供者が空き時間で収入を得られる' ),
			'kpi'     => array( '予約件数', '提供者の登録数', 'リピート率', '口コミの評価' ),
			'name'    => 'ご近所ヘルプ',
			'summary' => '家事代行やペットシッターを、近くの人にお願いできる地域マッチングアプリ',
			'roles'   => array( array( 'name' => '利用者', 'desc' => '近くの提供者を探して予約する' ), array( 'name' => '提供者', 'desc' => 'サービスを登録して依頼を受ける' ), array( 'name' => '運営', 'desc' => '提供者の審査・トラブル対応' ) ),
			'screens' => array(
				$s( 'home', 'ホーム', '利用者', '頼めるサービスと近くの人を見せる', array( $p( 'hero', 'ちょっと手伝ってほしい、を近所で' ), $p( 'tabs', '', array( '家事', 'ペット', '送迎', '子ども' ) ), $p( 'cards', '人気のサービス', array( 'お部屋の掃除 ¥3,000/時', '犬の散歩 ¥1,500/回', '買い物代行 ¥1,000〜' ) ) ), array( 'search', 'detail' ) ),
				$s( 'search', 'さがす', '利用者', '地図で近くの提供者を探す', array( $p( 'map', '近くの提供者' ), $p( 'list', '近い順', array( '田中さん ★4.9 家事（0.5km）', '山本さん ★4.8 ペット（0.8km）' ) ) ), array( 'detail' ) ),
				$s( 'detail', '提供者', '利用者', 'プロフィールと口コミを見て予約する', array( $p( 'profile', '田中さん ★4.9（86件）', array( '本人確認済み', '保険加入', '¥3,000/時' ) ), $p( 'list', '口コミ', array( 'とても丁寧でした（30代女性）', 'また頼みたいです（40代男性）' ) ), $p( 'button', '日時を選んで予約する' ) ), array( 'book' ) ),
				$s( 'book', '予約', '利用者', '日時を選んで支払う', array( $p( 'calendar', '希望の日時' ), $p( 'form', '依頼の内容', array( '住所', 'お願いしたいこと' ) ), $p( 'stats', '', array( '2時間 ¥6,000', '手数料 ¥600', '合計 ¥6,600' ) ), $p( 'button', '支払って予約する' ) ), array( 'mypage' ) ),
				$s( 'messages', 'メッセージ', '共通', '提供者とやり取りする', array( $p( 'chat', '田中さん', array( '当日はよろしくお願いします', '駐車場はありますか？' ) ) ), array() ),
				$s( 'mypage', 'マイページ', '共通', '予約と利用の履歴', array( $p( 'list', '予約', array( '10/28 10:00 お部屋の掃除', '11/2 17:00 犬の散歩' ) ), $p( 'button', '提供者として登録する' ) ), array() ),
			),
			'tabs'    => array( 'home', 'search', 'messages', 'mypage' ),
			'questions' => array( '提供者の本人確認と、事故・トラブル時の保険', 'キャンセル料と返金のルール', '最初に始める地域' ),
		),
		'flea'     => array(
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNSで登録', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '出品', 'desc' => '写真・商品名・状態・価格', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '商品の検索', 'desc' => 'カテゴリー・価格・状態で検索', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '購入・決済', 'desc' => '運営が代金を預かり、受け取り後に出品者へ', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '取引の連絡', 'desc' => '発送・受け取りの連絡', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '評価', 'desc' => '取引後に双方を評価', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => 'コメント', 'desc' => '商品への質問', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => '新着・メッセージ・状況の通知', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '配送サービス連携', 'desc' => '匿名配送・送り状の発行', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'data'    => array( array( 'entity' => '会員', 'fields' => array( 'ニックネーム', '住所', '振込先', '評価' ) ), array( 'entity' => '商品', 'fields' => array( '商品名', '写真', 'カテゴリー', '状態', '価格', '送料', '公開状態' ) ), array( 'entity' => '取引', 'fields' => array( '商品', '購入者', '出品者', '金額', '手数料', '状態' ) ), array( 'entity' => '評価', 'fields' => array( '取引', '点数', 'コメント' ) ) ),
			'word'    => '商品',
			'goals'   => array( '使わなくなったモノを、手軽に売り買いできる', '取引の安全を運営が守る' ),
			'kpi'     => array( '出品数', '購入件数', '取引の完了率', 'リピート率' ),
			'name'    => 'ユズリバ',
			'summary' => '使わなくなったモノを、写真で出品して売り買いできるフリマアプリ',
			'roles'   => array( array( 'name' => '購入者', 'desc' => '商品を探して購入する' ), array( 'name' => '出品者', 'desc' => '写真で出品して発送する' ), array( 'name' => '運営', 'desc' => '出品の監視・取引のトラブル対応' ) ),
			'screens' => array(
				$s( 'home', 'ホーム', '購入者', 'おすすめの商品を並べる', array( $p( 'search', 'なにをお探しですか？' ), $p( 'tabs', '', array( 'おすすめ', 'ファッション', '家電', 'ベビー' ) ), $p( 'cards', 'おすすめの商品', array( 'ベビーカー ¥6,800', 'ダウンジャケット ¥4,500', 'コーヒーメーカー ¥2,900', '絵本10冊セット ¥1,200' ) ) ), array( 'detail', 'search' ) ),
				$s( 'search', 'さがす', '購入者', '条件で商品を探す', array( $p( 'search', 'キーワード' ), $p( 'tabs', '', array( '新着', '安い順', '送料込み' ) ), $p( 'cards', '検索結果', array( 'ベビーカー ¥6,800', 'チャイルドシート ¥5,000' ) ) ), array( 'detail' ) ),
				$s( 'detail', '商品', '購入者', '状態と価格を見て購入する', array( $p( 'image', 'ベビーカー（写真4枚）' ), $p( 'profile', 'ベビーカー ¥6,800（送料込み）', array( '目立った傷なし', '発送まで2〜3日', '出品者 ★4.9' ) ), $p( 'button', '購入手続きへ' ), $p( 'button', 'コメントで質問する' ) ), array( 'buy', 'messages' ) ),
				$s( 'buy', '購入', '購入者', '支払いと配送先を決める', array( $p( 'form', '購入手続き', array( '配送先', '支払い方法' ) ), $p( 'stats', '', array( '商品 ¥6,800', '送料 込み', '合計 ¥6,800' ) ), $p( 'button', '購入する' ) ), array( 'mypage' ) ),
				$s( 'sell', '出品', '出品者', '写真を撮って出品する', array( $p( 'form', '出品', array( '写真', '商品名', 'カテゴリー', '状態', '価格', '発送の方法' ) ), $p( 'button', '出品する' ) ), array( 'mypage' ) ),
				$s( 'messages', '取引', '共通', '取引の連絡', array( $p( 'list', '取引中', array( 'ベビーカー：発送しました', '絵本セット：受け取り評価待ち' ) ), $p( 'chat', '出品者', array( '本日発送しました', 'ありがとうございます！' ) ) ), array() ),
				$s( 'mypage', 'マイページ', '共通', '売上と取引の状況', array( $p( 'stats', '', array( '売上 ¥18,400', '出品中 6件', '評価 ★4.9' ) ), $p( 'button', '出品する' ) ), array( 'sell' ) ),
			),
			'tabs'    => array( 'home', 'search', 'sell', 'mypage' ),
			'questions' => array( '古物営業の許可が必要な形か（運営が買い取るかどうか）の確認', '出品禁止物のルールと監視の方法', '販売手数料と売上金の振込' ),
		),
		'hobby'    => array(
			'features' => array(
				array( 'name' => '会員登録・ログイン', 'desc' => 'メール・SNSで登録', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => 'プロフィール', 'desc' => '趣味・レベル・活動エリア', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => '募集の作成', 'desc' => '日時・場所・人数・参加費', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '募集の検索', 'desc' => 'ジャンル・日付・地図で検索', 'phase' => 'MVP', 'size' => 'M', 'base' => false ),
				array( 'name' => '参加', 'desc' => 'ワンタップで参加', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => 'グループチャット', 'desc' => '参加者どうしの連絡', 'phase' => 'MVP', 'size' => 'L', 'base' => false ),
				array( 'name' => '通報・ブロック', 'desc' => '不適切な利用への対応', 'phase' => 'MVP', 'size' => 'S', 'base' => false ),
				array( 'name' => 'プッシュ通知', 'desc' => '新着・メッセージ・状況の通知', 'phase' => 'MVP', 'size' => 'S', 'base' => true ),
				array( 'name' => '参加費の集金', 'desc' => 'アプリで参加費を支払う', 'phase' => '将来', 'size' => 'L', 'base' => false ),
			),
			'data'    => array( array( 'entity' => '会員', 'fields' => array( 'ニックネーム', '趣味', 'レベル', '活動エリア' ) ), array( 'entity' => '募集', 'fields' => array( 'タイトル', 'ジャンル', '日時', '場所', '定員', '参加費', '主催者' ) ), array( 'entity' => '参加', 'fields' => array( '募集', '会員', '状態' ) ), array( 'entity' => 'メッセージ', 'fields' => array( 'グループ', '送信者', '本文' ) ) ),
			'word'    => '募集',
			'goals'   => array( '同じ趣味の仲間に気軽に出会える', '募集から参加までをアプリで完結できる' ),
			'kpi'     => array( '募集数', '参加者数', '継続して参加する人の割合' ),
			'name'    => 'ナカマップ',
			'summary' => 'テニスやランニングなど、趣味の仲間とイベントを見つけるアプリ',
			'roles'   => array( array( 'name' => '会員', 'desc' => '募集を探して参加する・募集を出す' ), array( 'name' => '運営', 'desc' => '募集の監視・通報への対応' ) ),
			'screens' => array(
				$s( 'home', 'ホーム', '会員', '近くの募集と人気のサークル', array( $p( 'hero', '週末、いっしょに走ろう' ), $p( 'tabs', '', array( 'テニス', 'ランニング', 'ボードゲーム', '写真' ) ), $p( 'list', '今週の募集', array( '土 9:00 河川敷ラン 5km（あと3人）', '日 13:00 初心者テニス（あと2人）', '金 19:00 ボードゲーム会（あと4人）' ) ) ), array( 'detail', 'search' ) ),
				$s( 'search', 'さがす', '会員', '地図と日付で募集を探す', array( $p( 'map', '近くの募集' ), $p( 'calendar', '日付' ), $p( 'list', '検索結果', array( '初心者テニス（1.2km）', '河川敷ラン（2.0km）' ) ) ), array( 'detail' ) ),
				$s( 'detail', '募集', '会員', '内容を見て参加する', array( $p( 'image', '初心者テニス' ), $p( 'profile', '主催：けんた（★4.8）', array( '10/27（日）13:00〜15:00', '市民コート', '参加費 ¥500' ) ), $p( 'button', '参加する' ) ), array( 'group' ) ),
				$s( 'group', 'グループ', '会員', '参加者どうしで連絡する', array( $p( 'chat', '初心者テニスの会', array( '当日はラケットの貸し出しもあります', 'はじめてですがよろしくお願いします！' ) ) ), array() ),
				$s( 'post', '募集する', '会員', '仲間を募集する', array( $p( 'form', '募集の内容', array( 'タイトル', 'ジャンル', '日時', '場所', '人数', '参加費' ) ), $p( 'button', '募集を出す' ) ), array( 'mypage' ) ),
				$s( 'mypage', 'マイページ', '会員', '参加予定と仲間', array( $p( 'profile', 'あなた', array( 'テニス・ランニング', '参加 8回' ) ), $p( 'list', '参加予定', array( '10/27 初心者テニス', '11/2 河川敷ラン' ) ), $p( 'button', '募集する' ) ), array( 'post' ) ),
			),
			'tabs'    => array( 'home', 'search', 'group', 'mypage' ),
			'questions' => array( '恋愛目的の利用をどう扱うか（異性紹介にあたらない運営の確認）', '当日のケガ・トラブルの責任の範囲', '参加費をアプリで集めるか' ),
		),
	);
	if ( empty( $G[ $genre ] ) ) {
		return $base;
	}
	$g = $G[ $genre ];
	// 機能・データ・管理画面は、スキルマッチングの「スキル」をジャンルの言葉に置き換えて使う。
	if ( ! empty( $g['word'] ) ) {
		foreach ( array( 'features', 'data', 'admin' ) as $k ) {
			if ( ! isset( $g[ $k ] ) ) {
				$g[ $k ] = json_decode( str_replace( 'スキル', $g['word'], wp_json_encode( $base[ $k ], JSON_UNESCAPED_UNICODE ) ), true );
			}
		}
		unset( $g['word'] );
	}
	return array_merge( $base, $g );
}

/* -------------------------------------------------------------------------
 * REST：デザインだけ切り替える（試作は作り直さない）
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/studio/orig-design',
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				if ( ! apprex_studio_check_nonce( $request ) ) {
					return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
				}
				$uid  = get_current_user_id();
				$spec = apprex_orig_sanitize( json_decode( (string) get_user_meta( $uid, 'apprex_orig_spec', true ), true ) );
				if ( ! $spec ) {
					return new WP_Error( 'bad_request', '先にアプリの試作を作ってください。', array( 'status' => 400 ) );
				}
				$g   = apprex_orig_genre_key( $request->get_param( 'genre' ) );
				$all = apprex_orig_genres();
				if ( ! $g ) {
					return new WP_Error( 'bad_request', 'デザインを選んでください。', array( 'status' => 400 ) );
				}
				$spec['design'] = apprex_orig_design_clean( array( 'key' => $g ) + $all[ $g ]['design'] );
				update_user_meta( $uid, 'apprex_orig_spec', wp_slash( wp_json_encode( $spec, JSON_UNESCAPED_UNICODE ) ) );
				return rest_ensure_response( array( 'ok' => true, 'spec' => $spec ) );
			},
			'permission_callback' => function () {
				return is_user_logged_in();
			},
		)
	);
} );
