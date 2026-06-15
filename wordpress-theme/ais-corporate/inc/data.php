<?php
/**
 * コンテンツデータ層。
 * Next.js 版の src/content/*.ts を PHP 配列へ移植したもの。
 * サイト全体の単一の情報源（source of truth）。確定情報が出たらここを編集する。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** サイト基本情報 */
function ais_site() {
	return array(
		'name'         => '合同会社アイズ',
		'name_en'      => 'AIS LLC',
		'tagline'      => 'Always Innovation Solutions',
		'url'          => 'https://aisjaltd.com',
		'description'  => '合同会社アイズは、自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカーを主軸に、ノーコードアプリ開発「APPREX」、サブスクWeb制作「WEB crews」、FC事業を展開する会社です。クルマのことからデジタルまで、ワンストップでお応えします。',
		'email'        => 'info@aisjaltd.com',
		'address'      => '〒979-0204 福島県いわき市四倉町細谷字大町1番',
		'reply_target' => '原則1〜2営業日以内',
	);
}

/** グローバルナビ（ヘッダー）。children があるとドロップダウン表示。 */
function ais_main_nav() {
	return array(
		array(
			'label' => '事業紹介', 'href' => '/services',
			'children' => array(
				array( 'label' => '自動車販売（カーメル）', 'href' => '/services/carmel', 'description' => '国産車の販売・全国対応' ),
				array( 'label' => '自動車買取（BUYMO）', 'href' => '/services/buymo', 'description' => '車・農機具・アルミ等を全国買取' ),
				array( 'label' => 'オンライン車販売（CARSHICO）', 'href' => '/services/carshico', 'description' => '新車をオンライン注文・自宅納車' ),
				array( 'label' => '車両セキュリティ（天護 TENGO）', 'href' => '/services/tengo', 'description' => 'GPS遠隔停止システム' ),
				array( 'label' => 'レッカー事業', 'href' => '/services/towing', 'description' => '福島県内のレッカー・カーレスキュー' ),
				array( 'label' => 'FC事業（カーメル／BUYMO）', 'href' => '/services/fc', 'description' => 'フランチャイズ加盟募集' ),
				array( 'label' => 'IT事業（APPREX）', 'href' => '/services/apprex', 'description' => 'ノーコードアプリ開発' ),
				array( 'label' => 'WEB制作（WEB crews）', 'href' => '/services/webcrews', 'description' => 'サブスク型のWeb制作・運用' ),
				array( 'label' => 'AIオペレーター24', 'href' => '/services/ai-operator-24', 'description' => '24時間対応のAI電話応対（準備中）' ),
			),
		),
		array( 'label' => 'ブランド一覧', 'href' => '/brands', 'children' => array() ),
		array(
			'label' => 'アイズについて', 'href' => '/about',
			'children' => array(
				array( 'label' => '会社概要', 'href' => '/about', 'description' => '会社情報・価値観' ),
				array( 'label' => '代表メッセージ', 'href' => '/message', 'description' => '代表からのごあいさつ' ),
				array( 'label' => '理念', 'href' => '/philosophy', 'description' => 'Always Innovation Solutions' ),
			),
		),
		array( 'label' => '実績', 'href' => '/works', 'children' => array() ),
		array( 'label' => 'お知らせ', 'href' => '/news', 'children' => array() ),
		array( 'label' => 'よくある質問', 'href' => '/faq', 'children' => array() ),
	);
}

/** フッターのリンク列 */
function ais_footer_nav() {
	return array(
		array( 'title' => '自動車事業', 'items' => array(
			array( 'label' => '自動車販売（カーメル）', 'href' => '/services/carmel' ),
			array( 'label' => '自動車買取（BUYMO）', 'href' => '/services/buymo' ),
			array( 'label' => 'オンライン車販売（CARSHICO）', 'href' => '/services/carshico' ),
			array( 'label' => '車両セキュリティ（天護）', 'href' => '/services/tengo' ),
			array( 'label' => 'レッカー事業', 'href' => '/services/towing' ),
		) ),
		array( 'title' => 'IT・WEB・FC', 'items' => array(
			array( 'label' => 'IT事業（APPREX）', 'href' => '/services/apprex' ),
			array( 'label' => 'WEB制作（WEB crews）', 'href' => '/services/webcrews' ),
			array( 'label' => 'AIオペレーター24', 'href' => '/services/ai-operator-24' ),
			array( 'label' => 'FC加盟募集', 'href' => '/franchise' ),
			array( 'label' => 'ブランド一覧', 'href' => '/brands' ),
		) ),
		array( 'title' => '会社情報・サポート', 'items' => array(
			array( 'label' => 'アイズについて', 'href' => '/about' ),
			array( 'label' => '代表メッセージ', 'href' => '/message' ),
			array( 'label' => '理念', 'href' => '/philosophy' ),
			array( 'label' => '採用情報', 'href' => '/recruit' ),
			array( 'label' => '実績', 'href' => '/works' ),
			array( 'label' => 'お知らせ', 'href' => '/news' ),
			array( 'label' => 'よくある質問', 'href' => '/faq' ),
			array( 'label' => 'お問い合わせ', 'href' => '/contact' ),
			array( 'label' => 'プライバシーポリシー', 'href' => '/privacy' ),
		) ),
	);
}

/** 事業グループ */
function ais_service_groups() {
	return array(
		array(
			'id'          => 'mobility',
			'label'       => '自動車事業',
			'description' => '販売・買取・オンライン納車・車両セキュリティ・レッカーまで、クルマに関わるすべてを。',
			'is_primary'  => true,
		),
		array(
			'id'          => 'it',
			'label'       => 'IT・WEB事業',
			'description' => 'ノーコードアプリ開発「APPREX」、サブスクWeb制作「WEB crews」、AI電話応対「AIオペレーター24」（準備中）。',
			'is_primary'  => false,
		),
		array(
			'id'          => 'business',
			'label'       => 'FC事業',
			'description' => '「カーメル」「BUYMO」のフランチャイズ加盟を募集しています。',
			'is_primary'  => false,
		),
	);
}

/** 全サービス（ブランド／事業） */
function ais_services() {
	return array(
		array(
			'slug' => 'carmel', 'name' => '自動車販売', 'brand' => 'カーメル', 'group' => 'mobility',
			'tagline' => '国産車の販売・全国対応', 'icon' => 'car', 'coming_soon' => false,
			'summary' => '「カーメル」は、国産車を中心に、ご予算・ご用途に合わせた一台をご提案する自動車販売サービスです。全国対応で、お住まいの地域を問わずご利用いただけます。',
			'highlights' => array( '国産車を幅広くご提案', '全国対応でお届け', '乗り換え・下取りのご相談' ),
			'audience' => array(
				'クルマの購入・乗り換えを検討している個人・法人',
				'予算や用途に合う一台を相談しながら選びたい方',
				'社用車の導入を検討している法人・個人事業主',
			),
			'offerings' => array(
				array( 'title' => '国産車のご提案・販売', 'description' => 'ご予算・ご用途をうかがい、国産車を中心に最適な一台をご提案。全国どこからでもご相談いただけます。' ),
				array( 'title' => '乗り換え・下取りのご相談', 'description' => '今お乗りのクルマの下取りや買取（BUYMO）と合わせて、スムーズな乗り換えをサポートします。' ),
				array( 'title' => 'お支払いプランのご案内', 'description' => '現金・ローンなど、ご希望に合わせたお支払い方法をご案内します。オンライン販売（CARSHICO）との組み合わせも可能です。' ),
			),
			'external_url' => 'https://carmelonline.jp/',
			'seo' => array(
				'title' => '自動車販売「カーメル」｜国産車・全国対応｜合同会社アイズ',
				'description' => '自動車販売ブランド「カーメル」。国産車を中心に、全国対応でご提案。乗り換え・下取りのご相談まで、ご予算・ご用途に合わせた一台をご提案します。',
			),
		),
		array(
			'slug' => 'buymo', 'name' => '自動車買取', 'brand' => 'BUYMO', 'group' => 'mobility',
			'tagline' => 'クルマから農機具・アルミまで全国買取', 'icon' => 'tag', 'coming_soon' => false,
			'summary' => '「BUYMO（バイモ）」は、国産・輸入車はもちろん、トラック・旧車から農機具・アルミまで、幅広く買取する全国対応の買取サービスです。',
			'highlights' => array( '国産・輸入車・トラック・旧車', '農機具・アルミなども買取', '全国対応・無料査定' ),
			'audience' => array(
				'クルマ（国産・輸入）をできるだけ高く売りたい方',
				'トラック・旧車・農機具などの売却先を探している方',
				'アルミなどの金属を買取してほしい事業者',
			),
			'offerings' => array(
				array( 'title' => '幅広い買取対象', 'description' => '国産・輸入車はもちろん、トラックや旧車、農機具、アルミなど、幅広い品目を買取の対象とします。まずはご相談ください。' ),
				array( 'title' => '無料査定・適正買取', 'description' => '市場相場や状態をふまえ、適正な査定額をご提示。全国対応で、お住まいの地域を問わず査定をご依頼いただけます。' ),
				array( 'title' => '手続きサポート', 'description' => '名義変更など売却に必要な手続きをサポートし、スムーズなお取引を実現します。' ),
			),
			'external_url' => '',
			'seo' => array(
				'title' => '自動車買取「BUYMO」｜車・トラック・農機具・アルミ買取｜合同会社アイズ',
				'description' => '買取ブランド「BUYMO（バイモ）」。国産・輸入車、トラック・旧車から農機具・アルミまで、全国対応で買取。無料査定から手続きサポートまで承ります。',
			),
		),
		array(
			'slug' => 'carshico', 'name' => 'オンライン車販売', 'brand' => 'CARSHICO', 'group' => 'mobility',
			'tagline' => '新車をオンラインで注文、自宅にお届け', 'icon' => 'key', 'coming_soon' => false,
			'summary' => '「CARSHICO（カシコ）」は、国産・輸入車の新車を、オンラインで注文してご自宅で受け取れる、新しいクルマの買い方です。来店せずに手続きが完結します。',
			'highlights' => array( '新車をオンラインで注文', 'ご自宅まで納車', '国産・輸入車に対応' ),
			'audience' => array(
				'来店せずにクルマを購入したい方',
				'自宅にいながら新車を受け取りたい方',
				'国産・輸入の新車を検討している個人・法人',
			),
			'offerings' => array(
				array( 'title' => 'オンラインで注文', 'description' => 'スマホ・PCから新車をオンラインで注文。来店不要で、手続きまでオンラインで完結します。' ),
				array( 'title' => 'ご自宅まで納車', 'description' => 'ご注文いただいた新車を、ご自宅までお届け。納車のために店舗へ足を運ぶ必要はありません。' ),
				array( 'title' => '国産・輸入車に対応', 'description' => '国産車から輸入車まで、幅広い新車をお選びいただけます。ご希望の車種をお気軽にご相談ください。' ),
			),
			'external_url' => '',
			'seo' => array(
				'title' => 'オンライン車販売「CARSHICO」｜新車をオンラインで注文・自宅納車｜合同会社アイズ',
				'description' => 'オンライン車販売ブランド「CARSHICO（カシコ）」。国産・輸入の新車をオンラインで注文し、ご自宅にお届け。来店せずに購入手続きが完結します。',
			),
		),
		array(
			'slug' => 'tengo', 'name' => '車両セキュリティ', 'brand' => '天護 TENGO', 'group' => 'mobility',
			'tagline' => 'GPS遠隔停止システムで愛車を守る', 'icon' => 'gps', 'coming_soon' => false,
			'summary' => '「天護（TENGO）」は、GPSによる位置把握と遠隔エンジン停止で、大切なクルマを盗難・不正利用から守る、個人・法人向けの車両セキュリティシステムです。',
			'highlights' => array( 'GPSで車両位置を把握', '遠隔でエンジンを停止', '個人・法人どちらも対応' ),
			'audience' => array(
				'クルマの盗難・不正利用が心配な方',
				'高価格帯・人気車種にお乗りの方',
				'複数の車両を管理・保全したい法人',
			),
			'offerings' => array(
				array( 'title' => 'GPSによる位置把握', 'description' => 'GPSで車両の位置を把握。万一の盗難時にも、位置情報の確認に役立ちます。' ),
				array( 'title' => '遠隔停止システム', 'description' => '万一の盗難・不正利用時に、遠隔操作でエンジンを停止。被害の拡大を防ぎ、車両を保全します。' ),
				array( 'title' => '個人・法人の車両管理', 'description' => '個人の愛車から法人の複数車両まで対応。位置・稼働の把握と保全で、車両管理を支援します。' ),
			),
			'external_url' => '',
			'seo' => array(
				'title' => '車両セキュリティ「天護 TENGO」｜GPS遠隔停止システム｜合同会社アイズ',
				'description' => '車両セキュリティブランド「天護（TENGO）」。GPSによる位置把握と遠隔エンジン停止で大切なクルマを守ります。個人・法人専用の車両保全システムです。',
			),
		),
		array(
			'slug' => 'towing', 'name' => 'レッカー事業', 'brand' => '', 'group' => 'mobility',
			'tagline' => '福島県内のレッカー・カーレスキュー', 'icon' => 'truck', 'coming_soon' => false,
			'summary' => '福島県内のクルマのトラブルに、レッカー手配やカーレスキューで対応します。もしものときも、アイズにお任せください。',
			'highlights' => array( '福島県内に対応', 'レッカー移動の手配', 'バッテリー上がり・パンク等' ),
			'audience' => array(
				'福島県内で出先のクルマのトラブルに備えたい方',
				'すぐに駆けつけてほしい方',
				'事故・故障時の搬送が必要な方',
			),
			'offerings' => array(
				array( 'title' => 'レッカー移動', 'description' => '故障や事故で動かなくなったクルマを、レッカーで安全に移動・搬送します。対応エリアは福島県内です。' ),
				array( 'title' => '応急対応（カーレスキュー）', 'description' => 'バッテリー上がり・パンク・キー閉じ込みなど、出先のトラブルに応急対応します。' ),
				array( 'title' => '事故・故障時の搬送', 'description' => '万一の事故や故障の際にも、状況に応じて適切な搬送・対応をご案内します。' ),
			),
			'external_url' => '',
			'seo' => array(
				'title' => 'レッカー事業・カーレスキュー｜福島県内対応｜合同会社アイズ',
				'description' => '福島県内対応のレッカー手配・カーレスキュー。バッテリー上がり・パンク・キー閉じ込み・事故時の搬送など、出先のクルマのトラブルに対応します。',
			),
		),
		array(
			'slug' => 'fc', 'name' => 'FC事業', 'brand' => 'カーメル／BUYMO', 'group' => 'business',
			'tagline' => 'フランチャイズ加盟募集', 'icon' => 'store', 'coming_soon' => false,
			'summary' => '自動車販売「カーメル」・買取「BUYMO」のフランチャイズ（FC）加盟を募集しています。自動車事業への参入・拡大をサポートします。',
			'highlights' => array( '「カーメル」FC加盟', '「BUYMO」FC加盟', '開業・運営をサポート' ),
			'audience' => array(
				'自動車販売・買取事業に新規参入したい方',
				'既存事業に車の販売・買取を加えたい事業者',
				'ブランドを活かして独立開業したい方',
			),
			'offerings' => array(
				array( 'title' => '「カーメル」フランチャイズ', 'description' => '自動車販売ブランド「カーメル」の加盟店として、車販売事業を立ち上げ・運営いただけます。' ),
				array( 'title' => '「BUYMO」フランチャイズ', 'description' => '買取ブランド「BUYMO」の加盟店として、車・トラック・農機具・アルミなどの買取事業に参入いただけます。' ),
				array( 'title' => '開業・運営サポート', 'description' => '開業準備から運営まで、本部がノウハウを提供しサポートします。詳細はお問い合わせ・専用サイトをご覧ください。' ),
			),
			'external_url' => 'https://buysellfc.carmelonline.jp/',
			'seo' => array(
				'title' => 'FC（フランチャイズ）事業｜カーメル・BUYMO加盟募集｜合同会社アイズ',
				'description' => '自動車販売「カーメル」・買取「BUYMO」のフランチャイズ加盟募集。自動車事業への新規参入・拡大を、開業から運営までサポートします。',
			),
		),
		array(
			'slug' => 'apprex', 'name' => 'IT事業', 'brand' => 'APPREX', 'group' => 'it',
			'tagline' => 'ノーコードアプリ開発', 'icon' => 'app', 'coming_soon' => false,
			'summary' => '「APPREX（アップレックス）」は、ノーコードでアプリを企画・開発・リリースするIT事業です。スピーディに低コストで、アイデアを形にします。',
			'highlights' => array( 'ノーコードでアプリ開発', 'スピーディ・低コスト', 'リリース後の運用・改善' ),
			'audience' => array(
				'アプリを早く・低コストで立ち上げたい事業者',
				'アイデアはあるが開発手段に悩んでいる方',
				'公開後の運用・改善まで任せたい企業',
			),
			'offerings' => array(
				array( 'title' => 'ノーコードアプリ開発', 'description' => 'ノーコードでアプリを開発・リリース。専門的な開発知識がなくても、スピーディかつ低コストでアプリを形にします。' ),
				array( 'title' => '企画・要件整理', 'description' => '「何を作るか」の整理から伴走。実現したいことをうかがい、アプリの形に落とし込みます。' ),
				array( 'title' => '運用・改善', 'description' => 'リリース後の運用・改善まで継続的にサポートし、サービスの成長を支えます。' ),
			),
			'external_url' => 'https://site.aiscompany.jp/',
			'seo' => array(
				'title' => 'ノーコードアプリ開発「APPREX」｜IT事業｜合同会社アイズ',
				'description' => 'ノーコードアプリ開発ブランド「APPREX（アップレックス）」。スピーディかつ低コストで、アイデアをアプリへ。企画から開発・運用までサポートします。',
			),
		),
		array(
			'slug' => 'webcrews', 'name' => 'WEB制作', 'brand' => 'WEB crews', 'group' => 'it',
			'tagline' => 'サブスク型のWeb制作・運用', 'icon' => 'code', 'coming_soon' => false,
			'summary' => '「WEB crews（ウェブクルーズ）」は、初期費用を抑え、月額サブスクでWebサイトを制作・運用できるサービスです。スモールスタートで、公開後の更新まで任せられます。',
			'highlights' => array( 'サブスクで初期費用を抑制', '制作から運用・更新まで', '月額でずっとサポート' ),
			'audience' => array(
				'初期費用を抑えてWebサイトを持ちたい事業者',
				'制作だけでなく公開後の更新も任せたい企業',
				'Webサイトをリニューアルしたい企業',
			),
			'offerings' => array(
				array( 'title' => 'サブスク型Web制作', 'description' => '初期費用を抑え、月額サブスクでWebサイトを制作。まとまった資金をかけずにスモールスタートできます。' ),
				array( 'title' => '運用・更新サポート', 'description' => '公開後の更新・運用も月額に含めて対応。「変更したい」「直したい」にも継続的にお応えします。' ),
				array( 'title' => 'デザイン・改善', 'description' => '見やすく伝わるデザインで制作し、公開後も成果につながるよう継続的に改善していきます。' ),
			),
			'external_url' => '',
			'seo' => array(
				'title' => 'サブスクWeb制作「WEB crews」｜月額でサイト制作・運用｜合同会社アイズ',
				'description' => 'Web制作ブランド「WEB crews（ウェブクルーズ）」。初期費用を抑え、月額サブスクでWebサイトを制作・運用。公開後の更新・改善まで継続サポートします。',
			),
		),
		array(
			'slug' => 'ai-operator-24', 'name' => 'AIオペレーター24', 'brand' => 'AIオペレーター24', 'group' => 'it',
			'tagline' => '24時間対応のAI電話応対（準備中）', 'icon' => 'spark', 'coming_soon' => true,
			'summary' => '「AIオペレーター24」は、電話や問い合わせへの一次対応を、AIが24時間体制で自動化するサービスです。現在リリースに向けて準備を進めています。',
			'highlights' => array( 'AIが24時間自動で応対', '電話・問い合わせ対応を自動化', '現在リリース準備中' ),
			'audience' => array(
				'電話・問い合わせ対応の負担を減らしたい事業者',
				'営業時間外の問い合わせを取りこぼしたくない方',
				'人手不足を自動化で補いたい企業',
			),
			'offerings' => array(
				array( 'title' => '24時間の自動応対', 'description' => 'AIが電話・問い合わせに24時間対応。営業時間外や繁忙時の取りこぼしを防ぎます。' ),
				array( 'title' => '一次対応の自動化', 'description' => 'よくある質問や受付などの一次対応を自動化し、スタッフの負担を軽減します。' ),
				array( 'title' => '導入のご相談', 'description' => '現在リリースに向けて準備中です。導入をご検討の方は、お気軽にお問い合わせください。' ),
			),
			'external_url' => '',
			'seo' => array(
				'title' => 'AIオペレーター24｜24時間対応のAI電話応対（準備中）｜合同会社アイズ',
				'description' => '「AIオペレーター24」は、電話・問い合わせの一次対応をAIが24時間自動化するサービス。現在リリース準備中。導入のご相談を受け付けています。',
			),
		),
	);
}

/** スラッグからサービスを取得 */
function ais_get_service( $slug ) {
	foreach ( ais_services() as $s ) {
		if ( $s['slug'] === $slug ) { return $s; }
	}
	return null;
}

/** グループIDでサービスを抽出 */
function ais_services_by_group( $group ) {
	return array_values( array_filter( ais_services(), function ( $s ) use ( $group ) {
		return $s['group'] === $group;
	} ) );
}

/** トップ: 課題 */
function ais_home_problems() {
	return array(
		'heading' => 'こんなお悩みはありませんか？',
		'lead'    => 'クルマのことから、アプリ・Webの活用まで。幅広いご相談に、アイズがワンストップでお応えします。',
		'items'   => array(
			array( 'title' => 'クルマの購入・乗り換えを考えている', 'body' => '新車も中古車も。予算や用途に合う一台を、相談しながら選びたい。' ),
			array( 'title' => '今の愛車をできるだけ高く売りたい', 'body' => '適正な査定で買取してほしい。乗り換えと合わせて相談したい。' ),
			array( 'title' => '来店せずにクルマを買いたい', 'body' => '新車をオンラインで注文して、自宅で受け取りたい。国産も輸入車も相談したい。' ),
			array( 'title' => 'アプリやWebサービスを立ち上げたい', 'body' => 'ノーコードで早く・低コストに。アプリやサイトの開発を一緒に進めてほしい。' ),
		),
	);
}

/** トップ: 選ばれる理由 */
function ais_home_strengths() {
	return array(
		'heading' => 'アイズが選ばれる理由',
		'lead'    => '戦略の入口から実行・改善まで、一社で完結できる伴走体制が私たちの強みです。',
		'items'   => array(
			array( 'no' => '01', 'title' => 'クルマのことをワンストップで', 'body' => '販売・買取・オンライン納車・セキュリティ・レッカーを一社で完結。購入から売却、保全まで、窓口をひとつにまとめてご相談いただけます。' ),
			array( 'no' => '02', 'title' => '自動車 × IT / アプリ開発', 'body' => 'クルマの現場知見と、アプリ・システム開発の実装力を併せ持つから、デジタル活用まで一気通貫で支援できます。' ),
			array( 'no' => '03', 'title' => '多角的な事業展開', 'body' => '自動車（販売・買取・オンライン販売・セキュリティ・レッカー）からIT・WEB、FCまで幅広く展開。複数の領域をまたいで、総合的に課題解決をお手伝いします。' ),
			array( 'no' => '04', 'title' => '顧客伴走型の支援体制', 'body' => '「革新」「品質」「信頼性」を軸に、課題解決しながら最後まで寄り添います。お渡しして終わりにはしません。' ),
		),
	);
}

/** トップ／サービス: 流れ */
function ais_home_workflow() {
	return array(
		'heading' => 'ご相談から支援開始までの流れ',
		'lead'    => 'まずは現状をお聞かせください。無理な提案や売り込みはいたしません。',
		'steps'   => array(
			array( 'no' => '01', 'title' => 'お問い合わせ・ご相談', 'body' => 'フォームからお気軽にご連絡ください。相談内容が固まっていなくても大丈夫です。' ),
			array( 'no' => '02', 'title' => 'ヒアリング', 'body' => '事業の現状・課題・目指す姿をお聞きし、論点を一緒に整理します。' ),
			array( 'no' => '03', 'title' => 'ご提案・お見積り', 'body' => '課題に合わせた支援内容・進め方・費用をご提案します。' ),
			array( 'no' => '04', 'title' => '実行支援', 'body' => '戦略の実行、制作・開発、施策の運用まで、手を動かして伴走します。' ),
			array( 'no' => '05', 'title' => '改善・グロース', 'body' => '成果を計測し、継続的に改善。事業の成長まで並走します。' ),
		),
	);
}

/** トップ: メッセージ */
function ais_home_message() {
	return array(
		'heading' => '構想を、成果へ。',
		'body'    => array(
			'AIS＝Always Innovation Solutions。私たちは、常に新しいことを取り入れ、課題を解決しながら最後まで寄り添う会社です。',
			'自動車業界の知見とITの実装力を掛け合わせ、戦略から実行まで一気通貫で伴走する。アイデアを「構想」で終わらせず、確かな「成果」へと変えていく。それが私たちの役割です。',
		),
	);
}

/** 会社概要 */
function ais_company_profile() {
	return array(
		array( 'label' => '会社名', 'value' => '合同会社アイズ（AIS LLC）' ),
		array( 'label' => '代表者', 'value' => '代表 吉田 一平' ),
		array( 'label' => '所在地', 'value' => '〒979-0204 福島県いわき市四倉町細谷字大町1番' ),
		array( 'label' => 'お問い合わせ', 'value' => 'info@aisjaltd.com（メール・チャットにて対応）' ),
		array( 'label' => '事業内容', 'value' => '自動車販売「カーメル」（国産車・全国）／自動車買取「BUYMO」（車・トラック・農機具・アルミ等／全国）／オンライン車販売「CARSHICO」（新車・自宅納車）／車両セキュリティ「天護 TENGO」（GPS遠隔停止）／レッカー事業（福島県内）／FC事業／IT事業「APPREX」（ノーコードアプリ開発）／サブスクWeb制作「WEB crews」／AI電話応対「AIオペレーター24」（準備中）' ),
	);
}

/** 代表メッセージ（※文面はドラフト。確定文に差し替え） */
function ais_representative() {
	return array(
		'name'    => '吉田 一平',
		'title'   => '合同会社アイズ 代表',
		'heading' => '変化を、お客様の成長の機会に。',
		'lead'    => 'ごあいさつ',
		'body'    => array(
			'数あるサイトの中から、合同会社アイズをご覧いただきありがとうございます。',
			'私たちは「Always Innovation Solutions（常に、新しい解決策を）」を掲げ、自動車事業を主軸に、IT・WEB事業まで幅広く手がけています。クルマの販売・買取・オンライン納車・セキュリティ・レッカーから、アプリ・Web制作まで——お客様の「困った」「こうしたい」に、ワンストップでお応えすることを大切にしています。',
			'とりわけ自動車業界は、販売のかたちもサービスのあり方も、大きく変わろうとしています。私たちはその変化を「リスク」ではなく「成長の機会」と捉え、新しい仕組みを積極的に取り入れながら、地域とお客様に寄り添い続けます。',
			'お渡しして終わりにはしません。構想から実行、そしてその先の成果まで——最後まで伴走するパートナーでありたいと考えています。どうぞお気軽にご相談ください。',
		),
	);
}

/** 理念 */
function ais_philosophy() {
	return array(
		'brand'   => 'AIS = Always Innovation Solutions',
		'tagline' => '常に、新しい解決策を。',
		'vision'  => array(
			'title' => '私たちの想い',
			'body'  => array(
				'テクノロジーの進化は、あらゆる業界のあり方を変えつつあります。とりわけ自動車業界は、販売・サービス・業務のすべてで大きな変革のただ中にあります。',
				'私たちアイズは、その変化を「リスク」ではなく「成長の機会」に変えるパートナーでありたいと考えています。常に新しいことを取り入れ、課題を解決しながら、最後までお客様に寄り添う。それが私たちの変わらない姿勢です。',
			),
		),
		'values'  => array(
			array( 'title' => '革新 / Innovation', 'body' => '現状維持に留まらず、常に新しい技術と発想を取り入れ、より良い解決策を追求します。' ),
			array( 'title' => '品質 / Quality', 'body' => '戦略から実行まで、一つひとつの仕事に責任を持ち、確かな品質で成果に向き合います。' ),
			array( 'title' => '信頼性 / Reliability', 'body' => '誠実な対応と継続的な伴走で、長く頼っていただける関係を築きます。' ),
		),
	);
}

/** FAQ */
function ais_faqs() {
	return array(
		array( 'q' => '相談は無料ですか？費用はどのくらいかかりますか？', 'a' => '初回のご相談・お見積りは無料です。費用はご支援の内容・範囲によって異なるため、ヒアリングのうえでお見積りをご提示します。ご予算に合わせた進め方のご提案も可能です。' ),
		array( 'q' => '個人でも、法人でも相談できますか？', 'a' => 'はい、個人のお客様・法人のどちらもご相談いただけます。クルマの購入・買取・オンライン販売から、アプリ・Web制作のご相談まで承ります。' ),
		array( 'q' => 'クルマの販売・買取・オンライン購入・レスキューはどれも対応できますか？', 'a' => 'はい。国産車を中心とした販売（カーメル）、車から農機具・アルミまでの買取（BUYMO）、新車をオンライン注文して自宅で受け取れるオンライン車販売（CARSHICO）、出先のトラブルに対応するカーレスキューまで、クルマに関わるニーズにワンストップでお応えします。乗り換えと買取を合わせたご相談も可能です。' ),
		array( 'q' => '何が課題か整理できていない段階でも相談できますか？', 'a' => 'もちろんです。「何から始めればよいか分からない」という段階こそ、私たちの得意とするところです。現状をお聞きしながら、論点の整理からお手伝いします。' ),
		array( 'q' => '戦略の提案だけ、または実行・制作だけの依頼も可能ですか？', 'a' => '可能です。戦略設計から実行まで一気通貫で対応できますが、必要な部分のみのご依頼も承ります。既存の取り組みに合わせて柔軟にご支援します。' ),
		array( 'q' => 'オンラインでクルマを買えると聞きました。どんな仕組みですか？', 'a' => 'オンライン車販売「CARSHICO」では、国産・輸入の新車をオンラインで注文し、ご自宅まで納車します。来店せずに手続きが完結するため、お忙しい方やお近くに店舗がない方にもご利用いただけます。ご希望の車種をお気軽にご相談ください。' ),
		array( 'q' => 'ノーコードでアプリを作れると聞きました。どんなことができますか？', 'a' => 'ノーコードアプリ開発「APPREX」では、専門的な開発知識がなくても、スピーディかつ低コストでアプリを形にできます。Webサイトは月額サブスクで制作・運用できる「WEB crews」で対応し、公開後の更新・改善まで継続的にご支援します。' ),
		array( 'q' => '問い合わせ後、どのくらいで返信がありますか？', 'a' => '原則1〜2営業日以内にご返信します。内容によってはお時間をいただく場合がありますので、あらかじめご了承ください。' ),
	);
}

/** 実績（※サンプル） */
function ais_works() {
	return array(
		array( 'slug' => 'sample-carmel-buymo', 'title' => '中古車のご提案（カーメル）と買取（BUYMO）での乗り換え', 'category' => 'mobility', 'category_label' => '自動車販売・買取', 'summary' => 'ご予算・ご用途をうかがい最適な中古車をご提案（カーメル）。今お乗りの車の買取（BUYMO）と合わせて、スムーズな乗り換えを実現。', 'result' => 'ご希望条件での乗り換えを実現（※サンプル）', 'is_placeholder' => true ),
		array( 'slug' => 'sample-carshico-online', 'title' => 'オンライン車販売（CARSHICO）での新車購入・自宅納車', 'category' => 'mobility', 'category_label' => 'オンライン車販売', 'summary' => '来店が難しいお客様へ、新車をオンラインで注文（CARSHICO）。手続きまでオンラインで完結し、ご自宅まで納車。', 'result' => '来店なしで新車購入が完結（※サンプル）', 'is_placeholder' => true ),
		array( 'slug' => 'sample-apprex-app', 'title' => 'ノーコード（APPREX）でのアプリ開発・リリース', 'category' => 'it', 'category_label' => 'IT事業（APPREX）', 'summary' => 'APPREXのノーコード開発で、企画からリリースまでをスピーディに対応。低コストでアプリを形にし、公開後の改善まで支援。', 'result' => '短期間・低コストでリリース（※サンプル）', 'is_placeholder' => true ),
	);
}

function ais_get_work( $slug ) {
	foreach ( ais_works() as $w ) {
		if ( $w['slug'] === $slug ) { return $w; }
	}
	return null;
}

/** お知らせ・コラム（※サンプル） */
function ais_news() {
	return array(
		array( 'slug' => 'sample-website-renewal', 'title' => 'コーポレートサイトをリニューアルしました', 'date' => '2026-06-07', 'category' => 'お知らせ', 'excerpt' => '事業内容がより分かりやすく伝わるよう、コーポレートサイトを全面リニューアルしました。', 'body' => array(
			'この度、合同会社アイズのコーポレートサイトを全面リニューアルいたしました。',
			'自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカー、IT事業「APPREX」、サブスクWeb制作「WEB crews」、FC事業を分かりやすく整理し、ご相談いただきやすい構成へと刷新しています。',
			'（本記事はサンプルです。実際のお知らせに差し替えてください。）',
		), 'is_placeholder' => true ),
		array( 'slug' => 'sample-column-online-car-buying', 'title' => '【コラム】クルマをオンラインで買うという選択', 'date' => '2026-05-20', 'category' => 'コラム', 'excerpt' => '新車をオンラインで注文して自宅で受け取る、新しいクルマの買い方とそのメリットを整理します。', 'body' => array(
			'「店舗に行く時間がない」「近くに販売店がない」といった理由から、オンラインでのクルマ購入に関心を持つ方が増えています。',
			'オンライン車販売（CARSHICO）なら、新車の注文から手続きまでをオンラインで完結し、ご自宅まで納車できます。（本記事はサンプルです。）',
		), 'is_placeholder' => true ),
		array( 'slug' => 'sample-column-car-buyback', 'title' => '【コラム】愛車を少しでも高く売るためのポイント', 'date' => '2026-04-15', 'category' => 'コラム', 'excerpt' => 'クルマの買取査定で、少しでも高く売るために知っておきたいポイントを解説します。', 'body' => array(
			'買取査定の金額は、車両の状態や時期、需要によって変わります。',
			'売却のタイミングや書類の準備など、事前にできることがあります。（本記事はサンプルです。）',
		), 'is_placeholder' => true ),
	);
}

function ais_get_news( $slug ) {
	foreach ( ais_news() as $n ) {
		if ( $n['slug'] === $slug ) { return $n; }
	}
	return null;
}
