<?php
/**
 * AIチャット（OpenRouter 連携）。
 * - フロントの吹き出しウィジェットからのメッセージを WordPress REST で受け、
 *   サーバー側で OpenRouter Chat Completions API を呼び出して応答を返す。
 * - APIキーはフロントに出さず、定数 AIS_OPENROUTER_API_KEY（wp-config.php 推奨）
 *   または管理画面の設定（設定 → AIチャット）で保持する。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** チャット設定（option + 定数）を取得 */
function ais_chat_options() {
	$defaults = array(
		'enabled'      => '1',
		'model'        => 'openai/gpt-4o-mini',
		'greeting'     => 'いらっしゃいませ。合同会社アイズのAIコンシェルジュです。クルマの販売・買取・オンライン購入・セキュリティ、アプリ・Web制作、FCのことまで、ご用件をお聞かせください。最適なご案内をいたします。',
		'system_extra' => '',
		'api_key'      => '',
	);
	$opt = get_option( 'ais_chat', array() );
	if ( ! is_array( $opt ) ) { $opt = array(); }
	return array_merge( $defaults, $opt );
}

/** 実際に使う APIキー（定数を優先） */
function ais_chat_api_key() {
	if ( defined( 'AIS_OPENROUTER_API_KEY' ) && AIS_OPENROUTER_API_KEY ) {
		return AIS_OPENROUTER_API_KEY;
	}
	$opt = ais_chat_options();
	return trim( (string) $opt['api_key'] );
}

/** チャットが利用可能か（有効 かつ キー設定済み） */
function ais_chat_active() {
	$opt = ais_chat_options();
	return '1' === (string) $opt['enabled'] && '' !== ais_chat_api_key();
}

/** AIに渡すシステムプロンプトを会社データから生成 */
function ais_chat_system_prompt() {
	$site = ais_site();
	$lines = array();
	$lines[] = 'あなたは「' . $site['name'] . '（' . $site['name_en'] . '）」のコーポレートサイトに常駐する、女性のAIコンシェルジュ（ご案内係）です。丁寧で親しみやすく、落ち着いた日本語で対応します。';
	$lines[] = '役割：訪問者のご質問にお答えし、最適な事業・ページへ「ご案内」し、具体的なご相談はお問い合わせフォームへ自然に「誘導」すること。受付スタッフのように一歩先回りして案内します。';
	$lines[] = '';
	$lines[] = '# 会社概要';
	$lines[] = '・社名：' . $site['name'] . '（' . $site['name_en'] . '） / タグライン：' . $site['tagline'];
	$lines[] = '・所在地：' . $site['address'];
	$lines[] = '・連絡方法：メール（' . $site['email'] . '）またはこのチャット。※電話での問い合わせ窓口はありません。';
	$lines[] = '・返信目安：' . $site['reply_target'] . '。初回相談・お見積りは無料。法人・個人どちらも対応。';
	$lines[] = '';
	$lines[] = '# 事業・ブランド';
	foreach ( ais_services() as $s ) {
		$brand = $s['brand'] ? '【' . $s['brand'] . '】' : '';
		$cs    = ! empty( $s['coming_soon'] ) ? '（準備中）' : '';
		$lines[] = '・' . $brand . $s['name'] . $cs . '：' . $s['tagline'] . ' — ' . $s['summary'];
	}
	$lines[] = '';
	$lines[] = '# よくある質問（要点）';
	foreach ( ais_faqs() as $f ) {
		$lines[] = 'Q: ' . $f['q'];
		$lines[] = 'A: ' . $f['a'];
	}
	$lines[] = '';
	$lines[] = '# 回答ルール';
	$lines[] = '・日本語で、簡潔（目安2〜4文）かつ丁寧に回答する。一人称は控えめにし、「ご案内します」「承っております」などの接客的な表現を用いる。';
	$lines[] = '・回答の最後に、関連する事業ページやお問い合わせへの「次のご案内」を一言添える（例：「詳しくは○○のページをご覧ください」「ご希望でしたらお問い合わせフォームへご案内します」）。';
	$lines[] = '・上記の事実のみにもとづく。価格・在庫・納期・個別条件など不確実な事項は断定せず、「お問い合わせフォームからご相談ください」とご案内する。';
	$lines[] = '・電話番号は案内しない（窓口はメールまたはこのチャット）。';
	$lines[] = '・当社の事業と無関係な話題には、丁寧にお答えできない旨を伝え、事業に関するご相談へ案内する。';
	$lines[] = '・具体的な相談・見積りはお問い合わせフォーム（/contact）へご誘導する。';

	$opt = ais_chat_options();
	if ( ! empty( $opt['system_extra'] ) ) {
		$lines[] = '';
		$lines[] = '# 追加指示';
		$lines[] = (string) $opt['system_extra'];
	}
	return implode( "\n", $lines );
}

/** REST ルート登録 */
function ais_chat_register_rest() {
	register_rest_route( 'ais/v1', '/chat', array(
		'methods'             => 'POST',
		'callback'            => 'ais_chat_rest_handler',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'ais_chat_register_rest' );

/** REST ハンドラ：OpenRouter を呼び出して応答を返す */
function ais_chat_rest_handler( WP_REST_Request $request ) {
	if ( ! ais_chat_active() ) {
		return new WP_REST_Response( array( 'error' => 'チャットは現在ご利用いただけません。' ), 503 );
	}

	$incoming = $request->get_param( 'messages' );
	if ( ! is_array( $incoming ) ) {
		return new WP_REST_Response( array( 'error' => 'リクエストが不正です。' ), 400 );
	}

	// 直近の最大10往復に制限・サニタイズ
	$history = array();
	foreach ( array_slice( $incoming, -20 ) as $m ) {
		$role = isset( $m['role'] ) ? $m['role'] : '';
		$text = isset( $m['content'] ) ? (string) $m['content'] : '';
		if ( ! in_array( $role, array( 'user', 'assistant' ), true ) ) { continue; }
		$text = trim( wp_strip_all_tags( $text ) );
		if ( '' === $text ) { continue; }
		$history[] = array( 'role' => $role, 'content' => mb_substr( $text, 0, 2000 ) );
	}
	if ( empty( $history ) ) {
		return new WP_REST_Response( array( 'error' => 'メッセージが空です。' ), 400 );
	}

	$opt      = ais_chat_options();
	$messages = array_merge(
		array( array( 'role' => 'system', 'content' => ais_chat_system_prompt() ) ),
		$history
	);

	$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . ais_chat_api_key(),
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => home_url( '/' ),
			'X-Title'       => get_bloginfo( 'name' ),
		),
		'body'    => wp_json_encode( array(
			'model'       => $opt['model'],
			'messages'    => $messages,
			'temperature' => 0.3,
			'max_tokens'  => 600,
		) ),
	) );

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response( array( 'error' => '応答の取得に失敗しました。時間をおいてお試しください。' ), 502 );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 || ! isset( $body['choices'][0]['message']['content'] ) ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'AIの応答を取得できませんでした。';
		return new WP_REST_Response( array( 'error' => $msg ), 502 );
	}

	$reply = trim( (string) $body['choices'][0]['message']['content'] );
	return new WP_REST_Response( array( 'reply' => $reply ), 200 );
}

/* -------------------------------------------------------------------------
 * 管理画面：設定 → AIチャット
 * ---------------------------------------------------------------------- */
function ais_chat_register_settings() {
	register_setting( 'ais_chat_group', 'ais_chat', array(
		'type'              => 'array',
		'sanitize_callback' => 'ais_chat_sanitize',
		'default'           => array(),
	) );
}
add_action( 'admin_init', 'ais_chat_register_settings' );

function ais_chat_sanitize( $input ) {
	$out = ais_chat_options();
	$out['enabled']      = ! empty( $input['enabled'] ) ? '1' : '0';
	$out['model']        = isset( $input['model'] ) ? sanitize_text_field( $input['model'] ) : $out['model'];
	$out['greeting']     = isset( $input['greeting'] ) ? sanitize_textarea_field( $input['greeting'] ) : $out['greeting'];
	$out['system_extra'] = isset( $input['system_extra'] ) ? sanitize_textarea_field( $input['system_extra'] ) : '';
	// 定数でキーを設定している場合は option に保存しない
	if ( ! defined( 'AIS_OPENROUTER_API_KEY' ) ) {
		$out['api_key'] = isset( $input['api_key'] ) ? trim( sanitize_text_field( $input['api_key'] ) ) : '';
	}
	return $out;
}

function ais_chat_admin_menu() {
	add_options_page( 'AIチャット設定', 'AIチャット', 'manage_options', 'ais-chat', 'ais_chat_settings_page' );
}
add_action( 'admin_menu', 'ais_chat_admin_menu' );

function ais_chat_settings_page() {
	$opt          = ais_chat_options();
	$key_constant = defined( 'AIS_OPENROUTER_API_KEY' ) && AIS_OPENROUTER_API_KEY;
	?>
	<div class="wrap">
		<h1>AIチャット設定（OpenRouter）</h1>
		<p>サイトに常駐するAIチャットの設定です。APIキーは <code>wp-config.php</code> に
			<code>define('AIS_OPENROUTER_API_KEY', '...');</code> と定義する方法を推奨します（DBに保存されません）。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'ais_chat_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">チャットを有効化</th>
					<td><label><input type="checkbox" name="ais_chat[enabled]" value="1" <?php checked( '1', $opt['enabled'] ); ?>> サイト全体に表示する</label></td>
				</tr>
				<tr>
					<th scope="row">OpenRouter APIキー</th>
					<td>
						<?php if ( $key_constant ) : ?>
							<p><em>wp-config.php の定数で設定済みです（この欄は使用されません）。</em></p>
						<?php else : ?>
							<input type="password" name="ais_chat[api_key]" value="<?php echo esc_attr( $opt['api_key'] ); ?>" class="regular-text" autocomplete="off" placeholder="sk-or-...">
							<p class="description">openrouter.ai で発行したキー。</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">モデル</th>
					<td>
						<input type="text" name="ais_chat[model]" value="<?php echo esc_attr( $opt['model'] ); ?>" class="regular-text" placeholder="openai/gpt-4o-mini">
						<p class="description">OpenRouter のモデルID（例：<code>openai/gpt-4o-mini</code>、<code>anthropic/claude-3.5-haiku</code>、<code>google/gemini-flash-1.5</code>）。</p>
					</td>
				</tr>
				<tr>
					<th scope="row">あいさつ文（最初の表示）</th>
					<td><textarea name="ais_chat[greeting]" rows="3" class="large-text"><?php echo esc_textarea( $opt['greeting'] ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row">追加のAI指示（任意）</th>
					<td>
						<textarea name="ais_chat[system_extra]" rows="4" class="large-text" placeholder="例：キャンペーン情報や、特定の質問への定型回答など"><?php echo esc_textarea( $opt['system_extra'] ); ?></textarea>
						<p class="description">会社概要・事業・FAQは自動で読み込まれます。ここには追加の方針のみ記載してください。</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
