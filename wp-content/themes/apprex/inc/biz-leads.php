<?php
/**
 * AI営業リスト（Googleマップのお店から、アプリを作れそうな見込み先をジャンル別に集める）。
 *
 * 1) 探す：ジャンル（アプリと相性のよい業種）と地域で、Google Places API（公式）を検索する。
 * 2) 保存：Google の Place ID と、自社で調べた情報・営業の記録だけをずっと残す。
 *    店名・住所・電話・口コミなど Google の情報は一時的に（最長30日）持ち、古くなったら読み直す（Google の規約に合わせるため）。
 * 3) 調べる：お店のホームページ（公開されているもの）を読み、公開メールアドレス・「営業お断り」・アプリやLINEの有無を確かめ、
 *    AIが「アプリを提案するきっかけ」と点数をつける。
 * 4) 管理：状態（未対応・調査済み・連絡した・返信あり・打ち合わせ・成約・失注・対象外）と、いつ何をしたかの記録・メモ。
 *
 * 画面：業務ナビ → AI営業リスト。設定：同じ画面の下（Google の APIキー・月の上限）。
 * メールの作成・送信・追いかけは、別の機能で足す予定（ここの記録に「いつ送ったか」を残す）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ジャンル・状態
 * ====================================================================== */

/** アプリと相性のよいジャンル。key => [名前, 検索語, 基本点, なぜアプリが合うか, まとまり]。 */
function apprex_ld_genres() {
	$B = '美容・リラク';
	$H = '健康・医療・ペット';
	$S = 'スポーツ・フィットネス';
	$L = '習い事・教育';
	$F = '飲食';
	$R = 'お店・小売';
	$V = '暮らし・サービス';
	$C = '会社・その他';
	$g = array(
		// 美容・リラク
		'hair'      => array( '美容室', '美容室', 85, '来店の間隔が決まっていて、予約・次回の案内・ポイントでリピートを増やしやすい', $B ),
		'barber'    => array( '理容室・バーバー', 'バーバー', 80, '来店の間隔が短く、予約とポイントで常連を増やしやすい', $B ),
		'nail'      => array( 'ネイルサロン', 'ネイルサロン', 80, '予約とデザインの見せ方、次回の来店の案内がアプリに向く', $B ),
		'eyelash'   => array( 'まつげ・アイブロウ', 'まつげエクステ', 80, 'リペアの時期のお知らせと予約でリピートを増やせる', $B ),
		'esthe'     => array( 'エステ', 'エステサロン', 80, '回数券・コース・予約の管理と、会員向けのお知らせが向く', $B ),
		'datsumo'   => array( '脱毛サロン', '脱毛サロン', 80, '通う回数が多く、予約・回数の管理・次回の案内が向く', $B ),
		'headspa'   => array( 'ヘッドスパ', 'ヘッドスパ', 75, '予約・回数券・会員特典でリピートを増やせる', $B ),
		'relax'     => array( 'リラク・マッサージ', 'マッサージ', 80, '予約とクーポン、ポイントで常連を増やしやすい', $B ),
		'sauna'     => array( 'サウナ・銭湯・スパ', 'サウナ', 70, '会員証・回数券・混雑状況のお知らせが向く', $B ),
		// 健康・医療・ペット
		'seitai'    => array( '整体・整骨院', '整体', 85, '通院の間隔の案内、予約、回数券でリピートを増やしやすい', $H ),
		'shinkyu'   => array( '鍼灸院', '鍼灸院', 80, '通院の予約と、次に来る時期のお知らせが向く', $H ),
		'dental'    => array( '歯科医院', '歯科医院', 70, '定期検診の案内・予約・診察券をアプリにできる', $H ),
		'clinic'    => array( 'クリニック', 'クリニック', 65, '予約・診察券・順番待ちのお知らせが向く', $H ),
		'biyou'     => array( '美容クリニック', '美容クリニック', 75, 'カウンセリングの予約・施術の案内・会員特典が向く', $H ),
		'pharmacy'  => array( '調剤薬局', '調剤薬局', 55, '処方せんの送信・受け取りのお知らせが向く', $H ),
		'kaigo'     => array( 'デイサービス・介護', 'デイサービス', 55, 'ご家族へのお知らせ・連絡帳をまとめられる', $H ),
		'pet'       => array( 'トリミングサロン', 'トリミングサロン', 80, '予約・トリミングの時期のお知らせが向く', $H ),
		'vet'       => array( '動物病院', '動物病院', 70, '予約・ワクチンの時期のお知らせ・診察券が向く', $H ),
		'pethotel'  => array( 'ペットホテル・ドッグラン', 'ペットホテル', 65, '予約・会員証・写真のお届けが向く', $H ),
		// スポーツ・フィットネス
		'gym'       => array( 'パーソナルジム', 'パーソナルジム', 85, '会員証・予約・トレーニング記録・動画をアプリでまとめられる', $S ),
		'fitness'   => array( 'フィットネスジム', 'フィットネスジム', 75, '会員証・混雑状況・レッスンの予約が向く', $S ),
		'yoga'      => array( 'ヨガ・ピラティス', 'ピラティス', 85, 'レッスンの予約・回数券・休講のお知らせが向く', $S ),
		'boxing'    => array( 'キックボクシング・格闘技', 'キックボクシングジム', 80, 'クラスの予約・会員証・動画が向く', $S ),
		'swim'      => array( 'スイミングスクール', 'スイミングスクール', 75, '振替・進級のお知らせ・保護者への連絡が向く', $S ),
		'tennis'    => array( 'テニス・スポーツスクール', 'テニススクール', 75, 'レッスンの予約・振替・会員証が向く', $S ),
		'golf'      => array( 'ゴルフ練習場・スクール', 'ゴルフスクール', 75, '打席・レッスンの予約、会員証、ポイントが向く', $S ),
		'climb'     => array( 'ボルダリング', 'ボルダリングジム', 70, '会員証・混雑状況・イベントのお知らせが向く', $S ),
		// 習い事・教育
		'juku'      => array( '学習塾', '学習塾', 75, '保護者へのお知らせ・出欠・面談の予約をまとめられる', $L ),
		'eikaiwa'   => array( '英会話教室', '英会話教室', 75, 'レッスン予約・振替・会員証・動画教材が向く', $L ),
		'program'   => array( 'プログラミング教室', 'プログラミング教室', 75, '予約・振替・教材・保護者への連絡が向く', $L ),
		'dance'     => array( 'ダンススクール', 'ダンススクール', 75, 'クラスの予約・お知らせ・発表会の案内が向く', $L ),
		'music'     => array( 'ピアノ・音楽教室', '音楽教室', 70, 'レッスンの予約・振替・発表会のお知らせが向く', $L ),
		'cooking'   => array( '料理教室', '料理教室', 70, 'レッスンの予約・レシピ・会員特典が向く', $L ),
		'calli'     => array( '書道・そろばん・学童', 'そろばん教室', 65, '出欠・保護者へのお知らせが向く', $L ),
		'drive'     => array( '自動車教習所', '自動車教習所', 70, '教習の予約・進み具合・お知らせが向く', $L ),
		// 飲食
		'cafe'      => array( 'カフェ', 'カフェ', 70, 'スタンプカード・クーポン・新メニューのお知らせで来店を増やせる', $F ),
		'yakiniku'  => array( '焼肉', '焼肉', 70, '予約・クーポン・誕生日の特典で再来店を増やせる', $F ),
		'izakaya'   => array( '居酒屋・焼き鳥', '居酒屋', 65, '宴会の予約・クーポン・ポイントで再来店を増やせる', $F ),
		'sushi'     => array( '寿司・和食', '寿司', 65, '予約・テイクアウトの注文・会員特典が向く', $F ),
		'italian'   => array( 'イタリアン・フレンチ', 'イタリアン', 65, '予約・記念日の特典・コースの案内が向く', $F ),
		'chinese'   => array( '中華・韓国料理', '中華料理', 60, 'テイクアウトの注文・スタンプカードが向く', $F ),
		'ramen'     => array( 'ラーメン・定食', 'ラーメン', 60, 'スタンプカードと限定メニューのお知らせが向く', $F ),
		'bakery'    => array( 'パン屋', 'パン屋', 65, '焼き上がりのお知らせ・予約・スタンプカードが向く', $F ),
		'sweets'    => array( 'ケーキ・洋菓子', 'ケーキ屋', 65, 'ケーキの予約・誕生日の特典・新作のお知らせが向く', $F ),
		'bar'       => array( 'バー', 'バー', 55, 'イベントのお知らせ・ボトルキープ・会員特典が向く', $F ),
		'karaoke'   => array( 'カラオケ・娯楽', 'カラオケ', 55, '会員証・クーポン・予約が向く', $F ),
		// お店・小売
		'apparel'   => array( 'アパレル・セレクトショップ', 'セレクトショップ', 60, '新作のお知らせ・ポイント・ネット販売が向く', $R ),
		'florist'   => array( '花屋', '花屋', 55, '予約注文・記念日のお知らせが向く', $R ),
		'optician'  => array( '眼鏡店', '眼鏡店', 55, '保証書・会員証・点検のお知らせが向く', $R ),
		'bike'      => array( '自転車店', '自転車店', 50, '点検の時期のお知らせ・会員証が向く', $R ),
		'liquor'    => array( '酒屋・食品店', '酒屋', 55, '入荷のお知らせ・ポイント・予約注文が向く', $R ),
		// 暮らし・サービス
		'realty'    => array( '不動産会社', '不動産会社', 65, '物件の新着のお知らせ・内見の予約・お気に入りが向く', $V ),
		'reform'    => array( 'リフォーム・工務店', 'リフォーム会社', 55, '施工事例・見積もりの相談・点検のお知らせが向く', $V ),
		'car'       => array( '車検・整備', '車検', 65, '車検・点検の時期のお知らせと予約が向く', $V ),
		'carwash'   => array( '洗車・コーティング', 'カーコーティング', 65, '予約・メンテナンスの時期のお知らせが向く', $V ),
		'cleaning'  => array( 'クリーニング', 'クリーニング', 60, '会員証・ポイント・受け取りのお知らせが向く', $V ),
		'photo'     => array( '写真館・フォトスタジオ', 'フォトスタジオ', 70, '撮影の予約・七五三などの時期のお知らせが向く', $V ),
		'bridal'    => array( '結婚式場・ブライダル', '結婚式場', 60, 'ブライダルフェアの予約・打ち合わせの管理が向く', $V ),
		'kimono'    => array( '着物レンタル・呉服', '着物レンタル', 55, '予約・成人式などの時期のお知らせが向く', $V ),
		'hotel'     => array( 'ホテル・旅館', '旅館', 55, '予約・館内案内・リピーター向けの特典が向く', $V ),
		'space'     => array( 'レンタルスペース・コワーキング', 'コワーキングスペース', 65, '予約・会員証・入退室の案内が向く', $V ),
		// 会社・その他
		'shigyo'    => array( '税理士・社労士・士業', '税理士事務所', 55, '顧問先へのお知らせ・書類のやりとり・相談の予約が向く', $C ),
		'insurance' => array( '保険代理店', '保険代理店', 55, '契約の更新のお知らせ・相談の予約が向く', $C ),
		'temple'    => array( 'お寺・神社・葬儀', '葬儀社', 50, '法要・行事のお知らせと予約が向く', $C ),
	);
	// もっと細かいジャンル（業態ごと）。キーは名前から作るので、あとで並べ替えても変わらない。
	foreach ( apprex_ld_more_genres() as $k => $v ) {
		if ( ! isset( $g[ $k ] ) ) {
			$g[ $k ] = $v;
		}
	}
	// 自分で追加したジャンル（「探す」で言葉を入れたもの）。
	foreach ( (array) get_option( 'apprex_ld_custom_genres', array() ) as $k => $v ) {
		if ( is_array( $v ) && ! isset( $g[ $k ] ) ) {
			$g[ $k ] = array( $v[0], $v[0], 60, '自分で追加したジャンル', '自分で追加' );
		}
	}
	return $g;
}

/**
 * 細かいジャンル（業態ごと）。まとまり => [基本点, アプリが合う理由, [名前,検索語,点（省略可）] の一覧]。
 *
 * @return array key => [名前, 検索語, 基本点, 理由, まとまり]
 */
function apprex_ld_more_genres() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$data = array(
		'飲食' => array(
			65,
			'スタンプ・クーポン・予約・新メニューのお知らせで、再来店を増やせる',
			array(
				array( '焼き鳥', '焼き鳥' ), array( '寿司', '寿司' ), array( '回転寿司', '回転寿司', 55 ), array( '天ぷら', '天ぷら' ), array( 'うどん・そば', 'そば' ),
				array( 'つけ麺', 'つけ麺', 60 ), array( 'カレー', 'カレー', 60 ), array( '韓国料理', '韓国料理' ), array( 'タイ・アジア料理', 'タイ料理' ), array( 'フレンチ', 'フレンチ' ),
				array( 'ステーキ・鉄板焼き', 'ステーキ' ), array( 'ハンバーグ・洋食', '洋食' ), array( '定食・食堂', '定食', 55 ), array( 'お好み焼き・もんじゃ', 'お好み焼き' ), array( 'たこ焼き', 'たこ焼き', 55 ),
				array( 'しゃぶしゃぶ・すき焼き', 'しゃぶしゃぶ' ), array( 'もつ鍋・鍋料理', 'もつ鍋' ), array( '海鮮・魚料理', '海鮮料理' ), array( 'うなぎ', 'うなぎ' ), array( '和食・割烹', '割烹' ),
				array( '懐石・料亭', '料亭', 60 ), array( 'ホルモン', 'ホルモン' ), array( '串カツ・串焼き', '串カツ' ), array( 'ジンギスカン', 'ジンギスカン' ), array( 'ビストロ・バル', 'バル' ),
				array( 'ピザ', 'ピザ' ), array( 'ハンバーガー', 'ハンバーガー' ), array( '喫茶店', '喫茶店' ), array( 'ブックカフェ・コンセプトカフェ', 'コンセプトカフェ' ), array( '和菓子・甘味処', '和菓子' ),
				array( 'クレープ・スイーツ', 'クレープ' ), array( 'ジェラート・アイス', 'ジェラート' ), array( 'ドーナツ', 'ドーナツ' ), array( 'ワインバー', 'ワインバー', 55 ), array( '日本酒バー・立ち飲み', '立ち飲み', 55 ),
				array( 'ダイニングバー', 'ダイニングバー' ), array( 'スナック・ラウンジ', 'スナック', 55 ), array( 'ビアバー・ブルワリー', 'クラフトビール' ), array( '弁当・惣菜', '弁当', 60 ), array( 'ケータリング・キッチンカー', 'ケータリング', 55 ),
				array( 'ビュッフェ・食べ放題', '食べ放題' ), array( 'ヴィーガン・オーガニック', 'オーガニック レストラン' ),
			),
		),
		'美容・リラク' => array(
			80,
			'来店の間隔が決まっていて、予約・次回の案内・回数券でリピートを増やしやすい',
			array(
				array( 'メンズ美容室', 'メンズ美容室' ), array( '髪質改善・縮毛矯正', '髪質改善' ), array( 'ヘアセット・着付け', 'ヘアセット' ), array( 'フェイシャルエステ', 'フェイシャルエステ' ), array( '痩身エステ', '痩身エステ' ),
				array( 'メンズ脱毛', 'メンズ脱毛' ), array( 'アイブロウ・眉毛サロン', '眉毛サロン' ), array( 'ホワイトニング', 'ホワイトニング' ), array( '日焼けサロン', '日焼けサロン', 65 ), array( 'リフレクソロジー', 'リフレクソロジー' ),
				array( 'タイ古式マッサージ', 'タイ古式マッサージ' ), array( 'アロマ・リンパ', 'アロマ リンパ' ), array( '岩盤浴', '岩盤浴', 70 ), array( 'スーパー銭湯・温泉', 'スーパー銭湯', 65 ), array( 'ネイルスクール・美容スクール', '美容スクール', 70 ),
			),
		),
		'健康・医療・ペット' => array(
			70,
			'予約・順番待ち・次に来る時期のお知らせ・診察券をアプリにできる',
			array(
				array( '接骨院', '接骨院', 80 ), array( 'カイロプラクティック', 'カイロプラクティック', 80 ), array( '骨盤矯正・産後ケア', '骨盤矯正', 80 ), array( '矯正歯科', '矯正歯科' ), array( '小児歯科', '小児歯科' ),
				array( '皮膚科', '皮膚科', 65 ), array( '美容皮膚科', '美容皮膚科', 75 ), array( '美容外科', '美容外科', 70 ), array( '眼科', '眼科', 60 ), array( '耳鼻科', '耳鼻咽喉科', 65 ),
				array( '小児科', '小児科', 70 ), array( '内科', '内科', 60 ), array( '婦人科・レディースクリニック', 'レディースクリニック', 70 ), array( '心療内科', '心療内科', 60 ), array( '整形外科', '整形外科', 65 ),
				array( '健診センター', '健診センター', 55 ), array( '訪問看護・訪問介護', '訪問看護', 55 ), array( '介護施設', '介護施設', 50 ), array( '漢方・自然療法', '漢方薬局', 60 ), array( '補聴器', '補聴器', 55 ),
				array( 'ペットショップ', 'ペットショップ', 70 ), array( 'ドッグカフェ', 'ドッグカフェ', 70 ), array( '猫カフェ', '猫カフェ', 70 ), array( '犬のしつけ教室', 'ドッグトレーニング', 75 ), array( 'ペットシッター', 'ペットシッター', 70 ),
			),
		),
		'スポーツ・フィットネス' => array(
			80,
			'会員証・レッスンの予約・振替・お知らせ・動画をアプリでまとめられる',
			array(
				array( '24時間ジム', '24時間ジム', 70 ), array( 'ホットヨガ', 'ホットヨガ' ), array( 'ボクシングジム', 'ボクシングジム' ), array( '空手・柔道・剣道', '空手道場', 75 ), array( '合気道・武道', '合気道', 70 ),
				array( 'サッカースクール', 'サッカースクール', 75 ), array( '野球スクール', '野球スクール', 70 ), array( 'バスケットボールスクール', 'バスケットボールスクール', 70 ), array( 'バレエ教室', 'バレエ教室', 75 ), array( '体操教室', '体操教室', 75 ),
				array( '乗馬クラブ', '乗馬クラブ', 70 ), array( 'ゴルフ練習場', 'ゴルフ練習場', 75 ), array( 'テニスコート・クラブ', 'テニスクラブ', 70 ), array( 'フットサル場', 'フットサル', 70 ), array( 'ボウリング場', 'ボウリング', 60 ),
				array( 'バッティングセンター', 'バッティングセンター', 60 ), array( 'スケート・スキー', 'スケートリンク', 60 ), array( '卓球場', '卓球場', 65 ), array( '加圧・EMSトレーニング', '加圧トレーニング', 80 ), array( 'ストレッチ専門店', 'ストレッチ専門店', 85 ),
			),
		),
		'習い事・教育' => array(
			75,
			'保護者・生徒へのお知らせ・出欠・振替・予約をまとめられる',
			array(
				array( '個別指導塾', '個別指導塾' ), array( '予備校', '予備校', 70 ), array( '子ども英語', '子ども英語' ), array( '中国語・韓国語教室', '韓国語教室' ), array( 'ロボット・STEM教室', 'ロボット教室' ),
				array( '書道教室', '書道教室', 70 ), array( '絵画・アート教室', '絵画教室', 70 ), array( 'ピアノ教室', 'ピアノ教室' ), array( 'ギター・ボーカル教室', 'ボーカルスクール' ), array( 'バイオリン・管楽器', 'バイオリン教室', 70 ),
				array( 'パン・お菓子教室', 'パン教室', 70 ), array( '茶道・華道', '華道教室', 65 ), array( '着付け教室', '着付け教室', 65 ), array( '写真・カメラ教室', '写真教室', 65 ), array( 'パソコン教室', 'パソコン教室', 70 ),
				array( '学童保育', '学童保育' ), array( '保育園・幼稚園', '保育園', 70 ), array( '幼児教室・知育', '幼児教室' ), array( '家庭教師', '家庭教師', 70 ), array( '資格スクール', '資格スクール', 70 ),
				array( '専門学校', '専門学校', 65 ), array( 'オンライン講座・セミナー', 'セミナー', 65 ),
			),
		),
		'お店・小売' => array(
			60,
			'新入荷のお知らせ・ポイント・会員証・ネット販売で、また来てもらえる',
			array(
				array( '古着屋', '古着屋', 65 ), array( '靴屋', '靴屋' ), array( '雑貨屋', '雑貨屋', 65 ), array( '家具・インテリア', 'インテリアショップ', 65 ), array( '時計・ジュエリー', 'ジュエリーショップ', 60 ),
				array( 'ワインショップ', 'ワインショップ', 60 ), array( '精肉店', '精肉店', 60 ), array( '鮮魚店', '鮮魚店', 55 ), array( '八百屋・青果店', '八百屋', 55 ), array( 'スーパー', 'スーパーマーケット', 60 ),
				array( 'お茶・コーヒー豆', 'コーヒー豆 専門店', 65 ), array( '書店', '書店', 55 ), array( '文房具店', '文房具店', 55 ), array( 'おもちゃ屋', 'おもちゃ屋', 55 ), array( 'スポーツ用品店', 'スポーツ用品店', 60 ),
				array( '釣具店', '釣具店', 60 ), array( '楽器店', '楽器店', 60 ), array( '家電・携帯ショップ', '携帯ショップ', 55 ), array( 'リサイクル・買取', 'リサイクルショップ', 65 ), array( 'ベビー・子ども服', '子供服', 60 ),
				array( 'ドラッグストア', 'ドラッグストア', 55 ), array( 'ホームセンター', 'ホームセンター', 55 ), array( 'ギフト・お土産', 'お土産屋', 55 ), array( '農産物直売所', '直売所', 60 ),
			),
		),
		'暮らし・サービス' => array(
			60,
			'予約・見積もりの相談・点検や時期のお知らせ・会員証が向く',
			array(
				array( '賃貸仲介', '賃貸', 65 ), array( '住宅メーカー', '住宅メーカー', 60 ), array( '工務店', '工務店', 55 ), array( '外構・エクステリア', '外構工事', 55 ), array( '外壁塗装', '外壁塗装', 55 ),
				array( '水回り・リフォーム', '水回り リフォーム', 55 ), array( '鍵・修理', '鍵屋', 50 ), array( 'ハウスクリーニング', 'ハウスクリーニング', 65 ), array( '家事代行', '家事代行', 70 ), array( '引越し', '引越し業者', 55 ),
				array( 'コインランドリー', 'コインランドリー', 60 ), array( '中古車販売', '中古車販売', 65 ), array( '新車ディーラー', 'カーディーラー', 60 ), array( 'タイヤ・カー用品', 'タイヤ', 60 ), array( 'バイクショップ', 'バイクショップ', 60 ),
				array( 'レンタカー', 'レンタカー', 55 ), array( '民宿・ゲストハウス', 'ゲストハウス', 55 ), array( 'グランピング・キャンプ場', 'グランピング', 65 ), array( 'シェアオフィス', 'シェアオフィス', 65 ), array( 'トランクルーム', 'トランクルーム', 50 ),
				array( '印刷・看板', '印刷会社', 50 ), array( 'スマホ・PC修理', 'スマホ修理', 60 ), array( '靴・時計の修理', '靴修理', 55 ), array( '便利屋', '便利屋', 55 ), array( '結婚相談所', '結婚相談所', 70 ),
				array( '仏壇・墓石', '仏壇', 45 ), array( '写真スタジオ（子ども）', 'こども写真館', 75 ),
			),
		),
		'会社・その他' => array(
			55,
			'お客様・会員へのお知らせ・書類のやりとり・相談の予約をまとめられる',
			array(
				array( '会計事務所', '会計事務所' ), array( '社労士事務所', '社会保険労務士' ), array( '行政書士', '行政書士' ), array( '司法書士', '司法書士' ), array( '法律事務所', '法律事務所' ),
				array( '経営コンサルタント', '経営コンサルタント' ), array( '人材派遣・紹介', '人材派遣', 60 ), array( '広告・制作会社', '広告代理店', 50 ), array( '建設会社', '建設会社', 50 ), array( '製造業・工場', '製造業', 50 ),
				array( '運送・物流', '運送会社', 50 ), array( '農園・観光農園', '観光農園', 60 ), array( '酒蔵・ワイナリー', '酒蔵', 60 ), array( '神社', '神社', 50 ), array( '教会', '教会', 50 ),
				array( 'NPO・団体', 'NPO法人', 50 ), array( '商店街・商工会', '商店街', 60 ), array( '自治体・観光協会', '観光協会', 55 ),
			),
		),
		'遊び・観光' => array(
			60,
			'チケット・予約・会員証・イベントのお知らせが向く',
			array(
				array( '映画館', '映画館', 55 ), array( 'ネットカフェ・漫画喫茶', 'ネットカフェ', 55 ), array( 'ゲームセンター', 'ゲームセンター', 55 ), array( '脱出ゲーム・体験', '体験施設', 60 ), array( '釣り堀・釣り船', '釣り船', 60 ),
				array( '水族館・動物園', '水族館', 55 ), array( '美術館・博物館', '美術館', 50 ), array( '遊園地・テーマパーク', 'テーマパーク', 55 ), array( '観光ツアー', '観光ツアー', 60 ), array( 'ライブハウス', 'ライブハウス', 60 ),
				array( 'ダーツ・ビリヤード', 'ダーツバー', 60 ), array( '麻雀店', '雀荘', 60 ),
			),
		),
	);
	$out = array();
	foreach ( $data as $group => $d ) {
		foreach ( $d[2] as $it ) {
			$k         = 'm' . substr( md5( $it[0] ), 0, 7 );
			$out[ $k ] = array( $it[0], $it[1], isset( $it[2] ) ? (int) $it[2] : (int) $d[0], $d[1], $group );
		}
	}
	return $out;
}

/** 言葉から、自分で追加するジャンルを作る（同じ言葉なら同じもの）。 */
function apprex_ld_custom_genre( $kw ) {
	$kw = trim( mb_substr( sanitize_text_field( (string) $kw ), 0, 30 ) );
	if ( '' === $kw ) {
		return '';
	}
	foreach ( apprex_ld_genres() as $k => $v ) {
		if ( $v[1] === $kw || $v[0] === $kw ) {
			return $k;
		}
	}
	$k        = 'c_' . substr( md5( $kw ), 0, 8 );
	$c        = (array) get_option( 'apprex_ld_custom_genres', array() );
	$c[ $k ]  = array( $kw );
	update_option( 'apprex_ld_custom_genres', $c, false );
	return $k;
}

/** 状態。 */
function apprex_ld_statuses() {
	return array(
		'new'      => '未対応',
		'checked'  => '調査済み',
		'sent'     => '連絡した',
		'replied'  => '返信あり',
		'meeting'  => '打ち合わせ',
		'proposal' => '見積・提案中',
		'won'      => '成約',
		'lost'     => '失注',
		'skip'     => '対象外',
	);
}

/* =========================================================================
 * 保存する場所（管理画面には出さない投稿タイプ）
 * ====================================================================== */
add_action(
	'init',
	function () {
		register_post_type(
			'apprex_lead',
			array(
				'label'           => 'AI営業リスト',
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
	}
);

/** Place ID から見込み先を探す。 */
function apprex_ld_find( $place_id ) {
	$p = get_posts(
		array(
			'post_type'      => 'apprex_lead',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_ld_place', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (string) $place_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $p ? (int) $p[0] : 0;
}

/** 記録を足す。 */
function apprex_ld_log( $id, $text ) {
	$l   = get_post_meta( $id, 'apprex_ld_log', true );
	$l   = is_array( $l ) ? $l : array();
	$l[] = array( time(), (string) $text, get_current_user_id() );
	update_post_meta( $id, 'apprex_ld_log', array_slice( $l, -100 ) );
}

/* =========================================================================
 * Google Places API（New）
 * ====================================================================== */

function apprex_gp_key() {
	return trim( (string) get_option( 'apprex_gp_key', '' ) );
}

/** 今月の呼び出し回数と、月の上限。 */
function apprex_gp_usage( $add = 0 ) {
	$u = get_option( 'apprex_gp_usage', array() );
	$m = wp_date( 'Ym' );
	$u = ( is_array( $u ) && ( $u['m'] ?? '' ) === $m ) ? $u : array( 'm' => $m, 'n' => 0 );
	if ( $add ) {
		$u['n'] += $add;
		update_option( 'apprex_gp_usage', $u, false );
	}
	return (int) $u['n'];
}
function apprex_gp_cap() {
	return max( 0, (int) get_option( 'apprex_gp_cap', 500 ) );
}

/** 取る項目（Text Search と Place Details で共通）。 */
function apprex_gp_fields( $prefix = '' ) {
	$f = array( 'id', 'displayName', 'formattedAddress', 'nationalPhoneNumber', 'websiteUri', 'rating', 'userRatingCount', 'businessStatus', 'googleMapsUri', 'primaryTypeDisplayName' );
	return implode( ',', array_map( function ( $x ) use ( $prefix ) {
		return $prefix . $x;
	}, $f ) );
}

/** Google の返事を、画面で使う形に。 */
function apprex_gp_norm( $p ) {
	return array(
		'id'      => (string) ( $p['id'] ?? '' ),
		'name'    => (string) ( $p['displayName']['text'] ?? '' ),
		'address' => preg_replace( '/^日本、\s*/u', '', (string) ( $p['formattedAddress'] ?? '' ) ),
		'phone'   => (string) ( $p['nationalPhoneNumber'] ?? '' ),
		'site'    => (string) ( $p['websiteUri'] ?? '' ),
		'rating'  => isset( $p['rating'] ) ? (float) $p['rating'] : 0,
		'reviews' => (int) ( $p['userRatingCount'] ?? 0 ),
		'status'  => (string) ( $p['businessStatus'] ?? '' ),
		'maps'    => (string) ( $p['googleMapsUri'] ?? '' ),
		'type'    => (string) ( $p['primaryTypeDisplayName']['text'] ?? '' ),
	);
}

/**
 * 検索（最大60件＝3ページ）。
 *
 * @return array|WP_Error 見つかったお店の配列。
 */
function apprex_gp_search( $query, $max = 20 ) {
	$key = apprex_gp_key();
	if ( '' === $key ) {
		return new WP_Error( 'gp', 'Google の APIキーが入っていません（この画面の下の設定）。' );
	}
	$out   = array();
	$token = '';
	for ( $page = 0; $page < 3 && count( $out ) < $max; $page++ ) {
		if ( apprex_gp_usage() >= apprex_gp_cap() ) {
			return $out ? $out : new WP_Error( 'gp', '今月の上限（' . apprex_gp_cap() . '回）に達しました。設定で上限を変えられます。' );
		}
		$body = array( 'textQuery' => $query, 'languageCode' => 'ja', 'regionCode' => 'JP', 'pageSize' => 20 );
		if ( '' !== $token ) {
			$body['pageToken'] = $token;
		}
		$r = wp_remote_post(
			'https://places.googleapis.com/v1/places:searchText',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => apprex_gp_fields( 'places.' ) . ',nextPageToken',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		apprex_gp_usage( 1 );
		if ( is_wp_error( $r ) ) {
			$GLOBALS['apprex_gp_debug'][] = array( $query, 0, 0, $r->get_error_message() );
			return $out ? $out : $r;
		}
		$b    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$code = (int) wp_remote_retrieve_response_code( $r );
		if ( 200 !== $code ) {
			$msg = $b['error']['message'] ?? ( 'HTTP ' . $code );
			$GLOBALS['apprex_gp_debug'][] = array( $query, $code, 0, $msg );
			return $out ? $out : new WP_Error( 'gp', 'Google：' . apprex_gp_explain( $msg ) );
		}
		$GLOBALS['apprex_gp_debug'][] = array( $query, $code, count( (array) ( $b['places'] ?? array() ) ), '' );
		foreach ( (array) ( $b['places'] ?? array() ) as $p ) {
			$out[] = apprex_gp_norm( $p );
		}
		$token = (string) ( $b['nextPageToken'] ?? '' );
		if ( '' === $token ) {
			break;
		}
	}
	return array_slice( $out, 0, $max );
}

/** Google のエラーの文を、分かる言葉にする。 */
function apprex_gp_explain( $msg ) {
	$m = (string) $msg;
	if ( preg_match( '/API key not valid|API_KEY_INVALID/i', $m ) ) {
		return 'APIキーが正しくありません。コピーしたキーをもう一度貼ってください。（' . $m . '）';
	}
	if ( preg_match( '/has not been used|is disabled|SERVICE_DISABLED|not enabled/i', $m ) ) {
		return '「Places API (New)」が有効になっていません。Google Cloud の「ライブラリ」で有効にしてください。（' . $m . '）';
	}
	if ( preg_match( '/billing|BILLING/i', $m ) ) {
		return 'Google Cloud でお支払い（カード）の登録が必要です。（' . $m . '）';
	}
	if ( preg_match( '/blocked|API_KEY_SERVICE_BLOCKED|referer|IP address/i', $m ) ) {
		return 'APIキーの制限で止められています。キーの「APIの制限」に Places API (New) が入っているか、「アプリケーションの制限」が「なし」か確かめてください。（' . $m . '）';
	}
	return $m;
}

/** 探す言葉の言い換え（最大3つ）。 */
function apprex_ld_terms( $term ) {
	$map = array(
		'美容室'         => array( '美容室', '美容院', 'ヘアサロン' ),
		'バーバー'       => array( 'バーバー', '理容室', '床屋' ),
		'ネイルサロン'   => array( 'ネイルサロン', 'ネイル' ),
		'まつげエクステ' => array( 'まつげエクステ', 'まつげパーマ', 'アイラッシュ' ),
		'エステサロン'   => array( 'エステサロン', 'エステ' ),
		'脱毛サロン'     => array( '脱毛サロン', '脱毛' ),
		'マッサージ'     => array( 'マッサージ', 'もみほぐし', 'リラクゼーション' ),
		'整体'           => array( '整体', '整体院', '整骨院' ),
		'整骨院'         => array( '整骨院', '接骨院' ),
		'接骨院'         => array( '接骨院', '整骨院' ),
		'鍼灸院'         => array( '鍼灸院', 'はり きゅう' ),
		'パーソナルジム' => array( 'パーソナルジム', 'パーソナルトレーニング' ),
		'フィットネスジム' => array( 'フィットネスジム', 'ジム', 'スポーツクラブ' ),
		'ヨガスタジオ'   => array( 'ヨガスタジオ', 'ヨガ' ),
		'ピラティス'     => array( 'ピラティス', 'ヨガ' ),
		'学習塾'         => array( '学習塾', '塾', '個別指導' ),
		'英会話教室'     => array( '英会話教室', '英会話スクール', '英語教室' ),
		'歯科医院'       => array( '歯科医院', '歯医者', 'デンタルクリニック' ),
		'クリニック'     => array( 'クリニック', '医院' ),
		'カフェ'         => array( 'カフェ', '喫茶店', 'コーヒー' ),
		'焼肉'           => array( '焼肉', '焼肉店' ),
		'居酒屋'         => array( '居酒屋', '酒場' ),
		'ラーメン'       => array( 'ラーメン', 'ラーメン屋' ),
		'寿司'           => array( '寿司', '寿司屋' ),
		'不動産会社'     => array( '不動産会社', '不動産' ),
		'車検'           => array( '車検', '自動車整備', '自動車修理' ),
		'クリーニング'   => array( 'クリーニング', 'クリーニング店' ),
		'トリミングサロン' => array( 'トリミングサロン', 'トリミング', 'ペットサロン' ),
		'動物病院'       => array( '動物病院', 'ペットクリニック' ),
		'ゴルフスクール' => array( 'ゴルフスクール', 'ゴルフレッスン', 'ゴルフ練習場' ),
		'パン屋'         => array( 'パン屋', 'ベーカリー' ),
		'ケーキ屋'       => array( 'ケーキ屋', '洋菓子店', 'スイーツ' ),
		'旅館'           => array( '旅館', 'ホテル', '民宿' ),
		'税理士事務所'   => array( '税理士事務所', '税理士' ),
		'リフォーム会社' => array( 'リフォーム会社', 'リフォーム' ),
	);
	$t = trim( (string) $term );
	return isset( $map[ $t ] ) ? $map[ $t ] : array( $t );
}

/** 1件を読み直す。 */
function apprex_gp_details( $place_id ) {
	$key = apprex_gp_key();
	if ( '' === $key ) {
		return new WP_Error( 'gp', 'Google の APIキーが入っていません。' );
	}
	if ( apprex_gp_usage() >= apprex_gp_cap() ) {
		return new WP_Error( 'gp', '今月の上限に達しました。' );
	}
	$r = wp_remote_get(
		'https://places.googleapis.com/v1/places/' . rawurlencode( $place_id ) . '?languageCode=ja&regionCode=JP',
		array(
			'timeout' => 15,
			'headers' => array( 'X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => apprex_gp_fields() ),
		)
	);
	apprex_gp_usage( 1 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$b = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'gp', 'Google：' . ( $b['error']['message'] ?? 'HTTP ' . wp_remote_retrieve_response_code( $r ) ) );
	}
	return apprex_gp_norm( $b );
}

/** Google の情報（一時的に持つ。30日で読み直し）。 */
function apprex_ld_google( $id, $refresh = false ) {
	$g = get_post_meta( $id, 'apprex_ld_g', true );
	if ( is_array( $g ) && ! empty( $g['t'] ) && time() - (int) $g['t'] < 30 * DAY_IN_SECONDS && ! $refresh ) {
		return $g['d'];
	}
	if ( ! $refresh ) {
		return is_array( $g ) && ! empty( $g['d'] ) && time() - (int) $g['t'] < 30 * DAY_IN_SECONDS ? $g['d'] : null;
	}
	$d = apprex_gp_details( (string) get_post_meta( $id, 'apprex_ld_place', true ) );
	if ( is_wp_error( $d ) ) {
		return null;
	}
	update_post_meta( $id, 'apprex_ld_g', array( 't' => time(), 'd' => $d ) );
	return $d;
}

/* =========================================================================
 * 点数
 * ====================================================================== */

/** 店名から支店名を外した「もとの名前」（同じお店がいくつあるか数えるため）。 */
function apprex_ld_brand( $name ) {
	$n = preg_replace( '/[\s　]+(本店|.{1,12}(店|院|校|教室|スタジオ|サロン))$/u', '', (string) $name );
	$n = preg_replace( '/[（(].*?[)）]/u', '', $n );
	return trim( mb_strtolower( $n ) );
}

/** 点数（0〜100）と理由。 */
function apprex_ld_score( $genre, $g, $flags, $chain ) {
	$gs     = apprex_ld_genres();
	$s      = isset( $gs[ $genre ] ) ? $gs[ $genre ][2] - 30 : 40;
	$why    = array();
	$rv     = (int) ( $g['reviews'] ?? 0 );
	$s     += min( 20, (int) round( log( 1 + $rv ) * 4 ) );
	if ( $rv >= 100 ) {
		$why[] = '口コミ' . $rv . '件（お客様が多い）';
	}
	if ( ( $g['rating'] ?? 0 ) >= 4.3 && $rv >= 20 ) {
		$s    += 5;
		$why[] = '評価' . $g['rating'] . '（ファンが多い）';
	}
	if ( $chain >= 2 ) {
		$s    += min( 15, $chain * 4 );
		$why[] = $chain . '店舗（多店舗）';
	}
	if ( empty( $g['site'] ) ) {
		$why[] = 'ホームページなし（ホームページ制作も提案できる）';
	}
	if ( ! empty( $flags['app'] ) ) {
		$s    -= 25;
		$why[] = 'すでにアプリあり（リニューアル・乗り換えの提案）';
	}
	if ( ! empty( $flags['phone_only'] ) ) {
		$s    += 8;
		$why[] = '予約が電話だけ';
	}
	if ( ! empty( $flags['paper'] ) ) {
		$s    += 5;
		$why[] = '紙のポイントカード';
	}
	if ( ! empty( $flags['line'] ) ) {
		$why[] = 'LINE公式あり（LINEとの併用を提案）';
	}
	if ( 'OPERATIONAL' !== ( $g['status'] ?? 'OPERATIONAL' ) ) {
		$s     = 0;
		$why[] = '営業していない';
	}
	return array( max( 0, min( 100, $s ) ), $why );
}

/* =========================================================================
 * 検索して保存
 * ====================================================================== */

/**
 * ジャンルと地域で探して、新しいお店をリストに入れる。
 *
 * @return array|WP_Error [見つかった数, 新しく入れた数]
 */
function apprex_ld_collect( $genre, $area, $max = 20 ) {
	$gs = apprex_ld_genres();
	if ( ! isset( $gs[ $genre ] ) ) {
		return new WP_Error( 'ld', 'ジャンルを選んでください。' );
	}
	$area = trim( preg_replace( '/[\s　]+/u', ' ', (string) $area ) );
	if ( '' === $area ) {
		return new WP_Error( 'ld', '地域を入れてください（例：福島県いわき市、渋谷駅）。' );
	}
	// 言い換えでも探して、同じお店はまとめる（1つの言葉だけだと、Googleマップの登録の仕方によっては出てこないため）。
	$GLOBALS['apprex_gp_debug'] = array();
	$terms = apprex_ld_terms( $gs[ $genre ][1] );
	$res   = array();
	$seen  = array();
	$err   = null;
	$query = '';
	foreach ( $terms as $ti => $t ) {
		if ( count( $res ) >= $max ) {
			break;
		}
		$q = $area . ' ' . $t;
		$query = '' === $query ? $q : $query;
		$r = apprex_gp_search( $q, $max - count( $res ) );
		if ( is_wp_error( $r ) ) {
			$err = $err ? $err : $r;
			if ( false !== strpos( $r->get_error_message(), 'APIキー' ) || false !== strpos( $r->get_error_message(), '上限' ) ) {
				break; // キーや上限の問題は、言い換えても同じ。
			}
			continue;
		}
		foreach ( $r as $g ) {
			if ( '' !== $g['id'] && empty( $seen[ $g['id'] ] ) ) {
				$seen[ $g['id'] ] = 1;
				$res[]            = $g;
			}
		}
	}
	// 1件も出ないときは、言葉の順番を変えてもう一度。
	if ( ! $res && ! ( $err && ( false !== strpos( $err->get_error_message(), 'APIキー' ) || false !== strpos( $err->get_error_message(), '上限' ) ) ) ) {
		$r = apprex_gp_search( $terms[0] . ' ' . $area, $max );
		if ( ! is_wp_error( $r ) ) {
			$res = $r;
		}
	}
	update_option( 'apprex_ld_last_debug', array( time(), $GLOBALS['apprex_gp_debug'] ), false );
	if ( ! $res && $err ) {
		return $err;
	}
	// 同じ名前のお店がいくつあるか（多店舗）。
	$brands = array();
	foreach ( $res as $g ) {
		$b            = apprex_ld_brand( $g['name'] );
		$brands[ $b ] = ( $brands[ $b ] ?? 0 ) + 1;
	}
	$new  = 0;
	$nids = array();
	foreach ( $res as $g ) {
		if ( '' === $g['id'] ) {
			continue;
		}
		$id = apprex_ld_find( $g['id'] );
		if ( ! $id ) {
			$id = wp_insert_post( array( 'post_type' => 'apprex_lead', 'post_status' => 'publish', 'post_title' => 'place:' . $g['id'] ) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, 'apprex_ld_place', $g['id'] );
			update_post_meta( $id, 'apprex_ld_genre', $genre );
			update_post_meta( $id, 'apprex_ld_area', $area );
			update_post_meta( $id, 'apprex_ld_status', 'new' );
			apprex_ld_log( $id, 'Googleマップで見つけてリストに入れました（「' . $query . '」）' );
			$new++;
			$nids[] = (int) $id;
		}
		update_post_meta( $id, 'apprex_ld_g', array( 't' => time(), 'd' => $g ) );
		$chain = $brands[ apprex_ld_brand( $g['name'] ) ] ?? 1;
		update_post_meta( $id, 'apprex_ld_chain', $chain );
		$flags = (array) get_post_meta( $id, 'apprex_ld_flags', true );
		list( $sc, $why ) = apprex_ld_score( $genre, $g, $flags, $chain );
		if ( ! get_post_meta( $id, 'apprex_ld_researched', true ) ) {
			update_post_meta( $id, 'apprex_ld_score', $sc );
			update_post_meta( $id, 'apprex_ld_why', $why );
		}
	}
	$hist   = (array) get_option( 'apprex_ld_searches', array() );
	$hist[] = array( time(), $genre, $area, count( $res ), $new );
	update_option( 'apprex_ld_searches', array_slice( $hist, -50 ), false );
	return array( count( $res ), $new, $nids );
}

/* =========================================================================
 * お店のホームページを調べる（公開されている情報だけ）
 * ====================================================================== */

/** ページを読む（文字とリンク）。 */
function apprex_ld_fetch( $url ) {
	$r = wp_remote_get(
		$url,
		array(
			'timeout'     => 12,
			'redirection' => 3,
			'user-agent'  => 'Mozilla/5.0 (compatible; APPREX-research; +' . home_url( '/' ) . ')',
			'limit_response_size' => 600000,
		)
	);
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$html = (string) wp_remote_retrieve_body( $r );
	if ( ! preg_match( '/charset\s*=\s*["\']?utf-?8/i', $html ) && function_exists( 'mb_convert_encoding' ) && preg_match( '/charset\s*=\s*["\']?(shift_jis|sjis|euc-jp)/i', $html, $m ) ) {
		$html = mb_convert_encoding( $html, 'UTF-8', strtoupper( str_replace( 'sjis', 'SJIS', $m[1] ) ) );
	}
	return $html;
}

/** メタの配列（空・壊れた値を除く）。 */
function apprex_ld_meta_list( $id, $key ) {
	$v = get_post_meta( $id, $key, true );
	return is_array( $v ) ? array_values( array_filter( $v, function ( $x ) {
		return '' !== $x && null !== $x && false !== $x;
	} ) ) : array();
}

/** ページの中のリンク [[絶対URL, 文字], …]。 */
function apprex_ld_links( $html, $base ) {
	$out = array();
	if ( preg_match_all( '#<a\b[^>]*href=["\']([^"\'\#]+)[^"\']*["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $a ) {
			if ( preg_match( '#^(mailto|tel|javascript):#i', $a[1] ) ) {
				continue;
			}
			$u = WP_Http::make_absolute_url( html_entity_decode( $a[1] ), $base );
			if ( preg_match( '#^https?://#i', $u ) ) {
				$out[] = array( $u, trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $a[2] ) ) ) );
			}
		}
	}
	return $out;
}

/** ページの中のメールアドレス（ふつうの書き方・＠・[at]・mailto・Cloudflareの隠し方）。 */
function apprex_ld_find_emails( $html ) {
	$found = array();
	// Cloudflare のメール保護（data-cfemail="16進"）。
	if ( preg_match_all( '/data-cfemail=["\']([0-9a-f]+)["\']|\/cdn-cgi\/l\/email-protection#([0-9a-f]+)/i', $html, $cf, PREG_SET_ORDER ) ) {
		foreach ( $cf as $c ) {
			$hex = '' !== $c[1] ? $c[1] : ( $c[2] ?? '' );
			if ( strlen( $hex ) < 4 ) {
				continue;
			}
			$k = hexdec( substr( $hex, 0, 2 ) );
			$e = '';
			for ( $i = 2; $i < strlen( $hex ); $i += 2 ) {
				$e .= chr( hexdec( substr( $hex, $i, 2 ) ) ^ $k );
			}
			$found[] = $e;
		}
	}
	$t = html_entity_decode( rawurldecode( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$t = str_replace( array( '＠', '［at］', '[at]', '(at)', '（at）', '[ at ]', '(アットマーク)', '（アットマーク）', '★', '☆' ), '@', $t );
	$t = str_replace( array( '［dot］', '[dot]', '(dot)' ), '.', $t );
	if ( preg_match_all( '/[A-Za-z0-9._%+\-]+\s?@\s?[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/', $t, $em ) ) {
		$found = array_merge( $found, $em[0] );
	}
	$out = array();
	foreach ( $found as $e ) {
		$e = strtolower( trim( str_replace( ' ', '', $e ), '.' ) );
		if ( ! is_email( $e ) || preg_match( '/\.(png|jpe?g|gif|webp|svg|css|js)$|example\.|sentry|wixpress|@2x|@3x|domain\.(com|jp)|xxx|your|sample|test@|noreply|no-reply|do-not-reply|mailer-daemon|@sentry|\.wix\.com$|@w3\.org/i', $e ) ) {
			continue;
		}
		$out[ $e ] = true;
	}
	return array_keys( $out );
}

/** 運営会社の名前（ページの文字から）。 */
function apprex_ld_find_operator( $text ) {
	if ( preg_match( '/(?:運営会社|運営元|運営者|運営|販売業者|事業者名?|会社名|商号)\s*[：:｜|]?\s*((?:株式会社|有限会社|合同会社|一般社団法人|医療法人(?:社団|財団)?)\s*[^\s、。,|｜()（）]{1,24}|[^\s、。,|｜()（）]{1,24}\s*(?:株式会社|有限会社|合同会社))/u', $text, $m ) ) {
		return trim( $m[1] );
	}
	return '';
}

/** ホームページから、公開メール・営業お断り・アプリ/LINE/紙のカードなどを調べる。運営会社のサイトや、お問い合わせ・会社概要・特定商取引法・プライバシーのページも見る。 */
function apprex_ld_inspect_site( $site ) {
	$out  = array( 'emails' => array(), 'src' => array(), 'ng' => false, 'flags' => array(), 'text' => '', 'pages' => 0, 'operator' => '', 'op_site' => '', 'form' => '' );
	$home = apprex_ld_fetch( $site );
	if ( null === $home ) {
		return $out;
	}
	$host  = (string) wp_parse_url( $site, PHP_URL_HOST );
	$pages = array( $site => $home );
	$kind  = array( $site => 'shop' );
	$pat   = array(
		'contact' => '/contact|inquiry|toiawase|otoiawase|form|お問い?合わ?せ|ご相談|ご意見/iu',
		'law'     => '/tokushoho|tokutei|law|legal|特定商取引|特商法/iu',
		'company' => '/company|corporate|about|profile|outline|gaiyou|会社概要|会社案内|運営会社|企業情報|事業者/iu',
		'privacy' => '/privacy|policy|個人情報|プライバシー/iu',
	);
	$queue = array();
	$add   = function ( $links, $k ) use ( &$queue, $pat, $host ) {
		foreach ( $links as $l ) {
			if ( (string) wp_parse_url( $l[0], PHP_URL_HOST ) !== $host ) {
				continue;
			}
			foreach ( $pat as $p => $re ) {
				if ( preg_match( $re, $l[0] . ' ' . $l[1] ) ) {
					$u = preg_replace( '/[?#].*$/', '', $l[0] );
					if ( ! isset( $queue[ $u ] ) ) {
						$queue[ $u ] = array( $p, $k );
					}
					break;
				}
			}
		}
	};
	$links = apprex_ld_links( $home, $site );
	$add( $links, 'shop' );
	// お店のページがサイトの一部（例：ホテルの中のレストラン）なら、サイトのトップも見る。
	$root = wp_parse_url( $site, PHP_URL_SCHEME ) . '://' . $host . '/';
	if ( untrailingslashit( preg_replace( '/[?#].*$/', '', $site ) ) !== untrailingslashit( $root ) ) {
		$rh = apprex_ld_fetch( $root );
		if ( null !== $rh ) {
			$pages[ $root ] = $rh;
			$kind[ $root ]  = 'site';
			$add( apprex_ld_links( $rh, $root ), 'site' );
			$links = array_merge( $links, apprex_ld_links( $rh, $root ) );
		}
	}
	// 読む順：お問い合わせ → 特定商取引法 → 会社概要 → プライバシー。全部で8ページまで。
	$order = array( 'contact' => 0, 'law' => 1, 'company' => 2, 'privacy' => 3 );
	uasort(
		$queue,
		function ( $a, $b ) use ( $order ) {
			return $order[ $a[0] ] <=> $order[ $b[0] ];
		}
	);
	$per = array();
	foreach ( $queue as $u => $q ) {
		if ( count( $pages ) >= 8 ) {
			break;
		}
		if ( isset( $pages[ $u ] ) || ( $per[ $q[0] ] ?? 0 ) >= 2 ) {
			continue;
		}
		$h = apprex_ld_fetch( $u );
		$per[ $q[0] ] = ( $per[ $q[0] ] ?? 0 ) + 1;
		if ( null !== $h ) {
			$pages[ $u ] = $h;
			$kind[ $u ]  = $q[0];
			if ( '' === $out['form'] && 'contact' === $q[0] && preg_match( '/<form\b[\s\S]*?<textarea/i', $h ) ) {
				$out['form'] = $u;
			}
		}
	}
	$text_of = function ( $h ) {
		return preg_replace( '/\s+/u', ' ', wp_strip_all_tags( preg_replace( '#<(script|style|noscript)[^>]*>.*?</\1>#is', ' ', $h ) ) );
	};
	$all  = implode( "\n", $pages );
	$text = $text_of( $all );
	// 運営会社と、そのサイト（外のサイトへのリンクで、文字に会社名・「運営」があるもの）。
	$out['operator'] = apprex_ld_find_operator( $text );
	foreach ( $links as $l ) {
		$lh = (string) wp_parse_url( $l[0], PHP_URL_HOST );
		if ( '' === $lh || $lh === $host || preg_match( '/instagram|facebook|twitter|x\.com|line\.me|lin\.ee|youtube|tiktok|google|apple\.com|hotpepper|tabelog|gnavi|retty|ikyu|jalan|rakuten|booking|tablecheck|wix|jimdo|wordpress|ameblo|note\.com|amazon|yahoo/i', $lh ) ) {
			continue;
		}
		if ( preg_match( '/運営|会社|株式会社|有限会社|合同会社|corporate|company|group|グループ/iu', $l[1] ) || ( '' !== $out['operator'] && false !== mb_strpos( $l[1], preg_replace( '/株式会社|有限会社|合同会社|\s/u', '', $out['operator'] ) ) ) ) {
			$out['op_site'] = wp_parse_url( $l[0], PHP_URL_SCHEME ) . '://' . $lh . '/';
			break;
		}
	}
	if ( '' !== $out['op_site'] ) {
		$oh = apprex_ld_fetch( $out['op_site'] );
		if ( null !== $oh ) {
			$opages = array( $out['op_site'] => $oh );
			$ohost  = (string) wp_parse_url( $out['op_site'], PHP_URL_HOST );
			$n      = 0;
			foreach ( apprex_ld_links( $oh, $out['op_site'] ) as $l ) {
				if ( $n >= 2 || (string) wp_parse_url( $l[0], PHP_URL_HOST ) !== $ohost || ! preg_match( $pat['contact'] . '', $l[0] . ' ' . $l[1] ) && ! preg_match( $pat['company'], $l[0] . ' ' . $l[1] ) ) {
					continue;
				}
				$u = preg_replace( '/[?#].*$/', '', $l[0] );
				if ( isset( $opages[ $u ] ) ) {
					continue;
				}
				$h = apprex_ld_fetch( $u );
				$n++;
				if ( null !== $h ) {
					$opages[ $u ] = $h;
				}
			}
			foreach ( $opages as $u => $h ) {
				$pages[ $u ] = $h;
				$kind[ $u ]  = 'operator';
			}
			if ( '' === $out['operator'] ) {
				$out['operator'] = apprex_ld_find_operator( $text_of( implode( "\n", $opages ) ) );
			}
		}
	}
	$out['pages'] = count( $pages );
	$out['text']  = mb_substr( $text, 0, 3000 );
	// メールアドレスを集めて、よいものから並べる（お店と同じドメイン → info/contact → 運営会社 → そのほか）。
	$cands = array();
	foreach ( $pages as $u => $h ) {
		foreach ( apprex_ld_find_emails( $h ) as $e ) {
			// 個人情報・採用・IRなどの窓口には、営業メールを送らない。
			if ( isset( $cands[ $e ] ) || preg_match( '/^(privacy|kojin|personal|recruit|saiyo|career|jinji|ir|press|pr|job|kabunushi)[._@-]/', $e ) ) {
				continue;
			}
			$dom = substr( strrchr( $e, '@' ), 1 );
			$sc  = 0;
			$sc += ( $dom === $host || '.' . $dom === substr( $host, -strlen( $dom ) - 1 ) ) ? 30 : 0;
			$sc += ( '' !== $out['op_site'] && false !== strpos( (string) wp_parse_url( $out['op_site'], PHP_URL_HOST ), $dom ) ) ? 20 : 0;
			$sc += preg_match( '/^(info|contact|support|inquiry|otoiawase|toiawase|mail|shop|store|office|webmaster|sales|reserve|yoyaku)/', $e ) ? 15 : 0;
			$sc += in_array( $kind[ $u ], array( 'contact', 'law', 'company' ), true ) ? 10 : 0;
			$cands[ $e ] = array( $sc, $u, $kind[ $u ] );
		}
	}
	uasort(
		$cands,
		function ( $a, $b ) {
			return $b[0] <=> $a[0];
		}
	);
	foreach ( array_slice( $cands, 0, 3, true ) as $e => $c ) {
		$out['emails'][]  = $e;
		$out['src'][ $e ] = array( $c[1], $c[2] );
	}
	// 営業お断り（お店・運営会社のどちらに書いてあっても）。
	$out['ng'] = (bool) preg_match( '/(営業|セールス|広告|勧誘|売り込み)[^。]{0,12}(メール|ご連絡|電話|お問い?合わせ)?[^。]{0,10}(お断り|ご遠慮|控え|禁止|固くお断り)/u', $text . ' ' . $text_of( implode( "\n", array_intersect_key( $pages, array_filter( $kind, function ( $k ) {
		return 'operator' === $k;
	} ) ) ) ) );
	// アプリ・LINE・Instagram・予約・紙のカード（お店のページだけで見る）。
	$shop_html = implode( "\n", array_intersect_key( $pages, array_filter( $kind, function ( $k ) {
		return 'operator' !== $k;
	} ) ) );
	$shop_text = $text_of( $shop_html );
	$out['flags'] = array(
		'app'      => (bool) preg_match( '#apps\.apple\.com|itunes\.apple\.com|play\.google\.com/store/apps#i', $shop_html ),
		'line'     => (bool) preg_match( '#lin\.ee/|line\.me/R/ti|page\.line\.me|@[a-z0-9]{3,}\s*（?LINE#i', $shop_html ),
		'insta'    => (bool) preg_match( '#instagram\.com/#i', $shop_html ),
		'web_book' => (bool) preg_match( '#beauty\.hotpepper|hotpepper\.jp|reserva\.be|airrsv|coubic|stores\.jp/reserve|tablecheck|ekiten|minimodel|web予約|ネット予約|オンライン予約#iu', $shop_html ),
		'paper'    => (bool) preg_match( '/スタンプカード|ポイントカード/u', $shop_text ) && ! preg_match( '#apps\.apple\.com|play\.google\.com#i', $shop_html ),
		'form'     => '' !== $out['form'],
	);
	$out['flags']['phone_only'] = ! $out['flags']['web_book'] && (bool) preg_match( '/(ご予約|予約)[^。]{0,15}(お電話|電話)/u', $shop_text );
	return $out;
}

/** AIで「提案のきっかけ」（AIが使えないときは空）。 */
function apprex_ld_ai_hook( $g, $genre, $info ) {
	if ( ! function_exists( 'apprex_ai_text_ready' ) || ! apprex_ai_text_ready() || ! function_exists( 'apprex_openrouter_complete' ) ) {
		return '';
	}
	$gs  = apprex_ld_genres();
	$sys = 'あなたはアプリ制作会社APPREXの営業担当です。お店の公開情報から、アプリを提案するきっかけを考えます。事実として書かれていることだけを使い、推測で決めつけない。';
	$msg = "お店：{$g['name']}（{$gs[$genre][0]}・{$g['address']}）\n口コミ：{$g['reviews']}件・評価{$g['rating']}\n"
		. 'ホームページで分かったこと：' . wp_json_encode( $info['flags'], JSON_UNESCAPED_UNICODE ) . "\nホームページの文章（一部）：" . mb_substr( $info['text'], 0, 1800 )
		. "\n\nこのお店にアプリ（予約・ポイント・クーポン・お知らせ・会員証など）を提案するなら、どこがきっかけになりますか。2〜3行の日本語で、お店の具体的な内容にふれて書いてください。前置きは不要です。";
	$r = apprex_openrouter_complete( array( array( 'role' => 'system', 'content' => $sys ), array( 'role' => 'user', 'content' => $msg ) ), array( 'max_tokens' => 300, 'temperature' => 0.4, 'timeout' => 40 ) );
	if ( is_wp_error( $r ) ) {
		return '';
	}
	$t = trim( wp_strip_all_tags( (string) $r ) );
	// 文章でない返事（JSONなど）は使わない。
	if ( '' === $t || preg_match( '/^[\[{]/', $t ) || false !== strpos( $t, '":"' ) ) {
		return '';
	}
	return mb_substr( $t, 0, 300 );
}

/** 1件を調べる。 */
function apprex_ld_research( $id ) {
	$g = apprex_ld_google( $id );
	if ( ! $g ) {
		$g = apprex_ld_google( $id, true );
	}
	if ( ! $g ) {
		return new WP_Error( 'ld', 'Google の情報を読めませんでした。' );
	}
	$genre = (string) get_post_meta( $id, 'apprex_ld_genre', true );
	$info  = ! empty( $g['site'] ) ? apprex_ld_inspect_site( $g['site'] ) : array( 'emails' => array(), 'src' => array(), 'ng' => false, 'flags' => array(), 'text' => '', 'pages' => 0, 'operator' => '', 'op_site' => '', 'form' => '' );
	update_post_meta( $id, 'apprex_ld_flags', $info['flags'] );
	update_post_meta( $id, 'apprex_ld_emails', $info['emails'] );
	update_post_meta( $id, 'apprex_ld_email_src', $info['src'] ?? array() );
	update_post_meta( $id, 'apprex_ld_operator', $info['operator'] ?? '' );
	update_post_meta( $id, 'apprex_ld_op_site', $info['op_site'] ?? '' );
	update_post_meta( $id, 'apprex_ld_form', $info['form'] ?? '' );
	update_post_meta( $id, 'apprex_ld_ng', $info['ng'] ? 1 : 0 );
	list( $sc, $why ) = apprex_ld_score( $genre, $g, $info['flags'], (int) get_post_meta( $id, 'apprex_ld_chain', true ) );
	if ( $info['ng'] ) {
		$why[] = '「営業お断り」の記載あり（メールは送らない）';
	}
	update_post_meta( $id, 'apprex_ld_score', $sc );
	update_post_meta( $id, 'apprex_ld_why', $why );
	$hook = apprex_ld_ai_hook( $g, $genre, $info );
	if ( '' !== $hook ) {
		update_post_meta( $id, 'apprex_ld_hook', $hook );
	}
	update_post_meta( $id, 'apprex_ld_researched', time() );
	if ( 'new' === get_post_meta( $id, 'apprex_ld_status', true ) ) {
		update_post_meta( $id, 'apprex_ld_status', $info['ng'] ? 'skip' : 'checked' );
	}
	apprex_ld_log( $id, 'ホームページを調べました（' . $info['pages'] . 'ページ・公開メール ' . count( $info['emails'] ) . '件' . ( ! empty( $info['operator'] ) ? '・運営：' . $info['operator'] : '' ) . ( ! empty( $info['form'] ) ? '・問い合わせフォームあり' : '' ) . ( $info['ng'] ? '・営業お断りの記載あり' : '' ) . '）' );
	return true;
}

/* =========================================================================
 * 画面：業務ナビ → AI営業リスト
 * ====================================================================== */
add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'apprex-nav', 'AI営業リスト', 'AI営業リスト', 'manage_options', 'apprex-leads', 'apprex_ld_page' );
	},
	21
);

add_action(
	'admin_init',
	function () {
		register_setting( 'apprex_leads', 'apprex_gp_key', array( 'sanitize_callback' => function ( $v ) {
			$v = trim( (string) $v );
			return '' === $v ? (string) get_option( 'apprex_gp_key', '' ) : sanitize_text_field( $v );
		} ) );
		register_setting( 'apprex_leads', 'apprex_gp_cap', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto_genres', array( 'sanitize_callback' => function ( $v ) {
			return array_values( array_intersect( array_map( 'sanitize_key', (array) $v ), array_keys( apprex_ld_genres() ) ) );
		} ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto_areas', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto_freq', array( 'sanitize_callback' => 'sanitize_key' ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto_max', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto_research', array( 'sanitize_callback' => 'absint' ) );
		register_setting( 'apprex_leads_auto', 'apprex_ld_auto_min', array( 'sanitize_callback' => 'absint' ) );
	}
);

/** 操作（探す・調べる・状態を変える・メモ）。 */
add_action(
	'admin_post_apprex_ld',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_ld' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$msg  = '';
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=apprex-leads' );
		if ( 'collect' === $do ) {
			$genre = sanitize_key( $_POST['genre'] ?? '' );
			$kw    = sanitize_text_field( wp_unslash( $_POST['kw'] ?? '' ) );
			if ( '' !== trim( $kw ) ) {
				$genre = apprex_ld_custom_genre( $kw ); // 一覧にない業種は、言葉で探す。
			}
			$apprex_pref  = sanitize_text_field( wp_unslash( $_POST['pref'] ?? '' ) );
			$apprex_city  = sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) );
			$apprex_place = sanitize_text_field( wp_unslash( $_POST['place'] ?? '' ) );
			$apprex_area  = isset( $_POST['area'] ) ? sanitize_text_field( wp_unslash( $_POST['area'] ) ) : trim( apprex_ld_area_join( $apprex_pref, $apprex_city ) . ' ' . $apprex_place );
			update_user_meta( get_current_user_id(), 'apprex_ld_last_area', array( $apprex_pref, $apprex_city ) );
			$r   = apprex_ld_collect( $genre, $apprex_area, min( 60, max( 20, (int) ( $_POST['max'] ?? 20 ) ) ) );
			if ( is_wp_error( $r ) ) {
				$msg = '失敗：' . $r->get_error_message();
			} elseif ( ! $r[0] ) {
				$msg = '0件でした。下の「前回の検索の中身」で、Google からの返事を確かめてください。地域は「福島県いわき市」「渋谷駅」のように、市区町村や駅の名前で入れると見つかりやすくなります。';
			} else {
				$msg = $r[0] . '件見つかり、新しく' . $r[1] . '件をリストに入れました。' . ( $r[0] > $r[1] ? '（' . ( $r[0] - $r[1] ) . '件はリストに入っていたお店）' : '' );
			}
		} elseif ( 'research' === $do ) {
			$ids = array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) );
			$n   = 0;
			if ( count( $ids ) > 10 && function_exists( 'apprex_ldq_start' ) ) {
				$n    = apprex_ldq_start( $ids, 'チェックしたお店' );
				$msg  = $n . '件を、まとめて調べ直しに入れました。上の「調べ直し中」で進み具合が見られます。';
				$ids  = array();
			}
			foreach ( array_slice( $ids, 0, 10 ) as $id ) {
				if ( 'apprex_lead' === get_post_type( $id ) && ! is_wp_error( apprex_ld_research( $id ) ) ) {
					$n++;
				}
			}
			$msg = '' !== $msg ? $msg : $n . '件を調べました。';
		} elseif ( 'status' === $do ) {
			$id = absint( $_POST['id'] ?? 0 );
			$st = sanitize_key( $_POST['status'] ?? '' );
			if ( 'apprex_lead' === get_post_type( $id ) && isset( apprex_ld_statuses()[ $st ] ) ) {
				$old = (string) get_post_meta( $id, 'apprex_ld_status', true );
				update_post_meta( $id, 'apprex_ld_status', $st );
				$note = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
				apprex_ld_log( $id, '状態：' . ( apprex_ld_statuses()[ $old ] ?? $old ) . ' → ' . apprex_ld_statuses()[ $st ] . ( '' !== $note ? '（' . $note . '）' : '' ) );
				if ( in_array( $st, array( 'won', 'lost' ), true ) ) {
					update_post_meta( $id, 'apprex_ld_closed', time() );
					update_post_meta( $id, 'apprex_ld_close_note', $note );
				}
				$msg = '状態を「' . apprex_ld_statuses()[ $st ] . '」にしました。';
			}
		} elseif ( 'mdraft' === $do && function_exists( 'apprex_ldm_make_draft' ) ) {
			$ok   = 0;
			$errs = array();
			foreach ( array_slice( array_map( 'absint', (array) ( $_POST['ids'] ?? array() ) ), 0, 10 ) as $id ) {
				$r = apprex_ldm_make_draft( $id );
				if ( true === $r ) {
					$ok++;
				} elseif ( is_wp_error( $r ) ) {
					$errs[ $r->get_error_message() ] = true;
				}
			}
			$msg  = $ok . '件の下書きを作りました。下の「送る前の確認」で読んでから送ってください。' . ( $errs ? '（作れなかった理由：' . implode( '／', array_keys( $errs ) ) . '）' : '' );
			$back = admin_url( 'admin.php?page=apprex-leads#ldm' );
		} elseif ( 'gptest' === $do ) {
			$GLOBALS['apprex_gp_debug'] = array();
			$r   = apprex_gp_search( '東京駅 カフェ', 20 );
			$msg = is_wp_error( $r ) ? '失敗：' . $r->get_error_message() : 'Google につながりました（テストで「東京駅 カフェ」を探して ' . count( $r ) . '件）。';
			update_option( 'apprex_ld_last_debug', array( time(), $GLOBALS['apprex_gp_debug'] ), false );
		} elseif ( 'autorun' === $do ) {
			$r   = apprex_ld_auto_run( true );
			$msg = is_wp_error( $r ) ? '失敗：' . $r->get_error_message() : $r;
		} elseif ( 'refresh' === $do ) {
			$id  = absint( $_POST['id'] ?? 0 );
			$msg = ( 'apprex_lead' === get_post_type( $id ) && apprex_ld_google( $id, true ) ) ? 'Google の情報を読み直しました。' : '読み直せませんでした。';
		}
		wp_safe_redirect( add_query_arg( 'ldmsg', rawurlencode( $msg ), $back ) );
		exit;
	}
);

/** 一覧の条件で見込み先を集める。 */
function apprex_ld_query( $f ) {
	$mq = array();
	if ( ! empty( $f['genre'] ) ) {
		$mq[] = array( 'key' => 'apprex_ld_genre', 'value' => $f['genre'] );
	}
	if ( ! empty( $f['status'] ) ) {
		$mq[] = array( 'key' => 'apprex_ld_status', 'value' => $f['status'] );
	}
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_lead',
			'post_status'    => 'any',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'meta_query'     => $mq, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	usort(
		$ids,
		function ( $a, $b ) {
			return (int) get_post_meta( $b, 'apprex_ld_score', true ) <=> (int) get_post_meta( $a, 'apprex_ld_score', true );
		}
	);
	return $ids;
}

/** ジャンルを選ぶ欄（まとまりごと）。 */
/** 都道府県と市区町村の一覧（[[都道府県, [市区町村…]], …]）。 */
function apprex_ld_areas_data() {
	static $d = null;
	if ( null === $d ) {
		$f = get_template_directory() . '/assets/data/jp-areas.json';
		$d = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$d = is_array( $d ) ? $d : array();
	}
	return $d;
}

/** 都道府県＋市区町村 → Google で探す地域の言葉（例：福島県いわき市）。 */
function apprex_ld_area_join( $pref, $city ) {
	$pref = trim( (string) $pref );
	$city = trim( (string) $city );
	return '' === $city ? $pref : $pref . $city;
}

/** 地域を選ぶしくみ（ページに1回だけ出す）。 */
function apprex_ld_area_script() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<script>
	(function(){
		var A=<?php echo wp_json_encode( apprex_ld_areas_data(), JSON_UNESCAPED_UNICODE ); ?>;
		var P={};A.forEach(function(r){P[r[0]]=r[1];});
		function esc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}
		function fillPref(sel,cur){
			sel.innerHTML='<option value="">都道府県を選ぶ</option>'+A.map(function(r){return '<option'+(r[0]===cur?' selected':'')+'>'+esc(r[0])+'</option>';}).join('');
		}
		/* 探す：都道府県 → 市区町村 */
		document.querySelectorAll('form .ld-pref[name="pref"]').forEach(function(ps){
			var cs=ps.form.querySelector('.ld-city');
			fillPref(ps,ps.dataset.cur||'');
			function fillCity(cur){
				var l=P[ps.value]||[];
				cs.innerHTML='<option value="">'+(ps.value?esc(ps.value)+'全体':'市区町村')+'</option>'+l.map(function(c){return '<option'+(c===cur?' selected':'')+'>'+esc(c)+'</option>';}).join('');
				cs.disabled=!ps.value;
			}
			fillCity(cs.dataset.cur||'');
			ps.addEventListener('change',function(){fillCity('');});
		});
		/* 自動で集める：チェックで選ぶ */
		var ta=document.getElementById('ld_areas');if(!ta)return;
		var ap=document.getElementById('ld_apref'),box=document.getElementById('ld_acities'),q=document.getElementById('ld_acityq'),chips=document.getElementById('ld_chips');
		var sel=ta.value.split(/\r?\n/).map(function(s){return s.trim();}).filter(Boolean);
		function save(){ta.value=sel.join('\n');}
		function drawChips(){
			chips.innerHTML=sel.length?sel.map(function(a,i){return '<span style="background:#f0f6fc;border:1px solid #c5d9ed;border-radius:99px;padding:2px 6px 2px 10px">'+esc(a)+' <button type="button" data-i="'+i+'" aria-label="外す" style="border:0;background:none;cursor:pointer;color:#b32d2e">×</button></span>';}).join(''):'<span class="description">まだ選んでいません。</span>';
		}
		function drawCities(){
			var pf=ap.value,qq=(q.value||'').trim();
			if(!pf){box.innerHTML='<span class="description">先に都道府県を選んでください。</span>';return;}
			var l=[''].concat(P[pf]||[]);
			box.innerHTML=l.filter(function(c){return !qq||c.indexOf(qq)>-1;}).map(function(c){
				var v=pf+c;
				return '<label style="white-space:nowrap"><input type="checkbox" value="'+esc(v)+'"'+(sel.indexOf(v)>-1?' checked':'')+'> '+(c?esc(c):'<b>'+esc(pf)+'全体</b>')+'</label>';
			}).join('');
		}
		fillPref(ap,'');
		ap.addEventListener('change',function(){q.value='';drawCities();});
		q.addEventListener('input',drawCities);
		box.addEventListener('change',function(e){var v=e.target.value;if(!v)return;var i=sel.indexOf(v);if(e.target.checked&&i<0)sel.push(v);if(!e.target.checked&&i>-1)sel.splice(i,1);save();drawChips();});
		chips.addEventListener('click',function(e){var b=e.target.closest('button[data-i]');if(!b)return;sel.splice(+b.dataset.i,1);save();drawChips();drawCities();});
		drawChips();drawCities();
	})();
	</script>
	<?php
}

function apprex_ld_genre_select( $name, $cur, $all = '', $onchange = '' ) {
	$groups = array();
	foreach ( apprex_ld_genres() as $k => $g ) {
		$groups[ $g[4] ][ $k ] = $g[0];
	}
	echo '<select name="' . esc_attr( $name ) . '"' . ( '' !== $onchange ? ' onchange="' . esc_attr( $onchange ) . '"' : '' ) . '>';
	if ( '' !== $all ) {
		echo '<option value="">' . esc_html( $all ) . '</option>';
	}
	foreach ( $groups as $gl => $items ) {
		echo '<optgroup label="' . esc_attr( $gl ) . '">';
		foreach ( $items as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select>';
}

function apprex_ld_page() {
	$gs   = apprex_ld_genres();
	$sts  = apprex_ld_statuses();
	$f    = array(
		'genre'  => sanitize_key( $_GET['genre'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
		'status' => sanitize_key( $_GET['status'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
	);
	$view = absint( $_GET['lead'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$base = admin_url( 'admin.php?page=apprex-leads' );
	?>
	<div class="wrap">
		<h1>AI営業リスト</h1>
		<p>Googleマップに載っているお店から、<b>アプリを作れそうな見込み先</b>をジャンル別に集めます。お店のホームページ（公開されている情報）をAIが調べて、点数と「提案のきっかけ」を付けます。</p>
		<?php if ( isset( $_GET['ldmsg'] ) ) : // phpcs:ignore ?>
			<div class="notice notice-info inline"><p><?php echo esc_html( rawurldecode( wp_unslash( $_GET['ldmsg'] ) ) ); // phpcs:ignore ?></p></div>
		<?php endif; ?>
		<?php if ( '' === apprex_gp_key() ) : ?>
			<div class="notice notice-warning inline"><p>はじめに、この画面のいちばん下で Google の APIキーを入れてください。</p></div>
		<?php endif; ?>

		<?php
		if ( $view && 'apprex_lead' === get_post_type( $view ) ) {
			apprex_ld_detail( $view );
			echo '</div>';
			return;
		}
		// まとめ。
		$all = get_posts( array( 'post_type' => 'apprex_lead', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$cnt = array_fill_keys( array_keys( $sts ), 0 );
		$byg = array();
		foreach ( $all as $id ) {
			$s = (string) get_post_meta( $id, 'apprex_ld_status', true );
			$g = (string) get_post_meta( $id, 'apprex_ld_genre', true );
			if ( isset( $cnt[ $s ] ) ) {
				$cnt[ $s ]++;
			}
			if ( ! isset( $byg[ $g ] ) ) {
				$byg[ $g ] = array( 0, 0, 0 );
			}
			$byg[ $g ][0]++;
			$byg[ $g ][1] += 'won' === $s ? 1 : 0;
			$byg[ $g ][2] += 'lost' === $s ? 1 : 0;
		}
		?>
		<div style="display:flex;flex-wrap:wrap;gap:8px;margin:12px 0">
			<a class="button<?php echo '' === $f['status'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url( remove_query_arg( 'status' ) ); ?>">すべて（<?php echo count( $all ); ?>）</a>
			<?php foreach ( $sts as $k => $l ) : ?>
				<a class="button<?php echo $f['status'] === $k ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'status', $k ) ); ?>"><?php echo esc_html( $l . '（' . $cnt[ $k ] . '）' ); ?></a>
			<?php endforeach; ?>
		</div>

		<h2>探す</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff;border:1px solid #dcdcde;padding:12px;border-radius:8px;max-width:1000px">
			<input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="collect"><?php wp_nonce_field( 'apprex_ld' ); ?>
			<label>ジャンル <?php apprex_ld_genre_select( 'genre', '' ); ?></label>
			<label>または言葉で <input name="kw" placeholder="例：ボルダリング、整骨院" style="width:170px"></label>
			<?php $apprex_la = (array) get_user_meta( get_current_user_id(), 'apprex_ld_last_area', true ); ?>
			<label>地域 <select name="pref" class="ld-pref" data-cur="<?php echo esc_attr( $apprex_la[0] ?? '' ); ?>" required></select></label>
			<select name="city" class="ld-city" data-cur="<?php echo esc_attr( $apprex_la[1] ?? '' ); ?>" aria-label="市区町村"></select>
			<label>駅・地名（なくてもOK） <input name="place" placeholder="例：渋谷駅" style="width:120px"></label>
			<label>件数 <select name="max"><option value="20">20件</option><option value="40">40件</option><option value="60">60件</option></select></label>
			<?php submit_button( 'Googleマップで探す', 'primary', 'submit', false ); ?>
			<span class="description">今月の Google の呼び出し：<?php echo (int) apprex_gp_usage(); ?> / <?php echo (int) apprex_gp_cap(); ?>回（20件ごとに1回）</span>
		</form>
		<?php
		$apprex_dbg = get_option( 'apprex_ld_last_debug' );
		if ( is_array( $apprex_dbg ) && ! empty( $apprex_dbg[1] ) ) :
			?>
			<details style="max-width:1000px;margin-top:8px"><summary style="cursor:pointer">前回の検索の中身（<?php echo esc_html( wp_date( 'n/j G:i', (int) $apprex_dbg[0] ) ); ?>）</summary>
				<table class="widefat striped" style="margin-top:6px"><thead><tr><th>Googleで探した言葉</th><th>結果</th><th>Googleからの返事</th></tr></thead><tbody>
				<?php foreach ( (array) $apprex_dbg[1] as $d ) : ?>
					<tr><td><?php echo esc_html( $d[0] ); ?></td><td><?php echo (int) $d[2]; ?>件</td><td><?php echo esc_html( 200 === (int) $d[1] ? 'OK' : ( $d[1] ? 'HTTP ' . $d[1] . '：' : '' ) . apprex_gp_explain( $d[3] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			</details>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:6px">
			<input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="gptest"><?php wp_nonce_field( 'apprex_ld' ); ?>
			<?php submit_button( 'Google につながるか確かめる', 'small', 'submit', false ); ?>
		</form>

		<h2>リスト</h2>
		<form method="get" style="margin-bottom:8px">
			<input type="hidden" name="page" value="apprex-leads">
			<?php if ( '' !== $f['status'] ) : ?><input type="hidden" name="status" value="<?php echo esc_attr( $f['status'] ); ?>"><?php endif; ?>
			<?php apprex_ld_genre_select( 'genre', $f['genre'], 'ジャンル：すべて', 'this.form.submit()' ); ?>
		</form>
		<?php if ( function_exists( 'apprex_ldq_box' ) ) { apprex_ldq_box( $f ); } ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="research"><?php wp_nonce_field( 'apprex_ld' ); ?>
			<table class="widefat striped">
				<thead><tr><td style="width:24px"><input type="checkbox" onclick="document.querySelectorAll('.ldck').forEach(function(c){c.checked=this.checked}.bind(this))" aria-label="すべて選ぶ"></td><th>点数</th><th>お店</th><th>ジャンル・地域</th><th>口コミ</th><th>ホームページ・メール</th><th>アプリに向く理由・提案のきっかけ</th><th>状態</th></tr></thead>
				<tbody>
				<?php
				$ids = apprex_ld_query( $f );
				if ( ! $ids ) {
					echo '<tr><td colspan="8">まだありません。上の「探す」から集めてください。</td></tr>';
				}
				foreach ( array_slice( $ids, 0, 200 ) as $id ) :
					$g     = apprex_ld_google( $id );
					$st    = (string) get_post_meta( $id, 'apprex_ld_status', true );
					$sc    = (int) get_post_meta( $id, 'apprex_ld_score', true );
					$why   = (array) get_post_meta( $id, 'apprex_ld_why', true );
					$hook  = (string) get_post_meta( $id, 'apprex_ld_hook', true );
					$em    = apprex_ld_meta_list( $id, 'apprex_ld_emails' );
					$ng    = (int) get_post_meta( $id, 'apprex_ld_ng', true );
					$res   = (int) get_post_meta( $id, 'apprex_ld_researched', true );
					$genre = (string) get_post_meta( $id, 'apprex_ld_genre', true );
					$col   = $sc >= 70 ? '#00a32a' : ( $sc >= 50 ? '#dba617' : '#8c8f94' );
					?>
					<tr>
						<td><input type="checkbox" class="ldck" name="ids[]" value="<?php echo (int) $id; ?>" aria-label="選ぶ"></td>
						<td><b style="font-size:16px;color:<?php echo esc_attr( $col ); ?>"><?php echo (int) $sc; ?></b></td>
						<td><a href="<?php echo esc_url( add_query_arg( 'lead', $id, $base ) ); ?>"><b><?php echo esc_html( $g ? $g['name'] : '（Google の情報を読み直してください）' ); ?></b></a>
							<?php if ( $g && ! empty( $g['maps'] ) ) : ?><br><a href="<?php echo esc_url( $g['maps'] ); ?>" target="_blank" rel="noopener" style="font-size:12px">Googleマップ</a><?php endif; ?>
							<?php if ( $g && $g['phone'] ) : ?><br><small><?php echo esc_html( $g['phone'] ); ?></small><?php endif; ?></td>
						<td><?php echo esc_html( ( $gs[ $genre ][0] ?? $genre ) ); ?><br><small><?php echo esc_html( $g ? mb_substr( $g['address'], 0, 26 ) : (string) get_post_meta( $id, 'apprex_ld_area', true ) ); ?></small></td>
						<td><?php echo $g ? esc_html( $g['reviews'] . '件' . ( $g['rating'] ? '・★' . $g['rating'] : '' ) ) : '—'; ?></td>
						<td style="font-size:12px"><?php echo $g && $g['site'] ? '<a href="' . esc_url( $g['site'] ) . '" target="_blank" rel="noopener">サイト</a>' : 'サイトなし'; ?>
							<?php echo $em ? '<br>' . esc_html( implode( ', ', $em ) ) : ( get_post_meta( $id, 'apprex_ld_form', true ) ? '<br><span style="color:#2271b1">問い合わせフォームあり</span>' : ( $res ? '<br><span style="color:#8c8f94">公開メールなし</span>' : '' ) ); ?>
							<?php echo $ng ? '<br><b style="color:#d63638">営業お断り</b>' : ''; ?></td>
						<td style="font-size:12px;max-width:420px"><?php echo esc_html( implode( '・', $why ) ); ?><?php echo '' !== $hook ? '<div style="margin-top:4px;color:#1d2327;background:#f0f6fc;padding:4px 6px;border-radius:4px">💡 ' . esc_html( $hook ) . '</div>' : ''; ?>
							<?php echo ! $res ? '<div style="color:#8c8f94">まだ調べていません</div>' : ''; ?></td>
						<td><?php echo esc_html( $sts[ $st ] ?? $st ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><?php submit_button( 'チェックしたお店のホームページを調べる（AI）', 'secondary', 'submit', false ); ?>
				<?php if ( function_exists( 'apprex_ldm_box' ) ) : ?><button class="button" name="do" value="mdraft">チェックしたお店のメールの下書きを作る（AI・10件まで）</button><?php endif; ?></p>
		</form>

		<h2>ジャンルごとの成果</h2>
		<table class="widefat striped" style="max-width:600px"><thead><tr><th>ジャンル</th><th>リスト</th><th>成約</th><th>失注</th></tr></thead><tbody>
		<?php
		if ( ! $byg ) {
			echo '<tr><td colspan="4">まだありません。</td></tr>';
		}
		foreach ( $byg as $g => $v ) {
			echo '<tr><td>' . esc_html( $gs[ $g ][0] ?? $g ) . '</td><td>' . (int) $v[0] . '</td><td>' . (int) $v[1] . '</td><td>' . (int) $v[2] . '</td></tr>';
		}
		?>
		</tbody></table>

		<?php if ( function_exists( 'apprex_ldm_box' ) ) { apprex_ldm_box(); } ?>
		<?php apprex_ld_auto_box(); ?>
		<?php apprex_ld_area_script(); ?>

		<h2>設定</h2>
		<form method="post" action="options.php" style="max-width:900px">
			<?php settings_fields( 'apprex_leads' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="apprex_gp_key">Google の APIキー</label></th><td>
					<input type="password" id="apprex_gp_key" name="apprex_gp_key" class="regular-text" value="" placeholder="<?php echo '' !== apprex_gp_key() ? '入っています（変えるときだけ入力）' : 'AIza...'; ?>" autocomplete="new-password">
					<p class="description">Google Cloud で「Places API (New)」を有効にして作ったAPIキーです。キーの「アプリケーションの制限」で「IPアドレス」をこのサーバーにすると安全です。</p>
				</td></tr>
				<tr><th><label for="apprex_gp_cap">月の上限</label></th><td>
					<select id="apprex_gp_cap" name="apprex_gp_cap"><?php $apprex_caps = array_unique( array_merge( array( 100, 200, 300, 500, 1000, 2000, 3000, 5000 ), array( apprex_gp_cap() ) ) ); sort( $apprex_caps ); foreach ( $apprex_caps as $n ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( apprex_gp_cap(), $n ); ?>><?php echo (int) $n; ?>回</option><?php endforeach; ?></select>
					<p class="description">Google を呼び出す回数の月の上限です（検索20件で1回、読み直し1件で1回）。上限に達したら止まります。Google Cloud 側でも予算のアラートを設定してください。</p>
				</td></tr>
			</table>
			<?php submit_button( '設定を保存' ); ?>
		</form>
		<p class="description">Google の規約に合わせて、店名・住所・電話などの Google の情報は最長30日だけ持ち、古くなったら読み直します。ずっと残すのは Place ID と、こちらで調べた情報・営業の記録です。<br>
		ホームページは公開されているページだけを、1店につき8ページまで読みます（お問い合わせ・特定商取引法・会社概要のページと、運営会社のサイト）。個人情報・採用の窓口のアドレスは使いません。「営業お断り」と書いてあるお店は、自動で「対象外」にします。</p>
	</div>
	<?php
}

/** 1件の詳しい画面。 */
function apprex_ld_detail( $id ) {
	$gs   = apprex_ld_genres();
	$sts  = apprex_ld_statuses();
	$g    = apprex_ld_google( $id );
	$st   = (string) get_post_meta( $id, 'apprex_ld_status', true );
	$log  = array_reverse( (array) get_post_meta( $id, 'apprex_ld_log', true ) );
	$flg  = (array) get_post_meta( $id, 'apprex_ld_flags', true );
	$labs = array( 'app' => 'アプリあり', 'line' => 'LINE公式あり', 'insta' => 'Instagramあり', 'web_book' => 'ネット予約あり', 'phone_only' => '予約は電話だけ', 'paper' => '紙のポイントカード' );
	?>
	<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=apprex-leads' ) ); ?>">← リストに戻る</a></p>
	<h2 style="margin-top:0"><?php echo esc_html( $g ? $g['name'] : '（Google の情報を読み直してください）' ); ?>　<span style="color:#00a32a"><?php echo (int) get_post_meta( $id, 'apprex_ld_score', true ); ?>点</span></h2>
	<table class="form-table" role="presentation" style="max-width:900px">
		<tr><th>ジャンル</th><td><?php echo esc_html( $gs[ get_post_meta( $id, 'apprex_ld_genre', true ) ][0] ?? '' ); ?></td></tr>
		<?php if ( $g ) : ?>
			<tr><th>住所・電話</th><td><?php echo esc_html( $g['address'] . '　' . $g['phone'] ); ?></td></tr>
			<tr><th>口コミ</th><td><?php echo esc_html( $g['reviews'] . '件・★' . $g['rating'] ); ?>　<a href="<?php echo esc_url( $g['maps'] ); ?>" target="_blank" rel="noopener">Googleマップで見る</a></td></tr>
			<tr><th>ホームページ</th><td><?php echo $g['site'] ? '<a href="' . esc_url( $g['site'] ) . '" target="_blank" rel="noopener">' . esc_html( $g['site'] ) . '</a>' : 'なし'; ?></td></tr>
		<?php endif; ?>
		<?php
		$apprex_src  = (array) get_post_meta( $id, 'apprex_ld_email_src', true );
		$apprex_kl   = array( 'shop' => 'お店のページ', 'site' => 'サイトのトップ', 'contact' => 'お問い合わせのページ', 'law' => '特定商取引法のページ', 'company' => '会社概要のページ', 'privacy' => 'プライバシーのページ', 'operator' => '運営会社のサイト' );
		$apprex_op   = (string) get_post_meta( $id, 'apprex_ld_operator', true );
		$apprex_ops  = (string) get_post_meta( $id, 'apprex_ld_op_site', true );
		$apprex_form = (string) get_post_meta( $id, 'apprex_ld_form', true );
		?>
		<tr><th>公開メール</th><td>
			<?php $apprex_ems = apprex_ld_meta_list( $id, 'apprex_ld_emails' ); ?>
			<?php if ( ! $apprex_ems ) : ?>—<?php endif; ?>
			<?php foreach ( $apprex_ems as $apprex_e ) : ?>
				<div><?php echo esc_html( $apprex_e ); ?><?php if ( ! empty( $apprex_src[ $apprex_e ] ) ) : ?> <small style="color:#646970">（<a href="<?php echo esc_url( $apprex_src[ $apprex_e ][0] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $apprex_kl[ $apprex_src[ $apprex_e ][1] ] ?? 'ページ' ); ?></a>で見つけました）</small><?php endif; ?></div>
			<?php endforeach; ?>
			<?php echo get_post_meta( $id, 'apprex_ld_ng', true ) ? ' <b style="color:#d63638">「営業お断り」の記載あり</b>' : ''; ?></td></tr>
		<?php if ( '' !== $apprex_op || '' !== $apprex_ops ) : ?>
			<tr><th>運営会社</th><td><?php echo esc_html( $apprex_op ?: '—' ); ?><?php echo '' !== $apprex_ops ? '　<a href="' . esc_url( $apprex_ops ) . '" target="_blank" rel="noopener">運営会社のサイト</a>' : ''; ?></td></tr>
		<?php endif; ?>
		<?php if ( '' !== $apprex_form ) : ?>
			<tr><th>問い合わせフォーム</th><td><a href="<?php echo esc_url( $apprex_form ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $apprex_form ); ?></a></td></tr>
		<?php endif; ?>
		<tr><th>ホームページで分かったこと</th><td><?php
			$on = array();
			foreach ( $labs as $k => $l ) {
				if ( ! empty( $flg[ $k ] ) ) {
					$on[] = $l;
				}
			}
			echo esc_html( $on ? implode( '・', $on ) : '—' );
		?></td></tr>
		<tr><th>アプリに向く理由</th><td><?php echo esc_html( implode( '・', (array) get_post_meta( $id, 'apprex_ld_why', true ) ) ); ?><br><small><?php echo esc_html( $gs[ get_post_meta( $id, 'apprex_ld_genre', true ) ][3] ?? '' ); ?></small></td></tr>
		<tr><th>提案のきっかけ（AI）</th><td><?php echo esc_html( (string) get_post_meta( $id, 'apprex_ld_hook', true ) ?: '— （「調べる」でAIが書きます）' ); ?></td></tr>
	</table>
	<div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 16px">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="research"><input type="hidden" name="ids[]" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ld' ); ?><?php submit_button( 'ホームページを調べる（AI）', 'secondary', 'submit', false ); ?></form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="refresh"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ld' ); ?><?php submit_button( 'Google の情報を読み直す', 'secondary', 'submit', false ); ?></form>
	</div>
	<h3>状態を変える</h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
		<input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?php echo (int) $id; ?>"><?php wp_nonce_field( 'apprex_ld' ); ?>
		<select name="status"><?php foreach ( $sts as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $st, $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
		<input name="note" placeholder="メモ（例：電話で話した・失注の理由など）" style="width:360px">
		<?php submit_button( '保存', 'primary', 'submit', false ); ?>
	</form>
	<?php if ( function_exists( 'apprex_ldm_detail_box' ) ) { apprex_ldm_detail_box( $id ); } ?>
	<h3>これまでの記録</h3>
	<table class="widefat striped" style="max-width:900px"><tbody>
	<?php
	if ( ! $log ) {
		echo '<tr><td>まだありません。</td></tr>';
	}
	foreach ( $log as $l ) {
		$u = ! empty( $l[2] ) ? get_userdata( (int) $l[2] ) : null;
		echo '<tr><td style="width:120px">' . esc_html( wp_date( 'Y/n/j G:i', (int) $l[0] ) ) . '</td><td>' . esc_html( $l[1] ) . '</td><td style="width:120px;color:#8c8f94">' . esc_html( $u ? $u->display_name : '' ) . '</td></tr>';
	}
	?>
	</tbody></table>
	<?php
}

/* =========================================================================
 * 自動で集める（決まった曜日に、ジャンル×地域を順番に探して、上位を調べて、結果を知らせる）
 * ====================================================================== */

/** 自動で探す組み合わせ（ジャンル×地域）。 */
function apprex_ld_auto_combos() {
	$gs    = (array) get_option( 'apprex_ld_auto_genres', array() );
	$areas = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) get_option( 'apprex_ld_auto_areas', '' ) ) ) ) );
	$out   = array();
	foreach ( $areas as $a ) {
		foreach ( $gs as $g ) {
			if ( isset( apprex_ld_genres()[ $g ] ) ) {
				$out[] = $g . '|' . $a;
			}
		}
	}
	return $out;
}

/** 今日は動かす日か。 */
function apprex_ld_auto_due() {
	$f = (string) get_option( 'apprex_ld_auto_freq', 'weekly' );
	$w = (int) wp_date( 'N' ); // 1=月。
	if ( 'daily' === $f ) {
		return $w <= 5; // 平日。
	}
	if ( 'twice' === $f ) {
		return in_array( $w, array( 1, 4 ), true ); // 月・木。
	}
	return 1 === $w; // 毎週月曜。
}

/**
 * 1回動かす：まだ探していない（いちばん前に探した）組み合わせを1つ探し、新しいお店の上位を調べて知らせる。
 *
 * @param bool $manual 管理画面の「いま1回動かす」。
 * @return string|WP_Error 結果の文。
 */
function apprex_ld_auto_run( $manual = false ) {
	if ( '' === apprex_gp_key() ) {
		return new WP_Error( 'ld', 'Google の APIキーが入っていません。' );
	}
	$combos = apprex_ld_auto_combos();
	if ( ! $combos ) {
		return new WP_Error( 'ld', '自動で探すジャンルと地域を選んでください。' );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$done = (array) get_option( 'apprex_ld_auto_done', array() );
	usort(
		$combos,
		function ( $a, $b ) use ( $done ) {
			return (int) ( $done[ $a ] ?? 0 ) <=> (int) ( $done[ $b ] ?? 0 );
		}
	);
	$pick = $combos[0];
	list( $genre, $area ) = explode( '|', $pick, 2 );
	$max = min( 60, max( 20, (int) get_option( 'apprex_ld_auto_max', 20 ) ) );
	$r   = apprex_ld_collect( $genre, $area, $max );
	$done[ $pick ] = time();
	update_option( 'apprex_ld_auto_done', $done, false );
	if ( is_wp_error( $r ) ) {
		update_option( 'apprex_ld_auto_last', array( time(), '失敗：' . $r->get_error_message() ), false );
		if ( function_exists( 'apprex_notify' ) ) {
			apprex_notify( 'sales', ':warning: AI営業リストの自動収集に失敗しました（' . $area . '・' . apprex_ld_genres()[ $genre ][0] . '）：' . $r->get_error_message() . "\n" . admin_url( 'admin.php?page=apprex-leads' ) );
		}
		return $r;
	}
	// 新しいお店のうち、点数の高いものからホームページを調べる。
	$ids = $r[2];
	usort(
		$ids,
		function ( $a, $b ) {
			return (int) get_post_meta( $b, 'apprex_ld_score', true ) <=> (int) get_post_meta( $a, 'apprex_ld_score', true );
		}
	);
	$nres  = min( 10, (int) get_option( 'apprex_ld_auto_research', 5 ) );
	$min   = (int) get_option( 'apprex_ld_auto_min', 60 );
	$check = 0;
	$mail  = 0;
	foreach ( $ids as $id ) {
		if ( $check >= $nres || (int) get_post_meta( $id, 'apprex_ld_score', true ) < $min ) {
			break;
		}
		if ( ! is_wp_error( apprex_ld_research( $id ) ) ) {
			$check++;
			if ( apprex_ld_meta_list( $id, 'apprex_ld_emails' ) && ! get_post_meta( $id, 'apprex_ld_ng', true ) ) {
				$mail++;
			}
		}
	}
	usort(
		$ids,
		function ( $a, $b ) {
			return (int) get_post_meta( $b, 'apprex_ld_score', true ) <=> (int) get_post_meta( $a, 'apprex_ld_score', true );
		}
	);
	$gl    = apprex_ld_genres()[ $genre ][0];
	$sum   = $area . 'の' . $gl . '：' . $r[0] . '件見つかり、新しく' . $r[1] . '件。調べた ' . $check . '件（公開メールあり ' . $mail . '件）';
	update_option( 'apprex_ld_auto_last', array( time(), $sum ), false );
	if ( function_exists( 'apprex_notify' ) && ( $r[1] || $manual ) ) {
		$lines = array( '🤖 *AI営業リスト（自動）*　' . $sum );
		foreach ( array_slice( $ids, 0, 3 ) as $i => $id ) {
			$g = apprex_ld_google( $id );
			$h = (string) get_post_meta( $id, 'apprex_ld_hook', true );
			$lines[] = ( $i + 1 ) . '. ' . ( $g ? $g['name'] : '' ) . '（' . (int) get_post_meta( $id, 'apprex_ld_score', true ) . '点）' . ( '' !== $h ? ' ' . mb_substr( $h, 0, 60 ) : '' );
		}
		$lines[] = admin_url( 'admin.php?page=apprex-leads&status=checked' );
		apprex_notify( 'sales', implode( "\n", $lines ) );
	}
	return $sum;
}

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'apprex_ld_cron' ) ) {
			$next = strtotime( wp_date( 'Y-m-d' ) . ' 10:20:00 ' . wp_timezone_string() );
			wp_schedule_event( $next > time() ? $next : $next + DAY_IN_SECONDS, 'daily', 'apprex_ld_cron' );
		}
	}
);
add_action(
	'apprex_ld_cron',
	function () {
		if ( get_option( 'apprex_ld_auto', 0 ) && apprex_ld_auto_due() ) {
			apprex_ld_auto_run();
		}
	}
);
add_filter(
	'apprex_cron_hooks',
	function ( $h ) {
		if ( get_option( 'apprex_ld_auto', 0 ) ) {
			$h['apprex_ld_cron'] = 'AI営業リストの自動収集';
		}
		return $h;
	}
);

/** 自動収集の設定の欄。 */
function apprex_ld_auto_box() {
	$on    = (int) get_option( 'apprex_ld_auto', 0 );
	$sel   = (array) get_option( 'apprex_ld_auto_genres', array() );
	$freq  = (string) get_option( 'apprex_ld_auto_freq', 'weekly' );
	$last  = get_option( 'apprex_ld_auto_last' );
	$next  = wp_next_scheduled( 'apprex_ld_cron' );
	$combo = count( apprex_ld_auto_combos() );
	$groups = array();
	foreach ( apprex_ld_genres() as $k => $g ) {
		$groups[ $g[4] ][ $k ] = $g[0];
	}
	?>
	<h2 id="auto">自動で集める</h2>
	<p>決まった曜日の朝10時ごろに、下で選んだ「ジャンル×地域」を<b>1回に1つずつ順番に</b>探してリストに入れます。新しく入ったお店のうち点数の高いものは、AIがホームページを調べます。結果は社内通知（Slack・LINE WORKS）に届きます。</p>
	<p>状態：<b><?php echo $on ? '✅ 自動で動きます' : '⏸ 止まっています'; ?></b>（組み合わせ <?php echo (int) $combo; ?> 通り）
	<?php if ( is_array( $last ) ) : ?><br>前回（<?php echo esc_html( wp_date( 'n/j G:i', (int) $last[0] ) ); ?>）：<?php echo esc_html( $last[1] ); ?><?php endif; ?></p>
	<form method="post" action="options.php" style="max-width:1000px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:8px 16px">
		<?php settings_fields( 'apprex_leads_auto' ); ?>
		<table class="form-table" role="presentation">
			<tr><th>自動で集める</th><td><label><input type="checkbox" name="apprex_ld_auto" value="1" <?php checked( $on ); ?>> 自動で集める</label></td></tr>
			<tr><th>曜日</th><td>
				<?php foreach ( array( 'weekly' => '毎週月曜', 'twice' => '週2回（月・木）', 'daily' => '平日の毎日' ) as $k => $l ) : ?>
					<label style="margin-right:14px"><input type="radio" name="apprex_ld_auto_freq" value="<?php echo esc_attr( $k ); ?>" <?php checked( $freq, $k ); ?>> <?php echo esc_html( $l ); ?></label>
				<?php endforeach; ?>
			</td></tr>
			<tr><th>ジャンル</th><td>
				<input type="search" placeholder="ジャンルを探す（例：ヨガ）" oninput="var q=this.value.trim();document.querySelectorAll('.ldg-item').forEach(function(l){l.style.display=!q||l.textContent.indexOf(q)>-1?'':'none';});document.querySelectorAll('.ldg-grp').forEach(function(d){if(q)d.open=true;});" style="width:260px;margin-bottom:8px">
				<?php foreach ( $groups as $gl => $items ) : ?>
					<?php $apprex_n = count( array_intersect( array_keys( $items ), $sel ) ); ?>
					<details class="ldg-grp" style="margin:0 0 6px;border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px"<?php echo $apprex_n ? ' open' : ''; ?>>
						<summary style="cursor:pointer;font-weight:700"><?php echo esc_html( $gl ); ?>（<?php echo (int) count( $items ); ?>）<?php echo $apprex_n ? '<span style="color:#2271b1">　選択中 ' . (int) $apprex_n . '</span>' : ''; ?></summary>
						<div style="display:flex;flex-wrap:wrap;gap:4px 14px;margin-top:6px">
						<?php foreach ( $items as $k => $l ) : ?>
							<label class="ldg-item" style="white-space:nowrap"><input type="checkbox" name="apprex_ld_auto_genres[]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, $sel, true ) ); ?>> <?php echo esc_html( $l ); ?></label>
						<?php endforeach; ?>
						</div>
					</details>
				<?php endforeach; ?>
			</td></tr>
			<tr><th><label for="ld_areas">地域</label></th><td>
				<textarea id="ld_areas" name="apprex_ld_auto_areas" hidden><?php echo esc_textarea( (string) get_option( 'apprex_ld_auto_areas', '' ) ); ?></textarea>
				<div id="ld_chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px"></div>
				<select class="ld-pref" id="ld_apref" aria-label="都道府県"></select>
				<input type="search" id="ld_acityq" placeholder="市区町村を探す（例：いわき）" style="width:220px">
				<div id="ld_acities" style="max-height:240px;overflow:auto;border:1px solid #dcdcde;border-radius:6px;padding:6px 10px;margin-top:6px;display:flex;flex-wrap:wrap;gap:4px 14px;align-content:flex-start"></div>
				<p class="description">都道府県を選んで、市区町村にチェックを入れます（いくつでも）。選んだ地域は上に並びます。×で外せます。</p></td></tr>
			<tr><th>1回に探す数</th><td><select name="apprex_ld_auto_max"><?php foreach ( array( 20, 40, 60 ) as $n ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( (int) get_option( 'apprex_ld_auto_max', 20 ), $n ); ?>><?php echo (int) $n; ?>件</option><?php endforeach; ?></select>
				　AIが調べる数 <select name="apprex_ld_auto_research"><?php for ( $n = 0; $n <= 10; $n++ ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( (int) get_option( 'apprex_ld_auto_research', 5 ), $n ); ?>><?php echo $n ? (int) $n . '件まで' : '調べない'; ?></option><?php endfor; ?></select>（<select name="apprex_ld_auto_min"><?php for ( $n = 0; $n <= 90; $n += 10 ) : ?><option value="<?php echo (int) $n; ?>" <?php selected( (int) get_option( 'apprex_ld_auto_min', 60 ), $n ); ?>><?php echo $n ? (int) $n . '点以上' : 'すべて'; ?></option><?php endfor; ?></select> のお店）
				<p class="description">1回の費用の目安：Google の呼び出し 1〜3回＋AI（調べる数ぶん）。月の上限の回数に達したら止まります。</p></td></tr>
		</table>
		<?php submit_button( '自動収集の設定を保存' ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
		<input type="hidden" name="action" value="apprex_ld"><input type="hidden" name="do" value="autorun"><?php wp_nonce_field( 'apprex_ld' ); ?>
		<?php submit_button( 'いま1回動かす（次の組み合わせを探す）', 'secondary', 'submit', false ); ?>
		<?php if ( $on && $next ) : ?><span class="description">　次の自動の確認：<?php echo esc_html( wp_date( 'n/j G:i', $next ) ); ?></span><?php endif; ?>
	</form>
	<?php
}
