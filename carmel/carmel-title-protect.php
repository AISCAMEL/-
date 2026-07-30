<?php
/**
 * Plugin Name: カーメル タイトル保護
 * Description: 在庫の保存時にタイトルが空になるのを防ぎます。更新時にタイトルが空なら、車のデータ（メーカー/車種/年式/走行距離）から自動で組み立てて補完。既にタイトルがあればそのまま維持。これで「更新するとタイトルが消える」を根本から防ぎます。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化するだけ。
 *         無効化すれば元の挙動に戻ります。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmel_title_protect_build' ) ) {

	/* 車のデータからタイトルを組み立て（メーカー 車種 年式 走行距離）。接頭語なし。 */
	function carmel_title_protect_build( $pid ) {
		$get = function ( $keys ) use ( $pid ) {
			if ( function_exists( 'carmel_detail_get_any' ) ) {
				return (string) carmel_detail_get_any( $pid, (array) $keys );
			}
			foreach ( (array) $keys as $k ) {
				$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : '';
				if ( null === $v || '' === $v || false === $v ) { $v = get_post_meta( $pid, $k, true ); }
				if ( is_array( $v ) ) { $v = implode( '', array_filter( $v ) ); }
				if ( is_string( $v ) && '' !== trim( $v ) ) { return trim( $v ); }
			}
			return '';
		};

		$maker = $get( array( 'marker', 'maker', 'メーカー' ) );
		$type  = $get( array( 'type', 'name', 'car_model', 'shashu' ) );

		// メーカー・車種が両方なければ作らない（誤った空タイトル回避）
		if ( '' === $maker && '' === $type ) { return ''; }

		// 年式：4桁西暦なら「年」付与
		$year = $get( array( 'year', 'nenshiki' ) );
		if ( function_exists( 'mb_convert_kana' ) && '' !== $year ) { $year = mb_convert_kana( $year, 'n', 'UTF-8' ); }
		if ( preg_match( '/^\d{4}$/', (string) $year ) ) { $year .= '年'; }

		// 走行距離：数値なら 3桁カンマ + km
		$mileage = $get( array( 'mileage', 'soukou', 'soukou_kyori', 'kyori' ) );
		if ( '' !== $mileage ) {
			$digits = preg_replace( '/[^0-9]/', '', $mileage );
			if ( '' !== $digits && (int) $digits > 0 ) { $mileage = number_format( (int) $digits ) . 'km'; }
		}

		$parts = array_filter( array( $maker, $type, $year, $mileage ), function ( $v ) { return '' !== trim( (string) $v ); } );
		return trim( preg_replace( '/\s+/', ' ', implode( ' ', $parts ) ) );
	}
}

/* 保存直前にタイトルが空なら補完（既存フィルタ priority 999 の後で動く） */
add_filter( 'wp_insert_post_data', 'carmel_title_protect_filter', 1000, 2 );
function carmel_title_protect_filter( $data, $postarr ) {
	if ( empty( $data['post_type'] ) || 'portfolio' !== $data['post_type'] ) { return $data; }

	// 下書き自動保存・ゴミ箱・自動下書きは対象外
	$status = isset( $data['post_status'] ) ? $data['post_status'] : '';
	if ( in_array( $status, array( 'auto-draft', 'trash' ), true ) ) { return $data; }

	$title = isset( $data['post_title'] ) ? trim( (string) $data['post_title'] ) : '';
	if ( '' !== $title ) { return $data; } // タイトルがあるなら何もしない

	$pid = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
	if ( ! $pid ) { return $data; }

	// 1) 現在保存済みのデータからタイトルを組み立てる
	$built = carmel_title_protect_build( $pid );

	// 2) 作れなければ、消える前の既存タイトルを維持する
	if ( '' === $built ) {
		$prev = get_post_field( 'post_title', $pid );
		if ( is_string( $prev ) && '' !== trim( $prev ) ) { $built = trim( $prev ); }
	}

	if ( '' !== $built ) { $data['post_title'] = $built; }
	return $data;
}
