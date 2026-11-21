<?php
/** カスタム投稿タイプ：事業 / お問い合わせ */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ais_hub_register_cpt() {
	register_post_type( 'ais_business', array(
		'label'        => 'AIS事業',
		'labels'       => array( 'name' => 'AIS事業', 'singular_name' => '事業', 'add_new_item' => '事業を追加', 'edit_item' => '事業を編集' ),
		'public'       => true,
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-car',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		'has_archive'  => false,
		'rewrite'      => array( 'slug' => 'business', 'with_front' => false ),
	) );

	register_post_type( 'ais_inquiry', array(
		'label'        => 'AISお問い合わせ',
		'labels'       => array( 'name' => 'AISお問い合わせ', 'singular_name' => 'お問い合わせ' ),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'ais-hub',
		'show_in_rest' => false,
		'menu_icon'    => 'dashicons-email',
		'supports'     => array( 'title', 'editor' ),
		'capabilities' => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap' => true,
	) );

	$meta_keys = array( 'ais_en', 'ais_ja', 'ais_cat', 'ais_desc', 'ais_illo', 'ais_layout', 'ais_status', 'ais_cta', 'ais_url' );
	foreach ( $meta_keys as $k ) {
		register_post_meta( 'ais_business', $k, array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'ais_hub_register_cpt' );

/** 事業編集画面のメタボックス */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'ais_business_fields', '事業の表示設定', 'ais_hub_business_metabox', 'ais_business', 'normal', 'high' );
} );

function ais_hub_business_metabox( $post ) {
	wp_nonce_field( 'ais_business_save', 'ais_business_nonce' );
	$fields = array(
		'ais_en'     => array( '英字名（カード大見出し）', 'text' ),
		'ais_ja'     => array( '補足名（カナ等）', 'text' ),
		'ais_cat'    => array( 'カテゴリ（例：中古車販売）', 'text' ),
		'ais_desc'   => array( '一言説明', 'text' ),
		'ais_illo'   => array( 'イラストのキー（carmel/buymo/gps 等）', 'text' ),
		'ais_layout' => array( 'ホーム表示（grid/wide/hidden）', 'text' ),
		'ais_status' => array( '提供状況（提供中/準備中/構想段階）', 'text' ),
		'ais_cta'    => array( 'CTA文言（例：リース相談）', 'text' ),
		'ais_url'    => array( '転送先URL（空欄=事業ページ／外部URLは別タブで開く）', 'text' ),
	);
	echo '<table class="form-table">';
	foreach ( $fields as $key => $f ) {
		$val = esc_attr( get_post_meta( $post->ID, $key, true ) );
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $f[0] ) . '</label></th>';
		echo '<td><input type="text" style="width:100%" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . $val . '"></td></tr>';
	}
	echo '</table>';
	echo '<p class="description">画像は「アイキャッチ画像」を設定すると、イラストより優先してカードに表示されます。</p>';
}

add_action( 'save_post_ais_business', function ( $post_id ) {
	if ( ! isset( $_POST['ais_business_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_business_nonce'] ) ), 'ais_business_save' ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	foreach ( array( 'ais_en', 'ais_ja', 'ais_cat', 'ais_desc', 'ais_illo', 'ais_layout', 'ais_status', 'ais_cta' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	if ( isset( $_POST['ais_url'] ) ) {
		update_post_meta( $post_id, 'ais_url', esc_url_raw( wp_unslash( $_POST['ais_url'] ) ) );
	}
} );
