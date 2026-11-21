<?php
/** お問い合わせフォーム [ais_contact] と送信処理 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** トピック定義（forms.json 準拠）。key => [ラベル, 追加フィールド] */
function ais_hub_topics() {
	return array(
		'general'  => array( '総合お問い合わせ', array() ),
		'purchase' => array( '車の購入・ローン（カーメル）', array( 'vehicle_preference' => '希望の車種・条件', 'monthly_budget' => '月々のご予算' ) ),
		'lease'    => array( 'カーリース（CARSHICO）', array( 'vehicle_preference' => '希望の車種・条件', 'monthly_budget' => '月々のご予算' ) ),
		'buyback'  => array( '車の買取（BUYMO）', array( 'make' => 'メーカー', 'model' => '車種', 'year' => '年式', 'mileage' => '走行距離' ) ),
		'gps'      => array( 'GPS・車両管理', array( 'fleet_size' => '管理台数', 'desired_features' => 'ご希望の機能' ) ),
		'export'   => array( '中古車の海外輸出', array( 'country' => '輸出先の国', 'vehicle_type' => '車種タイプ', 'quantity' => '台数' ) ),
		'app'      => array( 'AI・アプリ／システム開発', array( 'app_type' => 'アプリ種別', 'target_platform' => '対象プラットフォーム', 'budget_range' => 'ご予算' ) ),
		'partner'  => array( '販売店・事業パートナー', array( 'company' => '会社名', 'prefecture' => '都道府県', 'business_experience' => '事業経験' ) ),
	);
}

/** [ais_contact] フォーム表示 */
add_shortcode( 'ais_contact', function ( $atts ) {
	$atts  = shortcode_atts( array( 'topic' => '' ), $atts );
	$topics = ais_hub_topics();
	$sel   = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( $_GET['topic'] ) ) : $atts['topic'];
	if ( ! isset( $topics[ $sel ] ) ) { $sel = 'general'; }

	$notice = '';
	if ( isset( $_GET['ais_sent'] ) ) {
		$notice = '<div class="ais-form-ok" role="status">お問い合わせを受け付けました。担当者より折り返しご連絡いたします。</div>';
	}

	$extra = $topics[ $sel ][1];
	$h  = $notice . '<form class="ais-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
	$h .= '<input type="hidden" name="action" value="ais_contact_submit">';
	$h .= wp_nonce_field( 'ais_contact', 'ais_nonce', true, false );
	// topic
	$h .= '<p><label>ご相談内容<br><select name="ais_topic">';
	foreach ( $topics as $k => $t ) {
		$h .= '<option value="' . esc_attr( $k ) . '" ' . selected( $k, $sel, false ) . '>' . esc_html( $t[0] ) . '</option>';
	}
	$h .= '</select></label></p>';
	$h .= '<p><label>お名前（必須）<br><input name="ais_name" required maxlength="100"></label></p>';
	$h .= '<p><label>メール（必須）<br><input type="email" name="ais_email" required maxlength="200"></label></p>';
	$h .= '<p><label>電話番号（任意）<br><input name="ais_phone" maxlength="40"></label></p>';
	foreach ( $extra as $k => $label ) {
		$h .= '<p><label>' . esc_html( $label ) . '（任意）<br><input name="ais_x_' . esc_attr( $k ) . '" maxlength="200"></label></p>';
	}
	$h .= '<p><label>メッセージ<br><textarea name="ais_message" rows="5" maxlength="5000"></textarea></label></p>';
	$h .= '<p><label><input type="checkbox" name="ais_consent" value="1" required> <a href="' . esc_url( home_url( '/privacy/' ) ) . '" target="_blank" rel="noopener">個人情報の取り扱い</a>に同意します（必須）</label></p>';
	// honeypot
	$h .= '<p style="position:absolute;left:-9999px" aria-hidden="true"><label>この欄は入力しないでください<input name="ais_website" tabindex="-1" autocomplete="off"></label></p>';
	$h .= '<p><button type="submit" class="ais-btn ais-btn-teal">送信する</button></p>';
	$h .= '</form>';
	return $h;
} );

add_action( 'admin_post_nopriv_ais_contact_submit', 'ais_hub_contact_submit' );
add_action( 'admin_post_ais_contact_submit', 'ais_hub_contact_submit' );

function ais_hub_contact_submit() {
	if ( ! isset( $_POST['ais_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_nonce'] ) ), 'ais_contact' ) ) {
		wp_die( '不正なリクエストです', '', array( 'response' => 403 ) );
	}
	if ( ! empty( $_POST['ais_website'] ) ) { wp_die( 'Invalid request', '', array( 'response' => 400 ) ); } // honeypot
	if ( empty( $_POST['ais_consent'] ) ) { wp_die( '同意が必要です', '', array( 'response' => 400 ) ); }

	$topics = ais_hub_topics();
	$topic  = sanitize_key( wp_unslash( $_POST['ais_topic'] ?? 'general' ) );
	if ( ! isset( $topics[ $topic ] ) ) { $topic = 'general'; }
	$name    = sanitize_text_field( wp_unslash( $_POST['ais_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['ais_email'] ?? '' ) );
	$phone   = sanitize_text_field( wp_unslash( $_POST['ais_phone'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['ais_message'] ?? '' ) );
	if ( ! $name || ! is_email( $email ) ) { wp_die( '必須項目が未入力です', '', array( 'response' => 400 ) ); }

	$label  = $topics[ $topic ][0];
	$lines  = array( '氏名: ' . $name, 'メール: ' . $email, '電話: ' . $phone, '種別: ' . $label );
	$meta_x = array();
	foreach ( $topics[ $topic ][1] as $k => $flabel ) {
		$v = sanitize_text_field( wp_unslash( $_POST[ 'ais_x_' . $k ] ?? '' ) );
		if ( '' !== $v ) { $lines[] = $flabel . ': ' . $v; $meta_x[ $k ] = $v; }
	}
	$lines[] = '';
	$lines[] = $message;
	$body    = implode( "\n", $lines );

	$id = wp_insert_post( array(
		'post_type'    => 'ais_inquiry',
		'post_status'  => 'private',
		'post_title'   => wp_strip_all_tags( $label . ' / ' . $name ),
		'post_content' => $body,
	), true );
	if ( is_wp_error( $id ) ) { wp_die( '送信に失敗しました', '', array( 'response' => 500 ) ); }
	update_post_meta( $id, 'ais_email', $email );
	update_post_meta( $id, 'ais_phone', $phone );
	update_post_meta( $id, 'ais_topic', $label );
	update_post_meta( $id, 'ais_consent_at', current_time( 'mysql' ) );
	foreach ( $meta_x as $k => $v ) { update_post_meta( $id, 'ais_x_' . $k, $v ); }

	$to = ais_hub_get( 'notify_email', get_option( 'admin_email' ) );
	wp_mail( $to, 'AIS 新規お問い合わせ: ' . $label, $body );

	/**
	 * 新規お問い合わせ通知フック（Slack等）。
	 * @param int    $id    問い合わせ投稿ID
	 * @param string $label 種別ラベル
	 * @param string $topic トピックキー
	 */
	do_action( 'ais_hub_inquiry', $id, $label, $topic );

	wp_safe_redirect( add_query_arg( 'ais_sent', '1', wp_get_referer() ? wp_get_referer() : home_url( '/contact/' ) ) );
	exit;
}

/** フォームの最小スタイル */
add_action( 'wp_head', function () {
	echo '<style>.ais-form{max-width:640px}.ais-form p{margin:0 0 16px}.ais-form label{font-weight:600;font-size:14px}.ais-form input,.ais-form select,.ais-form textarea{width:100%;padding:11px 13px;border:1px solid #d7dee8;border-radius:8px;font-size:15px;margin-top:6px;font-family:inherit}.ais-form input[type=checkbox]{width:auto;margin:0 6px 0 0}.ais-form-ok{background:#e8f7f1;border:1px solid #2f8f66;color:#1c6b4e;border-radius:10px;padding:14px 16px;margin-bottom:20px}</style>';
} );
