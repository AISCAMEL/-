<?php
/**
 * 料金の単一ソース（APPREX仕様）。
 * 見積もり計算・料金ページ・チャット回答・GAS連携が全てここを参照。
 *
 * 方針：
 * - アプリはAIスタジオ（無料）でデザイン・仕様書を作り、その内容から制作する。初期費用 150,000円〜。
 *   AIスタジオを使わず1からヒアリングして作る場合は 300,000円〜。（自分で設定する0円のプランはなし）
 * - 仕様書ができたら、ホームページ制作やオプションもあわせて提案する（AIスタジオの「ご提案」）。
 * - チャット・GPS等は一回限りのオプション。
 * - マッチング/カスタム/オンプレミスは個別見積（要相談）として別掲。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * アプリ基本機能の一覧（トライアル）。
 *
 * @return string[]
 */
function apprex_app_basic_features() {
	return array(
		'簡単作成CMS', 'TOPデザインテンプレート', 'デザイン編集機能', 'ページ作成機能',
		'プッシュ通知（6種類）', '予約機能', 'スタンプカード', 'クーポン', 'ポイント機能',
		'スタンプラリー', '会員機能', '会員証機能', 'アンケート', 'ブログ機能', '動画機能',
		'LINEで友達紹介', 'HTML編集', '申し込みフォーム作成', 'DL分析', 'フォトギャラリー',
		'SNS連動', 'ウェブビュー機能', '各種ページ作成',
	);
}

/**
 * 価格モデル。
 *
 * @return array
 */
function apprex_pricing_config() {
	$config = array(
		'currency' => 'JPY',
		'campaign' => array(
			'label'           => 'AIスタジオは無料。作った内容から制作で初期費用15万円〜',
			'initial_to_zero' => true, // ホームページ制作の初期費用0円。
		),
		'services' => array(
			'app' => array(
				'label'      => 'アプリ開発（月額制）',
				'billing'    => 'monthly',
				'min_months' => 12, // 1年契約。
				// アプリ登録時に別途必要（iOS/Android 一律）。キャンペーン対象外。
				'registration_fee'   => 55000,
				'registration_label' => 'アプリ登録費（iOS/Android）',
				'plans'      => array(
					'trial'    => array(
						'label'           => 'トライアル（基本機能）',
						'monthly'         => 19800,
						'monthly_regular' => 19800,
						'initial'         => 100000,
						'desc'            => '基本機能・1年契約。まずはここから。',
						'note'            => '',
					),
					'start'    => array(
						'label'           => 'スタート（電子カタログ）',
						'monthly'         => 39800,
						'monthly_regular' => 39800,
						'initial'         => 200000,
						'desc'            => 'トライアル＋電子カタログ機能。',
						'note'            => '電子カタログ11冊以上は別途。',
					),
					'business' => array(
						'label'           => 'ビジネス（多店舗）',
						'monthly'         => 59800,
						'monthly_regular' => 59800,
						'initial'         => 300000,
						'desc'            => 'スタート＋多店舗機能（店舗数無制限）。',
						'note'            => '21店舗以上は1店舗ごと+1万円。',
					),
				),
				// 初期費用（作り方で選ぶ。AIスタジオから作るのが基本）。
				'setup'      => array(
					'ai'   => array( 'label' => 'AIスタジオで作った内容から制作', 'price' => 150000, 'desc' => 'AIスタジオ（無料）で作ったデザイン・仕様書をもとに、APPREXが制作・ストア申請まで行います。初期設定・サーバー設定を含みます。' ),
					'full' => array( 'label' => '1からヒアリングして制作', 'price' => 300000, 'desc' => 'AIスタジオを使わず、打ち合わせで1から決めて制作します。初期設定・サーバー設定を含みます。' ),
				),
				// 一回限りのオプション（金額あり）。
				'options'    => array(
					'chat'    => array( 'label' => 'チャット機能', 'price' => 300000 ),
					'gps'     => array( 'label' => 'GPSプッシュ（1カ所）', 'price' => 20000 ),
				),
				// 要相談オプション（金額は個別見積）。
				'quote_options' => array(
					'API連動', '基幹システム連動', 'オンプレミス', '機能カスタマイズ', '補助金サポート',
				),
			),
			'hp'  => array(
				'label'      => 'ホームページ制作（月額制・初期費用0円）',
				'billing'    => 'monthly',
				'min_months' => 12,
				'plans'      => array(
					'light'    => array(
						'label' => 'Light', 'monthly' => 9800, 'monthly_regular' => 9800, 'initial' => 0,
						'desc' => '基本的なコーポレートサイト',
						'support' => array( '〜5ページ', '月1回までの更新サポート', 'お問い合わせフォーム', '常時SSL', '基本SEO設定' ),
					),
					'standard' => array(
						'label' => 'Standard', 'monthly' => 19800, 'monthly_regular' => 19800, 'initial' => 0,
						'desc' => 'LP付き・問い合わせ充実',
						'support' => array( '〜10ページ＋LP1本', '月2回までの更新サポート', '予約・問い合わせ強化', '構造化データ（基本）' ),
					),
					'premium'  => array(
						'label' => 'Premium', 'monthly' => 39800, 'monthly_regular' => 39800, 'initial' => 0,
						'desc' => 'EC・会員機能付き',
						'support' => array( '〜20ページ', '月3回までの更新サポート', 'EC・会員機能', '優先サポート' ),
					),
				),
				// HPの追加項目はすべて別途見積もり（要相談）。
				'options'       => array(),
				'quote_options' => array(
					'バナー制作', '構造化（SEO）', 'LINE構築', 'AIMEO（AIで運用するMEO）', 'LLMO構築',
				),
			),
		),
	);

	return apply_filters( 'apprex_pricing_config', $config );
}

/**
 * 個別見積（要相談）の上位プラン。料金ページ・チャットで案内。
 *
 * @return array
 */
function apprex_quote_plans() {
	return apply_filters(
		'apprex_quote_plans',
		array(
			'original' => array(
				'label'    => 'オリジナルプラン',
				'lead'     => 'APPREXの基盤をベースに、ご要望の機能・デザインをカスタマイズ開発するプランです。低価格・短納期で柔軟に対応します。',
				'rows'     => array(
					'開発費'     => '別途見積もり',
					'月額管理費' => '別途見積もり',
					'納期'       => '別途見積もり（要件により算出）',
					'仕様定義'   => '無料オンライン相談で要件をヒアリング',
				),
				'examples' => array( '基盤カスタマイズ', '機能追加', '独自デザイン', '業務アプリ' ),
			),
			'matching' => array(
				'label'    => 'マッチングアプリ開発',
				'lead'     => 'スキルマッチング／ビジネスマッチング／出会い系／フリマ等。Flutterスクラッチ開発でカスタマイズ自由。',
				'rows'     => array(
					'開発費用（iOS/Android）' => '300万円〜',
					'月額保守・サーバ代'      => '7万円〜',
					'制作期間'                => '3ヶ月〜',
					'開発言語'                => 'アプリ：Flutter／管理：PHP',
					'サーバ'                  => 'AWS無料提供',
					'決済'                    => 'iOS/Android 内部課金 または Square等の外部課金',
					'最低利用期間'            => '1年契約（12ヶ月）',
				),
				'examples' => array( 'スキルマッチング', 'ビジネスマッチング', '出会い系マッチング', 'フリマアプリ' ),
				'cta'      => 'matching-appli.net も参照',
			),
			'custom'   => array(
				'label' => 'カスタマイズプラン',
				'lead'  => 'APPREXの基盤の上に、希望の機能・デザインをスクラッチ開発。他社比で低価格・短納期。',
				'rows'  => array(
					'開発費'     => '相談（案件ごと見積）',
					'月額管理費' => '相談',
					'仕様定義'   => '1週間〜',
					'開発期間'   => '3ヶ月〜',
					'用途'       => 'マッチング・業務用・課金アプリ等あらゆるアプリ',
				),
			),
			'onprem'   => array(
				'label' => 'オンプレミスプラン',
				'lead'  => 'プログラムソース買取。自社で改造可能。',
				'rows'  => array(
					'ライセンス' => '1ライセンス',
					'サーバ'     => '自社サーバー または 弊社サーバー',
					'価格'       => '2,000万円〜',
					'月額管理費' => '20万円〜',
				),
			),
		)
	);
}

/**
 * 今月のキャンペーン後・初期費用（0円 or 通常）。
 *
 * @param int $regular 通常初期費用。
 * @return int
 */
function apprex_campaign_initial( $regular ) {
	$c = apprex_pricing_config();
	return ! empty( $c['campaign']['initial_to_zero'] ) ? 0 : (int) $regular;
}

/**
 * サーバー側で見積りを再計算（改ざん防止）。
 *
 * @param string   $service Service key.
 * @param string   $plan    Plan key.
 * @param string[] $options Option keys.
 * @param string   $setup   初期費用の種類（self|ai|full）。
 * @return array|WP_Error
 */
function apprex_calculate_estimate( $service, $plan, $options = array(), $setup = '' ) {
	$config = apprex_pricing_config();
	if ( empty( $config['services'][ $service ] ) ) {
		return new WP_Error( 'invalid_service', 'サービスの指定が正しくありません。' );
	}
	$svc = $config['services'][ $service ];
	if ( empty( $svc['plans'][ $plan ] ) ) {
		return new WP_Error( 'invalid_plan', 'プランの指定が正しくありません。' );
	}
	$p = $svc['plans'][ $plan ];

	$monthly         = (int) $p['monthly'];
	$monthly_regular = (int) ( $p['monthly_regular'] ?? $p['monthly'] );
	$initial_regular = (int) ( $p['initial'] ?? 0 );

	$options = array_values( array_unique( (array) $options ) );
	// 以前の形式（制作代行をオプションで送る）も受け付ける。
	if ( '' === $setup ) {
		$setup = in_array( 'seisaku', $options, true ) ? 'full' : 'ai';
	}
	$options = array_values( array_diff( $options, array( 'seisaku_ai', 'seisaku' ) ) );

	// 初期費用：作り方ごとの金額（初期設定・制作を含む）。
	$setups = isset( $svc['setup'] ) ? $svc['setup'] : array();
	if ( $setups ) {
		$setup       = isset( $setups[ $setup ] ) ? $setup : 'ai';
		$setup_label = $setups[ $setup ]['label'];
		$initial     = (int) $setups[ $setup ]['price'];
	} else {
		$setup       = '';
		$setup_label = '';
		$initial     = apprex_campaign_initial( $initial_regular );
	}

	$opt_total = 0;
	$opt_lines = array();
	foreach ( $options as $opt_key ) {
		if ( ! empty( $svc['options'][ $opt_key ] ) ) {
			$opt_total  += (int) $svc['options'][ $opt_key ]['price'];
			$opt_lines[] = array(
				'key'   => $opt_key,
				'label' => $svc['options'][ $opt_key ]['label'],
				'price' => (int) $svc['options'][ $opt_key ]['price'],
			);
		}
	}

	$initial_total = $initial + $opt_total; // オプションは一回費用。

	// アプリ登録費（iOS/Android）はキャンペーン対象外で別途必要。
	$registration       = (int) ( $svc['registration_fee'] ?? 0 );
	$registration_label = (string) ( $svc['registration_label'] ?? 'アプリ登録費' );
	$initial_total     += $registration;

	$annual_est = $monthly * 12 + $initial_total;

	return array(
		'service'            => $service,
		'service_label'      => $svc['label'],
		'plan'               => $plan,
		'plan_label'         => $p['label'],
		'billing'            => 'monthly',
		'monthly'            => $monthly,
		'monthly_regular'    => $monthly_regular,
		'initial'            => $initial,
		'initial_regular'    => $initial_regular,
		'setup'              => $setup,
		'setup_label'        => $setup_label,
		'registration'       => $registration,
		'registration_label' => $registration_label,
		'options'            => $opt_lines,
		'options_total'      => $opt_total,
		'initial_total'      => $initial_total,
		'annual_est'         => $annual_est,
		'min_months'         => (int) $svc['min_months'],
	);
}

/**
 * チャット用の料金要約（自動でAIに渡る）。
 *
 * @return string
 */
function apprex_pricing_summary_text() {
	$c     = apprex_pricing_config();
	$lines = array();

	$app = $c['services']['app'];
	$lines[] = '【アプリ開発（月額制・1年契約）】';
	$lines[] = '- AIスタジオ（無料会員登録）でのデザイン・試作・仕様定義書づくりと相談は無料。';
	$lines[] = '- 初期費用：AIスタジオで作った内容から制作 150,000円〜（基本）／AIスタジオを使わず1からヒアリングして制作 300,000円〜。初期設定・制作・ストア申請を含む。自分で設定する0円のプランはない。「制作代行費」ではなく「初期費用」と呼ぶ。';
	$lines[] = '- 仕様書ができたら、ホームページ制作・オプション・集客施策（LINE構築・AIMEOなど）もあわせて提案する。';
	$lines[] = '- AIMEO：AIで運用するMEO（Googleマップ対策）。投稿・口コミ返信・ビジネスの説明文の下書きをAIが作り、担当者が確認してGoogleビジネスプロフィールに載せる。ライト月15,000円／スタンダード月30,000円（毎週の投稿運用）／プロ月50,000円（複数店舗）。順位の保証はしない。';
	$lines[] = '- 補助金：小規模事業者持続化補助金や自治体の補助金を案内し、申請に使う企画書・見積書の用意と、交付決定に合わせたスケジュールをサポート。APPREXはIT導入補助金の支援事業者ではないので、IT導入補助金は対象外。申請書の作成代行はしない（作成・提出はお客様、必要なら商工会・商工会議所や行政書士に相談）。補助率・上限は回ごとに変わるので断定しない。' . ( function_exists( 'apprex_rn_public' ) && apprex_rn_public() ? '案内ページ：' . home_url( '/subsidy/' ) : '' );
	foreach ( $app['plans'] as $p ) {
		$lines[] = sprintf( '- %s：月額%s円', $p['label'], number_format( $p['monthly'] ) );
	}
	$opts = array();
	foreach ( $app['options'] as $o ) {
		$opts[] = sprintf( '%s(%s円)', $o['label'], number_format( $o['price'] ) );
	}
	$lines[] = '  オプション：' . implode( ' / ', $opts ) . '。1年契約・DL課金なし・プッシュ無制限。';
	if ( ! empty( $app['registration_fee'] ) ) {
		$lines[] = '  ' . $app['registration_label'] . '：一律' . number_format( $app['registration_fee'] ) . '円（iOS/Android、アプリ登録時に別途必要）。';
	}

	$lines[] = '【ホームページ制作（初期費用0円・月額）】Light 9,800円／Standard 19,800円／Premium 39,800円。プラン毎にサポート範囲あり。追加項目（バナー制作・構造化・LINE構築・AIMEO（AIで運用するMEO）・LLMO構築）はすべて別途見積もり。';

	$lines[] = '【個別見積（要相談）】';
	foreach ( apprex_quote_plans() as $q ) {
		$lines[] = '- ' . $q['label'] . '：' . $q['lead'];
	}
	$lines[] = 'マッチングアプリ開発：開発費 300万円〜、月額保守7万円〜、Flutterスクラッチ、期間3ヶ月〜。オリジナル開発も期間3ヶ月〜。';
	$lines[] = '30日間の管理画面体験は無料。詳しい見積りは /estimate、上位プランはお問い合わせへ。';
	return implode( "\n", $lines );
}

/**
 * 見積りの初期費用の説明（メール・画面で共通）。
 *
 * @param array $e Estimate.
 * @return string 例：「150,000円（AIスタジオで作った内容から制作）」
 */
function apprex_estimate_initial_text( $e ) {
	$yen = number_format( (int) $e['initial'] ) . '円';
	if ( ! empty( $e['setup_label'] ) ) {
		return $yen . '（' . $e['setup_label'] . '）';
	}
	if ( (int) $e['initial_regular'] > (int) $e['initial'] ) {
		return $yen . '（通常 ' . number_format( (int) $e['initial_regular'] ) . '円→1年契約で0円）';
	}
	return $yen;
}
