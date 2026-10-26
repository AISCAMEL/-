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
	register_rest_route( 'apprex/v1', '/studio/ai-design', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_ai_design', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/ai-image', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_ai_image', 'permission_callback' => $member ) );
	register_rest_route( 'apprex/v1', '/studio/ai-plan', array( 'methods' => 'POST', 'callback' => 'apprex_studio_rest_ai_plan', 'permission_callback' => $member ) );
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
	if ( function_exists( 'apprex_rn_public' ) && ! apprex_rn_public() ) {
		return new WP_Error( 'not_public', 'AIスタジオは公開準備中です。', array( 'status' => 403 ) );
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
	// AIで生成して採用した画像（無ければ最新3枚）のURLを添える。
	$imgs = apprex_studio_images_out( $user->ID );
	$pick = array_filter( $imgs, function ( $im ) {
		return $im['picked'];
	} );
	$pick = $pick ? $pick : array_slice( $imgs, 0, 3 );
	if ( $pick ) {
		$summary .= "\n\n【AIデザイン画像】\n" . implode( "\n", array_map( function ( $im ) {
			return '・' . $im['label'] . ( $im['picked'] ? '（採用）' : '' ) . '：' . $im['url'];
		}, $pick ) );
	}
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
		'studioUrl' => apprex_studio_url( apprex_studio_mode() ),
		'manual'    => function_exists( 'apprex_manual_url' ) ? apprex_manual_url() : '',
		'mode'      => apprex_studio_mode(),
		'studios'   => array( 'app' => apprex_studio_url( 'app' ), 'hp' => apprex_studio_url( 'hp' ), 'matching' => apprex_studio_url( 'matching' ) ),
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
		$cfg['logout']  = wp_logout_url( apprex_studio_url( apprex_studio_mode() ) );
		$cfg['ai']      = array(
			'text'  => apprex_ai_text_ready(),
			'image' => '' !== apprex_openrouter_key(),
			'left'  => apprex_studio_image_left( $u->ID ),
			'limit' => apprex_studio_image_limit(),
		);
		$cfg['images']  = apprex_studio_images_out( $u->ID );
		$cfg['assets']  = apprex_studio_assets( $u->ID );
		$cfg['demoUrl'] = apprex_demo_url( apprex_demo_token( $u->ID ) );
		$cfg['preview'] = apprex_preview_app_links();
		$cfg['orig']    = function_exists( 'apprex_orig_sanitize' ) ? apprex_orig_sanitize( json_decode( (string) get_user_meta( $u->ID, 'apprex_orig_spec', true ), true ) ) : null;
		$cfg['origDemo'] = apprex_demo_url( apprex_demo_token( $u->ID ), array( 'v' => 'orig' ) );
		$cfg['origImages'] = function_exists( 'apprex_orig_images_out' ) ? apprex_orig_images_out( $u->ID ) : null;
		$cfg['est']     = function_exists( 'apprex_orig_estimate_rules' ) ? apprex_orig_estimate_rules() : null;
		$cfg['hp']       = function_exists( 'apprex_hp_get' ) ? apprex_hp_get( $u->ID ) : null;
		$cfg['hpDemo']   = function_exists( 'apprex_hp_demo_url' ) ? apprex_hp_demo_url( $u->ID ) : '';
		$cfg['hpImage']  = function_exists( 'apprex_hp_image_url' ) ? apprex_hp_image_url( $u->ID ) : '';
		$cfg['hpSample'] = apprex_demo_url( 'sample', array( 'v' => 'hp' ) );
	}
	wp_enqueue_script( 'apprex-studio-data', APPREX_URI . '/assets/js/studio-data.js', array(), APPREX_VERSION, true );
	wp_enqueue_script( 'apprex-qrcode', APPREX_URI . '/assets/js/vendor/qrcode.js', array(), '2.0.4', true );
	wp_enqueue_script( 'apprex-studio', APPREX_URI . '/assets/js/studio.js', array( 'apprex-studio-data', 'apprex-qrcode' ), APPREX_VERSION, true );
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
		<?php
		$apprex_os = function_exists( 'apprex_orig_sanitize' ) ? apprex_orig_sanitize( json_decode( (string) get_user_meta( $user->ID, 'apprex_orig_spec', true ), true ) ) : null;
		if ( $apprex_os ) :
			?>
		<tr><th>オリジナルアプリ（AI試作）</th><td>
			<strong><?php echo esc_html( $apprex_os['name'] ); ?></strong>（<?php echo esc_html( $apprex_os['category'] ); ?>・<?php echo esc_html( count( $apprex_os['screens'] ) ); ?>画面・機能<?php echo esc_html( count( $apprex_os['features'] ) ); ?>）<br>
			<?php echo esc_html( $apprex_os['summary'] ); ?><br>
			<a href="<?php echo esc_url( apprex_demo_url( apprex_demo_token( $user->ID ), array( 'v' => 'orig' ) ) ); ?>" target="_blank" rel="noopener">試作を開く</a>
			<p class="description">仕様定義書は、会員がAIスタジオから相談すると「相談内容」に要約が届きます。</p>
		</td></tr>
		<?php endif; ?>
		<tr><th>AIデザイン画像</th><td>
			<?php
			$imgs = apprex_studio_images_out( $user->ID );
			if ( ! $imgs ) {
				echo '（まだありません）';
			}
			foreach ( $imgs as $im ) {
				printf( '<a href="%1$s" target="_blank" rel="noopener" style="display:inline-block;margin:0 8px 8px 0;text-align:center;font-size:11px"><img src="%1$s" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:%3$s"><br>%2$s</a>', esc_url( $im['url'] ), esc_html( $im['label'] . ( $im['picked'] ? '（採用）' : '' ) ), $im['picked'] ? '3px solid #ff6b1f' : '1px solid #dcdcde' );
			}
			?>
		</td></tr>
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

/* -------------------------------------------------------------------------
 * AIデザイン（OpenRouter：文章モデル＋画像モデル）
 * ---------------------------------------------------------------------- */

/** 1人あたり1日に生成できる画像の数（設定 > APPREX チャットのキーを使用）。 */
function apprex_studio_image_limit() {
	return (int) apply_filters( 'apprex_studio_image_limit', 10 );
}

/** 本日の残り回数。 */
function apprex_studio_image_left( $user_id ) {
	$u = get_user_meta( $user_id, 'apprex_studio_img_usage', true );
	$n = ( is_array( $u ) && isset( $u['d'] ) && wp_date( 'Ymd' ) === $u['d'] ) ? (int) $u['n'] : 0;
	return max( 0, apprex_studio_image_limit() - $n );
}

/** 生成画像の一覧（保存形式）。 */
function apprex_studio_images( $user_id ) {
	$m = get_user_meta( $user_id, 'apprex_studio_images', true );
	return is_array( $m ) ? $m : array();
}

/** 画面表示用の生成画像一覧（新しい順）。 */
function apprex_studio_images_out( $user_id ) {
	$labels = array( 'screens' => '画面デザイン', 'icon' => 'アプリアイコン', 'hero' => 'メインビジュアル' );
	$out    = array();
	foreach ( array_reverse( apprex_studio_images( $user_id ) ) as $im ) {
		$url = wp_get_attachment_url( (int) $im['id'] );
		if ( ! $url ) {
			continue;
		}
		$out[] = array(
			'id'     => (int) $im['id'],
			'url'    => $url,
			'kind'   => $im['kind'],
			'label'  => isset( $labels[ $im['kind'] ] ) ? $labels[ $im['kind'] ] : 'デザイン',
			'note'   => (string) $im['note'],
			'time'   => wp_date( 'n/j H:i', (int) $im['t'] ),
			'picked' => ! empty( $im['picked'] ),
		);
	}
	return $out;
}

/**
 * AIの応答からJSONを取り出す。
 *
 * @param string $text 応答本文。
 * @return array|null
 */
function apprex_studio_json( $text ) {
	$text = trim( (string) $text );
	$text = preg_replace( '/^```(?:json)?|```$/m', '', $text );
	$a    = strpos( $text, '{' );
	$b    = strrpos( $text, '}' );
	if ( false === $a || false === $b ) {
		return null;
	}
	$data = json_decode( substr( $text, $a, $b - $a + 1 ), true );
	return is_array( $data ) ? $data : null;
}

/** デザインの要約（AIに渡す現在の設定）。 */
function apprex_studio_brief( WP_REST_Request $request ) {
	$b    = (array) $request->get_param( 'brief' );
	$keep = array( 'industry', 'name', 'goal', 'target', 'tone', 'color', 'dark', 'catch', 'menus', 'pages', 'variant', 'plan', 'request' );
	$out  = array();
	foreach ( $keep as $k ) {
		if ( ! isset( $b[ $k ] ) ) {
			continue;
		}
		$out[ $k ] = is_array( $b[ $k ] )
			? array_slice( array_map( 'sanitize_text_field', $b[ $k ] ), 0, 8 )
			: mb_substr( sanitize_text_field( (string) $b[ $k ] ), 0, 'request' === $k ? 400 : 80 );
	}
	if ( isset( $out['plan'] ) && ! in_array( $out['plan'], array( 'trial', 'start', 'biz' ), true ) ) {
		unset( $out['plan'] );
	}
	return $out;
}

/**
 * 言葉の要望 → デザイン設定の変更（AIデザイナー）。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_ai_design( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	if ( ! apprex_ai_text_ready() ) {
		return rest_ensure_response( array( 'ok' => true, 'fallback' => true ) );
	}
	$brief  = apprex_studio_brief( $request );
	$system = "あなたは日本のモバイルアプリ制作会社のUIデザイナーです。お客様の要望を読み、店舗・企業アプリのデザイン設定を変更します。\n"
		. "必ずJSONだけを出力してください（説明文やコードブロックは不要）。形式：\n"
		. '{"reply":"変更内容を日本語で1〜2文","note":"自動で反映できない要望の要約（無ければ空）","changes":{"color":"#RRGGBB または null","tone":"luxury|pop|natural|sporty|trust または null","variant":"A(グリッド)|B(リスト)|C(大きな写真) または null","cols":"2|3 または null","fontScale":"1|1.15 または null","bigPhoto":"true|false|null","bigReserve":"true|false|null","catch":"ホームのキャッチコピー（全角24字以内）または null","menus":"ホームのボタン6個の表示名（各8字以内）の配列。pages の同じ順番のページの役割を変えずに、その店らしい言い方にする。または null"}}' . "\n"
		. "要望に関係しない項目は null。色は業種と雰囲気に合う具体的なHEXを選ぶ。キャッチコピーやボタン名は、その店らしい自然な日本語にする。";
	$res = apprex_studio_complete(
		array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => '現在のデザイン：' . wp_json_encode( $brief, JSON_UNESCAPED_UNICODE ) ),
		),
		array( 'temperature' => 0.5, 'max_tokens' => 500 )
	);
	if ( is_wp_error( $res ) ) {
		return rest_ensure_response( array( 'ok' => true, 'fallback' => true, 'error' => $res->get_error_message() ) );
	}
	$data = apprex_studio_json( $res );
	if ( ! $data ) {
		return rest_ensure_response( array( 'ok' => true, 'fallback' => true ) );
	}
	$c   = isset( $data['changes'] ) && is_array( $data['changes'] ) ? $data['changes'] : array();
	$out = array();
	if ( ! empty( $c['color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $c['color'] ) ) {
		$out['color'] = strtolower( $c['color'] );
	}
	if ( ! empty( $c['tone'] ) && in_array( $c['tone'], array( 'luxury', 'pop', 'natural', 'sporty', 'trust' ), true ) ) {
		$out['tone'] = $c['tone'];
	}
	if ( ! empty( $c['variant'] ) && in_array( strtoupper( substr( (string) $c['variant'], 0, 1 ) ), array( 'A', 'B', 'C' ), true ) ) {
		$out['variant'] = strtoupper( substr( (string) $c['variant'], 0, 1 ) );
	}
	if ( isset( $c['cols'] ) && in_array( (int) $c['cols'], array( 2, 3 ), true ) ) {
		$out['cols'] = (int) $c['cols'];
	}
	if ( isset( $c['fontScale'] ) && null !== $c['fontScale'] ) {
		$out['fs'] = ( (float) $c['fontScale'] > 1.05 ) ? 1.15 : 1;
	}
	foreach ( array( 'bigPhoto' => 'tall', 'bigReserve' => 'resBig' ) as $k => $to ) {
		if ( isset( $c[ $k ] ) && null !== $c[ $k ] && 'null' !== $c[ $k ] ) {
			$out[ $to ] = filter_var( $c[ $k ], FILTER_VALIDATE_BOOLEAN );
		}
	}
	if ( ! empty( $c['catch'] ) && is_string( $c['catch'] ) ) {
		$out['catch'] = mb_substr( sanitize_text_field( $c['catch'] ), 0, 28 );
	}
	if ( ! empty( $c['menus'] ) && is_array( $c['menus'] ) ) {
		$m = array_values( array_filter( array_map( function ( $x ) {
			return mb_substr( sanitize_text_field( (string) $x ), 0, 10 );
		}, $c['menus'] ) ) );
		if ( count( $m ) >= 4 ) {
			$out['menus'] = array_slice( $m, 0, 6 );
		}
	}
	return rest_ensure_response(
		array(
			'ok'      => true,
			'reply'   => mb_substr( sanitize_text_field( isset( $data['reply'] ) ? (string) $data['reply'] : '' ), 0, 200 ),
			'note'    => mb_substr( sanitize_text_field( isset( $data['note'] ) ? (string) $data['note'] : '' ), 0, 200 ),
			'changes' => $out,
		)
	);
}

/**
 * 画像生成の指示文（日本語UIの本格的なデザインカンプ）。
 *
 * @param string $kind  screens|icon|hero。
 * @param array  $brief 要約。
 * @return string
 */
function apprex_studio_image_prompt( $kind, $brief ) {
	$tones = array(
		'luxury'  => 'luxurious, elegant, refined serif headings, generous whitespace, subtle gold accents',
		'pop'     => 'cute and playful, rounded shapes, friendly illustrations, soft shadows',
		'natural' => 'natural and warm, soft earthy palette, organic textures, calm',
		'sporty'  => 'sporty and energetic, bold typography, dynamic diagonal shapes',
		'trust'   => 'clean, trustworthy, professional, clear hierarchy, medical-grade clarity',
	);
	$g     = function ( $k, $d = '' ) use ( $brief ) {
		return isset( $brief[ $k ] ) && '' !== $brief[ $k ] ? $brief[ $k ] : $d;
	};
	$tone  = isset( $tones[ $g( 'tone' ) ] ) ? $tones[ $g( 'tone' ) ] : $tones['trust'];
	$menus = isset( $brief['menus'] ) && is_array( $brief['menus'] ) ? implode( ' / ', $brief['menus'] ) : '';
	$base  = sprintf(
		'Business type: %s. App/brand name: "%s". Catch copy: "%s". Target users: %s. Goal: %s. Primary color %s, dark color %s. Mood: %s.',
		$g( 'industry', 'shop' ),
		$g( 'name', 'APP' ),
		$g( 'catch' ),
		$g( 'target' ),
		$g( 'goal' ),
		$g( 'color', '#2f66f5' ),
		$g( 'dark', '#13306a' ),
		$tone
	);
	$req = $g( 'request' ) ? ' Additional request from the client (Japanese): ' . $g( 'request' ) . '.' : '';
	if ( 'icon' === $kind ) {
		return 'Design a high-quality mobile app icon (iOS style rounded square, 1:1, centered, no text except at most one Latin letter or simple symbol, no mockup frame, flat background). ' . $base . $req;
	}
	if ( 'hero' === $kind ) {
		return 'Create a photorealistic, high-end key visual for the top banner of this mobile app (landscape 16:9, no text, no UI, appetizing/attractive product or interior photography matching the business). ' . $base . $req;
	}
	// 2画面目は選んだプランに合わせる（スタート＝電子カタログ、ビジネス＝ネットショップと店舗切り替え）。
	$second = array(
		'start' => 'digital catalog shelf with brochure covers to flip through',
		'biz'   => 'online shop product list with add-to-cart buttons and a store switcher for multiple branches',
	);
	$second = isset( $second[ $g( 'plan' ) ] ) ? $second[ $g( 'plan' ) ] : 'menu/price list';
	return 'Create a professional, high-fidelity mobile app UI design presentation: five iPhone screens side by side in front view on a light neutral background — 1) home with hero image, catch copy and 6 large menu buttons (' . $menus . '), 2) ' . $second . ', 3) coupons, 4) reservation calendar with time slots, 5) digital membership card with points. Japanese UI text, consistent design system, polished like a top Dribbble shot, crisp typography, bottom tab bar. ' . $base . $req;
}

/**
 * AIデザイン画像の生成。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_ai_image( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	$uid  = get_current_user_id();
	$kind = sanitize_key( (string) $request->get_param( 'kind' ) );

	// 採用（生成はしない）。
	$pick = (int) $request->get_param( 'pick' );
	if ( $pick ) {
		$list = apprex_studio_images( $uid );
		foreach ( $list as &$im ) {
			if ( (int) $im['id'] === $pick ) {
				$im['picked'] = empty( $im['picked'] );
			} elseif ( $im['kind'] === $kind ) {
				$im['picked'] = false; // 種類ごとに1枚だけ採用。
			}
		}
		unset( $im );
		update_user_meta( $uid, 'apprex_studio_images', $list );
		return rest_ensure_response( array( 'ok' => true, 'images' => apprex_studio_images_out( $uid ) ) );
	}

	if ( ! in_array( $kind, array( 'screens', 'icon', 'hero' ), true ) ) {
		$kind = 'screens';
	}
	if ( '' === apprex_openrouter_key() ) {
		return new WP_Error( 'not_configured', 'AI画像の生成は準備中です。担当者が設定すると使えるようになります。', array( 'status' => 503 ) );
	}
	if ( apprex_studio_image_left( $uid ) <= 0 ) {
		return new WP_Error( 'limit', '本日の生成回数の上限に達しました。明日またお試しください。', array( 'status' => 429 ) );
	}
	// 同じ会員の同時生成を防ぐ。
	$lock = 'apprex_img_lock_' . $uid;
	if ( get_transient( $lock ) ) {
		return new WP_Error( 'busy', '生成中です。完了までお待ちください。', array( 'status' => 429 ) );
	}
	set_transient( $lock, 1, 3 * MINUTE_IN_SECONDS );

	$brief = apprex_studio_brief( $request );
	$img   = apprex_openrouter_image( apprex_studio_image_prompt( $kind, $brief ) );
	delete_transient( $lock );
	if ( is_wp_error( $img ) ) {
		return new WP_Error( 'failed', 'デザインを生成できませんでした。少し時間をおいてもう一度お試しください。', array( 'status' => 502 ) );
	}
	$att = apprex_save_generated_image( $img, 'AIスタジオ-' . $uid . '-' . $kind );
	if ( is_wp_error( $att ) ) {
		return new WP_Error( 'failed', '画像を保存できませんでした。', array( 'status' => 500 ) );
	}
	update_post_meta( $att, '_apprex_studio_user', $uid );

	$u = get_user_meta( $uid, 'apprex_studio_img_usage', true );
	$n = ( is_array( $u ) && isset( $u['d'] ) && wp_date( 'Ymd' ) === $u['d'] ) ? (int) $u['n'] : 0;
	update_user_meta( $uid, 'apprex_studio_img_usage', array( 'd' => wp_date( 'Ymd' ), 'n' => $n + 1 ) );

	$list   = apprex_studio_images( $uid );
	$list[] = array( 'id' => $att, 'kind' => $kind, 'note' => isset( $brief['request'] ) ? $brief['request'] : '', 't' => time(), 'picked' => false );
	update_user_meta( $uid, 'apprex_studio_images', array_slice( $list, -30 ) );

	return rest_ensure_response(
		array(
			'ok'     => true,
			'images' => apprex_studio_images_out( $uid ),
			'left'   => apprex_studio_image_left( $uid ),
		)
	);
}

/**
 * 企画書の文章をAIが作成。
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_studio_rest_ai_plan( WP_REST_Request $request ) {
	if ( ! apprex_studio_check_nonce( $request ) ) {
		return new WP_Error( 'forbidden', '再読み込みしてください。', array( 'status' => 403 ) );
	}
	if ( ! apprex_ai_text_ready() ) {
		return new WP_Error( 'not_configured', 'AIによる文章作成は準備中です。', array( 'status' => 503 ) );
	}
	$brief = apprex_studio_brief( $request );
	$feats = array_slice( array_map( 'sanitize_text_field', (array) $request->get_param( 'features' ) ), 0, 20 );
	$res   = apprex_studio_complete(
		array(
			array(
				'role'    => 'system',
				'content' => "あなたはアプリ制作会社の企画担当です。店舗・企業アプリの企画書の本文を、社内稟議にそのまま使える自然で具体的な日本語で書きます。誇張や数値の断定は避けます。JSONだけを出力：\n"
					. '{"background":"背景と目的（120〜180字）","target":"対象とするお客様（80〜120字）","concept":"コンセプトの説明（60〜100字）","effects":[{"feature":"機能名","effect":"期待する効果（40字以内）"}],"kpi":["効果を測る指標（5個まで）"],"promo":["アプリを広めるための施策（4個まで）"]}',
			),
			array( 'role' => 'user', 'content' => wp_json_encode( array( 'design' => $brief, 'features' => $feats ), JSON_UNESCAPED_UNICODE ) ),
		),
		array( 'temperature' => 0.6, 'max_tokens' => 1200, 'timeout' => 60 )
	);
	$data = is_wp_error( $res ) ? null : apprex_studio_json( $res );
	if ( ! $data ) {
		return new WP_Error( 'failed', '企画書を作成できませんでした。もう一度お試しください。', array( 'status' => 502 ) );
	}
	$t   = function ( $k, $n ) use ( $data ) {
		return isset( $data[ $k ] ) ? mb_substr( sanitize_textarea_field( (string) $data[ $k ] ), 0, $n ) : '';
	};
	$arr = function ( $k, $n ) use ( $data ) {
		return isset( $data[ $k ] ) && is_array( $data[ $k ] ) ? array_slice( array_map( function ( $x ) {
			return mb_substr( sanitize_text_field( is_string( $x ) ? $x : '' ), 0, 60 );
		}, $data[ $k ] ), 0, $n ) : array();
	};
	$eff = array();
	if ( isset( $data['effects'] ) && is_array( $data['effects'] ) ) {
		foreach ( array_slice( $data['effects'], 0, 12 ) as $e ) {
			if ( is_array( $e ) && ! empty( $e['feature'] ) ) {
				$eff[] = array( mb_substr( sanitize_text_field( (string) $e['feature'] ), 0, 30 ), mb_substr( sanitize_text_field( (string) ( isset( $e['effect'] ) ? $e['effect'] : '' ) ), 0, 60 ) );
			}
		}
	}
	return rest_ensure_response(
		array(
			'ok'   => true,
			'plan' => array(
				'background' => $t( 'background', 300 ),
				'target'     => $t( 'target', 200 ),
				'concept'    => $t( 'concept', 160 ),
				'effects'    => $eff,
				'kpi'        => array_filter( $arr( 'kpi', 5 ) ),
				'promo'      => array_filter( $arr( 'promo', 4 ) ),
			),
		)
	);
}

