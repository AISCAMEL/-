<?php
/**
 * 代理店の仕組み（本部の管理と、代理店専用ページ）。
 *
 * 本部（管理画面「代理店」）
 * - 代理店の登録：種類（紹介・販売・OEM）、代理店コード、報酬率（初期費用・月額）、状態、振込先
 * - 代理店の申し込み（お問い合わせ）から1クリックで登録、代理店専用ページのアカウント発行
 * - 契約に「紹介した代理店」をつける（メールアドレスから自動で判定、手動で変更も可）
 * - 報酬・支払い：月ごとに代理店別の報酬（初期費用×率＋月額×率）を計算、支払い済みの記録、CSV
 *
 * 代理店（/agency/ ・ログインが必要）は page-templates/page-agency.php。
 *
 * 報酬の考え方：契約開始日の月に「初期費用＋制作費」×初期の率、
 * 契約開始の月から毎月「月額」×月額の率（解約した月まで。継続の月数を決めた場合はその月数まで）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 代理店の種類。 */
function apprex_agency_types() {
	return array( 'introducer' => '紹介代理店', 'reseller' => '販売代理店', 'oem' => 'OEM代理店' );
}

/** 代理店の状態。 */
function apprex_agency_statuses() {
	return array( 'pending' => '審査中', 'active' => '有効', 'suspended' => '停止' );
}

/** 代理店の登録項目。 */
function apprex_agency_fields() {
	return array(
		'company'      => array( '会社名・屋号', 'text' ),
		'contact'      => array( '担当者名', 'text' ),
		'email'        => array( 'メール（ログインに使います）', 'email' ),
		'phone'        => array( '電話', 'text' ),
		'type'         => array( '代理店の種類', 'type' ),
		'status'       => array( '状態', 'status' ),
		'code'         => array( '代理店コード（紹介URLの ?ref= の後ろ）', 'code' ),
		'rate_initial' => array( '報酬率：初期費用（%）', 'rate' ),
		'rate_monthly' => array( '報酬率：月額（%）', 'rate' ),
		'months'       => array( '月額の報酬を払う月数（0＝契約が続くあいだ）', 'int' ),
		'bank'         => array( '振込先（銀行・支店・種別・口座番号・名義）', 'textarea' ),
		'note'         => array( '本部メモ（代理店には見えません）', 'textarea' ),
	);
}

/** 代理店の情報。 */
function apprex_agency_get( $id ) {
	$v = get_post_meta( $id, 'apprex_ag', true );
	$v = is_array( $v ) ? $v : array();
	return wp_parse_args( $v, array( 'company' => '', 'contact' => '', 'email' => '', 'phone' => '', 'type' => 'introducer', 'status' => 'pending', 'code' => '', 'rate_initial' => 0, 'rate_monthly' => 0, 'months' => 0, 'bank' => '', 'note' => '', 'user_id' => 0 ) );
}

/** 代理店コードから代理店ID。 */
function apprex_agency_by_code( $code ) {
	if ( '' === (string) $code ) {
		return 0;
	}
	$ids = get_posts( array( 'post_type' => 'apprex_agency', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'apprex_ag_code', 'meta_value' => $code ) );
	return $ids ? (int) $ids[0] : 0;
}

/** ログイン中のユーザーの代理店ID（無ければ0）。 */
function apprex_agency_for_user( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 0;
	}
	$ids = get_posts( array( 'post_type' => 'apprex_agency', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => 'apprex_ag_user', 'meta_value' => (int) $user_id ) );
	return $ids ? (int) $ids[0] : 0;
}

/** 代理店専用ページのURL。 */
function apprex_agency_portal_url() {
	return home_url( '/agency/' );
}

/** 受付・発注を探すときの状態（発注は独自の状態を使う）。 */
function apprex_agency_statuses_any() {
	return array( 'publish', 'apprex_new', 'private', 'draft', 'pending', 'future' );
}

/* 代理店専用ページは検索エンジンに出さない。 */
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_page_template( 'page-templates/page-agency.php' ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$p = get_page_by_path( 'agency' );
		if ( $p ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), array( $p->ID ) );
		}
	}
	return $args;
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 投稿タイプ・権限
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type(
		'apprex_agency',
		array(
			'labels'        => array(
				'name'          => '代理店',
				'singular_name' => '代理店',
				'add_new'       => '代理店を追加',
				'add_new_item'  => '代理店を追加',
				'edit_item'     => '代理店の情報',
				'all_items'     => '代理店の一覧',
				'menu_name'     => '代理店',
			),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 27,
			'menu_icon'     => 'dashicons-groups',
			'supports'      => array( 'title' ),
		)
	);
	if ( ! get_role( 'apprex_agency' ) ) {
		add_role( 'apprex_agency', '代理店', array( 'read' => true ) );
	}
} );

/* 代理店のアカウントは管理画面に入れない（代理店専用ページへ）。 */
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! is_user_logged_in() || ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) ) {
		return;
	}
	$u = wp_get_current_user();
	if ( in_array( 'apprex_agency', (array) $u->roles, true ) && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( apprex_agency_portal_url() );
		exit;
	}
} );
add_filter( 'show_admin_bar', function ( $show ) {
	$u = wp_get_current_user();
	return ( $u && in_array( 'apprex_agency', (array) $u->roles, true ) && ! current_user_can( 'edit_posts' ) ) ? false : $show;
} );
add_filter( 'login_redirect', function ( $to, $req, $user ) {
	return ( $user instanceof WP_User && in_array( 'apprex_agency', (array) $user->roles, true ) ) ? apprex_agency_portal_url() : $to;
}, 20, 3 );

/* -------------------------------------------------------------------------
 * 代理店の編集画面
 * ---------------------------------------------------------------------- */
add_filter( 'enter_title_here', function ( $text, $post ) {
	return 'apprex_agency' === $post->post_type ? '代理店名（管理用）' : $text;
}, 10, 2 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_agency_info', '代理店の情報', 'apprex_agency_info_box', 'apprex_agency', 'normal', 'high' );
	add_meta_box( 'apprex_agency_sum', '紹介と報酬', 'apprex_agency_sum_box', 'apprex_agency', 'normal', 'default' );
	add_meta_box( 'apprex_agency_acct', '代理店専用ページ', 'apprex_agency_acct_box', 'apprex_agency', 'side', 'high' );
	add_meta_box( 'apprex_contract_agency', '紹介した代理店', 'apprex_contract_agency_box', 'apprex_contract', 'side', 'default' );
} );

/** 代理店の情報。 */
function apprex_agency_info_box( $post ) {
	$v = apprex_agency_get( $post->ID );
	wp_nonce_field( 'apprex_agency_save', 'apprex_agency_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( apprex_agency_fields() as $k => $f ) {
		$val = $v[ $k ];
		echo '<tr><th><label for="ag-' . esc_attr( $k ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
		switch ( $f[1] ) {
			case 'type':
			case 'status':
				$opts = 'type' === $f[1] ? apprex_agency_types() : apprex_agency_statuses();
				echo '<select id="ag-' . esc_attr( $k ) . '" name="apprex_ag[' . esc_attr( $k ) . ']">';
				foreach ( $opts as $ok => $ol ) {
					echo '<option value="' . esc_attr( $ok ) . '"' . selected( $val, $ok, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select>';
				break;
			case 'textarea':
				echo '<textarea id="ag-' . esc_attr( $k ) . '" name="apprex_ag[' . esc_attr( $k ) . ']" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
				break;
			case 'rate':
			case 'int':
				echo '<input type="number" id="ag-' . esc_attr( $k ) . '" name="apprex_ag[' . esc_attr( $k ) . ']" value="' . esc_attr( $val ) . '" min="0" max="' . ( 'rate' === $f[1] ? '100' : '120' ) . '" step="' . ( 'rate' === $f[1] ? '0.5' : '1' ) . '" style="width:100px">';
				break;
			case 'code':
				echo '<input type="text" id="ag-code" name="apprex_ag[code]" value="' . esc_attr( $val ) . '" class="regular-text" pattern="[A-Za-z0-9_-]{2,32}" placeholder="空なら自動で作ります">';
				if ( $val ) {
					echo '<p class="description">紹介URL：<code>' . esc_html( add_query_arg( 'ref', $val, home_url( '/' ) ) ) . '</code></p>';
				}
				break;
			default:
				echo '<input type="' . ( 'email' === $f[1] ? 'email' : 'text' ) . '" id="ag-' . esc_attr( $k ) . '" name="apprex_ag[' . esc_attr( $k ) . ']" value="' . esc_attr( $val ) . '" class="regular-text">';
		}
		echo '</td></tr>';
	}
	echo '</table><p class="description">報酬率は代理店には「あなたの報酬率」として表示されます。ほかの代理店の率は見えません。</p>';
}

add_action( 'save_post_apprex_agency', function ( $post_id ) {
	if ( ! isset( $_POST['apprex_agency_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_agency_nonce'] ) ), 'apprex_agency_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in  = isset( $_POST['apprex_ag'] ) ? (array) wp_unslash( $_POST['apprex_ag'] ) : array();
	$old = apprex_agency_get( $post_id );
	$v   = $old;
	foreach ( apprex_agency_fields() as $k => $f ) {
		$raw = isset( $in[ $k ] ) ? $in[ $k ] : '';
		switch ( $f[1] ) {
			case 'type':
				$v[ $k ] = isset( apprex_agency_types()[ $raw ] ) ? $raw : 'introducer';
				break;
			case 'status':
				$v[ $k ] = isset( apprex_agency_statuses()[ $raw ] ) ? $raw : 'pending';
				break;
			case 'rate':
				$v[ $k ] = max( 0, min( 100, round( (float) $raw, 1 ) ) );
				break;
			case 'int':
				$v[ $k ] = max( 0, (int) $raw );
				break;
			case 'email':
				$v[ $k ] = sanitize_email( $raw );
				break;
			case 'textarea':
				$v[ $k ] = sanitize_textarea_field( $raw );
				break;
			case 'code':
				$raw     = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $raw );
				$v[ $k ] = $raw;
				break;
			default:
				$v[ $k ] = sanitize_text_field( $raw );
		}
	}
	// 代理店コード：空なら作る。ほかの代理店と同じなら作り直す。
	$code = $v['code'];
	if ( strlen( $code ) < 2 ) {
		$code = 'ag' . strtolower( wp_generate_password( 6, false, false ) );
	}
	$other = apprex_agency_by_code( $code );
	while ( $other && $other !== (int) $post_id ) {
		$code  = 'ag' . strtolower( wp_generate_password( 6, false, false ) );
		$other = apprex_agency_by_code( $code );
	}
	$v['code'] = $code;
	update_post_meta( $post_id, 'apprex_ag', $v );
	update_post_meta( $post_id, 'apprex_ag_code', $code );
	// 有効になったら代理店にお知らせ。
	if ( 'active' === $v['status'] && 'active' !== $old['status'] && is_email( $v['email'] ) ) {
		apprex_agency_mail( $v['email'], '【APPREX】代理店の登録が完了しました', ( $v['contact'] ? $v['contact'] : $v['company'] ) . " 様\n\nAPPREXの代理店としての登録が完了しました。\n\nあなたの紹介URL：\n" . add_query_arg( 'ref', $code, home_url( '/' ) ) . "\n\nこのURLから届いたお問い合わせ・会員登録・ご契約は、あなたのご紹介として記録されます。ご紹介の状況と報酬は、代理店専用ページで確認できます。\n" . apprex_agency_portal_url() . "\n" );
	}
}, 10 );

/** 代理店専用ページのアカウント。 */
function apprex_agency_acct_box( $post ) {
	$v   = apprex_agency_get( $post->ID );
	$uid = (int) get_post_meta( $post->ID, 'apprex_ag_user', true );
	$u   = $uid ? get_user_by( 'id', $uid ) : null;
	if ( $u ) {
		echo '<p>アカウント：<b>' . esc_html( $u->user_email ) . '</b></p>';
		echo '<p><a class="button" href="' . esc_url( add_query_arg( 'as', $post->ID, apprex_agency_portal_url() ) ) . '" target="_blank" rel="noopener">代理店の画面を見る</a></p>';
	} else {
		echo '<p>まだアカウントがありません。メールを入れて保存してから発行してください。</p>';
	}
	if ( ! $u && is_email( $v['email'] ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_agency_account&id=' . $post->ID ), 'apprex_agency_account_' . $post->ID );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">アカウントを発行して案内メールを送る</a></p>';
	}
	echo '<p class="description">代理店はこのアカウントで <code>/agency/</code> にログインし、紹介の状況と報酬を確認します。</p>';
}

/* アカウント発行。 */
add_action( 'admin_post_apprex_agency_account', function () {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_agency_account_' . $id );
	$v     = apprex_agency_get( $id );
	$email = $v['email'];
	if ( ! is_email( $email ) ) {
		wp_die( 'メールアドレスを入れて保存してください。' );
	}
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		if ( user_can( $user, 'edit_posts' ) ) {
			wp_die( 'このメールアドレスは管理者・編集者のアカウントです。別のメールアドレスを使ってください。' );
		}
		$user->add_role( 'apprex_agency' );
		$uid = $user->ID;
	} else {
		$login = sanitize_user( strstr( $email, '@', true ), true );
		$base  = $login ? $login : 'agency';
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i++;
		}
		$uid = wp_insert_user( array( 'user_login' => $login, 'user_email' => $email, 'user_pass' => wp_generate_password( 20 ), 'display_name' => $v['contact'] ? $v['contact'] : $v['company'], 'role' => 'apprex_agency' ) );
		if ( is_wp_error( $uid ) ) {
			wp_die( esc_html( $uid->get_error_message() ) );
		}
	}
	update_post_meta( $id, 'apprex_ag_user', $uid );
	$u   = get_user_by( 'id', $uid );
	$key = get_password_reset_key( $u );
	$set = is_wp_error( $key ) ? wp_lostpassword_url( apprex_agency_portal_url() ) : network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $u->user_login ), 'login' );
	apprex_agency_mail( $email, '【APPREX】代理店専用ページのご案内', ( $v['contact'] ? $v['contact'] : $v['company'] ) . " 様\n\nAPPREXの代理店専用ページのアカウントをご用意しました。下のURLからパスワードを設定してください。\n{$set}\n\nログインID：{$u->user_login}\n代理店専用ページ：" . apprex_agency_portal_url() . "\n\n紹介URL・ご紹介の状況・報酬の明細・営業資料を確認できます。\n" );
	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit&apprex_acct=1' ) );
	exit;
} );

/* お問い合わせ（代理店の申し込み）から代理店を登録。 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_inquiry_agency', '代理店として登録', function ( $post ) {
		if ( 'partner' !== get_post_meta( $post->ID, 'apprex_type', true ) ) {
			echo '<p class="description">代理店の申し込みではありません。</p>';
			return;
		}
		$done = (int) get_post_meta( $post->ID, 'apprex_ag_created', true );
		if ( $done ) {
			echo '<p>登録済みです。<a href="' . esc_url( admin_url( 'post.php?post=' . $done . '&action=edit' ) ) . '">代理店の情報を開く</a></p>';
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_agency_from_inquiry&id=' . $post->ID ), 'apprex_agency_from_' . $post->ID );
		echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">この内容で代理店を登録する</a></p><p class="description">「審査中」で登録します。報酬率を決めて「有効」にすると、代理店に登録完了のメールが届きます。</p>';
	}, 'apprex_inquiry', 'side', 'high' );
} );

add_action( 'admin_post_apprex_agency_from_inquiry', function () {
	$iid = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $iid || ! current_user_can( 'edit_posts' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_agency_from_' . $iid );
	$msg  = (string) get_post_meta( $iid, 'apprex_message', true );
	$type = 'introducer';
	if ( false !== strpos( $msg, '販売代理店' ) ) {
		$type = 'reseller';
	} elseif ( false !== strpos( $msg, 'OEM' ) ) {
		$type = 'oem';
	}
	$company = (string) get_post_meta( $iid, 'apprex_company', true );
	$name    = (string) get_post_meta( $iid, 'apprex_name', true );
	$id      = wp_insert_post( array( 'post_type' => 'apprex_agency', 'post_status' => 'publish', 'post_title' => $company ? $company : $name ) );
	if ( ! $id || is_wp_error( $id ) ) {
		wp_die( '登録できませんでした。' );
	}
	$code = 'ag' . strtolower( wp_generate_password( 6, false, false ) );
	update_post_meta( $id, 'apprex_ag', array( 'company' => $company, 'contact' => $name, 'email' => (string) get_post_meta( $iid, 'apprex_email', true ), 'phone' => (string) get_post_meta( $iid, 'apprex_phone', true ), 'type' => $type, 'status' => 'pending', 'code' => $code, 'rate_initial' => 0, 'rate_monthly' => 0, 'months' => 0, 'bank' => '', 'note' => '申し込み内容：' . $msg ) );
	update_post_meta( $id, 'apprex_ag_code', $code );
	update_post_meta( $iid, 'apprex_ag_created', $id );
	wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * 紹介の記録（契約・発注への紐づけ）
 * ---------------------------------------------------------------------- */

/** メールアドレスから、紹介した代理店コードを探す（最初に紹介した代理店を優先）。 */
function apprex_agency_ref_for_email( $email ) {
	if ( ! is_email( $email ) ) {
		return '';
	}
	foreach ( array( array( 'apprex_inquiry', 'apprex_email' ), array( 'apprex_order', 'apprex_customer_email' ) ) as $q ) {
		$ids = get_posts( array( 'post_type' => $q[0], 'post_status' => apprex_agency_statuses_any(), 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'ASC', 'meta_query' => array( array( 'key' => $q[1], 'value' => $email ), array( 'key' => 'apprex_ref', 'compare' => 'EXISTS' ) ) ) );
		if ( $ids ) {
			return (string) get_post_meta( $ids[0], 'apprex_ref', true );
		}
	}
	$u = get_user_by( 'email', $email );
	return $u ? (string) get_user_meta( $u->ID, 'apprex_ref', true ) : '';
}

/** 契約の編集画面：紹介した代理店。 */
function apprex_contract_agency_box( $post ) {
	$ref = (string) get_post_meta( $post->ID, 'apprex_ref', true );
	$ags = get_posts( array( 'post_type' => 'apprex_agency', 'post_status' => 'any', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
	wp_nonce_field( 'apprex_contract_agency', 'apprex_contract_agency_nonce' );
	echo '<select name="apprex_contract_ref" style="width:100%"><option value="">（なし）</option>';
	foreach ( $ags as $a ) {
		$v = apprex_agency_get( $a->ID );
		echo '<option value="' . esc_attr( $v['code'] ) . '"' . selected( $ref, $v['code'], false ) . '>' . esc_html( $a->post_title . '（' . $v['code'] . '）' ) . '</option>';
	}
	echo '</select><p class="description">空のまま保存すると、お客様のメールアドレスから自動で判定します。</p>';
}

add_action( 'save_post_apprex_contract', function ( $post_id ) {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$manual = '';
	if ( isset( $_POST['apprex_contract_agency_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_contract_agency_nonce'] ) ), 'apprex_contract_agency' ) ) {
		$manual = isset( $_POST['apprex_contract_ref'] ) ? preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_POST['apprex_contract_ref'] ) ) : '';
	}
	$ref = '' !== $manual ? $manual : apprex_agency_ref_for_email( (string) get_post_meta( $post_id, 'apprex_c_email', true ) );
	if ( '' !== $ref ) {
		update_post_meta( $post_id, 'apprex_ref', $ref );
	}
	// 解約した日を記録（月額の報酬はその月まで）。
	$st = get_post_meta( $post_id, 'apprex_c_status', true );
	if ( 'cancelled' === $st && ! get_post_meta( $post_id, 'apprex_c_cancelled_at', true ) ) {
		update_post_meta( $post_id, 'apprex_c_cancelled_at', current_time( 'Y-m-d' ) );
	} elseif ( 'cancelled' !== $st ) {
		delete_post_meta( $post_id, 'apprex_c_cancelled_at' );
	}
}, 30 );

/* 紹介URLから届いたお問い合わせ・会員登録を、代理店にもお知らせ（お客様の連絡先は送らない）。 */
add_action( 'apprex_inquiry_submitted', function ( $post_id, $type ) {
	$ref = (string) get_post_meta( $post_id, 'apprex_ref', true );
	$aid = apprex_agency_by_code( $ref );
	if ( ! $aid || in_array( $type, array( 'partner', 'agency_lead' ), true ) ) {
		return;
	}
	$v = apprex_agency_get( $aid );
	if ( 'active' !== $v['status'] || ! is_email( $v['email'] ) ) {
		return;
	}
	$label = function_exists( 'apprex_type_label' ) ? apprex_type_label( $type ) : $type;
	apprex_agency_mail( $v['email'], '【APPREX】ご紹介のお客様から「' . $label . '」が届きました', ( $v['contact'] ? $v['contact'] : $v['company'] ) . " 様\n\nあなたの紹介URLから「{$label}」が届きました。担当者がご対応します。\n\nご紹介の状況は、代理店専用ページで確認できます。\n" . apprex_agency_portal_url() . "\n" );
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 紹介の状況・報酬の計算
 * ---------------------------------------------------------------------- */

/** 名前を一部だけ見せる（例：山田太郎 → 山○○○）。 */
function apprex_agency_mask( $s ) {
	$s = trim( (string) $s );
	if ( '' === $s ) {
		return '';
	}
	$len = mb_strlen( $s );
	return mb_substr( $s, 0, 1 ) . str_repeat( '○', min( 3, max( 1, $len - 1 ) ) );
}

/**
 * 代理店が紹介したお客様（メールアドレスごとに、いちばん進んだ段階）。
 *
 * @param string $code 代理店コード。
 * @return array[] { key, label, company, name, first, stage, stage_label, contract }
 */
function apprex_agency_referrals( $code ) {
	$rank   = array( 'contact' => 1, 'document' => 2, 'agency_lead' => 2, 'signup' => 3, 'trial' => 4, 'studio' => 5, 'estimate' => 6, 'meeting' => 7, 'contract' => 8, 'cancelled' => 9 );
	$labels = array( 'contact' => 'お問い合わせ', 'document' => '資料請求', 'agency_lead' => '代理店が登録', 'signup' => 'AIスタジオ登録', 'trial' => '無料体験', 'studio' => '開発の相談', 'estimate' => '見積り・発注', 'meeting' => 'ミーティング予約', 'contract' => '契約中', 'cancelled' => '解約' );
	$out    = array();
	$add    = function ( $email, $company, $name, $date, $stage, $contract = 0 ) use ( &$out, $rank, $labels ) {
		$key = strtolower( $email ? $email : $company . $name );
		if ( '' === $key ) {
			return;
		}
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array( 'company' => $company, 'name' => $name, 'first' => $date, 'stage' => $stage, 'contract' => $contract );
		}
		if ( $date && $date < $out[ $key ]['first'] ) {
			$out[ $key ]['first'] = $date;
		}
		if ( ! $out[ $key ]['company'] && $company ) {
			$out[ $key ]['company'] = $company;
		}
		if ( $rank[ $stage ] >= $rank[ $out[ $key ]['stage'] ] ) {
			$out[ $key ]['stage'] = $stage;
			if ( $contract ) {
				$out[ $key ]['contract'] = $contract;
			}
		}
	};
	foreach ( get_posts( array( 'post_type' => 'apprex_inquiry', 'post_status' => apprex_agency_statuses_any(), 'numberposts' => 500, 'meta_key' => 'apprex_ref', 'meta_value' => $code ) ) as $p ) {
		$t = (string) get_post_meta( $p->ID, 'apprex_type', true );
		$add( (string) get_post_meta( $p->ID, 'apprex_email', true ), (string) get_post_meta( $p->ID, 'apprex_company', true ), (string) get_post_meta( $p->ID, 'apprex_name', true ), get_the_date( 'Y-m-d', $p ), isset( $rank[ $t ] ) ? $t : 'contact' );
	}
	foreach ( get_posts( array( 'post_type' => 'apprex_order', 'post_status' => apprex_agency_statuses_any(), 'numberposts' => 500, 'meta_key' => 'apprex_ref', 'meta_value' => $code ) ) as $p ) {
		$add( (string) get_post_meta( $p->ID, 'apprex_customer_email', true ), (string) get_post_meta( $p->ID, 'apprex_customer_company', true ), (string) get_post_meta( $p->ID, 'apprex_customer_name', true ), get_the_date( 'Y-m-d', $p ), 'estimate' );
	}
	foreach ( apprex_agency_contracts( $code ) as $c ) {
		$st = 'cancelled' === get_post_meta( $c->ID, 'apprex_c_status', true ) ? 'cancelled' : 'contract';
		$add( (string) get_post_meta( $c->ID, 'apprex_c_email', true ), (string) get_post_meta( $c->ID, 'apprex_c_company', true ), (string) get_post_meta( $c->ID, 'apprex_c_name', true ), (string) get_post_meta( $c->ID, 'apprex_c_start', true ), $st, $c->ID );
	}
	foreach ( $out as $k => $r ) {
		$out[ $k ]['stage_label'] = $labels[ $r['stage'] ];
		$out[ $k ]['label']       = $r['company'] ? $r['company'] : apprex_agency_mask( $r['name'] ) . ' 様';
	}
	uasort( $out, function ( $a, $b ) {
		return strcmp( $b['first'], $a['first'] );
	} );
	return array_values( $out );
}

/** 代理店コードがついた契約。 */
function apprex_agency_contracts( $code ) {
	if ( ! post_type_exists( 'apprex_contract' ) || '' === (string) $code ) {
		return array();
	}
	return get_posts( array( 'post_type' => 'apprex_contract', 'post_status' => 'any', 'numberposts' => 500, 'meta_key' => 'apprex_ref', 'meta_value' => $code ) );
}

/**
 * 代理店の、ある月の報酬の内訳。
 *
 * @param int    $agency_id 代理店ID。
 * @param string $ym        'Y-m'。
 * @return array { rows: [ {contract, label, kind, base, rate, amount} ], total }
 */
function apprex_agency_commission( $agency_id, $ym ) {
	$v     = apprex_agency_get( $agency_id );
	$rows  = array();
	$total = 0;
	$m0    = strtotime( $ym . '-01' );
	$m1    = strtotime( '+1 month', $m0 ) - 1;
	foreach ( apprex_agency_contracts( $v['code'] ) as $c ) {
		$start = (string) get_post_meta( $c->ID, 'apprex_c_start', true );
		if ( ! $start ) {
			continue;
		}
		$st = strtotime( $start );
		if ( $st > $m1 ) {
			continue;
		}
		$label = (string) get_post_meta( $c->ID, 'apprex_c_company', true );
		$label = $label ? $label : apprex_agency_mask( (string) get_post_meta( $c->ID, 'apprex_c_name', true ) ) . ' 様';
		// 初期費用（契約開始の月）。
		if ( wp_date( 'Y-m', $st ) === $ym && $v['rate_initial'] > 0 ) {
			$base = (int) get_post_meta( $c->ID, 'apprex_c_initial', true ) + (int) get_post_meta( $c->ID, 'apprex_c_production', true );
			if ( $base > 0 ) {
				$amt    = (int) floor( $base * $v['rate_initial'] / 100 );
				$rows[] = array( 'contract' => $c->ID, 'label' => $label, 'kind' => '初期費用', 'base' => $base, 'rate' => $v['rate_initial'], 'amount' => $amt );
				$total += $amt;
			}
		}
		// 月額（契約開始の月から。解約した月まで。月数の上限あり）。
		if ( $v['rate_monthly'] > 0 ) {
			$cancel = (string) get_post_meta( $c->ID, 'apprex_c_cancelled_at', true );
			if ( 'cancelled' === get_post_meta( $c->ID, 'apprex_c_status', true ) && ! $cancel ) {
				continue;
			}
			if ( $cancel && strtotime( $cancel ) < $m0 ) {
				continue;
			}
			$n = ( (int) wp_date( 'Y', $m0 ) - (int) wp_date( 'Y', $st ) ) * 12 + ( (int) wp_date( 'n', $m0 ) - (int) wp_date( 'n', $st ) ) + 1;
			if ( $v['months'] > 0 && $n > $v['months'] ) {
				continue;
			}
			$base = (int) get_post_meta( $c->ID, 'apprex_c_monthly', true );
			if ( $base > 0 ) {
				$amt    = (int) floor( $base * $v['rate_monthly'] / 100 );
				$rows[] = array( 'contract' => $c->ID, 'label' => $label, 'kind' => '月額（' . $n . 'か月目）', 'base' => $base, 'rate' => $v['rate_monthly'], 'amount' => $amt );
				$total += $amt;
			}
		}
	}
	return array( 'rows' => $rows, 'total' => $total );
}

/** 支払い済みの記録（代理店ID => [ 'Y-m' => 支払日 ]）。 */
function apprex_agency_paid( $agency_id ) {
	$p = get_post_meta( $agency_id, 'apprex_ag_paid', true );
	return is_array( $p ) ? $p : array();
}

/** 代理店へのメール。 */
function apprex_agency_mail( $to, $subject, $body ) {
	$html = function_exists( 'apprex_render_email' ) ? apprex_render_email( $subject, $body, array( 'heading' => $subject ) ) : nl2br( esc_html( $body ) );
	wp_mail( $to, $subject, $html, function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/** 紹介と報酬（代理店の編集画面）。 */
function apprex_agency_sum_box( $post ) {
	$v = apprex_agency_get( $post->ID );
	if ( ! $v['code'] ) {
		echo '<p>保存すると表示されます。</p>';
		return;
	}
	$refs = apprex_agency_referrals( $v['code'] );
	$con  = count( array_filter( $refs, function ( $r ) {
		return 'contract' === $r['stage'];
	} ) );
	$now  = current_time( 'Y-m' );
	$cm   = apprex_agency_commission( $post->ID, $now );
	echo '<p>紹介したお客様：<b>' . count( $refs ) . '件</b>（契約中 ' . (int) $con . '件）／ 今月の報酬（見込み）：<b>' . esc_html( number_format( $cm['total'] ) ) . '円</b>　<a href="' . esc_url( admin_url( 'edit.php?post_type=apprex_agency&page=apprex-agency-pay&ym=' . $now ) ) . '">報酬・支払いの画面へ</a></p>';
	if ( $refs ) {
		echo '<table class="widefat striped"><thead><tr><th>お客様</th><th>最初の日</th><th>今の段階</th></tr></thead><tbody>';
		foreach ( array_slice( $refs, 0, 30 ) as $r ) {
			echo '<tr><td>' . esc_html( $r['company'] ? $r['company'] : $r['name'] ) . '</td><td>' . esc_html( $r['first'] ) . '</td><td>' . esc_html( $r['stage_label'] ) . ( $r['contract'] ? ' <a href="' . esc_url( admin_url( 'post.php?post=' . $r['contract'] . '&action=edit' ) ) . '">契約</a>' : '' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}

/* 一覧の列。 */
add_filter( 'manage_apprex_agency_posts_columns', function ( $cols ) {
	return array( 'cb' => $cols['cb'], 'title' => '代理店', 'ag_type' => '種類', 'ag_status' => '状態', 'ag_code' => 'コード', 'ag_refs' => '紹介／契約', 'ag_month' => '今月の報酬' );
} );
add_action( 'manage_apprex_agency_posts_custom_column', function ( $col, $id ) {
	$v = apprex_agency_get( $id );
	switch ( $col ) {
		case 'ag_type':
			echo esc_html( apprex_agency_types()[ $v['type'] ] );
			break;
		case 'ag_status':
			echo esc_html( apprex_agency_statuses()[ $v['status'] ] );
			break;
		case 'ag_code':
			echo '<code>' . esc_html( $v['code'] ) . '</code>';
			break;
		case 'ag_refs':
			$refs = $v['code'] ? apprex_agency_referrals( $v['code'] ) : array();
			echo count( $refs ) . '／' . count( array_filter( $refs, function ( $r ) {
				return 'contract' === $r['stage'];
			} ) );
			break;
		case 'ag_month':
			echo esc_html( number_format( apprex_agency_commission( $id, current_time( 'Y-m' ) )['total'] ) ) . '円';
			break;
	}
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 本部：報酬・支払い／代理店の設定
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=apprex_agency', '報酬・支払い', '報酬・支払い', 'edit_posts', 'apprex-agency-pay', 'apprex_agency_pay_page' );
	add_submenu_page( 'edit.php?post_type=apprex_agency', '代理店ページの設定', '代理店ページの設定', 'manage_options', 'apprex-agency-settings', 'apprex_agency_settings_page' );
} );

/** 報酬・支払い。 */
function apprex_agency_pay_page() {
	$ym  = isset( $_GET['ym'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['ym'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['ym'] ) ) : current_time( 'Y-m' );
	$ags = get_posts( array( 'post_type' => 'apprex_agency', 'post_status' => 'any', 'numberposts' => 500, 'orderby' => 'title', 'order' => 'ASC' ) );
	$pay = wp_date( 'Y年n月j日', strtotime( 'last day of +1 month', strtotime( $ym . '-01' ) ) );
	echo '<div class="wrap"><h1>報酬・支払い</h1>';
	if ( isset( $_GET['apprex_paid'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>支払いの記録を更新しました。</p></div>';
	}
	echo '<form method="get" style="margin:12px 0"><input type="hidden" name="post_type" value="apprex_agency"><input type="hidden" name="page" value="apprex-agency-pay"><label>対象の月 <input type="month" name="ym" value="' . esc_attr( $ym ) . '"></label> <button class="button">表示</button> <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=apprex_agency_csv&ym=' . $ym ), 'apprex_agency_csv' ) ) . '">CSVをダウンロード</a></form>';
	echo '<p>' . esc_html( wp_date( 'Y年n月', strtotime( $ym . '-01' ) ) ) . '分（月末締め）の報酬です。お支払い予定日：<b>' . esc_html( $pay ) . '</b>（翌月末払い）</p>';
	echo '<table class="widefat striped"><thead><tr><th>代理店</th><th>種類</th><th>件数</th><th style="text-align:right">報酬（税抜）</th><th>支払い</th><th>内訳</th></tr></thead><tbody>';
	$sum = 0;
	foreach ( $ags as $a ) {
		$v  = apprex_agency_get( $a->ID );
		$cm = apprex_agency_commission( $a->ID, $ym );
		if ( ! $cm['rows'] && 'active' !== $v['status'] ) {
			continue;
		}
		$sum += $cm['total'];
		$paid = apprex_agency_paid( $a->ID );
		$done = isset( $paid[ $ym ] ) ? $paid[ $ym ] : '';
		$url  = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_agency_pay&id=' . $a->ID . '&ym=' . $ym . '&on=' . ( $done ? 0 : 1 ) ), 'apprex_agency_pay_' . $a->ID );
		echo '<tr><td><a href="' . esc_url( get_edit_post_link( $a->ID ) ) . '">' . esc_html( $a->post_title ) . '</a></td><td>' . esc_html( apprex_agency_types()[ $v['type'] ] ) . '</td><td>' . count( $cm['rows'] ) . '</td><td style="text-align:right"><b>' . esc_html( number_format( $cm['total'] ) ) . '円</b></td>';
		echo '<td>' . ( $done ? '支払い済み（' . esc_html( $done ) . '）<br><a href="' . esc_url( $url ) . '">取り消す</a>' : ( $cm['total'] > 0 ? '<a class="button button-small" href="' . esc_url( $url ) . '">支払い済みにする</a>' : '—' ) ) . '</td><td>';
		if ( $cm['rows'] ) {
			echo '<details><summary>' . count( $cm['rows'] ) . '件</summary><ul style="margin:6px 0">';
			foreach ( $cm['rows'] as $r ) {
				echo '<li>' . esc_html( $r['label'] . '：' . $r['kind'] . ' ' . number_format( $r['base'] ) . '円 × ' . $r['rate'] . '% = ' . number_format( $r['amount'] ) . '円' ) . '</li>';
			}
			echo '</ul></details>';
		}
		echo '</td></tr>';
	}
	echo '</tbody><tfoot><tr><th colspan="3">合計</th><th style="text-align:right">' . esc_html( number_format( $sum ) ) . '円</th><th colspan="2"></th></tr></tfoot></table>';
	echo '<p class="description">報酬は「契約」の 初期費用・制作費（契約開始の月）と 月額（毎月）に、代理店ごとの報酬率をかけて計算します。契約の右側「紹介した代理店」で、紹介元を確認・変更できます。</p></div>';
}

add_action( 'admin_post_apprex_agency_pay', function () {
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$ym = isset( $_GET['ym'] ) ? sanitize_text_field( wp_unslash( $_GET['ym'] ) ) : '';
	if ( ! $id || ! current_user_can( 'edit_posts' ) || ! preg_match( '/^\d{4}-\d{2}$/', $ym ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_agency_pay_' . $id );
	$paid = apprex_agency_paid( $id );
	if ( ! empty( $_GET['on'] ) ) {
		$paid[ $ym ] = current_time( 'Y-m-d' );
	} else {
		unset( $paid[ $ym ] );
	}
	update_post_meta( $id, 'apprex_ag_paid', $paid );
	wp_safe_redirect( admin_url( 'edit.php?post_type=apprex_agency&page=apprex-agency-pay&ym=' . $ym . '&apprex_paid=1' ) );
	exit;
} );

add_action( 'admin_post_apprex_agency_csv', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_agency_csv' );
	$ym = isset( $_GET['ym'] ) && preg_match( '/^\d{4}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['ym'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['ym'] ) ) : current_time( 'Y-m' );
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="agency-commission-' . $ym . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( '対象月', '代理店', '代理店コード', 'お客様', '種別', '金額（税抜）', '報酬率(%)', '報酬（税抜）', '支払い' ) );
	foreach ( get_posts( array( 'post_type' => 'apprex_agency', 'post_status' => 'any', 'numberposts' => 500 ) ) as $a ) {
		$v    = apprex_agency_get( $a->ID );
		$paid = apprex_agency_paid( $a->ID );
		foreach ( apprex_agency_commission( $a->ID, $ym )['rows'] as $r ) {
			fputcsv( $out, array( $ym, $a->post_title, $v['code'], $r['label'], $r['kind'], $r['base'], $r['rate'], $r['amount'], isset( $paid[ $ym ] ) ? '支払い済み ' . $paid[ $ym ] : '未払い' ) );
		}
	}
	fclose( $out );
	exit;
} );

/** 代理店ページの設定（お知らせ・資料）。 */
function apprex_agency_settings_page() {
	if ( isset( $_POST['apprex_agency_settings'] ) && check_admin_referer( 'apprex_agency_settings' ) ) {
		update_option( 'apprex_agency_news', sanitize_textarea_field( wp_unslash( isset( $_POST['news'] ) ? $_POST['news'] : '' ) ) );
		update_option( 'apprex_agency_docs', sanitize_textarea_field( wp_unslash( isset( $_POST['docs'] ) ? $_POST['docs'] : '' ) ) );
		echo '<div class="notice notice-success"><p>保存しました。</p></div>';
	}
	echo '<div class="wrap"><h1>代理店ページの設定</h1><form method="post">';
	wp_nonce_field( 'apprex_agency_settings' );
	echo '<input type="hidden" name="apprex_agency_settings" value="1"><table class="form-table" role="presentation">';
	echo '<tr><th>本部からのお知らせ</th><td><textarea name="news" rows="6" class="large-text" placeholder="例：11月から業種別の提案資料を追加しました。">' . esc_textarea( (string) get_option( 'apprex_agency_news', '' ) ) . '</textarea><p class="description">代理店専用ページの上に表示されます。空なら表示しません。</p></td></tr>';
	echo '<tr><th>追加の資料</th><td><textarea name="docs" rows="6" class="large-text" placeholder="資料の名前|https://...">' . esc_textarea( (string) get_option( 'apprex_agency_docs', '' ) ) . '</textarea><p class="description">1行に1つ、「名前|URL」の形で。サービス資料・料金ページ・見積もり・デモアプリは自動で表示されます。</p></td></tr>';
	echo '</table>';
	submit_button( '保存' );
	echo '</form><p>代理店専用ページ：<a href="' . esc_url( apprex_agency_portal_url() ) . '" target="_blank" rel="noopener">' . esc_html( apprex_agency_portal_url() ) . '</a></p></div>';
}

/* -------------------------------------------------------------------------
 * 代理店：お客様の登録（販売代理店・OEM代理店の商談の登録）
 * ---------------------------------------------------------------------- */
add_action( 'admin_post_apprex_agency_lead', 'apprex_agency_lead_submit' );
function apprex_agency_lead_submit() {
	$aid = apprex_agency_for_user();
	if ( ! $aid || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'apprex_agency_lead' ) ) {
		wp_die( '再読み込みしてからお試しください。' );
	}
	$v = apprex_agency_get( $aid );
	if ( 'active' !== $v['status'] ) {
		wp_die( '代理店の登録が有効になってからご利用ください。' );
	}
	$f = array();
	foreach ( array( 'company', 'name', 'email', 'phone', 'memo' ) as $k ) {
		$f[ $k ] = isset( $_POST[ $k ] ) ? ( 'memo' === $k ? sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : '';
	}
	$f['email'] = sanitize_email( $f['email'] );
	if ( '' === $f['company'] && '' === $f['name'] ) {
		wp_safe_redirect( add_query_arg( 'lead', 'empty', apprex_agency_portal_url() ) . '#lead' );
		exit;
	}
	// ほかの代理店がすでに紹介しているお客様か。
	$prev  = $f['email'] ? apprex_agency_ref_for_email( $f['email'] ) : '';
	$clash = $prev && $prev !== $v['code'];
	$pid   = wp_insert_post( array( 'post_type' => 'apprex_inquiry', 'post_status' => 'publish', 'post_title' => '[代理店が登録] ' . ( $f['company'] ? $f['company'] : $f['name'] ) . '（' . $v['company'] . '）' ) );
	if ( $pid && ! is_wp_error( $pid ) ) {
		update_post_meta( $pid, 'apprex_type', 'agency_lead' );
		update_post_meta( $pid, 'apprex_name', $f['name'] );
		update_post_meta( $pid, 'apprex_company', $f['company'] );
		update_post_meta( $pid, 'apprex_email', $f['email'] );
		update_post_meta( $pid, 'apprex_phone', $f['phone'] );
		update_post_meta( $pid, 'apprex_message', "代理店（{$v['company']}／{$v['code']}）からのお客様の登録です。\n" . ( $clash ? "※ このお客様は、別の代理店（{$prev}）の紹介として記録があります。確認してください。\n" : '' ) . $f['memo'] );
		update_post_meta( $pid, 'apprex_submitted_at', current_time( 'mysql' ) );
		if ( ! $clash ) {
			update_post_meta( $pid, 'apprex_ref', $v['code'] );
		}
		$to = function_exists( 'apprex_notify_to' ) ? apprex_notify_to() : get_option( 'admin_email' );
		apprex_agency_mail( $to, '[APPREX] 代理店がお客様を登録しました — ' . $v['company'], "代理店：{$v['company']}（{$v['code']}）\nお客様：{$f['company']} {$f['name']}\nメール：{$f['email']}\n電話：{$f['phone']}\nメモ：{$f['memo']}\n" . ( $clash ? "\n※ 別の代理店（{$prev}）の紹介として記録があるため、紹介元は未設定です。\n" : '' ) . "\n管理画面：" . admin_url( 'post.php?post=' . $pid . '&action=edit' ) . "\n" );
	}
	wp_safe_redirect( add_query_arg( 'lead', $clash ? 'clash' : 'ok', apprex_agency_portal_url() ) . '#lead' );
	exit;
}
