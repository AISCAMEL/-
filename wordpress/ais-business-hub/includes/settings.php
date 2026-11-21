<?php
/** サイト設定（ヒーロー・実績・理念・連絡先）と管理メニュー */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ais_hub_default_settings() {
	return array(
		'hero_title'       => "モビリティ × AI × プラットフォームで\n人とクルマとビジネスの\n新しい価値を創る。",
		'hero_lead'        => '合同会社アイズ（AIS）は、自動車ビジネスを軸に、AI・デジタル・グローバル・プラットフォーム事業を展開する次世代モビリティ企業です。',
		'hero_cta1_label'  => '事業・サービス一覧',
		'hero_cta1_url'    => '/business/',
		'hero_cta2_label'  => '2分でわかるAIS',
		'hero_cta2_url'    => '#',
		'hero_image'       => '',
		'philo_quote'      => "「クルマを通じて、\n人の暮らしと挑戦を支える。」",
		'philo_lead'       => 'モビリティの提供にとどまらず、AIとデジタルの力で地域・日本・そして世界をつなぎ、人とビジネスの可能性を広げていきます。',
		'notify_email'     => get_option( 'admin_email' ),
		'show_stats'       => 1,
		'stats'            => ais_hub_default_stats(),
		'stats_note'       => '',
		'show_news'        => 1,
	);
}

function ais_hub_get( $key, $default = '' ) {
	$opt = get_option( 'ais_hub_settings', array() );
	if ( is_array( $opt ) && array_key_exists( $key, $opt ) ) {
		return $opt[ $key ];
	}
	$def = ais_hub_default_settings();
	return array_key_exists( $key, $def ) ? $def[ $key ] : $default;
}

/** 管理メニュー */
add_action( 'admin_menu', function () {
	add_menu_page( 'AIS Business Hub', 'AIS Business Hub', 'manage_options', 'ais-hub', 'ais_hub_dashboard_page', 'dashicons-admin-site-alt3', 25 );
	add_submenu_page( 'ais-hub', 'ダッシュボード', 'ダッシュボード', 'manage_options', 'ais-hub', 'ais_hub_dashboard_page' );
	add_submenu_page( 'ais-hub', 'サイト設定', 'サイト設定', 'manage_options', 'ais-hub-settings', 'ais_hub_settings_page' );
} );

function ais_hub_dashboard_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$done = (int) get_option( 'ais_hub_setup_done', 0 );
	echo '<div class="wrap"><h1>AIS Business Hub</h1>';
	echo '<p>合同会社アイズ公式サイトの管理基盤です（v' . esc_html( AIS_HUB_VER ) . '）。</p>';
	echo '<ol>';
	echo '<li><strong>一括セットアップ</strong>：固定ページ・事業データ・サンプルニュースを作成し、ホームを設定します。 <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=ais-hub-setup' ) ) . '">一括セットアップへ</a></li>';
	echo '<li><strong>サイト設定</strong>：ヒーロー・実績・理念・通知先を編集。 <a class="button" href="' . esc_url( admin_url( 'admin.php?page=ais-hub-settings' ) ) . '">サイト設定へ</a></li>';
	echo '<li><strong>事業</strong>：各事業の文言・画像・表示を編集。 <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=ais_business' ) ) . '">事業一覧へ</a></li>';
	echo '</ol>';
	echo '<p>セットアップ状態：' . ( $done ? '<span style="color:#2f8f66">実施済み</span>' : '<span style="color:#c0453f">未実施</span>' ) . '</p>';
	echo '<hr><h2>未接続・未実装（正直な状態）</h2><ul style="list-style:disc;padding-left:20px">';
	echo '<li>AIコンシェルジュ／AI記事生成の<strong>外部API接続は未設定</strong>です（UIと導線のみ）。APIキー設定後に有効化します。</li>';
	echo '<li>モックの実績数値・ニュースは<strong>サンプル</strong>です。事実確認・承認後に差し替えてください。</li>';
	echo '<li>ヒーロー等の写真は<strong>権利確認済みの実写</strong>に差し替え前提のプレースホルダです。</li>';
	echo '</ul></div>';
}

function ais_hub_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>'; }
	$s = wp_parse_args( get_option( 'ais_hub_settings', array() ), ais_hub_default_settings() );
	$stats = is_array( $s['stats'] ) ? $s['stats'] : ais_hub_default_stats();
	echo '<div class="wrap"><h1>AIS サイト設定</h1>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ais_hub_save_settings">';
	wp_nonce_field( 'ais_hub_settings', 'ais_hub_settings_nonce' );
	echo '<h2>ヒーロー</h2><table class="form-table">';
	ais_hub_field_textarea( '見出し（改行で分割）', 'hero_title', $s['hero_title'] );
	ais_hub_field_textarea( 'リード文', 'hero_lead', $s['hero_lead'] );
	ais_hub_field_text( 'CTA1 ラベル', 'hero_cta1_label', $s['hero_cta1_label'] );
	ais_hub_field_text( 'CTA1 リンク', 'hero_cta1_url', $s['hero_cta1_url'] );
	ais_hub_field_text( 'CTA2 ラベル', 'hero_cta2_label', $s['hero_cta2_label'] );
	ais_hub_field_text( 'CTA2 リンク', 'hero_cta2_url', $s['hero_cta2_url'] );
	ais_hub_field_text( 'ヒーロー画像URL（任意・実写）', 'hero_image', $s['hero_image'] );
	echo '</table>';
	echo '<h2>実績ストリップ <label style="font-size:13px;font-weight:400"><input type="checkbox" name="show_stats" value="1" ' . checked( ! empty( $s['show_stats'] ), true, false ) . '> 表示する</label></h2>';
	echo '<p class="description">モックの数値はサンプルです。公開前に承認済みの実数へ変更するか、非表示にしてください。</p><table class="form-table">';
	foreach ( $stats as $i => $st ) {
		$l = esc_attr( isset( $st['label'] ) ? $st['label'] : '' );
		$v = esc_attr( isset( $st['value'] ) ? $st['value'] : '' );
		echo '<tr><th>項目' . ( $i + 1 ) . '</th><td><input type="text" name="stats_label[]" value="' . $l . '" placeholder="ラベル" style="width:38%"> <input type="text" name="stats_value[]" value="' . $v . '" placeholder="値" style="width:38%"></td></tr>';
	}
	ais_hub_field_text( '注記', 'stats_note', $s['stats_note'] );
	echo '</table>';
	echo '<h2>理念</h2><table class="form-table">';
	ais_hub_field_textarea( '引用（改行可）', 'philo_quote', $s['philo_quote'] );
	ais_hub_field_textarea( 'リード文', 'philo_lead', $s['philo_lead'] );
	echo '</table>';
	echo '<h2>ニュース・通知</h2><table class="form-table">';
	echo '<tr><th>ホームにニュースを表示</th><td><label><input type="checkbox" name="show_news" value="1" ' . checked( ! empty( $s['show_news'] ), true, false ) . '> 表示する（記事が無い場合はサンプルを表示）</label></td></tr>';
	ais_hub_field_text( 'お問い合わせ通知メール', 'notify_email', $s['notify_email'] );
	echo '</table>';
	submit_button( '保存' );
	echo '</form></div>';
}

function ais_hub_field_text( $label, $name, $value ) {
	echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" style="width:100%"></td></tr>';
}
function ais_hub_field_textarea( $label, $name, $value ) {
	echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><textarea id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" rows="3" style="width:100%">' . esc_textarea( $value ) . '</textarea></td></tr>';
}

add_action( 'admin_post_ais_hub_save_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', 403 ); }
	if ( ! isset( $_POST['ais_hub_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_settings_nonce'] ) ), 'ais_hub_settings' ) ) { wp_die( '不正なリクエストです', 403 ); }
	$def = ais_hub_default_settings();
	$out = array();
	foreach ( array( 'hero_title', 'hero_lead', 'philo_quote', 'philo_lead' ) as $k ) {
		$out[ $k ] = sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? $def[ $k ] ) );
	}
	foreach ( array( 'hero_cta1_label', 'hero_cta1_url', 'hero_cta2_label', 'hero_cta2_url', 'hero_image', 'stats_note' ) as $k ) {
		$out[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? $def[ $k ] ) );
	}
	$out['notify_email'] = sanitize_email( wp_unslash( $_POST['notify_email'] ?? $def['notify_email'] ) );
	$out['show_stats']   = empty( $_POST['show_stats'] ) ? 0 : 1;
	$out['show_news']    = empty( $_POST['show_news'] ) ? 0 : 1;
	$labels = isset( $_POST['stats_label'] ) ? (array) wp_unslash( $_POST['stats_label'] ) : array();
	$values = isset( $_POST['stats_value'] ) ? (array) wp_unslash( $_POST['stats_value'] ) : array();
	$icons  = wp_list_pluck( ais_hub_default_stats(), 'icon' );
	$stats  = array();
	foreach ( $labels as $i => $lab ) {
		$stats[] = array(
			'icon'  => $icons[ $i ] ?? 'i-chart',
			'label' => sanitize_text_field( $lab ),
			'value' => sanitize_text_field( $values[ $i ] ?? '' ),
		);
	}
	$out['stats'] = $stats ? $stats : ais_hub_default_stats();
	update_option( 'ais_hub_settings', $out );
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-settings&updated=1' ) );
	exit;
} );
