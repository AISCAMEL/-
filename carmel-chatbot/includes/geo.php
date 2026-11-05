<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 地域（都道府県）推定。ipinfo.io を使い、お客様のIPからおおよその地域を判定する。
 * ※ スマホ(4G/5G)は携帯会社の拠点地域が出るため精度は目安。PC/光回線は比較的正確。
 */

/** 訪問者のIPを取得（CDN/プロキシ考慮。プライベートIPより公開IPを優先）。 */
function carmel_cb_client_ip() {
	$cands = array();
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) { $cands[] = $_SERVER['HTTP_CF_CONNECTING_IP']; }
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		foreach ( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) as $p ) { $cands[] = trim( $p ); }
	}
	if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) { $cands[] = $_SERVER['HTTP_X_REAL_IP']; }
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) )    { $cands[] = $_SERVER['REMOTE_ADDR']; }

	foreach ( $cands as $ip ) {
		$ip = trim( (string) $ip );
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) { return $ip; }
	}
	foreach ( $cands as $ip ) {
		$ip = trim( (string) $ip );
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $ip; }
	}
	return '';
}

/** ipinfoの英語県名 → 日本語（主要都道府県。無ければ元の値を返す）。 */
function carmel_cb_geo_jp_pref( $en ) {
	$en = trim( (string) $en );
	if ( $en === '' ) { return ''; }
	$map = array(
		'Hokkaido'=>'北海道','Aomori'=>'青森県','Iwate'=>'岩手県','Miyagi'=>'宮城県','Akita'=>'秋田県','Yamagata'=>'山形県','Fukushima'=>'福島県',
		'Ibaraki'=>'茨城県','Tochigi'=>'栃木県','Gunma'=>'群馬県','Saitama'=>'埼玉県','Chiba'=>'千葉県','Tokyo'=>'東京都','Kanagawa'=>'神奈川県',
		'Niigata'=>'新潟県','Toyama'=>'富山県','Ishikawa'=>'石川県','Fukui'=>'福井県','Yamanashi'=>'山梨県','Nagano'=>'長野県',
		'Gifu'=>'岐阜県','Shizuoka'=>'静岡県','Aichi'=>'愛知県','Mie'=>'三重県',
		'Shiga'=>'滋賀県','Kyoto'=>'京都府','Osaka'=>'大阪府','Hyogo'=>'兵庫県','Nara'=>'奈良県','Wakayama'=>'和歌山県',
		'Tottori'=>'鳥取県','Shimane'=>'島根県','Okayama'=>'岡山県','Hiroshima'=>'広島県','Yamaguchi'=>'山口県',
		'Tokushima'=>'徳島県','Kagawa'=>'香川県','Ehime'=>'愛媛県','Kochi'=>'高知県',
		'Fukuoka'=>'福岡県','Saga'=>'佐賀県','Nagasaki'=>'長崎県','Kumamoto'=>'熊本県','Oita'=>'大分県','Miyazaki'=>'宮崎県','Kagoshima'=>'鹿児島県','Okinawa'=>'沖縄県',
	);
	return isset( $map[ $en ] ) ? $map[ $en ] : $en;
}

/** IPから地域文字列を取得（ipinfo.io・IPごとに7日キャッシュ）。 */
function carmel_cb_geo_lookup( $ip ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['geo_on'] ) || empty( $s['geo_ipinfo_token'] ) ) { return ''; }
	if ( $ip === '' || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) { return ''; }
	// プライベート/予約IPは判定不可
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) { return ''; }

	$ck = 'carmel_cb_geo_' . md5( $ip );
	$c  = get_transient( $ck );
	if ( $c !== false ) { return (string) $c; }

	$region = '';
	$res = wp_remote_get(
		'https://ipinfo.io/' . rawurlencode( $ip ) . '/json?token=' . rawurlencode( (string) $s['geo_ipinfo_token'] ),
		array( 'timeout' => 8, 'headers' => array( 'Accept' => 'application/json' ) )
	);
	if ( ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) === 200 ) {
		$d = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $d ) ) {
			$country = (string) ( $d['country'] ?? '' );
			$reg     = (string) ( $d['region'] ?? '' );
			$city    = (string) ( $d['city'] ?? '' );
			if ( $country === 'JP' ) {
				$region = carmel_cb_geo_jp_pref( $reg );
				if ( $region === '' ) { $region = $city; }
			} elseif ( $country !== '' ) {
				$region = '海外（' . $country . '）';
			}
		}
	}
	// 失敗/空でもキャッシュ（短め）して連打を防ぐ
	set_transient( $ck, $region, $region !== '' ? 7 * DAY_IN_SECONDS : 30 * MINUTE_IN_SECONDS );
	return $region;
}

/** セッションの推定地域（セッション単位で3時間キャッシュ）。現在のリクエストのIPで判定。 */
function carmel_cb_geo_session_region( $sid ) {
	$s = carmel_cb_get_settings();
	if ( empty( $s['geo_on'] ) || empty( $s['geo_ipinfo_token'] ) ) { return ''; }
	$sid = (string) $sid;
	if ( $sid === '' ) { return carmel_cb_geo_lookup( carmel_cb_client_ip() ); }

	$k = 'carmel_cb_georeg_' . md5( $sid );
	$v = get_transient( $k );
	if ( $v !== false ) { return (string) $v; }
	$r = carmel_cb_geo_lookup( carmel_cb_client_ip() );
	set_transient( $k, $r, 3 * HOUR_IN_SECONDS );
	return $r;
}

/** 表示用ラベル（例：「（推定地域：東京都）」）。空なら空文字。 */
function carmel_cb_geo_label( $sid ) {
	$r = carmel_cb_geo_session_region( $sid );
	return $r !== '' ? ( '（推定地域：' . $r . '）' ) : '';
}
