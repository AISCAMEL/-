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
	register_setting( 'apprex_integrations', 'apprex_drip_enabled', array( 'sanitize_callback' => 'absint' ) );
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
					<th scope="row"><?php esc_html_e( 'ステップメール（1年間）', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_drip_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_drip_enabled', 1 ) ); ?>> <?php esc_html_e( '有効にする（申込後、定期的にフォローメールを自動送信）', 'apprex' ); ?></label></td>
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
 * Resolve the notification recipient.
 *
 * @return string
 */
function apprex_notify_to() {
	$opt = get_option( 'apprex_notify_email', '' );
	return $opt ? $opt : get_option( 'admin_email' );
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
 * @param string $type contact|document|trial.
 * @return array
 */
function apprex_form_meta( $type ) {
	$map = array(
		'contact'  => array( 'submit' => 'この内容で送信する', 'msg_label' => 'お問い合わせ内容', 'msg_required' => true ),
		'document' => array( 'submit' => '資料をダウンロードする', 'msg_label' => 'ご質問・ご要望（任意）', 'msg_required' => false ),
		'trial'    => array( 'submit' => '30日間 無料体験を申し込む', 'msg_label' => 'ご要望（任意）', 'msg_required' => false ),
	);
	return isset( $map[ $type ] ) ? $map[ $type ] : $map['contact'];
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
			<label><?php esc_html_e( 'お名前', 'apprex' ); ?> <span>*</span>
				<input type="text" name="name" required>
			</label>
			<label><?php esc_html_e( '会社名', 'apprex' ); ?>
				<input type="text" name="company">
			</label>
		</div>
		<label><?php esc_html_e( 'メールアドレス', 'apprex' ); ?> <span>*</span>
			<input type="email" name="email" required>
		</label>
		<label><?php esc_html_e( '電話番号（任意）', 'apprex' ); ?>
			<input type="tel" name="phone">
		</label>
		<label><?php echo esc_html( $meta['msg_label'] ); ?> <?php echo $meta['msg_required'] ? '<span>*</span>' : ''; ?>
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
	$type    = in_array( $type, array( 'contact', 'document', 'trial' ), true ) ? $type : 'contact';
	$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$company = sanitize_text_field( (string) $request->get_param( 'company' ) );
	$email   = sanitize_email( (string) $request->get_param( 'email' ) );
	$phone   = sanitize_text_field( (string) $request->get_param( 'phone' ) );
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

	if ( '' === $name || ! is_email( $email ) ) {
		return new WP_Error( 'bad_request', 'お名前と有効なメールアドレスをご入力ください。', array( 'status' => 400 ) );
	}

	$type_label = array( 'contact' => 'お問い合わせ', 'document' => '資料請求', 'trial' => '無料体験申込' )[ $type ];
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

	// Drip subscription.
	if ( get_option( 'apprex_drip_enabled', 1 ) ) {
		update_post_meta( $post_id, 'apprex_drip_active', 1 );
		update_post_meta( $post_id, 'apprex_drip_start', time() );
		update_post_meta( $post_id, 'apprex_drip_sent', array() );
	}

	apprex_send_autoreply( $type, $fields );
	apprex_notify_inquiry( $post_id, $type_label, $fields );

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
		default:
			return 'お問い合わせありがとうございます。担当者より2営業日以内にご連絡いたします。';
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
		'内容: ' . ( $fields['message'] ? $fields['message'] : '（なし）' ),
		'',
		'管理画面: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
	);
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
 * Step definitions: offset in days => {subject, body}. {name} is replaced.
 * Editable via the apprex_step_mails filter.
 *
 * @return array<int,array{subject:string,body:string}>
 */
function apprex_step_mails() {
	$steps = array(
		1   => array(
			'subject' => '【APPREX】ノーコードで“最短2週間”アプリ公開の進め方',
			'body'    => "{name} 様\n\nAPPREXなら、企画から最短2週間でアプリを公開できます。まずは無料体験で操作感をお試しください。\n無料体験：" . home_url( '/free-trial/' ) . "\n",
		),
		3   => array(
			'subject' => '【APPREX】導入事例：9業種で成果が出ています',
			'body'    => "{name} 様\n\nアパレル・飲食・士業・不動産など、業種を問わず成果につながっています。事例はこちら。\n" . home_url( '/cases/' ) . "\n",
		),
		7   => array(
			'subject' => '【APPREX】料金は月額19,800円〜・初期費用0円キャンペーン中',
			'body'    => "{name} 様\n\n従来の開発コストの1/10。初期費用0円キャンペーン実施中です。お見積りは1分で完了します。\n見積り：" . home_url( '/estimate/' ) . "\n",
		),
		14  => array(
			'subject' => '【APPREX】ホームページ制作も月額9,800円〜対応しています',
			'body'    => "{name} 様\n\nアプリだけでなく、ホームページ制作も初期費用0円・月額制で承っています。\n" . home_url( '/hp-creation/' ) . "\n",
		),
		30  => array(
			'subject' => '【APPREX】ご不明点はチャット・LINEでお気軽に',
			'body'    => "{name} 様\n\n導入のご検討状況はいかがでしょうか。サイトのチャットやLINEでお気軽にご相談ください。\n",
		),
		60  => array(
			'subject' => '【APPREX】補助金を活用したアプリ・DX導入のご案内',
			'body'    => "{name} 様\n\nIT導入補助金などを活用すると、コストを抑えて導入いただけます。お気軽にご相談ください。\n",
		),
		90  => array(
			'subject' => '【APPREX】3ヶ月限定の特別ご提案',
			'body'    => "{name} 様\n\n改めてAPPREXのご検討はいかがでしょうか。条件面のご相談も承ります。\n見積り：" . home_url( '/estimate/' ) . "\n",
		),
		180 => array(
			'subject' => '【APPREX】最新機能・事例アップデートのお知らせ',
			'body'    => "{name} 様\n\nAPPREXは機能を継続的に拡充しています。最新の事例・機能をぜひご覧ください。\n" . home_url( '/' ) . "\n",
		),
		365 => array(
			'subject' => '【APPREX】1年のご愛顧に感謝を込めて',
			'body'    => "{name} 様\n\nいつもありがとうございます。改めてアプリ・HP制作のご相談がございましたらお気軽にご連絡ください。\n",
		),
	);
	return apply_filters( 'apprex_step_mails', $steps );
}

/**
 * Ensure the daily cron is scheduled.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_daily_dripmail' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'apprex_daily_dripmail' );
	}
} );

/**
 * Daily handler: send due step mails for active subscribers.
 */
add_action( 'apprex_daily_dripmail', 'apprex_process_dripmail' );

/**
 * Process the drip queue.
 */
function apprex_process_dripmail() {
	if ( ! get_option( 'apprex_drip_enabled', 1 ) ) {
		return;
	}
	$subscribers = get_posts(
		array(
			'post_type'      => 'apprex_inquiry',
			'posts_per_page' => 200,
			'meta_key'       => 'apprex_drip_active',
			'meta_value'     => '1',
			'fields'         => 'ids',
		)
	);
	if ( empty( $subscribers ) ) {
		return;
	}

	$steps = apprex_step_mails();
	ksort( $steps );
	$now = time();

	foreach ( $subscribers as $id ) {
		$start = (int) get_post_meta( $id, 'apprex_drip_start', true );
		$sent  = (array) get_post_meta( $id, 'apprex_drip_sent', true );
		$email = get_post_meta( $id, 'apprex_email', true );
		$name  = get_post_meta( $id, 'apprex_name', true );
		if ( ! $start || ! is_email( $email ) ) {
			update_post_meta( $id, 'apprex_drip_active', 0 );
			continue;
		}

		$max_offset = max( array_keys( $steps ) );
		foreach ( $steps as $offset => $mail ) {
			if ( in_array( $offset, $sent, true ) ) {
				continue;
			}
			if ( $now >= $start + ( $offset * DAY_IN_SECONDS ) ) {
				$subject = $mail['subject'];
				$body    = str_replace( '{name}', $name, $mail['body'] );
				$body   .= "\n──────────\nAPPREX / 合同会社アイズ\n配信停止：" . apprex_unsubscribe_url( $id, $email ) . "\n";
				wp_mail( $email, $subject, $body, apprex_mail_headers() );
				$sent[] = $offset;
				update_post_meta( $id, 'apprex_drip_sent', $sent );
			}
		}

		// Complete after the final step or beyond 1 year.
		if ( in_array( $max_offset, $sent, true ) || $now > $start + ( 366 * DAY_IN_SECONDS ) ) {
			update_post_meta( $id, 'apprex_drip_active', 0 );
		}
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
	$ts = wp_next_scheduled( 'apprex_daily_dripmail' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'apprex_daily_dripmail' );
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
	echo '<tr><th>ステップメール</th><td>' . ( $active ? '配信中' : '停止/完了' ) . '（送信済み: ' . esc_html( $sent ? implode( ', ', $sent ) . '日目' : 'なし' ) . '）</td></tr>';
	echo '</table>';
}
