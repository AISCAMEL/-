<?php
/**
 * カーメル：コンテンツの入れ物を保持（記事・加盟店ブログ・ニュースの投稿タイプ／店舗タクソノミー）
 * ---------------------------------------------------------------------------
 * 目的：旧プラグイン（CARMEL統合管理・CARMEL自動生成）を止めても、外部のカーメルシステムが投稿する先
 *       （メディア記事・加盟店ブログ・ニュース・店舗の分類）がそのまま残るようにする。
 *
 * 仕組み：
 *   1) 他のプラグインがこれらを登録している間は、その登録内容（表示名・URLの形・REST・対応機能など）を
 *      そのまま「控え」として保存する（毎回、変化があれば更新）。
 *   2) 登録するプラグインが無くなったら、控えの内容で同じものを登録する（＝引き継ぎ）。
 * 旧プラグインの登録内容を写すだけなので、見た目・URL・投稿データは変わらない。
 *   3) 旧プラグインを止めた後に残る定期実行の予約（5分おきのSlack確認など、空回りするもの）を取り消す。
 *      旧プラグインが動いている間は触らない。
 * 投稿・設定は何も削除しない。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_ctypes_watch' ) ) {
	/** 保持する対象 */
	function carmelx_ctypes_watch() {
		return array(
			'post_types' => array( 'media_article', 'shop_blog', 'news', 'article_brief' ),
			'taxonomies' => array( 'carmel_store', 'media_topic' ),
		);
	}
}

if ( ! function_exists( 'carmelx_ctypes_plain' ) ) {
	/** 保存できる値だけ残す（関数・オブジェクトは捨てる） */
	function carmelx_ctypes_plain( $v ) {
		if ( is_object( $v ) ) { $v = $v instanceof Closure ? null : get_object_vars( $v ); }
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $x ) { $x = carmelx_ctypes_plain( $x ); if ( null !== $x ) { $out[ $k ] = $x; } }
			return $out;
		}
		return ( is_scalar( $v ) || null === $v ) ? $v : null;
	}
}

if ( ! function_exists( 'carmelx_ctypes_args_pt' ) ) {
	/** 登録済みの投稿タイプ → register_post_type に渡せる形 */
	function carmelx_ctypes_args_pt( $name ) {
		$o = get_post_type_object( $name );
		if ( ! $o ) { return null; }
		$args = array( 'labels' => carmelx_ctypes_plain( $o->labels ), 'supports' => array_keys( (array) get_all_post_type_supports( $name ) ), 'taxonomies' => array_values( (array) get_object_taxonomies( $name ) ), 'capabilities' => carmelx_ctypes_plain( $o->cap ) );
		foreach ( array( 'label', 'description', 'public', 'hierarchical', 'exclude_from_search', 'publicly_queryable', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'show_in_admin_bar', 'menu_position', 'menu_icon', 'capability_type', 'map_meta_cap', 'has_archive', 'rewrite', 'query_var', 'can_export', 'delete_with_user', 'show_in_rest', 'rest_base', 'rest_namespace', 'rest_controller_class', 'template', 'template_lock' ) as $k ) {
			if ( property_exists( $o, $k ) ) { $v = carmelx_ctypes_plain( $o->$k ); if ( null !== $v ) { $args[ $k ] = $v; } }
		}
		if ( isset( $args['capability_type'] ) && is_array( $args['capability_type'] ) ) { $args['capability_type'] = array_values( $args['capability_type'] ); }
		return $args;
	}
}

if ( ! function_exists( 'carmelx_ctypes_args_tax' ) ) {
	/** 登録済みのタクソノミー → register_taxonomy に渡せる形 */
	function carmelx_ctypes_args_tax( $name ) {
		$o = get_taxonomy( $name );
		if ( ! $o ) { return null; }
		$args = array( 'labels' => carmelx_ctypes_plain( $o->labels ), 'capabilities' => carmelx_ctypes_plain( $o->cap ) );
		foreach ( array( 'label', 'description', 'public', 'publicly_queryable', 'hierarchical', 'show_ui', 'show_in_menu', 'show_in_nav_menus', 'show_tagcloud', 'show_in_quick_edit', 'show_admin_column', 'rewrite', 'query_var', 'show_in_rest', 'rest_base', 'rest_namespace', 'rest_controller_class', 'default_term', 'sort' ) as $k ) {
			if ( property_exists( $o, $k ) ) { $v = carmelx_ctypes_plain( $o->$k ); if ( null !== $v ) { $args[ $k ] = $v; } }
		}
		// 編集画面の選択欄：標準のもの（カテゴリー型／タグ型）の時だけ引き継ぐ
		if ( is_string( $o->meta_box_cb ) && in_array( $o->meta_box_cb, array( 'post_categories_meta_box', 'post_tags_meta_box' ), true ) ) { $args['meta_box_cb'] = $o->meta_box_cb; }
		return array( 'object_type' => array_values( (array) $o->object_type ), 'args' => $args );
	}
}

if ( ! function_exists( 'carmelx_ctypes_ours' ) ) {
	/** この表示の間に、このプラグインが登録したもの（名前 => 登録したオブジェクト） */
	function &carmelx_ctypes_ours() { static $ours = array( 'pt' => array(), 'tax' => array() ); return $ours; }
}

if ( ! function_exists( 'carmelx_ctypes_takeover' ) ) {
	/** 登録するプラグインが無くなったものを、控えの内容で登録する */
	function carmelx_ctypes_takeover() {
		$snap = get_option( 'carmelx_ctypes_snapshot', array() );
		if ( ! is_array( $snap ) ) { return; }
		$ours = &carmelx_ctypes_ours();
		$w    = carmelx_ctypes_watch();
		// 先に投稿タイプ、次にタクソノミー（対象の投稿タイプが在る状態で結びつける）
		foreach ( $w['post_types'] as $pt ) {
			if ( post_type_exists( $pt ) || empty( $snap['pt'][ $pt ] ) || ! is_array( $snap['pt'][ $pt ] ) ) { continue; }
			$r = register_post_type( $pt, $snap['pt'][ $pt ] );
			if ( ! is_wp_error( $r ) ) { $ours['pt'][ $pt ] = get_post_type_object( $pt ); }
		}
		foreach ( $w['taxonomies'] as $tx ) {
			if ( taxonomy_exists( $tx ) || empty( $snap['tax'][ $tx ]['args'] ) ) { continue; }
			$types = array_values( array_filter( (array) $snap['tax'][ $tx ]['object_type'], 'post_type_exists' ) );
			$r     = register_taxonomy( $tx, $types, (array) $snap['tax'][ $tx ]['args'] );
			if ( ! is_wp_error( $r ) ) { $ours['tax'][ $tx ] = get_taxonomy( $tx ); }
		}
		// 新しく引き継いだものがあれば、URLの決まりを1回だけ作り直す
		$now  = array_merge( array_keys( $ours['pt'] ), array_keys( $ours['tax'] ) );
		sort( $now );
		$sig  = implode( ',', $now );
		if ( $sig !== (string) get_option( 'carmelx_ctypes_owned', '' ) ) {
			update_option( 'carmelx_ctypes_owned', $sig, false );
			if ( '' !== $sig ) { flush_rewrite_rules( false ); }
		}
	}
	add_action( 'init', 'carmelx_ctypes_takeover', 30 );
}

if ( ! function_exists( 'carmelx_ctypes_snapshot' ) ) {
	/** 他のプラグインが登録しているものの内容を、控えとして保存する（変化があった時だけ書き込む） */
	function carmelx_ctypes_snapshot() {
		$snap = get_option( 'carmelx_ctypes_snapshot', array() );
		if ( ! is_array( $snap ) ) { $snap = array(); }
		$ours = &carmelx_ctypes_ours();
		$w    = carmelx_ctypes_watch();
		$new  = $snap;
		foreach ( $w['post_types'] as $pt ) {
			$o = get_post_type_object( $pt );
			// 他のプラグインが登録している（このプラグインが登録したオブジェクトではない）時だけ控えを更新
			if ( $o && ( ! isset( $ours['pt'][ $pt ] ) || $ours['pt'][ $pt ] !== $o ) ) { $a = carmelx_ctypes_args_pt( $pt ); if ( $a ) { $new['pt'][ $pt ] = $a; } }
		}
		foreach ( $w['taxonomies'] as $tx ) {
			$o = get_taxonomy( $tx );
			if ( $o && ( ! isset( $ours['tax'][ $tx ] ) || $ours['tax'][ $tx ] !== $o ) ) { $a = carmelx_ctypes_args_tax( $tx ); if ( $a ) { $new['tax'][ $tx ] = $a; } }
		}
		if ( md5( wp_json_encode( $new ) ) !== md5( wp_json_encode( $snap ) ) ) { update_option( 'carmelx_ctypes_snapshot', $new, false ); }
	}
	add_action( 'init', 'carmelx_ctypes_snapshot', 9999 );
}

if ( ! function_exists( 'carmelx_ctypes_old_crons' ) ) {
	/**
	 * 旧プラグインの定期実行（プラグインを止めても、予約だけが残って空回りするもの）。
	 * 目印の関数 => その関数が無い時（＝プラグインが止まっている時）に取り消す予約の名前
	 */
	function carmelx_ctypes_old_crons() {
		return array(
			// CARMEL 自動生成（毎日自動）
			'carmel3_auto_run' => array( 'carmel3_auto_generate_cron', 'carmel3_auto_img_worker', 'carmel3_auto_run_single', 'carmel3_regen_single', 'carmel3_news_generate_single', 'carmel3_slack_poll_tick', 'carmel3_eyecatch_single', 'carmel3_daily_tick', 'carmel3_daily_now', 'carmel3_rewrite_tick', 'carmel3_rewrite_now' ),
			// CARMEL統合管理プラグイン
			'carmel_check_fb_token_expiry' => array( 'carmel_daily_token_check', 'carmel_process_sns_schedules' ),
		);
	}
}

if ( ! function_exists( 'carmelx_ctypes_cron_cleanup' ) ) {
	/**
	 * 止めた旧プラグインの定期実行の予約を取り消す（管理画面を開いた時に確認。1日1回まで）。
	 * 旧プラグインを有効に戻せば、旧プラグイン自身が予約を作り直す。
	 * @return string[] 取り消した予約の名前
	 */
	function carmelx_ctypes_cron_cleanup( $force = false ) {
		if ( ! $force && get_transient( 'carmelx_ctypes_cron_checked' ) ) { return array(); }
		set_transient( 'carmelx_ctypes_cron_checked', 1, DAY_IN_SECONDS );
		$cleared = array();
		foreach ( carmelx_ctypes_old_crons() as $marker => $hooks ) {
			if ( function_exists( $marker ) ) { continue; } // 旧プラグインが動いている → 触らない
			foreach ( $hooks as $h ) {
				if ( wp_next_scheduled( $h ) || ( function_exists( 'wp_get_scheduled_event' ) && wp_get_scheduled_event( $h ) ) ) { $cleared[] = $h; }
				if ( function_exists( 'wp_unschedule_hook' ) ) { wp_unschedule_hook( $h ); } else { wp_clear_scheduled_hook( $h ); }
			}
		}
		return $cleared;
	}
	add_action( 'admin_init', 'carmelx_ctypes_cron_cleanup', 50 );
}

if ( ! function_exists( 'carmelx_ctypes_status' ) ) {
	/** いまの状態（構成調査ページ用）：名前 => 説明 */
	function carmelx_ctypes_status() {
		$snap = get_option( 'carmelx_ctypes_snapshot', array() );
		$ours = &carmelx_ctypes_ours();
		$w    = carmelx_ctypes_watch();
		$out  = array();
		foreach ( $w['post_types'] as $pt ) {
			$has = ! empty( $snap['pt'][ $pt ] );
			if ( isset( $ours['pt'][ $pt ] ) && get_post_type_object( $pt ) === $ours['pt'][ $pt ] ) { $out[ '投稿タイプ ' . $pt ] = 'カーメルシステムが登録中（引き継ぎ済み）'; }
			elseif ( post_type_exists( $pt ) ) { $out[ '投稿タイプ ' . $pt ] = '他のプラグインが登録中 ／ 控え ' . ( $has ? 'あり' : 'なし（管理画面をもう一度開くと作られます）' ); }
			else { $out[ '投稿タイプ ' . $pt ] = '未登録 ／ 控え ' . ( $has ? 'あり（次の表示で引き継ぎます）' : 'なし' ); }
		}
		foreach ( $w['taxonomies'] as $tx ) {
			$has = ! empty( $snap['tax'][ $tx ]['args'] );
			if ( isset( $ours['tax'][ $tx ] ) && get_taxonomy( $tx ) === $ours['tax'][ $tx ] ) { $out[ 'タクソノミー ' . $tx ] = 'カーメルシステムが登録中（引き継ぎ済み）'; }
			elseif ( taxonomy_exists( $tx ) ) { $out[ 'タクソノミー ' . $tx ] = '他のプラグインが登録中 ／ 控え ' . ( $has ? 'あり' : 'なし（管理画面をもう一度開くと作られます）' ); }
			else { $out[ 'タクソノミー ' . $tx ] = '未登録 ／ 控え ' . ( $has ? 'あり（次の表示で引き継ぎます）' : 'なし' ); }
		}
		return $out;
	}
}
