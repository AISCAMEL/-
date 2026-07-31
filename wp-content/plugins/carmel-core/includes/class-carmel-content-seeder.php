<?php
/**
 * 加盟店コンテンツの初期データ投入（始め方マニュアル・各種マニュアル・FAQ）。
 *
 * プラグイン有効化時に、加盟店ポータルが空にならないよう既定コンテンツを
 * carmel_content として投入する。冪等（option のバージョンで管理し、二重投入
 * しない）。投入物は `_carmel_seeded` メタで識別でき、本部は wp-admin から
 * 自由に編集・追加・削除できる。
 *
 * content_type:
 *   guide  … スタートガイド（始め方）。step_order で番号順に表示
 *   manual … マニュアル・資料
 *   faq    … よくある質問
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Content_Seeder {

	/** 投入バージョン（内容を増やしたら上げる）。 */
	const VERSION = 1;
	const OPTION  = 'carmel_content_seeded';

	/**
	 * 既定コンテンツを投入（冪等）。有効化フックから呼ぶ。
	 */
	public static function seed() {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		foreach ( self::entries() as $entry ) {
			self::insert_once( $entry );
		}
		update_option( self::OPTION, self::VERSION );
	}

	/**
	 * 同一スラッグ（_carmel_seed_key）が既にあれば作らない。
	 *
	 * @param array $entry
	 */
	private static function insert_once( array $entry ) {
		$existing = get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_carmel_seed_key',
				'meta_value'     => $entry['key'],
			)
		);
		if ( ! empty( $existing ) ) {
			return;
		}

		wp_insert_post(
			array(
				'post_type'    => 'carmel_content',
				'post_status'  => 'publish',
				'post_title'   => $entry['title'],
				'post_content' => $entry['body'],
				'meta_input'   => array(
					'content_type'   => $entry['type'],
					'summary'        => isset( $entry['summary'] ) ? $entry['summary'] : '',
					'step_order'     => isset( $entry['step'] ) ? (int) $entry['step'] : 0,
					'pinned'         => ! empty( $entry['pinned'] ) ? 1 : 0,
					'notify_stores'  => 0,
					'_carmel_seeded' => 1,
					'_carmel_seed_key' => $entry['key'],
				),
			)
		);
	}

	/**
	 * 投入する全コンテンツ定義。
	 *
	 * @return array<int,array>
	 */
	public static function entries() {
		$guides  = self::start_guide();
		$manuals = self::manuals();
		$faqs    = self::faqs();
		return apply_filters( 'carmel_seed_content', array_merge( $guides, $manuals, $faqs ) );
	}

	/* --------------------------------------------------------------------- *
	 * 始め方マニュアル（スタートガイド）
	 * --------------------------------------------------------------------- */

	private static function start_guide() {
		$steps = array(
			array(
				'title'   => 'ログインと初期設定',
				'summary' => 'まずはログインし、自店の基本情報を確認します。',
				'body'    =>
"加盟店として承認されると、ログイン設定（パスワード設定）リンクがメールで届きます。\n\n"
. "1. メール内のリンクからパスワードを設定\n"
. "2. /login からログイン（ログイン後は自動で /store に入ります）\n"
. "3. 店舗住所・連絡先など自店情報に誤りがないか確認（陸送費の計算に店舗住所を使います）\n\n"
. "※ 本部・加盟店・お客様は同じログイン画面を使い、入った先の画面だけが役割で分かれます。",
			),
			array(
				'title'   => 'スタッフを招待する',
				'summary' => 'オーナーは自店スタッフのアカウントを発行できます。',
				'body'    =>
"オーナー権限では /store ダッシュボードの「スタッフを追加」から、自店スタッフ（store_staff）を発行できます。\n\n"
. "・氏名とメールアドレスを入力して「発行」\n"
. "・スタッフにはパスワード設定リンクがメールで届きます\n"
. "・スタッフは自店の案件操作ができます（他店舗の案件は閲覧・操作できません）",
			),
			array(
				'title'   => '在庫車両を登録する',
				'summary' => 'メーカー・車種・価格・車検満了日などを登録します。',
				'body'    =>
"在庫車両を登録しておくと、案件・帳票・契約書に車両情報が自動で連携されます。\n\n"
. "登録項目の例：メーカー / 車種 / 年式 / 走行距離 / 車台番号 / ナンバー / 販売価格 / 車検満了日\n\n"
. "案件に車両を紐付けると、ステータス進行に合わせて在庫ステータス（販売中→商談中→売約済→納車済）が自動で切り替わります。",
			),
			array(
				'title'   => 'お申込み〜案件の流れを知る',
				'summary' => 'お客様の申込からマイページ連動までの全体像。',
				'body'    =>
"お客様が申込フォームから申し込むと、アカウントが自動発行され案件が作られます。\n\n"
. "・ローン販売：仮申込 → AIスコア → 信販審査 → 審査結果 → 加盟店マッチング → 書類準備 → 契約 → 納車準備 → 納車 → アフター\n"
. "・車買取：査定申込 → 査定 → 査定額提示 → 成約 → 書類準備 → 引取\n"
. "・自社リース：リース申込 → 審査 → 契約 → 納車 → リース中 → 満了\n\n"
. "お客様はマイページで進捗（フェーズ）・返済状況・発行書類を確認できます。",
			),
			array(
				'title'   => '案件を進める',
				'summary' => '担当工程のステータスを前に進めます。',
				'body'    =>
"/store の案件一覧で、担当する工程の「○○へ」ボタンを押すとステータスが進みます。\n\n"
. "・お客様への通知・在庫連動・履歴記録が自動で走ります\n"
. "・納車準備に進める際は納車予定日を入力できます\n"
. "・審査（OK/NG）と契約送付は本部の工程です。該当工程では「本部の手続き待ち」と表示されます",
			),
			array(
				'title'   => '帳票・契約書を発行する',
				'summary' => '見積書・請求書・各種契約書をお客様向けに発行。',
				'body'    =>
"/store-billing（帳票・契約書を発行）から、案件ごとに書類を発行できます。\n\n"
. "・見積書／請求書：品目・数量・単価を入れると消費税・合計を自動計算\n"
. "・契約書テンプレート：売買契約書／自社リース契約書／保証書／委任状／譲渡証明書（車両・金額・お客様情報を自動差し込み）\n"
. "・発行した書類は「表示・印刷」からA4で印刷・PDF保存でき、お客様のマイページにも表示されます\n\n"
. "※ 電子署名による売買契約（マネーフォワード契約）は本部が送付します。加盟店の発行は手元で使う印刷用テンプレートです。",
			),
			array(
				'title'   => '販売支援ツールを使う',
				'summary' => '保証・陸送・オートローン・自社リース・販促ツール。',
				'body'    =>
"/sales-support（販売支援）に成約を後押しするツールをまとめています。\n\n"
. "・保証：プランを選んで適用すると保証情報を記録し保証書を発行\n"
. "・陸送：店舗〜納車先の距離から陸送費を自動見積\n"
. "・オートローン：頭金・回数・年率から月々支払いを試算し、見積書として発行\n"
. "・自社リース：残価設定で月額を試算し、見積書として発行\n"
. "・販促ツール：本部配布のPOP・チラシ等をダウンロード",
			),
			array(
				'title'   => '納車とアフターサポート',
				'summary' => '納車後は車検・保険のご案内まで。',
				'body'    =>
"納車準備で陸送を手配し、納車後はアフターサポートに移ります。\n\n"
. "・車検満了・保険満了が近づくと、お客様へ自動でご案内が届きます（マイページにもカウントダウン表示）\n"
. "・点検・車検・保険の各レコードを登録して管理できます",
			),
			array(
				'title'   => '会費とお問い合わせ',
				'summary' => '会費の確認と、困ったときの連絡先。',
				'body'    =>
"・会費の状況は本部が管理します。更新期日が近づくとご案内が届きます\n"
. "・操作で困ったら、本部からのお知らせ・マニュアル・FAQ（このページ）を確認してください\n"
. "・解決しない場合は本部までご連絡ください",
			),
		);

		$out = array();
		foreach ( $steps as $i => $s ) {
			$out[] = array(
				'key'     => 'guide_' . ( $i + 1 ),
				'type'    => 'guide',
				'step'    => $i + 1,
				'title'   => $s['title'],
				'summary' => $s['summary'],
				'body'    => $s['body'],
			);
		}
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * 各種マニュアル・資料
	 * --------------------------------------------------------------------- */

	private static function manuals() {
		return array(
			array(
				'key'     => 'manual_billing',
				'type'    => 'manual',
				'title'   => '帳票発行マニュアル（見積・請求・契約書）',
				'summary' => '/store-billing の使い方と各書類の発行手順。',
				'body'    => "見積書・請求書・各種契約書の発行手順をまとめた資料です。明細の入れ方、消費税率の扱い、契約書テンプレートの差し込み項目について説明します。",
			),
			array(
				'key'     => 'manual_sales_support',
				'type'    => 'manual',
				'title'   => '販売支援ツール活用ガイド',
				'summary' => '保証・陸送・オートローン・自社リース・販促の使い分け。',
				'body'    => "成約率を高めるための販売支援ツールの活用例。ローン／リースの試算をその場でお客様に提示し、見積書として発行する流れを紹介します。",
			),
			array(
				'key'     => 'manual_inventory',
				'type'    => 'manual',
				'title'   => '在庫登録・公開マニュアル',
				'summary' => '車両情報の登録と在庫ステータスの連動。',
				'body'    => "在庫車両の登録項目、案件との紐付け、ステータス連動（販売中→商談中→売約済→納車済）について説明します。",
			),
			array(
				'key'     => 'manual_notify',
				'type'    => 'manual',
				'title'   => '通知・連絡の仕組み',
				'summary' => 'お客様・社内への自動通知について。',
				'body'    => "案件のステータス変更や書類発行に応じて、お客様（LINE/メール）や社内へ自動で通知が送られます。どのタイミングで何が送られるかをまとめています。",
			),
		);
	}

	/* --------------------------------------------------------------------- *
	 * FAQ
	 * --------------------------------------------------------------------- */

	private static function faqs() {
		return array(
			array(
				'key'   => 'faq_other_store',
				'type'  => 'faq',
				'title' => '他店舗の案件は見えますか？',
				'body'  => 'いいえ。加盟店オーナー・スタッフは自店の案件のみ閲覧・操作できます。他店舗の案件にはアクセスできません（本部のみ全店を横断できます）。',
			),
			array(
				'key'   => 'faq_contract_who',
				'type'  => 'faq',
				'title' => '売買契約書は誰が送りますか？',
				'body'  => '電子署名による正式な売買契約（マネーフォワード契約）は本部が送付します。加盟店が /store-billing で発行する売買契約書は、手元で確認・印刷するためのテンプレートです。用途に応じて使い分けてください。',
			),
			array(
				'key'   => 'faq_tax',
				'type'  => 'faq',
				'title' => '見積書・請求書の消費税率を変えたい',
				'body'  => '消費税率は本部設定（既定10%）です。変更が必要な場合は本部にご連絡ください。ローン・リースの試算帳票は税抜の概算で発行されます。',
			),
			array(
				'key'   => 'faq_transport',
				'type'  => 'faq',
				'title' => '陸送費が計算できません',
				'body'  => '店舗住所とお客様の納車先住所の両方が登録されている必要があります。また地図APIの設定が必要なため、表示されない場合は本部にご確認ください。',
			),
			array(
				'key'   => 'faq_staff_scope',
				'type'  => 'faq',
				'title' => 'スタッフはどこまで操作できますか？',
				'body'  => 'スタッフは自店の案件のステータス操作・帳票発行・販売支援の利用ができます。スタッフの追加（アカウント発行）はオーナーのみ可能です。',
			),
		);
	}
}
