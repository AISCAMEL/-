<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * フロント側の表示制御
 */
add_action( 'wp_enqueue_scripts', 'carmel_cb_enqueue_front' );
function carmel_cb_enqueue_front() {
	$s = carmel_cb_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }

	wp_enqueue_style( 'carmel-cb', CARMEL_CB_URL . 'assets/chat.css', array(), CARMEL_CB_VERSION );
	wp_enqueue_script( 'carmel-cb', CARMEL_CB_URL . 'assets/chat.js', array(), CARMEL_CB_VERSION, true );

	wp_localize_script( 'carmel-cb', 'CARMEL_CB', carmel_cb_js_config( $s ) );
}

/**
 * chat.js に渡す設定を生成（通常表示・埋め込み・直リンク版で共通利用）。
 * $extra で standalone 等を上書きできる。
 */
function carmel_cb_js_config( $s, $extra = array() ) {
	// CTAの飛び先：ページIDがあれば公開URLを解決（スラッグ変更に強い）。無ければURL直指定を使う。
	$apply_url   = carmel_cb_resolve_link( $s['apply_page_id'] ?? 0,   $s['apply_url'] ?? '' );
	$contact_url = carmel_cb_resolve_link( $s['contact_page_id'] ?? 0, $s['contact_url'] ?? '' );

	$cfg = array(
		'endpoint'      => esc_url_raw( rest_url( 'carmel-cb/v1/chat' ) ),
		'welcome'       => $s['welcome_msg'],
		'botName'       => $s['bot_name'],
		'autoOpenSec'   => (int) ( $s['auto_open_sec'] ?? 0 ),
		'primaryColor'  => $s['primary_color'],
		'lineUrl'       => $s['line_url'],
		'tel'           => isset( $s['tel'] ) ? $s['tel'] : '',
		'applyUrl'      => $apply_url,
		'contactUrl'    => $contact_url,
		'handoffUrl'      => esc_url_raw( rest_url( 'carmel-cb/v1/handoff' ) ),
		'handoffStartUrl' => esc_url_raw( rest_url( 'carmel-cb/v1/handoff/start' ) ),
		'handoffSendUrl'  => esc_url_raw( rest_url( 'carmel-cb/v1/handoff/send' ) ),
		'handoffPollUrl'  => esc_url_raw( rest_url( 'carmel-cb/v1/handoff/poll' ) ),
		'handoffUploadUrl' => esc_url_raw( rest_url( 'carmel-cb/v1/handoff/upload' ) ),
		'intakeOn'        => ! empty( $s['intake_on'] ),
		'intakeMsg'       => (string) ( $s['intake_msg'] ?? '' ),
		'intakeAfterMsg'  => (string) ( $s['intake_after_msg'] ?? '' ),
		'visitorUrl'      => esc_url_raw( rest_url( 'carmel-cb/v1/visitor' ) ),
		'convoPollUrl'    => esc_url_raw( rest_url( 'carmel-cb/v1/convo/poll' ) ),
		'convoSendUrl'    => esc_url_raw( rest_url( 'carmel-cb/v1/convo/send' ) ),
		'convoHandoffUrl' => esc_url_raw( rest_url( 'carmel-cb/v1/convo/handoff' ) ),
		'handoffOn'       => ! empty( $s['handoff_enabled'] ),
		'withinHours'     => carmel_cb_within_hours( $s ),
		'operatorName'    => ! empty( $s['operator_name'] ) ? $s['operator_name'] : '担当者',
		'operatorAvatar'  => esc_url_raw( (string) ( $s['operator_avatar_url'] ?? '' ) ),
		'standalone'      => false,
		// チャット内フォーム（かんたん審査 / お問い合わせ）
		'leadUrl'      => esc_url_raw( rest_url( 'carmel-cb/v1/lead' ) ),
		'ctaMode'      => ( ( $s['cta_mode'] ?? 'form' ) === 'link' ) ? 'link' : 'form',
		'applyFormOn'  => ! empty( $s['apply_form_on'] ),
		'contactFormOn'=> ! empty( $s['contact_form_on'] ),
		'consentText'  => (string) ( $s['consent_text'] ?? '' ),
		'consentUrl'   => esc_url_raw( (string) ( $s['consent_url'] ?? '' ) ),
		'stockUrl'     => esc_url_raw( (string) ( $s['stock_page_url'] ?? '' ) ),
	);
	return array_merge( $cfg, $extra );
}

/**
 * ページID優先で公開URLを解決。IDが無効ならURL直指定にフォールバック。
 */
function carmel_cb_resolve_link( $page_id, $fallback_url ) {
	$page_id = (int) $page_id;
	if ( $page_id > 0 ) {
		$link = get_permalink( $page_id );
		if ( $link ) { return $link; }
	}
	return esc_url_raw( (string) $fallback_url );
}

/**
 * ウィジェットのHTMLをフッターに出力
 */
add_action( 'wp_footer', 'carmel_cb_render_widget' );
function carmel_cb_render_widget() {
	$s = carmel_cb_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	// 直リンク版（フルページ）では二重表示しない
	if ( function_exists( 'carmel_cb_is_standalone_request' ) && carmel_cb_is_standalone_request() ) { return; }
	echo carmel_cb_widget_html( $s );
}

/**
 * ウィジェット本体のHTMLを文字列で返す（フッター常設・直リンク版で共通利用）。
 * $standalone=true のときはランチャー（吹き出し）を出さず、チャット窓だけを返す。
 */
function carmel_cb_widget_html( $s, $standalone = false ) {
	$color = esc_attr( $s['primary_color'] );
	ob_start();
	?>
	<div id="carmel-cb-root"<?php echo $standalone ? ' class="ccb-standalone"' : ''; ?> style="--ccb-primary: <?php echo $color; ?>;">
		<button id="carmel-cb-launcher" aria-label="チャットで相談">
			<span class="ccb-launch-bubble">お困りですか？お気軽にどうぞ</span>
			<span class="ccb-launch-avatar">
				<video src="<?php echo esc_url( CARMEL_CB_URL . 'assets/icon.mp4' ); ?>" autoplay muted loop playsinline></video>
			</span>
		</button>

		<div id="carmel-cb-window" hidden>
			<div class="ccb-header">
				<span class="ccb-header-avatar">
					<?php $avatar_src = 'assets/intro.mp4'; // ヘッダーアイコンは常に女性の顔（intro.mp4） ?>
					<video src="<?php echo esc_url( CARMEL_CB_URL . $avatar_src ); ?>" autoplay muted loop playsinline></video>
				</span>
				<span class="ccb-title"><?php echo esc_html( $s['bot_name'] ); ?></span>
				<button id="carmel-cb-close" aria-label="閉じる">×</button>
			</div>
			<div id="carmel-cb-messages"></div>
			<div class="ccb-input-row">
				<label id="carmel-cb-attach" title="画像・ファイルを送る" style="display:none">📎<input type="file" id="carmel-cb-file" accept="image/*,application/pdf" hidden></label>
				<textarea id="carmel-cb-input" rows="1" placeholder="メッセージを入力…"></textarea>
				<button id="carmel-cb-send" aria-label="送信">➤</button>
			</div>
			<div class="ccb-foot">
				<a href="<?php echo esc_url( $s['line_url'] ); ?>" target="_blank" rel="noopener">LINEで直接相談する →</a>
				<?php if ( ! empty( $s['handoff_enabled'] ) ) : ?>
					<button type="button" id="carmel-cb-handoff" class="ccb-foot-btn">担当者に相談</button>
				<?php endif; ?>
			</div>

			<!-- イントロ：開いた直後は女性が大きく表示 → タップ/ボタンで会話開始（小さくなる） -->
			<div class="ccb-intro" data-role="intro">
				<video class="ccb-intro-video" src="<?php echo esc_url( CARMEL_CB_URL . 'assets/intro.mp4' ); ?>" autoplay muted loop playsinline preload="metadata"></video>
				<button type="button" class="ccb-intro-close" data-role="intro-close" aria-label="閉じる">×</button>
				<div class="ccb-intro-overlay">
					<p class="ccb-intro-title"><?php echo esc_html( $s['bot_name'] ); ?></p>
					<p class="ccb-intro-sub"><?php echo esc_html( $s['welcome_msg'] ); ?></p>
					<span class="ccb-intro-start">相談をはじめる</span>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
