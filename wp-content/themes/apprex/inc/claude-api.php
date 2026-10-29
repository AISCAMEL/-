<?php
/**
 * Claude API（Anthropic）で文章を作る。
 *
 * - Claude のAPIキーを入れると、Claude を使う範囲（設定）に応じて文章を Claude で作る。
 *   「チャットだけ」（既定）：サイトのAIチャットとLINEのAI自動返信だけ Claude、ほかは OpenRouter。
 *   「チャットと記事」：上に加えて AI記事も Claude（毎日の自動投稿では、Claude が Web で最新情報を調べて書ける）。
 *   「文章すべて」：AIスタジオ・ホームページのAI・AIMEO・AI記事なども Claude。
 * - 画像の生成は Claude ではできないため、これまでどおり OpenRouter のキーで行う。
 * - Claude で失敗したときは、OpenRouter のキーがあれば OpenRouter でやり直す。
 *
 * 設定：設定 → APPREX チャット（Claude APIキー・モデル）
 * キーは wp-config.php の定数 APPREX_CLAUDE_API_KEY でも設定できる（こちらが優先）。
 *
 * WordPress のテーマとして zip で入れるため、SDK（Composer）を同梱せず、
 * ほかの外部連携と同じく wp_remote_post で Messages API を呼ぶ。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const APPREX_CLAUDE_ENDPOINT      = 'https://api.anthropic.com/v1/messages';
const APPREX_CLAUDE_API_VERSION   = '2026-10-21';
const APPREX_CLAUDE_DEFAULT_MODEL = 'claude-opus-5-5';

/** Claude APIキー（定数 → 設定の順）。 */
function apprex_claude_key() {
	if ( defined( 'APPREX_CLAUDE_API_KEY' ) && APPREX_CLAUDE_API_KEY ) {
		return (string) APPREX_CLAUDE_API_KEY;
	}
	return trim( (string) get_option( 'apprex_claude_api_key', '' ) );
}

/** チャット・LINE・記事などに使う Claude のモデル。 */
function apprex_claude_model() {
	$m = trim( (string) get_option( 'apprex_claude_model', '' ) );
	return $m ? $m : APPREX_CLAUDE_DEFAULT_MODEL;
}

/** AI記事（毎日の記事・記事の見直し）に使う Claude のモデル。未入力ならチャットと同じ。 */
function apprex_claude_article_model() {
	$m = trim( (string) get_option( 'apprex_claude_article_model', '' ) );
	return 0 === strpos( $m, 'claude-' ) ? $m : apprex_claude_model();
}

/** AIスタジオ（デザイン・試作・仕様書）に使う Claude のモデル。 */
function apprex_claude_studio_model() {
	$m = trim( (string) get_option( 'apprex_claude_studio_model', '' ) );
	return $m ? $m : APPREX_CLAUDE_DEFAULT_MODEL;
}

/** Claude のキーがあるか。 */
function apprex_ai_use_claude() {
	return '' !== apprex_claude_key();
}

/** Claude を使う範囲：chat（チャット・LINEだけ）／chat_article（チャットと記事）／all（文章すべて）。 */
function apprex_claude_scope() {
	$v = get_option( 'apprex_claude_scope', 'chat' );
	return in_array( $v, array( 'chat', 'chat_article', 'all' ), true ) ? $v : 'chat';
}

/**
 * この文章を Claude で作るか。
 *
 * @param bool|string $kind true または 'chat'＝チャット・LINE／'article'＝AI記事／false＝そのほか。
 * @return bool
 */
function apprex_claude_for( $kind ) {
	if ( ! apprex_ai_use_claude() ) {
		return false;
	}
	$scope = apprex_claude_scope();
	if ( true === $kind || 'chat' === $kind ) {
		return true;
	}
	if ( 'article' === $kind ) {
		return 'all' === $scope || 'chat_article' === $scope;
	}
	return 'all' === $scope;
}

/**
 * 文章のAIが使えるか。
 *
 * @param bool $chat チャット（サイト・LINE）の確認なら true。
 * @return bool
 */
function apprex_ai_text_ready( $chat = false ) {
	return apprex_claude_for( $chat ) || ( function_exists( 'apprex_openrouter_key' ) && '' !== apprex_openrouter_key() );
}

/** いま文章に使っているAIの表示名（管理画面用）。 */
function apprex_ai_text_label() {
	$or = function_exists( 'apprex_openrouter_model' ) && function_exists( 'apprex_openrouter_key' ) && '' !== apprex_openrouter_key() ? 'OpenRouter（' . apprex_openrouter_model() . '）' : '未設定';
	if ( ! apprex_ai_use_claude() ) {
		return $or;
	}
	if ( 'all' === apprex_claude_scope() ) {
		return 'Claude（' . apprex_claude_model() . '）';
	}
	return ( 'chat_article' === apprex_claude_scope() ? 'チャット・LINE・記事' : 'チャット・LINE' ) . '：Claude（' . apprex_claude_model() . '）／そのほか：' . $or;
}

/**
 * OpenRouter 形式のメッセージ（role: system/user/assistant）を Claude の形に変える。
 *
 * @param array $messages [{role, content}, ...]。
 * @return array { system: string, messages: array }
 */
function apprex_claude_convert( $messages ) {
	$system = array();
	$out    = array();
	foreach ( (array) $messages as $m ) {
		$role = isset( $m['role'] ) ? $m['role'] : 'user';
		$text = isset( $m['content'] ) ? $m['content'] : '';
		if ( is_array( $text ) ) {
			// [{type:text,text}] の形にも対応（文章だけ取り出す）。
			$parts = array();
			foreach ( $text as $p ) {
				if ( is_array( $p ) && isset( $p['text'] ) ) {
					$parts[] = (string) $p['text'];
				}
			}
			$text = implode( "\n", $parts );
		}
		$text = (string) $text;
		if ( 'system' === $role ) {
			if ( '' !== trim( $text ) ) {
				$system[] = $text;
			}
			continue;
		}
		$role = 'assistant' === $role ? 'assistant' : 'user';
		if ( '' === trim( $text ) ) {
			continue;
		}
		// 最初はお客様の発言から始める（チャット画面の最初のあいさつなどは除く）。
		if ( ! $out && 'assistant' === $role ) {
			continue;
		}
		// 同じ話し手が続いたら1つにまとめる。
		$last = count( $out ) - 1;
		if ( $last >= 0 && $out[ $last ]['role'] === $role ) {
			$out[ $last ]['content'] .= "\n\n" . $text;
			continue;
		}
		$out[] = array( 'role' => $role, 'content' => $text );
	}
	// 最後がAIの発言だと続きを書かせる形になり使えないので、外す。
	while ( $out && 'assistant' === $out[ count( $out ) - 1 ]['role'] ) {
		array_pop( $out );
	}
	return array(
		'system'   => implode( "\n\n", $system ),
		'blocks'   => $system, // system ごと（1つ目＝毎回同じ部分。チャットでは使い回しの対象）。
		'messages' => $out,
	);
}

/**
 * Claude で文章を作る。
 *
 * @param array $messages [{role, content}, ...]（system を含めてよい）。
 * @param array $args     model / max_tokens / timeout / effort（low|medium|high）。
 * @return string|WP_Error 生成した文章。
 */
function apprex_claude_complete( $messages, $args = array() ) {
	$key = apprex_claude_key();
	if ( '' === $key ) {
		return new WP_Error( 'not_configured', 'Claude APIキーが未設定です（設定 → APPREX チャット）。' );
	}
	$conv = apprex_claude_convert( $messages );
	if ( ! $conv['messages'] ) {
		return new WP_Error( 'bad_request', '送る内容がありません。' );
	}
	$model = ( isset( $args['model'] ) && 0 === strpos( (string) $args['model'], 'claude-' ) ) ? (string) $args['model'] : apprex_claude_model();
	// 考える時間も出力の上限に含まれるので、呼び出し側の上限より余裕をもたせる（長さは指示文で決める）。
	$want    = isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 600;
	$payload = array(
		'model'         => $model,
		'max_tokens'    => max( 4000, min( 16000, $want * 4 ) ),
		'messages'      => $conv['messages'],
		'output_config' => array( 'effort' => isset( $args['effort'] ) ? (string) $args['effort'] : 'low' ),
	);
	// 安全のための判定で断られたときは、Anthropic が勧めるモデルで自動的にやり直す（Haiku は対象外）。
	if ( apprex_claude_has_fallback( $model ) ) {
		$payload['fallbacks'] = 'default';
	}
	if ( '' !== $conv['system'] ) {
		$payload['system'] = $conv['system'];
		// チャット：毎回同じ指示（ナレッジ・料金など）を使い回して、2回目からの読み込みを安くする（プロンプトキャッシュ）。
		// 1つ目の system が毎回同じ部分、2つ目以降がページ・LINE向けなどの変わる部分。
		if ( ! empty( $args['chat'] ) ) {
			$blocks = array();
			foreach ( $conv['blocks'] as $i => $t ) {
				$b = array( 'type' => 'text', 'text' => $t );
				if ( 0 === $i ) {
					$b['cache_control'] = array( 'type' => 'ephemeral' );
				}
				$blocks[] = $b;
			}
			$payload['system'] = $blocks;
		}
	}
	$payload = apply_filters( 'apprex_claude_payload', $payload, $args );

	$res = wp_remote_post(
		APPREX_CLAUDE_ENDPOINT,
		array(
			'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 60,
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version' => APPREX_CLAUDE_API_VERSION,
				'anthropic-beta'    => 'server-side-fallback-2026-07-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'upstream_error', 'Claude に接続できませんでした（' . $res->get_error_message() . '）。' );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 !== $code || ! is_array( $body ) ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : '';
		return new WP_Error( 'upstream_error', 'Claude での生成に失敗しました（HTTP ' . $code . '）。' . ( $msg ? ' ' . $msg : '' ) );
	}
	$stop = isset( $body['stop_reason'] ) ? (string) $body['stop_reason'] : '';
	if ( 'refusal' === $stop ) {
		return new WP_Error( 'refusal', 'この内容にはお答えできませんでした。' );
	}
	$text = '';
	foreach ( isset( $body['content'] ) ? (array) $body['content'] : array() as $block ) {
		if ( isset( $block['type'] ) && 'text' === $block['type'] && isset( $block['text'] ) ) {
			$text .= $block['text'];
		}
	}
	$text = trim( $text );
	if ( '' === $text ) {
		return new WP_Error( 'upstream_error', 'Claude の返答が空でした（' . ( $stop ? $stop : 'unknown' ) . '）。' );
	}
	return $text;
}

/* =========================================================================
 * 設定（設定 → APPREX チャット に入力欄を追加）
 * ====================================================================== */
add_action( 'admin_init', function () {
	register_setting( 'apprex_chat', 'apprex_claude_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_chat', 'apprex_claude_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_chat', 'apprex_claude_studio_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_chat', 'apprex_claude_article_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting(
		'apprex_chat',
		'apprex_claude_scope',
		array(
			'sanitize_callback' => function ( $v ) {
				return in_array( $v, array( 'chat', 'chat_article', 'all' ), true ) ? $v : 'chat';
			},
		)
	);
} );

/** 設定画面の Claude の欄（apprex_chat_settings_page から呼ぶ）。 */
function apprex_claude_settings_rows() {
	$const = defined( 'APPREX_CLAUDE_API_KEY' ) && APPREX_CLAUDE_API_KEY;
	?>
	<tr>
		<th scope="row">Claude APIキー</th>
		<td>
			<?php if ( $const ) : ?>
				<p><em>wp-config.php の APPREX_CLAUDE_API_KEY で設定済みです（こちらが優先されます）。</em></p>
			<?php else : ?>
				<input type="password" name="apprex_claude_api_key" value="<?php echo esc_attr( get_option( 'apprex_claude_api_key', '' ) ); ?>" class="regular-text" autocomplete="off" placeholder="sk-ant-...">
			<?php endif; ?>
			<p class="description">入れると、下の「Claude を使う範囲」で選んだ文章のAIが Claude で動きます。画像の生成は OpenRouter のキーを使います。<br>いま文章に使っているAI：<strong><?php echo esc_html( apprex_ai_text_label() ); ?></strong></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="apprex_claude_scope">Claude を使う範囲</label></th>
		<td>
			<select id="apprex_claude_scope" name="apprex_claude_scope">
				<option value="chat" <?php selected( 'chat', apprex_claude_scope() ); ?>>チャットだけ（サイトのAIチャット・LINEの自動返信）</option>
				<option value="chat_article" <?php selected( 'chat_article', apprex_claude_scope() ); ?>>チャットと記事（AI記事・毎日の自動投稿も Claude）</option>
				<option value="all" <?php selected( 'all', apprex_claude_scope() ); ?>>文章すべて（AIスタジオ・ホームページのAI・AIMEO・AI記事・LABOも）</option>
			</select>
			<p class="description">「チャットだけ」なら、それ以外は今までどおり OpenRouter で動きます。Claude で失敗したときは OpenRouter でやり直します。</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Claude のモデル（チャット・LINE）</th>
		<td>
			<input type="text" name="apprex_claude_model" value="<?php echo esc_attr( get_option( 'apprex_claude_model', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( APPREX_CLAUDE_DEFAULT_MODEL ); ?>">
			<p class="description">未入力時は <?php echo esc_html( APPREX_CLAUDE_DEFAULT_MODEL ); ?>。チャットには claude-sonnet-5-5（おすすめ）や、さらに安い claude-haiku-5-5 も選べます。「文章すべて」のときは AI記事・AIMEO にも使います。</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Claude のモデル（記事）</th>
		<td>
			<input type="text" name="apprex_claude_article_model" value="<?php echo esc_attr( get_option( 'apprex_claude_article_model', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( apprex_claude_model() ); ?>">
			<p class="description">毎日の記事・記事の見直しに使います（「チャットと記事」「文章すべて」のとき）。未入力ならチャットと同じモデル。質を上げたいときは claude-opus-5-5（費用はおよそ2倍）。</p>
		</td>
	</tr>
	<tr>
		<th scope="row">Claude のモデル（AIスタジオ）</th>
		<td>
			<input type="text" name="apprex_claude_studio_model" value="<?php echo esc_attr( get_option( 'apprex_claude_studio_model', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( APPREX_CLAUDE_DEFAULT_MODEL ); ?>">
			<p class="description">「文章すべて」のときだけ使います（AIデザイナー・試作・仕様定義書・ホームページのAI・LABO）。未入力時は <?php echo esc_html( APPREX_CLAUDE_DEFAULT_MODEL ); ?>。</p>
		</td>
	</tr>
	<?php
}

/* 動作テスト：設定画面のボタンから1回だけ Claude を呼ぶ。 */
add_action( 'admin_post_apprex_claude_test', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'apprex_claude_test' ) ) {
		wp_die( '権限がありません。' );
	}
	$t0 = microtime( true );
	$r  = apprex_claude_complete(
		array(
			array( 'role' => 'system', 'content' => 'あなたはAPPREXのアシスタントです。1文で短くあいさつしてください。' ),
			array( 'role' => 'user', 'content' => 'こんにちは' ),
		),
		array( 'max_tokens' => 100, 'timeout' => 40 )
	);
	$sec = round( microtime( true ) - $t0, 1 );
	$msg = is_wp_error( $r ) ? 'NG：' . $r->get_error_message() : 'OK（' . apprex_claude_model() . '・' . $sec . '秒）：' . mb_substr( $r, 0, 80 );
	set_transient( 'apprex_claude_test_' . get_current_user_id(), $msg, 120 );
	wp_safe_redirect( admin_url( 'options-general.php?page=apprex-chat#apprex-claude-test' ) );
	exit;
} );

/** 設定画面の「Claude の動作テスト」（フォームの外に出す）。 */
function apprex_claude_test_box() {
	$msg = get_transient( 'apprex_claude_test_' . get_current_user_id() );
	delete_transient( 'apprex_claude_test_' . get_current_user_id() );
	?>
	<hr>
	<h2 id="apprex-claude-test">Claude の動作テスト</h2>
	<?php if ( $msg ) : ?>
		<div class="notice <?php echo 0 === strpos( $msg, 'OK' ) ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo esc_html( $msg ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="apprex_claude_test">
		<?php wp_nonce_field( 'apprex_claude_test' ); ?>
		<p><button class="button" <?php disabled( ! apprex_ai_use_claude() ); ?>>Claude に「こんにちは」と送ってみる</button>
		<?php if ( ! apprex_ai_use_claude() ) : ?><span class="description">先に Claude APIキーを入れて保存してください。</span><?php endif; ?></p>
	</form>
	<?php
}

/** 断られたときの自動やり直し（fallbacks）に対応したモデルか。 */
function apprex_claude_has_fallback( $model ) {
	return 0 !== strpos( (string) $model, 'claude-haiku' );
}

/**
 * Claude が Web で最新の情報を調べてから、記事を書く（毎日の自動投稿用）。
 *
 * Web 検索・Web 取得は Anthropic のサーバーで動く道具（server tools）なので、
 * こちらは結果を受け取るだけ。長くかかったとき（pause_turn）は続きを頼む。
 *
 * @param string $system 指示（APPREXの説明・守ること）。
 * @param string $user   依頼文。
 * @param array  $args   model / timeout / searches。
 * @return array|WP_Error { text, sources: [ [url, title], ... ] }
 */
function apprex_claude_research( $system, $user, $args = array() ) {
	$key = apprex_claude_key();
	if ( '' === $key ) {
		return new WP_Error( 'not_configured', 'Claude APIキーが未設定です（設定 → APPREX チャット）。' );
	}
	$model = isset( $args['model'] ) && 0 === strpos( (string) $args['model'], 'claude-' ) ? (string) $args['model'] : apprex_claude_model();
	// Haiku は基本の検索だけ。ほかは結果を絞り込める新しい版（うまくいかなければ基本の版でやり直す）。
	$haiku = 0 === strpos( $model, 'claude-haiku' ) || ! empty( $args['basic'] );
	$tools = array(
		array(
			'type'          => $haiku ? 'web_search_20250305' : 'web_search_20260209',
			'name'          => 'web_search',
			'max_uses'      => isset( $args['searches'] ) ? (int) $args['searches'] : 8,
			'user_location' => array( 'type' => 'approximate', 'country' => 'JP', 'timezone' => 'Asia/Tokyo' ),
		),
		array(
			'type'     => $haiku ? 'web_fetch_20250910' : 'web_fetch_20260209',
			'name'     => 'web_fetch',
			'max_uses' => 3,
		),
	);
	$messages = array( array( 'role' => 'user', 'content' => $user ) );
	$sources  = array();
	$text     = '';
	$found    = 0;       // 見つかった検索結果・取得できたページの数。
	$errors   = array(); // Web検索・取得のエラー（error_code）。
	for ( $round = 0; $round < 4; $round++ ) {
		$payload = array(
			'model'         => $model,
			'max_tokens'    => 16000,
			'system'        => $system,
			'messages'      => $messages,
			'tools'         => $tools,
			'output_config' => array( 'effort' => 'medium' ),
		);
		if ( apprex_claude_has_fallback( $model ) ) {
			$payload['fallbacks'] = 'default';
		}
		$res = wp_remote_post(
			APPREX_CLAUDE_ENDPOINT,
			array(
				'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 300,
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version' => APPREX_CLAUDE_API_VERSION,
					'anthropic-beta'    => 'server-side-fallback-2026-07-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'upstream_error', 'Claude に接続できませんでした（' . $res->get_error_message() . '）。' );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : '';
			return new WP_Error( 'upstream_error', 'Claude での記事づくりに失敗しました（HTTP ' . $code . '）。' . ( $msg ? ' ' . $msg : '' ) );
		}
		$stop = isset( $body['stop_reason'] ) ? (string) $body['stop_reason'] : '';
		if ( 'refusal' === $stop ) {
			return new WP_Error( 'refusal', 'この内容では記事を作れませんでした。' );
		}
		$content = isset( $body['content'] ) && is_array( $body['content'] ) ? $body['content'] : array();
		foreach ( $content as $block ) {
			$type = isset( $block['type'] ) ? $block['type'] : '';
			// Web検索・取得の結果。失敗は HTTP 200 のまま、content が { error_code } の形で届く。
			if ( 'web_search_tool_result' === $type || 'web_fetch_tool_result' === $type ) {
				$c = isset( $block['content'] ) ? $block['content'] : null;
				if ( is_array( $c ) && isset( $c['error_code'] ) ) {
					$errors[] = (string) $c['error_code'];
				} elseif ( 'web_search_tool_result' === $type && is_array( $c ) ) {
					$found += count( $c );
				} elseif ( is_array( $c ) ) {
					$found++;
				}
			}
			if ( 'text' === $type ) {
				$text .= isset( $block['text'] ) ? $block['text'] : '';
				// 本文が引用した出典。
				foreach ( isset( $block['citations'] ) ? (array) $block['citations'] : array() as $c ) {
					if ( ! empty( $c['url'] ) ) {
						$sources[ $c['url'] ] = isset( $c['title'] ) ? (string) $c['title'] : $c['url'];
					}
				}
			}
		}
		if ( 'pause_turn' !== $stop ) {
			break;
		}
		// 調べものの途中で区切られた：ここまでの返答を渡して続きを頼む。
		$messages[] = array( 'role' => 'assistant', 'content' => $content );
		$text       = '';
	}
	$text = trim( $text );
	// 調べられなかったときは記事にしない（「作れませんでした」という文章を記事にしない）。
	if ( ( 0 === $found && empty( $args['optional'] ) ) || preg_match( '/^\s*ERROR\s*[:：]/mu', $text ) ) {
		$why = array(
			'max_uses_exceeded' => '検索の回数の上限',
			'too_many_requests' => '短い時間に検索が集中した（しばらく待つと直ります）',
			'unavailable'       => 'Web検索が使えない状態（Console の組織の設定で Web検索 がオンかご確認ください）',
			'query_too_long'    => '検索の言葉が長すぎた',
			'invalid_input'     => '検索の指定が正しくない',
		);
		$msg = array();
		foreach ( array_unique( $errors ) as $e ) {
			$msg[] = isset( $why[ $e ] ) ? $why[ $e ] . '（' . $e . '）' : $e;
		}
		// 新しい版の検索で1件も取れなかったときは、基本の版で1回だけやり直す。
		if ( empty( $args['basic'] ) && 0 !== strpos( $model, 'claude-haiku' ) ) {
			return apprex_claude_research( $system, $user, array_merge( $args, array( 'basic' => true ) ) );
		}
		return new WP_Error( 'no_research', 'Web検索ができなかったため、記事は作りませんでした。' . ( $msg ? '理由：' . implode( '／', $msg ) . '。' : '' ) . 'Anthropic Console の設定（組織の Web検索 がオンか、利用上限）をご確認ください。' );
	}
	if ( '' === $text ) {
		return new WP_Error( 'upstream_error', 'Claude の記事が空でした。' );
	}
	$list = array();
	foreach ( $sources as $u => $t ) {
		$list[] = array( $u, $t );
	}
	return array( 'text' => $text, 'sources' => array_slice( $list, 0, 6 ) );
}
