<?php
/**
 * Floating in-site chatbot.
 *
 * Primary: native OpenRouter-powered assistant (when configured / mock).
 * Fallback: Zapier iframe widget (when an iframe URL is set but no AI key).
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_ai      = apprex_chat_enabled();
$apprex_zapier  = $apprex_ai ? '' : apprex_chatbot_url();

if ( ! $apprex_ai && ! $apprex_zapier ) {
	return;
}

$apprex_agent_img    = get_theme_file_uri( 'assets/images/support-agent.jpg' );
$apprex_agent_img_sm = get_theme_file_uri( 'assets/images/support-agent-sm.jpg' );
?>
<div class="apprex-chat-launch">
	<div class="apprex-chat-hint" id="apprex-chat-hint" hidden>
		<span class="apprex-chat-hint__text"><?php esc_html_e( 'お困りですか？お気軽にどうぞ', 'apprex' ); ?></span>
		<button class="apprex-chat-hint__x" id="apprex-chat-hint-x" type="button" aria-label="<?php esc_attr_e( '閉じる', 'apprex' ); ?>">×</button>
	</div>
	<button class="apprex-chat-toggle" type="button" aria-expanded="false" aria-controls="apprex-chat-window" aria-label="<?php esc_attr_e( 'チャットで相談', 'apprex' ); ?>">
		<span class="apprex-chat-toggle__icon apprex-chat-toggle__icon--open" aria-hidden="true">
			<img class="apprex-chat-toggle__photo" src="<?php echo esc_url( $apprex_agent_img_sm ); ?>" alt="" width="64" height="64" loading="lazy" decoding="async">
		</span>
		<span class="apprex-chat-toggle__icon apprex-chat-toggle__icon--close" aria-hidden="true">✕</span>
		<span class="apprex-chat-toggle__pip" aria-hidden="true"></span>
		<span class="apprex-chat-toggle__badge" aria-hidden="true">1</span>
	</button>
</div>

<?php if ( $apprex_ai ) : ?>
<div class="apprex-chat" id="apprex-chat-window" hidden style="--apprex-agent: url('<?php echo esc_url( $apprex_agent_img_sm ); ?>');">
	<div class="apprex-chat__head">
		<div class="apprex-chat__id">
			<span class="apprex-chat__avatar" aria-hidden="true">
				<img class="apprex-chat__avatar-photo" src="<?php echo esc_url( $apprex_agent_img ); ?>" alt="" width="40" height="40" loading="lazy" decoding="async">
			</span>
			<div>
				<strong><?php esc_html_e( 'APPREX サポート', 'apprex' ); ?></strong>
				<span class="apprex-chat__status"><span class="apprex-chat__dot" aria-hidden="true"></span><?php esc_html_e( 'オンライン', 'apprex' ); ?></span>
			</div>
		</div>
		<div class="apprex-chat__actions">
			<button class="apprex-chat__mute" id="apprex-chat-mute" type="button" aria-label="<?php esc_attr_e( '通知音のオン/オフ', 'apprex' ); ?>" title="<?php esc_attr_e( '通知音のオン/オフ', 'apprex' ); ?>">🔔</button>
			<button class="apprex-chat__close" type="button" aria-label="<?php esc_attr_e( '閉じる', 'apprex' ); ?>">×</button>
		</div>
	</div>

	<div class="apprex-chat__log" id="apprex-chat-log" aria-live="polite"></div>

	<div class="apprex-chat__quick" id="apprex-chat-quick">
		<button type="button" data-q="料金を教えて"><?php esc_html_e( '料金を知りたい', 'apprex' ); ?></button>
		<button type="button" data-q="見積もりをしたい"><?php esc_html_e( '見積もりしたい', 'apprex' ); ?></button>
		<button type="button" data-q="どんなアプリが作れますか？"><?php esc_html_e( '作れるアプリは？', 'apprex' ); ?></button>
		<?php if ( function_exists( 'apprex_chat_op_enabled' ) && apprex_chat_op_enabled() ) : ?>
			<button type="button" class="apprex-chat__quick--op" id="apprex-chat-operator"><?php esc_html_e( '担当者に相談', 'apprex' ); ?></button>
		<?php endif; ?>
	</div>

	<form class="apprex-chat__form" id="apprex-chat-form">
		<input type="text" id="apprex-chat-input" autocomplete="off" placeholder="<?php esc_attr_e( 'メッセージを入力…', 'apprex' ); ?>" aria-label="<?php esc_attr_e( 'メッセージ', 'apprex' ); ?>">
		<button type="submit" aria-label="<?php esc_attr_e( '送信', 'apprex' ); ?>">➤</button>
	</form>

	<!-- 未解決時のメール誘導フォーム（担当者がメールで返信） -->
	<form class="apprex-chat__mail" id="apprex-chat-mailform" data-type="contact" hidden>
		<div class="apprex-chat__mail-head">
			<strong><?php esc_html_e( 'メールで相談', 'apprex' ); ?></strong>
			<button type="button" class="apprex-chat__mail-back" id="apprex-chat-mail-cancel"><?php esc_html_e( '← チャットに戻る', 'apprex' ); ?></button>
		</div>
		<p class="apprex-chat__mail-lead"><?php esc_html_e( '担当者が内容を確認し、メールでご返信します。', 'apprex' ); ?></p>
		<input type="text" name="name" autocomplete="name" required placeholder="<?php esc_attr_e( 'お名前', 'apprex' ); ?>">
		<input type="email" name="email" autocomplete="email" required placeholder="<?php esc_attr_e( 'メールアドレス', 'apprex' ); ?>">
		<textarea name="message" rows="3" placeholder="<?php esc_attr_e( 'ご相談内容', 'apprex' ); ?>"></textarea>
		<button type="submit"><?php esc_html_e( '送信する', 'apprex' ); ?></button>
		<div class="apprex-chat__mail-result" id="apprex-chat-mail-result" hidden></div>
	</form>

	<div class="apprex-chat__foot">
		<button type="button" class="apprex-chat__foot-btn apprex-chat__foot-btn--mail" id="apprex-chat-mail"><?php esc_html_e( '✉ メールで相談', 'apprex' ); ?></button>
		<a class="apprex-chat__foot-btn" href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>"><?php esc_html_e( '見積もり', 'apprex' ); ?></a>
		<a class="apprex-chat__foot-btn" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( '相談予約', 'apprex' ); ?></a>
		<?php $apprex_line = apprex_line_url(); ?>
		<?php if ( $apprex_line ) : ?>
			<a class="apprex-chat__foot-btn apprex-chat__foot-btn--line" href="<?php echo esc_url( $apprex_line ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LINE', 'apprex' ); ?></a>
		<?php endif; ?>
	</div>
</div>
<?php else : ?>
<div class="apprex-chat-window" id="apprex-chat-window" data-src="<?php echo esc_url( $apprex_zapier ); ?>"></div>
<?php endif; ?>
