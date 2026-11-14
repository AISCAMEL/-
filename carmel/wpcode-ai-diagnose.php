<?php
/**
 * カーメル：AI診断（Claude API）
 * ---------------------------------------------------------------------------
 * ・ページ診断：指定ページを取得し、重さの指標＋HTML要約をClaudeに渡して
 *   「どこが重い/こう直す」優先度つき改善提案を日本語で受け取る。
 * ・デザイン修正：やりたいことを日本語で書くとClaudeがCSSを生成。プレビューを
 *   確認して「適用」を押すと、そのCSSをフロントに反映（承認制）。OFFで即停止。
 *
 * ・APIキーはオーナーが設定画面に入力（WordPressに保存・画面では伏せ字）。
 *   利用料は従量課金。公開ページのHTMLのみ送信（個人情報は送りません）。
 * 管理者専用。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'CARMEL_AI_OPT' ) ) { define( 'CARMEL_AI_OPT', 'carmel_ai_opts' ); }
if ( ! defined( 'CARMEL_AI_CSS' ) ) { define( 'CARMEL_AI_CSS', 'carmel_ai_css' ); }

if ( ! function_exists( 'carmelx_ai_opts' ) ) {
	function carmelx_ai_opts() {
		$o = get_option( CARMEL_AI_OPT );
		return array(
			'key'   => isset( $o['key'] ) ? (string) $o['key'] : '',
			'model' => isset( $o['model'] ) && $o['model'] ? (string) $o['model'] : 'claude-opus-5-5',
		);
	}
}

/* ========== Claude API 呼び出し（WordPressのHTTPで raw リクエスト） ========== */
if ( ! function_exists( 'carmelx_ai_call' ) ) {
	function carmelx_ai_call( $system, $user, $max_tokens = 3000 ) {
		$o = carmelx_ai_opts();
		if ( '' === $o['key'] ) { return array( 'ok' => false, 'msg' => 'APIキーが未設定です。' ); }
		$res = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 60,
			'headers' => array(
				'x-api-key'         => $o['key'],
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body' => wp_json_encode( array(
				'model'      => $o['model'],
				'max_tokens' => (int) $max_tokens,
				'system'     => $system,
				'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
			) ),
		) );
		if ( is_wp_error( $res ) ) { return array( 'ok' => false, 'msg' => '通信エラー: ' . $res->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code ) {
			$em = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
			if ( 401 === $code ) { $em = 'APIキーが正しくない可能性があります（401）。'; }
			return array( 'ok' => false, 'msg' => 'APIエラー: ' . $em );
		}
		if ( isset( $body['stop_reason'] ) && 'refusal' === $body['stop_reason'] ) {
			return array( 'ok' => false, 'msg' => 'AIがこの依頼への回答を控えました。内容を変えてお試しください。' );
		}
		$text = '';
		if ( ! empty( $body['content'] ) && is_array( $body['content'] ) ) {
			foreach ( $body['content'] as $b ) { if ( isset( $b['type'], $b['text'] ) && 'text' === $b['type'] ) { $text .= $b['text']; } }
		}
		if ( '' === $text ) { return array( 'ok' => false, 'msg' => '空の応答が返りました。' ); }
		$usage = isset( $body['usage'] ) ? $body['usage'] : array();
		return array( 'ok' => true, 'text' => $text, 'usage' => $usage );
	}
}

/* ページを取得して“コンパクトな要約”を作る（送信トークン節約） */
if ( ! function_exists( 'carmelx_ai_page_brief' ) ) {
	function carmelx_ai_page_brief( $url ) {
		$res = wp_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'CarmelAI/1.0' ) );
		if ( is_wp_error( $res ) ) { return null; }
		$html = (string) wp_remote_retrieve_body( $res );
		if ( '' === $html ) { return null; }
		$html_kb = round( strlen( $html ) / 1024 );
		$img = preg_match_all( '/<img\b[^>]*>/i', $html );
		$scr = preg_match_all( '/<script\b[^>]*\bsrc=/i', $html );
		$sty = preg_match_all( '/<link\b[^>]*stylesheet/i', $html );
		$head = '';
		if ( preg_match( '/<head[^>]*>(.*?)<\/head>/is', $html, $m ) ) { $head = $m[1]; }
		$head = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $head );
		$brief = "URL: {$url}\nHTMLサイズ: {$html_kb}KB / 画像: {$img} / スクリプト: {$scr} / CSS: {$sty}\n\n";
		$brief .= "[<head>抜粋]\n" . mb_substr( trim( wp_strip_all_tags( $head, false ) ), 0, 2000 ) . "\n\n";
		$brief .= "[本文HTML抜粋]\n" . mb_substr( $html, 0, 12000 );
		return $brief;
	}
}

/* ========== 適用CSSをフロントへ出力（承認制） ========== */
if ( ! function_exists( 'carmelx_ai_output_css' ) ) {
	function carmelx_ai_output_css() {
		if ( is_admin() ) { return; }
		$css = get_option( CARMEL_AI_CSS );
		if ( ! is_array( $css ) || empty( $css['enabled'] ) || empty( $css['code'] ) ) { return; }
		echo "\n<style id=\"carmel-ai-css\">\n" . wp_strip_all_tags( (string) $css['code'] ) . "\n</style>\n";
	}
	add_action( 'wp_head', 'carmelx_ai_output_css', 100 );
}

/* ========== 管理ページ（ツール → AI診断）オーナー専用 ========== */
if ( ! function_exists( 'carmelx_ai_add_page' ) ) {
	function carmelx_ai_add_page() {
		add_management_page( 'AI診断・デザイン', 'AI診断・デザイン', 'manage_options', 'carmel-ai-diagnose', 'carmelx_ai_render' );
	}
	add_action( 'admin_menu', 'carmelx_ai_add_page' );
}

if ( ! function_exists( 'carmelx_ai_render' ) ) {
	function carmelx_ai_render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$diag = ''; $css_preview = ''; $msg = ''; $usage_note = '';
		if ( isset( $_POST['carmel_ai_nonce'] ) && wp_verify_nonce( $_POST['carmel_ai_nonce'], 'carmel_ai' ) ) {
			if ( isset( $_POST['carmel_ai_save'] ) ) {
				$key = trim( (string) wp_unslash( $_POST['ai_key'] ?? '' ) );
				$cur = carmelx_ai_opts();
				update_option( CARMEL_AI_OPT, array(
					'key'   => ( '' !== $key ) ? $key : $cur['key'], // 空なら既存キーを維持
					'model' => in_array( $_POST['ai_model'] ?? '', array( 'claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5' ), true ) ? $_POST['ai_model'] : 'claude-opus-5-5',
				), false );
				$msg = '設定を保存しました。';
			} elseif ( isset( $_POST['carmel_ai_diagnose'] ) ) {
				$url = esc_url_raw( (string) ( $_POST['ai_url'] ?? '' ) );
				if ( $url ) {
					$brief = carmelx_ai_page_brief( $url );
					if ( ! $brief ) { $msg = 'ページを取得できませんでした。'; }
					else {
						$sys = 'あなたは日本語で答えるWeb表示速度・UX改善の専門家です。与えられたページの指標とHTML抜粋から、表示を軽く・速くするための改善を、効果が大きい順に最大7個、箇条書きで提案してください。各項目は「何を・なぜ・具体的にどうする」を1〜2文で。専門用語は避け、実行手順が分かるように。カーメルは中古車販売の集客サイトです。';
						$r = carmelx_ai_call( $sys, $brief, 2500 );
						if ( empty( $r['ok'] ) ) { $msg = $r['msg']; }
						else { $diag = $r['text']; $usage_note = carmelx_ai_usage_note( $r['usage'] ); }
					}
				}
			} elseif ( isset( $_POST['carmel_ai_gencss'] ) ) {
				$want = trim( (string) wp_unslash( $_POST['ai_want'] ?? '' ) );
				$scope = trim( (string) wp_unslash( $_POST['ai_scope'] ?? '' ) );
				if ( $want ) {
					$sys = 'あなたはCSSの専門家です。ユーザーの要望を満たす安全なCSSだけを出力してください。出力はCSSコードのみ（説明やコードフェンス```は書かない）。!importantは必要な時だけ。壊れやすい広域セレクタ（*など）は避け、できるだけ具体的に。レスポンシブにも配慮。';
					$u = '要望: ' . $want;
					if ( $scope ) { $u .= "\n対象（任意のセレクタ/場所のヒント）: " . $scope; }
					$r = carmelx_ai_call( $sys, $u, 1500 );
					if ( empty( $r['ok'] ) ) { $msg = $r['msg']; }
					else { $css_preview = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', trim( $r['text'] ) ); $usage_note = carmelx_ai_usage_note( $r['usage'] ); }
				}
			} elseif ( isset( $_POST['carmel_ai_apply'] ) ) {
				$code = (string) wp_unslash( $_POST['ai_css_code'] ?? '' );
				update_option( CARMEL_AI_CSS, array( 'enabled' => 1, 'code' => $code, 'time' => time() ), false );
				$msg = 'CSSを適用しました（フロントに反映）。';
			} elseif ( isset( $_POST['carmel_ai_css_off'] ) ) {
				$c = get_option( CARMEL_AI_CSS ); if ( is_array( $c ) ) { $c['enabled'] = 0; update_option( CARMEL_AI_CSS, $c, false ); }
				$msg = '適用中のCSSを停止しました。';
			}
		}
		$o = carmelx_ai_opts();
		$key_mask = ( $o['key'] !== '' ) ? ( '設定済み（••••' . substr( $o['key'], -4 ) . '）' ) : '未設定';
		$applied = get_option( CARMEL_AI_CSS );
		?>
		<div class="wrap">
			<h1>AI診断・デザイン</h1>
			<?php if ( $msg ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>

			<h2>設定（Claude API）</h2>
			<form method="post">
				<?php wp_nonce_field( 'carmel_ai', 'carmel_ai_nonce' ); ?>
				<table class="form-table" style="max-width:760px;">
					<tr><th>APIキー</th><td>
						<input type="password" name="ai_key" value="" placeholder="<?php echo esc_attr( $o['key'] !== '' ? '変更する時だけ入力' : 'sk-ant-...' ); ?>" style="width:360px;" autocomplete="off">
						<span style="margin-left:8px;color:#555;">現在：<?php echo esc_html( $key_mask ); ?></span>
						<p style="color:#888;font-size:12px;margin:4px 0 0;">console.anthropic.com で発行したキー。空欄のまま保存すると現在のキーを維持します。</p>
					</td></tr>
					<tr><th>モデル</th><td>
						<select name="ai_model">
							<option value="claude-opus-5-5" <?php selected( $o['model'], 'claude-opus-5-5' ); ?>>Opus 5.5（高精度・やや高）</option>
							<option value="claude-sonnet-5-5" <?php selected( $o['model'], 'claude-sonnet-5-5' ); ?>>Sonnet 5.5（バランス）</option>
							<option value="claude-haiku-5-5" <?php selected( $o['model'], 'claude-haiku-5-5' ); ?>>Haiku 5.5（最安・高速）</option>
						</select>
						<p style="color:#888;font-size:12px;margin:4px 0 0;">診断1回あたり数円程度。まずはHaikuやSonnetで十分なことが多いです。</p>
					</td></tr>
				</table>
				<p><button type="submit" name="carmel_ai_save" value="1" class="button button-primary">設定を保存</button></p>
			</form>

			<hr>
			<h2>① ページをAIで診断</h2>
			<form method="post">
				<?php wp_nonce_field( 'carmel_ai', 'carmel_ai_nonce' ); ?>
				<input type="url" name="ai_url" value="<?php echo esc_attr( home_url( '/' ) ); ?>" style="width:420px;" placeholder="https://...">
				<button type="submit" name="carmel_ai_diagnose" value="1" class="button button-primary" <?php disabled( $o['key'] === '' ); ?>>このページを診断</button>
				<?php if ( $o['key'] === '' ) : ?><span style="color:#b32d2e;margin-left:8px;">※ 先にAPIキーを設定してください</span><?php endif; ?>
			</form>
			<?php if ( $diag ) : ?>
				<div style="max-width:860px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px;margin-top:12px;white-space:pre-wrap;line-height:1.7;"><?php echo esc_html( $diag ); ?></div>
				<?php if ( $usage_note ) : ?><p style="color:#888;font-size:12px;"><?php echo esc_html( $usage_note ); ?></p><?php endif; ?>
			<?php endif; ?>

			<hr>
			<h2>② デザイン修正（AIがCSSを作成 → 確認して適用）</h2>
			<form method="post">
				<?php wp_nonce_field( 'carmel_ai', 'carmel_ai_nonce' ); ?>
				<p><label>やりたいこと（日本語で）<br>
					<textarea name="ai_want" rows="3" style="width:620px;" placeholder="例：車両ページの『お支払総額』をもっと大きく目立たせたい"><?php echo isset( $_POST['ai_want'] ) ? esc_textarea( wp_unslash( $_POST['ai_want'] ) ) : ''; ?></textarea></label></p>
				<p><label>対象のヒント（任意：クラス名や場所）<br>
					<input type="text" name="ai_scope" value="<?php echo isset( $_POST['ai_scope'] ) ? esc_attr( wp_unslash( $_POST['ai_scope'] ) ) : ''; ?>" style="width:620px;" placeholder="例：.carmel-lg__hd-price（分からなければ空欄でOK）"></label></p>
				<p><button type="submit" name="carmel_ai_gencss" value="1" class="button button-primary" <?php disabled( $o['key'] === '' ); ?>>CSSを作ってもらう</button></p>
			</form>
			<?php if ( $css_preview ) : ?>
				<h3>プレビュー（このCSSを適用します）</h3>
				<form method="post">
					<?php wp_nonce_field( 'carmel_ai', 'carmel_ai_nonce' ); ?>
					<textarea name="ai_css_code" rows="10" style="width:860px;font-family:monospace;font-size:12px;"><?php echo esc_textarea( $css_preview ); ?></textarea>
					<p><button type="submit" name="carmel_ai_apply" value="1" class="button button-primary" onclick="return confirm('このCSSをサイト全体に適用します。よろしいですか？（コピーで先に確認推奨）');">このCSSを適用する</button></p>
				</form>
				<?php if ( $usage_note ) : ?><p style="color:#888;font-size:12px;"><?php echo esc_html( $usage_note ); ?></p><?php endif; ?>
			<?php endif; ?>

			<?php if ( is_array( $applied ) && ! empty( $applied['code'] ) ) : ?>
			<h3 style="margin-top:20px;">現在適用中のCSS <?php echo ! empty( $applied['enabled'] ) ? '<span style="color:#1c7a3a;">（有効）</span>' : '<span style="color:#888;">（停止中）</span>'; ?></h3>
			<pre style="max-width:860px;background:#f6f7f7;border:1px solid #ddd;padding:12px;overflow:auto;max-height:200px;"><?php echo esc_html( $applied['code'] ); ?></pre>
			<?php if ( ! empty( $applied['enabled'] ) ) : ?>
			<form method="post"><?php wp_nonce_field( 'carmel_ai', 'carmel_ai_nonce' ); ?><button type="submit" name="carmel_ai_css_off" value="1" class="button">適用を停止する</button></form>
			<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'carmelx_ai_usage_note' ) ) {
	function carmelx_ai_usage_note( $u ) {
		if ( empty( $u ) || ! is_array( $u ) ) { return ''; }
		$in  = isset( $u['input_tokens'] ) ? (int) $u['input_tokens'] : 0;
		$out = isset( $u['output_tokens'] ) ? (int) $u['output_tokens'] : 0;
		return "使用トークン：入力 {$in} / 出力 {$out}";
	}
}
