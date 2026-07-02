<?php
/**
 * Plugin Name: カーメル 保存データ保護
 * Description: 在庫の保存時に、基本情報（年式/走行距離/排気量/色/メーカー等）や見積もり項目が「空で上書き」されるのを防ぎます。古い車を編集画面で開いて更新したときの消失を根本から防止。タイトルは空化に加え「信用回復ローン＋番号だけ」の劣化も検知して元に戻します。本文の空化も防止。ACFの保存が完全に終わった後に復元するので確実に効きます。
 * Version: 1.2.0
 * Author: CARMEL
 *
 * 使い方：wp-content/plugins/ にアップロード →「プラグイン」で有効化するだけ。
 *         「タイトル保護」を入れている場合は無効化してこちらに一本化してください。
 *
 * 仕組み（v1.1 改良）：
 *   - 保存の最初(save_post_portfolio 優先度1)で、保護対象項目の現在値を記録
 *   - ACF等すべての保存が終わった後(save_post 優先度99999)で、
 *     「前は値があったのに空になった保護項目」を1つでも検出したら元に戻す
 *   - 装備チェックなど（保護対象外）はそのまま通すので、通常の編集は邪魔しない
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'Carmel_Save_Guard' ) ) {

class Carmel_Save_Guard {

	/** @var array post_id => 保存前スナップショット（保護対象キーのみ） */
	private static $snap = array();

	public function __construct() {
		add_filter( 'wp_insert_post_data', array( $this, 'protect_title_content' ), 1000, 2 );
		// スナップショットは最速で（カスタム保存やACFが書き込む前）
		add_action( 'save_post_portfolio', array( $this, 'snapshot' ), 1, 1 );
		// 復元は最遅で（ACFの save_post:10 等が終わった後）。save_post 全体の最後に実行。
		add_action( 'save_post', array( $this, 'restore' ), 99999, 1 );
	}

	/* 空で上書きされたら困る「中身」のキー一覧（装備チェックは含めない＝通常編集を邪魔しない） */
	private function protected_keys() {
		return array(
			// 基本情報
			'marker', 'maker', 'type', 'name', 'car_model', 'shashu',
			'year', 'nenshiki', 'mileage', 'soukou', 'soukou_kyori', 'kyori',
			'displacement', 'haikiryou', 'haiki', 'mission', 'mt', 'kudou', 'drive',
			'handle', 'color', 'body_color', 'iro', 'fuel_type', 'fuel', 'nenryou',
			'shaken', 'inspection', 'recicle', 'recycle',
			// 状態・保証・追加情報
			'repair_history', 'exterior_cond', 'interior_cond',
			'seibi', 'kanreichi', 'joutai', 'hoshou', 'hosho', 'warranty', 'shuufuku',
			// 店舗・連絡先
			'shop', 'tel', 'phone', 'denwa', 'line_link', 'line-link', 'contact-link', 'contact_link',
			// 見積もり・価格
			'price', 'total', 'est_total', 'est_honntai', 'est_atamakin', 'est_nenritsu',
		);
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

		// 空 または「信用回復ローン＋番号だけ」の劣化タイトルを検知して守る
		if ( isset( $data['post_title'] ) ) {
			$incoming = trim( (string) $data['post_title'] );
			if ( '' === $incoming || $this->is_degraded_title( $incoming ) ) {
				$prev = get_post_field( 'post_title', $pid );
				$prev = is_string( $prev ) ? trim( $prev ) : '';
				if ( '' !== $prev && ! $this->is_degraded_title( $prev ) ) {
					// 保存前の良いタイトルを維持
					$data['post_title'] = $prev;
				} else {
					// 良い既存タイトルが無ければ車データから作り直す
					$built = $this->build_title( $pid );
					if ( '' !== $built ) { $data['post_title'] = $built; }
					elseif ( '' !== $prev ) { $data['post_title'] = $prev; }
				}
			}
		}
		if ( isset( $data['post_content'] ) && '' === trim( (string) $data['post_content'] ) ) {
			$prev = get_post_field( 'post_content', $pid );
			if ( is_string( $prev ) && '' !== trim( $prev ) ) { $data['post_content'] = $prev; }
		}
		return $data;
	}

	/* 「車情報の無い劣化タイトル」か判定。
	   例：'信用回復ローン' / '信用回復ローン CM-0002' / 'CM-0002' / '低与信ローン' → true */
	private function is_degraded_title( $t ) {
		$t = trim( (string) $t );
		if ( '' === $t ) { return true; }
		// 先頭の接頭語（信用回復ローン/低与信ローン/低与信）を除去
		$r = preg_replace( '/^[\s　]*(信用回復ローン|低与信ローン|低与信)[\s　]*/u', '', $t );
		$r = trim( (string) $r );
		if ( '' === $r ) { return true; }                                  // 接頭語だけ
		if ( preg_match( '/^[A-Za-z]{1,6}[-_]?\d{1,8}$/u', $r ) ) { return true; } // 管理番号だけ(CM-0002 等)
		return false;
	}

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

	/* ---------- メタ（基本情報など）の空上書き保護 ---------- */
	public function snapshot( $post_id ) {
		if ( $this->skip( $post_id ) ) { return; }
		$keys = $this->protected_keys();
		$snap = array();
		foreach ( $keys as $k ) {
			$v = get_post_meta( $post_id, $k, true );
			if ( ! $this->is_blank( $v ) ) { $snap[ $k ] = $v; } // 元々値がある項目だけ記録
		}
		if ( $snap ) { self::$snap[ $post_id ] = $snap; }
	}

	public function restore( $post_id ) {
		// save_post（全post_type）で動くので portfolio 限定＆スナップショット必須
		if ( 'portfolio' !== get_post_type( $post_id ) ) { return; }
		if ( $this->skip( $post_id ) ) { return; }
		if ( empty( self::$snap[ $post_id ] ) ) { return; }

		$restored = 0;
		foreach ( self::$snap[ $post_id ] as $key => $before ) {
			$now = get_post_meta( $post_id, $key, true );
			if ( $this->is_blank( $now ) ) {
				// 前は値があったのに今は空 → 空で上書きされた → 元に戻す
				// ただし年式に〜が混入している場合は〜を除去してから復元
				if ( in_array( $key, array( 'year', 'nenshiki' ), true ) && is_string( $before )
					&& preg_match( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', $before ) ) {
					$before = trim( preg_replace( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', '', $before ) );
					if ( $this->is_blank( $before ) ) { continue; } // 〜だけだったら復元しない
				}
				if ( function_exists( 'update_field' ) ) { update_field( $key, $before, $post_id ); }
				update_post_meta( $post_id, $key, $before );
				$restored++;
			}
		}
		unset( self::$snap[ $post_id ] );

		if ( $restored && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( '[carmel-save-guard] post %d: restored %d emptied field(s)', $post_id, $restored ) );
		}
	}
}

new Carmel_Save_Guard();

/* 年式フィールドに残った〜を保存のたびに自動除去（save_guard の復元後に動く優先度） */
add_action( 'save_post_portfolio', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
	foreach ( array( 'year', 'nenshiki' ) as $key ) {
		$v = get_post_meta( $post_id, $key, true );
		if ( ! is_string( $v ) || ! preg_match( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', $v ) ) { continue; }
		// 〜の後ろに西暦があれば残す。なければ〜だけ除去
		if ( preg_match( '/(19\d{2}|20\d{2})/', $v, $m ) ) {
			$clean = $m[1] . '年';
		} else {
			$clean = trim( preg_replace( '/[\x{301C}\x{FF5E}\x{2053}\x{223C}]/u', '', $v ) );
		}
		if ( '' !== $clean && $clean !== $v ) {
			update_post_meta( $post_id, $key, $clean );
			if ( function_exists( 'update_field' ) ) { update_field( $key, $clean, $post_id ); }
		}
	}
}, 100000 );

}
