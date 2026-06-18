<?php
/**
 * Google Drive 連携：顧客フォルダの自動作成。
 *
 * Google Workspace のサービスアカウントを使い、共有ドライブ上の親フォルダ配下に
 * 顧客ごとのフォルダを作成し、その共有リンクを各レコードの apprex_crm_drive に保存する。
 *
 * 安全設計：
 *  - 認証情報（サービスアカウントJSON）＋親フォルダID＋有効化トグルが揃うまで一切動作しない。
 *  - フォーム送信・保存をブロックしない（失敗しても握りつぶしてログのみ）。
 *  - 自動作成は「契約」公開時のみ（任意）。各レコードには手動ボタンを用意。
 *
 * 必要設定（外観 → 設定 → APPREX Drive連携）:
 *  - サービスアカウントの鍵JSON（client_email / private_key を含む）
 *  - 親フォルダID（Workspaceの「共有ドライブ」内に作成し、SAに編集権限を付与）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 設定アクセサ
 * ---------------------------------------------------------------------- */

function apprex_drive_enabled() {
	return (bool) get_option( 'apprex_drive_enabled', 0 );
}
function apprex_drive_sa() {
	$raw = (string) get_option( 'apprex_drive_sa_json', '' );
	if ( '' === $raw ) {
		return null;
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) || empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
		return null;
	}
	return $data;
}
function apprex_drive_parent() {
	return (string) get_option( 'apprex_drive_parent', '' );
}
/** 連携の準備が整っているか。 */
function apprex_drive_ready() {
	return apprex_drive_enabled() && apprex_drive_sa() && '' !== apprex_drive_parent();
}

/* -------------------------------------------------------------------------
 * 認証（サービスアカウント JWT → アクセストークン）
 * ---------------------------------------------------------------------- */

/** base64url。 */
function apprex_drive_b64url( $bin ) {
	return rtrim( strtr( base64_encode( $bin ), '+/', '-_' ), '=' );
}

/**
 * アクセストークンを取得（50分キャッシュ）。失敗時 WP_Error。
 *
 * @return string|WP_Error
 */
function apprex_drive_token() {
	$sa = apprex_drive_sa();
	if ( ! $sa ) {
		return new WP_Error( 'apprex_drive', 'サービスアカウントJSONが未設定/不正です。' );
	}
	$cache_key = 'apprex_drive_tok_' . md5( $sa['client_email'] );
	$cached    = get_transient( $cache_key );
	if ( $cached ) {
		return $cached;
	}
	if ( ! function_exists( 'openssl_sign' ) ) {
		return new WP_Error( 'apprex_drive', 'サーバーに openssl 拡張が必要です。' );
	}

	$now    = time();
	$header = apprex_drive_b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claim  = apprex_drive_b64url(
		wp_json_encode(
			array(
				'iss'   => $sa['client_email'],
				'scope' => 'https://www.googleapis.com/auth/drive',
				'aud'   => 'https://oauth2.googleapis.com/token',
				'iat'   => $now,
				'exp'   => $now + 3600,
			)
		)
	);
	$signing_input = $header . '.' . $claim;
	$signature     = '';
	$ok            = openssl_sign( $signing_input, $signature, $sa['private_key'], 'sha256WithRSAEncryption' );
	if ( ! $ok ) {
		return new WP_Error( 'apprex_drive', '署名に失敗しました（private_keyを確認）。' );
	}
	$jwt = $signing_input . '.' . apprex_drive_b64url( $signature );

	$res = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 20,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['access_token'] ) ) {
		$msg = isset( $body['error_description'] ) ? $body['error_description'] : 'アクセストークン取得に失敗しました。';
		return new WP_Error( 'apprex_drive', $msg );
	}
	set_transient( $cache_key, $body['access_token'], 50 * MINUTE_IN_SECONDS );
	return $body['access_token'];
}

/* -------------------------------------------------------------------------
 * フォルダ作成
 * ---------------------------------------------------------------------- */

/**
 * 親フォルダ配下にフォルダを作成し、共有リンクを返す。失敗時 WP_Error。
 *
 * @param string $name フォルダ名。
 * @return array|WP_Error { id, link }
 */
function apprex_drive_create_folder( $name ) {
	$token = apprex_drive_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$res = wp_remote_post(
		'https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id,webViewLink',
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'name'     => $name,
					'mimeType' => 'application/vnd.google-apps.folder',
					'parents'  => array( apprex_drive_parent() ),
				)
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['id'] ) ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'フォルダ作成に失敗しました。';
		return new WP_Error( 'apprex_drive', $msg );
	}
	$link = ! empty( $body['webViewLink'] ) ? $body['webViewLink'] : 'https://drive.google.com/drive/folders/' . $body['id'];
	return array(
		'id'   => $body['id'],
		'link' => $link,
	);
}

/** レコードに紐づくフォルダ名を生成。 */
function apprex_drive_folder_name( $post_id ) {
	$title = get_the_title( $post_id );
	$title = '' !== trim( wp_strip_all_tags( $title ) ) ? $title : ( '顧客 #' . $post_id );
	return mb_substr( $title, 0, 120 ) . ' (#' . $post_id . ')';
}

/**
 * レコードにフォルダを作成して apprex_crm_drive に保存。
 *
 * @return true|WP_Error
 */
function apprex_drive_create_for_post( $post_id ) {
	if ( get_post_meta( $post_id, 'apprex_crm_drive', true ) ) {
		return true; // 既にある。
	}
	$r = apprex_drive_create_folder( apprex_drive_folder_name( $post_id ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	update_post_meta( $post_id, 'apprex_crm_drive', esc_url_raw( $r['link'] ) );
	update_post_meta( $post_id, 'apprex_crm_drive_id', sanitize_text_field( $r['id'] ) );
	return true;
}

/* -------------------------------------------------------------------------
 * CRMボックスへ「自動作成」ボタンを差し込み
 * ---------------------------------------------------------------------- */

add_action( 'apprex_crm_after_drive', function ( $post, $drive ) {
	if ( ! apprex_drive_ready() || $drive ) {
		return;
	}
	$url = wp_nonce_url(
		admin_url( 'admin-post.php?action=apprex_drive_make&post=' . $post->ID ),
		'apprex_drive_make_' . $post->ID
	);
	echo '<p style="margin:6px 0 0;"><a class="button button-secondary" href="' . esc_url( $url ) . '">📁 Googleドライブにフォルダを自動作成</a></p>';
}, 10, 2 );

add_action( 'admin_post_apprex_drive_make', function () {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_drive_make_' . $post_id );
	$r    = apprex_drive_create_for_post( $post_id );
	$back = get_edit_post_link( $post_id, 'url' );
	$arg  = is_wp_error( $r ) ? array( 'apprex_drive_err' => rawurlencode( $r->get_error_message() ) ) : array( 'apprex_drive_ok' => 1 );
	wp_safe_redirect( add_query_arg( $arg, $back ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( isset( $_GET['apprex_drive_ok'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Googleドライブにフォルダを作成し、リンクを保存しました。</p></div>';
	} elseif ( isset( $_GET['apprex_drive_err'] ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>ドライブ連携エラー：' . esc_html( rawurldecode( wp_unslash( $_GET['apprex_drive_err'] ) ) ) . '</p></div>';
	}
} );

/* -------------------------------------------------------------------------
 * 契約の公開時に自動作成（任意トグル）
 * ---------------------------------------------------------------------- */

add_action( 'save_post_apprex_contract', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! apprex_drive_ready() || ! get_option( 'apprex_drive_auto_contract', 0 ) ) {
		return;
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return;
	}
	if ( get_post_meta( $post_id, 'apprex_crm_drive', true ) ) {
		return;
	}
	$r = apprex_drive_create_for_post( $post_id );
	if ( is_wp_error( $r ) ) {
		// 保存はブロックせず、ログのみ。
		error_log( '[APPREX Drive] ' . $r->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}, 20 );

/* -------------------------------------------------------------------------
 * 設定ページ
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page( 'APPREX Drive連携', 'APPREX Drive連携', 'manage_options', 'apprex-drive', 'apprex_drive_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_drive', 'apprex_drive_enabled', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_drive', 'apprex_drive_sa_json', array( 'sanitize_callback' => 'apprex_drive_sanitize_json' ) );
	register_setting( 'apprex_drive', 'apprex_drive_parent', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_drive', 'apprex_drive_auto_contract', array( 'sanitize_callback' => 'absint' ) );
} );

/** JSONはそのまま保持（整形のみ・検証）。 */
function apprex_drive_sanitize_json( $raw ) {
	$raw = trim( (string) wp_unslash( $raw ) );
	if ( '' === $raw ) {
		return '';
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		add_settings_error( 'apprex_drive', 'badjson', 'サービスアカウントJSONの形式が不正です。' );
		return get_option( 'apprex_drive_sa_json', '' );
	}
	return wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/** 接続テスト。 */
add_action( 'admin_post_apprex_drive_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_drive_test' );
	$token = apprex_drive_token();
	$back  = admin_url( 'options-general.php?page=apprex-drive' );
	if ( is_wp_error( $token ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_drive_err', rawurlencode( $token->get_error_message() ), $back ) );
		exit;
	}
	// 親フォルダの読み取りで疎通確認。
	$res = wp_remote_get(
		'https://www.googleapis.com/drive/v3/files/' . rawurlencode( apprex_drive_parent() ) . '?supportsAllDrives=true&fields=id,name',
		array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		)
	);
	$body = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['id'] ) ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : '親フォルダにアクセスできません（IDと共有設定を確認）。';
		wp_safe_redirect( add_query_arg( 'apprex_drive_err', rawurlencode( $msg ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'apprex_drive_tok', rawurlencode( '接続OK：親フォルダ「' . $body['name'] . '」を確認しました。' ), $back ) );
	exit;
} );

function apprex_drive_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>APPREX Drive連携（顧客フォルダ自動作成）</h1>

		<?php if ( isset( $_GET['apprex_drive_tok'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( rawurldecode( wp_unslash( $_GET['apprex_drive_tok'] ) ) ); ?></p></div>
		<?php elseif ( isset( $_GET['apprex_drive_err'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>エラー：<?php echo esc_html( rawurldecode( wp_unslash( $_GET['apprex_drive_err'] ) ) ); ?></p></div>
		<?php endif; ?>

		<div class="notice notice-info"><p>
			<strong>準備手順</strong>：① Google Cloud でサービスアカウント作成＋Drive API 有効化 →
			② 鍵（JSON）を発行 → ③ Workspace の<strong>共有ドライブ</strong>に親フォルダを作成し、サービスアカウントの
			メールアドレス（<code>…@….iam.gserviceaccount.com</code>）を<strong>編集者</strong>で共有 →
			④ 親フォルダIDをここに設定。
		</p></div>

		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_drive' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row">連携を有効化</th>
					<td><label><input type="checkbox" name="apprex_drive_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_drive_enabled', 0 ) ); ?>> 顧客フォルダの自動作成機能を有効にする</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_drive_parent">親フォルダID（共有ドライブ）</label></th>
					<td><input type="text" id="apprex_drive_parent" name="apprex_drive_parent" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_drive_parent', '' ) ); ?>" placeholder="例）1AbC… フォルダURL末尾のID">
					<p class="description">共有ドライブ内に作った「顧客」親フォルダのID。フォルダを開いたときのURL末尾の文字列です。</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_drive_sa_json">サービスアカウント鍵（JSON）</label></th>
					<td><textarea id="apprex_drive_sa_json" name="apprex_drive_sa_json" rows="8" class="large-text code" placeholder='{ "type": "service_account", "client_email": "...", "private_key": "-----BEGIN PRIVATE KEY-----\n..." }'><?php echo esc_textarea( get_option( 'apprex_drive_sa_json', '' ) ); ?></textarea>
					<p class="description">発行した鍵JSONの中身を貼り付け。<?php echo apprex_drive_sa() ? '<strong style="color:#15803d;">✓ 読み込みOK（client_email / private_key を検出）</strong>' : '<strong style="color:#b91c1c;">未設定 / 不正</strong>'; ?></p></td>
				</tr>
				<tr>
					<th scope="row">契約の自動作成</th>
					<td><label><input type="checkbox" name="apprex_drive_auto_contract" value="1" <?php checked( 1, (int) get_option( 'apprex_drive_auto_contract', 0 ) ); ?>> 「契約」を公開したとき、自動で顧客フォルダを作成する</label>
					<p class="description">お問い合わせ・見積発注の各レコードは、編集画面の「📁 Googleドライブにフォルダを自動作成」ボタンから個別に作成できます。</p></td>
				</tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2>接続テスト</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_drive_test">
			<?php wp_nonce_field( 'apprex_drive_test' ); ?>
			<p>保存後にこのボタンで、認証と親フォルダへのアクセスを確認できます。</p>
			<?php submit_button( '接続をテストする', 'secondary' ); ?>
		</form>
	</div>
	<?php
}
