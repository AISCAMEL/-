<?php
/**
 * AIスタジオ（無料会員システム）。
 *
 * 流れ：無料会員登録 → AIでデザイン → AIで仕様書・概算 → 開発を相談 → オンラインミーティング
 *       → お見積り・ご入金 → 制作 → 公開
 *
 * - 会員 = WordPressユーザー（subscriber）。登録すると自動ログインし、そのままスタジオを使える
 * - デザイン・仕様の作業内容はユーザーメタに保存（別の端末でも続きから）
 * - 「開発を相談」「ミーティング予約」は既存の問い合わせ基盤（REST /inquiry）に流す
 *   → 自動返信・管理者通知・GAS/Slack連携・ホットリード通知・広告コンバージョンがそのまま動く
 * - お見積り以降の進み具合と担当者メッセージは、管理画面のユーザー編集画面「AIスタジオ」で更新
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 進み具合（管理者が更新する段階）。 */
function apprex_studio_stages() {
	return array(
		0 => '作成中',
		1 => '相談を受付',
		2 => 'ミーティング予約済み',
		3 => 'お見積りを送付',
		4 => '入金確認・制作中',
		5 => '公開済み',
	);
}

/** 制作の工程（画面の「制作の進捗」と共通）。 */
function apprex_studio_prod_steps() {
	return array( 'キックオフ', 'デザイン確定', '開発・登録', 'テスト版の確認', 'ストア申請', '公開' );
}

/* -------------------------------------------------------------------------
 * 問い合わせ基盤への記録
 * ---------------------------------------------------------------------- */

/**
 * 既存の問い合わせ処理（apprex_rest_inquiry）に内部リクエストで流す。
 *
 * @param array $params type/name/company/email/phone/message/meeting_at。
 * @return array|WP_Error REST の結果配列。
 */
function apprex_studio_inquiry( $params ) {
	$req = new WP_REST_Request( 'POST', '/apprex/v1/inquiry' );
	$req->set_header( 'x_wp_nonce', wp_create_nonce( 'wp_rest' ) );
	$req->set_body_params( $params );
	$res = apprex_rest_inquiry( $req );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return $res->get_data();
}

/** ログイン中の会員の連絡先。 */
function apprex_studio_contact( $user ) {
	return array(
		'name'    => $user->display_name ? $user->display_name : $user->user_login,
		'company' => (string) get_user_meta( $user->ID, 'apprex_company', true ),
		'email'   => $user->user_email,
		'phone'   => (string) get_user_meta( $user->ID, 'apprex_phone', true ),
	);
}

/* -------------------------------------------------------------------------
 * REST
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	$member = function () {
		return is_user_logged_in();
	};
	register_rest_route( 'apprex/v1', '/studio/register', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_register', 'permission_callback' => '__return_true' ) );
	register_rest_route( 'apprex/v1', '/studio/state', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_state', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/consult', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_consult', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/meeting', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_meeting', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/message', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_message', 'permission_callback' => $member ) );
} );

/** nonce 確認（ログイン前後どちらも wp_rest）。 */
function apprex_studio_check_nonce( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
}

/**
 * 無料会員登録 → 自動ログイン。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_register( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', 'ページの有効期限が切れました。再読み込みしてください。', array( 'status' => 403 ) );
	}
	if ( is_user_logged_in() ) {
		return rest_ensure_response( array( 'ok' => true ) );
	}
	// ボット対策：非表示の入力欄に値が入っていたら破棄。
	if ( '' !== (string) $request->get_param( 'website' ) ) {
		return new WP_Error( 'bad_request', '登録できませんでした。', array( 'status' => 400 ) );
	}
	// 同じIPからの連続登録を制限（1時間に5件まで）。
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'apprex_reg_' . md5( $ip );
	$cnt = (int) get_transient( $key );
	if ( $cnt >= 5 ) {
		return new WP_Error( 'too_many', 'しばらく時間をおいてからお試しください。', array( 'status' => 429 ) );
	}

	$name     = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$company  = sanitize_text_field( (string) $request->get_param( 'company' ) );
	$email    = sanitize_email( (string) $request->get_param( 'email' ) );
	$password = (string) $request->get_param( 'password' );

	if ( '' === $name || ! is_email( $email ) ) {
		return new WP_Error( 'bad_request', 'お名前と有効なメールアドレスをご入力ください。', array( 'status' => 400 ) );
	}
	if ( strlen( $password ) < 8 ) {
		return new WP_Error( 'bad_request', 'パスワードは8文字以上にしてください。', array( 'status' => 400 ) );
	}
	if ( email_exists( $email ) ) {
		return new WP_Error( 'exists', 'このメールアドレスは登録済みです。下の「ログイン」からお入りください。', array( 'status' => 409 ) );
	}

	$login = sanitize_user( current( explode( '@', $email ) ), true );
	$login = $login ? $login : 'member';
	$base  = $login;
	$i     = 1;
	while ( username_exists( $login ) ) {
		$login = $base . $i++;
	}
	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $name,
			'first_name'   => $name,
			'role'         => 'subscriber',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		return new WP_Error( 'failed', '登録に失敗しました。時間をおいて再度お試しください。', array( 'status' => 500 ) );
	}
	set_transient( $key, $cnt + 1, HOUR_IN_SECONDS );
	update_user_meta( $user_id, 'apprex_company', $company );
	update_user_meta( $user_id, 'apprex_studio', 1 );
	update_user_meta( $user_id, 'apprex_studio_joined', time() );
	update_user_meta( $user_id, 'apprex_studio_stage', 0 );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	// リードとして記録（自動返信・通知・CRM・広告計測は既存の仕組み）。
	apprex_studio_inquiry(
		array(
			'type'    => 'signup',
			'name'    => $name,
			'company' => $company,
			'email'   => $email,
			'message' => 'AIスタジオに無料会員登録しました。',
		)
	);

	return rest_ensure_response(
		array(
			'ok'       => true,
			'redirect' => apprex_studio_url(),
			'lead'     => array( 'type' => 'signup', 'event_id' => 'signup_' . $user_id ),
		)
	);
}

/**
 * 作業内容の保存。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_state( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$state = (string) $request->get_param( 'state' );
	if ( strlen( $state ) > 200000 || null === json_decode( $state ) ) {
		return new WP_Error( 'bad_request', '保存できませんでした。', array( 'status' => 400 ) );
	}
	update_user_meta( get_current_user_id(), 'apprex_studio_state', wp_slash( $state ) );
	update_user_meta( get_current_user_id(), 'apprex_studio_saved', time() );
	return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * 開発を相談（デザイン・仕様書・概算をまとめて担当者へ）。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_consult( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$user    = wp_get_current_user();
	$summary = sanitize_textarea_field( (string) $request->get_param( 'summary' ) );
	$plan    = sanitize_text_field( (string) $request->get_param( 'plan' ) );
	$res     = apprex_studio_inquiry(
		array_merge(
			apprex_studio_contact( $user ),
			array(
				'type'    => 'studio',
				'message' => "【進め方】{$plan}\n\n{$summary}",
			)
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	update_user_meta( $user->ID, 'apprex_studio_consulted', time() );
	update_user_meta( $user->ID, 'apprex_studio_summary', $summary );
	if ( (int) get_user_meta( $user->ID, 'apprex_studio_stage', true ) < 1 ) {
		update_user_meta( $user->ID, 'apprex_studio_stage', 1 );
	}
	return rest_ensure_response( array( 'ok' => true, 'lead' => isset( $res['lead'] ) ? $res['lead'] : null ) );
}

/**
 * オンラインミーティング予約（既存のミーティング予約と同じ処理）。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_meeting( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$user = wp_get_current_user();
	$at   = sanitize_text_field( (string) $request->get_param( 'meeting_at' ) );
	$res  = apprex_studio_inquiry(
		array_merge(
			apprex_studio_contact( $user ),
			array(
				'type'       => 'meeting',
				'meeting_at' => $at,
				'message'    => 'AIスタジオからのミーティング予約です。相談内容はAIスタジオの相談（管理画面のユーザー → AIスタジオ）をご確認ください。',
			)
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	update_user_meta( $user->ID, 'apprex_studio_meeting', $at );
	if ( (int) get_user_meta( $user->ID, 'apprex_studio_stage', true ) < 2 ) {
		update_user_meta( $user->ID, 'apprex_studio_stage', 2 );
	}
	return rest_ensure_response( array( 'ok' => true, 'message' => isset( $res['message'] ) ? $res['message'] : '' ) );
}

/**
 * 担当者へのメッセージ。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_message( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$text = sanitize_textarea_field( (string) $request->get_param( 'text' ) );
	if ( '' === $text ) {
		return new WP_Error( 'bad_request', 'メッセージを入力してください。', array( 'status' => 400 ) );
	}
	$user   = wp_get_current_user();
	$msgs   = apprex_studio_messages( $user->ID );
	$msgs[] = array( 'me', mb_substr( $text, 0, 2000 ), time() );
	update_user_meta( $user->ID, 'apprex_studio_msgs', array_slice( $msgs, -200 ) );

	wp_mail(
		apprex_notify_to(),
		sprintf( '【AIスタジオ】%s 様からメッセージ', $user->display_name ),
		$text . "\n\n返信はこちら：\n" . admin_url( 'user-edit.php?user_id=' . $user->ID . '#apprex-studio' ),
		apprex_mail_headers()
	);
	return rest_ensure_response( array( 'ok' => true, 'msgs' => apprex_studio_messages_out( $user->ID ) ) );
}

/** メッセージ一覧（[who, text, time]）。 */
function apprex_studio_messages( $user_id ) {
	$m = get_user_meta( $user_id, 'apprex_studio_msgs', true );
	return is_array( $m ) ? $m : array();
}

/** 画面表示用のメッセージ。 */
function apprex_studio_messages_out( $user_id ) {
	return array_map(
		function ( $m ) {
			return array( $m[0], $m[1], wp_date( 'n/j H:i', (int) $m[2] ) );
		},
		apprex_studio_messages( $user_id )
	);
}

/* -------------------------------------------------------------------------
 * 画面（page-templates/page-studio.php）用の設定
 * ---------------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page_template( 'page-templates/page-studio.php' ) ) {
		return;
	}
	wp_enqueue_style( 'apprex-studio-fonts', 'https://fonts.googleapis.com/css2?family=Zen+Old+Mincho:wght@700&family=M+PLUS+Rounded+1c:wght@700;800&display=swap', array(), null );
	wp_enqueue_style( 'apprex-studio', APPREX_URI . '/assets/css/studio.css', array( 'apprex-style' ), APPREX_VERSION );

	$cfg = array(
		'root'      => esc_url_raw( rest_url( 'apprex/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'loggedIn'  => is_user_logged_in(),
		'mypage'    => apprex_mypage_url(),
		'studioUrl' => apprex_studio_url(),
	);
	if ( is_user_logged_in() ) {
		$u          = wp_get_current_user();
		$state      = (string) get_user_meta( $u->ID, 'apprex_studio_state', true );
		$cfg['uid'] = $u->ID;
		$cfg['user'] = apprex_studio_contact( $u );
		$cfg['state']   = $state ? json_decode( $state, true ) : null;
		$cfg['stage']   = (int) get_user_meta( $u->ID, 'apprex_studio_stage', true );
		$cfg['prod']    = (int) get_user_meta( $u->ID, 'apprex_studio_prod', true );
		$cfg['meeting'] = (string) get_user_meta( $u->ID, 'apprex_studio_meeting', true );
		$cfg['quote']   = array(
			'url'  => esc_url_raw( (string) get_user_meta( $u->ID, 'apprex_studio_quote_url', true ) ),
			'note' => (string) get_user_meta( $u->ID, 'apprex_studio_quote_note', true ),
		);
		$cfg['msgs']    = apprex_studio_messages_out( $u->ID );
		$cfg['logout']  = wp_logout_url( apprex_studio_url() );
	}
	wp_enqueue_script( 'apprex-studio', APPREX_URI . '/assets/js/studio.js', array(), APPREX_VERSION, true );
	wp_localize_script( 'apprex-studio', 'APPREX_STUDIO', $cfg );
}, 25 );

/* -------------------------------------------------------------------------
 * 管理画面：ユーザー編集「AIスタジオ」
 * ---------------------------------------------------------------------- */
add_action( 'show_user_profile', 'apprex_studio_admin_box' );
add_action( 'edit_user_profile', 'apprex_studio_admin_box' );

/**
 * 進み具合・お見積り・担当者メッセージを管理する欄。
 *
 * @param WP_User $user ユーザー。
 */
function apprex_studio_admin_box( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! get_user_meta( $user->ID, 'apprex_studio', true ) ) {
		return;
	}
	$stage   = (int) get_user_meta( $user->ID, 'apprex_studio_stage', true );
	$prod    = (int) get_user_meta( $user->ID, 'apprex_studio_prod', true );
	$summary = (string) get_user_meta( $user->ID, 'apprex_studio_summary', true );
	$meeting = (string) get_user_meta( $user->ID, 'apprex_studio_meeting', true );
	wp_nonce_field( 'apprex_studio_admin', 'apprex_studio_nonce' );
	?>
	<h2 id="apprex-studio">AIスタジオ</h2>
	<table class="form-table" role="presentation">
		<tr><th>会社名</th><td><?php echo esc_html( (string) get_user_meta( $user->ID, 'apprex_company', true ) ); ?></td></tr>
		<tr><th>進み具合</th><td>
			<select name="apprex_studio_stage">
				<?php foreach ( apprex_studio_stages() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $stage, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description">「お見積りを送付」にすると、会員のスタジオにお見積りの案内が表示されます。「入金確認・制作中」で制作の進捗が表示されます。</p>
		</td></tr>
		<tr><th>制作の工程</th><td>
			<select name="apprex_studio_prod">
				<?php foreach ( apprex_studio_prod_steps() as $k => $label ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $prod, $k ); ?>><?php echo esc_html( ( $k + 1 ) . '. ' . $label . '（進行中）' ); ?></option>
				<?php endforeach; ?>
				<option value="6" <?php selected( $prod, 6 ); ?>>すべて完了</option>
			</select>
		</td></tr>
		<tr><th>お見積り・お支払いURL</th><td>
			<input type="url" class="regular-text" name="apprex_studio_quote_url" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'apprex_studio_quote_url', true ) ); ?>" placeholder="https://（見積書PDF・Square決済リンクなど）">
			<p class="description">空欄の場合はマイページ（契約・お支払い）へ案内します。</p>
		</td></tr>
		<tr><th>お見積りの説明</th><td><textarea name="apprex_studio_quote_note" rows="3" class="large-text"><?php echo esc_textarea( (string) get_user_meta( $user->ID, 'apprex_studio_quote_note', true ) ); ?></textarea></td></tr>
		<tr><th>ミーティング</th><td><?php echo esc_html( $meeting ? $meeting : '未予約' ); ?></td></tr>
		<tr><th>相談内容（AI仕様書）</th><td><textarea readonly rows="10" class="large-text code"><?php echo esc_textarea( $summary ? $summary : '（まだ相談は届いていません）' ); ?></textarea></td></tr>
		<tr><th>メッセージ</th><td>
			<div style="max-height:260px;overflow:auto;background:#f6f7f7;border:1px solid #dcdcde;padding:8px 10px;margin-bottom:8px">
				<?php
				$msgs = apprex_studio_messages( $user->ID );
				if ( ! $msgs ) {
					echo '<p>まだメッセージはありません。</p>';
				}
				foreach ( $msgs as $m ) {
					printf( '<p><strong>%s</strong> <small>%s</small><br>%s</p>', 'staff' === $m[0] ? 'APPREX' : esc_html( $user->display_name ), esc_html( wp_date( 'Y/n/j H:i', (int) $m[2] ) ), nl2br( esc_html( $m[1] ) ) );
				}
				?>
			</div>
			<textarea name="apprex_studio_reply" rows="3" class="large-text" placeholder="会員への返信（保存すると送信され、メールでもお知らせします）"></textarea>
		</td></tr>
	</table>
	<?php
}

add_action( 'personal_options_update', 'apprex_studio_admin_save' );
add_action( 'edit_user_profile_update', 'apprex_studio_admin_save' );

/**
 * 管理欄の保存。
 *
 * @param int $user_id ユーザーID。
 */
function apprex_studio_admin_save( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['apprex_studio_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_studio_nonce'] ) ), 'apprex_studio_admin' ) ) {
		return;
	}
	if ( isset( $_POST['apprex_studio_stage'] ) ) {
		update_user_meta( $user_id, 'apprex_studio_stage', min( 5, max( 0, (int) $_POST['apprex_studio_stage'] ) ) );
	}
	if ( isset( $_POST['apprex_studio_prod'] ) ) {
		update_user_meta( $user_id, 'apprex_studio_prod', min( 6, max( 0, (int) $_POST['apprex_studio_prod'] ) ) );
	}
	if ( isset( $_POST['apprex_studio_quote_url'] ) ) {
		update_user_meta( $user_id, 'apprex_studio_quote_url', esc_url_raw( wp_unslash( $_POST['apprex_studio_quote_url'] ) ) );
	}
	if ( isset( $_POST['apprex_studio_quote_note'] ) ) {
		update_user_meta( $user_id, 'apprex_studio_quote_note', sanitize_textarea_field( wp_unslash( $_POST['apprex_studio_quote_note'] ) ) );
	}
	$reply = isset( $_POST['apprex_studio_reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apprex_studio_reply'] ) ) : '';
	if ( '' !== $reply ) {
		$msgs   = apprex_studio_messages( $user_id );
		$msgs[] = array( 'staff', $reply, time() );
		update_user_meta( $user_id, 'apprex_studio_msgs', array_slice( $msgs, -200 ) );
		$user = get_userdata( $user_id );
		if ( $user ) {
			wp_mail(
				$user->user_email,
				'【APPREX】AIスタジオに担当者からメッセージが届きました',
				$user->display_name . " 様\n\n" . $reply . "\n\nAIスタジオで続きを確認・返信できます。\n" . apprex_studio_url(),
				apprex_mail_headers()
			);
		}
	}
}

/* マイページにAIスタジオへの入口を出す。 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'mypage' ) || ! is_user_logged_in() || ! in_the_loop() ) {
		return $content;
	}
	$link = '<p class="apprex-mypage-studio" style="margin:0 0 20px;padding:14px 16px;border:1px solid #cbd9ff;border-radius:12px;background:#eef3ff">'
		. '<strong>AIスタジオ</strong>　アプリのデザイン・仕様書・相談・制作の進捗はこちら '
		. '<a class="btn btn--primary" style="margin-left:8px" href="' . esc_url( apprex_studio_url() ) . '">AIスタジオを開く</a></p>';
	return $link . $content;
}, 9 );
