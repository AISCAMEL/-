<?php
/**
 * 料金の単一ソース（APPREX仕様）。
 * 見積もり計算・料金ページ・チャット回答・GAS連携が全てここを参照。
 *
 * 方針：
 * - アプリ開発は「初期設定費」を提示し、今月キャンペーンで 0円 に自動反映。
 * - 月額は通常価格（取り消し線）＋キャンペーン価格を表示。
 * - 制作代行・チャット・GPS等は一回限りのオプション。
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
			'label'           => '今月末まで限定キャンペーン（先着10社）',
			'initial_to_zero' => true, // 初期設定費を0円に。
		),
		'services' => array(
			'app' => array(
				'label'      => 'アプリ開発（月額制）',
				'billing'    => 'monthly',
				'min_months' => 12, // 1年契約。
				'plans'      => array(
					'trial'    => array(
						'label'           => 'トライアル（基本機能）',
						'monthly'         => 19800,
						'monthly_regular' => 19800,
						'initial'         => 100000,
						'desc'            => '基本機能・1年契約。まずはここから。',
						'note'            => '制作代行（オプション）10万円。自社で制作する場合は不要。',
					),
					'start'    => array(
						'label'           => 'スタート（電子カタログ）',
						'monthly'         => 39800,
						'monthly_regular' => 39800,
						'initial'         => 200000,
						'desc'            => 'トライアル＋電子カタログ機能。',
						'note'            => '制作代行（オプション）10万円（電子カタログ10冊まで）。10冊以上は別途。自社制作は不要。',
					),
					'business' => array(
						'label'           => 'ビジネス（多店舗）',
						'monthly'         => 59800,
						'monthly_regular' => 59800,
						'initial'         => 300000,
						'desc'            => 'スタート＋多店舗機能（店舗数無制限）。',
						'note'            => '制作代行（オプション）20万円（20店舗まで込み）。20店舗以上は1店舗ごと+1万円。自社制作は不要。',
					),
				),
				// 一回限りのオプション（金額あり）。
				'options'    => array(
					'seisaku' => array( 'label' => '制作代行（10メニューまで）', 'price' => 100000 ),
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
					'バナー制作', '構造化（SEO）', 'LINE構築', 'MEO構築', 'LLMO構築',
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
			'matching' => array(
				'label'    => 'マッチングアプリ開発',
				'lead'     => 'スキルマッチング／ビジネスマッチング／出会い系／フリマ等。Flutterスクラッチ開発でカスタマイズ自由。',
				'rows'     => array(
					'開発費用（iOS/Android）' => '300万円〜',
					'月額保守・サーバ代'      => '7万円〜',
					'制作期間'                => '1〜3ヶ月',
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
 * @return array|WP_Error
 */
function apprex_calculate_estimate( $service, $plan, $options = array() ) {
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
	$initial         = apprex_campaign_initial( $initial_regular );

	$opt_total = 0;
	$opt_lines = array();
	foreach ( (array) $options as $opt_key ) {
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
	$annual_est    = $monthly * 12 + $initial_total;

	return array(
		'service'         => $service,
		'service_label'   => $svc['label'],
		'plan'            => $plan,
		'plan_label'      => $p['label'],
		'billing'         => 'monthly',
		'monthly'         => $monthly,
		'monthly_regular' => $monthly_regular,
		'initial'         => $initial,
		'initial_regular' => $initial_regular,
		'options'         => $opt_lines,
		'options_total'   => $opt_total,
		'initial_total'   => $initial_total,
		'annual_est'      => $annual_est,
		'min_months'      => (int) $svc['min_months'],
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
	$lines[] = '【アプリ開発（月額・初期設定費は今月キャンペーンで0円）】';
	foreach ( $app['plans'] as $p ) {
		$lines[] = sprintf(
			'- %s：月額%s円／初期設定費 通常%s円→今月0円',
			$p['label'],
			number_format( $p['monthly'] ),
			number_format( $p['initial'] )
		);
	}
	$opts = array();
	foreach ( $app['options'] as $o ) {
		$opts[] = sprintf( '%s(%s円)', $o['label'], number_format( $o['price'] ) );
	}
	$lines[] = '  オプション：' . implode( ' / ', $opts ) . '。1年契約・DL課金なし・プッシュ無制限。';

	$lines[] = '【ホームページ制作（初期費用0円・月額）】Light 9,800円／Standard 19,800円／Premium 39,800円。プラン毎にサポート範囲あり。追加項目（バナー制作・構造化・LINE構築・MEO構築・LLMO構築）はすべて別途見積もり。';

	$lines[] = '【個別見積（要相談）】';
	foreach ( apprex_quote_plans() as $q ) {
		$lines[] = '- ' . $q['label'] . '：' . $q['lead'];
	}
	$lines[] = 'マッチングアプリ開発：開発費 300万円〜、月額保守7万円〜、Flutterスクラッチ、最短1ヶ月〜。';
	$lines[] = '30日間の管理画面体験は無料。詳しい見積りは /estimate、上位プランはお問い合わせへ。';
	return implode( "\n", $lines );
}
