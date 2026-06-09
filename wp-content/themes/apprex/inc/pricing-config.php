<?php
/**
 * Single source of truth for pricing — used by the estimate calculator,
 * the order processor (server-side recompute), and the AI chatbot prompt.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structured pricing model.
 *
 * billing: 'monthly' (月額) | 'oneoff' (買い切り)
 * Amounts are in JPY, tax excluded.
 *
 * @return array
 */
function apprex_pricing_config() {
	$config = array(
		'currency'    => 'JPY',
		'campaign'    => array(
			'label'        => '初期費用0円キャンペーン（先着5名様）',
			'initial_fee'  => 0,
			'normal_fee'   => 300000,
		),
		'services'    => array(
			'app'    => array(
				'label'      => 'アプリ開発（月額制）',
				'billing'    => 'monthly',
				'min_months' => 12,
				'plans'      => array(
					'trial'    => array( 'label' => 'Trial', 'price' => 19800 ),
					'start'    => array( 'label' => 'Start', 'price' => 39800 ),
					'business' => array( 'label' => 'Business', 'price' => 59800 ),
				),
				'options'    => array(
					'multilang' => array( 'label' => '多言語対応', 'price' => 5000 ),
					'ec'        => array( 'label' => 'EC・決済機能', 'price' => 10000 ),
					'api'       => array( 'label' => '外部API連携', 'price' => 8000 ),
					'support'   => array( 'label' => '保守・運用強化', 'price' => 10000 ),
				),
			),
			'agency' => array(
				'label'      => '制作代行（買い切り）',
				'billing'    => 'oneoff',
				'min_months' => 0,
				'plans'      => array(
					'trial'    => array( 'label' => 'Trial', 'price' => 100000 ),
					'start'    => array( 'label' => 'Start', 'price' => 150000 ),
					'business' => array( 'label' => 'Business', 'price' => 200000 ),
				),
				'options'    => array(
					'design'  => array( 'label' => 'デザイン強化', 'price' => 50000 ),
					'release' => array( 'label' => 'ストア公開代行', 'price' => 30000 ),
				),
			),
			'hp'     => array(
				'label'      => 'ホームページ制作（月額制・初期費用0円）',
				'billing'    => 'monthly',
				'min_months' => 12,
				'plans'      => array(
					'light'    => array( 'label' => 'Light', 'price' => 9800 ),
					'standard' => array( 'label' => 'Standard', 'price' => 19800 ),
					'premium'  => array( 'label' => 'Premium', 'price' => 39800 ),
				),
				'options'    => array(
					'banner'  => array( 'label' => 'バナー制作サブスク', 'price' => 50000 ),
					'ads'     => array( 'label' => '広告運用代行', 'price' => 50000 ),
					'consult' => array( 'label' => 'Webコンサルティング', 'price' => 80000 ),
				),
			),
		),
	);

	return apply_filters( 'apprex_pricing_config', $config );
}

/**
 * Server-side recompute of an estimate from raw input (anti-tampering).
 *
 * @param string   $service Service key.
 * @param string   $plan    Plan key.
 * @param string[] $options Option keys.
 * @return array|WP_Error { service, plan, billing, monthly, oneoff, initial_fee, options[], annual_est, min_months, label }
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
	$base = (int) $svc['plans'][ $plan ]['price'];

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

	$monthly     = 'monthly' === $svc['billing'] ? $base + $opt_total : 0;
	$oneoff      = 'oneoff' === $svc['billing'] ? $base + $opt_total : 0;
	$initial_fee = (int) $config['campaign']['initial_fee'];
	$annual_est  = $monthly * 12;

	return array(
		'service'     => $service,
		'service_label' => $svc['label'],
		'plan'        => $plan,
		'plan_label'  => $svc['plans'][ $plan ]['label'],
		'billing'     => $svc['billing'],
		'base'        => $base,
		'options'     => $opt_lines,
		'monthly'     => $monthly,
		'oneoff'      => $oneoff,
		'initial_fee' => $initial_fee,
		'annual_est'  => $annual_est,
		'min_months'  => (int) $svc['min_months'],
	);
}

/**
 * Human-readable pricing summary injected into the AI system prompt.
 *
 * @return string
 */
function apprex_pricing_summary_text() {
	$c     = apprex_pricing_config();
	$lines = array();
	foreach ( $c['services'] as $svc ) {
		$unit = 'monthly' === $svc['billing'] ? '円/月' : '円(買い切り)';
		$plans = array();
		foreach ( $svc['plans'] as $p ) {
			$plans[] = sprintf( '%s %s%s', $p['label'], number_format( $p['price'] ), $unit );
		}
		$opts = array();
		foreach ( $svc['options'] as $o ) {
			$opts[] = sprintf( '%s(+%s%s)', $o['label'], number_format( $o['price'] ), $unit );
		}
		$lines[] = sprintf(
			'- %s：%s。オプション：%s',
			$svc['label'],
			implode( ' / ', $plans ),
			$opts ? implode( ' / ', $opts ) : 'なし'
		);
	}
	$lines[] = '- 初期費用：0円キャンペーン中（通常30万円）。月額プランは最低契約12ヶ月。30日間無料体験あり。';
	return implode( "\n", $lines );
}
