<?php
/**
 * マネーフォワード クラウド請求書 連携（振込請求の発行＋入金消し込み）。
 *
 * 振込（payment_method=invoice）の契約に対し、MFクラウド請求書APIで請求書を発行し、
 * 入金状況をAPIで取得して自動消し込み（apprex_c_last_paid 更新）する。
 *
 * 認証：OAuth2（認可コード＋リフレッシュトークン）。
 * ※ MF API の各URL・スコープは設定で変更可能（環境差異に対応）。
 * ※ 認証情報・部門IDが揃うまで一切動作しない安全設計。設定後に接続テスト→実発行で確認。
 *
 * 設定：設定 → APPREX 請求書(マネフォ)
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 設定アクセサ
 * ====================================================================== */
function apprex_mf_opt( $k, $default = '' ) {
	return (string) get_option( $k, $default );
}
function apprex_mf_enabled() {
	return (bool) get_option( 'apprex_mf_enabled', 0 );
}
function apprex_mf_authorize_url() {
	return apprex_mf_opt( 'apprex_mf_authorize_url', 'https://api.biz.moneyforward.com/authorize' );
}
function apprex_mf_token_url() {
	return apprex_mf_opt( 'apprex_mf_token_url', 'https://api.biz.moneyforward.com/token' );
}
function apprex_mf_api_base() {
	return rtrim( apprex_mf_opt( 'apprex_mf_api_base', 'https://invoice.moneyforward.com/api/v3' ), '/' );
}
function apprex_mf_scope() {
	return apprex_mf_opt( 'apprex_mf_scope', 'mfc/invoice/data.read mfc/invoice/data.write' );
}
function apprex_mf_redirect_uri() {
	return admin_url( 'admin-post.php?action=apprex_mf_oauth_cb' );
}
/** 連携準備が整っているか（トークン保有）。 */
function apprex_mf_ready() {
	return apprex_mf_enabled() && '' !== apprex_mf_opt( 'apprex_mf_refresh_token' ) && '' !== apprex_mf_opt( 'apprex_mf_department_id' );
}

/* =========================================================================
 * OAuth2
 * ====================================================================== */

/** 認可開始（MFの同意画面へ）。 */
add_action( 'admin_post_apprex_mf_connect', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_mf_connect' );
	$state = wp_generate_password( 24, false );
	set_transient( 'apprex_mf_state', $state, 600 );
	$url = add_query_arg(
		array(
			'response_type' => 'code',
			'client_id'     => apprex_mf_opt( 'apprex_mf_client_id' ),
			'redirect_uri'  => rawurlencode( apprex_mf_redirect_uri() ),
			'scope'         => rawurlencode( apprex_mf_scope() ),
			'state'         => $state,
		),
		apprex_mf_authorize_url()
	);
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
} );

/** 認可コールバック → トークン取得。 */
add_action( 'admin_post_apprex_mf_oauth_cb', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	$back = admin_url( 'options-general.php?page=apprex-mf' );
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	if ( ! $code || $state !== get_transient( 'apprex_mf_state' ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_mf', 'state', $back ) );
		exit;
	}
	$res = wp_remote_post(
		apprex_mf_token_url(),
		array(
			'timeout' => 25,
			'body'    => array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'client_id'     => apprex_mf_opt( 'apprex_mf_client_id' ),
				'client_secret' => apprex_mf_opt( 'apprex_mf_client_secret' ),
				'redirect_uri'  => apprex_mf_redirect_uri(),
			),
		)
	);
	$body = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['access_token'] ) ) {
		$msg = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['error'] ) ? $body['error'] : 'トークン取得に失敗' );
		wp_safe_redirect( add_query_arg( 'apprex_mf_err', rawurlencode( $msg ), $back ) );
		exit;
	}
	apprex_mf_store_tokens( $body );
	wp_safe_redirect( add_query_arg( 'apprex_mf', 'connected', $back ) );
	exit;
} );

function apprex_mf_store_tokens( $body ) {
	update_option( 'apprex_mf_access_token', $body['access_token'], false );
	if ( ! empty( $body['refresh_token'] ) ) {
		update_option( 'apprex_mf_refresh_token', $body['refresh_token'], false );
	}
	$expires = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
	update_option( 'apprex_mf_token_expire', time() + $expires - 60, false );
}

/** 有効なアクセストークンを返す（必要ならリフレッシュ）。 */
function apprex_mf_access_token() {
	$tok = apprex_mf_opt( 'apprex_mf_access_token' );
	$exp = (int) get_option( 'apprex_mf_token_expire', 0 );
	if ( $tok && time() < $exp ) {
		return $tok;
	}
	$refresh = apprex_mf_opt( 'apprex_mf_refresh_token' );
	if ( '' === $refresh ) {
		return new WP_Error( 'mf', '未連携です（OAuth連携してください）。' );
	}
	$res = wp_remote_post(
		apprex_mf_token_url(),
		array(
			'timeout' => 25,
			'body'    => array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $refresh,
				'client_id'     => apprex_mf_opt( 'apprex_mf_client_id' ),
				'client_secret' => apprex_mf_opt( 'apprex_mf_client_secret' ),
			),
		)
	);
	$body = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['access_token'] ) ) {
		return new WP_Error( 'mf', 'トークン更新に失敗（再連携が必要かもしれません）。' );
	}
	apprex_mf_store_tokens( $body );
	return $body['access_token'];
}

/** MF APIリクエスト（Bearer・401で1度だけ再取得）。 */
function apprex_mf_request( $method, $path, $body = null, $retry = true ) {
	$token = apprex_mf_access_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 25,
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$res = wp_remote_request( apprex_mf_api_base() . $path, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	if ( 401 === $code && $retry ) {
		delete_option( 'apprex_mf_token_expire' ); // 強制リフレッシュ。
		return apprex_mf_request( $method, $path, $body, false );
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return array(
		'ok'   => ( $code >= 200 && $code < 300 ),
		'code' => $code,
		'data' => $data,
	);
}

function apprex_mf_err( $r ) {
	if ( is_wp_error( $r ) ) {
		return $r->get_error_message();
	}
	if ( ! empty( $r['data']['errors'][0]['message'] ) ) {
		return $r['data']['errors'][0]['message'];
	}
	if ( ! empty( $r['data']['message'] ) ) {
		return $r['data']['message'];
	}
	return 'MFエラー（HTTP ' . ( isset( $r['code'] ) ? $r['code'] : '?' ) . '）';
}

/* =========================================================================
 * 取引先（partner）＋請求書（billing）発行
 * ====================================================================== */

/** 契約に対応するMF取引先IDを取得（無ければ作成）。 */
function apprex_mf_ensure_partner( $id ) {
	$pid = get_post_meta( $id, 'apprex_c_mf_partner_id', true );
	if ( $pid ) {
		return $pid;
	}
	$name  = (string) get_post_meta( $id, 'apprex_c_company', true );
	$name  = '' !== $name ? $name : (string) get_post_meta( $id, 'apprex_c_name', true );
	$email = (string) get_post_meta( $id, 'apprex_c_email', true );
	$r = apprex_mf_request(
		'POST',
		'/partners',
		array(
			'name'  => $name,
			'email' => $email,
		)
	);
	if ( is_wp_error( $r ) || empty( $r['ok'] ) || empty( $r['data']['id'] ) ) {
		return new WP_Error( 'mf', '取引先の作成に失敗：' . apprex_mf_err( $r ) );
	}
	update_post_meta( $id, 'apprex_c_mf_partner_id', $r['data']['id'] );
	return $r['data']['id'];
}

/** 支払期日（今月の指定日。過ぎていれば翌月）。 */
function apprex_mf_due_date( $day ) {
	$day  = $day ? min( 28, max( 1, (int) $day ) ) : 27;
	$ts   = current_time( 'timestamp' );
	$cand = strtotime( wp_date( 'Y-m-', $ts ) . sprintf( '%02d', $day ) );
	if ( $cand < $ts ) {
		$cand = strtotime( '+1 month', $cand );
	}
	return wp_date( 'Y-m-d', $cand );
}

/** 1契約の請求書をMFで発行。成功でMF請求書データ。 */
function apprex_mf_issue_invoice( $id ) {
	if ( ! apprex_mf_ready() ) {
		return new WP_Error( 'mf', 'マネーフォワード連携が未設定です。' );
	}
	$monthly = (int) get_post_meta( $id, 'apprex_c_monthly', true );
	if ( $monthly <= 0 ) {
		return new WP_Error( 'mf', '月額が0円です。' );
	}
	$partner = apprex_mf_ensure_partner( $id );
	if ( is_wp_error( $partner ) ) {
		return $partner;
	}
	$day  = (int) get_post_meta( $id, 'apprex_c_payment_day', true );
	$plan = trim( get_post_meta( $id, 'apprex_c_service', true ) . ' ' . get_post_meta( $id, 'apprex_c_plan', true ) );
	$body = array(
		'department_id' => apprex_mf_opt( 'apprex_mf_department_id' ),
		'partner_id'    => $partner,
		'billing_date'  => wp_date( 'Y-m-d' ),
		'due_date'      => apprex_mf_due_date( $day ),
		'title'         => 'APPREX ご利用料金（' . wp_date( 'Y年n月' ) . '）',
		'items'         => array(
			array(
				'name'      => ( $plan ? $plan : 'APPREX' ) . ' 月額利用料',
				'quantity'  => 1,
				'unit_price' => $monthly,
				'excise'    => 'ten_percent', // 税区分（必要に応じて設定で変更可）。
			),
		),
	);
	$body = apply_filters( 'apprex_mf_billing_body', $body, $id );

	$r = apprex_mf_request( 'POST', '/billings', $body );
	if ( is_wp_error( $r ) || empty( $r['ok'] ) || empty( $r['data']['id'] ) ) {
		return new WP_Error( 'mf', '請求書の発行に失敗：' . apprex_mf_err( $r ) );
	}
	$iv = $r['data'];
	update_post_meta( $id, 'apprex_c_mf_billing_id', $iv['id'] );
	update_post_meta( $id, 'apprex_c_mf_billing_month', current_time( 'Y-m' ) );
	if ( ! empty( $iv['pdf_url'] ) ) {
		update_post_meta( $id, 'apprex_c_mf_billing_pdf', esc_url_raw( $iv['pdf_url'] ) );
	}
	$status = isset( $iv['payment_status'] ) ? $iv['payment_status'] : ( isset( $iv['posting_status'] ) ? $iv['posting_status'] : '' );
	update_post_meta( $id, 'apprex_c_mf_billing_status', $status );
	return $iv;
}

/* 毎月の振込フローに割り込み（自動発行）。 */
add_filter( 'apprex_send_payment_via_mf', function ( $handled, $id ) {
	if ( $handled || ! apprex_mf_ready() || ! get_option( 'apprex_mf_auto', 0 ) ) {
		return $handled;
	}
	if ( 'invoice' !== get_post_meta( $id, 'apprex_c_payment_method', true ) ) {
		return false; // 振込契約のみ。
	}
	if ( get_post_meta( $id, 'apprex_c_mf_billing_month', true ) === current_time( 'Y-m' ) ) {
		return true; // 今月発行済み → 通常メール抑止。
	}
	$res = apprex_mf_issue_invoice( $id );
	if ( is_wp_error( $res ) ) {
		if ( function_exists( 'apprex_slack_notify' ) ) {
			apprex_slack_notify( ':x: MF請求書の発行に失敗：' . get_post_meta( $id, 'apprex_c_name', true ) . ' / ' . $res->get_error_message() );
		}
		return false; // 失敗時は通常メールにフォールバック。
	}
	return true;
}, 10, 2 );

/* =========================================================================
 * 入金消し込み（ステータス同期）
 * ====================================================================== */

/** MFの入金ステータス語 → 支払済み判定。 */
function apprex_mf_is_paid( $status ) {
	$status = strtolower( (string) $status );
	return in_array( $status, array( 'paid', 'settled', 'received', 'completed', '入金済み', '消込済み' ), true );
}

/** 1契約の請求書ステータスをMFから取得。支払済みなら自動消し込み。 */
function apprex_mf_refresh_invoice( $id ) {
	$bid = get_post_meta( $id, 'apprex_c_mf_billing_id', true );
	if ( ! $bid || ! apprex_mf_ready() ) {
		return false;
	}
	$r = apprex_mf_request( 'GET', '/billings/' . rawurlencode( $bid ) );
	if ( is_wp_error( $r ) || empty( $r['ok'] ) || empty( $r['data'] ) ) {
		return false;
	}
	$iv     = $r['data'];
	$status = isset( $iv['payment_status'] ) ? $iv['payment_status'] : ( isset( $iv['posting_status'] ) ? $iv['posting_status'] : '' );
	update_post_meta( $id, 'apprex_c_mf_billing_status', $status );
	if ( apprex_mf_is_paid( $status ) ) {
		update_post_meta( $id, 'apprex_c_last_paid', current_time( 'Y-m-d' ) ); // 自動消し込み。
	}
	return $status;
}

/** MFで発行した全契約のステータスを更新。件数を返す。 */
function apprex_mf_refresh_all() {
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_contract',
			'post_status'    => 'any',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_c_mf_billing_id',
		)
	);
	$n = 0;
	foreach ( $ids as $id ) {
		if ( false !== apprex_mf_refresh_invoice( $id ) ) {
			$n++;
		}
	}
	return $n;
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_mf_sync_cron' ) ) {
		wp_schedule_event( time() + 20 * MINUTE_IN_SECONDS, 'daily', 'apprex_mf_sync_cron' );
	}
} );
add_action( 'apprex_mf_sync_cron', function () {
	if ( apprex_mf_ready() ) {
		apprex_mf_refresh_all();
	}
} );

/* =========================================================================
 * 契約編集：MF請求ボックス（振込契約）
 * ====================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_mf_box', 'マネフォ請求書（振込）', 'apprex_mf_box', 'apprex_contract', 'side', 'default' );
} );

function apprex_mf_box( $post ) {
	if ( ! apprex_mf_ready() ) {
		echo '<p>マネーフォワード連携が未設定です。<br><a href="' . esc_url( admin_url( 'options-general.php?page=apprex-mf' ) ) . '">設定する</a></p>';
		return;
	}
	$bid = get_post_meta( $post->ID, 'apprex_c_mf_billing_id', true );
	$mon = get_post_meta( $post->ID, 'apprex_c_mf_billing_month', true );
	$st  = get_post_meta( $post->ID, 'apprex_c_mf_billing_status', true );
	$pdf = get_post_meta( $post->ID, 'apprex_c_mf_billing_pdf', true );
	if ( $bid ) {
		echo '<p>最終発行：<strong>' . esc_html( $mon ) . '</strong>（' . esc_html( apprex_mf_is_paid( $st ) ? '入金済み' : ( $st ? $st : '未入金' ) ) . '）';
		if ( $pdf ) {
			echo '<br><a href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener">請求書PDF</a>';
		}
		echo '</p>';
	} else {
		echo '<p>まだMFで請求書を発行していません。</p>';
	}
	$btn = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_mf_issue&contract=' . $post->ID ), 'apprex_mf_issue_' . $post->ID );
	echo '<a href="' . esc_url( $btn ) . '" class="button button-primary" style="width:100%;text-align:center;">マネフォで請求書を発行</a>';
	echo '<p class="description" style="margin-top:8px;">支払方法が「請求書（振込）」の契約向け。月額・メール・会社名を先に保存してください。</p>';
}

add_action( 'admin_post_apprex_mf_issue', function () {
	$id = isset( $_GET['contract'] ) ? absint( $_GET['contract'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_mf_issue_' . $id );
	$r    = apprex_mf_issue_invoice( $id );
	$back = get_edit_post_link( $id, 'url' );
	$arg  = is_wp_error( $r ) ? array( 'apprex_mf_err' => rawurlencode( $r->get_error_message() ) ) : array( 'apprex_mf_ok' => 1 );
	wp_safe_redirect( add_query_arg( $arg, $back ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( isset( $_GET['apprex_mf_ok'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>マネーフォワードで請求書を発行しました。</p></div>';
	} elseif ( isset( $_GET['apprex_mf_err'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>MF請求エラー：' . esc_html( rawurldecode( wp_unslash( $_GET['apprex_mf_err'] ) ) ) . '</p></div>';
	}
} );

/* ダッシュボードの「Square請求状況」の下に、MF振込状況も表示。 */
add_action( 'apprex_dashboard_after_overdue', function () {
	if ( ! apprex_mf_ready() ) {
		return;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'apprex_contract',
			'post_status'    => 'any',
			'posts_per_page' => 300,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_c_mf_billing_id',
		)
	);
	?>
	<h2 style="margin-top:24px;">マネフォ請求状況（振込）</h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
		<input type="hidden" name="action" value="apprex_mf_sync">
		<?php wp_nonce_field( 'apprex_mf_sync' ); ?>
		<button type="submit" class="button">入金状況を更新（MFから取得）</button>
	</form>
	<?php if ( $ids ) : ?>
		<table class="widefat striped" style="max-width:920px;">
			<thead><tr><th>顧客 / 会社</th><th style="text-align:right;">月額</th><th>請求月</th><th>状況</th><th>入金日</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $ids as $id ) :
				$paid = apprex_mf_is_paid( get_post_meta( $id, 'apprex_c_mf_billing_status', true ) );
				?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>"><?php echo esc_html( get_post_meta( $id, 'apprex_c_name', true ) ); ?></a></td>
					<td style="text-align:right;">¥<?php echo esc_html( number_format( (int) get_post_meta( $id, 'apprex_c_monthly', true ) ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $id, 'apprex_c_mf_billing_month', true ) ); ?></td>
					<td><span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700;color:<?php echo $paid ? '#15803d' : '#b91c1c'; ?>;background:<?php echo $paid ? '#dcfce7' : '#fee2e2'; ?>;"><?php echo $paid ? '入金済み' : '未入金'; ?></span></td>
					<td><?php echo esc_html( $paid ? get_post_meta( $id, 'apprex_c_last_paid', true ) : '—' ); ?></td>
					<td><?php $pdf = get_post_meta( $id, 'apprex_c_mf_billing_pdf', true ); echo $pdf ? '<a href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener">PDF</a>' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p style="color:#6b7280;">まだMFでの請求はありません。</p>
	<?php endif;
} );

add_action( 'admin_post_apprex_mf_sync', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_mf_sync' );
	$n = apprex_mf_ready() ? apprex_mf_refresh_all() : 0;
	wp_safe_redirect( add_query_arg( 'apprex_sqsync', (int) $n, admin_url( 'admin.php?page=apprex-dashboard' ) ) );
	exit;
} );

/* =========================================================================
 * 設定ページ
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 請求書(マネフォ)', 'APPREX 請求書(マネフォ)', 'manage_options', 'apprex-mf', 'apprex_mf_settings_page' );
} );
add_action( 'admin_init', function () {
	foreach ( array(
		'apprex_mf_enabled'       => 'absint',
		'apprex_mf_auto'          => 'absint',
		'apprex_mf_client_id'     => 'sanitize_text_field',
		'apprex_mf_client_secret' => 'sanitize_text_field',
		'apprex_mf_department_id' => 'sanitize_text_field',
		'apprex_mf_authorize_url' => 'esc_url_raw',
		'apprex_mf_token_url'     => 'esc_url_raw',
		'apprex_mf_api_base'      => 'esc_url_raw',
		'apprex_mf_scope'         => 'sanitize_text_field',
	) as $opt => $cb ) {
		register_setting( 'apprex_mf', $opt, array( 'sanitize_callback' => $cb ) );
	}
} );

add_action( 'admin_post_apprex_mf_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_mf_test' );
	$back = admin_url( 'options-general.php?page=apprex-mf' );
	$r    = apprex_mf_request( 'GET', '/billings?per_page=1' );
	if ( is_wp_error( $r ) || empty( $r['ok'] ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_mf_err', rawurlencode( apprex_mf_err( $r ) ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'apprex_mf', 'testok', $back ) );
	exit;
} );

function apprex_mf_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$connected = '' !== apprex_mf_opt( 'apprex_mf_refresh_token' );
	?>
	<div class="wrap">
		<h1>APPREX 請求書（マネーフォワード クラウド請求書）</h1>

		<?php
		if ( isset( $_GET['apprex_mf'] ) ) {
			$m = sanitize_key( wp_unslash( $_GET['apprex_mf'] ) );
			$ok = array( 'connected' => '連携が完了しました。', 'testok' => '接続テストOK（請求書APIにアクセスできました）。' );
			if ( isset( $ok[ $m ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $ok[ $m ] ) . '</p></div>';
			} elseif ( 'state' === $m ) {
				echo '<div class="notice notice-error is-dismissible"><p>認可に失敗しました（state不一致）。もう一度お試しください。</p></div>';
			}
		}
		if ( isset( $_GET['apprex_mf_err'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>エラー：' . esc_html( rawurldecode( wp_unslash( $_GET['apprex_mf_err'] ) ) ) . '</p></div>';
		}
		?>

		<div class="notice notice-info"><p>
			<strong>準備</strong>：マネーフォワード クラウドの API（アプリ）を作成し、<strong>リダイレクトURI</strong> に下記を登録 →
			Client ID / Secret を入力 →「保存」→「マネーフォワードと連携する」。<br>
			リダイレクトURI：<code><?php echo esc_html( apprex_mf_redirect_uri() ); ?></code>
		</p></div>

		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_mf' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>有効化</th><td><label><input type="checkbox" name="apprex_mf_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_mf_enabled', 0 ) ); ?>> マネフォ請求を使う</label></td></tr>
				<tr><th>自動発行</th><td><label><input type="checkbox" name="apprex_mf_auto" value="1" <?php checked( 1, (int) get_option( 'apprex_mf_auto', 0 ) ); ?>> 「請求書（振込）」の契約に、毎月自動で請求書を発行する</label></td></tr>
				<tr><th>Client ID</th><td><input type="text" name="apprex_mf_client_id" class="regular-text" value="<?php echo esc_attr( apprex_mf_opt( 'apprex_mf_client_id' ) ); ?>" autocomplete="off"></td></tr>
				<tr><th>Client Secret</th><td><input type="password" name="apprex_mf_client_secret" class="regular-text" value="<?php echo esc_attr( apprex_mf_opt( 'apprex_mf_client_secret' ) ); ?>" autocomplete="off"></td></tr>
				<tr><th>事業者ID（department_id）</th><td><input type="text" name="apprex_mf_department_id" class="regular-text" value="<?php echo esc_attr( apprex_mf_opt( 'apprex_mf_department_id' ) ); ?>"><p class="description">請求書を発行する事業者/部門のID。</p></td></tr>
				<tr><th colspan="2"><strong>詳細（通常は既定のままでOK）</strong></th></tr>
				<tr><th>認可URL</th><td><input type="url" name="apprex_mf_authorize_url" class="regular-text" value="<?php echo esc_attr( apprex_mf_authorize_url() ); ?>"></td></tr>
				<tr><th>トークンURL</th><td><input type="url" name="apprex_mf_token_url" class="regular-text" value="<?php echo esc_attr( apprex_mf_token_url() ); ?>"></td></tr>
				<tr><th>APIベース</th><td><input type="url" name="apprex_mf_api_base" class="regular-text" value="<?php echo esc_attr( apprex_mf_api_base() ); ?>"></td></tr>
				<tr><th>スコープ</th><td><input type="text" name="apprex_mf_scope" class="regular-text" value="<?php echo esc_attr( apprex_mf_scope() ); ?>"></td></tr>
			</tbody></table>
			<?php submit_button( '保存する' ); ?>
		</form>

		<hr>
		<h2>連携</h2>
		<p>状態：<strong style="color:<?php echo $connected ? '#15803d' : '#b91c1c'; ?>;"><?php echo $connected ? '連携済み' : '未連携'; ?></strong></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<input type="hidden" name="action" value="apprex_mf_connect">
			<?php wp_nonce_field( 'apprex_mf_connect' ); ?>
			<?php submit_button( $connected ? '再連携する' : 'マネーフォワードと連携する', 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:8px;">
			<input type="hidden" name="action" value="apprex_mf_test">
			<?php wp_nonce_field( 'apprex_mf_test' ); ?>
			<?php submit_button( '接続テスト', 'secondary', 'submit', false ); ?>
		</form>

		<hr>
		<p class="description" style="max-width:820px;">
			※ APIの各URL・スコープ・税区分・項目名は、ご契約のMFプランやAPI仕様により調整が必要な場合があります。
			連携後に「接続テスト」や実発行でエラーが出たら、その文言をお知らせください（その内容に合わせて即調整します）。
		</p>
	</div>
	<?php
}
