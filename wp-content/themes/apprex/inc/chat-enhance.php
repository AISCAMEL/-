<?php
/**
 * チャットbot強化（フェーズE）。
 *
 * - 後追い（リード獲得）：会話中にメールアドレスが出たら、問い合わせ(lead)として登録し、
 *   ステップメール登録・管理者通知・Slack・GAS連携まで自動で繋げる。
 * - 学習：会話ログを CPT「チャットログ」に保存（運営者が見て改善＝学習に活用）。
 * - 誘導：応答に「次の行動」サジェスト（見積もり・無料体験・ミーティング等）を返す。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * チャットログ CPT
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type(
		'apprex_chatlog',
		array(
			'labels'          => array(
				'name'          => __( 'チャットログ', 'apprex' ),
				'singular_name' => __( 'チャットログ', 'apprex' ),
				'menu_name'     => __( 'チャットログ', 'apprex' ),
				'all_items'     => __( 'チャットログ', 'apprex' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-format-chat',
			'menu_position'   => 26,
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'editor' ),
		)
	);
} );

/* -------------------------------------------------------------------------
 * 応答後のフック：ログ保存＋リード獲得
 * ---------------------------------------------------------------------- */

/**
 * @param array  $messages 会話履歴 [{role,content}]。
 * @param string $reply    今回のAI応答。
 * @param string $session  クライアント発行のセッションID。
 * @return bool リードを新規獲得したら true。
 */
function apprex_chat_after_reply( $messages, $reply, $session ) {
	$transcript = apprex_chat_transcript( $messages, $reply );
	apprex_chat_log_save( $session, $transcript );

	// 会話からメールを検出（最後に出たものを採用）。
	$email = '';
	foreach ( $messages as $m ) {
		if ( 'user' !== $m['role'] ) {
			continue;
		}
		if ( preg_match_all( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $m['content'], $mm ) ) {
			$email = end( $mm[0] );
		}
	}
	if ( ! $email || ! is_email( $email ) ) {
		return false;
	}
	return apprex_chat_register_lead( $email, $transcript, $session );
}

/** 会話を読みやすいテキストに整形。 */
function apprex_chat_transcript( $messages, $reply = '' ) {
	$lines = array();
	foreach ( $messages as $m ) {
		$who     = ( 'assistant' === $m['role'] ) ? 'AI' : 'お客様';
		$lines[] = $who . '：' . $m['content'];
	}
	if ( $reply ) {
		$lines[] = 'AI：' . $reply;
	}
	return implode( "\n", $lines );
}

/** セッション単位でチャットログを upsert。 */
function apprex_chat_log_save( $session, $transcript ) {
	$session = $session ? $session : 'anon-' . gmdate( 'Ymd' );
	$existing = get_posts(
		array(
			'post_type'      => 'apprex_chatlog',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'apprex_session',
			'meta_value'     => $session,
		)
	);
	$title = '会話 ' . wp_date( 'Y-m-d H:i' ) . ' / ' . $session;
	if ( $existing ) {
		wp_update_post(
			array(
				'ID'           => $existing[0],
				'post_content' => $transcript,
			)
		);
		return $existing[0];
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'apprex_chatlog',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $transcript,
		)
	);
	if ( $id ) {
		update_post_meta( $id, 'apprex_session', $session );
	}
	return $id;
}

/**
 * チャット経由のリードを問い合わせとして登録し、後追い導線に乗せる。
 *
 * @param string $email      検出したメール。
 * @param string $transcript 会話全文。
 * @param string $session    セッションID。
 * @return bool 新規登録したら true（重複時 false）。
 */
function apprex_chat_register_lead( $email, $transcript, $session ) {
	// 同じメールの多重登録を1日抑止。
	$guard = 'apprex_chatlead_' . md5( strtolower( $email ) );
	if ( get_transient( $guard ) ) {
		return false;
	}
	set_transient( $guard, 1, DAY_IN_SECONDS );

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'apprex_inquiry',
			'post_status' => 'publish',
			'post_title'  => '[チャット] ' . $email,
		)
	);
	if ( ! $post_id ) {
		return false;
	}

	$fields = array(
		'name'    => 'チャット来訪者',
		'company' => '',
		'email'   => $email,
		'phone'   => '',
		'message' => "（チャットからの問い合わせ）\n\n" . $transcript,
	);
	update_post_meta( $post_id, 'apprex_type', 'contact' );
	update_post_meta( $post_id, 'apprex_name', $fields['name'] );
	update_post_meta( $post_id, 'apprex_email', $email );
	update_post_meta( $post_id, 'apprex_message', $fields['message'] );
	update_post_meta( $post_id, 'apprex_source', 'chat' );

	// ステップメール（contact）に登録 → 後追い配信。
	if ( function_exists( 'apprex_enroll_drip' ) ) {
		apprex_enroll_drip( $post_id, 'contact', $email, $fields['name'] );
	}
	// 管理者通知。
	if ( function_exists( 'apprex_notify_inquiry' ) ) {
		apprex_notify_inquiry( $post_id, 'チャット問い合わせ', $fields );
	}
	// Slack（任意）。
	if ( function_exists( 'apprex_slack_notify' ) ) {
		apprex_slack_notify( ":speech_balloon: チャットから新規リード：{$email} " . admin_url( 'post.php?post=' . $post_id . '&action=edit' ) );
	}
	// GAS（スプレッド/Asana/Slack）へ。
	if ( function_exists( 'apprex_dispatch_event' ) ) {
		apprex_dispatch_event(
			'inquiry',
			array(
				'id'         => $post_id,
				'type'       => 'contact',
				'type_label' => 'チャット問い合わせ',
				'name'       => $fields['name'],
				'company'    => '',
				'email'      => $email,
				'phone'      => '',
				'message'    => $fields['message'],
				'source'     => 'chat',
				'admin_url'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
			)
		);
	}
	return true;
}

/* -------------------------------------------------------------------------
 * 次の行動サジェスト（UIにチップ表示）
 * ---------------------------------------------------------------------- */
function apprex_chat_suggestions() {
	$out = array(
		array( 'label' => '見積もりする', 'url' => home_url( '/estimate/' ) ),
		array( 'label' => '無料体験', 'url' => home_url( '/free-trial/' ) ),
	);
	$meet = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '';
	if ( $meet ) {
		$out[] = array( 'label' => 'ミーティング予約', 'url' => $meet );
	}
	$out[] = array( 'label' => 'お問い合わせ', 'url' => home_url( '/contact/' ) );
	return $out;
}
