<?php
/**
 * AIスタジオ：ホームページをAIで作る（イメージの試作と仕様書）。
 *
 * - REST /studio/ai-hp：業種・要望から、ページ構成・文章・色をAIが作る（修正の指示で作り直しも）
 * - REST /studio/hp-image：メインビジュアルの写真をAIで作る
 * - 試作の表示：/app-demo/{トークン}/?v=hp（&p=ページID）。テーマのヘッダー・フッターなしの1枚のHTML
 *
 * 作るのはあくまでイメージ。実際の制作はホームページ制作プラン（Light／Standard／Premium）で行う。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** セクションの種類（キー => 名前）。 */
function apprex_hp_section_types() {
	return array(
		'hero'     => 'メインビジュアル',
		'about'    => '紹介文',
		'features' => '強み・特徴',
		'services' => 'サービス・メニュー',
		'price'    => '料金',
		'flow'     => 'ご利用の流れ',
		'voice'    => 'お客様の声',
		'staff'    => 'スタッフ',
		'gallery'  => 'ギャラリー',
		'news'     => 'お知らせ',
		'faq'      => 'よくある質問',
		'company'  => '会社・店舗情報',
		'access'   => 'アクセス',
		'contact'  => 'お問い合わせ',
		'cta'      => '行動の呼びかけ',
	);
}

/** HP制作プラン（料金ページと同じ）。 */
function apprex_hp_plans() {
	return array(
		'light'    => array( 'Light', 9800, 5, '〜5ページ・お問い合わせフォーム・月1回の更新サポート' ),
		'standard' => array( 'Standard', 19800, 10, '〜10ページ＋LP1本・予約／問い合わせ強化・月2回の更新サポート' ),
		'premium'  => array( 'Premium', 39800, 20, '〜20ページ・EC／会員機能・月3回の更新サポート' ),
	);
}

/**
 * デザインの選択肢（デザイン案・色・文字・写真の雰囲気）。スタジオの画面と試作の表示で使う。
 *
 * @return array
 */
function apprex_hp_design_options() {
	return array(
		'styles'   => array(
			'A' => array( 'スタンダード', '見出しを中央にそろえた、すっきり読みやすいデザイン' ),
			'B' => array( 'エレガント', '写真と文字を左右に並べた、雑誌のように上品なデザイン' ),
			'C' => array( 'インパクト', '大きな写真と太い文字で、ひと目で印象に残るデザイン' ),
		),
		'palettes' => array(
			'ai'     => array( 'AIのおすすめ', '', '#c9a227' ),
			'navy'   => array( '信頼のネイビー', '#1f3a68', '#c9a227' ),
			'red'    => array( '和の赤', '#8a1c1c', '#d4a24c' ),
			'green'  => array( 'ナチュラルグリーン', '#2f6b4f', '#e0b45a' ),
			'blue'   => array( '清潔感のブルー', '#0e7490', '#22c55e' ),
			'pink'   => array( 'やさしいピンク', '#c2577a', '#f2c94c' ),
			'orange' => array( '元気なオレンジ', '#e8590c', '#1f6feb' ),
			'purple' => array( '上品なパープル', '#5b3f8c', '#e5b567' ),
			'mono'   => array( 'モノトーン', '#1f2328', '#b08d57' ),
		),
		'fonts'    => array(
			'trust'   => array( 'ゴシック', '"Noto Sans JP",sans-serif', 'まじめで読みやすい' ),
			'luxury'  => array( '明朝', '"Zen Old Mincho","Noto Serif JP",serif', '上品・高級感' ),
			'natural' => array( '丸ゴシック', '"Zen Maru Gothic","Noto Sans JP",sans-serif', 'やさしい・親しみ' ),
			'pop'     => array( 'まるもじ', '"M PLUS Rounded 1c","Noto Sans JP",sans-serif', '明るく元気' ),
			'sporty'  => array( '角ゴシック', '"Zen Kaku Gothic New","Noto Sans JP",sans-serif', '力強い・シャープ' ),
		),
		'photos'   => array(
			'bright'  => array( '明るい自然光', 'bright, airy, natural daylight, clean and fresh', 'brightness(1.06) saturate(1.05)' ),
			'luxury'  => array( '落ち着いた高級感', 'moody, warm low-key lighting, premium and calm atmosphere', 'brightness(.88) contrast(1.08)' ),
			'natural' => array( 'ナチュラル', 'soft natural light, warm earthy tones, cozy and gentle', 'saturate(.85) sepia(.12)' ),
			'vivid'   => array( '元気・カラフル', 'vivid, colorful, energetic, lively', 'saturate(1.3) contrast(1.05)' ),
			'mono'    => array( 'モノトーン', 'monochrome, stylish, minimal, black and white', 'grayscale(1) contrast(1.05)' ),
		),
	);
}

/** トーンに合う、最初のデザイン。 */
function apprex_hp_design_default( $tone ) {
	$style = array( 'luxury' => 'B', 'natural' => 'B', 'pop' => 'C', 'sporty' => 'C' );
	$photo = array( 'luxury' => 'luxury', 'natural' => 'natural', 'pop' => 'vivid', 'sporty' => 'vivid' );
	return array(
		'style'   => isset( $style[ $tone ] ) ? $style[ $tone ] : 'A',
		'palette' => 'ai',
		'font'    => $tone ? $tone : 'trust',
		'photo'   => isset( $photo[ $tone ] ) ? $photo[ $tone ] : 'bright',
	);
}

/** デザインの値をそろえる（知らない値は既定に）。 */
function apprex_hp_sanitize_design( $in, $tone = 'trust' ) {
	$o   = apprex_hp_design_options();
	$def = apprex_hp_design_default( $tone );
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	foreach ( array( 'style' => 'styles', 'palette' => 'palettes', 'font' => 'fonts', 'photo' => 'photos' ) as $k => $list ) {
		$v         = isset( $in[ $k ] ) ? (string) $in[ $k ] : '';
		$out[ $k ] = isset( $o[ $list ][ $v ] ) ? $v : $def[ $k ];
	}
	return $out;
}

/**
 * 表示に使うデザイン（色の値・文字の指定まで）。
 *
 * @param array $spec     試作。
 * @param array $override 試しに見るときの上書き（style/palette/font/photo）。
 * @return array
 */
function apprex_hp_design( $spec, $override = array() ) {
	$d = apprex_hp_sanitize_design( array_merge( isset( $spec['design'] ) ? (array) $spec['design'] : array(), array_filter( (array) $override ) ), $spec['tone'] );
	$o = apprex_hp_design_options();
	$p = $o['palettes'][ $d['palette'] ];
	$d['c']      = $p[1] ? $p[1] : $spec['color'];
	$d['a']      = $p[2];
	$d['ff']     = $o['fonts'][ $d['font'] ][1];
	$d['filter'] = $o['photos'][ $d['photo'] ][2];
	// アクセント色の上の文字色（明るい色なら黒、暗い色なら白）。
	$rgb     = sscanf( $d['a'], '#%02x%02x%02x' );
	$d['at'] = ( 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2] ) > 150 ? '#111' : '#fff';
	return $d;
}

/**
 * AIの出力を、決まった形にそろえる。
 *
 * @param mixed $in AIの出力（配列）。
 * @return array|null
 */
function apprex_hp_sanitize( $in ) {
	if ( ! is_array( $in ) || empty( $in['pages'] ) || ! is_array( $in['pages'] ) ) {
		return null;
	}
	$str  = function ( $s, $n ) {
		return mb_substr( trim( sanitize_text_field( (string) $s ) ), 0, $n );
	};
	$txt  = function ( $s, $n ) {
		return mb_substr( trim( sanitize_textarea_field( (string) $s ) ), 0, $n );
	};
	$list = function ( $a, $n, $len ) use ( $str ) {
		$out = array();
		foreach ( array_slice( (array) $a, 0, $n ) as $x ) {
			if ( is_array( $x ) ) {
				$x = implode( '｜', array_map( 'strval', array_slice( $x, 0, 3 ) ) );
			}
			$x = $str( $x, $len );
			if ( '' !== $x ) {
				$out[] = $x;
			}
		}
		return $out;
	};
	$types = apprex_hp_section_types();
	$color = isset( $in['color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $in['color'] ) ? strtolower( $in['color'] ) : '#1f4398';
	$out   = array(
		'name'      => $str( isset( $in['name'] ) ? $in['name'] : 'ホームページ', 30 ),
		'industry'  => $str( isset( $in['industry'] ) ? $in['industry'] : '', 20 ),
		'catch'     => $str( isset( $in['catch'] ) ? $in['catch'] : '', 40 ),
		'sub'       => $str( isset( $in['sub'] ) ? $in['sub'] : '', 90 ),
		'color'     => $color,
		'tone'      => in_array( isset( $in['tone'] ) ? $in['tone'] : '', array( 'trust', 'luxury', 'natural', 'pop', 'sporty' ), true ) ? $in['tone'] : 'trust',
		'cta'       => $str( isset( $in['cta'] ) ? $in['cta'] : 'お問い合わせ', 14 ),
		'target'    => $str( isset( $in['target'] ) ? $in['target'] : '', 60 ),
		'goals'     => $list( isset( $in['goals'] ) ? $in['goals'] : array(), 4, 40 ),
		'keywords'  => $list( isset( $in['keywords'] ) ? $in['keywords'] : array(), 6, 20 ),
		'seo_desc'  => $str( isset( $in['seo_desc'] ) ? $in['seo_desc'] : '', 120 ),
		'features'  => $list( isset( $in['features'] ) ? $in['features'] : array(), 8, 30 ),
		'questions' => $list( isset( $in['questions'] ) ? $in['questions'] : array(), 6, 60 ),
		'pages'     => array(),
		'idea'      => $txt( isset( $in['idea'] ) ? $in['idea'] : '', 1000 ),
	);
	$ids = array();
	foreach ( array_slice( $in['pages'], 0, 12 ) as $pg ) {
		if ( ! is_array( $pg ) ) {
			continue;
		}
		$id = sanitize_key( isset( $pg['id'] ) ? $pg['id'] : '' );
		$id = '' === $id || isset( $ids[ $id ] ) ? 'p' . ( count( $ids ) + 1 ) : $id;
		$ids[ $id ] = true;
		$secs = array();
		foreach ( array_slice( (array) ( isset( $pg['sections'] ) ? $pg['sections'] : array() ), 0, 8 ) as $sc ) {
			if ( ! is_array( $sc ) || ! isset( $types[ isset( $sc['t'] ) ? $sc['t'] : '' ] ) ) {
				continue;
			}
			$secs[] = array(
				't'     => $sc['t'],
				'title' => $str( isset( $sc['title'] ) ? $sc['title'] : $types[ $sc['t'] ], 30 ),
				'body'  => $txt( isset( $sc['body'] ) ? $sc['body'] : '', 300 ),
				'items' => $list( isset( $sc['items'] ) ? $sc['items'] : array(), 8, 90 ),
			);
		}
		if ( ! $secs ) {
			continue;
		}
		$out['pages'][] = array(
			'id'       => $id,
			'name'     => $str( isset( $pg['name'] ) ? $pg['name'] : 'ページ', 12 ),
			'purpose'  => $str( isset( $pg['purpose'] ) ? $pg['purpose'] : '', 60 ),
			'sections' => $secs,
		);
	}
	$out['design'] = apprex_hp_sanitize_design( isset( $in['design'] ) ? $in['design'] : array(), $out['tone'] );
	return $out['pages'] ? $out : null;
}

/** AIが使えないときのひな形。 */
function apprex_hp_template( $idea, $name, $industry ) {
	$n = $name ? $name : ( $industry ? $industry : 'あなたのお店' );
	return array(
		'name'      => $n,
		'industry'  => $industry,
		'catch'     => '地域のみなさまに、いちばん近い' . ( $industry ? $industry : 'お店' ) . 'へ',
		'sub'       => 'ていねいな対応と、わかりやすいご案内を大切にしています。お気軽にご相談ください。',
		'color'     => '#1f4398',
		'tone'      => 'trust',
		'cta'       => 'お問い合わせ',
		'target'    => '地域のお客様',
		'goals'     => array( '新しいお客様に見つけてもらう', '問い合わせ・予約を増やす' ),
		'keywords'  => array_filter( array( $industry, '地域名' ) ),
		'seo_desc'  => $n . 'の公式ホームページ。サービス・料金・アクセス・お問い合わせ。',
		'features'  => array( 'お問い合わせフォーム', 'スマホ対応', 'SSL（暗号化）', 'Googleマップ' ),
		'questions' => array( '掲載する写真はありますか？', '料金表を載せますか？', '予約の受付方法は？' ),
		'idea'      => $idea,
		'pages'     => array(
			array( 'id' => 'home', 'name' => 'トップ', 'purpose' => '第一印象と主な導線', 'sections' => array(
				array( 't' => 'hero', 'title' => 'ようこそ', 'body' => '', 'items' => array() ),
				array( 't' => 'features', 'title' => '選ばれる理由', 'body' => '', 'items' => array( 'ていねいな対応｜はじめての方にもわかりやすくご案内します', '通いやすい場所｜駅から歩いてすぐです', '明朗な料金｜事前に料金をお伝えします' ) ),
				array( 't' => 'services', 'title' => 'サービス', 'body' => '', 'items' => array( 'サービスA｜内容の説明', 'サービスB｜内容の説明', 'サービスC｜内容の説明' ) ),
				array( 't' => 'news', 'title' => 'お知らせ', 'body' => '', 'items' => array( '新しいホームページを公開しました', '営業時間のお知らせ' ) ),
				array( 't' => 'cta', 'title' => 'お気軽にご相談ください', 'body' => 'ご予約・ご質問はお問い合わせフォームから受け付けています。', 'items' => array() ),
			) ),
			array( 'id' => 'service', 'name' => 'サービス', 'purpose' => 'サービス内容と料金', 'sections' => array(
				array( 't' => 'services', 'title' => 'サービス内容', 'body' => '', 'items' => array( 'サービスA｜内容の説明', 'サービスB｜内容の説明' ) ),
				array( 't' => 'price', 'title' => '料金', 'body' => '', 'items' => array( 'サービスA｜〇〇円〜', 'サービスB｜〇〇円〜' ) ),
				array( 't' => 'flow', 'title' => 'ご利用の流れ', 'body' => '', 'items' => array( 'お問い合わせ', 'ご相談', 'ご利用' ) ),
			) ),
			array( 'id' => 'about', 'name' => '会社概要', 'purpose' => '信頼してもらう', 'sections' => array(
				array( 't' => 'about', 'title' => '私たちについて', 'body' => '地域のみなさまに喜んでいただけるよう、日々努めています。', 'items' => array() ),
				array( 't' => 'company', 'title' => '会社・店舗情報', 'body' => '', 'items' => array( '名称｜' . $n, '所在地｜〒000-0000 〇〇県〇〇市', '営業時間｜10:00〜19:00' ) ),
				array( 't' => 'access', 'title' => 'アクセス', 'body' => '〇〇駅から徒歩5分', 'items' => array() ),
			) ),
			array( 'id' => 'contact', 'name' => 'お問い合わせ', 'purpose' => '問い合わせ・予約', 'sections' => array(
				array( 't' => 'contact', 'title' => 'お問い合わせ', 'body' => 'お気軽にお問い合わせください。2営業日以内にご連絡します。', 'items' => array( 'お名前', 'メールアドレス', 'お問い合わせ内容' ) ),
				array( 't' => 'faq', 'title' => 'よくある質問', 'body' => '', 'items' => array( '予約は必要ですか？｜予約なしでもご利用いただけます。', '駐車場はありますか？｜近くにコインパーキングがあります。' ) ),
			) ),
		),
	);
}

/** 公開サンプル（飲食店）。 */
function apprex_hp_sample() {
	return array(
		'name' => '炭火焼肉 はなび', 'industry' => '飲食店（焼肉）', 'catch' => '炭火で焼く、特別なひととき。', 'sub' => '駅から徒歩3分。全席個室で、ご家族の記念日や会社の宴会にもゆっくりお過ごしいただけます。',
		'color' => '#8a1c1c', 'tone' => 'luxury', 'cta' => 'ネットで予約する', 'target' => '30〜50代のご家族・会社員', 'goals' => array( 'ネット予約を増やす', '宴会・記念日の利用を増やす' ),
		'keywords' => array( '焼肉 個室', '焼肉 宴会', '記念日 ディナー' ), 'seo_desc' => '全席個室の炭火焼肉店。黒毛和牛のコース料理、宴会・記念日のご予約はネットから。駅から徒歩3分。',
		'features' => array( 'ネット予約', 'Googleマップ', 'メニューの写真', 'お問い合わせフォーム' ), 'questions' => array( '予約システムは今お使いのものがありますか？', '料理の写真はありますか？' ), 'idea' => '',
		'pages' => array(
			array( 'id' => 'home', 'name' => 'トップ', 'purpose' => '', 'sections' => array(
				array( 't' => 'hero', 'title' => '', 'body' => '', 'items' => array() ),
				array( 't' => 'features', 'title' => 'はなびが選ばれる理由', 'body' => '', 'items' => array( '全席個室｜周りを気にせず、会話と食事を楽しめます', '炭火焼き｜備長炭の遠赤外線で、お肉のうまみを閉じ込めます', '黒毛和牛｜目利きの店長が、その日いちばんのお肉を仕入れます' ) ),
				array( 't' => 'services', 'title' => 'おすすめのコース', 'body' => '', 'items' => array( 'はなびコース｜上タン・上カルビなど全10品', '記念日コース｜ケーキとメッセージプレートつき', '宴会コース｜2時間飲み放題つき・10名様から' ) ),
				array( 't' => 'voice', 'title' => 'お客様の声', 'body' => '', 'items' => array( '40代男性｜個室なので、取引先との会食に安心して使えました', '30代女性｜記念日のプレートに家族みんなが喜んでくれました' ) ),
				array( 't' => 'cta', 'title' => 'ご予約はネットで24時間', 'body' => 'ご希望の日時と人数を選ぶだけ。お電話よりかんたんです。', 'items' => array() ),
			) ),
			array( 'id' => 'menu', 'name' => 'メニュー', 'purpose' => '料理と料金', 'sections' => array(
				array( 't' => 'price', 'title' => 'コース', 'body' => '', 'items' => array( 'はなびコース｜5,500円', '記念日コース｜6,600円', '宴会コース（飲み放題つき）｜6,000円' ) ),
				array( 't' => 'price', 'title' => '単品', 'body' => '', 'items' => array( '上カルビ｜1,480円', 'ハラミ｜1,280円', '厚切りタン塩｜1,380円', '石焼ビビンバ｜980円' ) ),
				array( 't' => 'gallery', 'title' => '料理の写真', 'body' => '', 'items' => array( '上カルビ', '厚切りタン塩', '記念日プレート', '個室' ) ),
			) ),
			array( 'id' => 'about', 'name' => '店舗情報', 'purpose' => '場所と営業時間', 'sections' => array(
				array( 't' => 'company', 'title' => '店舗情報', 'body' => '', 'items' => array( '店名｜炭火焼肉 はなび', '営業時間｜17:00〜23:00（L.O. 22:30）', '定休日｜毎週火曜日', '席数｜個室8部屋（2〜20名様）' ) ),
				array( 't' => 'access', 'title' => 'アクセス', 'body' => '', 'items' => array( '駅南口から徒歩3分', '近くにコインパーキングがあります' ) ),
			) ),
			array( 'id' => 'reserve', 'name' => 'ご予約', 'purpose' => 'ネット予約・お問い合わせ', 'sections' => array(
				array( 't' => 'contact', 'title' => 'ネット予約', 'body' => 'ご希望の日時・人数・コースをお選びください。確認のメールをお送りします。', 'items' => array( 'ご希望の日時', '人数', 'コース', 'お名前', 'メールアドレス' ) ),
				array( 't' => 'faq', 'title' => 'よくある質問', 'body' => '', 'items' => array( '子ども連れでも大丈夫ですか？｜はい。お子様用のいすと取り皿をご用意しています。', 'アレルギーに対応できますか？｜ご予約のときにお知らせいただければ、できるかぎり対応します。' ) ),
			) ),
		),
	);
}

/** 会員のHP試作。 */
function apprex_hp_get( $uid ) {
	return apprex_hp_sanitize( json_decode( (string) get_user_meta( $uid, 'apprex_hp_spec', true ), true ) );
}

/** 試作のURL。 */
function apprex_hp_demo_url( $uid ) {
	return apprex_demo_url( apprex_demo_token( $uid ), array( 'v' => 'hp' ) );
}

/** メインビジュアルの写真。 */
function apprex_hp_image_url( $uid ) {
	$id = (int) get_user_meta( $uid, 'apprex_hp_image', true );
	return $id ? (string) wp_get_attachment_url( $id ) : '';
}

/* -------------------------------------------------------------------------
 * REST
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	$member = function () {
		return is_user_logged_in();
	};
	register_rest_route( 'apprex/v1', '/studio/ai-hp', array( 'methods' => 'POST', 'callback' => 'apprex_hp_rest', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/hp-image', array( 'methods' => 'POST', 'callback' => 'apprex_hp_image_rest', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/hp-design', array( 'methods' => 'POST', 'callback' => 'apprex_hp_design_rest', 'permission_callback' => $member ) );
} );

/** 1日の回数（使った回数を1増やし、上限なら false）。 */
function apprex_hp_count( $uid, $key, $limit ) {
	$use = get_user_meta( $uid, $key, true );
	$n   = ( is_array( $use ) && isset( $use['d'] ) && wp_date( 'Ymd' ) === $use['d'] ) ? (int) $use['n'] : 0;
	if ( $n >= $limit ) {
		return false;
	}
	update_user_meta( $uid, $key, array( 'd' => wp_date( 'Ymd' ), 'n' => $n + 1 ) );
	return $limit - $n - 1;
}

/** AIでホームページを作る／作り直す。 */
function apprex_hp_rest( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$uid      = get_current_user_id();
	$idea     = mb_substr( sanitize_textarea_field( (string) $request->get_param( 'idea' ) ), 0, 1000 );
	$name     = mb_substr( sanitize_text_field( (string) $request->get_param( 'name' ) ), 0, 30 );
	$industry = mb_substr( sanitize_text_field( (string) $request->get_param( 'industry' ) ), 0, 20 );
	$plan     = sanitize_key( (string) $request->get_param( 'plan' ) );
	$plans    = apprex_hp_plans();
	$plan     = isset( $plans[ $plan ] ) ? $plan : 'standard';
	$instr    = mb_substr( sanitize_textarea_field( (string) $request->get_param( 'instruction' ) ), 0, 500 );
	$cur      = $instr ? apprex_hp_get( $uid ) : null;
	if ( '' === $idea && ! $cur ) {
		return new WP_Error( 'bad_request', '作りたいホームページの内容を書いてください。', array( 'status' => 400 ) );
	}
	if ( false === apprex_hp_count( $uid, 'apprex_hp_usage', (int) apply_filters( 'apprex_hp_daily_limit', 20 ) ) ) {
		return new WP_Error( 'limit', '本日の作成回数の上限に達しました。明日またお試しください。', array( 'status' => 429 ) );
	}
	$max    = $plans[ $plan ][2];
	$spec   = null;
	$source = 'template';
	if ( apprex_ai_text_ready() ) {
		$types  = implode( '|', array_keys( apprex_hp_section_types() ) );
		$schema = '{"name":"サイト名・店名","industry":"業種","catch":"トップの大見出し（24字以内）","sub":"サブコピー（60字以内）","color":"#RRGGBB（業種と雰囲気に合うメインカラー）","tone":"trust|luxury|natural|pop|sporty","cta":"一番押してほしいボタンの文字（例：予約する）",'
			. '"target":"想定するお客様","goals":["ホームページの目的（2〜3個）"],"keywords":["検索で見つけてほしい言葉（3〜5個）"],"seo_desc":"検索結果に出る説明文（100字以内）","features":["必要な機能（フォーム・予約・地図など）"],'
			. '"pages":[{"id":"英小文字","name":"ページ名（8字以内）","purpose":"ページの目的","sections":[{"t":"' . $types . '","title":"見出し","body":"本文（自然な日本語で具体的に）","items":["項目。見出し｜説明 の形（料金は 名前｜金額、FAQは 質問｜答え、会社情報は 項目｜内容）"]}]}],'
			. '"questions":["制作前にお客様に確認すべきこと"]}';
		$system = "あなたは日本のホームページ制作会社のディレクター兼コピーライターです。お客様の希望から、中小企業・店舗のホームページの構成と文章を作ります。\n"
			. "目的は、お客様にイメージを早く共有して制作の相談を進めることです。\n"
			. "ルール：ページは{$max}ページ以内（3〜6ページが目安）。1ページ目は id=home のトップ。各ページのセクションは2〜6個。最後のページはお問い合わせ。"
			. "文章はその業種らしい自然な日本語で、具体的に書く。ただし、実在しない受賞歴・実績の数字・お客様の実名は作らない（お客様の声は「30代女性」のように書く）。料金がわからないときは「〇〇円〜」とする。"
			. "誇大な表現（No.1、絶対など）や医療・法律の断定はしない。\n必ずJSONだけを出力（説明やコードブロックは不要）。形式：\n" . $schema;
		$user = $cur
			? '今のホームページ：' . wp_json_encode( $cur, JSON_UNESCAPED_UNICODE ) . "\n\n修正の指示：" . $instr . "\n（指示に関係しない部分はなるべく変えない）"
			: 'お客様の希望：' . $idea . ( $name ? "\n店名・会社名：" . $name : '' ) . ( $industry ? "\n業種：" . $industry : '' ) . "\nプラン：" . $plans[ $plan ][0] . "（" . $plans[ $plan ][3] . '）';
		$res = apprex_studio_complete(
			array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $user ) ),
			array( 'temperature' => 0.6, 'max_tokens' => 4000, 'timeout' => 90 )
		);
		if ( ! is_wp_error( $res ) ) {
			$spec = apprex_hp_sanitize( apprex_studio_json( $res ) );
			if ( $spec ) {
				$source = 'ai';
			}
		}
	}
	if ( ! $spec ) {
		if ( $cur ) {
			$spec                = $cur;
			$spec['questions'][] = 'ご要望：' . mb_substr( $instr, 0, 50 );
		} else {
			$spec = apprex_hp_sanitize( apprex_hp_template( $idea, $name, $industry ) );
		}
	}
	$spec['idea'] = $cur ? $cur['idea'] : $idea;
	// 作り直しても、選んだデザインはそのまま。新しく作ったときは雰囲気に合うデザインから。
	$spec['design'] = $cur ? $cur['design'] : apprex_hp_design_default( $spec['tone'] );
	$spec['plan'] = $cur && isset( $cur['plan'] ) ? $cur['plan'] : $plan;
	if ( $name && ! $cur ) {
		$spec['name'] = $name;
	}
	$spec['pages'] = array_slice( $spec['pages'], 0, $max );
	update_user_meta( $uid, 'apprex_hp_spec', wp_slash( wp_json_encode( $spec, JSON_UNESCAPED_UNICODE ) ) );
	return rest_ensure_response( array( 'ok' => true, 'spec' => $spec, 'source' => $source, 'demo' => apprex_hp_demo_url( $uid ) ) );
}

/** デザイン（案・色・文字・写真の雰囲気）を保存する。 */
function apprex_hp_design_rest( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$uid  = get_current_user_id();
	$spec = apprex_hp_get( $uid );
	if ( ! $spec ) {
		return new WP_Error( 'bad_request', '先にホームページの試作を作ってください。', array( 'status' => 400 ) );
	}
	$in = array();
	foreach ( array( 'style', 'palette', 'font', 'photo' ) as $k ) {
		$v = sanitize_key( (string) $request->get_param( $k ) );
		if ( '' !== $v ) {
			$in[ $k ] = 'style' === $k ? strtoupper( $v ) : $v;
		}
	}
	$spec['design'] = apprex_hp_sanitize_design( array_merge( $spec['design'], $in ), $spec['tone'] );
	update_user_meta( $uid, 'apprex_hp_spec', wp_slash( wp_json_encode( $spec, JSON_UNESCAPED_UNICODE ) ) );
	return rest_ensure_response( array( 'ok' => true, 'design' => $spec['design'] ) );
}

/** メインビジュアルの写真をAIで作る。 */
function apprex_hp_image_rest( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$uid  = get_current_user_id();
	$spec = apprex_hp_get( $uid );
	if ( ! $spec ) {
		return new WP_Error( 'bad_request', '先にホームページの試作を作ってください。', array( 'status' => 400 ) );
	}
	if ( '' === apprex_openrouter_key() ) {
		return new WP_Error( 'not_configured', '写真の生成は準備中です。', array( 'status' => 503 ) );
	}
	if ( false === apprex_hp_count( $uid, 'apprex_hp_img_usage', (int) apply_filters( 'apprex_hp_image_daily_limit', 6 ) ) ) {
		return new WP_Error( 'limit', '本日の写真の生成回数の上限に達しました。明日またお試しください。', array( 'status' => 429 ) );
	}
	$dz     = apprex_hp_design( $spec );
	$ops    = apprex_hp_design_options();
	$prompt = sprintf( 'Photorealistic hero image for the top of a Japanese small business website. Business: %1$s (%2$s). Mood: %3$s. Main color accent %4$s. Landscape 16:9, wide composition with calm space on the left for a headline, natural light, realistic Japanese setting. Absolutely no text, letters, numbers, logos or watermarks.', $spec['name'], $spec['industry'] ? $spec['industry'] : $spec['catch'], $ops['photos'][ $dz['photo'] ][1], $dz['c'] );
	$img    = apprex_openrouter_image( $prompt );
	if ( is_wp_error( $img ) ) {
		return new WP_Error( 'failed', '写真を作れませんでした。少し時間をおいてお試しください。', array( 'status' => 502 ) );
	}
	$att = apprex_save_generated_image( $img, 'AIスタジオ-' . $uid . '-HP' );
	if ( is_wp_error( $att ) ) {
		return new WP_Error( 'failed', '写真を保存できませんでした。', array( 'status' => 500 ) );
	}
	update_post_meta( $att, '_apprex_studio_user', $uid );
	$old = (int) get_user_meta( $uid, 'apprex_hp_image', true );
	if ( $old ) {
		wp_delete_attachment( $old, true );
	}
	update_user_meta( $uid, 'apprex_hp_image', $att );
	return rest_ensure_response( array( 'ok' => true, 'image' => apprex_hp_image_url( $uid ) ) );
}

/* -------------------------------------------------------------------------
 * 試作の表示（/app-demo/{トークン}/?v=hp）
 * ---------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	$token = get_query_var( 'apprex_demo' );
	if ( ! $token || ! isset( $_GET['v'] ) || 'hp' !== $_GET['v'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( function_exists( 'apprex_rn_public' ) && ! apprex_rn_public() && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}
	$user = apprex_demo_user( sanitize_key( $token ) );
	$spec = $user ? apprex_hp_get( $user->ID ) : null;
	if ( 'sample' === $token ) {
		$spec = apprex_hp_sanitize( apprex_hp_sample() );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	if ( ! $spec ) {
		status_header( 404 );
		echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width"><p style="font-family:sans-serif;padding:24px">ホームページの試作が見つかりませんでした。</p>';
		exit;
	}
	$page = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	// デザインを試しに見るときの上書き（保存はしない）。&d=A|B|C&pal=色&font=文字&photo=写真&thumb=1
	$try = array();
	foreach ( array( 'd' => 'style', 'pal' => 'palette', 'font' => 'font', 'photo' => 'photo' ) as $q => $k ) {
		if ( isset( $_GET[ $q ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$v         = sanitize_key( wp_unslash( $_GET[ $q ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$try[ $k ] = 'style' === $k ? strtoupper( $v ) : $v;
		}
	}
	$thumb = ! empty( $_GET['thumb'] ); // phpcs:ignore WordPress.Security.NonceVerification
	apprex_hp_render( $spec, $page, $user ? apprex_hp_image_url( $user->ID ) : '', apprex_demo_url( sanitize_key( $token ), array( 'v' => 'hp' ) ), $try, $thumb );
	exit;
}, 0 );

/**
 * 試作のHTMLを出す。
 *
 * デザイン案 A（スタンダード）・B（エレガント）・C（インパクト）を body のクラスで切り替え、
 * 色・文字・写真の雰囲気は CSS の変数で切り替える。
 *
 * @param array  $spec  試作。
 * @param string $pid   表示するページID。
 * @param string $img   メインビジュアルの写真。
 * @param string $base  試作のURL。
 * @param array  $try   試しに見るデザイン（保存はしない）。
 * @param bool   $thumb 一覧の小さな見本として出す（試作の帯を出さない）。
 */
function apprex_hp_render( $spec, $pid, $img, $base, $try = array(), $thumb = false ) {
	$pages = $spec['pages'];
	$cur   = $pages[0];
	foreach ( $pages as $p ) {
		if ( $p['id'] === $pid ) {
			$cur = $p;
		}
	}
	$d     = apprex_hp_design( $spec, $try );
	$ff    = $d['ff'];
	$split = function ( $s ) {
		$p = explode( '｜', $s, 2 );
		return array( $p[0], isset( $p[1] ) ? $p[1] : '' );
	};
	// ページを移っても、試しているデザインはそのまま。
	$keep = array_filter(
		array(
			'd'     => isset( $try['style'] ) ? $try['style'] : '',
			'pal'   => isset( $try['palette'] ) ? $try['palette'] : '',
			'font'  => isset( $try['font'] ) ? $try['font'] : '',
			'photo' => isset( $try['photo'] ) ? $try['photo'] : '',
			'thumb' => $thumb ? '1' : '',
		)
	);
	$url  = function ( $id ) use ( $base, $keep ) {
		return add_query_arg( array_merge( $keep, array( 'p' => $id ) ), $base );
	};
	$last = end( $pages );
	$eb   = array(
		'about'    => 'ABOUT',
		'features' => 'FEATURES',
		'services' => 'SERVICE',
		'price'    => 'PRICE',
		'flow'     => 'FLOW',
		'voice'    => 'VOICE',
		'staff'    => 'STAFF',
		'gallery'  => 'GALLERY',
		'news'     => 'NEWS',
		'faq'      => 'FAQ',
		'company'  => 'COMPANY',
		'access'   => 'ACCESS',
		'contact'  => 'CONTACT',
		'cta'      => 'CONTACT',
	);
	?>
<!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $cur['name'] . '｜' . $spec['name'] ); ?>（ホームページの試作）</title>
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;900&family=Zen+Old+Mincho:wght@500;700&family=Zen+Maru+Gothic:wght@500;700&family=M+PLUS+Rounded+1c:wght@500;700;800&family=Zen+Kaku+Gothic+New:wght@500;700;900&family=Cormorant+Garamond:wght@500;600&family=Montserrat:wght@700;800&display=swap">
<style>
:root{--c:<?php echo esc_html( $d['c'] ); ?>;--a:<?php echo esc_html( $d['a'] ); ?>;--at:<?php echo esc_html( $d['at'] ); ?>;--ff:<?php echo $ff; // phpcs:ignore ?>;--ink:#1d2433;--body:#4b5563;--line:#e7e9ef;--soft:#f6f7fb;--pf:<?php echo esc_html( $d['filter'] ); ?>}
*{box-sizing:border-box}body{margin:0;font-family:"Noto Sans JP",sans-serif;color:var(--ink);line-height:1.85;background:#fff}
a{color:inherit}img{max-width:100%;display:block}
.demo-bar{background:#111827;color:#e5e7eb;font-size:12px;text-align:center;padding:6px 10px}.demo-bar b{color:#fbbf24}
.hd{position:sticky;top:0;z-index:10;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
.thumb .hd{position:relative}
.wrap{max-width:1080px;margin:0 auto;padding:0 20px}
.hd .wrap{display:flex;align-items:center;gap:20px;min-height:64px}
.logo{font-family:var(--ff);font-weight:900;font-size:20px;color:var(--c);text-decoration:none;white-space:nowrap}
.nav{display:flex;gap:18px;margin-left:auto;font-size:14px;font-weight:700;overflow-x:auto}
.nav a{text-decoration:none;white-space:nowrap;padding:4px 0;border-bottom:2px solid transparent}
.nav a.on{border-color:var(--c);color:var(--c)}
.hbtn{background:var(--c);color:#fff;text-decoration:none;border-radius:999px;padding:9px 18px;font-size:13px;font-weight:700;white-space:nowrap}
@media(max-width:760px){.hd .wrap{flex-wrap:wrap;gap:6px 12px;padding-block:10px}.nav{order:3;width:100%;margin:0;gap:14px}.hbtn{margin-left:auto}}
h1,h2,h3{font-family:var(--ff);line-height:1.4;margin:0}
.btn{display:inline-block;background:#fff;color:var(--c);text-decoration:none;font-weight:800;border-radius:999px;padding:14px 30px}
.btn.solid{background:var(--c);color:#fff}
/* メインビジュアル */
.hero{position:relative;color:#fff;overflow:hidden;background:linear-gradient(120deg,color-mix(in srgb,var(--c) 92%,#000) 0%,color-mix(in srgb,var(--c) 62%,#000) 100%)}
.hero .bg{position:absolute;inset:0;background-size:cover;background-position:center;filter:var(--pf);opacity:.42}
.hero-in{position:relative;padding-block:clamp(70px,12vw,130px)}
.hero h1{font-size:clamp(28px,5.4vw,50px);font-weight:900;max-width:16em}
.hero p{font-size:clamp(15px,2.2vw,18px);max-width:34em;opacity:.92;margin:18px 0 28px}
.hero-tag{display:none}.hero-vis{display:none}
.deco{position:relative;width:100%;height:100%;min-height:280px;border-radius:24px;overflow:hidden;background:linear-gradient(140deg,color-mix(in srgb,var(--c) 70%,#fff),color-mix(in srgb,var(--a) 60%,#fff))}
.deco:before,.deco:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.35)}
.deco:before{width:60%;aspect-ratio:1;right:-12%;top:-18%}.deco:after{width:38%;aspect-ratio:1;left:8%;bottom:-10%;background:rgba(255,255,255,.22)}
.deco span{position:absolute;left:18px;bottom:14px;font-size:12px;font-weight:700;color:#fff;opacity:.9}
/* セクション */
.ph{background:var(--soft);border-bottom:1px solid var(--line)}.ph .wrap{padding-block:46px}.ph h1{font-size:clamp(24px,4vw,34px);color:var(--c)}.ph p{margin:6px 0 0;color:var(--body)}
.sec{padding-block:clamp(48px,8vw,84px)}.sec:nth-of-type(even){background:var(--soft)}
.sec h2{font-size:clamp(22px,3.4vw,30px);text-align:center;margin-bottom:10px}
.sec h2 .eb{display:none}
.sec h2:after{content:"";display:block;width:40px;height:3px;background:var(--c);border-radius:3px;margin:14px auto 0}
.lead{text-align:center;color:var(--body);max-width:40em;margin:0 auto 28px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:16px}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px}
.card h3{font-size:17px;margin-bottom:6px;color:var(--c)}.card p{margin:0;color:var(--body);font-size:14.5px}
.card .n{display:inline-flex;width:34px;height:34px;border-radius:50%;background:var(--c);color:#fff;align-items:center;justify-content:center;font-weight:800;margin-bottom:10px}
.price{max-width:640px;margin:0 auto;border-top:1px solid var(--line)}.price div{display:flex;justify-content:space-between;gap:12px;padding:14px 4px;border-bottom:1px solid var(--line)}.price b{color:var(--c);white-space:nowrap}
.faq{max-width:760px;margin:0 auto}.faq details{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 18px;margin-bottom:10px}.faq summary{font-weight:700;cursor:pointer}.faq summary:before{content:"Q. ";color:var(--c)}.faq p{margin:8px 0 0;color:var(--body)}
.gal{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}.gal div{aspect-ratio:4/3;border-radius:12px;background:linear-gradient(135deg,color-mix(in srgb,var(--c) 30%,#fff),color-mix(in srgb,var(--a) 45%,#fff));display:flex;align-items:flex-end;padding:10px;font-size:12px;font-weight:700;color:#fff;filter:var(--pf)}
.tbl{max-width:720px;margin:0 auto;border-collapse:collapse;width:100%;background:#fff}.tbl th,.tbl td{border:1px solid var(--line);padding:12px 14px;text-align:left;font-size:14.5px}.tbl th{background:var(--soft);width:32%;white-space:nowrap}
.map{max-width:720px;margin:16px auto 0;aspect-ratio:16/7;border-radius:14px;background:repeating-linear-gradient(45deg,#eef0f5 0 12px,#e6e9f0 12px 24px);display:flex;align-items:center;justify-content:center;color:#6b7280;font-weight:700}
.form{max-width:620px;margin:0 auto;display:grid;gap:12px}.form label{display:grid;gap:4px;font-size:14px;font-weight:700}.form span{border:1.5px solid var(--line);border-radius:10px;padding:12px;background:#fff;color:#9ca3af;font-weight:400}
.voice .card p:before{content:"“";color:var(--c);font-size:28px;line-height:0;vertical-align:-10px;margin-right:2px}
.news{max-width:760px;margin:0 auto}.news div{display:flex;gap:16px;padding:14px 4px;border-bottom:1px solid var(--line)}.news time{color:var(--c);font-weight:700;white-space:nowrap}
.cta{background:var(--c)!important;color:#fff;text-align:center}.cta h2{color:#fff}.cta h2:after{background:#fff}.cta .lead{color:rgba(255,255,255,.9)}
.ft{background:#111827;color:#9ca3af;font-size:13px}.ft .wrap{padding-block:36px;display:flex;flex-wrap:wrap;gap:12px 24px;justify-content:space-between}.ft a{text-decoration:none;margin-right:14px}

/* ===== B：エレガント（写真と文字を左右に・雑誌風） ===== */
.st-B{--soft:#faf8f4}
.st-B .hd{background:rgba(255,255,255,.97)}
.st-B .hbtn{border-radius:2px;background:transparent;color:var(--c);border:1px solid var(--c)}
.st-B .hero{background:var(--soft);color:var(--ink)}
.st-B .hero .bg{display:none}
.st-B .hero-in{display:grid;grid-template-columns:1.05fr 1fr;gap:clamp(24px,5vw,56px);align-items:center;padding-block:clamp(48px,8vw,90px)}
.st-B .hero-tag{display:block;font-family:"Cormorant Garamond",serif;font-size:15px;letter-spacing:.3em;color:var(--a);text-transform:uppercase;margin-bottom:14px}
.st-B .hero h1{font-weight:700;color:var(--ink);letter-spacing:.04em}
.st-B .hero p{color:var(--body)}
.st-B .hero .btn{background:var(--c);color:#fff;border-radius:2px;letter-spacing:.1em}
.st-B .hero-vis{display:block;aspect-ratio:4/5;max-height:520px}
.st-B .hero-vis img{width:100%;height:100%;object-fit:cover;border-radius:2px;filter:var(--pf);box-shadow:24px 24px 0 color-mix(in srgb,var(--a) 35%,transparent)}
.st-B .hero-vis .deco{border-radius:2px;box-shadow:24px 24px 0 color-mix(in srgb,var(--a) 35%,transparent)}
@media(max-width:760px){.st-B .hero-in{grid-template-columns:1fr}.st-B .hero-vis{aspect-ratio:16/10}}
.st-B .sec h2{text-align:left;font-weight:600;letter-spacing:.06em;max-width:1040px;margin-inline:auto}
.st-B .sec h2 .eb{display:block;font-family:"Cormorant Garamond",serif;font-size:14px;letter-spacing:.3em;color:var(--a);margin-bottom:6px;font-weight:600}
.st-B .sec h2:after{width:100%;height:1px;background:var(--line);margin:18px 0 0}
.st-B .lead{text-align:left;margin:18px 0 30px}
.st-B .card{border:0;border-top:2px solid var(--c);border-radius:0;background:#fff;padding:24px 4px 6px}
.st-B .sec:nth-of-type(even) .card{background:transparent}
.st-B .card h3{color:var(--ink);font-weight:600}
.st-B .card .n{border-radius:0;background:transparent;color:var(--a);font-family:"Cormorant Garamond",serif;font-size:30px;width:auto;height:auto;justify-content:flex-start}
.st-B .price,.st-B .faq,.st-B .news,.st-B .tbl,.st-B .form{max-width:none}
.st-B .faq details{border-radius:0;border-width:0 0 1px;background:transparent;padding-inline:4px}
.st-B .gal div{border-radius:2px}
.st-B .btn{border-radius:2px}
.st-B .cta{background:color-mix(in srgb,var(--c) 85%,#000)!important}.st-B .cta h2{text-align:center}.st-B .cta h2 .eb{color:var(--a)}.st-B .cta h2:after{display:none}.st-B .cta .lead{text-align:center;margin-inline:auto}

/* ===== C：インパクト（大きな写真と太い文字） ===== */
.st-C .hd{background:#fff}
.st-C .hbtn{background:var(--a);color:var(--at)}
.st-C .hero{min-height:min(78vh,640px);display:flex;align-items:center;background:linear-gradient(115deg,color-mix(in srgb,var(--c) 96%,#000) 0%,color-mix(in srgb,var(--c) 70%,#000) 55%,color-mix(in srgb,var(--a) 70%,#000) 100%)}
.st-C .hero .bg{opacity:.55}
.st-C .hero:after{content:"";position:absolute;right:-8%;bottom:-30%;width:55%;aspect-ratio:1;border-radius:50%;background:var(--a);opacity:.25}
.st-C .hero-in{z-index:1;width:100%}
.st-C .hero-tag{display:inline-block;background:var(--a);color:var(--at);font-weight:800;font-size:13px;border-radius:999px;padding:4px 14px;margin-bottom:16px}
.st-C .hero h1{font-size:clamp(34px,7vw,68px);font-weight:900;letter-spacing:-.01em;line-height:1.2;text-shadow:0 4px 24px rgba(0,0,0,.25)}
.st-C .hero .btn{background:var(--a);color:var(--at);padding:16px 34px;box-shadow:0 10px 24px rgba(0,0,0,.25)}
.st-C .sec:nth-of-type(even){background:color-mix(in srgb,var(--c) 6%,#fff)}
.st-C .sec h2{font-size:clamp(26px,4.2vw,40px);font-weight:900;position:relative}
.st-C .sec h2 .eb{display:block;font-family:Montserrat,sans-serif;font-weight:800;font-size:clamp(34px,7vw,64px);line-height:1;letter-spacing:.02em;color:color-mix(in srgb,var(--c) 12%,transparent);margin-bottom:-.45em}
.st-C .sec h2:after{width:56px;height:6px;background:var(--a)}
.st-C .card{border:0;border-radius:20px;box-shadow:0 12px 32px rgba(17,24,39,.08);border-top:6px solid var(--c)}
.st-C .card .n{background:var(--a);color:var(--at)}
.st-C .price b,.st-C .news time{color:var(--c)}
.st-C .gal div{border-radius:18px}
.st-C .cta{background:linear-gradient(120deg,var(--c),color-mix(in srgb,var(--c) 55%,var(--a)))!important}
.st-C .cta h2 .eb{color:rgba(255,255,255,.18)}.st-C .cta .btn{background:var(--a);color:var(--at)}
</style></head><body class="st-<?php echo esc_attr( $d['style'] ); ?><?php echo $thumb ? ' thumb' : ''; ?>">
<?php if ( ! $thumb ) : ?>
<div class="demo-bar"><b>AIが作ったホームページの試作</b>（イメージです。実際の内容・デザインは制作時に決めます）</div>
<?php endif; ?>
<header class="hd"><div class="wrap"><a class="logo" href="<?php echo esc_url( $url( $pages[0]['id'] ) ); ?>"><?php echo esc_html( $spec['name'] ); ?></a>
<nav class="nav"><?php foreach ( $pages as $p ) : ?><a href="<?php echo esc_url( $url( $p['id'] ) ); ?>"<?php echo $p['id'] === $cur['id'] ? ' class="on"' : ''; ?>><?php echo esc_html( $p['name'] ); ?></a><?php endforeach; ?></nav>
<a class="hbtn" href="<?php echo esc_url( $url( $last['id'] ) ); ?>"><?php echo esc_html( $spec['cta'] ); ?></a></div></header>
<main>
<?php if ( $cur['id'] !== $pages[0]['id'] ) : ?>
<section class="ph"><div class="wrap"><h1><?php echo esc_html( $cur['name'] ); ?></h1><?php if ( $cur['purpose'] ) : ?><p><?php echo esc_html( $cur['purpose'] ); ?></p><?php endif; ?></div></section>
<?php endif; ?>
<?php
foreach ( $cur['sections'] as $i => $s ) :
	$t = $s['t'];
	if ( 'hero' === $t ) :
		?>
<section class="hero"><?php if ( $img ) : ?><div class="bg" style="background-image:url(<?php echo esc_url( $img ); ?>)"></div><?php endif; ?><div class="wrap hero-in"><div class="hero-txt"><span class="hero-tag"><?php echo esc_html( $spec['industry'] ? $spec['industry'] : $spec['name'] ); ?></span><h1><?php echo esc_html( $spec['catch'] ? $spec['catch'] : $s['title'] ); ?></h1><p><?php echo esc_html( $spec['sub'] ? $spec['sub'] : $s['body'] ); ?></p><a class="btn" href="<?php echo esc_url( $url( $last['id'] ) ); ?>"><?php echo esc_html( $spec['cta'] ); ?></a></div>
<div class="hero-vis"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php else : ?><div class="deco"><span>写真が入ります</span></div><?php endif; ?></div></div></section>
		<?php
		continue;
	endif;
	?>
<section class="sec<?php echo 'cta' === $t ? ' cta' : ( 'voice' === $t ? ' voice' : '' ); ?>"><div class="wrap">
<h2><span class="eb"><?php echo esc_html( isset( $eb[ $t ] ) ? $eb[ $t ] : '' ); ?></span><?php echo esc_html( $s['title'] ); ?></h2>
	<?php if ( $s['body'] ) : ?><p class="lead"><?php echo nl2br( esc_html( $s['body'] ) ); ?></p><?php endif; ?>
	<?php
	switch ( $t ) {
		case 'price':
			echo '<div class="price">';
			foreach ( $s['items'] as $it ) {
				list( $a, $b ) = $split( $it );
				echo '<div><span>' . esc_html( $a ) . '</span><b>' . esc_html( $b ) . '</b></div>';
			}
			echo '</div>';
			break;
		case 'faq':
			echo '<div class="faq">';
			foreach ( $s['items'] as $it ) {
				list( $a, $b ) = $split( $it );
				echo '<details><summary>' . esc_html( $a ) . '</summary><p>' . esc_html( $b ) . '</p></details>';
			}
			echo '</div>';
			break;
		case 'gallery':
			echo '<div class="gal">';
			foreach ( $s['items'] ? $s['items'] : array( '写真1', '写真2', '写真3', '写真4' ) as $it ) {
				echo '<div>' . esc_html( $split( $it )[0] ) . '</div>';
			}
			echo '</div>';
			break;
		case 'company':
			echo '<table class="tbl"><tbody>';
			foreach ( $s['items'] as $it ) {
				list( $a, $b ) = $split( $it );
				echo '<tr><th>' . esc_html( $a ) . '</th><td>' . esc_html( $b ) . '</td></tr>';
			}
			echo '</tbody></table>';
			break;
		case 'access':
			foreach ( $s['items'] as $it ) {
				echo '<p class="lead" style="margin-bottom:6px">' . esc_html( str_replace( '｜', '：', $it ) ) . '</p>';
			}
			echo '<div class="map">Googleマップ</div>';
			break;
		case 'contact':
			echo '<div class="form">';
			foreach ( $s['items'] ? $s['items'] : array( 'お名前', 'メールアドレス', 'お問い合わせ内容' ) as $it ) {
				echo '<label>' . esc_html( $split( $it )[0] ) . '<span>入力欄</span></label>';
			}
			echo '<p style="text-align:center;margin:8px 0 0"><span class="btn solid">送信する</span></p></div>';
			break;
		case 'news':
			echo '<div class="news">';
			foreach ( $s['items'] as $k => $it ) {
				echo '<div><time>' . esc_html( wp_date( 'Y.m.d', strtotime( '-' . ( $k * 9 ) . ' days' ) ) ) . '</time><span>' . esc_html( $split( $it )[0] ) . '</span></div>';
			}
			echo '</div>';
			break;
		case 'cta':
			echo '<p style="text-align:center"><a class="btn" href="' . esc_url( $url( $last['id'] ) ) . '">' . esc_html( $spec['cta'] ) . '</a></p>';
			break;
		case 'about':
			break;
		default:
			if ( $s['items'] ) {
				echo '<div class="grid">';
				foreach ( $s['items'] as $k => $it ) {
					list( $a, $b ) = $split( $it );
					echo '<div class="card">' . ( 'flow' === $t ? '<span class="n">' . ( $k + 1 ) . '</span>' : '' ) . ( $b || 'voice' !== $t ? '<h3>' . esc_html( $a ) . '</h3>' : '' ) . ( $b ? '<p>' . esc_html( $b ) . '</p>' : ( 'voice' === $t ? '<p>' . esc_html( $a ) . '</p>' : '' ) ) . '</div>';
				}
				echo '</div>';
			}
	}
	?>
</div></section>
<?php endforeach; ?>
</main>
<footer class="ft"><div class="wrap"><div><?php foreach ( $pages as $p ) : ?><a href="<?php echo esc_url( $url( $p['id'] ) ); ?>"><?php echo esc_html( $p['name'] ); ?></a><?php endforeach; ?></div><div>&copy; <?php echo esc_html( $spec['name'] ); ?></div></div></footer>
</body></html>
	<?php
}
