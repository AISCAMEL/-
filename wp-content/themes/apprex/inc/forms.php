<?php
/**
 * Native forms (お問い合わせ / 資料請求 / 無料体験), lead storage, auto-reply,
 * 1-year step-mail (drip) sequence, and LINE誘導.
 *
 * - CPT apprex_inquiry stores every submission.
 * - REST POST /apprex/v1/inquiry handles all form types.
 * - On submit: immediate auto-reply to the customer + admin notification.
 * - A daily cron sends scheduled step mails up to 1 year.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Settings: LINE URL / 通知先 / 資料DL URL
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 連携設定', 'APPREX 連携', 'manage_options', 'apprex-integrations', 'apprex_integrations_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_integrations', 'apprex_line_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	register_setting( 'apprex_integrations', 'apprex_notify_email', array( 'sanitize_callback' => 'sanitize_email' ) );
	register_setting( 'apprex_integrations', 'apprex_document_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	register_setting( 'apprex_integrations', 'apprex_meeting_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	register_setting( 'apprex_integrations', 'apprex_drip_enabled', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_integrations', 'apprex_wp_meeting_reminders', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_integrations', 'apprex_gas_webhook_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
	register_setting( 'apprex_integrations', 'apprex_gas_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_integrations', 'apprex_gsc_verify', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_integrations', 'apprex_twitter', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_integrations', 'apprex_slack_webhook', array( 'sanitize_callback' => 'esc_url_raw' ) );
} );

/**
 * Render the integrations settings page.
 */
function apprex_integrations_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'APPREX 連携設定（LINE / メール / 資料）', 'apprex' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_integrations' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'LINE 公式アカウント URL', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_line_url" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_line_url', '' ) ); ?>" placeholder="https://lin.ee/xxxxxxx">
					<p class="description"><?php esc_html_e( '設定すると、チャット・フォーム・資料請求にLINE誘導ボタンが表示されます。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'LINE配信バナー：バッジ文言', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_line_badge" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_line_badge', '📱 新着記事｜APPREX' ) ); ?>" placeholder="📱 新着記事｜APPREX">
					<p class="description"><?php esc_html_e( '記事公開時にLINEへ送るFlexバナー上部の小見出し（訴求コピー）。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'LINE配信バナー：ボタン文言', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_line_cta" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_line_cta', '記事を読む ▶' ) ); ?>" placeholder="記事を読む ▶">
					<p class="description"><?php esc_html_e( 'タップで記事へ遷移するボタンのラベル。画像・カード全体もタップで記事に飛びます。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'LINE配信バナー：代替画像URL', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_line_banner_fallback" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_line_banner_fallback', '' ) ); ?>" placeholder="https://example.com/line-banner.png">
					<p class="description"><?php esc_html_e( 'アイキャッチが無い記事に使うバナー画像（https のPNG/JPEG）。未設定なら画像なしのカードになります。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '通知先メール', 'apprex' ); ?></th>
					<td><input type="email" name="apprex_notify_email" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_notify_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<p class="description"><?php esc_html_e( '未入力時はサイト管理者メールに送信します。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '資料ダウンロード URL', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_document_url" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_document_url', '' ) ); ?>" placeholder="https://example.com/apprex-document.pdf">
					<p class="description"><?php esc_html_e( '資料請求の自動返信メール・成功画面・ステップメールに記載されるダウンロードリンクです。', 'apprex' ); ?><br>
					<?php printf( esc_html__( '未入力の場合は、テーマ同梱の APPREX サービス資料を自動的に使用します（現在の既定：%s）。', 'apprex' ), '<code>/assets/docs/apprex-service-guide.html</code>' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ミーティング予約URL（Google Meet）', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_meeting_url" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_meeting_url', '' ) ); ?>" placeholder="https://calendar.app.google/xxxxxxxx">
					<p class="description"><?php esc_html_e( 'Googleカレンダーの予約ページ（appointment schedule）URL。設定すると、ミーティングページに「Google Meetで予約」ボタンを表示します。予約時にGoogle Meetの参加URL・招待・リマインダーが自動で発行されます。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ステップメール（1年間）', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_drip_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_drip_enabled', 1 ) ); ?>> <?php esc_html_e( '有効にする（申込後、定期的にフォローメールを自動送信）', 'apprex' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ミーティングのリマインダー', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_wp_meeting_reminders" value="1" <?php checked( 1, (int) get_option( 'apprex_wp_meeting_reminders', 0 ) ); ?>> <?php esc_html_e( 'WordPressからも送る', 'apprex' ); ?></label>
					<p class="description"><?php esc_html_e( '通常はOFF。Google Meet予約のリマインダー（前日・直前）はGoogleカレンダーが自動送信するため、重複を避けるためWordPressからは送りません。ONにすると前日・1時間前・翌日フォローをWordPressからも送信します。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'GAS Webhook URL', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_gas_webhook_url" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_gas_webhook_url', '' ) ); ?>" placeholder="https://script.google.com/macros/s/XXXX/exec">
					<p class="description"><?php esc_html_e( 'お問い合わせ・発注の内容を送る GAS WebアプリのURL。GAS側で スプレッド→Asana→Slack に展開します。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'GAS 共有トークン', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_gas_token" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_gas_token', '' ) ); ?>" autocomplete="off">
					<p class="description"><?php esc_html_e( 'なりすまし防止用の合言葉。GAS側の同じ値と照合します（任意の文字列）。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Slack Webhook URL（新着記事通知）', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_slack_webhook" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_slack_webhook', '' ) ); ?>" placeholder="https://hooks.slack.com/services/XXX/YYY/ZZZ">
					<p class="description"><?php esc_html_e( 'Slack の Incoming Webhook URL。ブログ記事を公開すると、このチャンネルに新着記事を自動投稿します。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Google Search Console 確認コード', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_gsc_verify" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_gsc_verify', '' ) ); ?>" placeholder="例：abcd1234...（content= の値だけ）">
					<p class="description"><?php esc_html_e( 'Search Console の「HTMLタグ」確認で表示される content="..." の中の文字列だけを貼り付け。<head>に確認メタタグを自動出力します。', 'apprex' ); ?></p></td>
				</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'X（旧Twitter）ユーザー名', 'apprex' ); ?></th>
						<td><input type="text" name="apprex_twitter" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_twitter', '' ) ); ?>" placeholder="例：apprex（@は不要）">
						<p class="description"><?php esc_html_e( 'SNSシェア時のTwitterカードに表示するアカウント名（任意）。設定すると twitter:site メタタグを出力します。', 'apprex' ); ?></p></td>
					</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Output the Google Search Console verification meta tag in <head>.
 *
 * Without this, the stored 確認コード is never rendered and verification fails.
 */
add_action( 'wp_head', function () {
	$token = (string) get_option( 'apprex_gsc_verify', '' );
	// Tolerate a full <meta> tag being pasted: keep only the content token.
	if ( $token && preg_match( '/content=["\']([^"\']+)["\']/i', $token, $m ) ) {
		$token = $m[1];
	}
	if ( '' !== $token ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $token ) . '">' . "\n";
	}
}, 1 );

/**
 * LINE URL accessor.
 *
 * @return string
 */
function apprex_line_url() {
	return (string) get_option( 'apprex_line_url', '' );
}

/**
 * Google Meet 予約ページ（Googleカレンダー appointment schedule）URL。
 *
 * @return string
 */
function apprex_meeting_url() {
	return (string) get_option( 'apprex_meeting_url', 'https://calendar.app.google/6xgkopT97tSwsHb76' );
}

/**
 * 資料ダウンロード URL。
 *
 * 管理画面（連携設定）で URL を設定すればそれを優先。未設定なら、テーマ同梱の
 * APPREX サービス資料（自己完結HTML）を既定値として返す。これにより資料請求の
 * 自動返信メール・成功画面・ステップメールのダウンロードリンクが常に有効になる。
 *
 * @return string
 */
function apprex_document_url() {
	$url = (string) get_option( 'apprex_document_url', '' );
	if ( $url ) {
		return $url;
	}
	return APPREX_URI . '/assets/docs/apprex-service-guide.html';
}

/**
 * 資料の共有用ショートURL（例：https://example.com/service-guide ）。
 * 共有・ボタン用のきれいでテーマ非依存のURL。
 *
 * @return string
 */
function apprex_document_view_url() {
	return home_url( '/service-guide' );
}

/**
 * /service-guide でサービス資料を表示するエンドポイント。
 * 管理画面で外部URLが設定されていればそこへリダイレクト、無ければ同梱資料を配信。
 */
add_action( 'init', function () {
	add_rewrite_rule( '^service-guide/?$', 'index.php?apprex_doc=1', 'top' );
	// 初回のみリライト規則を反映（再有効化不要で /service-guide が使えるように）。
	if ( '1' !== get_option( 'apprex_doc_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_doc_rw', '1' );
	}
} );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'apprex_doc';
	return $vars;
} );
// /service-guide の末尾スラッシュ正規化リダイレクトを抑止（リダイレクトループ防止）。
add_filter( 'redirect_canonical', function ( $redirect ) {
	return get_query_var( 'apprex_doc' ) ? false : $redirect;
} );
add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'apprex_doc' ) ) {
		return;
	}
	$override = (string) get_option( 'apprex_document_url', '' );
	$self     = home_url( '/service-guide' );
	// 自己参照（無限ループ）防止：override が /service-guide 自身を指す場合は無視して同梱資料を表示。
	if ( $override
		&& false === strpos( $override, 'service-guide' )
		&& false === strpos( $override, 'apprex_doc' )
		&& untrailingslashit( $override ) !== untrailingslashit( $self ) ) {
		wp_redirect( $override ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}
	$file = APPREX_DIR . '/assets/docs/apprex-service-guide.html';
	if ( is_readable( $file ) ) {
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		readfile( $file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData
		exit;
	}
	wp_safe_redirect( home_url( '/' ) );
	exit;
} );

/**
 * Resolve the notification recipient.
 *
 * @return string
 */
function apprex_notify_to() {
	$opt = get_option( 'apprex_notify_email', '' );
	if ( $opt ) {
		return $opt;
	}
	return function_exists( 'apprex_contact_email' ) ? apprex_contact_email() : get_option( 'admin_email' );
}

/* -------------------------------------------------------------------------
 * Inquiry CPT
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type(
		'apprex_inquiry',
		array(
			'labels'        => array(
				'name'          => __( 'お問い合わせ', 'apprex' ),
				'singular_name' => __( 'お問い合わせ', 'apprex' ),
				'menu_name'     => __( 'お問い合わせ', 'apprex' ),
				'all_items'     => __( 'お問い合わせ一覧', 'apprex' ),
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-email-alt',
			'menu_position' => 7,
			'supports'      => array( 'title' ),
		)
	);
} );

/* -------------------------------------------------------------------------
 * Form rendering helper
 * ---------------------------------------------------------------------- */

/**
 * Labels per form type.
 *
 * @param string $type contact|document|trial|meeting.
 * @return array
 */
function apprex_form_meta( $type ) {
	$map = array(
		'contact'  => array( 'submit' => 'この内容で送信する', 'msg_label' => 'お問い合わせ内容', 'msg_required' => true, 'datetime' => false ),
		'document' => array( 'submit' => '資料をダウンロードする', 'msg_label' => 'ご質問・ご要望（任意）', 'msg_required' => false, 'datetime' => false ),
		'trial'    => array( 'submit' => '30日間 無料体験を申し込む', 'msg_label' => 'ご要望（任意）', 'msg_required' => false, 'datetime' => false ),
		'meeting'  => array( 'submit' => 'この日時で予約する', 'msg_label' => '相談したい内容（任意）', 'msg_required' => false, 'datetime' => true ),
		'partner'  => array( 'submit' => 'パートナー登録を申し込む', 'msg_label' => '事業内容・ご質問（任意）', 'msg_required' => false, 'datetime' => false ),
	);
	return isset( $map[ $type ] ) ? $map[ $type ] : $map['contact'];
}

/**
 * Allowed inquiry types.
 *
 * @return string[]
 */
function apprex_inquiry_types() {
	return array( 'contact', 'document', 'trial', 'meeting', 'partner' );
}

/**
 * Type → Japanese label.
 *
 * @param string $type Type.
 * @return string
 */
function apprex_type_label( $type ) {
	$labels = array(
		'contact'  => 'お問い合わせ',
		'document' => '資料請求',
		'trial'    => '無料体験申込',
		'meeting'  => 'ミーティング予約',
		'partner'  => 'パートナー応募',
		'estimate' => '見積もり・発注',
	);
	return isset( $labels[ $type ] ) ? $labels[ $type ] : 'お問い合わせ';
}

/**
 * Render a native form. Echoes markup; submitted via REST (apprex-forms.js).
 *
 * @param string $type contact|document|trial.
 */
function apprex_render_form( $type = 'contact' ) {
	$meta = apprex_form_meta( $type );
	$line = apprex_line_url();
	?>
	<form class="apprex-form" data-apprex-form data-type="<?php echo esc_attr( $type ); ?>">
		<div class="apprex-form__row">
			<label>
				<span class="apprex-form__lbl"><?php esc_html_e( 'お名前', 'apprex' ); ?> <span class="req">*</span></span>
				<input type="text" name="name" required>
			</label>
			<label>
				<span class="apprex-form__lbl"><?php esc_html_e( '会社名', 'apprex' ); ?></span>
				<input type="text" name="company">
			</label>
		</div>
		<label>
			<span class="apprex-form__lbl"><?php esc_html_e( 'メールアドレス', 'apprex' ); ?> <span class="req">*</span></span>
			<input type="email" name="email" required>
		</label>
		<label>
			<span class="apprex-form__lbl"><?php esc_html_e( '電話番号（任意）', 'apprex' ); ?></span>
			<input type="tel" name="phone">
		</label>
		<?php if ( ! empty( $meta['datetime'] ) ) : ?>
			<label>
				<span class="apprex-form__lbl"><?php esc_html_e( 'ご希望日時', 'apprex' ); ?> <span class="req">*</span></span>
				<input type="datetime-local" name="meeting_at" required>
			</label>
		<?php endif; ?>
		<label>
			<span class="apprex-form__lbl"><?php echo esc_html( $meta['msg_label'] ); ?> <?php echo $meta['msg_required'] ? '<span class="req">*</span>' : ''; ?></span>
			<textarea name="message" rows="5" <?php echo $meta['msg_required'] ? 'required' : ''; ?>></textarea>
		</label>
		<label class="apprex-form__consent">
			<input type="checkbox" name="consent" value="1" required>
			<span><?php esc_html_e( 'プライバシーポリシーに同意します', 'apprex' ); ?></span>
		</label>
		<button type="submit" class="btn btn--primary btn--block"><?php echo esc_html( $meta['submit'] ); ?></button>
		<div class="apprex-form__result" hidden></div>
	</form>

	<?php if ( $line ) : ?>
		<div class="apprex-form__line">
			<p><?php esc_html_e( 'LINEでのご相談も歓迎です。', 'apprex' ); ?></p>
			<a class="line-cta" href="<?php echo esc_url( $line ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'LINEで相談する', 'apprex' ); ?>
			</a>
		</div>
	<?php endif; ?>
	<?php
}

/* -------------------------------------------------------------------------
 * REST submit
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/inquiry',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_rest_inquiry',
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * Handle a form submission.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_rest_inquiry( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'forbidden', '不正なリクエストです。ページを再読み込みしてください。', array( 'status' => 403 ) );
	}

	$type    = sanitize_key( $request->get_param( 'type' ) );
	$type    = in_array( $type, apprex_inquiry_types(), true ) ? $type : 'contact';
	$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$company = sanitize_text_field( (string) $request->get_param( 'company' ) );
	$email   = sanitize_email( (string) $request->get_param( 'email' ) );
	$phone   = sanitize_text_field( (string) $request->get_param( 'phone' ) );
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

	// Meeting preferred datetime (meeting type only).
	// datetime-local の値はサイトのタイムゾーンで解釈する（UTC解釈による時刻ズレ防止）。
	$meeting_raw = sanitize_text_field( (string) $request->get_param( 'meeting_at' ) );
	$meeting_at  = 0;
	if ( 'meeting' === $type && $meeting_raw ) {
		$dt         = date_create( $meeting_raw, wp_timezone() );
		$meeting_at = $dt ? $dt->getTimestamp() : 0;
	}
	if ( 'meeting' === $type && ! $meeting_at ) {
		return new WP_Error( 'bad_request', 'ご希望日時をご指定ください。', array( 'status' => 400 ) );
	}

	if ( '' === $name || ! is_email( $email ) ) {
		return new WP_Error( 'bad_request', 'お名前と有効なメールアドレスをご入力ください。', array( 'status' => 400 ) );
	}

	$type_label = apprex_type_label( $type );
	$post_id    = wp_insert_post(
		array(
			'post_type'   => 'apprex_inquiry',
			'post_status' => 'publish',
			'post_title'  => sprintf( '[%s] %s', $type_label, $name ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save_failed', '送信に失敗しました。時間をおいて再度お試しください。', array( 'status' => 500 ) );
	}

	$fields = compact( 'type', 'name', 'company', 'email', 'phone', 'message' );
	foreach ( $fields as $k => $v ) {
		update_post_meta( $post_id, 'apprex_' . $k, $v );
	}
	update_post_meta( $post_id, 'apprex_submitted_at', current_time( 'mysql' ) );
	if ( $meeting_at ) {
		update_post_meta( $post_id, 'apprex_meeting_at', $meeting_at );
		$fields['meeting_at'] = $meeting_at;
	}

	// Type-specific auto-reply + step / reminder sequence.
	apprex_enroll_drip( $post_id, $type, $email, $name, $meeting_at );
	apprex_send_autoreply( $type, $fields );
	apprex_notify_inquiry( $post_id, $type_label, $fields );

	// GAS連携（スプレッド→Asana→Slack）。
	if ( function_exists( 'apprex_dispatch_event' ) ) {
		apprex_dispatch_event(
			'inquiry',
			array(
				'id'         => $post_id,
				'type'       => $type,
				'type_label' => $type_label,
				'name'       => $name,
				'company'    => $company,
				'email'      => $email,
				'phone'      => $phone,
				'message'    => $message,
				'meeting_at' => $meeting_at ? wp_date( 'Y-m-d H:i', $meeting_at ) : '',
				'admin_url'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			)
		);
	}

	$result = array(
		'ok'      => true,
		'message' => apprex_autoreply_onscreen( $type ),
	);
	if ( 'document' === $type ) {
		$doc = apprex_document_url();
		if ( $doc ) {
			$result['download'] = esc_url_raw( $doc );
		}
	}
	// お問い合わせ・パートナーはミーティング（Web面談）予約へ誘導。
	if ( in_array( $type, array( 'contact', 'partner' ), true ) ) {
		$meet = apprex_meeting_url();
		if ( $meet ) {
			$result['meeting'] = esc_url_raw( $meet );
		}
	}
	$line = apprex_line_url();
	if ( $line ) {
		$result['line'] = esc_url_raw( $line );
	}
	return rest_ensure_response( $result );
}

/**
 * On-screen confirmation per type.
 *
 * @param string $type Type.
 * @return string
 */
function apprex_autoreply_onscreen( $type ) {
	switch ( $type ) {
		case 'document':
			return '資料請求ありがとうございます。ダウンロードリンクをメールでもお送りしました。';
		case 'trial':
			return '無料体験のお申し込みを受け付けました。担当者より2営業日以内にご案内します。';
		case 'meeting':
			return 'ミーティングのご予約を受け付けました。確認のうえ、確定のご連絡を差し上げます。リマインダーもお送りします。';
		case 'partner':
			return 'パートナー登録のお申し込みを受け付けました。担当者より詳細をご案内します。下のボタンから、Webミーティングのご予約もできます。';
		default:
			return 'お問い合わせありがとうございます。担当者より2営業日以内にご連絡いたします。お急ぎの方は、下のボタンからミーティング（Web面談）をご予約ください。';
	}
}

/**
 * Enroll a submission (inquiry or order) into the type-specific drip sequence.
 *
 * @param int    $post_id    Post ID (apprex_inquiry or apprex_order).
 * @param string $type       Sequence type.
 * @param string $email      Recipient.
 * @param string $name       Name.
 * @param int    $meeting_at Meeting timestamp (meeting type only).
 */
function apprex_enroll_drip( $post_id, $type, $email, $name, $meeting_at = 0 ) {
	if ( ! get_option( 'apprex_drip_enabled', 1 ) ) {
		return;
	}
	// ミーティングは原則 Google カレンダーがリマインダーを送るため、WordPress 側は既定で送らない。
	if ( 'meeting' === $type && ! get_option( 'apprex_wp_meeting_reminders', 0 ) ) {
		if ( $meeting_at ) {
			update_post_meta( $post_id, 'apprex_meeting_at', $meeting_at );
		}
		return;
	}
	update_post_meta( $post_id, 'apprex_drip_active', 1 );
	update_post_meta( $post_id, 'apprex_drip_type', $type );
	update_post_meta( $post_id, 'apprex_drip_start', time() );
	update_post_meta( $post_id, 'apprex_drip_sent', array() );
	// Cron reads these standardized keys across both CPTs.
	update_post_meta( $post_id, 'apprex_email', $email );
	update_post_meta( $post_id, 'apprex_name', $name );
	if ( $meeting_at ) {
		update_post_meta( $post_id, 'apprex_meeting_at', $meeting_at );
	}
}

/* -------------------------------------------------------------------------
 * Emails: auto-reply + admin notify
 * ---------------------------------------------------------------------- */

/**
 * Send the immediate auto-reply to the customer.
 *
 * @param string $type   Form type.
 * @param array  $fields Submitted fields.
 */
function apprex_autoreply_message( $type, $fields ) {
	$name = isset( $fields['name'] ) ? $fields['name'] : '';
	$doc  = apprex_document_url();

	switch ( $type ) {
		case 'document':
			$subject = '【APPREX】資料請求ありがとうございます';
			$body    = "{$name} 様\n\nこの度はAPPREXの資料をご請求いただきありがとうございます。\n";
			$body   .= $doc ? "下記より資料をダウンロードいただけます。\n{$doc}\n" : "担当者より資料をお送りいたします。\n";
			break;
		case 'trial':
			$subject = '【APPREX】無料体験のお申し込みありがとうございます';
			$body    = "{$name} 様\n\n30日間無料体験のお申し込みを受け付けました。\n担当者より2営業日以内に開始方法をご案内いたします。\n";
			break;
		case 'meeting':
			$when    = ! empty( $fields['meeting_at'] ) ? wp_date( 'Y年n月j日(D) H:i', (int) $fields['meeting_at'] ) : '';
			$subject = '【APPREX】ミーティングのご予約を受け付けました';
			$body    = "{$name} 様\n\nオンラインミーティング（Google Meet）のご予約ありがとうございます。\n";
			$body   .= $when ? "ご希望日時：{$when}\n" : '';
			$body   .= "内容を確認のうえ、Google MeetのURLを含む確定のご連絡を差し上げます。\n確定後は、Googleカレンダーより開催前のリマインダーが自動で届きます。\n";
			$meet    = apprex_meeting_url();
			if ( $meet ) {
				$body .= "\n▼ ご自身で今すぐ日時を確定したい方はこちら（Google Meet・URL自動発行）\n{$meet}\n";
			}
			break;
		case 'partner':
			$subject = '【APPREX】パートナー登録のお申し込みありがとうございます';
			$body    = "{$name} 様\n\nこの度はAPPREXパートナー（取次販売・紹介）にお申し込みいただきありがとうございます。\n担当者より制度の詳細・報酬・進め方をご案内いたします。\n";
			break;
		default:
			$subject = '【APPREX】お問い合わせありがとうございます';
			$body    = "{$name} 様\n\nお問い合わせいただきありがとうございます。\n内容を確認のうえ、担当者より2営業日以内にご連絡いたします。\n";
	}

	// お問い合わせ・パートナーは Web ミーティング予約へ誘導（URL行はボタン化される）。
	if ( in_array( $type, array( 'contact', 'partner' ), true ) ) {
		$meet = apprex_meeting_url();
		if ( $meet ) {
			$body .= "\nお急ぎの方・先に相談したい方は、無料のWebミーティングをご予約ください。\n{$meet}\n";
		}
	}

	$ov = function_exists( 'apprex_mail_override' ) ? apprex_mail_override( 'autoreply.' . $type ) : null;
	if ( $ov ) {
		if ( ! empty( $ov['subject'] ) ) {
			$subject = $ov['subject'];
		}
		if ( ! empty( $ov['body'] ) ) {
			$body = $ov['body'];
		}
	}
	$when    = ! empty( $fields['meeting_at'] ) ? wp_date( 'Y年n月j日(D) H:i', (int) $fields['meeting_at'] ) : '';
	$body    = strtr( $body, array( '{name}' => $name, '{download_url}' => $doc, '{meeting_at}' => $when ) );
	$subject = strtr( $subject, array( '{name}' => $name ) );

	$body = apply_filters( 'apprex_autoreply_body', $body, $type, $fields );
	return array( $subject, $body );
}

/**
 * Send the immediate auto-reply (HTML) to the customer.
 *
 * @param string $type   Form type.
 * @param array  $fields Submitted fields.
 */
function apprex_send_autoreply( $type, $fields ) {
	list( $subject, $body ) = apprex_autoreply_message( $type, $fields );
	$html = apprex_render_email(
		$subject,
		$body,
		array( 'heading' => apprex_email_heading_from_subject( $subject ) )
	);
	wp_mail( $fields['email'], $subject, $html, apprex_mail_headers() );
}

/**
 * Notify the admin of a new submission.
 *
 * @param int    $post_id    Inquiry ID.
 * @param string $type_label Label.
 * @param array  $fields     Fields.
 */
function apprex_notify_inquiry( $post_id, $type_label, $fields ) {
	$subject = sprintf( '[APPREX] %s #%d — %s', $type_label, $post_id, $fields['name'] );
	$content = apprex_admin_notify_html( $type_label, $fields, $post_id );
	$html    = apprex_email_wrap( $subject, $content, array( 'heading' => '新しい' . $type_label . 'が届きました' ) );
	wp_mail( apprex_notify_to(), $subject, $html, apprex_mail_headers() );
}

/**
 * Default mail headers.
 *
 * @return array
 */
function apprex_mail_headers() {
	$from = apprex_notify_to();
	return array(
		'Content-Type: text/html; charset=UTF-8',
		'From: APPREX <' . $from . '>',
	);
}

/* -------------------------------------------------------------------------
 * Step mail (drip) — up to 1 year
 * ---------------------------------------------------------------------- */

/**
 * Type-specific step sequences. Offset in MINUTES => {subject, body}.
 * 1日=1440 / 3日=4320 / 7日=10080 / 14日=20160 / 30日=43200 など。
 * {name} is replaced at send time. Editable via the apprex_step_mails filter.
 *
 * @param string $type document|trial|estimate|contact.
 * @return array<int,array{subject:string,body:string}>
 */
function apprex_step_mails( $type = 'contact' ) {
	$ft    = home_url( '/free-trial/' );
	$est   = home_url( '/estimate/' );
	$cases = home_url( '/cases/' );
	$hp    = home_url( '/hp-creation/' );
	$home  = home_url( '/' );

	// 文面は PASONA の法則（問題→共感→解決→限定→行動）で構成。
	$sequences = array(
		// 資料請求後：資料活用 → 事例 → 料金 → 体験 → 相談.
		'document' => array(
			1440  => array( 'subject' => '【APPREX】その資料、“積ん読”になっていませんか？', 'body' => "{name} 様\n\n先日は資料をご請求いただき、ありがとうございました。\n\n実は、資料請求された方の多くが「あとでじっくり読もう」と思ったまま、気づけば1週間…というのがよくあるパターンです。お忙しい毎日では当然のことだと思います。\n\nでもAPPREXの本当の価値は、“読む”より“触る”と一瞬で伝わります。プログラミング不要で、画面をドラッグするだけでアプリの形ができていく——その手応えは資料だけでは伝わりきりません。\n\n気になる点が1つでもあれば、このメールにそのままご返信ください。担当が分かりやすくお答えします。\n" ),
			4320  => array( 'subject' => '【APPREX】「自社でも本当にできる？」を消す9つの実例', 'body' => "{name} 様\n\n「ノーコードと言っても、結局むずかしいのでは？」——資料を見て、そう感じた方もいらっしゃるかもしれません。\n\nそのお気持ち、よく分かります。だからこそ、実際に“自社で運用している”企業の事例をまとめました。不動産・士業・教育・店舗・コミュニティまで、9業種の生きた使い方が見られます。\n\nきっと、貴社に近いケースが見つかるはずです。\n{$cases}\n" ),
			10080 => array( 'subject' => '【APPREX】アプリ開発に数百万円は、もう過去の話です', 'body' => "{name} 様\n\nアプリ開発と聞くと「300万円〜」「数ヶ月待ち」——そんなイメージが、導入をためらわせる最大の壁でした。\n\nAPPREXは、その常識を覆します。初期費用0円・月額19,800円〜。従来の開発コストの“約1/10”で、しかも公開後の運用まで含まれています。\n\n「自社の場合はいくら？」は、1分で分かります。下のページで条件を選ぶだけです。\n{$est}\n" ),
			20160 => array( 'subject' => '【APPREX】30日間、まるごと無料で“自分の手”で', 'body' => "{name} 様\n\nここまで資料・事例・料金をご覧いただきました。最後に残るのは、たった1つ——「実際の使い心地」です。\n\n百の説明より、一度の操作。APPREXは30日間、すべての機能を無料でお試しいただけます。クレジットカード登録も不要です。\n\nまずは触れてみて、「これなら自社でできる」を体感してください。\n{$ft}\n" ),
			43200 => array( 'subject' => '【APPREX】最後に、あなたの“あと一歩”を後押しさせてください', 'body' => "{name} 様\n\n資料請求から1ヶ月。ご検討の状況はいかがでしょうか。\n\n「やりたい気持ちはあるけれど、何から始めれば…」という段階でしたら、それを整理するお手伝いをさせてください。チャットでもLINEでも、お電話でも構いません。\n\n初期費用0円のキャンペーンは先着順です。枠が埋まる前に、まずはお気軽にご相談を。このメールへのご返信でも結構です。\n" ),
		),
		// 30日お試し：開始 → 使い方 → 公開 → 事例 → 終了前リマインド → 継続.
		'trial'    => array(
			1440  => array( 'subject' => '【APPREX】無料体験スタート！最初の5分でやること', 'body' => "{name} 様\n\n無料体験のお申し込み、ありがとうございます。ここから30日間、すべての機能をお使いいただけます。\n\n最初の一歩はとてもシンプルです。用意されたテンプレートを1つ選び、文字と画像を差し替えるだけ。たった5分で「アプリらしい画面」が立ち上がります。\n\n「いきなり完璧」を目指す必要はありません。まずは触って、動かす楽しさを味わってください。詰まったら、このメールにご返信を。すぐにお助けします。\n" ),
			4320  => array( 'subject' => '【APPREX】使うほど効く“3つの武器”の設定法', 'body' => "{name} 様\n\n体験は進んでいますか？ APPREXには集客と再来店を生む機能が揃っていますが、まず押さえたいのは3つです。\n\n「プッシュ通知」でお客様のスマホに直接お知らせ。「会員機能」でリピーターを見える化。「クーポン・スタンプカード」でもう一度の来店を後押し。——どれも数クリックで設定できます。\n\n使いこなすほど、アプリは“24時間働く営業マン”になります。設定でお困りなら、お気軽にご返信ください。\n" ),
			10080 => array( 'subject' => '【APPREX】「いつ公開できる？」最短2週間のリアル', 'body' => "{name} 様\n\n体験を続ける中で、「これ、本当に公開できるんだ」と感じていただけていたら嬉しいです。\n\n公開までの流れはシンプルです。①画面を整える ②App Store / Google Play へ申請 ③審査通過で公開。APPREXなら、最短2週間でこのゴールに到達できます。\n\n申請まわりの不安は、私たちがサポートします。「公開したい」と思ったタイミングで、いつでもご相談ください。\n" ),
			20160 => array( 'subject' => '【APPREX】体験を“成果”に変えた人たちの共通点', 'body' => "{name} 様\n\n無料体験から本番運用へ。実際に成果を出した企業には、ある共通点があります。それは「完璧を待たず、まず公開した」こと。\n\n公開後に改善していけるのが、アプリの強みです。会員200%増、再来店率アップ——そんな実例をまとめました。\n{$cases}\n" ),
			36000 => array( 'subject' => '【APPREX】無料体験はあと少し。今だけの初期費用0円も', 'body' => "{name} 様\n\n無料体験の期間終了が近づいてきました。ここまで触っていただき、ありがとうございます。\n\nもし「続けたい」と感じていただけたなら、今がベストタイミングです。今月は先着10社限定で初期費用0円。体験で作った内容を、そのまま本番に引き継げます。\n\n枠には限りがあります。継続のお手続き・お見積りはこちらから。\n{$est}\n" ),
			43200 => array( 'subject' => '【APPREX】体験お疲れさまでした。続きはいつでも', 'body' => "{name} 様\n\n無料体験の期間が終了しました。30日間、ありがとうございました。\n\n「もう少し考えたい」も、もちろん歓迎です。APPREXはいつでも再開でき、ご相談も無料です。タイミングが来たら、いつでもお声がけください。\n\n本番運用・お見積りはこちらからどうぞ。\n{$est}\n" ),
		),
		// 見積もり・発注後：検討促進.
		'estimate' => array(
			1440  => array( 'subject' => '【APPREX】お見積り、ご不明点はありませんか？', 'body' => "{name} 様\n\nこのたびはお見積りをご利用いただき、ありがとうございます。\n\n金額や内容を見て、「ここはどういう意味？」「条件は調整できる？」と感じた点はありませんか。小さな疑問こそ、放っておくと検討が止まってしまう原因になりがちです。\n\nどんな細かいことでも構いません。このメールにご返信いただければ、担当が1つずつお答えします。条件のご相談も柔軟に承ります。\n" ),
			4320  => array( 'subject' => '【APPREX】ご発注後はこう進みます（最短2週間）', 'body' => "{name} 様\n\n「申し込んだあと、どう進むのか分からない」——これも、決断をためらわせる一因です。\n\nAPPREXの流れはシンプルです。①ヒアリングで要件整理 ②画面制作 ③申請・公開。専任の担当が伴走するので、初めての方でも迷いません。最短2週間で公開まで到達できます。\n\n進め方の詳細は、いつでもご説明します。お気軽にご相談ください。\n" ),
			10080 => array( 'subject' => '【APPREX】先着10社・初期費用0円は“今月”まで', 'body' => "{name} 様\n\nご検討ありがとうございます。1つだけ、お急ぎいただきたい理由があります。\n\n現在の「初期費用0円キャンペーン（通常30万円）」は、先着10社・今月末までの限定です。毎月設けている枠ではなく、埋まり次第終了となります。\n\n迷っている時間がもったいない条件です。この機会に、ぜひお見積りを確定させてください。\n{$est}\n" ),
			20160 => array( 'subject' => '【APPREX】その投資、何ヶ月で回収できるか', 'body' => "{name} 様\n\n「コストはわかった。では、効果は？」——当然のご関心だと思います。\n\n同じ価格帯のお客様が、再来店・客単価・新規集客でどう成果を出しているか、事例で具体的に見られます。月額以上のリターンを生んでいるケースも少なくありません。\n{$cases}\n" ),
			43200 => array( 'subject' => '【APPREX】最後に、担当から直接ご提案させてください', 'body' => "{name} 様\n\nお見積りから1ヶ月。ご検討状況はいかがでしょうか。\n\nもし判断に迷う点が残っているなら、オンラインで直接お話しさせてください。貴社の状況に合わせて、プラン・予算・進め方を一緒に最適化します。\n\n押し売りはいたしません。「相談してよかった」と思っていただける時間をお約束します。お気軽にご返信を。\n" ),
		),
		// 汎用（お問い合わせ）：10分後にお礼の1通目 → 翌日以降に長期ナーチャ.
		'contact'  => array(
			10     => array( 'subject' => '【APPREX】お問い合わせありがとうございます（30秒で読めます）', 'body' => "{name} 様\n\nお問い合わせ、ありがとうございます。担当者より改めてご連絡いたしますが、その前に少しだけ。\n\n「アプリを作りたい。でも開発会社に頼むと数百万円、ノーコードは不安…」——もし今そんな板挟みでしたら、それはあなただけの悩みではありません。多くの経営者が“費用”と“運用の難しさ”で立ち止まっています。\n\nAPPREXは、その両方を壊すために生まれました。初期費用0円・月額19,800円〜、プログラミング不要。すでに8,000社以上が最短2週間でアプリを公開しています。\n\nまずは“何ができるか”を、実例でご覧ください。\n{$cases}\n\nお急ぎなら、このメールへのご返信でもすぐ対応します。\n{$ft}\n" ),
			1440   => array( 'subject' => '【APPREX】「うちの業種でも作れる？」への答え', 'body' => "{name} 様\n\n昨日はありがとうございました。今日は、よくいただく不安に1つお答えします。\n\n「アプリは飲食や美容みたいな“店舗系”のもの」——もしそうお考えなら、それは大きな機会損失かもしれません。\n\n実際のAPPREXは、不動産・士業・教育・BtoB・コミュニティまで、業種を問わず動いています。会員管理・予約・プッシュ通知・電子カタログ…必要な機能が最初から揃っているからです。\n\n論より証拠。30日間の無料体験で、あなたの業種に当てはめて触ってみてください。\n{$ft}\n" ),
			4320   => array( 'subject' => '【APPREX】数字で見る、9業種のリアルな成果', 'body' => "{name} 様\n\n「本当に効果が出るの？」——一番気になるところだと思います。\n\nそこで、きれいな宣伝文句ではなく“数字”でお見せします。会員登録200%増、再来店率アップ、問い合わせ増——9業種の導入事例を、成果とあわせてまとめました。\n\n貴社に近いケースが、きっと見つかります。\n{$cases}\n" ),
			10080  => array( 'subject' => '【APPREX】アプリに数百万円は、もう要りません', 'body' => "{name} 様\n\n導入を止める最大の壁は、いつも“費用”でした。アプリ開発に300万円——多くの企業がここで諦めてきました。\n\nAPPREXは、その常識を覆します。初期費用0円・月額19,800円〜、開発費の約1/10。しかも今月は先着10社限定で初期費用0円です。\n\n「自社ならいくら？」は1分で分かります。\n{$est}\n" ),
			20160  => array( 'subject' => '【APPREX】アプリだけじゃない。HPも月額9,800円〜', 'body' => "{name} 様\n\n「まずはホームページから整えたい」——そんな段階の方にも、APPREXはお応えできます。\n\nホームページ制作も、初期費用0円・月額9,800円〜。SSL・SEO・問い合わせフォームまで標準で、公開後の更新サポートも込み。アプリと合わせて、集客の入口から出口まで一気通貫で整えられます。\n{$hp}\n" ),
			43200  => array( 'subject' => '【APPREX】迷ったら、チャット1本で大丈夫です', 'body' => "{name} 様\n\nここまでお付き合いいただき、ありがとうございます。\n\n「興味はあるけど、何から聞けばいいか分からない」——それで止まってしまうのは、本当にもったいないことです。\n\nAPPREXは、チャットでもLINEでも、ひと言からご相談いただけます。「自社の場合どう？」だけでも構いません。あなたの“あと一歩”を、私たちが一緒に踏み出します。\n" ),
			129600 => array( 'subject' => '【APPREX】3ヶ月ぶりに、改めてのご提案です', 'body' => "{name} 様\n\nその後、ご状況はいかがでしょうか。\n\n「あのときはタイミングが合わなかった」——それでも全く問題ありません。事業のフェーズが変われば、アプリの価値も変わります。\n\n条件面のご相談も、以前より柔軟に承れます。改めて、最適なプランを一緒に考えさせてください。\n{$est}\n" ),
			259200 => array( 'subject' => '【APPREX】半年で進化したAPPREXを見てください', 'body' => "{name} 様\n\nご無沙汰しております。この半年で、APPREXはさらに進化しました。\n\n新機能の追加、事例の蓄積、サポート体制の強化——“今のAPPREX”は、あなたが最初に見たときより、ずっと頼れる存在になっています。\n\n最新の事例・機能を、ぜひのぞいてみてください。\n{$home}\n" ),
			525600 => array( 'subject' => '【APPREX】1年間、ありがとうございました', 'body' => "{name} 様\n\nお問い合わせから、ちょうど1年が経ちました。\n\nその節は、APPREXに目を留めていただきありがとうございました。あなたのビジネスが、この1年でさらに前進していることを願っています。\n\nもし「そろそろアプリを」と思うことがあれば、いつでもお声がけください。1年後も、変わらずここでお待ちしています。\n" ),
		),
	);

	$steps = isset( $sequences[ $type ] ) ? $sequences[ $type ] : $sequences['contact'];
	return apply_filters( 'apprex_step_mails', $steps, $type );
}

/**
 * Meeting reminders relative to the booked datetime.
 * offset in seconds (negative = before the meeting). {name}/{when} replaced.
 *
 * @return array<string,array{offset:int,subject:string,body:string}>
 */
function apprex_meeting_reminders() {
	$reminders = array(
		'before_1d' => array(
			'offset'  => -DAY_IN_SECONDS,
			'subject' => '【APPREX】明日はオンラインミーティングのお約束です',
			'body'    => "{name} 様\n\n明日 {when} にオンラインミーティングを予定しております。接続URLは別途ご案内いたします。ご都合が変わる場合はご返信ください。\n",
		),
		'before_1h' => array(
			'offset'  => -HOUR_IN_SECONDS,
			'subject' => '【APPREX】まもなくミーティング開始のお時間です',
			'body'    => "{name} 様\n\n本日 {when} よりミーティングを予定しております。お時間になりましたらご参加ください。\n",
		),
		'after_1d'  => array(
			'offset'  => DAY_IN_SECONDS,
			'subject' => '【APPREX】先日はありがとうございました',
			'body'    => "{name} 様\n\n先日はお時間をいただきありがとうございました。お見積りや無料体験のご用意がございます。\n見積り：" . home_url( '/estimate/' ) . "\n無料体験：" . home_url( '/free-trial/' ) . "\n",
		),
	);
	return apply_filters( 'apprex_meeting_reminders', $reminders );
}

/**
 * Ensure the hourly cron is scheduled (hourly to support meeting reminders).
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_dripmail_cron' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS * 5, 'hourly', 'apprex_dripmail_cron' );
	}
} );
add_action( 'apprex_dripmail_cron', 'apprex_process_dripmail' );

/**
 * Send a single step/reminder mail with footer + unsubscribe.
 *
 * @param int    $id      Subscriber post ID.
 * @param string $email   Recipient.
 * @param string $subject Subject.
 * @param string $body    Body (placeholders already replaced).
 * @param bool   $unsub   Append unsubscribe link.
 */
function apprex_send_drip_mail( $id, $email, $subject, $body, $unsub = true ) {
	$html = apprex_render_email(
		$subject,
		$body,
		array(
			'heading'   => apprex_email_heading_from_subject( $subject ),
			'unsub_url' => $unsub ? apprex_unsubscribe_url( $id, $email ) : '',
		)
	);
	wp_mail( $email, $subject, $html, apprex_mail_headers() );
}

/**
 * Process the drip/reminder queue across inquiries and orders (type-aware).
 */
function apprex_process_dripmail() {
	if ( ! get_option( 'apprex_drip_enabled', 1 ) ) {
		return;
	}
	$subscribers = get_posts(
		array(
			'post_type'      => array( 'apprex_inquiry', 'apprex_order' ),
			// Explicit list: orders use the custom 'apprex_new' status which 'any' excludes.
			'post_status'    => array( 'publish', 'apprex_new' ),
			'posts_per_page' => 300,
			'meta_key'       => 'apprex_drip_active',
			'meta_value'     => '1',
			'fields'         => 'ids',
		)
	);
	if ( empty( $subscribers ) ) {
		return;
	}
	$now = time();

	foreach ( $subscribers as $id ) {
		$type  = get_post_meta( $id, 'apprex_drip_type', true );
		$type  = $type ? $type : 'contact';
		$email = get_post_meta( $id, 'apprex_email', true );
		$name  = get_post_meta( $id, 'apprex_name', true );
		$sent  = (array) get_post_meta( $id, 'apprex_drip_sent', true );

		if ( ! is_email( $email ) ) {
			update_post_meta( $id, 'apprex_drip_active', 0 );
			continue;
		}

		if ( 'meeting' === $type ) {
			apprex_run_meeting_reminders( $id, $email, $name, $sent, $now );
			continue;
		}

		$start = (int) get_post_meta( $id, 'apprex_drip_start', true );
		if ( ! $start ) {
			update_post_meta( $id, 'apprex_drip_active', 0 );
			continue;
		}
		$steps = apprex_step_mails( $type );
		ksort( $steps );
		if ( empty( $steps ) ) {
			update_post_meta( $id, 'apprex_drip_active', 0 );
			continue;
		}
		$max_offset = max( array_keys( $steps ) );

		// オフセットは「分」単位。テストモード時は 1日(1440分)→1分 に圧縮（管理画面で切替）。
		$sec_per_unit = get_option( 'apprex_drip_test_mode' ) ? ( MINUTE_IN_SECONDS / 1440 ) : MINUTE_IN_SECONDS;

		foreach ( $steps as $offset => $mail ) {
			if ( in_array( $offset, $sent, true ) ) {
				continue;
			}
			if ( $now >= $start + ( $offset * $sec_per_unit ) ) {
				apprex_send_drip_mail( $id, $email, $mail['subject'], str_replace( '{name}', $name, $mail['body'] ) );
				$sent[] = $offset;
				update_post_meta( $id, 'apprex_drip_sent', $sent );
			}
		}

		if ( in_array( $max_offset, $sent, true ) || $now > $start + ( 366 * DAY_IN_SECONDS ) ) {
			update_post_meta( $id, 'apprex_drip_active', 0 );
		}
	}
}

/**
 * Send due meeting reminders for one booking.
 *
 * @param int    $id    Inquiry ID.
 * @param string $email Recipient.
 * @param string $name  Name.
 * @param array  $sent  Already-sent reminder keys.
 * @param int    $now   Current timestamp.
 */
function apprex_run_meeting_reminders( $id, $email, $name, $sent, $now ) {
	// Google カレンダーに一本化している場合は WordPress からは送らない。
	if ( ! get_option( 'apprex_wp_meeting_reminders', 0 ) ) {
		update_post_meta( $id, 'apprex_drip_active', 0 );
		return;
	}
	$meeting_at = (int) get_post_meta( $id, 'apprex_meeting_at', true );
	if ( ! $meeting_at ) {
		update_post_meta( $id, 'apprex_drip_active', 0 );
		return;
	}
	$when = wp_date( 'Y年n月j日(D) H:i', $meeting_at );

	foreach ( apprex_meeting_reminders() as $key => $r ) {
		if ( in_array( $key, $sent, true ) ) {
			continue;
		}
		$is_before = $r['offset'] < 0;
		// Don't fire a pre-meeting reminder once the meeting time has passed.
		if ( $is_before && $now >= $meeting_at ) {
			$sent[] = $key;
			update_post_meta( $id, 'apprex_drip_sent', $sent );
			continue;
		}
		if ( $now >= $meeting_at + (int) $r['offset'] ) {
			$body = str_replace( array( '{name}', '{when}' ), array( $name, $when ), $r['body'] );
			apprex_send_drip_mail( $id, $email, $r['subject'], $body, false );
			$sent[] = $key;
			update_post_meta( $id, 'apprex_drip_sent', $sent );
		}
	}

	// Complete two days after the meeting.
	if ( $now > $meeting_at + ( 2 * DAY_IN_SECONDS ) ) {
		update_post_meta( $id, 'apprex_drip_active', 0 );
	}
}

/**
 * Unsubscribe URL with a token.
 *
 * @param int    $id    Inquiry ID.
 * @param string $email Email.
 * @return string
 */
function apprex_unsubscribe_url( $id, $email ) {
	$token = wp_hash( $id . '|' . $email );
	return add_query_arg(
		array(
			'apprex_unsub' => $id,
			'token'        => $token,
		),
		home_url( '/' )
	);
}

/**
 * Handle unsubscribe requests.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['apprex_unsub'] ) || empty( $_GET['token'] ) ) {
		return;
	}
	$id    = absint( $_GET['apprex_unsub'] );
	$email = get_post_meta( $id, 'apprex_email', true );
	$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
	if ( $email && hash_equals( wp_hash( $id . '|' . $email ), $token ) ) {
		update_post_meta( $id, 'apprex_drip_active', 0 );
		wp_die( 'メール配信を停止しました。ご利用ありがとうございました。', '配信停止', array( 'response' => 200 ) );
	}
	wp_die( 'リンクが無効です。', '配信停止', array( 'response' => 400 ) );
} );

/**
 * Clear the cron on theme switch.
 */
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'apprex_dripmail_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'apprex_dripmail_cron' );
	}
} );

/* -------------------------------------------------------------------------
 * Admin: show inquiry details
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_inquiry_detail', 'お問い合わせ詳細', 'apprex_inquiry_detail_box', 'apprex_inquiry', 'normal', 'high' );
} );

/**
 * Render inquiry detail meta box.
 *
 * @param WP_Post $post Inquiry.
 */
function apprex_inquiry_detail_box( $post ) {
	$keys = array(
		'apprex_type'    => '種別',
		'apprex_name'    => 'お名前',
		'apprex_company' => '会社名',
		'apprex_email'   => 'メール',
		'apprex_phone'   => '電話',
		'apprex_message' => '内容',
	);
	echo '<table class="form-table">';
	foreach ( $keys as $k => $label ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) get_post_meta( $post->ID, $k, true ) ) ) . '</td></tr>';
	}
	$sent   = (array) get_post_meta( $post->ID, 'apprex_drip_sent', true );
	$active = (int) get_post_meta( $post->ID, 'apprex_drip_active', true );
	$dtype  = get_post_meta( $post->ID, 'apprex_drip_type', true );
	$mt     = (int) get_post_meta( $post->ID, 'apprex_meeting_at', true );
	if ( $mt ) {
		echo '<tr><th>予約日時</th><td>' . esc_html( wp_date( 'Y-m-d H:i', $mt ) ) . '</td></tr>';
	}
	$kind = ( 'meeting' === $dtype ) ? 'リマインダー' : 'ステップメール';
	echo '<tr><th>' . esc_html( $kind ) . '</th><td>' . ( $active ? '配信中' : '停止/完了' ) . '（種別: ' . esc_html( $dtype ? $dtype : '-' ) . ' ／ 送信済み: ' . esc_html( $sent ? implode( ', ', $sent ) : 'なし' ) . '）</td></tr>';
	echo '</table>';
}
