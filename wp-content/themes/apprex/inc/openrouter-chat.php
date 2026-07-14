<?php
/**
 * OpenRouter-powered in-site chatbot.
 *
 * Architecture: the browser never sees the API key. The chat widget POSTs the
 * conversation to a WordPress REST endpoint, which proxies the request to
 * OpenRouter server-side and returns the assistant reply.
 *
 * Configuration (priority order):
 *   1. Constant in wp-config.php:  define( 'APPREX_OPENROUTER_API_KEY', 'sk-or-...' );
 *   2. Option (Settings > APPREX チャット):  apprex_openrouter_api_key
 * Model:  constant APPREX_OPENROUTER_MODEL or option apprex_openrouter_model
 *         (default below — any OpenRouter model id is accepted).
 *
 * Local testing without network: define( 'APPREX_CHAT_MOCK', true );
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const APPREX_OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';
// 安定して使える既定モデル（OpenAI直・安価・広く利用可）。設定 > APPREX チャットで変更可。
const APPREX_OPENROUTER_DEFAULT_MODEL = 'openai/gpt-4o-mini';

/**
 * Resolve the API key from constant or option.
 *
 * @return string
 */
function apprex_openrouter_key() {
	if ( defined( 'APPREX_OPENROUTER_API_KEY' ) && APPREX_OPENROUTER_API_KEY ) {
		return (string) APPREX_OPENROUTER_API_KEY;
	}
	return (string) get_option( 'apprex_openrouter_api_key', '' );
}

/**
 * Resolve the model id.
 *
 * @return string
 */
function apprex_openrouter_model() {
	if ( defined( 'APPREX_OPENROUTER_MODEL' ) && APPREX_OPENROUTER_MODEL ) {
		return (string) APPREX_OPENROUTER_MODEL;
	}
	$opt = get_option( 'apprex_openrouter_model', '' );
	return $opt ? (string) $opt : APPREX_OPENROUTER_DEFAULT_MODEL;
}

/**
 * Whether the chatbot is operational (key present or mock mode).
 *
 * @return bool
 */
function apprex_chat_enabled() {
	return ( defined( 'APPREX_CHAT_MOCK' ) && APPREX_CHAT_MOCK ) || '' !== apprex_openrouter_key();
}

/**
 * Reusable OpenRouter chat-completion call.
 *
 * @param array $messages [{role, content}, ...] including any system message.
 * @param array $args     Optional: model, temperature, max_tokens.
 * @return string|WP_Error Assistant content or error.
 */
function apprex_openrouter_complete( $messages, $args = array() ) {
	$api_key = apprex_openrouter_key();
	if ( '' === $api_key ) {
		return new WP_Error( 'not_configured', 'OpenRouter APIキーが未設定です（設定 > APPREX チャット）。' );
	}

	$payload = array(
		'model'       => isset( $args['model'] ) ? $args['model'] : apprex_openrouter_model(),
		'messages'    => $messages,
		'temperature' => isset( $args['temperature'] ) ? (float) $args['temperature'] : 0.4,
		'max_tokens'  => isset( $args['max_tokens'] ) ? (int) $args['max_tokens'] : 600,
		// 提供終了プロバイダ等で失敗しても、生きている提供元へ自動フォールバック。
		'provider'    => isset( $args['provider'] ) ? $args['provider'] : array(
			'allow_fallbacks' => true,
		),
	);
	$payload = apply_filters( 'apprex_openrouter_payload', $payload, $args );

	$response = wp_remote_post(
		APPREX_OPENROUTER_ENDPOINT,
		array(
			'timeout' => isset( $args['timeout'] ) ? (int) $args['timeout'] : 45,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url( '/' ),
				'X-Title'       => 'APPREX',
			),
			'body'    => wp_json_encode( $payload ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'upstream_error', '生成サービスに接続できませんでした。' );
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code || empty( $body['choices'][0]['message']['content'] ) ) {
		$err    = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
		$detail = ! empty( $err['message'] ) ? ' ' . $err['message'] : '';
		if ( ! empty( $err['metadata']['provider_name'] ) ) {
			$detail .= ' [' . $err['metadata']['provider_name'] . ']';
		}
		if ( ! empty( $err['metadata']['raw'] ) ) {
			$raw     = $err['metadata']['raw'];
			$detail .= ' / ' . ( is_string( $raw ) ? $raw : wp_json_encode( $raw, JSON_UNESCAPED_UNICODE ) );
		}
		return new WP_Error( 'upstream_error', '生成に失敗しました（HTTP ' . $code . '）。' . $detail );
	}
	return (string) $body['choices'][0]['message']['content'];
}

/**
 * Editable knowledge base (Settings > APPREX チャット). Injected into the prompt.
 *
 * @return string
 */
function apprex_chat_knowledge() {
	return trim( (string) get_option( 'apprex_chat_knowledge', '' ) );
}

/**
 * System prompt: APPREX persona + live pricing/services + admin knowledge base.
 *
 * @return string
 */
function apprex_chat_system_prompt() {
	$pricing = apprex_pricing_summary_text();
	$prompt  = <<<PROMPT
あなたは「合同会社アイズ」が運営するノーコードアプリ開発プラットフォーム「APPREX（アプリックス）」の公式サイトに常駐する、親しみやすく丁寧なカスタマーサポートAIです。

# 役割
- 来訪者の質問に正確・簡潔に答え、顧客満足を最優先する。
- 必要に応じて「見積もり」や「無料体験」「お問い合わせ」へ自然に案内する。
- 分からないことは正直に伝え、担当者へのお問い合わせを促す。推測で誤った金額や仕様を断定しない。
- 日本語で、絵文字は控えめに、敬体（です・ます）で回答する。1〜3文程度を基本に簡潔に。

# 回答の書式（チャット表示用・厳守）
- 小さなチャット欄に表示される。**長文や過度な装飾は避け、簡潔に**まとめる。
- 箇条書きが必要なときは行頭に「- 」を使い、各項目は短く（1行）。項目ごとに改行する。
- 見出しの「#」記号やコードブロック（```）は使わない。強調は控えめに（必要時のみ **太字**）。
- **リンクは必ずURLをそのまま記載**する（例：https://example.com/ ）。画面側で自動でクリック可能になる。「こちら」だけでURLを隠さない。

# APPREXの基本情報
- ノーコードでiOS/Androidアプリを開発・運営できるプラットフォーム。制作代行・ホームページ制作も提供。
- 強み：高性能・低価格（従来の1/10）・スピード公開（最短2週間）・専任サポート・分析機能。
- 導入実績8,000+。電話窓口は無し。**AIチャットは24時間対応**。担当者（有人）対応は **9:00〜18:00**。
- 時間外に担当者を希望された場合は、要件を伺ってAIで可能な範囲を案内し、必要ならメール相談（担当者が後ほど返信）へ誘導する。

# 料金（最新・税抜）
{$pricing}

# 見積もり・発注の案内
- 具体的な料金を知りたい人には、サイトの「見積もりフォーム（/estimate）」で、サービス・プラン・オプションを選ぶと概算が出て、そのまま発注（仮申込）まで進めることを伝える。
- 「即日公開」という表現は使わない（「スピード公開・最短2週間」と表現する）。
PROMPT;

	$knowledge = apprex_chat_knowledge();
	if ( '' !== $knowledge ) {
		$prompt .= "\n\n# 追加ナレッジ（運営者がWP管理画面で登録した最新情報。最優先で参照する）\n" . $knowledge;
	}

	// よくある質問（学習データとして注入）。
	if ( function_exists( 'apprex_default_faqs' ) ) {
		$faqs = apprex_default_faqs();
		if ( $faqs ) {
			$prompt .= "\n\n# よくある質問（参考にし、自然な言葉で答える）\n";
			foreach ( $faqs as $f ) {
				$prompt .= '- Q: ' . $f['q'] . ' / A: ' . $f['a'] . "\n";
			}
		}
	}

	// 導入事例（業種の例として提示してよい）。
	$cases = get_posts( array( 'post_type' => 'case', 'posts_per_page' => 12, 'post_status' => 'publish' ) );
	if ( $cases ) {
		$prompt .= "\n# 導入事例（業種の例として挙げてよい）\n";
		foreach ( $cases as $c ) {
			$prompt .= '- ' . get_the_title( $c ) . "\n";
		}
	}

	// 後追い（リード獲得）ルール。
	$meet    = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '';
	$prompt .= "\n# オリジナル／カスタムアプリのご相談（重要）\n";
	$prompt .= "- 「オリジナルアプリを作りたい」「独自アプリ」「フルスクラッチ」「マッチングアプリ」「業務システム」「こんなアプリは作れる?」など、標準プランに収まらない開発を希望された場合は、料金を推測で断定せず『内容により個別お見積りになります』と伝える。\n";
	if ( $meet ) {
		$prompt .= "- そのうえで、まず無料のオンライン相談（Webミーティング予約：" . $meet . "）を**最優先で案内**し、要件を直接ヒアリングする流れに誘導する。見積もりフォーム（" . home_url( '/estimate/' ) . "）の『要相談メニュー』からも予約できる旨を添えてよい。\n";
	}
	$prompt .= "\n# 誘導・後追いのルール\n";
	$prompt .= "- 料金や導入に前向きな様子が見えたら、具体的に次の行動を提案する：見積もり（" . home_url( '/estimate/' ) . "）、無料体験（" . home_url( '/free-trial/' ) . "）" . ( $meet ? '、Webミーティング予約（' . $meet . '）' : '' ) . "。\n";
	$prompt .= "- 検討中の相手には、よろしければメールアドレスを伺い、『担当より詳しくご連絡します』と伝える（しつこくしない）。メールをいただけたら、担当からの後追い連絡・フォローメールに繋げる旨を一言添える。\n";
	$prompt .= "- お問い合わせ窓口：" . home_url( '/contact/' ) . "（電話窓口は無し）。\n";

	return apply_filters( 'apprex_chat_system_prompt', $prompt );
}

/**
 * Register the REST route.
 */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/chat',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_rest_chat',
			'permission_callback' => '__return_true', // Public widget; protected by nonce + throttle below.
		)
	);
} );

/**
 * REST handler: validate, throttle, proxy to OpenRouter.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function apprex_rest_chat( WP_REST_Request $request ) {
	// Nonce check (sent by the widget as X-WP-Nonce).
	$nonce = $request->get_header( 'x_wp_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
		return new WP_Error( 'forbidden', '不正なリクエストです。ページを再読み込みしてください。', array( 'status' => 403 ) );
	}

	// Simple per-IP throttle: max 20 requests / 60s.
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'anon';
	$key = 'apprex_chat_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 20 ) {
		return new WP_Error( 'rate_limited', '混み合っています。しばらくしてからお試しください。', array( 'status' => 429 ) );
	}
	set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

	// Parse + sanitize history.
	$raw = $request->get_param( 'messages' );
	if ( ! is_array( $raw ) ) {
		return new WP_Error( 'bad_request', 'メッセージがありません。', array( 'status' => 400 ) );
	}
	$messages = array();
	foreach ( array_slice( $raw, -12 ) as $m ) { // Cap history length.
		$role    = isset( $m['role'] ) && in_array( $m['role'], array( 'user', 'assistant' ), true ) ? $m['role'] : 'user';
		$content = isset( $m['content'] ) ? sanitize_textarea_field( $m['content'] ) : '';
		$content = mb_substr( $content, 0, 2000 ); // Cap message length.
		if ( '' !== $content ) {
			$messages[] = array( 'role' => $role, 'content' => $content );
		}
	}
	if ( empty( $messages ) ) {
		return new WP_Error( 'bad_request', 'メッセージが空です。', array( 'status' => 400 ) );
	}

	$session = sanitize_text_field( (string) $request->get_param( 'session' ) );

	// 会話ログ：今回のお客様の発言を記録（AI／有人対応いずれの場合も残す）。
	if ( function_exists( 'apprex_chat_log_append' ) ) {
		$last_user = end( $messages );
		reset( $messages );
		if ( $last_user && 'user' === $last_user['role'] ) {
			apprex_chat_log_append( $session, 'お客様', $last_user['content'] );
		}
	}

	// オペレーター連携：発言を Slack へ転送。有人対応中ならAI応答を止める。
	if ( function_exists( 'apprex_chat_op_ingest' ) ) {
		$is_human = apprex_chat_op_ingest( $session, $messages );
		if ( $is_human ) {
			return rest_ensure_response( array( 'reply' => '', 'human' => true ) );
		}
	}

	// Mock mode for local/dev without network.
	if ( defined( 'APPREX_CHAT_MOCK' ) && APPREX_CHAT_MOCK ) {
		$last = end( $messages );
		return rest_ensure_response(
			array(
				'reply' => apprex_chat_mock_reply( $last['content'] ),
				'mock'  => true,
			)
		);
	}

	if ( '' === apprex_openrouter_key() ) {
		return new WP_Error( 'not_configured', 'チャットは現在準備中です。お問い合わせフォームをご利用ください。', array( 'status' => 503 ) );
	}

	// 会員ログイン連携：ログイン中の契約者なら本人の契約情報を文脈に追加。
	$system = apprex_chat_system_prompt();
	if ( function_exists( 'apprex_chat_member_context' ) ) {
		$member_ctx = apprex_chat_member_context();
		if ( '' !== $member_ctx ) {
			$system .= "\n\n# ログイン中の契約者情報（本人にのみ案内してよい）\n" . $member_ctx;
		}
	}

	$payload_messages = array_merge(
		array( array( 'role' => 'system', 'content' => $system ) ),
		$messages
	);
	$reply = apprex_openrouter_complete(
		$payload_messages,
		array( 'temperature' => 0.3, 'max_tokens' => 600, 'timeout' => 30 )
	);

	// 設定モデルが利用不可などで失敗した場合、安全な既定モデルで自動再試行（全件フォーム誘導の回避）。
	if ( is_wp_error( $reply ) && apprex_openrouter_model() !== APPREX_OPENROUTER_DEFAULT_MODEL ) {
		$reply = apprex_openrouter_complete(
			$payload_messages,
			array( 'model' => APPREX_OPENROUTER_DEFAULT_MODEL, 'temperature' => 0.3, 'max_tokens' => 600, 'timeout' => 30 )
		);
	}

	if ( is_wp_error( $reply ) ) {
		return new WP_Error( 'upstream_error', 'ただいま応答できませんでした。お手数ですがお問い合わせフォームをご利用ください。', array( 'status' => 502 ) );
	}

	// 学習・後追い：会話ログ保存＋メール検出によるリード獲得。
	$captured = false;
	if ( function_exists( 'apprex_chat_after_reply' ) ) {
		$captured = apprex_chat_after_reply( $messages, $reply, $session );
	}

	// オペレーター連携：AI応答もスレッドへ記録（担当者の文脈把握用）。
	if ( function_exists( 'apprex_chat_op_log_ai_reply' ) ) {
		apprex_chat_op_log_ai_reply( $session, $reply );
	}

	$out = array( 'reply' => $reply );
	if ( function_exists( 'apprex_chat_suggestions' ) ) {
		$out['suggestions'] = apprex_chat_suggestions();
	}
	if ( $captured ) {
		$out['lead_captured'] = true;
	}
	return rest_ensure_response( $out );
}

/**
 * Canned reply for mock mode (UI/plumbing verification).
 *
 * @param string $msg User message.
 * @return string
 */
function apprex_chat_mock_reply( $msg ) {
	if ( false !== mb_strpos( $msg, '料金' ) || false !== mb_strpos( $msg, '見積' ) ) {
		return "アプリ開発は月額19,800円〜（初期費用0円）です。サービスやオプションを選ぶと概算が出る「見積もりフォーム」から、そのまま発注まで進めます。ご案内しましょうか？【モック応答】";
	}
	return "ご質問ありがとうございます。APPREX はノーコードでiOS/Androidアプリを最短2週間で公開できます。詳しくは見積もりフォームや無料体験もご利用ください。【モック応答】";
}

/**
 * Minimal settings page: Settings > APPREX チャット.
 */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX チャット設定', 'APPREX チャット', 'manage_options', 'apprex-chat', 'apprex_chat_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_chat', 'apprex_openrouter_api_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_chat', 'apprex_openrouter_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_chat', 'apprex_chat_knowledge', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	register_setting( 'apprex_chat', 'apprex_chat_sdr', array( 'sanitize_callback' => 'absint', 'default' => 1 ) );
} );

/**
 * Render the settings page.
 */
function apprex_chat_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$key_from_const = defined( 'APPREX_OPENROUTER_API_KEY' ) && APPREX_OPENROUTER_API_KEY;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'APPREX チャット（OpenRouter）設定', 'apprex' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_chat' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'OpenRouter APIキー', 'apprex' ); ?></th>
					<td>
						<?php if ( $key_from_const ) : ?>
							<p><em><?php esc_html_e( 'wp-config.php の APPREX_OPENROUTER_API_KEY で設定済みです（こちらが優先されます）。', 'apprex' ); ?></em></p>
						<?php else : ?>
							<input type="password" name="apprex_openrouter_api_key" value="<?php echo esc_attr( get_option( 'apprex_openrouter_api_key', '' ) ); ?>" class="regular-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'sk-or- から始まるキー。セキュリティ上、可能なら wp-config.php の定数での設定を推奨します。', 'apprex' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'モデル', 'apprex' ); ?></th>
					<td>
						<input type="text" name="apprex_openrouter_model" value="<?php echo esc_attr( get_option( 'apprex_openrouter_model', '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( APPREX_OPENROUTER_DEFAULT_MODEL ); ?>">
						<p class="description"><?php printf( esc_html__( '未入力時は %s。任意の OpenRouter モデルID（例：google/gemini-flash-1.5、deepseek/deepseek-chat 等）を指定できます。', 'apprex' ), esc_html( APPREX_OPENROUTER_DEFAULT_MODEL ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'ナレッジ（チャット学習内容）', 'apprex' ); ?></th>
					<td>
						<textarea name="apprex_chat_knowledge" rows="10" class="large-text" placeholder="<?php esc_attr_e( '例）よくある質問と回答、キャンペーン情報、対応業種、納期の目安、注意事項 など。ここに書いた内容をチャットボットが最優先で参照して回答します。', 'apprex' ); ?>"><?php echo esc_textarea( get_option( 'apprex_chat_knowledge', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'チャットボットに覚えさせたい自社情報・FAQ・ルールを自由に記載してください（WP側で随時更新＝学習）。', 'apprex' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'AI自動アポ獲得モード', 'apprex' ); ?></th>
					<td>
						<label><input type="checkbox" name="apprex_chat_sdr" value="1" <?php checked( 1, (int) get_option( 'apprex_chat_sdr', 1 ) ); ?>> <?php esc_html_e( '有効にする（AIが要件をヒアリングし、概算提示→無料相談の予約へ誘導）', 'apprex' ); ?></label>
						<p class="description"><?php esc_html_e( 'ONにすると、チャットが受け身の回答だけでなく、業種・目的・時期などを自然に質問し、最終的にWebミーティング予約や連絡先の取得（アポ獲得）へ導きます。', 'apprex' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * 画像生成モデル（既定：Gemini Image「Nano Banana」）。
 *
 * @return string
 */
function apprex_image_model() {
	$opt = get_option( 'apprex_image_model', '' );
	return $opt ? (string) $opt : 'google/gemini-2.5-flash-image';
}

/**
 * OpenRouter のさまざまな応答形から画像URL（data: または http）を取り出す。
 *
 * @param array $body デコード済みレスポンス。
 * @return string URL（無ければ空文字）。
 */
function apprex_extract_image_url( $body ) {
	if ( ! is_array( $body ) ) {
		return '';
	}
	$msg = isset( $body['choices'][0]['message'] ) ? $body['choices'][0]['message'] : array();

	// 1) message.images[].image_url.url（OpenRouter 標準）。
	if ( ! empty( $msg['images'] ) && is_array( $msg['images'] ) ) {
		foreach ( $msg['images'] as $im ) {
			if ( ! empty( $im['image_url']['url'] ) ) {
				return (string) $im['image_url']['url'];
			}
			if ( ! empty( $im['url'] ) ) {
				return (string) $im['url'];
			}
		}
	}
	// 2) message.content が配列（type=image_url / image）の場合。
	if ( ! empty( $msg['content'] ) && is_array( $msg['content'] ) ) {
		foreach ( $msg['content'] as $part ) {
			if ( ! empty( $part['image_url']['url'] ) ) {
				return (string) $part['image_url']['url'];
			}
			if ( isset( $part['type'] ) && 'image' === $part['type'] && ! empty( $part['source']['data'] ) ) {
				$mime = isset( $part['source']['media_type'] ) ? $part['source']['media_type'] : 'image/png';
				return 'data:' . $mime . ';base64,' . $part['source']['data'];
			}
		}
	}
	// 3) 画像生成API互換（data[0].b64_json / url）。
	if ( ! empty( $body['data'][0]['b64_json'] ) ) {
		return 'data:image/png;base64,' . $body['data'][0]['b64_json'];
	}
	if ( ! empty( $body['data'][0]['url'] ) ) {
		return (string) $body['data'][0]['url'];
	}
	return '';
}

/**
 * OpenRouter で画像を生成（Nano Banana 等の画像出力モデル）。
 *
 * @param string $prompt 画像の指示文。
 * @param array  $args   model 等。
 * @return array|WP_Error { mime, data(binary) } または WP_Error。
 */
function apprex_openrouter_image( $prompt, $args = array() ) {
	$key = apprex_openrouter_key();
	if ( '' === $key ) {
		return new WP_Error( 'not_configured', 'OpenRouter APIキーが未設定です。' );
	}
	$payload = array(
		'model'      => isset( $args['model'] ) ? $args['model'] : apprex_image_model(),
		'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		'modalities' => array( 'image', 'text' ),
	);
	$resp = wp_remote_post(
		APPREX_OPENROUTER_ENDPOINT,
		array(
			'timeout' => 120,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
				'HTTP-Referer'  => home_url( '/' ),
				'X-Title'       => 'APPREX',
			),
			'body'    => wp_json_encode( $payload ),
		)
	);
	if ( is_wp_error( $resp ) ) {
		return new WP_Error( 'upstream_error', '画像生成サービスに接続できませんでした：' . $resp->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );

	// API がエラーを返したら、その内容をそのまま伝える（診断しやすく）。
	if ( 200 !== $code || isset( $body['error'] ) ) {
		$detail = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
		return new WP_Error( 'upstream_error', 'モデル「' . $payload['model'] . '」でエラー：' . $detail );
	}

	$url = apprex_extract_image_url( $body );
	if ( '' === $url ) {
		// テキストだけ返ってきた等：本文の冒頭を手掛かりに返す。
		$txt = isset( $body['choices'][0]['message']['content'] ) && is_string( $body['choices'][0]['message']['content'] )
			? mb_substr( trim( $body['choices'][0]['message']['content'] ), 0, 120 )
			: '';
		return new WP_Error( 'no_image', 'モデル「' . $payload['model'] . '」が画像を返しませんでした（画像出力対応モデルかご確認ください）。' . ( $txt ? ' 応答: ' . $txt : '' ) );
	}
	if ( 0 !== strpos( $url, 'data:' ) ) {
		// http(s) URL で返るモデルにも対応：取得して data 化。
		$bin = wp_remote_retrieve_body( wp_remote_get( $url, array( 'timeout' => 60 ) ) );
		if ( '' === $bin ) {
			return new WP_Error( 'no_image', '画像URLを取得できませんでした。' );
		}
		return array( 'mime' => 'image/png', 'data' => $bin );
	}
	if ( ! preg_match( '#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#s', $url, $m ) ) {
		return new WP_Error( 'bad_image', '画像データの形式が不正です。' );
	}
	$bin = base64_decode( $m[2], true );
	if ( false === $bin ) {
		return new WP_Error( 'bad_image', '画像データをデコードできませんでした。' );
	}
	return array( 'mime' => $m[1], 'data' => $bin );
}

/**
 * 生成画像をメディアに保存し添付IDを返す。
 *
 * @param array  $image   { mime, data }。
 * @param string $title   タイトル。
 * @param int    $parent  親投稿ID。
 * @return int|WP_Error
 */
function apprex_save_generated_image( $image, $title = 'apprex-ai', $parent = 0 ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$ext      = ( false !== strpos( $image['mime'], 'png' ) ) ? 'png' : ( ( false !== strpos( $image['mime'], 'webp' ) ) ? 'webp' : 'jpg' );
	$filename = sanitize_title( $title ) . '-' . wp_generate_password( 6, false ) . '.' . $ext;
	$upload   = wp_upload_bits( $filename, null, $image['data'] );
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'save_failed', $upload['error'] );
	}
	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => $image['mime'],
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$parent
	);
	if ( is_wp_error( $attach_id ) || ! $attach_id ) {
		return new WP_Error( 'attach_failed', 'メディア登録に失敗しました。' );
	}
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );
	return $attach_id;
}
