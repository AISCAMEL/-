<?php
/**
 * Slack通知モジュール
 * - AI記事の下書き生成／記事公開／新規お問い合わせ を Slack Incoming Webhook へ通知
 * - WPの編集・公開画面への導線リンク付き（＝Slackから確認→操作）
 * - 日次ダイジェスト（集客数値＋AI提案）は includes/analytics.php から送信
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ais_hub_notify_opt( $key, $default = '' ) {
	$o = get_option( 'ais_hub_notify', array() );
	return ( is_array( $o ) && isset( $o[ $key ] ) ) ? $o[ $key ] : $default;
}

function ais_hub_notify_defaults() {
	return array(
		'webhook_url' => '',
		'on_generate' => 1,
		'on_publish'  => 1,
		'on_inquiry'  => 1,
		'inq_name'    => 0, // 氏名（イニシャル）も送る
		'daily'       => 1, // 日次ダイジェスト
	);
}

/** Slack Webキー（wp-config 定数があれば優先） */
function ais_hub_slack_webhook() {
	if ( defined( 'AIS_SLACK_WEBHOOK' ) && AIS_SLACK_WEBHOOK ) { return AIS_SLACK_WEBHOOK; }
	return (string) ais_hub_notify_opt( 'webhook_url', '' );
}

/** Slackへ送信（text必須、blocksは任意） */
function ais_hub_slack_send( $text, $blocks = null ) {
	$url = ais_hub_slack_webhook();
	if ( ! $url ) { return new WP_Error( 'nowebhook', 'Slack Webhook URLが未設定です' ); }
	$payload = array( 'text' => $text );
	if ( $blocks ) { $payload['blocks'] = $blocks; }
	$resp = wp_remote_post( $url, array(
		'timeout' => 20,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $payload ),
	) );
	if ( is_wp_error( $resp ) ) { return $resp; }
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) { return new WP_Error( 'slack', 'Slack応答: HTTP ' . $code . ' ' . wp_remote_retrieve_body( $resp ) ); }
	return true;
}

/** section + context の簡易ブロックを作る */
function ais_hub_slack_blocks( $title, $lines, $context = '' ) {
	$body   = '*' . $title . "*\n" . implode( "\n", $lines );
	$blocks = array( array( 'type' => 'section', 'text' => array( 'type' => 'mrkdwn', 'text' => $body ) ) );
	if ( $context ) {
		$blocks[] = array( 'type' => 'context', 'elements' => array( array( 'type' => 'mrkdwn', 'text' => $context ) ) );
	}
	return $blocks;
}

/* =======================================================
 *  イベント通知
 * ======================================================= */

/** AI下書き生成 */
add_action( 'ais_hub_ai_generated', function ( $post_id, $keyword, $status ) {
	if ( ! ais_hub_notify_opt( 'on_generate', 1 ) || ! ais_hub_slack_webhook() ) { return; }
	$post    = get_post( $post_id );
	if ( ! $post ) { return; }
	$edit    = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 60, '…' );
	$suggest = ( 'publish' === $status )
		? '🤖 提案：自動公開済みです。内容と出典に問題がないか、公開記事を確認してください。'
		: '🤖 提案：下書きを確認し、固有の価格・審査可否など事実関係に問題がなければ公開してください。';
	$lines = array(
		'• キーワード：' . $keyword,
		'• 状態：' . ( 'publish' === $status ? '公開' : '下書き' ),
		'• ' . $excerpt,
		'• <' . esc_url_raw( $edit ) . '|WPで確認・編集/公開する>',
	);
	ais_hub_slack_send(
		'📝 AI記事を生成：' . $post->post_title,
		ais_hub_slack_blocks( '📝 AI記事を生成しました：' . $post->post_title, $lines, $suggest )
	);
}, 10, 3 );

/** 記事の公開（手動公開・予約公開含む。投稿タイプ post のみ） */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old ) { return; }
	if ( ! $post || 'post' !== $post->post_type ) { return; }
	if ( ! ais_hub_notify_opt( 'on_publish', 1 ) || ! ais_hub_slack_webhook() ) { return; }
	$lines = array(
		'• <' . esc_url_raw( get_permalink( $post ) ) . '|公開ページを見る>',
		'• カテゴリ：' . implode( '、', wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) ) ),
	);
	ais_hub_slack_send(
		'✅ 記事を公開：' . $post->post_title,
		ais_hub_slack_blocks( '✅ 記事を公開しました：' . $post->post_title, $lines, '🤖 提案：SNSや関連ページからの内部リンクで初速を付けると流入が伸びます。' )
	);
}, 10, 3 );

/** 新規お問い合わせ（個人情報は最小限） */
add_action( 'ais_hub_inquiry', function ( $id, $label, $topic ) {
	if ( ! ais_hub_notify_opt( 'on_inquiry', 1 ) || ! ais_hub_slack_webhook() ) { return; }
	$view  = admin_url( 'post.php?post=' . $id . '&action=edit' );
	$lines = array( '• 種別：' . $label );
	if ( ais_hub_notify_opt( 'inq_name', 0 ) ) {
		$post = get_post( $id );
		if ( $post ) {
			$nm = wp_strip_all_tags( $post->post_title );
			$lines[] = '• ' . mb_substr( $nm, 0, 1 ) . '※※様（詳細はWPで）';
		}
	}
	$lines[] = '• <' . esc_url_raw( $view ) . '|WPで内容を確認する>';
	ais_hub_slack_send(
		'📨 新規お問い合わせ：' . $label,
		ais_hub_slack_blocks( '📨 新規お問い合わせが届きました', $lines, '🤖 提案：一次返信は当日中が目安。反応率が上がります。' )
	);
}, 10, 3 );

/* =======================================================
 *  管理画面
 * ======================================================= */
add_action( 'admin_menu', function () {
	add_submenu_page( 'ais-hub', '通知（Slack）', '通知（Slack）', 'manage_options', 'ais-hub-notify', 'ais_hub_notify_page' );
}, 32 );

function ais_hub_notify_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$o = wp_parse_args( get_option( 'ais_hub_notify', array() ), ais_hub_notify_defaults() );
	if ( isset( $_GET['saved'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>保存しました。</p></div>'; }
	if ( isset( $_GET['tested'] ) ) {
		$ok = '1' === $_GET['tested'];
		echo '<div class="notice ' . ( $ok ? 'notice-success' : 'notice-error' ) . ' is-dismissible"><p>' . ( $ok ? 'Slackにテスト送信しました。' : 'テスト送信に失敗：' . esc_html( wp_unslash( $_GET['msg'] ?? '' ) ) ) . '</p></div>';
	}
	$const = defined( 'AIS_SLACK_WEBHOOK' );
	echo '<div class="wrap"><h1>Slack通知</h1>';
	echo '<p>Slackの<strong>Incoming Webhook</strong> URLを登録すると、AI記事の生成・公開・お問い合わせ、日次の集客ダイジェストを指定チャンネルへ通知します。</p>';
	echo '<p class="description">取得方法：Slack → 該当チャンネル → アプリ「Incoming Webhooks」を追加 → Webhook URL（https://hooks.slack.com/services/...）をコピー。</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ais_hub_notify_save">';
	wp_nonce_field( 'ais_hub_notify_save', 'ais_hub_notify_nonce' );
	echo '<table class="form-table">';
	echo '<tr><th>Webhook URL</th><td>' . ( $const ? '<em>wp-config の定数 AIS_SLACK_WEBHOOK を使用中</em>' : '<input type="password" name="webhook_url" value="' . esc_attr( $o['webhook_url'] ) . '" style="width:480px" autocomplete="off" placeholder="https://hooks.slack.com/services/XXX/YYY/ZZZ">' ) . '</td></tr>';
	echo '<tr><th>通知する内容</th><td>';
	echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="on_generate" value="1" ' . checked( ! empty( $o['on_generate'] ), true, false ) . '> AI記事の下書きを生成したとき</label>';
	echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="on_publish" value="1" ' . checked( ! empty( $o['on_publish'] ), true, false ) . '> 記事を公開したとき</label>';
	echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="on_inquiry" value="1" ' . checked( ! empty( $o['on_inquiry'] ), true, false ) . '> 新規お問い合わせが届いたとき</label>';
	echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="daily" value="1" ' . checked( ! empty( $o['daily'] ), true, false ) . '> 毎日の集客ダイジェスト（アクセス・流入・問い合わせ＋AI提案）</label>';
	echo '</td></tr>';
	echo '<tr><th>お問い合わせ通知</th><td><label><input type="checkbox" name="inq_name" value="1" ' . checked( ! empty( $o['inq_name'] ), true, false ) . '> 氏名の頭文字も送る（既定オフ＝個人情報はWP内でのみ確認）</label></td></tr>';
	echo '</table>';
	submit_button( '保存' );
	echo '</form>';

	echo '<hr><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ais_hub_notify_test">';
	wp_nonce_field( 'ais_hub_notify_test', 'ais_hub_notify_test_nonce' );
	submit_button( 'テスト送信', 'secondary', 'submit', false );
	echo ' <span class="description">保存済みのWebhookへテストメッセージを送ります。</span></form>';

	echo '<p style="margin-top:14px" class="description">※ お問い合わせ通知には個人情報が含まれ得ます。Slackチャンネルの公開範囲にご注意ください（既定では氏名・連絡先は送らず、WP内リンクのみ通知します）。</p>';
	echo '</div>';
}

add_action( 'admin_post_ais_hub_notify_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_notify_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_notify_nonce'] ) ), 'ais_hub_notify_save' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$o = ais_hub_notify_defaults();
	if ( ! defined( 'AIS_SLACK_WEBHOOK' ) ) {
		$o['webhook_url'] = esc_url_raw( trim( wp_unslash( $_POST['webhook_url'] ?? '' ) ) );
	} else {
		$o['webhook_url'] = ais_hub_notify_opt( 'webhook_url', '' );
	}
	$o['on_generate'] = empty( $_POST['on_generate'] ) ? 0 : 1;
	$o['on_publish']  = empty( $_POST['on_publish'] ) ? 0 : 1;
	$o['on_inquiry']  = empty( $_POST['on_inquiry'] ) ? 0 : 1;
	$o['inq_name']    = empty( $_POST['inq_name'] ) ? 0 : 1;
	$o['daily']       = empty( $_POST['daily'] ) ? 0 : 1;
	update_option( 'ais_hub_notify', $o );
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-notify&saved=1' ) );
	exit;
} );

add_action( 'admin_post_ais_hub_notify_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( '権限がありません', '', array( 'response' => 403 ) ); }
	if ( ! isset( $_POST['ais_hub_notify_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ais_hub_notify_test_nonce'] ) ), 'ais_hub_notify_test' ) ) { wp_die( '不正なリクエストです', '', array( 'response' => 403 ) ); }
	$r   = ais_hub_slack_send( '✅ AIS Business Hub：テスト送信です。通知は正常に動作しています。', ais_hub_slack_blocks( '✅ 接続テスト成功', array( '• ' . home_url( '/' ), '• ' . current_time( 'Y-m-d H:i' ) ) ) );
	$ok  = ( true === $r );
	$msg = is_wp_error( $r ) ? $r->get_error_message() : '';
	wp_safe_redirect( admin_url( 'admin.php?page=ais-hub-notify&tested=' . ( $ok ? '1' : '0' ) . ( $msg ? '&msg=' . rawurlencode( $msg ) : '' ) ) );
	exit;
} );
