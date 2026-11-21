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
		array( 'cat' => 'コラム', 'date' => '2026.11.20', 'illo' => 'carshico',   'title' => 'カーリースと購入はどっちがお得？違いと選び方', 'excerpt' => '初期費用・月々の支払い・所有権の違いと、向いている人・選び方のチェックポイントを整理します。' ),
		array( 'cat' => 'コラム', 'date' => '2026.11.18', 'illo' => 'export',     'title' => '中古車を高く売るには？査定前の準備チェックリスト', 'excerpt' => '洗車・書類の準備から相見積り・販路の違いまで、高く売るためのコツをまとめました。' ),
		array( 'cat' => 'コラム', 'date' => '2026.11.15', 'illo' => 'ai-digital', 'title' => '中小企業のAI活用の始め方｜小さく始める3ステップ', 'excerpt' => '身近な繰り返し作業から1つだけ試し、効果を見て横展開する、失敗しにくい進め方を紹介します。' ),
		array( 'cat' => 'コラム', 'date' => '2026.11.12', 'illo' => 'gps',        'title' => '社用車のGPS車両管理を導入するメリットと選び方', 'excerpt' => '位置把握・稼働の見える化・安全管理などのメリットと、選ぶときの確認ポイントを解説します。' ),
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

/** サンプル投稿（ニュース／コラムの体裁用。クリックで編集・削除可） */
function ais_hub_sample_posts() {
	return array(
		array(
			'slug'  => 'ais-sample-carshico',
			'cat'   => 'コラム',
			'title' => 'カーリースと購入はどっちがお得？違いと選び方を解説',
			'content' =>
				'<!-- wp:paragraph --><p>「毎月定額で新しいクルマに乗れる」と人気のカーリース。一方で「結局は買ったほうが安いのでは？」と迷う方も多いはずです。この記事では、リースと購入の違い、向いている人、選ぶときのチェックポイントを整理します。</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading --><h2>カーリースと購入の主な違い</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li><strong>初期費用</strong>：購入は頭金や諸費用でまとまった資金が必要。リースは頭金0円で始められるプランが多い。</li><li><strong>月々の支払い</strong>：リースは税金・自賠責・一部メンテ費が月額に含まれることが多く、支出が平準化しやすい。</li><li><strong>所有権</strong>：購入は自分の資産になる。リースは契約満了で返却が基本（買取可のプランもあり）。</li><li><strong>カスタマイズ</strong>：購入は自由。リースは原状回復が前提で制限がある場合がある。</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>カーリースが向いている人</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li>まとまった初期費用をかけたくない</li><li>家計やキャッシュフローを毎月一定にしたい</li><li>数年ごとに新しい車へ乗り換えたい</li><li>車検・税金の支払いタイミングを気にしたくない</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>契約前に確認したい5つのポイント</h2><!-- /wp:heading -->' .
				'<!-- wp:list {"ordered":true} --><ol><li>月額に何が含まれるか（税金・メンテ・車検の有無）</li><li>契約年数と中途解約時の条件</li><li>走行距離の上限と超過時の精算額の目安</li><li>契約満了時の選択肢（返却・買取・乗り換え）</li><li>総支払額（月額×期間＋諸費用）での比較</li></ol><!-- /wp:list -->' .
				'<!-- wp:paragraph --><p>最終的には「総支払額」と「手間・乗り換え頻度」のバランスで選ぶのがおすすめです。料金や条件はプランにより異なるため、複数の見積りで比較しましょう。具体的な条件はお気軽に<a href="/contact/">ご相談ください</a>。</p><!-- /wp:paragraph -->',
		),
		array(
			'slug'  => 'ais-sample-export',
			'cat'   => 'コラム',
			'title' => '中古車を高く売るには？査定前にできる準備チェックリスト',
			'content' =>
				'<!-- wp:paragraph --><p>同じ車でも、売り方と準備しだいで査定額は変わります。この記事では、査定前にできる準備と、高く売るためのコツをチェックリスト形式でまとめました。</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading --><h2>査定前にやっておきたい準備</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li>洗車・車内清掃（第一印象で評価が変わりやすい）</li><li>車検証・取扱説明書・スペアキー・整備記録簿をそろえる</li><li>社外パーツがある場合は純正パーツも用意</li><li>ニオイ対策（喫煙・ペット）を可能な範囲で</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>高く売るための考え方</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li><strong>相見積りを取る</strong>：1社だけで決めず、複数社の提示を比較する。</li><li><strong>売り時を逃さない</strong>：需要が高まる時期や、モデルチェンジ前は有利になりやすい。</li><li><strong>販路の違いを知る</strong>：店舗買取・オークション・海外輸出など、販路によって評価が変わる車種もある。</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>よくある質問</h2><!-- /wp:heading -->' .
				'<!-- wp:paragraph --><p><strong>Q. 走行距離が多いと売れない？</strong><br>いいえ。年式・車種・需要によって評価は異なり、過走行車や不動車でも販路によっては値が付くことがあります。</p><!-- /wp:paragraph -->' .
				'<!-- wp:paragraph --><p><strong>Q. 傷は直してから売るべき？</strong><br>修復費用が査定アップ額を上回ることも多く、無理に直さないほうが得な場合もあります。まずはそのまま査定に出すのが無難です。</p><!-- /wp:paragraph -->' .
				'<!-- wp:paragraph --><p>金額は車の状態や時期で変動します。愛車の目安を知りたい方は<a href="/contact/">無料査定をご相談ください</a>。</p><!-- /wp:paragraph -->',
		),
		array(
			'slug'  => 'ais-sample-ai',
			'cat'   => 'コラム',
			'title' => '中小企業のAI活用の始め方｜小さく始める3ステップ',
			'content' =>
				'<!-- wp:paragraph --><p>「AIを使ってみたいが、何から手を付ければいいか分からない」。そんな中小企業の方に向けて、失敗しにくい“小さく始める”進め方を紹介します。</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading --><h2>ステップ1：身近な“繰り返し作業”を洗い出す</h2><!-- /wp:heading -->' .
				'<!-- wp:paragraph --><p>いきなり大きな仕組みを作る必要はありません。まずは、毎日・毎週発生している定型業務（メール返信の下書き、議事録の要約、問い合わせの一次対応など）をリストアップします。</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading --><h2>ステップ2：1つだけ試す</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li>効果が見えやすく、失敗してもリスクが低い業務を1つ選ぶ</li><li>既存のAIツールやチャットAIで、まず手作業を置き換えてみる</li><li>「どれくらい時間が減ったか」を記録する</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>ステップ3：効果を見て横展開する</h2><!-- /wp:heading -->' .
				'<!-- wp:paragraph --><p>1つの業務で効果が出たら、似た業務へ広げます。この段階で、社内ルール（個人情報を入力しない等）や、専用ツール・アプリ化の検討に進むと無理がありません。</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading --><h2>始めるときの注意点</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li>機密情報・個人情報の取り扱いルールを先に決める</li><li>AIの出力は人が必ず確認してから使う</li><li>「ツール導入」が目的化しないよう、削減できた時間で効果を測る</li></ul><!-- /wp:list -->' .
				'<!-- wp:paragraph --><p>自社に合った業務の切り出しや、アプリ・システム化のご相談は<a href="/contact/">こちら</a>から承ります。</p><!-- /wp:paragraph -->',
		),
		array(
			'slug'  => 'ais-sample-gps',
			'cat'   => 'コラム',
			'title' => '社用車のGPS車両管理を導入するメリットと選び方',
			'content' =>
				'<!-- wp:paragraph --><p>社用車や営業車が増えてくると、「どこを走っているか」「稼働は適正か」の把握が難しくなります。GPS車両管理システムは、こうした課題を見える化する仕組みです。基本のメリットと選び方を解説します。</p><!-- /wp:paragraph -->' .
				'<!-- wp:heading --><h2>導入するとできること</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li>車両の位置・走行履歴の確認</li><li>稼働状況の把握による配車・ルートの最適化</li><li>急加速・急ブレーキなどの運転傾向の見える化（安全運転指導に活用）</li><li>盗難時の位置把握に役立つケースもある</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>主なメリット</h2><!-- /wp:heading -->' .
				'<!-- wp:list --><ul><li><strong>業務効率</strong>：移動の無駄を減らし、訪問件数の改善につなげやすい</li><li><strong>安全管理</strong>：運転データをもとに事故リスクの低減を図れる</li><li><strong>管理の手間削減</strong>：日報や稼働集計の一部を自動化できる</li></ul><!-- /wp:list -->' .
				'<!-- wp:heading --><h2>選ぶときのチェックポイント</h2><!-- /wp:heading -->' .
				'<!-- wp:list {"ordered":true} --><ol><li>必要な機能（位置のみ／運転診断／日報連携など）を決める</li><li>初期費用と月額、台数ごとの料金体系を確認</li><li>取り付け方式（シガーソケット／配線式）と工事の要否</li><li>管理画面の使いやすさ、スマホ対応</li><li>サポート体制とデータの保存期間</li></ol><!-- /wp:list -->' .
				'<!-- wp:paragraph --><p>最適な機能や台数規模は事業内容によって異なります。導入可否や構成のご相談は<a href="/contact/">お問い合わせ</a>ください。</p><!-- /wp:paragraph -->',
		),
	);
}

/** sitemap（インストーラが生成する固定ページ） */
function ais_hub_sitemap() {
	return array(
		array( 'key' => 'home',     'path' => '',               'title' => 'ホーム',                 'tpl' => '' ),
		array( 'key' => 'about',    'path' => 'company',        'title' => '会社情報',               'tpl' => '', 'content' => '<!-- wp:ais/corporate /-->' ),
		array( 'key' => 'mission',  'path' => 'company/vision', 'title' => '企業理念',               'tpl' => '', 'content' => '<!-- wp:heading --><h2>企業理念</h2><!-- /wp:heading --><!-- wp:paragraph --><p>クルマを通じて、人の暮らしと挑戦を支える。モビリティの提供にとどまらず、AIとデジタルの力で地域・日本・そして世界をつなぎ、人とビジネスの可能性を広げていきます。</p><!-- /wp:paragraph -->' ),
		array( 'key' => 'message',  'path' => 'company/message','title' => '代表メッセージ',         'tpl' => '', 'content' => '<!-- wp:paragraph --><p>合同会社アイズは、自動車ビジネスを軸に、AI・デジタル・グローバル・プラットフォーム事業へと歩みを広げてまいりました。クルマは、人の暮らしと挑戦を支える大切なパートナーです。私たちはその価値を、テクノロジーと誠実なサービスでさらに高め、地域から日本、そして世界へと貢献してまいります。これからもAISは、お客様と共に新しい価値を創り続けます。</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>合同会社アイズ　代表社員　吉田 一平</p><!-- /wp:paragraph -->' ),
		array( 'key' => 'business', 'path' => 'business',       'title' => '事業一覧',               'tpl' => '', 'content' => '<!-- wp:ais/business-list /-->' ),
		array( 'key' => 'partner',  'path' => 'partner',        'title' => '販売店・事業パートナー', 'tpl' => '', 'content' => '<!-- wp:heading --><h2>販売店・事業パートナー募集</h2><!-- /wp:heading --><!-- wp:ais/partner /-->' ),
		array( 'key' => 'news',     'path' => 'news',           'title' => 'ニュース・お知らせ',     'tpl' => '' ),
		array( 'key' => 'column',   'path' => 'column',         'title' => '業界コラム',             'tpl' => '', 'content' => '<!-- wp:heading --><h2>業界コラム</h2><!-- /wp:heading --><!-- wp:paragraph --><p>自動車・GPS・リース・AI／アプリ開発など、業界の動向や役立つ情報をお届けします。</p><!-- /wp:paragraph -->' ),
		array( 'key' => 'contact',  'path' => 'contact',        'title' => 'お問い合わせ',           'tpl' => '', 'content' => '[ais_contact]' ),
		array( 'key' => 'privacy',  'path' => 'privacy',        'title' => 'プライバシーポリシー',   'tpl' => '', 'content' => '<!-- wp:paragraph --><p>合同会社アイズ（以下「当社」）は、お客様の個人情報の保護を重要な責務と考え、以下の方針に基づき適切に取り扱います。</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>1. 個人情報の取得</h3><!-- /wp:heading --><!-- wp:paragraph --><p>当社は、適法かつ公正な手段により、お問い合わせ・各種お申し込み等を通じて個人情報を取得します。</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>2. 利用目的</h3><!-- /wp:heading --><!-- wp:paragraph --><p>取得した個人情報は、お問い合わせ・ご相談への対応、サービスのご提供・ご案内、品質向上、法令に基づく対応のために利用します。</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>3. 第三者提供</h3><!-- /wp:heading --><!-- wp:paragraph --><p>法令に定める場合を除き、ご本人の同意なく個人情報を第三者に提供しません。</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>4. 安全管理</h3><!-- /wp:heading --><!-- wp:paragraph --><p>個人情報の漏えい・滅失・毀損の防止その他安全管理のため、必要かつ適切な措置を講じます。</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>5. 開示・訂正・削除</h3><!-- /wp:heading --><!-- wp:paragraph --><p>ご本人から開示・訂正・利用停止・削除のお求めがあった場合、ご本人であることを確認のうえ、法令に従い速やかに対応します。</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>6. お問い合わせ窓口</h3><!-- /wp:heading --><!-- wp:paragraph --><p>合同会社アイズ　個人情報お問い合わせ窓口<br>〒979-0204 福島県いわき市四倉町細谷字大町1番<br>メール：info@aisjaltd.com</p><!-- /wp:paragraph --><!-- wp:heading {"level":3} --><h3>7. 改定</h3><!-- /wp:heading --><!-- wp:paragraph --><p>本方針は、法令の改正や事業内容の変更に応じて改定することがあります。</p><!-- /wp:paragraph -->' ),
	);
}
