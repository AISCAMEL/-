<?php
/**
 * アプリの作り方マニュアル ＋ ページに合わせたチャットボット。
 *
 * - apprex_manual_sections()：マニュアルの本文（/manual/ の画面・チャットの知識・AIスタジオのヘルプで共通）
 * - apprex_chat_page_context()：表示中のページに合わせた、チャットのあいさつ・質問ボタン・リンク
 * - apprex_chat_page_prompt()：ページ（AIスタジオでは今いる工程）に合わせてAIへ渡す追加の指示
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** マニュアルのURL（# で各章へ）。 */
function apprex_manual_url( $anchor = '' ) {
	return apprex_page_url( 'manual' ) . ( $anchor ? '#' . $anchor : '' );
}

/**
 * マニュアルの本文。
 * 各章：id / group / title / lead / steps（[見出し, 説明]）/ tips / quick（チャットの質問ボタン）。
 *
 * @return array<int,array>
 */
function apprex_manual_sections() {
	$s = array(
		array(
			'id'    => 'register',
			'group' => 'studio',
			'title' => '無料会員登録',
			'lead'  => '会社名・お名前・メールアドレス・パスワードを入れるだけで、すぐにAIスタジオが使えます。',
			'steps' => array(
				array( '登録フォームに入力', '会社名・店名、お名前、メールアドレス、パスワード（8文字以上）を入力して「無料で登録してAIスタジオを使う」を押します。' ),
				array( 'そのままAIスタジオへ', '登録が終わると自動でログインし、AIデザインの画面が開きます。確認メールも届きます。' ),
				array( '次回からはログイン', 'AIスタジオのページの「登録済みの方はこちら（ログイン）」から、メールアドレスとパスワードで入ります。' ),
			),
			'tips'  => array( '作った内容はアカウントに自動で保存されます。パソコンで作って、スマホで続きを見ることもできます。', 'パスワードを忘れたときは、ログイン欄の「パスワードをお忘れの方」から再設定できます。' ),
			'quick' => array( '登録のしかたを教えて', '登録は本当に無料？' ),
		),
		array(
			'id'    => 'design',
			'group' => 'studio',
			'title' => 'AIデザイナーで画面を作る',
			'lead'  => 'どんなアプリにしたいかを自由に書くと、AIが色・雰囲気・レイアウト・キャッチコピー・ボタン名をまとめて作り直します。',
			'steps' => array(
				array( '要望を書く', '「AIデザイナーに頼む」の欄に、誰向けのアプリか、雰囲気、色、いちばん押してほしいボタンなどを書きます。例文のボタンを押すと入力欄に入ります。' ),
				array( '「AIで作り直す」を押す', '数秒で画面の設定が変わり、下のプレビューにすぐ反映されます。変更の内容は「修正の履歴」に残ります。' ),
				array( '気に入るまで繰り返す', '「もっと文字を大きく」「写真を大きく」のように、何度でも頼み直せます。費用はかかりません。' ),
			),
			'tips'  => array( '書き方のコツ：①誰向けか（例：30〜50代の会社員）②雰囲気（高級感・かわいい・ナチュラルなど）③色 ④一番使ってほしい機能、の4つを入れると狙いどおりになりやすくなります。', '自動で直せない要望は、担当者への申し送りとして記録され、相談のときに一緒に届きます。' ),
			'quick' => array( '要望はどう書けばいい？', '色や雰囲気を変えたい' ),
		),
		array(
			'id'    => 'images',
			'group' => 'studio',
			'title' => '本格デザイン画像を作る',
			'lead'  => '画面デザイン（5画面）・アプリアイコン・メインビジュアルを、AIが本格的な画像で作ります。',
			'steps' => array(
				array( '作りたい画像を選ぶ', '「本格デザイン画像」の3つのボタンから選びます。「続けて画面デザイン画像も生成する」にチェックしておくと、AIで作り直したあと自動で画面デザインも作ります。' ),
				array( '30〜60秒待つ', '「AIがデザインしています…」と表示されている間に作成します。できた画像は右側に大きく表示され、押すと拡大できます。' ),
				array( '気に入った案を採用', '「この案を採用」を押すと、企画書・開発の相談・担当者の画面に、その画像が使われます。過去の案は下の小さな画像から選び直せます。' ),
			),
			'tips'  => array( '画像は1日10回まで作れます（無料）。残りの回数はボタンの下に表示されます。', 'AIの画像はデザインの方向性を決めるためのものです。制作では、これをもとにデザイナーが仕上げます。' ),
			'quick' => array( '画像の作り方を教えて', '採用するとどうなる？' ),
		),
		array(
			'id'    => 'tune',
			'group' => 'studio',
			'title' => '細かく調整する',
			'lead'  => 'AIデザイナーの下で、お店の情報・雰囲気・色・ロゴや写真を直接変えられます。変更はすぐプレビューに反映されます。',
			'steps' => array(
				array( 'お店の情報', '業種・店名・目的・お客様の層を入れます。業種を変えると、その業種向けの画面とおすすめ機能に切り替わります。' ),
				array( '雰囲気と色', '雰囲気（高級感・かわいい・ナチュラル・スポーティ・信頼感）と6色から選びます。「好きな色」でブランドカラーも指定できます。' ),
				array( 'ロゴと写真', 'ロゴとメイン写真をのせると、プレビューに反映されます（5MB以下の画像）。' ),
				array( '画面と別案', '上のタブでホーム・メニュー・クーポン・予約・会員証を切り替え、「全画面を並べる」で一覧にできます。AIの別案（グリッド・リスト・大きな写真）も選べます。' ),
			),
			'tips'  => array( 'ロゴは背景が透明なPNGがきれいに表示されます。', '「デザインを最初から」で、デザインと仕様だけを最初の状態に戻せます（相談・予約の記録は残ります）。' ),
			'quick' => array( 'ロゴや写真をのせたい', 'プレビューの切り替え方' ),
		),
		array(
			'id'    => 'spec',
			'group' => 'studio',
			'title' => 'AI仕様書と概算',
			'lead'  => 'デザインを決めると、AIが仕様書と概算費用を作ります。機能のオン・オフで概算が変わります。',
			'steps' => array(
				array( '「このデザインで決定して仕様書へ」を押す', 'デザインの画面から仕様書の画面に進みます。' ),
				array( '機能を選ぶ', '標準の機能、オプション、オリジナル開発が必要な機能に分かれています。オリジナル開発の機能を選ぶと、オリジナル開発プランとして判定されます。' ),
				array( '概算を確認', '月額の目安と、ノーコードで作れるかどうかが表示されます。正式な金額はミーティングで確定します。' ),
			),
			'tips'  => array( '迷った機能はオンのままで大丈夫です。ミーティングで必要かどうか一緒に整理します。' ),
			'quick' => array( '機能はどう選べばいい？', '概算の見方を教えて' ),
		),
		array(
			'id'    => 'docs',
			'group' => 'studio',
			'title' => '企画書・仕様書・画面構成図',
			'lead'  => 'デザインと仕様から、社内説明にそのまま使える3種類の書類ができます。',
			'steps' => array(
				array( '書類を切り替える', '上のタブで企画書・仕様書・画面構成図を切り替えます。' ),
				array( 'AIで企画書の文章を作る', 'ボタンを押すと、背景と目的・対象のお客様・コンセプト・機能の効果・指標・アプリを広める施策を、AIが文章にします。何度でも書き直せます。' ),
				array( 'コピーして使う', '「本文をコピー」で文章をコピーし、社内資料やメールに貼り付けられます。' ),
			),
			'tips'  => array( '採用したAIデザイン画像は、企画書の「アプリのイメージ」に入ります。' ),
			'quick' => array( '企画書をAIで作るには？', '書類はどう使う？' ),
		),
		array(
			'id'    => 'consult',
			'group' => 'studio',
			'title' => '開発を相談',
			'lead'  => '作ったデザイン・仕様書・採用した画像を、そのまま担当者に送ります。説明し直す必要はありません。',
			'steps' => array(
				array( '進め方を選ぶ', 'セルフプラン（自分で登録・初期費用0円）、おまかせプラン（制作を代行）、オリジナル開発から選びます。オリジナル開発の機能を選んでいる場合は自動でオリジナル開発になります。' ),
				array( '伝えたいことを書く', '公開したい時期や気になる点を書いておくと、ミーティングがスムーズです。' ),
				array( '送信', '「相談を送ってミーティングを予約する」を押すと、続けてミーティングの日時を選べます。' ),
			),
			'tips'  => array( 'AIスタジオから相談すると、おまかせプランの制作代行費が半額になるキャンペーンの対象です（毎月先着10社）。' ),
			'quick' => array( 'どのプランを選べばいい？', '相談すると費用はかかる？' ),
		),
		array(
			'id'    => 'meeting',
			'group' => 'studio',
			'title' => 'オンラインミーティング',
			'lead'  => '平日10時〜18時、60分ほど。作ったデザインと仕様書を画面で見ながら、正式なお見積りまでご案内します。',
			'steps' => array(
				array( '日付と時間を選ぶ', '平日の日付と時間を選んで「この日時で予約する」を押します。' ),
				array( '確認メールを受け取る', 'Google Meet の参加URLを含む確認のご連絡をお送りします。' ),
				array( '当日', 'AIスタジオで作った内容をもとに、細かいご要望を伺います。' ),
			),
			'tips'  => array( '日時の変更は、「制作の進捗」のメッセージか確認メールへの返信でお知らせください。' ),
			'quick' => array( 'ミーティングの予約方法', '日時を変更したい' ),
		),
		array(
			'id'    => 'quote',
			'group' => 'studio',
			'title' => 'お見積り・ご入金',
			'lead'  => 'ミーティングのあと、担当者が正式なお見積りをお送りし、AIスタジオに表示されます。',
			'steps' => array(
				array( 'お見積りを確認', '「お見積りを確認してお支払いへ」から内容を確認します。参考にAIの概算も表示されます。' ),
				array( 'お支払い', 'クレジットカード（Square）または銀行振込でお支払いいただけます。契約とお支払いはマイページでも確認できます。' ),
				array( '制作開始', 'ご入金を確認すると制作が始まり、「制作の進捗」が見られるようになります。' ),
			),
			'tips'  => array( '正式な金額は、担当者からのお見積りが優先されます。' ),
			'quick' => array( '支払い方法は？', 'お見積りはいつ届く？' ),
		),
		array(
			'id'    => 'prod',
			'group' => 'studio',
			'title' => '制作の進捗と公開',
			'lead'  => 'キックオフ → デザイン確定 → 開発・登録 → テスト版の確認 → ストア申請 → 公開、の6つの工程で進みます。',
			'steps' => array(
				array( '進み具合を見る', '今どの工程か、いつでも確認できます。' ),
				array( '担当者とやりとり', '画面右のメッセージ欄から担当者に質問や要望を送れます。返信はメールでもお知らせします。' ),
				array( 'テスト版を確認', 'お手元のスマホでテスト版を触って、気になる点を伝えます。' ),
			),
			'tips'  => array( 'ストアの審査には1〜2週間ほどかかることがあります。' ),
			'quick' => array( '公開まであとどのくらい？', '担当者に連絡したい' ),
		),
		array(
			'id'    => 'prepare',
			'group' => 'guide',
			'title' => '公開までに準備するもの',
			'lead'  => '早めにそろえておくと、制作がスムーズに進みます。',
			'steps' => array(
				array( 'ロゴ', '背景が透明なPNGがおすすめです。' ),
				array( 'アプリアイコン用の画像', '1024×1024ピクセルの正方形。AIスタジオで作ったアイコンをもとにすることもできます。' ),
				array( '写真', 'お店・商品・サービスの写真。メインビジュアルには横長の写真が向いています。' ),
				array( 'お店・会社の情報', '住所・営業時間・定休日・電話番号・メニューや料金。' ),
				array( 'クーポン・特典の内容', '最初に配るクーポンや、ポイントの特典。' ),
				array( 'ストア掲載文', 'アプリ名と説明文。おまかせプランでは検索されやすい文章に整えます。' ),
				array( 'プライバシーポリシーのURL', 'ストアへの公開に必要です。' ),
				array( 'ストアの開発者アカウント', 'App Store・Google Play に公開するためのアカウント。取得方法は担当者がご案内します。' ),
			),
			'tips'  => array(),
			'quick' => array( '準備するものは？', 'アイコンのサイズは？' ),
		),
		array(
			'id'    => 'self',
			'group' => 'guide',
			'title' => 'セルフプランでの作り方',
			'lead'  => 'デザインと登録をご自身で進めるプランです（初期費用0円）。APPREXがチェックして公開までサポートします。',
			'steps' => array(
				array( 'AIスタジオで方向性を決める', 'AIデザイナーで画面と仕様書を作ります。' ),
				array( '相談・ミーティング', '進め方で「セルフプラン」を選んで相談し、ミーティングで内容を確認します。' ),
				array( '管理画面と制作マニュアルを受け取る', 'お申し込み後に、アプリの管理画面と画像付きの制作マニュアルをお渡しします。' ),
				array( '管理画面で登録', 'AIスタジオで決めたデザインに沿って、メニュー・クーポン・お知らせなどを登録します。' ),
				array( 'APPREXがチェック', '登録内容を確認し、直したほうがよい点をお伝えします。' ),
				array( 'ストア申請・公開', '審査を通して公開します。' ),
			),
			'tips'  => array( '途中から「おまかせ」に切り替えることもできます。' ),
			'quick' => array( 'セルフプランの進め方', '自分で作るのは難しい？' ),
		),
		array(
			'id'    => 'operate',
			'group' => 'guide',
			'title' => '公開後の運用のコツ',
			'lead'  => 'アプリは公開してからが本番です。続けやすい目安をまとめました。',
			'steps' => array(
				array( 'ダウンロードを案内', '店内のPOPやレシート、SNSにQRコードをのせて、来店したお客様に案内します。初回特典があると効果的です。' ),
				array( 'プッシュ通知', '週1〜2回が目安です。新着情報・空き状況・キャンペーンを届けます。送りすぎに注意します。' ),
				array( 'クーポン', '月1〜2回、来店や購入のきっかけになる特典を配ります。' ),
				array( '効果を見る', 'ダウンロード数、アプリを開いた人数、クーポンの利用数、予約数、再来店の割合を毎月見て、配信内容を見直します。' ),
			),
			'tips'  => array( 'おまかせプランには、公開後3ヶ月の運用サポート（お知らせ・プッシュ通知の作成と配信）が含まれます。' ),
			'quick' => array( '公開後は何をすればいい？', 'プッシュ通知の頻度は？' ),
		),
		array(
			'id'    => 'trouble',
			'group' => 'guide',
			'title' => '困ったときは',
			'lead'  => 'よくあるお問い合わせです。',
			'steps' => array(
				array( 'AI画像が作れない', '1日10回の上限に達しているか、準備中の場合があります。翌日に再度お試しいただくか、担当者にご相談ください。' ),
				array( '作った内容が消えた', 'ログインしているか確認してください。内容はアカウントに自動保存され、上部に「保存しました」と表示されます。' ),
				array( '別の端末で続きをしたい', '同じメールアドレスでログインすると、続きから使えます（ロゴ・写真は端末ごとの保存です）。' ),
				array( 'ログインできない', 'ログイン欄の「パスワードをお忘れの方」から再設定してください。' ),
			),
			'tips'  => array( '画面右下のチャットでいつでも質問できます。平日10〜18時は担当者にもつながります。' ),
			'quick' => array( 'AI画像が作れない', 'ログインできない' ),
		),
	);
	return apply_filters( 'apprex_manual_sections', $s );
}

/** マニュアルの1章を取得。 */
function apprex_manual_section( $id ) {
	foreach ( apprex_manual_sections() as $sec ) {
		if ( $sec['id'] === $id ) {
			return $sec;
		}
	}
	return null;
}

/** チャットのAIに渡すマニュアル本文（テキスト）。 */
function apprex_manual_text( $ids = array() ) {
	$out = '';
	foreach ( apprex_manual_sections() as $sec ) {
		if ( $ids && ! in_array( $sec['id'], $ids, true ) ) {
			continue;
		}
		$out .= '## ' . $sec['title'] . '（' . apprex_manual_url( $sec['id'] ) . "）\n" . $sec['lead'] . "\n";
		foreach ( $sec['steps'] as $i => $st ) {
			$out .= ( $i + 1 ) . '. ' . $st[0] . '：' . $st[1] . "\n";
		}
		foreach ( $sec['tips'] as $t ) {
			$out .= '- ' . $t . "\n";
		}
	}
	return $out;
}

/** AIスタジオの画面（studio.js の view）→ マニュアルの章。 */
function apprex_studio_step_sections() {
	return array(
		'design'  => array( 'design', 'images', 'tune' ),
		'spec'    => array( 'spec' ),
		'docs'    => array( 'docs' ),
		'consult' => array( 'consult' ),
		'meeting' => array( 'meeting' ),
		'quote'   => array( 'quote' ),
		'prod'    => array( 'prod' ),
	);
}

/* -------------------------------------------------------------------------
 * ページに合わせたチャット
 * ---------------------------------------------------------------------- */

/**
 * 表示中のページのチャット設定（あいさつ・質問ボタン・リンク）。
 * key は REST /chat にそのまま送られ、apprex_chat_page_prompt() で使われる。
 *
 * @return array|null
 */
function apprex_chat_page_context() {
	if ( ! function_exists( 'apprex_rn_enabled' ) || ! apprex_rn_enabled() ) {
		return null; // 公開前プレビュー中の訪問者には、今までのチャットを出す。
	}
	$manual = array( 'label' => '作り方マニュアル', 'url' => apprex_manual_url() );
	$studio = array( 'label' => 'AIスタジオ（無料）', 'url' => apprex_studio_url() );

	if ( is_page_template( 'page-templates/page-studio.php' ) ) {
		if ( is_user_logged_in() ) {
			$steps = array();
			foreach ( apprex_studio_step_sections() as $view => $ids ) {
				$quick = array();
				foreach ( $ids as $id ) {
					$sec   = apprex_manual_section( $id );
					$quick = array_merge( $quick, $sec ? $sec['quick'] : array() );
				}
				$first          = apprex_manual_section( $ids[0] );
				$steps[ $view ] = array(
					'title' => $first ? $first['title'] : '',
					'quick' => array_slice( $quick, 0, 4 ),
					'link'  => array( 'label' => 'この画面の使い方', 'url' => apprex_manual_url( $ids[0] ) ),
				);
			}
			$name = wp_get_current_user()->display_name;
			return array(
				'key'   => 'studio',
				'hint'  => '使い方はAIガイドに聞けます',
				'greet' => $name . ' 様、AIスタジオのガイドです。今ご覧の画面の使い方や、デザインの頼み方など、なんでも聞いてください。',
				'steps' => $steps,
				'step'  => 'design',
				'links' => array( $manual ),
			);
		}
		return array(
			'key'   => 'studio-entry',
			'hint'  => 'AIスタジオについて聞いてみる',
			'greet' => 'AIスタジオでは、無料会員登録だけでアプリのデザインと仕様書をAIが作ります。登録は1分です。気になることを聞いてください。',
			'quick' => array( 'AIスタジオで何ができる？', '本当に無料？', '登録のしかたを教えて', '登録したあとの流れは？' ),
			'links' => array( $manual ),
		);
	}
	if ( is_page_template( 'page-templates/page-matching.php' ) ) {
		return array(
			'key'   => 'matching',
			'hint'  => 'マッチングアプリの費用を聞く',
			'greet' => 'マッチングアプリ開発のご相談ですね。費用・期間・必要な機能や届出について、お気軽にどうぞ。',
			'quick' => array( '費用の内訳は？', '公開までの期間は？', '必要な届出や許可は？', '見積りを依頼したい' ),
			'links' => array( $studio ),
		);
	}
	if ( is_page_template( 'page-templates/page-manual.php' ) ) {
		return array(
			'key'   => 'manual',
			'hint'  => '作り方をAIに聞く',
			'greet' => 'アプリの作り方マニュアルのガイドです。どの手順でも、わからないところを聞いてください。',
			'quick' => array( '準備するものは？', '画像の作り方を教えて', 'セルフプランの進め方', '公開後は何をすればいい？' ),
			'links' => array( $studio ),
		);
	}
	if ( is_front_page() ) {
		return array(
			'key'   => 'front',
			'hint'  => '料金や作り方、なんでもどうぞ',
			'greet' => 'こんにちは。APPREXのAIアシスタントです。アプリの料金、作り方、オリジナル開発のことなど、なんでも聞いてください。',
			'quick' => array( '料金を知りたい', '自分で作るか、おまかせか迷う', 'オリジナルアプリを作りたい', 'AIスタジオとは？', '作り方の流れを知りたい' ),
			'links' => array( $studio, $manual ),
		);
	}
	if ( is_page( array( 'pricing', 'estimate' ) ) ) {
		return array(
			'key'   => 'pricing',
			'hint'  => '料金の疑問にお答えします',
			'greet' => '料金のご質問にお答えします。プランの違いや、初期費用・月額の目安など、お気軽にどうぞ。',
			'quick' => array( 'セルフとおまかせの違いは？', '初期費用はいくら？', 'オリジナル開発の費用は？', '補助金は使える？' ),
			'links' => array( $studio ),
		);
	}
	if ( is_post_type_archive( 'case' ) || is_singular( 'case' ) ) {
		return array(
			'key'   => 'case',
			'post'  => is_singular( 'case' ) ? get_queried_object_id() : 0,
			'hint'  => '同じようなアプリを作るには？',
			'greet' => is_singular( 'case' ) ? '「' . get_the_title() . '」のような事例について、作り方や費用をご案内できます。' : '制作事例を見て気になったことを、お気軽にどうぞ。',
			'quick' => array( '同じようなアプリを作りたい', '費用と期間は？', 'うちの業種でも作れる？' ),
			'links' => array( $studio ),
		);
	}
	if ( is_singular( 'post' ) ) {
		return array(
			'key'   => 'post',
			'post'  => get_queried_object_id(),
			'hint'  => '記事の内容で相談する',
			'greet' => 'この記事「' . get_the_title() . '」の内容について、わからないことや自社の場合の相談ができます。',
			'quick' => array( 'この記事を自社に当てはめると？', '料金を知りたい', '作り方の流れを知りたい' ),
			'links' => array( $studio, $manual ),
		);
	}
	return null;
}

/**
 * ページ（AIスタジオでは今いる工程）に合わせた、AIへの追加の指示。
 *
 * @param string $key  ページの種類。
 * @param string $step AIスタジオの画面。
 * @param int    $post 投稿ID（事例・記事）。
 * @return string
 */
function apprex_chat_page_prompt( $key, $step = '', $post = 0 ) {
	$p = '';
	switch ( $key ) {
		case 'studio':
			$map  = apprex_studio_step_sections();
			$ids  = isset( $map[ $step ] ) ? $map[ $step ] : array( 'design' );
			$sec  = apprex_manual_section( $ids[0] );
			$p   .= "お客様はAIスタジオ（無料会員の作業画面）にログインしていて、今「" . ( $sec ? $sec['title'] : '' ) . "」の画面を見ています。\n";
			$p   .= "あなたはAIスタジオの使い方ガイドです。まず今の画面の操作を、ボタン名をそのまま使って手順で具体的に案内してください。必要なら該当するマニュアルのURLを添えます。\n";
			$p   .= "料金や契約の判断が必要な話は、開発の相談とオンラインミーティングで担当者が確定すると伝えます。\n\n# 今の画面のマニュアル\n" . apprex_manual_text( $ids );
			$p   .= "\n# AIスタジオ全体の流れ\n無料会員登録 → AIデザイン → AI仕様書 → 企画書・仕様書 → 開発を相談 → ミーティング予約 → お見積り・入金 → 制作の進捗 → 公開\n";
			break;
		case 'studio-entry':
			$p .= "お客様はAIスタジオの登録ページを見ています。AIスタジオで無料でできること（AIデザイン・本格デザイン画像・仕様書・企画書・開発の相談・ミーティング予約）と、登録の手順を案内し、登録をやさしく後押しします。\n\n" . apprex_manual_text( array( 'register', 'design', 'images' ) );
			break;
		case 'manual':
			$p .= "お客様は「アプリの作り方マニュアル」を見ています。マニュアルの内容に沿って、手順で具体的に答え、該当する章のURLを添えます。\n\n" . apprex_manual_text();
			break;
		case 'matching':
			$p .= "お客様はマッチングアプリ開発のページを見ています。\n- 開発費300万円〜（会員登録・プロフィール・検索・メッセージ・通知・管理画面などの基本機能）、月額保守7万円〜、最短1ヶ月〜。決済・ビデオ通話・本人確認などは個別見積り。\n- Flutterで iOS／Android を同時開発。ソースコード買い取りプランあり。NDA対応。\n- 恋活・婚活などの異性紹介はインターネット異性紹介事業の届出、中古品の売買（フリマ）は古物商許可、アプリ内で使えるポイントや前払いの仕組みは資金決済法の確認が必要になる場合がある。法的な最終判断は専門家への確認を勧め、断定しない。\n- 見積り依頼はページ内のフォーム、仕様のたたき台はAIスタジオで作れると案内する。\n";
			break;
		case 'front':
			$p .= "お客様はトップページを見ています。料金は「セルフプラン（初期費用0円・月額19,800円〜）」「おまかせプラン（制作代行300,000円〜、AIスタジオから相談で150,000円〜・毎月先着10社）」「オリジナル開発（個別見積り、マッチングアプリは300万円〜）」。迷っている方には、まず無料のAIスタジオでデザインと仕様書を作る流れを勧めます。\n";
			break;
		case 'pricing':
			$p .= "お客様は料金・見積もりのページを見ています。プランの違いと、正式な金額はミーティング後のお見積りで確定することを、わかりやすく説明します。\n";
			break;
		case 'case':
		case 'post':
			$obj = $post ? get_post( (int) $post ) : null;
			if ( $obj && 'publish' === $obj->post_status && in_array( $obj->post_type, array( 'case', 'post' ), true ) ) {
				$text = wp_strip_all_tags( strip_shortcodes( $obj->post_content ) );
				$p   .= "お客様は「" . get_the_title( $obj ) . "」の" . ( 'case' === $key ? '制作事例' : '記事' ) . "を見ています。内容の要約：" . mb_substr( preg_replace( '/\s+/', ' ', $text ), 0, 600 ) . "\nこの内容に沿って答え、自社に当てはめた相談はAIスタジオや無料相談へ案内します。\n";
			} else {
				$p .= "お客様は制作事例の一覧を見ています。業種に合う事例の探し方と、同じようなアプリの作り方（AIスタジオ→相談）を案内します。\n";
			}
			break;
	}
	return $p;
}

/**
 * AIの返答のあとに出すリンクボタン（ページ別）。
 *
 * @param string $key  ページの種類。
 * @param string $step AIスタジオの画面。
 * @return array
 */
function apprex_chat_page_links( $key, $step = '' ) {
	$studio  = array( 'label' => 'AIスタジオ（無料）', 'url' => apprex_studio_url() );
	$manual  = array( 'label' => '作り方マニュアル', 'url' => apprex_manual_url() );
	$meet    = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '';
	$meeting = $meet ? array( 'label' => 'ミーティング予約', 'url' => $meet ) : null;
	switch ( $key ) {
		case 'studio':
			$map = apprex_studio_step_sections();
			$ids = isset( $map[ $step ] ) ? $map[ $step ] : array( 'design' );
			return array( array( 'label' => 'この画面の使い方', 'url' => apprex_manual_url( $ids[0] ) ), array( 'label' => '困ったときは', 'url' => apprex_manual_url( 'trouble' ) ) );
		case 'studio-entry':
			return array( array( 'label' => '登録のしかた', 'url' => apprex_manual_url( 'register' ) ), $manual );
		case 'manual':
			return array_values( array_filter( array( $studio, array( 'label' => '準備するもの', 'url' => apprex_manual_url( 'prepare' ) ), $meeting ) ) );
		case 'matching':
			return array_values( array_filter( array( array( 'label' => '見積りを依頼', 'url' => apprex_page_url( 'matching-app' ) . '#lead' ), $studio, $meeting ) ) );
		case 'front':
		case 'pricing':
		case 'case':
		case 'post':
			return array_values( array_filter( array( $studio, $manual, $meeting ) ) );
	}
	return array();
}

