<?php
/**
 * カーメル：車両詳細ページ 装備表示（ショートコード [carmel_equipment]）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫(portfolio)のチェック済み装備を、カテゴリ別に自動で一覧表示する。
 *        全装備フィールドを1か所で定義しているので、今後増えても1行追加でOK。
 *
 * 使い方 :
 *   - 車両詳細テンプレート/固定ページ/エディタに  [carmel_equipment]  を置く
 *   - 特定投稿を指定: [carmel_equipment id="5049"]
 *   - PHPテンプレートから直接:  echo carmel_render_equipment( get_the_ID() );
 *
 * 表示 : チェックの入った装備だけを、カテゴリ見出し付きのバッジで表示。
 *        1件もチェックが無いカテゴリは出力しない。
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）。統合版にも内包。
 * ---------------------------------------------------------------------------
 */

/* 装備フィールド定義（カテゴリ => [ data-name => 表示名 ]）
   ※ STEP2/ACF と同じ data-name。装備を増やすときはここに1行足すだけ。 */
function carmel_equipment_map() {
	return array(
		'ナビ・TV' => array(
			'nav' => 'DVDナビ', 'nav2' => 'HDDナビ', 'nav3' => 'メモリーナビ',
			'nav4' => 'ワンセグTV', 'nav5' => 'フルセグTV', 'monitar' => '後席モニター',
			'junsei_nav' => '純正ナビ', 'shagai_nav' => '社外ナビ',
		),
		'オーディオ' => array(
			'blueray' => 'ブルーレイ再生', 'dvd' => 'DVD再生', 'sarver' => 'ミュージックサーバー',
			'usb' => 'USB・iPod接続', 'cd' => 'CD再生', 'bluetooth' => 'Bluetooth', 'hdmi' => 'HDMI',
			'carplay' => 'Apple CarPlay', 'androidauto' => 'Android Auto',
		),
		'安全・運転支援' => array(
			'airbag' => '運転席エアバッグ', 'airbag2' => '助手席エアバッグ', 'airbag3' => 'サイドエアバッグ',
			'airbag4' => 'カーテンエアバッグ', 'abs' => 'ABS', 'esc' => '横滑り防止装置',
			'shoutotu' => '衝突被害軽減ブレーキ', 'asist' => 'ナイトビューアシスト',
			'kamera' => 'フロントカメラ', 'kamera2' => 'サイドカメラ', 'kamera3' => 'バックカメラ',
			'kamera4' => '全周囲カメラ', 'asist2' => 'パーキングアシスト',
			'sensar' => 'フロントセンサー', 'sensar2' => 'リアセンサー',
			'lane_assist' => 'レーンアシスト', 'acc' => 'アダプティブクルーズ',
			'corner_sensor' => 'コーナーセンサー', 'drive_recorder' => 'ドライブレコーダー',
		),
		'快適・内装' => array(
			'aircon' => 'エアコン', 'aircon2' => 'Wエアコン', 'stea' => 'パワーステアリング',
			'window' => 'パワーウィンドウ', 'keyless' => 'キーレス', 'smartkey' => 'スマートキー',
			'push_start' => 'プッシュスタート', 'controll' => 'クルーズコントロール',
			'stop' => 'アイドリングストップ', 'tounan' => '盗難防止装置',
			'seat' => '運転席電動シート', 'seat2' => '助手席電動シート', 'seat3' => '後席電動シート',
			'memory' => 'シートメモリー', 'heater' => 'シートヒーター', 'otman' => 'オットマン',
			'work' => 'ウォークスルー', 'ventilation_seat' => 'ベンチレーションシート',
			'leather_seat' => '革シート',
		),
		'外装・ドア・ルーフ' => array(
			'door' => '電動スライドドア', 'door2' => 'イージークローザードア',
			'gate' => '電動トランク・リアゲート', 'almi' => 'アルミホイール', 'earo' => 'エアロパーツ',
			'down' => 'ローダウン', 'liftup' => 'リフトアップ', 'sunroof' => 'サンルーフ',
			'panorama_roof' => 'パノラマルーフ', 'led_light' => 'LEDヘッドライト',
			'fog_lamp' => 'フォグランプ', 'adaptive_light' => 'アダプティブライト',
		),
		'その他・車歴' => array(
			'etc' => 'ETC', 'etc2' => 'ETC2.0', 'kinen_sha' => '禁煙車', 'one_owner' => 'ワンオーナー',
			'kirokubo' => '記録簿あり', 'seibi_zumi' => '整備済み', 'shuufuku_nashi' => '修復歴なし',
		),
	);
}

/* 値が「チェック済み」か判定 */
function carmel_equip_checked( $name, $pid ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, $pid ) : get_post_meta( $pid, $name, true );
	if ( is_array( $v ) ) { return ! empty( $v ); }
	return ! empty( $v ) && $v !== '0';
}

/* 装備一覧HTMLを返す */
function carmel_render_equipment( $pid = 0 ) {
	if ( ! $pid ) { $pid = get_the_ID(); }
	if ( ! $pid ) { return ''; }

	$map = carmel_equipment_map();
	$out = '';
	foreach ( $map as $cat => $items ) {
		$badges = '';
		foreach ( $items as $name => $label ) {
			if ( carmel_equip_checked( $name, $pid ) ) {
				$badges .= '<li class="carmel-eq-item">' . esc_html( $label ) . '</li>';
			}
		}
		if ( $badges ) {
			$out .= '<div class="carmel-eq-cat"><h4 class="carmel-eq-h">' . esc_html( $cat ) . '</h4>'
				. '<ul class="carmel-eq-list">' . $badges . '</ul></div>';
		}
	}
	if ( ! $out ) { return ''; }

	return '<div class="carmel-equipment">' . $out . '</div>';
}

/* ショートコード [carmel_equipment id="123"] */
add_shortcode( 'carmel_equipment', 'carmel_equipment_shortcode' );
function carmel_equipment_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	return carmel_render_equipment( (int) $atts['id'] );
}

/* フロント用スタイル */
add_action( 'wp_head', 'carmel_equipment_style' );
function carmel_equipment_style() {
	?>
	<style>
	.carmel-equipment { margin:16px 0; }
	.carmel-eq-cat { margin-bottom:14px; }
	.carmel-eq-h { margin:0 0 6px; font-size:15px; font-weight:700; color:#1f2d3d;
		border-left:4px solid #c0392b; padding-left:8px; }
	.carmel-eq-list { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:6px; }
	.carmel-eq-item { display:inline-block; padding:4px 10px; font-size:13px; line-height:1.4;
		background:#f3f6fa; border:1px solid #d9e0e8; border-radius:14px; color:#333; }
	</style>
	<?php
}
