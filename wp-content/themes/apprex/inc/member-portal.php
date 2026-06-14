<?php
/**
 * 会員ポータル（フェーズD）。
 *
 * 契約者がログインして、契約・支払い・支払い期日・会員情報・アプリ製作ページを
 * 一元的に確認できるマイページ。
 *
 * - ショートコード [apprex_mypage]（自動で「マイページ」固定ページを作成）
 * - 会員アカウント = WordPressユーザー（subscriber）。契約とメール/ユーザーIDで紐付け
 * - 契約編集画面から「会員アカウントを発行」→ パスワード設定リンクをメール送信
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * マイページ固定ページの自動作成
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( get_option( 'apprex_mypage_created' ) ) {
		return;
	}
	if ( ! get_page_by_path( 'mypage' ) ) {
		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'マイページ',
				'post_name'    => 'mypage',
				'post_content' => '[apprex_mypage]',
			)
		);
	}
	update_option( 'apprex_mypage_created', 1 );
} );

/** マイページURL。 */
function apprex_mypage_url() {
	$p = get_page_by_path( 'mypage' );
	return $p ? get_permalink( $p ) : home_url( '/mypage/' );
}

/* -------------------------------------------------------------------------
 * 契約とユーザーの紐付け
 * ---------------------------------------------------------------------- */

/**
 * ログインユーザーに紐づく契約ID一覧（ユーザーID または メール一致）。
 *
 * @param WP_User $user ユーザー。
 * @return int[]
 */
function apprex_member_contracts( $user ) {
	$base = array(
		'post_type'      => 'apprex_contract',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'fields'         => 'ids',
	);
	$by_id = get_posts( array_merge( $base, array( 'meta_key' => 'apprex_c_user_id', 'meta_value' => $user->ID ) ) );
	$by_em = get_posts( array_merge( $base, array( 'meta_key' => 'apprex_c_email', 'meta_value' => $user->user_email ) ) );
	return array_values( array_unique( array_merge( $by_id, $by_em ) ) );
}

/* -------------------------------------------------------------------------
 * ショートコード [apprex_mypage]
 * ---------------------------------------------------------------------- */
add_shortcode( 'apprex_mypage', 'apprex_mypage_render' );

/* -------------------------------------------------------------------------
 * 会員ログイン処理（マイページ内で完結＝wp-login.php に依存しない）
 * ---------------------------------------------------------------------- */

/** マイページから送信されたログインを処理（出力前に実行＝Cookieを確実にセット）。 */
add_action( 'template_redirect', function () {
	if ( empty( $_POST['apprex_member_login'] ) ) {
		return;
	}
	if ( ! isset( $_POST['apprex_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_login_nonce'] ) ), 'apprex_member_login' ) ) {
		$GLOBALS['apprex_login_error'] = 'ページの有効期限が切れました。もう一度お試しください。';
		return;
	}
	$creds = array(
		'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
		'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
		'remember'      => ! empty( $_POST['rememberme'] ),
	);
	if ( '' === $creds['user_login'] || '' === $creds['user_password'] ) {
		$GLOBALS['apprex_login_error'] = 'メールアドレスとパスワードを入力してください。';
		return;
	}
	$user = wp_signon( $creds, is_ssl() );
	if ( is_wp_error( $user ) ) {
		$GLOBALS['apprex_login_error'] = 'メールアドレスまたはパスワードが正しくありません。';
		return;
	}
	wp_set_current_user( $user->ID );
	wp_safe_redirect( apprex_mypage_url() );
	exit;
}, 1 );

/** 会員（管理者以外）は wp-login.php 経由でも必ずマイページに着地させる。 */
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		return $redirect_to;
	}
	$roles = (array) $user->roles;
	if ( in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true ) ) {
		return $redirect_to;
	}
	return apprex_mypage_url();
}, 10, 3 );

/** 会員（subscriber）が wp-admin に入ってしまったらマイページへ戻す（プロフィール編集・Ajaxは除く）。 */
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() || ! is_user_logged_in() ) {
		return;
	}
	$user = wp_get_current_user();
	$roles = (array) $user->roles;
	if ( in_array( 'administrator', $roles, true ) || in_array( 'editor', $roles, true ) || current_user_can( 'edit_posts' ) ) {
		return;
	}
	wp_safe_redirect( apprex_mypage_url() );
	exit;
} );

/** マイページ本体を描画。 */
function apprex_mypage_render() {
	ob_start();

	if ( ! is_user_logged_in() ) {
		$err    = isset( $GLOBALS['apprex_login_error'] ) ? $GLOBALS['apprex_login_error'] : '';
		$action = esc_url( apprex_mypage_url() );
		$nonce  = wp_create_nonce( 'apprex_member_login' );
		echo '<div class="apprex-login" style="max-width:420px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px 26px;box-shadow:0 2px 10px rgba(0,0,0,.05);">';
		echo '<h2 style="text-align:center;margin:0 0 6px;font-size:22px;">会員ログイン</h2>';
		echo '<p style="text-align:center;color:#6b7280;font-size:14px;margin:0 0 18px;">ご契約者様向けのマイページです。<br>発行されたメールアドレスとパスワードでログインしてください。</p>';
		if ( $err ) {
			echo '<p style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 12px;border-radius:8px;font-size:14px;margin:0 0 14px;">' . esc_html( $err ) . '</p>';
		}
		echo '<form method="post" action="' . $action . '">';
		echo '<input type="hidden" name="apprex_member_login" value="1">';
		echo '<input type="hidden" name="apprex_login_nonce" value="' . esc_attr( $nonce ) . '">';
		echo '<label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">メールアドレス</label>';
		echo '<input type="text" name="log" autocomplete="username" required style="width:100%;min-height:46px;padding:0 14px;border:1px solid #d1d5db;border-radius:8px;margin-bottom:14px;font-size:15px;">';
		echo '<label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">パスワード</label>';
		echo '<input type="password" name="pwd" autocomplete="current-password" required style="width:100%;min-height:46px;padding:0 14px;border:1px solid #d1d5db;border-radius:8px;margin-bottom:12px;font-size:15px;">';
		echo '<label style="display:flex;align-items:center;gap:6px;font-size:14px;color:#374151;margin-bottom:16px;"><input type="checkbox" name="rememberme" value="1" checked> ログイン状態を保持する</label>';
		echo '<button type="submit" style="width:100%;min-height:48px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;">ログイン</button>';
		echo '</form>';
		echo '<p style="text-align:center;margin:14px 0 0;font-size:14px;"><a href="' . esc_url( wp_lostpassword_url( apprex_mypage_url() ) ) . '">パスワードをお忘れの方はこちら</a></p>';
		echo '</div>';
		return ob_get_clean();
	}

	$user = wp_get_current_user();
	$ids  = apprex_member_contracts( $user );

	echo '<div class="apprex-mypage" style="max-width:760px;margin:0 auto;">';
	echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">';
	echo '<h2 style="margin:0;">マイページ</h2>';
	echo '<span style="color:#6b7280;">' . esc_html( $user->display_name ) . ' 様　<a href="' . esc_url( wp_logout_url( apprex_mypage_url() ) ) . '">ログアウト</a></span>';
	echo '</div>';

	if ( empty( $ids ) ) {
		echo '<p style="margin-top:20px;">現在ご契約情報が見つかりません。お手数ですが担当者までお問い合わせください。</p>';
		echo '</div>';
		return ob_get_clean();
	}

	foreach ( $ids as $id ) {
		echo apprex_member_contract_card( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 内部でエスケープ済み。
	}

	echo '<p style="margin-top:24px;font-size:13px;color:#9ca3af;">登録情報の変更・ご解約などは、担当者またはお問い合わせ窓口へご連絡ください。</p>';
	echo '</div>';
	return ob_get_clean();
}

/**
 * 契約1件分のカードHTML。
 *
 * @param int $id 契約ID。
 * @return string
 */
function apprex_member_contract_card( $id ) {
	$m = function ( $k ) use ( $id ) {
		return (string) get_post_meta( $id, $k, true );
	};
	$statuses = array( 'active' => '契約中', 'pending' => '更新待ち', 'cancelled' => '解約' );
	$status   = $m( 'apprex_c_status' );
	$slabel   = isset( $statuses[ $status ] ) ? $statuses[ $status ] : '契約中';
	$scolor   = 'active' === $status ? '#16a34a' : ( 'pending' === $status ? '#d97706' : '#6b7280' );
	$method   = 'invoice' === $m( 'apprex_c_payment_method' ) ? '請求書（振込）' : 'Square（自動課金）';
	$app_url  = $m( 'apprex_c_app_url' );

	$row = function ( $label, $value ) {
		if ( '' === (string) $value ) {
			$value = '—';
		}
		return '<tr><th style="text-align:left;padding:8px 10px;background:#f8fafc;border:1px solid #e5e7eb;width:42%;color:#374151;font-weight:600;">' . esc_html( $label ) . '</th>'
			. '<td style="padding:8px 10px;border:1px solid #e5e7eb;color:#111827;">' . esc_html( $value ) . '</td></tr>';
	};

	ob_start();
	?>
	<div style="border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin:18px 0;box-shadow:0 1px 3px rgba(0,0,0,.05);">
		<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
			<h3 style="margin:0;font-size:18px;"><?php echo esc_html( trim( $m( 'apprex_c_service' ) . ' ' . $m( 'apprex_c_plan' ) ) ); ?></h3>
			<span style="background:<?php echo esc_attr( $scolor ); ?>;color:#fff;padding:3px 12px;border-radius:999px;font-size:13px;"><?php echo esc_html( $slabel ); ?></span>
		</div>

		<?php if ( $app_url ) : ?>
			<a href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:bold;margin-bottom:16px;">アプリ製作ページを開く →</a>
		<?php endif; ?>

		<h4 style="margin:14px 0 6px;color:#374151;">会員情報</h4>
		<table style="border-collapse:collapse;width:100%;font-size:14px;">
			<?php
			echo $row( 'お名前', $m( 'apprex_c_name' ) ); // phpcs:ignore
			echo $row( '会社名', $m( 'apprex_c_company' ) ); // phpcs:ignore
			echo $row( 'メール', $m( 'apprex_c_email' ) ); // phpcs:ignore
			?>
		</table>

		<?php
		$app_login = $m( 'apprex_c_app_login' );
		$app_pass  = $m( 'apprex_c_app_pass' );
		if ( $app_login || $app_pass || $app_url ) :
			?>
			<h4 style="margin:14px 0 6px;color:#374151;">アプリログイン情報</h4>
			<table style="border-collapse:collapse;width:100%;font-size:14px;">
				<?php
				echo $row( 'ログインID', $app_login ); // phpcs:ignore
				echo $row( 'パスワード', $app_pass ); // phpcs:ignore
				if ( $app_url ) {
					echo $row( 'アプリ製作ページ', $app_url ); // phpcs:ignore
				}
				?>
			</table>
		<?php endif; ?>

			<h4 style="margin:14px 0 6px;color:#374151;">契約書</h4>
			<?php
			$mf_status = $m( 'apprex_c_mf_status' );
			$mf_url    = $m( 'apprex_c_mf_url' );
			$mf_pdf    = $m( 'apprex_c_mf_signed_pdf' );
			$mf_label  = function_exists( 'apprex_mf_status_label' ) ? apprex_mf_status_label( $mf_status ) : '';
			$mf_color  = 'signed' === $mf_status ? '#16a34a' : ( 'sent' === $mf_status ? '#d97706' : '#6b7280' );
			$doc_url   = function_exists( 'apprex_contract_doc_url' ) ? apprex_contract_doc_url( $id ) : '';
			?>
			<table style="border-collapse:collapse;width:100%;font-size:14px;">
				<tr>
					<th style="text-align:left;padding:8px 10px;background:#f8fafc;border:1px solid #e5e7eb;width:42%;color:#374151;font-weight:600;">締結状況</th>
					<td style="padding:8px 10px;border:1px solid #e5e7eb;">
						<span style="display:inline-block;background:<?php echo esc_attr( $mf_color ); ?>;color:#fff;padding:2px 10px;border-radius:999px;font-size:13px;"><?php echo esc_html( $mf_label ? $mf_label : '未送付' ); ?></span>
					</td>
				</tr>
			</table>
			<div style="margin:10px 0 4px;display:flex;flex-wrap:wrap;gap:8px;">
				<?php if ( $doc_url ) : ?>
					<a href="<?php echo esc_url( $doc_url ); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#374151;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;">契約書を表示（PDF保存可）</a>
				<?php endif; ?>
				<?php if ( 'signed' !== $mf_status && $mf_url ) : ?>
					<a href="<?php echo esc_url( $mf_url ); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold;font-size:14px;">マネーフォワードで締結する →</a>
				<?php endif; ?>
				<?php if ( $mf_pdf ) : ?>
					<a href="<?php echo esc_url( $mf_pdf ); ?>" target="_blank" rel="noopener" style="display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;">署名済み契約書（PDF）</a>
				<?php endif; ?>
			</div>

		<h4 style="margin:14px 0 6px;color:#374151;">契約内容</h4>
		<table style="border-collapse:collapse;width:100%;font-size:14px;">
			<?php
			echo $row( 'プラン', trim( $m( 'apprex_c_service' ) . ' ' . $m( 'apprex_c_plan' ) ) ); // phpcs:ignore
			echo $row( '月額（税抜）', '¥' . number_format( (int) $m( 'apprex_c_monthly' ) ) ); // phpcs:ignore
			echo $row( '契約開始日', $m( 'apprex_c_start' ) ); // phpcs:ignore
			echo $row( '契約年数', $m( 'apprex_c_term' ) ? $m( 'apprex_c_term' ) . '年' : '' ); // phpcs:ignore
			echo $row( '次回更新日', $m( 'apprex_c_renewal' ) ); // phpcs:ignore
			?>
		</table>

		<h4 style="margin:14px 0 6px;color:#374151;">お支払い</h4>
		<table style="border-collapse:collapse;width:100%;font-size:14px;">
			<?php
			echo $row( '支払い方法', $method ); // phpcs:ignore
			echo $row( '支払い期日', $m( 'apprex_c_payment_day' ) ? '毎月' . $m( 'apprex_c_payment_day' ) . '日' : '' ); // phpcs:ignore
			echo $row( '最終入金確認日', $m( 'apprex_c_last_paid' ) ); // phpcs:ignore
			?>
		</table>
	</div>
	<?php
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * 契約編集画面：会員アカウント発行
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_member_account', '会員ログイン', 'apprex_member_account_box', 'apprex_contract', 'side', 'default' );
} );

/**
 * 会員アカウント発行メタボックス。
 *
 * @param WP_Post $post 契約。
 */
function apprex_member_account_box( $post ) {
	$uid   = (int) get_post_meta( $post->ID, 'apprex_c_user_id', true );
	$email = get_post_meta( $post->ID, 'apprex_c_email', true );

	if ( $uid && get_userdata( $uid ) ) {
		$u = get_userdata( $uid );
		echo '<p>会員アカウント発行済み：<br><strong>' . esc_html( $u->user_email ) . '</strong></p>';
		echo '<p>マイページ：<a href="' . esc_url( apprex_mypage_url() ) . '" target="_blank">' . esc_html( apprex_mypage_url() ) . '</a></p>';
	} else {
		echo '<p>この契約のメール宛に会員ログインを発行します。初期パスワードを自動発行し、ログイン情報をメール送信＋この画面にも表示します。</p>';
	}
	if ( ! $email ) {
		echo '<p style="color:#b91c1c;">先にメールアドレスを入力・保存してください。</p>';
		return;
	}
	$label = ( $uid && get_userdata( $uid ) ) ? 'ログイン情報を再送する' : '会員アカウントを発行する';
	$url   = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_issue_member&contract=' . $post->ID ), 'apprex_issue_member' );
	echo '<a href="' . esc_url( $url ) . '" class="button button-primary" style="width:100%;text-align:center;">' . esc_html( $label ) . '</a>';
}

/** 会員アカウント発行 / 再送の実処理。 */
add_action( 'admin_post_apprex_issue_member', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_issue_member' );
	$cid = isset( $_GET['contract'] ) ? absint( $_GET['contract'] ) : 0;
	if ( ! $cid || 'apprex_contract' !== get_post_type( $cid ) ) {
		wp_die( '対象の契約が見つかりません。' );
	}

	$email = get_post_meta( $cid, 'apprex_c_email', true );
	$name  = get_post_meta( $cid, 'apprex_c_name', true );
	if ( ! is_email( $email ) ) {
		wp_die( 'メールアドレスが正しくありません。' );
	}

	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		$uid = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 20 ),
				'display_name' => $name ? $name : $email,
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $uid ) ) {
			wp_die( '会員アカウントの作成に失敗しました：' . esc_html( $uid->get_error_message() ) );
		}
		$user = get_userdata( $uid );
	}
	update_post_meta( $cid, 'apprex_c_user_id', $user->ID );

	// 初期パスワードを発行して即ログインできる状態にする（メール不達でも管理者が手渡し可能）。
	$initial_pass = wp_generate_password( 12, false );
	wp_set_password( $initial_pass, $user->ID );

	// 管理画面に1回だけ表示するため一時保存。
	set_transient(
		'apprex_member_cred_' . $cid,
		array( 'email' => $email, 'pass' => $initial_pass, 'login' => $user->user_login ),
		180
	);

	// 会員へ案内メール（ログインID＋初期パスワード＋マイページURL）。
	$mypage = apprex_mypage_url();
	$body   = "{$name} 様\n\nAPPREX 会員マイページのログインを発行しました。\n下記の情報でログインいただけます。\n\n";
	$body  .= "マイページ：{$mypage}\n";
	$body  .= "ログインID（メール）：{$email}\n";
	$body  .= "初期パスワード：{$initial_pass}\n\n";
	$body  .= "ログイン後、パスワードの変更をおすすめします（マイページ内の案内、または「パスワードをお忘れの方」より再設定できます）。\n";

	$html = function_exists( 'apprex_render_email' )
		? apprex_render_email( '【APPREX】会員マイページのご案内', $body, array( 'heading' => '会員マイページのご案内' ) )
		: nl2br( esc_html( $body ) );
	wp_mail( $email, '【APPREX】会員マイページのご案内', $html, function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );

	wp_safe_redirect( admin_url( 'post.php?post=' . $cid . '&action=edit&apprex_member=sent' ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['apprex_member'] ) || 'sent' !== $_GET['apprex_member'] ) {
		return;
	}
	$cid  = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$cred = $cid ? get_transient( 'apprex_member_cred_' . $cid ) : false;
	echo '<div class="notice notice-success is-dismissible"><p><strong>会員ログインを発行しました。</strong>（同じ内容を会員へメール送信済み）</p>';
	if ( is_array( $cred ) ) {
		echo '<p style="font-family:monospace;background:#f6f7f7;padding:8px;border:1px solid #dcdcde;border-radius:4px;">'
			. 'マイページ：<a href="' . esc_url( apprex_mypage_url() ) . '" target="_blank">' . esc_html( apprex_mypage_url() ) . '</a><br>'
			. 'ログインID：' . esc_html( $cred['email'] ) . '<br>'
			. '初期パスワード：<strong>' . esc_html( $cred['pass'] ) . '</strong></p>';
		echo '<p class="description">※ このパスワードはこの画面でのみ表示されます。控えてお客様にお伝えください（メールが届かない場合の手渡し用）。</p>';
		delete_transient( 'apprex_member_cred_' . $cid );
	}
	echo '</div>';
} );
