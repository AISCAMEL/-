<?php
/**
 * Square 請求書（Invoices API）連携 — 請求書自動発行版。
 *
 * 契約レコードから Square の請求書を発行し、Square がお客様へメール送信します
 * （カード決済リンク付き）。手動ボタン＋毎月の自動発行に対応。
 *
 * 流れ：顧客作成 → 注文(Order)作成 → 請求書(Invoice)作成 → 公開(publish)。
 * 公開すると Square がお客様にメールを送ります。
 *
 * 設定：設定 > APPREX 請求(Square)（アクセストークン / ロケーションID / 環境 / 税率 / 自動発行）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 設定値
 * ---------------------------------------------------------------------- */
function apprex_square_token() {
	if ( defined( 'APPREX_SQUARE_TOKEN' ) && APPREX_SQUARE_TOKEN ) {
		return (string) APPREX_SQUARE_TOKEN;
	}
	return (string) get_option( 'apprex_square_token', '' );
}
function apprex_square_location() {
	return (string) get_option( 'apprex_square_location', '' );
}
function apprex_square_env() {
	return 'sandbox' === get_option( 'apprex_square_env', 'production' ) ? 'sandbox' : 'production';
}
function apprex_square_base() {
	return 'sandbox' === apprex_square_env() ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';
}
function apprex_square_tax_percent() {
	$v = get_option( 'apprex_square_tax', '10' );
	return is_numeric( $v ) ? (string) $v : '10';
}
function apprex_square_enabled() {
	return '' !== apprex_square_token() && '' !== apprex_square_location();
}

/* -------------------------------------------------------------------------
 * Square API リクエスト
 * ---------------------------------------------------------------------- */
function apprex_square_request( $method, $path, $body = null ) {
	$args = array(
		'method'  => $method,
		'timeout' => 20,
		'headers' => array(
			'Authorization'  => 'Bearer ' . apprex_square_token(),
			'Square-Version' => '2024-12-18',
			'Content-Type'   => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$res = wp_remote_request( apprex_square_base() . $path, $args );
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'code' => 0, 'data' => null );
	}
	$code = wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return array( 'ok' => ( $code >= 200 && $code < 300 ), 'code' => $code, 'data' => $data );
}

/** Square エラーの説明文を取り出す。 */
function apprex_square_err( $r ) {
	if ( ! empty( $r['data']['errors'][0]['detail'] ) ) {
		return $r['data']['errors'][0]['detail'];
	}
	return 'Squareエラー（HTTP ' . ( isset( $r['code'] ) ? $r['code'] : '?' ) . '）';
}

/** 支払い期日（今月の指定日。過ぎていれば翌月）。 */
function apprex_square_due_date( $day ) {
	$day = min( 28, max( 1, (int) $day ) );
	$ts  = current_time( 'timestamp' );
	$cand = strtotime( wp_date( 'Y-m-', $ts ) . sprintf( '%02d', $day ) );
	if ( $cand < $ts ) {
		$cand = strtotime( '+1 month', $cand );
	}
	return wp_date( 'Y-m-d', $cand );
}

/* -------------------------------------------------------------------------
 * 顧客 → 注文 → 請求書 → 公開
 * ---------------------------------------------------------------------- */

/** 契約に対応する Square 顧客IDを取得（無ければ作成）。 */
function apprex_square_ensure_customer( $id ) {
	$cid = get_post_meta( $id, 'apprex_c_sq_customer', true );
	if ( $cid ) {
		// 保存済みIDが現在のアカウント/環境に実在するか確認（環境変更で消える場合があるため）。
		$chk = apprex_square_request( 'GET', '/v2/customers/' . rawurlencode( $cid ) );
		if ( $chk['ok'] && ! empty( $chk['data']['customer']['id'] ) ) {
			return $cid;
		}
		// 見つからない → 作り直すため保存値を破棄。
		delete_post_meta( $id, 'apprex_c_sq_customer' );
	}
	$name    = get_post_meta( $id, 'apprex_c_name', true );
	$email   = get_post_meta( $id, 'apprex_c_email', true );
	$company = get_post_meta( $id, 'apprex_c_company', true );
	$r = apprex_square_request(
		'POST',
		'/v2/customers',
		array(
			'idempotency_key' => wp_generate_uuid4(),
			'given_name'      => $name ? $name : $email,
			'email_address'   => $email,
			'company_name'    => $company,
		)
	);
	if ( ! $r['ok'] || empty( $r['data']['customer']['id'] ) ) {
		return new WP_Error( 'sq_customer', '顧客作成に失敗：' . apprex_square_err( $r ) );
	}
	$cid = $r['data']['customer']['id'];
	update_post_meta( $id, 'apprex_c_sq_customer', $cid );
	return $cid;
}

/**
 * 契約に対して Square 請求書を発行・送信する。
 *
 * @param int $id 契約ID。
 * @return array|WP_Error 公開された invoice 配列、またはエラー。
 */
function apprex_square_send_invoice( $id ) {
	if ( ! apprex_square_enabled() ) {
		return new WP_Error( 'sq_disabled', 'Squareが未設定です（設定 > APPREX 請求）。' );
	}
	$email = get_post_meta( $id, 'apprex_c_email', true );
	if ( ! is_email( $email ) ) {
		return new WP_Error( 'sq_email', 'メールアドレスが未設定です。' );
	}
	$monthly = (int) get_post_meta( $id, 'apprex_c_monthly', true );
	if ( $monthly <= 0 ) {
		return new WP_Error( 'sq_amount', '月額が0円です。' );
	}

	$cid = apprex_square_ensure_customer( $id );
	if ( is_wp_error( $cid ) ) {
		return $cid;
	}

	$loc       = apprex_square_location();
	$plan      = trim( get_post_meta( $id, 'apprex_c_service', true ) . ' ' . get_post_meta( $id, 'apprex_c_plan', true ) );
	$line_name = ( $plan ? $plan : 'APPREX' ) . ' 月額利用料';

	// 1) 注文(Order)。JPYは最小単位＝円（小数なし）。
	$order = array(
		'location_id' => $loc,
		'customer_id' => $cid,
		'line_items'  => array(
			array(
				'name'             => mb_substr( $line_name, 0, 500 ),
				'quantity'         => '1',
				'base_price_money' => array( 'amount' => $monthly, 'currency' => 'JPY' ),
			),
		),
	);
	$tax = apprex_square_tax_percent();
	if ( (float) $tax > 0 ) {
		$order['taxes'] = array(
			array( 'uid' => 'tax', 'name' => '消費税', 'percentage' => (string) $tax, 'scope' => 'ORDER' ),
		);
	}
	$ro = apprex_square_request( 'POST', '/v2/orders', array( 'idempotency_key' => wp_generate_uuid4(), 'order' => $order ) );
	if ( ! $ro['ok'] || empty( $ro['data']['order']['id'] ) ) {
		return new WP_Error( 'sq_order', '注文作成に失敗：' . apprex_square_err( $ro ) );
	}
	$order_id = $ro['data']['order']['id'];

	// 2) 請求書(Invoice)。
	$day = (int) get_post_meta( $id, 'apprex_c_payment_day', true );
	$due = apprex_square_due_date( $day ? $day : 27 );
	$invoice = array(
		'location_id'              => $loc,
		'order_id'                 => $order_id,
		'primary_recipient'        => array( 'customer_id' => $cid ),
		'payment_requests'         => array( array( 'request_type' => 'BALANCE', 'due_date' => $due ) ),
		'delivery_method'          => 'EMAIL',
		'accepted_payment_methods' => array( 'card' => true, 'square_gift_card' => false, 'bank_account' => false, 'buy_now_pay_later' => false ),
		'title'                    => 'APPREX ご利用料金',
		'description'              => '今月分のご利用料金です。ご確認のうえお支払いください。',
	);
	$ri = apprex_square_request( 'POST', '/v2/invoices', array( 'idempotency_key' => wp_generate_uuid4(), 'invoice' => $invoice ) );
	if ( ! $ri['ok'] || empty( $ri['data']['invoice']['id'] ) ) {
		return new WP_Error( 'sq_invoice', '請求書作成に失敗：' . apprex_square_err( $ri ) );
	}
	$inv = $ri['data']['invoice'];

	// 3) 公開(publish) → Square がお客様にメール送信。
	$rp = apprex_square_request(
		'POST',
		'/v2/invoices/' . rawurlencode( $inv['id'] ) . '/publish',
		array( 'idempotency_key' => wp_generate_uuid4(), 'version' => (int) $inv['version'] )
	);
	if ( ! $rp['ok'] || empty( $rp['data']['invoice']['id'] ) ) {
		return new WP_Error( 'sq_publish', '請求書の送信(公開)に失敗：' . apprex_square_err( $rp ) );
	}
	$pub = $rp['data']['invoice'];

	update_post_meta( $id, 'apprex_c_sq_invoice_id', $pub['id'] );
	update_post_meta( $id, 'apprex_c_sq_invoice_month', current_time( 'Y-m' ) );
	update_post_meta( $id, 'apprex_c_sq_invoice_status', isset( $pub['status'] ) ? $pub['status'] : '' );
	if ( ! empty( $pub['public_url'] ) ) {
		update_post_meta( $id, 'apprex_c_sq_invoice_url', $pub['public_url'] );
	}
	return $pub;
}

/* -------------------------------------------------------------------------
 * 毎月の自動発行（lifecycle の支払い通知フックに割り込む）
 * ---------------------------------------------------------------------- */
add_filter( 'apprex_send_payment_via_square', function ( $handled, $id ) {
	if ( $handled ) {
		return $handled;
	}
	if ( ! apprex_square_enabled() || ! get_option( 'apprex_square_auto', 0 ) ) {
		return false;
	}
	if ( 'square' !== get_post_meta( $id, 'apprex_c_payment_method', true ) ) {
		return false; // 請求書(振込)の契約は対象外。
	}
	// 二重発行防止：今月すでに発行済みなら通常メールも抑止して終了。
	if ( get_post_meta( $id, 'apprex_c_sq_invoice_month', true ) === current_time( 'Y-m' ) ) {
		return true;
	}
	$res = apprex_square_send_invoice( $id );
	if ( is_wp_error( $res ) ) {
		if ( function_exists( 'apprex_slack_notify' ) ) {
			apprex_slack_notify( ':x: Square請求書の発行に失敗：' . get_post_meta( $id, 'apprex_c_name', true ) . ' / ' . $res->get_error_message() );
		}
		return false; // 失敗時は通常の支払い通知メールにフォールバック。
	}
	if ( function_exists( 'apprex_slack_notify' ) ) {
		apprex_slack_notify( ':money_with_wings: Square請求書を送信：' . get_post_meta( $id, 'apprex_c_name', true ) );
	}
	return true; // 成功時は通常メールを抑止（Squareがメール送信するため）。
}, 10, 2 );

/* -------------------------------------------------------------------------
 * 契約編集画面：Square請求メタボックス＋手動ボタン
 * ---------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_square_box', 'Square請求', 'apprex_square_box', 'apprex_contract', 'side', 'default' );
} );

function apprex_square_box( $post ) {
	if ( ! apprex_square_enabled() ) {
		echo '<p>Squareが未設定です。<br><a href="' . esc_url( admin_url( 'options-general.php?page=apprex-square' ) ) . '">設定する</a></p>';
		return;
	}
	$inv = get_post_meta( $post->ID, 'apprex_c_sq_invoice_id', true );
	$url = get_post_meta( $post->ID, 'apprex_c_sq_invoice_url', true );
	$st  = get_post_meta( $post->ID, 'apprex_c_sq_invoice_status', true );
	$mon = get_post_meta( $post->ID, 'apprex_c_sq_invoice_month', true );
	if ( $inv ) {
		echo '<p>最終発行：<strong>' . esc_html( $mon ) . '</strong>（' . esc_html( $st ) . '）';
		if ( $url ) {
			echo '<br><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">請求書を開く</a>';
		}
		echo '</p>';
	} else {
		echo '<p>まだ請求書を発行していません。</p>';
	}
	$btn = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_square_invoice_now&contract=' . $post->ID ), 'apprex_square_invoice_now' );
	echo '<a href="' . esc_url( $btn ) . '" class="button button-primary" style="width:100%;text-align:center;">今すぐ請求書を送る</a>';
	echo '<p class="description" style="margin-top:8px;">お客様のメールに、Squareから支払いリンク付きの請求書が届きます。先に「メール」「月額」を保存しておいてください。</p>';
}

add_action( 'admin_post_apprex_square_invoice_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_square_invoice_now' );
	$id = isset( $_GET['contract'] ) ? absint( $_GET['contract'] ) : 0;
	if ( ! $id || 'apprex_contract' !== get_post_type( $id ) ) {
		wp_die( '対象の契約が見つかりません。' );
	}
	$res = apprex_square_send_invoice( $id );
	$msg = is_wp_error( $res ) ? 'err:' . $res->get_error_message() : 'ok';
	wp_safe_redirect( add_query_arg( 'apprex_sq', rawurlencode( $msg ), admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( empty( $_GET['apprex_sq'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$m = sanitize_text_field( wp_unslash( $_GET['apprex_sq'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $m ) {
		echo '<div class="notice notice-success is-dismissible"><p>Square請求書を送信しました。お客様のメールをご確認ください。</p></div>';
	} elseif ( 0 === strpos( $m, 'err:' ) ) {
		echo '<div class="notice notice-error is-dismissible"><p>Square請求書の送信に失敗：' . esc_html( substr( $m, 4 ) ) . '</p></div>';
	}
} );

/* -------------------------------------------------------------------------
 * 設定ページ：設定 > APPREX 請求(Square)
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 請求(Square)', 'APPREX 請求(Square)', 'manage_options', 'apprex-square', 'apprex_square_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_square', 'apprex_square_token', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_square', 'apprex_square_location', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_square', 'apprex_square_env', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_square', 'apprex_square_tax', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_square', 'apprex_square_auto', array( 'sanitize_callback' => 'absint' ) );
} );

function apprex_square_settings_page() {
	?>
	<div class="wrap">
		<h1>APPREX 請求（Square 請求書自動発行）</h1>
		<p>契約から Square の請求書を発行し、Square がお客様へメール（カード決済リンク付き）を送ります。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_square' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="apprex_square_token">アクセストークン</label></th>
					<td>
						<?php if ( defined( 'APPREX_SQUARE_TOKEN' ) && APPREX_SQUARE_TOKEN ) : ?>
							<p><em>wp-config.php の APPREX_SQUARE_TOKEN で設定済みです。</em></p>
						<?php else : ?>
							<input type="password" id="apprex_square_token" name="apprex_square_token" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_square_token', '' ) ); ?>" autocomplete="off" placeholder="EAAA…">
						<?php endif; ?>
						<p class="description">Square Developer のアプリ →「Credentials」の Access token（本番は Production 側）。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_square_location">ロケーションID</label></th>
					<td><input type="text" id="apprex_square_location" name="apprex_square_location" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_square_location', '' ) ); ?>" placeholder="L…">
					<p class="description">Square Developer の「Locations」に表示される店舗ID。</p></td>
				</tr>
				<tr>
					<th scope="row">環境</th>
					<td>
						<select name="apprex_square_env">
							<?php $env = apprex_square_env(); ?>
							<option value="production" <?php selected( $env, 'production' ); ?>>本番（Production）</option>
							<option value="sandbox" <?php selected( $env, 'sandbox' ); ?>>テスト（Sandbox）</option>
						</select>
						<p class="description">最初は Sandbox（テスト）で動作確認するのがおすすめです。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_square_tax">消費税率(%)</label></th>
					<td><input type="number" id="apprex_square_tax" name="apprex_square_tax" value="<?php echo esc_attr( get_option( 'apprex_square_tax', '10' ) ); ?>" min="0" max="20" step="1" style="width:80px"> %
					<p class="description">月額は税抜のため、請求書にこの税率を加算します（0 で加算なし）。</p></td>
				</tr>
				<tr>
					<th scope="row">毎月の自動発行</th>
					<td><label><input type="checkbox" name="apprex_square_auto" value="1" <?php checked( 1, (int) get_option( 'apprex_square_auto', 0 ) ); ?>> 支払い方法が「Square」の契約に、毎月自動で請求書を発行する</label>
					<p class="description">支払い期日の数日前（日次バッチ）に、その月の請求書を自動発行します。手動で送りたい場合はOFFのまま、各契約の「今すぐ請求書を送る」をご利用ください。</p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2>接続テスト</h2>
		<?php
		$test_url = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_square_test' ), 'apprex_square_test' );
		?>
		<p>保存後、ボタンでトークン・ロケーションを確認できます。</p>
		<a href="<?php echo esc_url( $test_url ); ?>" class="button">接続をテストする</a>
		<p>状態：<strong><?php echo apprex_square_enabled() ? '✅ 設定あり' : '⛔ 未設定（トークンとロケーションIDが必要）'; ?></strong></p>
	</div>
	<?php
}

add_action( 'admin_post_apprex_square_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_square_test' );

	$loc    = apprex_square_location();
	$result = array(
		'env' => apprex_square_env(),
		'loc' => $loc,
	);
	$r = apprex_square_request( 'GET', '/v2/locations' );
	if ( ! $r['ok'] ) {
		$result['error'] = apprex_square_err( $r );
	} else {
		$locs = array();
		foreach ( (array) ( isset( $r['data']['locations'] ) ? $r['data']['locations'] : array() ) as $L ) {
			$locs[] = array(
				'id'     => isset( $L['id'] ) ? $L['id'] : '',
				'name'   => isset( $L['name'] ) ? $L['name'] : '',
				'status' => isset( $L['status'] ) ? $L['status'] : '',
			);
		}
		$result['locations'] = $locs;
		$result['match']     = in_array( $loc, wp_list_pluck( $locs, 'id' ), true );
	}
	set_transient( 'apprex_sqtest_' . get_current_user_id(), $result, 300 );
	wp_safe_redirect( add_query_arg( 'apprex_sqtest', '1', admin_url( 'options-general.php?page=apprex-square' ) ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( empty( $_GET['apprex_sqtest'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$res = get_transient( 'apprex_sqtest_' . get_current_user_id() );
	if ( ! is_array( $res ) ) {
		return;
	}
	$env_label = 'sandbox' === $res['env'] ? 'テスト（Sandbox）' : '本番（Production）';

	// 1) トークン自体がNG
	if ( isset( $res['error'] ) ) {
		echo '<div class="notice notice-error"><p><strong>Square 接続エラー（環境：' . esc_html( $env_label ) . '）</strong><br>'
			. esc_html( $res['error'] )
			. '<br>→ アクセストークンが正しくない／環境（本番⇄テスト）とトークンが食い違っている可能性があります。</p></div>';
		return;
	}

	$rows = '';
	foreach ( (array) $res['locations'] as $L ) {
		$mark  = ( $L['id'] === $res['loc'] ) ? ' ✅設定中' : '';
		$rows .= '<tr><td><code>' . esc_html( $L['id'] ) . '</code>' . esc_html( $mark ) . '</td><td>' . esc_html( $L['name'] ) . '</td><td>' . esc_html( $L['status'] ) . '</td></tr>';
	}
	$table = '<table class="widefat striped" style="max-width:680px;margin-top:8px;"><thead><tr><th>ロケーションID</th><th>名前</th><th>状態</th></tr></thead><tbody>' . $rows . '</tbody></table>';

	if ( ! empty( $res['match'] ) ) {
		// 2) ロケーション一致 → 認可OK
		echo '<div class="notice notice-success"><p><strong>接続OK（環境：' . esc_html( $env_label ) . '）</strong><br>'
			. 'このトークンで、設定中のロケーション <code>' . esc_html( $res['loc'] ) . '</code> を利用できます。'
			. 'これでも請求作成に失敗する場合は、トークンの権限（ORDERS / INVOICES / CUSTOMERS）をご確認ください。</p>'
			. wp_kses_post( $table ) . '</div>';
	} else {
		// 3) ロケーション不一致 ← 今回のエラーの主因
		echo '<div class="notice notice-error"><p><strong>設定中のロケーションIDが、このトークンでは使えません（環境：' . esc_html( $env_label ) . '）</strong><br>'
			. '設定中：<code>' . esc_html( $res['loc'] ) . '</code> は、このトークンで利用可能な一覧に<strong>含まれていません</strong>。<br>'
			. '→ 下の一覧にある正しいIDに置き換えてください。一覧が空/別の店舗しか出ない場合は、<strong>環境（本番⇄テスト）かアカウントが食い違っています</strong>。</p>'
			. wp_kses_post( $table ) . '</div>';
	}
} );
