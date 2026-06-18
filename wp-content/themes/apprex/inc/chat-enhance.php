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
	// 会話ログにAIの応答を追記（お客様の発言はリクエスト受信時に追記済み）。
	apprex_chat_log_append( $session, 'AI', $reply );

	// 既存の契約者（ログイン済み）はリード化しない。
	if ( apprex_chat_is_member() ) {
		return false;
	}

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
	return apprex_chat_register_lead( $email, apprex_chat_transcript( $messages, $reply ), $session );
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

/** セッションのチャットログ投稿IDを取得（無ければ作成）。 */
function apprex_chat_log_id( $session ) {
	$session  = $session ? $session : 'anon-' . gmdate( 'Ymd' );
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
	if ( $existing ) {
		return (int) $existing[0];
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'apprex_chatlog',
			'post_status' => 'publish',
			'post_title'  => '会話 ' . wp_date( 'Y-m-d H:i' ) . ' / ' . $session,
		)
	);
	if ( $id ) {
		update_post_meta( $id, 'apprex_session', $session );
	}
	return (int) $id;
}

/**
 * 1発言をチャットログに追記（誰の発言かを明記して時系列で残す）。
 *
 * @param string $session セッションID。
 * @param string $who     発言者ラベル（お客様／AI／担当者／システム）。
 * @param string $text    発言内容。
 */
function apprex_chat_log_append( $session, $who, $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return;
	}
	$id = apprex_chat_log_id( $session );
	if ( ! $id ) {
		return;
	}
	$line = $who . '：' . $text;
	$prev = (string) get_post_field( 'post_content', $id );
	wp_update_post(
		array(
			'ID'           => $id,
			'post_content' => ( '' !== $prev ? $prev . "\n" . $line : $line ),
		)
	);
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
 * 会員ログイン連携
 * ---------------------------------------------------------------------- */

/** ログイン中の契約者か（紐づく契約が1件以上ある）。 */
function apprex_chat_is_member() {
	if ( ! is_user_logged_in() || ! function_exists( 'apprex_member_contracts' ) ) {
		return false;
	}
	$ids = apprex_member_contracts( wp_get_current_user() );
	return ! empty( $ids );
}

/** フロントへ渡す会員情報（チャットの挨拶・導線切替用）。 */
function apprex_chat_member_info() {
	if ( ! apprex_chat_is_member() ) {
		return array( 'loggedIn' => false );
	}
	$user = wp_get_current_user();
	return array(
		'loggedIn'  => true,
		'name'      => $user->display_name ? $user->display_name : $user->user_email,
		'mypageUrl' => function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' ),
	);
}

/**
 * ログイン中の契約者の契約サマリ（システムプロンプトに注入）。
 * 本人にのみ案内してよい情報。未ログイン/非契約者なら空文字。
 *
 * @return string
 */
function apprex_chat_member_context() {
	if ( ! apprex_chat_is_member() ) {
		return '';
	}
	$user   = wp_get_current_user();
	$ids    = apprex_member_contracts( $user );
	$labels = array( 'active' => '契約中', 'pending' => '更新待ち', 'cancelled' => '解約' );

	$ctx  = '本チャットの相手は、ログイン済みのご契約者「' . $user->display_name . '」様です。以下はこの方の契約情報です。';
	$ctx .= "本人確認済みのため、本人の契約・支払い・更新日について具体的に案内してよい。次の各テーマには丁寧かつ具体的に対応する：\n";
	$ctx .= "- プラン変更：上位・下位プランへの変更が可能なこと、原則として次回更新日からの適用となること、差額や手続きは担当者がご案内する旨を伝え、ミーティング予約またはお問い合わせへ誘導する。\n";
	$ctx .= "- 解約：退会は所定の手続きで可能。ただし最低利用期間（契約開始日から12ヶ月）の満了前に解約する場合は中途解約の違約金が発生する旨を必ず案内する。違約金＝①キャンペーン/割引で無料・減額にした初期設定費の本来額（割引相当額）＋②解約月の翌月から最低利用期間満了月までの残存月数×月額利用料。既払いの初期費用・制作費・月額は返金されない。手続き自体は担当者/お問い合わせへ誘導する。\n";
	$ctx .= "- 支払い・請求：本人の支払い方法・支払期日・最終入金状況（下記）に基づき具体的に案内する。\n";
	$ctx .= "登録変更・解約・プラン変更の最終手続きは担当者/お問い合わせ窓口へ誘導する。マイページ（" . apprex_mypage_url() . "）も案内する。\n";
	foreach ( $ids as $id ) {
		$g     = function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, $k, true );
		};
		$st    = $g( 'apprex_c_status' );
		$pm    = 'invoice' === $g( 'apprex_c_payment_method' ) ? '請求書（振込）' : 'Square（自動課金）';
		$ctx  .= "\n- 契約：" . trim( $g( 'apprex_c_service' ) . ' ' . $g( 'apprex_c_plan' ) )
			. '／状態：' . ( isset( $labels[ $st ] ) ? $labels[ $st ] : $st )
			. '／月額：¥' . number_format( (int) $g( 'apprex_c_monthly' ) )
			. '／契約開始：' . ( $g( 'apprex_c_start' ) ? $g( 'apprex_c_start' ) : '不明' )
			. '／次回更新日：' . ( $g( 'apprex_c_renewal' ) ? $g( 'apprex_c_renewal' ) : '不明' )
			. '／支払い方法：' . $pm
			. '／支払い期日：' . ( $g( 'apprex_c_payment_day' ) ? '毎月' . $g( 'apprex_c_payment_day' ) . '日' : '不明' )
			. '／最終入金確認：' . ( $g( 'apprex_c_last_paid' ) ? $g( 'apprex_c_last_paid' ) : 'なし' );
	}
	return $ctx;
}

/* -------------------------------------------------------------------------
 * 次の行動サジェスト（UIにチップ表示）
 * ---------------------------------------------------------------------- */
function apprex_chat_suggestions() {
	// ログイン契約者には、まずマイページを案内。
	if ( apprex_chat_is_member() ) {
		$out   = array( array( 'label' => 'マイページ', 'url' => apprex_mypage_url() ) );
		$out[] = array( 'label' => 'お問い合わせ', 'url' => home_url( '/contact/' ) );
		$meet  = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '';
		if ( $meet ) {
			$out[] = array( 'label' => 'ミーティング予約', 'url' => $meet );
		}
		return $out;
	}

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
