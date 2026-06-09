<?php
/**
 * Quote → Order flow backend.
 *
 * - Registers a private "apprex_order" CPT to store submissions.
 * - REST endpoint POST /apprex/v1/order: validates, recomputes the estimate
 *   server-side (anti-tampering), stores the order, emails the admin.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the order post type (admin-only).
 */
function apprex_register_orders_cpt() {
	register_post_type(
		'apprex_order',
		array(
			'labels'       => array(
				'name'          => __( '見積・発注', 'apprex' ),
				'singular_name' => __( '見積・発注', 'apprex' ),
				'menu_name'     => __( '見積・発注', 'apprex' ),
				'all_items'     => __( '見積・発注一覧', 'apprex' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-cart',
			'menu_position' => 6,
			'capability_type' => 'post',
			'supports'     => array( 'title' ),
		)
	);

	register_post_status(
		'apprex_new',
		array(
			'label'                     => '新規受付',
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
		)
	);
}
add_action( 'init', 'apprex_register_orders_cpt' );

/**
 * REST route for order submission.
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/order',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_rest_order',
			'permission_callback' => '__return_true',
		)
	);
} );

/**
 * Handle an order submission.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_rest_order( WP_REST_Request $request ) {
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'forbidden', '不正なリクエストです。ページを再読み込みしてください。', array( 'status' => 403 ) );
	}

	$service = sanitize_key( $request->get_param( 'service' ) );
	$plan    = sanitize_key( $request->get_param( 'plan' ) );
	$options = array_map( 'sanitize_key', (array) $request->get_param( 'options' ) );

	$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$company = sanitize_text_field( (string) $request->get_param( 'company' ) );
	$email   = sanitize_email( (string) $request->get_param( 'email' ) );
	$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );

	if ( '' === $name || ! is_email( $email ) ) {
		return new WP_Error( 'bad_request', 'お名前と有効なメールアドレスをご入力ください。', array( 'status' => 400 ) );
	}

	// Recompute server-side — never trust client totals.
	$estimate = apprex_calculate_estimate( $service, $plan, $options );
	if ( is_wp_error( $estimate ) ) {
		return $estimate;
	}

	$title   = sprintf( '%s / %s — %s', $estimate['service_label'], $estimate['plan_label'], $name );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'apprex_order',
			'post_status' => 'apprex_new',
			'post_title'  => $title,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save_failed', '送信に失敗しました。お手数ですが再度お試しください。', array( 'status' => 500 ) );
	}

	$meta = array(
		'customer_name'    => $name,
		'customer_company' => $company,
		'customer_email'   => $email,
		'customer_message' => $message,
		'estimate'         => $estimate,
		'submitted_at'     => current_time( 'mysql' ),
		'source_url'       => esc_url_raw( (string) $request->get_param( 'source_url' ) ),
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $post_id, 'apprex_' . $k, $v );
	}

	apprex_notify_order( $post_id, $estimate, $meta );
	apprex_send_order_autoreply( $estimate, $meta );

	// Enroll the customer into the 見積もり (estimate) follow-up sequence.
	if ( function_exists( 'apprex_enroll_drip' ) ) {
		apprex_enroll_drip( $post_id, 'estimate', $meta['customer_email'], $meta['customer_name'] );
	}

	return rest_ensure_response(
		array(
			'ok'       => true,
			'order_id' => $post_id,
			'summary'  => apprex_estimate_summary_html( $estimate ),
			'message'  => 'お申し込みを受け付けました。担当者より2営業日以内にご連絡いたします。',
		)
	);
}

/**
 * Email the site admin about a new order.
 *
 * @param int   $post_id  Order ID.
 * @param array $estimate Estimate breakdown.
 * @param array $meta     Customer meta.
 */
function apprex_notify_order( $post_id, $estimate, $meta ) {
	$to      = apply_filters( 'apprex_order_notify_email', get_option( 'admin_email' ) );
	$subject = sprintf( '[APPREX] 新規見積・発注 #%d — %s', $post_id, $meta['customer_name'] );

	$lines = array(
		'新しい見積・発注を受け付けました。',
		'',
		'■ お客様',
		'お名前: ' . $meta['customer_name'],
		'会社名: ' . ( $meta['customer_company'] ? $meta['customer_company'] : '（未入力）' ),
		'メール: ' . $meta['customer_email'],
		'ご要望: ' . ( $meta['customer_message'] ? $meta['customer_message'] : '（なし）' ),
		'',
		'■ 見積内容',
		'サービス: ' . $estimate['service_label'],
		'プラン: ' . $estimate['plan_label'],
	);
	foreach ( $estimate['options'] as $o ) {
		$lines[] = 'オプション: ' . $o['label'] . '（+' . number_format( $o['price'] ) . '円）';
	}
	if ( 'monthly' === $estimate['billing'] ) {
		$lines[] = '月額: ' . number_format( $estimate['monthly'] ) . '円（税抜）';
		$lines[] = '初期費用: ' . number_format( $estimate['initial_fee'] ) . '円';
		$lines[] = '年間概算: ' . number_format( $estimate['annual_est'] ) . '円';
		$lines[] = '最低契約: ' . $estimate['min_months'] . 'ヶ月';
	} else {
		$lines[] = '合計（買い切り）: ' . number_format( $estimate['oneoff'] ) . '円（税抜）';
	}
	$lines[] = '';
	$lines[] = '管理画面: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' );

	wp_mail( $to, $subject, implode( "\n", $lines ) );
}

/**
 * Send the customer an order/estimate confirmation auto-reply (plain text).
 *
 * @param array $estimate Estimate breakdown.
 * @param array $meta     Customer meta.
 */
function apprex_send_order_autoreply( $estimate, $meta ) {
	$name    = $meta['customer_name'];
	$subject = '【APPREX】お見積り・お申し込みありがとうございます';

	$lines = array(
		"{$name} 様",
		'',
		'この度はお見積り・お申し込みをいただきありがとうございます。',
		'以下の内容で受け付けました。担当者より2営業日以内にご連絡いたします。',
		'',
		'■ お見積り内容',
		'サービス: ' . $estimate['service_label'],
		'プラン: ' . $estimate['plan_label'],
	);
	foreach ( $estimate['options'] as $o ) {
		$lines[] = 'オプション: ' . $o['label'] . '（+' . number_format( $o['price'] ) . '円）';
	}
	if ( 'monthly' === $estimate['billing'] ) {
		$lines[] = '月額: ' . number_format( $estimate['monthly'] ) . '円（税抜）';
		$lines[] = '初期費用: ' . number_format( $estimate['initial_fee'] ) . '円（キャンペーン中）';
		$lines[] = '年間概算: ' . number_format( $estimate['annual_est'] ) . '円';
		$lines[] = '最低契約: ' . $estimate['min_months'] . 'ヶ月';
	} else {
		$lines[] = '合計（買い切り）: ' . number_format( $estimate['oneoff'] ) . '円（税抜）';
	}
	$line = apprex_line_url();
	$lines[] = '';
	$lines[] = '──────────';
	$lines[] = 'ノーコードアプリ開発プラットフォーム APPREX / 合同会社アイズ';
	$lines[] = '受付：平日10:00〜18:00（チャット・メール・オンライン相談）';
	if ( $line ) {
		$lines[] = 'LINEでのご相談：' . $line;
	}

	wp_mail( $meta['customer_email'], $subject, implode( "\n", $lines ), apprex_mail_headers() );
}

/**
 * Build an HTML summary of an estimate (used in the order confirmation).
 *
 * @param array $estimate Estimate.
 * @return string
 */
function apprex_estimate_summary_html( $estimate ) {
	ob_start();
	?>
	<ul class="estimate-summary">
		<li><strong><?php esc_html_e( 'サービス', 'apprex' ); ?></strong>：<?php echo esc_html( $estimate['service_label'] ); ?></li>
		<li><strong><?php esc_html_e( 'プラン', 'apprex' ); ?></strong>：<?php echo esc_html( $estimate['plan_label'] ); ?></li>
		<?php if ( ! empty( $estimate['options'] ) ) : ?>
			<li><strong><?php esc_html_e( 'オプション', 'apprex' ); ?></strong>：
				<?php
				$labels = wp_list_pluck( $estimate['options'], 'label' );
				echo esc_html( implode( '、', $labels ) );
				?>
			</li>
		<?php endif; ?>
		<?php if ( 'monthly' === $estimate['billing'] ) : ?>
			<li><strong><?php esc_html_e( '月額', 'apprex' ); ?></strong>：¥<?php echo esc_html( number_format( $estimate['monthly'] ) ); ?>（税抜）</li>
			<li><strong><?php esc_html_e( '初期費用', 'apprex' ); ?></strong>：¥<?php echo esc_html( number_format( $estimate['initial_fee'] ) ); ?></li>
			<li><strong><?php esc_html_e( '年間概算', 'apprex' ); ?></strong>：¥<?php echo esc_html( number_format( $estimate['annual_est'] ) ); ?></li>
		<?php else : ?>
			<li><strong><?php esc_html_e( '合計（買い切り）', 'apprex' ); ?></strong>：¥<?php echo esc_html( number_format( $estimate['oneoff'] ) ); ?>（税抜）</li>
		<?php endif; ?>
	</ul>
	<?php
	return ob_get_clean();
}

/**
 * Admin columns for orders.
 */
add_filter( 'manage_apprex_order_posts_columns', function ( $cols ) {
	return array(
		'cb'       => $cols['cb'],
		'title'    => __( '内容', 'apprex' ),
		'customer' => __( 'お客様', 'apprex' ),
		'amount'   => __( '金額', 'apprex' ),
		'date'     => $cols['date'],
	);
} );

add_action( 'manage_apprex_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'customer' === $col ) {
		echo esc_html( get_post_meta( $post_id, 'apprex_customer_name', true ) );
		$company = get_post_meta( $post_id, 'apprex_customer_company', true );
		if ( $company ) {
			echo '<br><small>' . esc_html( $company ) . '</small>';
		}
	}
	if ( 'amount' === $col ) {
		$e = get_post_meta( $post_id, 'apprex_estimate', true );
		if ( is_array( $e ) ) {
			if ( 'monthly' === $e['billing'] ) {
				echo '月額 ¥' . esc_html( number_format( $e['monthly'] ) );
			} else {
				echo '¥' . esc_html( number_format( $e['oneoff'] ) );
			}
		}
	}
}, 10, 2 );

/**
 * Show order details in a meta box on the edit screen.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_order_detail', __( '見積・発注 詳細', 'apprex' ), 'apprex_order_detail_box', 'apprex_order', 'normal', 'high' );
} );

/**
 * Render the order detail meta box.
 *
 * @param WP_Post $post Order post.
 */
function apprex_order_detail_box( $post ) {
	$e       = get_post_meta( $post->ID, 'apprex_estimate', true );
	$name    = get_post_meta( $post->ID, 'apprex_customer_name', true );
	$company = get_post_meta( $post->ID, 'apprex_customer_company', true );
	$email   = get_post_meta( $post->ID, 'apprex_customer_email', true );
	$message = get_post_meta( $post->ID, 'apprex_customer_message', true );
	echo '<p><strong>お名前：</strong>' . esc_html( $name ) . '　<strong>会社：</strong>' . esc_html( $company ) . '</p>';
	echo '<p><strong>メール：</strong><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
	if ( $message ) {
		echo '<p><strong>ご要望：</strong><br>' . nl2br( esc_html( $message ) ) . '</p>';
	}
	if ( is_array( $e ) ) {
		echo wp_kses_post( apprex_estimate_summary_html( $e ) );
	}
}
