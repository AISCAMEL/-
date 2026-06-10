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
					<th scope="row"><?php esc_html_e( '通知先メール', 'apprex' ); ?></th>
					<td><input type="email" name="apprex_notify_email" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_notify_email', '' ) ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					<p class="description"><?php esc_html_e( '未入力時はサイト管理者メールに送信します。', 'apprex' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '資料ダウンロード URL', 'apprex' ); ?></th>
					<td><input type="url" name="apprex_document_url" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_document_url', '' ) ); ?>" placeholder="https://example.com/apprex-document.pdf">
					<p class="description"><?php esc_html_e( '資料請求の自動返信メールに記載されるダウンロードリンクです。', 'apprex' ); ?></p></td>
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
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

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
	$meeting_raw = sanitize_text_field( (string) $request->get_param( 'meeting_at' ) );
	$meeting_at  = ( 'meeting' === $type && $meeting_raw ) ? strtotime( $meeting_raw ) : 0;
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
		$doc = get_option( 'apprex_document_url', '' );
		if ( $doc ) {
			$result['download'] = esc_url_raw( $doc );
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
			return 'パートナー登録のお申し込みを受け付けました。担当者より詳細をご案内します。';
		default:
			return 'お問い合わせありがとうございます。担当者より2営業日以内にご連絡いたします。';
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
function apprex_send_autoreply( $type, $fields ) {
	$name = $fields['name'];
	$doc  = get_option( 'apprex_document_url', '' );
	$line = apprex_line_url();

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

	$body .= "\n──────────\nノーコードアプリ開発プラットフォーム APPREX\n合同会社アイズ\n受付：平日10:00〜18:00（チャット・メール・オンライン相談）\n";
	if ( $line ) {
		$body .= "LINEでのご相談：{$line}\n";
	}

	$body = apply_filters( 'apprex_autoreply_body', $body, $type, $fields );
	wp_mail( $fields['email'], $subject, $body, apprex_mail_headers() );
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
	$lines   = array(
		"種別: {$type_label}",
		"お名前: {$fields['name']}",
		'会社名: ' . ( $fields['company'] ? $fields['company'] : '（未入力）' ),
		"メール: {$fields['email']}",
		'電話: ' . ( $fields['phone'] ? $fields['phone'] : '（未入力）' ),
	);
	if ( ! empty( $fields['meeting_at'] ) ) {
		$lines[] = 'ご希望日時: ' . wp_date( 'Y-m-d H:i', (int) $fields['meeting_at'] );
	}
	$lines[] = '内容: ' . ( $fields['message'] ? $fields['message'] : '（なし）' );
	$lines[] = '';
	$lines[] = '管理画面: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	wp_mail( apprex_notify_to(), $subject, implode( "\n", $lines ), apprex_mail_headers() );
}

/**
 * Default mail headers.
 *
 * @return array
 */
function apprex_mail_headers() {
	$from = apprex_notify_to();
	return array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: APPREX <' . $from . '>',
	);
}

/* -------------------------------------------------------------------------
 * Step mail (drip) — up to 1 year
 * ---------------------------------------------------------------------- */

/**
 * Type-specific step sequences. Offset in days => {subject, body}.
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

	$sequences = array(
		// 資料請求後：資料活用 → 事例 → 料金 → 体験 → 相談.
		'document' => array(
			1  => array( 'subject' => '【APPREX】資料はお手元に届きましたか？', 'body' => "{name} 様\n\n先日お送りした資料はご覧いただけましたでしょうか。ご不明点があればこのメールにご返信ください。\n" ),
			3  => array( 'subject' => '【APPREX】事例で見る、アプリ活用のイメージ', 'body' => "{name} 様\n\n9業種の導入事例をまとめています。貴社に近いケースがきっと見つかります。\n{$cases}\n" ),
			7  => array( 'subject' => '【APPREX】料金は月額19,800円〜・初期費用0円', 'body' => "{name} 様\n\n従来の開発コストの1/10。1分でお見積りできます。\n{$est}\n" ),
			14 => array( 'subject' => '【APPREX】まずは30日間の無料体験から', 'body' => "{name} 様\n\n操作感はやはり触ってみるのが一番です。無料体験をご用意しています。\n{$ft}\n" ),
			30 => array( 'subject' => '【APPREX】個別相談・LINEでもお気軽に', 'body' => "{name} 様\n\nご検討状況はいかがでしょうか。チャットやLINEでお気軽にご相談ください。\n" ),
		),
		// 30日お試し：開始 → 使い方 → 公開 → 事例 → 終了前リマインド → 継続.
		'trial'    => array(
			1  => array( 'subject' => '【APPREX】無料体験スタート！最初の一歩', 'body' => "{name} 様\n\n無料体験ありがとうございます。まずはテンプレートを選んで画面を作ってみましょう。困ったらご返信ください。\n" ),
			3  => array( 'subject' => '【APPREX】よく使う機能の使い方', 'body' => "{name} 様\n\nプッシュ通知・会員管理など、よく使う機能の設定方法をご案内します。ご不明点はお気軽に。\n" ),
			7  => array( 'subject' => '【APPREX】公開までの進め方', 'body' => "{name} 様\n\nアプリ公開までの流れをまとめました。最短2週間での公開も可能です。\n" ),
			14 => array( 'subject' => '【APPREX】体験中の方へ：成功事例のご紹介', 'body' => "{name} 様\n\n体験から本番運用で成果を出した事例をご紹介します。\n{$cases}\n" ),
			25 => array( 'subject' => '【APPREX】無料体験は残りわずかです（終了前のご案内）', 'body' => "{name} 様\n\n無料体験の期間終了が近づいています。継続をご希望の場合のお手続き・お見積りはこちら。\n{$est}\n" ),
			30 => array( 'subject' => '【APPREX】無料体験ありがとうございました', 'body' => "{name} 様\n\n体験期間が終了しました。本番運用・ご相談はいつでも承ります。\n{$est}\n" ),
		),
		// 見積もり・発注後：検討促進.
		'estimate' => array(
			1  => array( 'subject' => '【APPREX】お見積り内容のご確認とご相談', 'body' => "{name} 様\n\nお見積りありがとうございます。内容のご相談・条件のご調整も承ります。お気軽にご返信ください。\n" ),
			3  => array( 'subject' => '【APPREX】導入の流れとスケジュール', 'body' => "{name} 様\n\nご発注後の流れ（ヒアリング→制作→公開）をご案内します。最短2週間での公開も可能です。\n" ),
			7  => array( 'subject' => '【APPREX】初期費用0円キャンペーンのご案内', 'body' => "{name} 様\n\n先着5名様・初期費用0円キャンペーン実施中です（通常30万円）。この機会をぜひご活用ください。\n{$est}\n" ),
			14 => array( 'subject' => '【APPREX】事例で見る費用対効果', 'body' => "{name} 様\n\n同価格帯でどのような成果が出ているか、事例でご確認いただけます。\n{$cases}\n" ),
			30 => array( 'subject' => '【APPREX】個別相談のご提案', 'body' => "{name} 様\n\nご検討状況はいかがでしょうか。オンラインでのご相談も承っております。\n" ),
		),
		// 汎用（お問い合わせ）：長期ナーチャ.
		'contact'  => array(
			1   => array( 'subject' => '【APPREX】ノーコードで“最短2週間”アプリ公開', 'body' => "{name} 様\n\nまずは無料体験で操作感をお試しください。\n{$ft}\n" ),
			3   => array( 'subject' => '【APPREX】9業種の導入事例', 'body' => "{name} 様\n\n業種を問わず成果につながっています。\n{$cases}\n" ),
			7   => array( 'subject' => '【APPREX】料金・初期費用0円キャンペーン', 'body' => "{name} 様\n\n1分でお見積りできます。\n{$est}\n" ),
			14  => array( 'subject' => '【APPREX】ホームページ制作も月額9,800円〜', 'body' => "{name} 様\n\nHP制作も初期費用0円・月額制で承っています。\n{$hp}\n" ),
			30  => array( 'subject' => '【APPREX】チャット・LINEでお気軽に', 'body' => "{name} 様\n\nご不明点はお気軽にご相談ください。\n" ),
			90  => array( 'subject' => '【APPREX】改めてのご提案', 'body' => "{name} 様\n\n条件面のご相談も承ります。\n{$est}\n" ),
			180 => array( 'subject' => '【APPREX】最新機能・事例アップデート', 'body' => "{name} 様\n\n最新の事例・機能をぜひご覧ください。\n" . home_url( '/' ) . "\n" ),
			365 => array( 'subject' => '【APPREX】1年のご愛顧に感謝を込めて', 'body' => "{name} 様\n\nいつもありがとうございます。改めてのご相談もお気軽に。\n" ),
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
	$body .= "\n──────────\nAPPREX / 合同会社アイズ\n";
	if ( $unsub ) {
		$body .= '配信停止：' . apprex_unsubscribe_url( $id, $email ) . "\n";
	}
	wp_mail( $email, $subject, $body, apprex_mail_headers() );
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

		foreach ( $steps as $offset => $mail ) {
			if ( in_array( $offset, $sent, true ) ) {
				continue;
			}
			if ( $now >= $start + ( $offset * DAY_IN_SECONDS ) ) {
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
