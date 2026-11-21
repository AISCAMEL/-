<?php
/** AIS 既定データ（事業・実績・理念・サンプルニュース） */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 事業の既定定義。ホームの事業カード・各事業ページの初期値。
 * layout: grid=通常カード / wide=横長カード / hidden=ホーム非表示（ページのみ）
 */
function ais_hub_default_businesses() {
	return array(
		array( 'slug' => 'carmel', 'en' => 'CARMEL', 'ja' => 'カーメル', 'cat' => '中古車販売・信用回復ローン', 'desc' => '低与信でもクルマに乗れる支援ローン', 'cta' => '購入・ローン相談', 'status' => '提供中', 'layout' => 'grid', 'order' => 10 ),
		array( 'slug' => 'carshico', 'en' => 'CARSHICO', 'ja' => 'カシコ', 'cat' => '新車・中古車カーリース', 'desc' => 'ライフスタイルに合わせた乗り方を', 'cta' => 'リース相談', 'status' => '提供中', 'layout' => 'grid', 'order' => 20 ),
		array( 'slug' => 'buymo', 'en' => 'BUYMO', 'ja' => 'バイモ', 'cat' => '中古車買取プラットフォーム', 'desc' => '査定・買取・売却をもっとスマートに', 'cta' => '査定申込', 'status' => '提供中', 'layout' => 'grid', 'order' => 30 ),
		array( 'slug' => 'gps', 'en' => 'GPS', 'ja' => '', 'cat' => '車両管理システム', 'desc' => '位置情報×車両管理で運用の安全を強化', 'cta' => '導入相談', 'status' => '提供中', 'layout' => 'grid', 'order' => 40 ),
		array( 'slug' => 'triple-gear', 'en' => 'トリプル・ギア・システム', 'ja' => 'Triple Gear System', 'cat' => '中古車販売店向けプラットフォーム', 'desc' => '在庫・審査・リース・GPSをワンパッケージ', 'cta' => '加盟相談', 'status' => '提供中', 'layout' => 'grid', 'order' => 50 ),
		array( 'slug' => 'export', 'en' => 'AIS JAPAN AUTO EXPORT', 'ja' => '', 'cat' => '中古車の海外輸出', 'desc' => '世界の需要へ、安心・確実に届ける', 'cta' => '車両リクエスト', 'status' => '提供中', 'layout' => 'grid', 'order' => 60 ),
		array( 'slug' => 'design', 'en' => 'DESIGN', 'ja' => 'デザイン', 'cat' => 'デザイン・クリエイティブ', 'desc' => 'ブランド・Web・販促物のデザイン制作', 'cta' => '制作相談', 'status' => '提供中', 'layout' => 'grid', 'order' => 65 ),
		array( 'slug' => 'ai-digital', 'en' => 'AI・デジタル事業', 'ja' => 'AI／Web／システム開発／SNS運用', 'cat' => 'AI導入支援・Web制作・システム開発', 'desc' => 'SNS運用・業務自動化でビジネスを加速', 'cta' => '開発相談', 'status' => '提供中', 'layout' => 'wide', 'order' => 70 ),
		array( 'slug' => 'platforms', 'en' => '各種プラットフォーム', 'ja' => 'APPREX・メルマッチ・RE:ME・NEXORA', 'cat' => 'AI分身・マッチング・業務支援・新規プロジェクト', 'desc' => '暮らし・ビジネス・ライフレガシーを支える多様なサービス', 'cta' => 'お問い合わせ', 'status' => '一部準備中', 'layout' => 'wide', 'order' => 80 ),
		array( 'slug' => 'app-development', 'en' => 'アプリ開発', 'ja' => 'iOS／Android／Webアプリ', 'cat' => '業務システム・AI搭載アプリ開発', 'desc' => '企画から開発・運用まで一気通貫で支援', 'cta' => '見積相談', 'status' => '提供中', 'layout' => 'hidden', 'order' => 90 ),
	);
}

/** 実績ストリップの既定（※サンプル。管理画面で編集／承認後に実数） */
function ais_hub_default_stats() {
	return array(
		array( 'icon' => 'i-car',   'label' => '累計取扱台数',       'value' => '50,000台+' ),
		array( 'icon' => 'i-hand',  'label' => '提携パートナー',     'value' => '200社+' ),
		array( 'icon' => 'i-globe', 'label' => '海外輸出国',         'value' => '30か国+' ),
		array( 'icon' => 'i-users', 'label' => '与信申込実績',       'value' => '10,000件+/年' ),
		array( 'icon' => 'i-shield','label' => '審査通過率',         'value' => '95%' ),
		array( 'icon' => 'i-chart', 'label' => '販売店ネットワーク', 'value' => '全国対応' ),
	);
}

/** 理念バリューの既定 */
function ais_hub_default_values() {
	return array(
		array( 'icon' => 'i-leaf',  'title' => '地域貢献',          'desc' => '地域のモビリティと雇用を支援' ),
		array( 'icon' => 'i-globe', 'title' => 'グローバル展開',    'desc' => '日本の中古車を世界へ' ),
		array( 'icon' => 'i-ai',    'title' => 'AIで社会を効率化',  'desc' => 'ビジネスのDXを推進' ),
		array( 'icon' => 'i-users', 'title' => '持続可能な社会',    'desc' => 'モビリティの価値と未来づくり' ),
	);
}

/** サンプルニュース（※サンプル。承認後に実ニュースへ差し替え） */
function ais_hub_sample_news() {
	return array(
		array( 'cat' => '事業情報',   'date' => '2026.11.20', 'illo' => 'carshico',   'title' => 'CARSHICO（カシコ）の新プランを開始', 'excerpt' => '新車・中古車リースの選択肢がさらに広がりました。法人・個人どちらも対応。' ),
		array( 'cat' => '業界ニュース', 'date' => '2026.11.18', 'illo' => 'export',     'title' => '中古車輸出の最新動向', 'excerpt' => 'アジア・アフリカでの需要が拡大。日本の中古車が海外で高い評価を受けています。' ),
		array( 'cat' => 'コラム',     'date' => '2026.11.15', 'illo' => 'ai-digital', 'title' => 'AIが変える自動車販売の未来', 'excerpt' => 'AI活用による業務効率化と、顧客体験の向上について解説します。' ),
		array( 'cat' => 'お知らせ',   'date' => '2026.11.12', 'illo' => 'gps',        'title' => 'GPS車両管理システムがアップデート', 'excerpt' => '位置情報の精度向上と、新機能の追加を行いました。' ),
	);
}

/** 事業ページの詳細コンテンツ（slug => [リード文, 提供内容の箇条書き...]） */
function ais_hub_business_detail( $slug ) {
	$d = array(
		'carmel'          => array( 'カーメルは中古車販売を中心に、信用回復ローンや注文販売まで対応。クルマが必要な方お一人おひとりに、最適なプランをご提案します。', array( '中古車・新車の販売', '信用回復ローン（低与信の方もご相談可）', 'ご希望条件に合わせた注文販売', '購入後のサポート' ) ),
		'carshico'        => array( 'CARSHICO（カシコ）は、新車・中古車・法人向けのカーリースをご提供。頭金0円・月々定額で、ライフスタイルに合わせた乗り方が選べます。', array( '新車・中古車カーリース', '個人／法人どちらも対応', '頭金0円・月々定額プラン', 'メンテナンス込みプランのご相談' ) ),
		'buymo'           => array( 'BUYMO（バイモ）は中古車買取・査定・個人売買支援のプラットフォーム。査定から売却までをスマートに、納得の価格でお手伝いします。', array( '無料査定・出張査定', '中古車の高価買取', '個人売買のサポート', '手続き・名義変更の代行' ) ),
		'gps'             => array( '車両の位置情報・車両管理・導入相談に対応するGPSソリューション。リース・販売・運用の安全管理を強化します。', array( '車両位置情報の可視化', '複数台のフリート管理', '導入・運用のご相談', '※遠隔制御等の機能は法令・契約確認後にご案内' ) ),
		'triple-gear'     => array( 'トリプル・ギア・システムは、中古車販売店向けの事業支援プラットフォーム。在庫・審査・リース・GPSをワンパッケージでご提供します。', array( '在庫管理・販売支援', '審査・信販の連携', 'リース・GPSの一体提供', '加盟店向けサポート' ) ),
		'export'          => array( 'AIS JAPAN AUTO EXPORTは中古車の海外輸出事業。世界の需要へ、日本の中古車を安心・確実にお届けします。', array( '中古車の海外輸出', '車両リクエストの受付', '輸出手続きのサポート', '各国の需要に応じたご提案' ) ),
		'ai-digital'      => array( 'AI・デジタル事業では、AI業務支援・Web制作・SNS運用をワンストップで。デジタルの力でビジネスを加速します。', array( 'AI導入・業務自動化支援', 'ホームページ・LP制作', 'SNS運用・マーケティング', 'システム開発のご相談' ) ),
		'app-development'  => array( 'アプリ開発事業では、iOS/Android/Webアプリ、業務システム、AI搭載アプリを企画から開発・運用まで支援します。', array( 'iOS／Android／Webアプリ開発', '業務システム・基幹連携', 'AI搭載アプリ', '企画・見積のご相談' ) ),
		'platforms'       => array( 'APPREX・メルマッチ・RE:ME・NEXORA 等、暮らしとビジネスを支える多様なプラットフォームを展開しています。', array( 'APPREX（ノーコードアプリ）', 'メルマッチ（マッチング）', 'RE:ME（AI分身）', 'NEXORA 他（提供中／準備中は個別にご案内）' ) ),
		'design'          => array( 'ブランド・Web・販促物まで、伝わるデザインをワンストップで制作。事業の魅力を正しく、美しく届けます。', array( 'ロゴ・ブランディング', 'Web／LP デザイン', 'チラシ・名刺・販促物', '写真・動画・SNS素材' ) ),
	);
	return isset( $d[ $slug ] ) ? $d[ $slug ] : array( '', array() );
}

/** sitemap（インストーラが生成する固定ページ） */
function ais_hub_sitemap() {
	return array(
		array( 'key' => 'home',     'path' => '',               'title' => 'ホーム',                 'tpl' => '' ),
		array( 'key' => 'about',    'path' => 'company',        'title' => '会社情報',               'tpl' => '', 'content' => '<!-- wp:ais/corporate /-->' ),
		array( 'key' => 'mission',  'path' => 'company/vision', 'title' => '企業理念',               'tpl' => '', 'content' => '<!-- wp:heading --><h2>企業理念</h2><!-- /wp:heading --><!-- wp:paragraph --><p>クルマを通じて、人の暮らしと挑戦を支える。モビリティの提供にとどまらず、AIとデジタルの力で地域・日本・そして世界をつなぎ、人とビジネスの可能性を広げていきます。</p><!-- /wp:paragraph -->' ),
		array( 'key' => 'message',  'path' => 'company/message','title' => '代表メッセージ',         'tpl' => '', 'content' => '<!-- wp:paragraph --><p>※代表メッセージの本文は管理画面から編集してください（プレースホルダ）。</p><!-- /wp:paragraph -->' ),
		array( 'key' => 'business', 'path' => 'business',       'title' => '事業一覧',               'tpl' => '', 'content' => '<!-- wp:ais/business-list /-->' ),
		array( 'key' => 'partner',  'path' => 'partner',        'title' => '販売店・事業パートナー', 'tpl' => '', 'content' => '<!-- wp:heading --><h2>販売店・事業パートナー募集</h2><!-- /wp:heading --><!-- wp:ais/partner /-->' ),
		array( 'key' => 'news',     'path' => 'news',           'title' => 'ニュース・お知らせ',     'tpl' => '' ),
		array( 'key' => 'column',   'path' => 'column',         'title' => '業界コラム',             'tpl' => '' ),
		array( 'key' => 'contact',  'path' => 'contact',        'title' => 'お問い合わせ',           'tpl' => '', 'content' => '[ais_contact]' ),
		array( 'key' => 'privacy',  'path' => 'privacy',        'title' => 'プライバシーポリシー',   'tpl' => '' ),
		array( 'key' => 'recruit',  'path' => 'recruit',        'title' => '採用情報',               'tpl' => '' ),
	);
}
