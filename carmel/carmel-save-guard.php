<?php
/**
 * Plugin Name: カーメル 保存データ保護
 * Description: 在庫の保存時に「前は入っていたのに一気に空になった」データを自動で元に戻します。古い車を編集画面で開いて更新したときの一括消失（タイトル・走行距離・年式・装備・本文など）を根本から防止。タイトルが空なら車データから自動補完。これ1つで「保存したら消える」を解消します。※「タイトル保護」を入れている場合は無効化してこちらに一本化してください。
 * Version: 1.0.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化するだけ。
 *         無効化すれば元の挙動に戻ります。
 *
 * 仕組み：
 *   - 保存の最初(優先度1)で、その車の現在の全データを記録（スナップショット）
 *   - 保存の最後(優先度9999)で、「前は値があったのに今は空」になった項目を検出
 *   - 大量に空になった＝事故的な一括消失 と判断し、自動で元に戻す
 *   - 1〜2項目だけの変更（意図的な編集）はそのまま通す
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Save_Guard' ) ) {

class Carmel_Save_Guard {

	/** @var array post_id => 保存前スナップショット */
	private static $snap = array();

	/* 大量消失と判断する閾値 */
	const MIN_FIELDS = 4;     // 元々これ以上の項目が埋まっていて
	const RATIO      = 0.6;   // その6割以上が空になったら「事故」とみなす

	public function __construct() {
		// タイトル・本文の空化を保存直前に防ぐ（既存フィルタ 999 の後）
		add_filter( 'wp_insert_post_data', array( $this, 'protect_title_content' ), 1000, 2 );
		// メタ（入力項目）の一括消失を保存の前後で監視して復元
		add_action( 'save_post_portfolio', array( $this, 'snapshot' ), 1, 1 );
		add_action( 'save_post_portfolio', array( $this, 'restore' ), 9999, 1 );
	}

	private function skip( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return true; }
		if ( wp_is_post_revision( $post_id ) ) { return true; }
		$status = get_post_status( $post_id );
		if ( in_array( $status, array( 'auto-draft', 'trash' ), true ) ) { return true; }
		return false;
	}

	private function is_blank( $v ) {
		if ( is_array( $v ) ) { return empty( array_filter( $v, function ( $x ) { return '' !== trim( (string) $x ); } ) ); }
		return ( null === $v || '' === trim( (string) $v ) );
	}

	/* ---------- タイトル・本文の保護 ---------- */
	public function protect_title_content( $data, $postarr ) {
		if ( empty( $data['post_type'] ) || 'portfolio' !== $data['post_type'] ) { return $data; }
		$status = isset( $data['post_status'] ) ? $data['post_status'] : '';
		if ( in_array( $status, array( 'auto-draft', 'trash' ), true ) ) { return $data; }

		$pid = ! empty( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( ! $pid ) { return $data; }

		// タイトル：空なら車データから組み立て → 無理なら従来タイトル維持
		if ( isset( $data['post_title'] ) && '' === trim( (string) $data['post_title'] ) ) {
			$built = $this->build_title( $pid );
			if ( '' === $built ) {
				$prev = get_post_field( 'post_title', $pid );
				if ( is_string( $prev ) && '' !== trim( $prev ) ) { $built = trim( $prev ); }
			}
			if ( '' !== $built ) { $data['post_title'] = $built; }
		}

		// 本文：空なら従来本文を維持（古い手入力の説明文を守る）
		if ( isset( $data['post_content'] ) && '' === trim( (string) $data['post_content'] ) ) {
			$prev = get_post_field( 'post_content', $pid );
			if ( is_string( $prev ) && '' !== trim( $prev ) ) { $data['post_content'] = $prev; }
		}

		return $data;
	}

	/* 車データからタイトル組み立て（メーカー 車種 年式 走行）。接頭語なし。 */
	private function build_title( $pid ) {
		$get = function ( $keys ) use ( $pid ) {
			if ( function_exists( 'carmel_detail_get_any' ) ) { return (string) carmel_detail_get_any( $pid, (array) $keys ); }
			foreach ( (array) $keys as $k ) {
				$v = function_exists( 'get_field' ) ? get_field( $k, $pid ) : '';
				if ( $this->is_blank( $v ) ) { $v = get_post_meta( $pid, $k, true ); }
				if ( is_array( $v ) ) { $v = implode( '', array_filter( $v ) ); }
				if ( is_string( $v ) && '' !== trim( $v ) ) { return trim( $v ); }
			}
			return '';
		};
		$maker = $get( array( 'marker', 'maker', 'メーカー' ) );
		$type  = $get( array( 'type', 'name', 'car_model', 'shashu' ) );
		if ( '' === $maker && '' === $type ) { return ''; }
		$year = $get( array( 'year', 'nenshiki' ) );
		if ( function_exists( 'mb_convert_kana' ) && '' !== $year ) { $year = mb_convert_kana( $year, 'n', 'UTF-8' ); }
		if ( preg_match( '/^\d{4}$/', (string) $year ) ) { $year .= '年'; }
		$mileage = $get( array( 'mileage', 'soukou', 'soukou_kyori', 'kyori' ) );
		if ( '' !== $mileage ) {
			$d = preg_replace( '/[^0-9]/', '', $mileage );
			if ( '' !== $d && (int) $d > 0 ) { $mileage = number_format( (int) $d ) . 'km'; }
		}
		$parts = array_filter( array( $maker, $type, $year, $mileage ), function ( $v ) { return '' !== trim( (string) $v ); } );
		return trim( preg_replace( '/\s+/', ' ', implode( ' ', $parts ) ) );
	}

	/* ---------- メタ（入力項目）の一括消失保護 ---------- */
	public function snapshot( $post_id ) {
		if ( $this->skip( $post_id ) ) { return; }
		self::$snap[ $post_id ] = get_post_meta( $post_id ); // 保存前の全メタ
	}

	public function restore( $post_id ) {
		if ( $this->skip( $post_id ) ) { return; }
		if ( empty( self::$snap[ $post_id ] ) ) { return; }
		$snap = self::$snap[ $post_id ];

		$before_filled = array(); // 元々値があった項目
		$now_blanked   = array(); // 今は空になった項目 => 元の値
		foreach ( $snap as $key => $vals ) {
			if ( '' === $key || '_' === substr( $key, 0, 1 ) ) { continue; } // 内部項目は対象外
			$before = isset( $vals[0] ) ? $vals[0] : '';
			if ( $this->is_blank( $before ) ) { continue; }
			$before_filled[] = $key;
			$now = get_post_meta( $post_id, $key, true );
			if ( $this->is_blank( $now ) ) {
				$now_blanked[ $key ] = ( count( (array) $vals ) > 1 ) ? $vals : $before;
			}
		}

		$nf = count( $before_filled );
		$nb = count( $now_blanked );
		if ( $nf < self::MIN_FIELDS ) { return; }                 // 元の項目が少なすぎる→判断しない
		if ( $nb < (int) ceil( $nf * self::RATIO ) ) { return; }  // 一部だけの変更→意図的とみなし通す

		// 事故的な一括消失 → 元に戻す
		foreach ( $now_blanked as $key => $orig ) {
			if ( is_array( $orig ) ) {
				delete_post_meta( $post_id, $key );
				foreach ( $orig as $v ) { add_post_meta( $post_id, $key, $v ); }
			} else {
				update_post_meta( $post_id, $key, $orig );
			}
		}
		// 記録（任意・デバッグ用）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[carmel-save-guard] post %d: restored %d/%d wiped fields', $post_id, $nb, $nf ) );
		}
	}
}

new Carmel_Save_Guard();

}
