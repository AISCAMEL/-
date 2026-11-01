<?php
/**
 * LINE WORKS：お客様ごとの個別トークルーム（Bot）。
 *
 * - LINE公式アカウント・サイトのチャットでお客様からメッセージが来たら、そのお客様専用のトークルームを
 *   LINE WORKS に作り（2回目からは同じルーム）、お客様のメッセージとAIの返事を書き込む。
 * - 担当者がそのルームに書くと、お客様に届く（LINE は LINE公式アカウントから、サイトはチャット画面に）。
 *   担当者が書くと、そのお客様へのAIの自動返信を2時間止める。「/ai」と書くとすぐAIに戻す。
 * - 社内へのお知らせ（記事・請求など）は、これまでどおり1つのルーム（Incoming Webhook）に届く。
 *
 * 使うもの（LINE WORKS Developer Console）：
 * - アプリ（Client ID・Client Secret・Service Account・Private Key）。OAuth Scope に bot を追加。
 * - Bot（Bot ID・Bot Secret）。Callback URL にこのサイトの /wp-json/apprex/v1/lineworks/callback を入れ、
 *   「複数人のトークルームに招待可」をオン。管理者画面で Bot をメンバーに公開する。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const APPREX_LWB_API  = 'https://www.worksapis.com/v1.0';
const APPREX_LWB_AUTH = 'https://auth.worksmobile.com/oauth2/v2.0/token';
const APPREX_LWB_HOLD = 7200; // 担当者が書いたあと、AIの自動返信を止める時間（秒）。

/** 設定の値。 */
function apprex_lwb_opt( $k ) {
	return trim( (string) get_option( 'apprex_lwb_' . $k, '' ) );
}

/** 担当者（ルームに入れるメンバー）の ID。1行に1人。 */
function apprex_lwb_members() {
	return array_values( array_unique( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', apprex_lwb_opt( 'members' ) ) ) ) ) );
}

/** 必要な設定がそろっているか。 */
function apprex_lwb_ready() {
	foreach ( array( 'client_id', 'client_secret', 'sa', 'key', 'bot_id', 'bot_secret' ) as $k ) {
		if ( '' === apprex_lwb_opt( $k ) ) {
			return false;
		}
	}
	return (bool) apprex_lwb_members() && function_exists( 'openssl_sign' );
}

/** 最後のエラーを残す（設定画面に出す）。 */
function apprex_lwb_err( $msg ) {
	update_option( 'apprex_lwb_last_err', array( time(), mb_substr( (string) $msg, 0, 300 ) ), false );
}

function apprex_lwb_b64u( $s ) {
	return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/** アクセストークン（JWTで取得・覚えておく）。 */
function apprex_lwb_token( $fresh = false ) {
	$t = get_transient( 'apprex_lwb_token' );
	if ( $t && ! $fresh ) {
		return $t;
	}
	$now = time();
	$jwt = apprex_lwb_b64u( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' . apprex_lwb_b64u(
		wp_json_encode(
			array(
				'iss' => apprex_lwb_opt( 'client_id' ),
				'sub' => apprex_lwb_opt( 'sa' ),
				'iat' => $now,
				'exp' => $now + 3600,
			)
		)
	);
	$key = openssl_pkey_get_private( str_replace( '\n', "\n", apprex_lwb_opt( 'key' ) ) );
	if ( ! $key || ! openssl_sign( $jwt, $sig, $key, OPENSSL_ALGO_SHA256 ) ) {
		return new WP_Error( 'lwb_key', 'Private Key が読み込めません。Developer Console でダウンロードした key ファイルの中身を、そのまま貼ってください。' );
	}
	$res = wp_remote_post(
		APPREX_LWB_AUTH,
		array(
			'timeout' => 15,
			'body'    => array(
				'assertion'     => $jwt . '.' . apprex_lwb_b64u( $sig ),
				'grant_type'    => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'client_id'     => apprex_lwb_opt( 'client_id' ),
				'client_secret' => apprex_lwb_opt( 'client_secret' ),
				'scope'         => 'bot',
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$b = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( empty( $b['access_token'] ) ) {
		return new WP_Error( 'lwb_token', 'LINE WORKS にログインできませんでした（' . (int) wp_remote_retrieve_response_code( $res ) . '）：' . mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 200 ) );
	}
	$ttl = max( 300, (int) ( $b['expires_in'] ?? 3600 ) - 600 );
	set_transient( 'apprex_lwb_token', $b['access_token'], $ttl );
	return $b['access_token'];
}

/**
 * LINE WORKS API を呼ぶ。
 *
 * @return array|WP_Error 返ってきた JSON（空なら []）。
 */
function apprex_lwb_api( $method, $path, $body = null, $retry = true ) {
	$tok = apprex_lwb_token();
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . $tok,
			'Content-Type'  => 'application/json',
		),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$res = wp_remote_request( APPREX_LWB_API . $path, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 401 === $code && $retry ) {
		delete_transient( 'apprex_lwb_token' );
		return apprex_lwb_api( $method, $path, $body, false );
	}
	$b = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 ) {
		$m = is_array( $b ) && ! empty( $b['description'] ) ? $b['description'] : mb_substr( (string) wp_remote_retrieve_body( $res ), 0, 200 );
		return new WP_Error( 'lwb_http', 'LINE WORKS ' . $code . '：' . $m );
	}
	return is_array( $b ) ? $b : array();
}

/** ルームに書き込む。 */
function apprex_lwb_say( $channel, $text ) {
	$r = apprex_lwb_api( 'POST', '/bots/' . rawurlencode( apprex_lwb_opt( 'bot_id' ) ) . '/channels/' . rawurlencode( $channel ) . '/messages', array( 'content' => array( 'type' => 'text', 'text' => mb_substr( (string) $text, 0, 2000 ) ) ) );
	if ( is_wp_error( $r ) ) {
		apprex_lwb_err( 'ルームへの書き込み：' . $r->get_error_message() );
	}
	return $r;
}

/* =========================================================================
 * お客様 ⇔ ルームの対応
 * ====================================================================== */

/** 対応表。key => [c=ルームID, t=題名, s=サイトのチャットの会話ID, at=作った日時]。 */
function apprex_lwb_rooms() {
	$r = get_option( 'apprex_lwb_rooms', array() );
	return is_array( $r ) ? $r : array();
}
function apprex_lwb_rooms_save( $r ) {
	if ( count( $r ) > 800 ) {
		uasort(
			$r,
			function ( $a, $b ) {
				return (int) ( $b['last'] ?? 0 ) - (int) ( $a['last'] ?? 0 );
			}
		);
		$r = array_slice( $r, 0, 800, true );
	}
	update_option( 'apprex_lwb_rooms', $r, false );
}
/** ルームIDからお客様の key。 */
function apprex_lwb_key_by_channel( $channel ) {
	foreach ( apprex_lwb_rooms() as $k => $v ) {
		if ( isset( $v['c'] ) && (string) $v['c'] === (string) $channel ) {
			return $k;
		}
	}
	return '';
}

/**
 * そのお客様のルーム（なければ作る）。
 *
 * @param string $key   ln:LINEのuserId / wm:会員ID / ws:会話ID。
 * @param string $title ルームの名前。
 * @param string $intro はじめに書く説明（お客様の情報）。
 * @param string $first 最初のメッセージ（社内のお知らせ用）。
 * @return string ルームID（作れなければ空）。
 */
function apprex_lwb_room( $key, $title, $intro, $first = '' ) {
	$rooms = apprex_lwb_rooms();
	if ( ! empty( $rooms[ $key ]['c'] ) ) {
		$rooms[ $key ]['last'] = time();
		apprex_lwb_rooms_save( $rooms );
		return (string) $rooms[ $key ]['c'];
	}
	// 同時に2つ作らないよう、少しのあいだ鍵をかける。
	$lock = 'apprex_lwb_mk_' . md5( $key );
	if ( get_transient( $lock ) ) {
		return '';
	}
	set_transient( $lock, 1, 30 );
	$title = mb_substr( $title, 0, 60 );
	$r     = apprex_lwb_api( 'POST', '/bots/' . rawurlencode( apprex_lwb_opt( 'bot_id' ) ) . '/channels', array( 'members' => apprex_lwb_members(), 'title' => $title ) );
	delete_transient( $lock );
	if ( is_wp_error( $r ) || empty( $r['channelId'] ) ) {
		apprex_lwb_err( 'ルームを作れませんでした：' . ( is_wp_error( $r ) ? $r->get_error_message() : '返事にルームIDがありません' ) );
		return '';
	}
	$ch            = (string) $r['channelId'];
	$rooms         = apprex_lwb_rooms();
	$rooms[ $key ] = array( 'c' => $ch, 't' => $title, 'at' => time(), 'last' => time() );
	apprex_lwb_rooms_save( $rooms );
	apprex_lwb_say( $ch, $intro . "\n\n――――――\nこのルームに書くと、お客様に届きます。\n担当者が書くと、このお客様へのAIの自動返信を2時間止めます。\n「/ai」と書くと、すぐAIの自動返信に戻します。" );
	if ( function_exists( 'apprex_notify' ) ) {
		apprex_notify( 'chat', '💬 *新しいお客様の会話*：' . $title . "\nLINE WORKS に個別のルームを作りました。" . ( '' !== $first ? "\n「" . mb_substr( $first, 0, 120 ) . '」' : '' ), array(), array( 'no_slack' => true ) );
	}
	return $ch;
}

/* =========================================================================
 * LINE のお客様
 * ====================================================================== */

/** LINE のお客様のルーム。 */
function apprex_lwb_line_room( $uid, $first = '' ) {
	$name  = function_exists( 'apprex_nh_line_name' ) ? apprex_nh_line_name( $uid ) : 'LINEのお客様';
	$lines = array( '【LINE】' . $name );
	$user  = function_exists( 'apprex_line_member' ) ? apprex_line_member( $uid ) : null;
	if ( $user ) {
		$lines[] = '会員：' . $user->display_name . ' 様（' . $user->user_email . '）';
		$cids    = function_exists( 'apprex_member_contracts' ) ? apprex_member_contracts( $user ) : array();
		foreach ( (array) $cids as $cid ) {
			$lines[] = 'ご契約：' . trim( get_post_meta( $cid, 'apprex_c_service', true ) . ' ' . get_post_meta( $cid, 'apprex_c_plan', true ) ) . ' ' . admin_url( 'post.php?post=' . (int) $cid . '&action=edit' );
		}
	} else {
		$lines[] = '会員：まだ（LINEの友だち）';
	}
	$fid = function_exists( 'apprex_line_friend_get' ) ? apprex_line_friend_get( $uid, false ) : 0;
	if ( $fid ) {
		$src = (string) get_post_meta( $fid, 'apprex_labo_src', true );
		if ( '' !== $src ) {
			$lines[] = 'どこから来たか：' . $src;
		}
		$lines[] = '友だちの記録：' . admin_url( 'post.php?post=' . (int) $fid . '&action=edit' );
	}
	return apprex_lwb_room( 'ln:' . $uid, 'LINE｜' . $name, implode( "\n", $lines ), $first );
}

/* お客様のメッセージ → ルームへ。 */
add_action(
	'apprex_line_event',
	function ( $ev, $uid, $type ) {
		if ( 'message' !== $type || ! apprex_lwb_ready() || '' === $uid ) {
			return;
		}
		$m    = isset( $ev['message'] ) ? $ev['message'] : array();
		$t    = isset( $m['type'] ) ? $m['type'] : '';
		$body = 'text' === $t ? (string) $m['text'] : ( array( 'image' => '（画像が届きました。LINE公式アカウントの管理画面で見られます）', 'sticker' => '（スタンプ）', 'video' => '（動画が届きました）', 'audio' => '（音声が届きました）', 'file' => '（ファイルが届きました）', 'location' => '（位置情報）' )[ $t ] ?? '（' . $t . '）' );
		$ch   = apprex_lwb_line_room( $uid, $body );
		if ( '' !== $ch ) {
			apprex_lwb_say( $ch, '👤 お客様：' . $body );
		}
	},
	6,
	3
);

/* AIの返事 → ルームへ（何と答えたか担当者が分かるように）。 */
add_action(
	'apprex_line_ai_replied',
	function ( $uid, $asked, $reply ) {
		if ( ! apprex_lwb_ready() ) {
			return;
		}
		$rooms = apprex_lwb_rooms();
		if ( ! empty( $rooms[ 'ln:' . $uid ]['c'] ) ) {
			apprex_lwb_say( $rooms[ 'ln:' . $uid ]['c'], '🤖 AI：' . $reply );
		}
	},
	10,
	3
);

/* 担当者が対応中は、LINE の AI の自動返信を止める。 */
add_filter(
	'apprex_line_ai_paused',
	function ( $paused, $uid ) {
		return $paused || (bool) get_transient( 'apprex_lwb_hold_' . md5( (string) $uid ) );
	},
	10,
	2
);

/* =========================================================================
 * サイトのチャットのお客様
 * ====================================================================== */

/** サイトのチャットのルーム。会員は会員ごと、ゲストは会話ごと。 */
function apprex_lwb_web_room( $session, $meta, $first = '' ) {
	$uid = get_current_user_id();
	$key = $uid ? 'wm:' . $uid : 'ws:' . $session;
	if ( $uid ) {
		$u     = wp_get_current_user();
		$name  = $u->display_name . ' 様（会員）';
		$lines = array( '【サイトのチャット】' . $name, 'メール：' . $u->user_email, '会員の情報：' . admin_url( 'user-edit.php?user_id=' . $uid ) );
	} else {
		$name  = 'ゲスト ' . wp_date( 'n/j G:i' );
		$lines = array( '【サイトのチャット】' . $name, '会員ではないお客様です（お名前は分かりません）。' );
	}
	if ( ! empty( $meta['page'] ) ) {
		$lines[] = '見ていたページ：' . $meta['page'];
	}
	$lines[] = '会話の記録：' . admin_url( 'edit.php?post_type=apprex_chatlog' );
	$lines[] = '※お客様がサイトのチャットを閉じていると、担当者の返事は次に開いたときまで届きません。';
	$ch      = apprex_lwb_room( $key, 'サイト｜' . $name, implode( "\n", $lines ), $first );
	if ( '' !== $ch ) {
		$rooms = apprex_lwb_rooms();
		if ( isset( $rooms[ $key ] ) && ( $rooms[ $key ]['s'] ?? '' ) !== $session ) {
			$rooms[ $key ]['s'] = $session; // 担当者の返事を届ける先（いちばん新しい会話）。
			apprex_lwb_rooms_save( $rooms );
		}
	}
	return $ch;
}

/* お客様のメッセージ → ルームへ。 */
add_action(
	'apprex_chat_op_customer_msg',
	function ( $session, $text, $meta ) {
		if ( ! apprex_lwb_ready() ) {
			return;
		}
		$ch = apprex_lwb_web_room( $session, $meta, $text );
		if ( '' !== $ch ) {
			apprex_lwb_say( $ch, '👤 お客様：' . $text );
		}
	},
	10,
	3
);

/* 担当者を呼ばれた。 */
add_action(
	'apprex_chat_op_requested',
	function ( $session, $meta ) {
		if ( ! apprex_lwb_ready() ) {
			return;
		}
		$ch = apprex_lwb_web_room( $session, $meta );
		if ( '' !== $ch ) {
			apprex_lwb_say( $ch, '🙋 お客様が担当者との会話を希望しています。このルームに書いて返事をしてください。' );
		}
		if ( function_exists( 'apprex_notify' ) ) {
			apprex_notify( 'chat', '🙋 サイトのチャットで、お客様が担当者を呼んでいます。LINE WORKS の個別ルームで返事をしてください。', array(), array( 'no_slack' => true ) );
		}
	},
	10,
	2
);

/* AIの返事 → ルームへ。 */
add_action(
	'apprex_chat_op_ai_reply',
	function ( $session, $reply ) {
		if ( ! apprex_lwb_ready() ) {
			return;
		}
		$uid   = get_current_user_id();
		$rooms = apprex_lwb_rooms();
		$key   = $uid ? 'wm:' . $uid : 'ws:' . $session;
		if ( ! empty( $rooms[ $key ]['c'] ) ) {
			apprex_lwb_say( $rooms[ $key ]['c'], '🤖 AI：' . $reply );
		}
	},
	10,
	2
);

/* 担当者を呼べるようにする（サイトのチャットの「担当者と話す」ボタン）。 */
add_filter(
	'apprex_chat_op_extra',
	function ( $on ) {
		return $on || apprex_lwb_ready();
	}
);

/** サイトのチャットに担当者の返事を入れる。 */
function apprex_lwb_web_push( $session, $text, $who ) {
	if ( ! function_exists( 'apprex_chat_op_get' ) ) {
		return;
	}
	$data          = apprex_chat_op_get( $session );
	$data['human'] = 'operator' === $who;
	$data['seq']   = (int) $data['seq'] + 1;
	$data['queue'][] = array( 'id' => $data['seq'], 'text' => $text, 'who' => $who );
	$data['queue']   = array_slice( $data['queue'], -50 );
	apprex_chat_op_set( $session, $data );
	if ( function_exists( 'apprex_chat_log_append' ) ) {
		apprex_chat_log_append( $session, 'operator' === $who ? '担当者' : 'システム', 'operator' === $who ? $text : 'AI自動応答に戻りました（担当者対応を終了）。' );
	}
}

/* =========================================================================
 * 担当者がルームに書いた → お客様へ（Callback）
 * ====================================================================== */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'apprex/v1',
			'/lineworks/callback',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => 'apprex_lwb_callback',
				'permission_callback' => '__return_true',
			)
		);
	}
);

function apprex_lwb_callback( WP_REST_Request $req ) {
	if ( 'GET' === $req->get_method() ) {
		return new WP_REST_Response( array( 'alive' => true, 'note' => 'APPREX LINE WORKS callback' ), 200 );
	}
	$raw    = (string) $req->get_body();
	$secret = apprex_lwb_opt( 'bot_secret' );
	$sig    = (string) $req->get_header( 'x_works_signature' );
	$calc   = base64_encode( hash_hmac( 'sha256', $raw, $secret, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	if ( '' === $secret || ! hash_equals( $calc, $sig ) ) {
		apprex_lwb_err( '受信：署名が合いません（Bot Secret を確認してください）' );
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}
	update_option( 'apprex_lwb_hook_last', time(), false );
	$ev = json_decode( $raw, true );
	if ( ! is_array( $ev ) || 'message' !== ( $ev['type'] ?? '' ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	$ch   = (string) ( $ev['source']['channelId'] ?? '' );
	$key  = '' !== $ch ? apprex_lwb_key_by_channel( $ch ) : '';
	$type = (string) ( $ev['content']['type'] ?? '' );
	$text = trim( (string) ( $ev['content']['text'] ?? '' ) );
	if ( '' === $key ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 ); // お客様とつながっていないルーム。
	}
	if ( 'text' !== $type || '' === $text ) {
		apprex_lwb_say( $ch, '⚠️ お客様には文字だけ送れます。画像などは送れませんでした。' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	if ( 0 === strpos( $key, 'test:' ) ) {
		apprex_lwb_say( $ch, '✅ 受け取りました：「' . mb_substr( $text, 0, 50 ) . '」。設定はOKです（このルームはテスト用なので、退出して大丈夫です）。' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	$back = in_array( strtolower( $text ), array( '/ai', 'ai', '/bot' ), true ) || 'AI復帰' === $text;
	$kind = substr( $key, 0, 3 );
	$id   = substr( $key, 3 );

	if ( 'ln:' === $kind ) {
		$hold = 'apprex_lwb_hold_' . md5( $id );
		if ( $back ) {
			delete_transient( $hold );
			apprex_lwb_say( $ch, '🤖 AIの自動返信に戻しました。' );
		} else {
			$r = function_exists( 'apprex_line_push' ) ? apprex_line_push( $id, array( array( 'type' => 'text', 'text' => mb_substr( $text, 0, 4900 ) ) ) ) : new WP_Error( 'line', 'LINEが未設定です' );
			if ( is_wp_error( $r ) ) {
				apprex_lwb_say( $ch, '⚠️ お客様のLINEに送れませんでした：' . $r->get_error_message() );
			} else {
				set_transient( $hold, 1, APPREX_LWB_HOLD );
			}
		}
	} else {
		$rooms   = apprex_lwb_rooms();
		$session = 'ws:' === $kind ? $id : (string) ( $rooms[ $key ]['s'] ?? '' );
		if ( '' === $session ) {
			apprex_lwb_say( $ch, '⚠️ お客様のチャットが見つからないため、送れませんでした。' );
		} elseif ( $back ) {
			apprex_lwb_web_push( $session, 'AIによる自動応答に戻りました。', 'system' );
			apprex_lwb_say( $ch, '🤖 AIの自動応答に戻しました。' );
		} else {
			apprex_lwb_web_push( $session, $text, 'operator' );
		}
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* =========================================================================
 * 設定（「APPREX 社内通知」の画面の下）
 * ====================================================================== */
add_action(
	'admin_init',
	function () {
		$keep = function ( $name ) {
			// 秘密の値は、空のまま保存したら前の値を残す。
			return function ( $v ) use ( $name ) {
				$v = trim( (string) $v );
				return '' === $v ? (string) get_option( $name, '' ) : $v;
			};
		};
		foreach ( array( 'client_id', 'sa', 'bot_id' ) as $k ) {
			register_setting( 'apprex_lwb', 'apprex_lwb_' . $k, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		}
		foreach ( array( 'client_secret', 'bot_secret', 'key' ) as $k ) {
			register_setting( 'apprex_lwb', 'apprex_lwb_' . $k, array( 'sanitize_callback' => $keep( 'apprex_lwb_' . $k ) ) );
		}
		register_setting( 'apprex_lwb', 'apprex_lwb_members', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		foreach ( array( 'client_id', 'client_secret', 'sa', 'key' ) as $k ) {
			add_action(
				'update_option_apprex_lwb_' . $k,
				function () {
					delete_transient( 'apprex_lwb_token' ); // 設定を変えたら、ログインし直す。
				}
			);
		}
	}
);

add_action(
	'admin_post_apprex_lwb_test',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_lwb_test' );
		$ch  = apprex_lwb_room( 'test:' . get_current_user_id(), 'テスト｜APPREXの個別ルーム', '【テスト】これは APPREX からのテストのルームです。このルームに何か書くと、Bot が「受け取りました」と返します（お客様には届きません）。' );
		$msg = '' !== $ch ? 'ルームを作りました。LINE WORKS を見てください。' : '失敗：' . ( get_option( 'apprex_lwb_last_err' )[1] ?? '' );
		set_transient( 'apprex_lwb_test_res', $msg, 300 );
		wp_safe_redirect( admin_url( 'options-general.php?page=apprex-notify#lwb' ) );
		exit;
	}
);

/** 設定の入力欄。 */
function apprex_lwb_settings_box() {
	$set = function ( $k ) {
		return '' !== apprex_lwb_opt( $k );
	};
	$err = get_option( 'apprex_lwb_last_err' );
	$res = get_transient( 'apprex_lwb_test_res' );
	delete_transient( 'apprex_lwb_test_res' );
	$hook = (int) get_option( 'apprex_lwb_hook_last', 0 );
	?>
	<h2 id="lwb">お客様ごとの個別ルーム（LINE WORKS Bot）</h2>
	<p>LINE・サイトのチャットでお客様からメッセージが来たら、<b>お客様ごとに LINE WORKS のトークルームを作ります</b>（2回目からは同じルーム）。ルームの名前は「LINE｜山田 太郎 さん」「サイト｜佐藤 花子 様（会員）」のように、誰か分かる名前にします。<br>
	担当者がそのルームに書くと、お客様に届きます（LINEのお客様には LINE公式アカウントから、サイトのお客様にはチャット画面に）。</p>
	<p>状態：<b><?php echo apprex_lwb_ready() ? '✅ 使えます' : '⛔ 未設定（下の項目をすべて入れると使えます）'; ?></b>
	<?php if ( $hook ) : ?>／最後に LINE WORKS から受信：<?php echo esc_html( wp_date( 'n/j G:i', $hook ) ); ?><?php endif; ?>
	<?php if ( is_array( $err ) && time() - (int) $err[0] < 3 * DAY_IN_SECONDS ) : ?><br><span style="color:#b32d2e">最近のエラー（<?php echo esc_html( wp_date( 'n/j G:i', (int) $err[0] ) ); ?>）：<?php echo esc_html( $err[1] ); ?></span><?php endif; ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'apprex_lwb' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Callback URL</th><td><code><?php echo esc_html( rest_url( 'apprex/v1/lineworks/callback' ) ); ?></code><p class="description">Developer Console の Bot の「Callback URL」に、これをそのまま入れます。</p></td></tr>
			<tr><th scope="row"><label for="lwb_client_id">Client ID</label></th><td><input id="lwb_client_id" name="apprex_lwb_client_id" class="regular-text" value="<?php echo esc_attr( apprex_lwb_opt( 'client_id' ) ); ?>"></td></tr>
			<tr><th scope="row"><label for="lwb_client_secret">Client Secret</label></th><td><input id="lwb_client_secret" type="password" name="apprex_lwb_client_secret" class="regular-text" value="" placeholder="<?php echo $set( 'client_secret' ) ? '入っています（変えるときだけ入力）' : ''; ?>" autocomplete="new-password"></td></tr>
			<tr><th scope="row"><label for="lwb_sa">Service Account</label></th><td><input id="lwb_sa" name="apprex_lwb_sa" class="regular-text" value="<?php echo esc_attr( apprex_lwb_opt( 'sa' ) ); ?>" placeholder="xxxxx.serviceaccount@..."></td></tr>
			<tr><th scope="row"><label for="lwb_key">Private Key</label></th><td><textarea id="lwb_key" name="apprex_lwb_key" rows="4" class="large-text code" placeholder="<?php echo $set( 'key' ) ? '入っています（変えるときだけ貼る）' : '-----BEGIN PRIVATE KEY----- から -----END PRIVATE KEY----- まで'; ?>"></textarea></td></tr>
			<tr><th scope="row"><label for="lwb_bot_id">Bot ID</label></th><td><input id="lwb_bot_id" name="apprex_lwb_bot_id" class="regular-text" value="<?php echo esc_attr( apprex_lwb_opt( 'bot_id' ) ); ?>"></td></tr>
			<tr><th scope="row"><label for="lwb_bot_secret">Bot Secret</label></th><td><input id="lwb_bot_secret" type="password" name="apprex_lwb_bot_secret" class="regular-text" value="" placeholder="<?php echo $set( 'bot_secret' ) ? '入っています（変えるときだけ入力）' : ''; ?>" autocomplete="new-password"></td></tr>
			<tr><th scope="row"><label for="lwb_members">ルームに入れる担当者</label></th><td><textarea id="lwb_members" name="apprex_lwb_members" rows="3" class="large-text" placeholder="taro@example.co.jp"><?php echo esc_textarea( apprex_lwb_opt( 'members' ) ); ?></textarea><p class="description">LINE WORKS のログインID（メールアドレスの形）を1行に1人。ここに入れた人が、すべてのお客様のルームに入ります。</p></td></tr>
		</table>
		<?php submit_button( '個別ルームの設定を保存する' ); ?>
	</form>
	<?php if ( $res ) : ?><div class="notice notice-info inline"><p><?php echo esc_html( $res ); ?></p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="apprex_lwb_test">
		<?php wp_nonce_field( 'apprex_lwb_test' ); ?>
		<?php submit_button( 'テストのルームを作る', 'secondary', 'submit', false, apprex_lwb_ready() ? array() : array( 'disabled' => 'disabled' ) ); ?>
	</form>
	<?php
	$rooms = apprex_lwb_rooms();
	if ( $rooms ) {
		uasort(
			$rooms,
			function ( $a, $b ) {
				return (int) ( $b['last'] ?? 0 ) - (int) ( $a['last'] ?? 0 );
			}
		);
		echo '<h3>作ったルーム（新しい順）</h3><table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( array_slice( $rooms, 0, 30, true ) as $r ) {
			echo '<tr><td>' . esc_html( $r['t'] ?? '' ) . '</td><td style="width:140px">' . esc_html( wp_date( 'n/j G:i', (int) ( $r['last'] ?? 0 ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
