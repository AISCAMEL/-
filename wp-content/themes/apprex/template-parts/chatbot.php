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
?>
<button class="apprex-chat-toggle" type="button" aria-expanded="false" aria-controls="apprex-chat-window" aria-label="<?php esc_attr_e( 'チャットで相談', 'apprex' ); ?>">
	<span class="apprex-chat-toggle__icon apprex-chat-toggle__icon--open" aria-hidden="true">💬</span>
	<span class="apprex-chat-toggle__icon apprex-chat-toggle__icon--close" aria-hidden="true">✕</span>
	<span class="apprex-chat-toggle__badge" aria-hidden="true">1</span>
</button>

<?php if ( $apprex_ai ) : ?>
<div class="apprex-chat" id="apprex-chat-window" hidden>
	<div class="apprex-chat__head">
		<div class="apprex-chat__id">
			<span class="apprex-chat__avatar" aria-hidden="true">🤖</span>
			<div>
				<strong><?php esc_html_e( 'APPREX サポート', 'apprex' ); ?></strong>
				<span class="apprex-chat__status"><span class="apprex-chat__dot" aria-hidden="true"></span><?php esc_html_e( 'オンライン｜お気軽にどうぞ', 'apprex' ); ?></span>
			</div>
		</div>
		<button class="apprex-chat__close" type="button" aria-label="<?php esc_attr_e( '閉じる', 'apprex' ); ?>">×</button>
	</div>
	<div class="apprex-chat__log" id="apprex-chat-log" aria-live="polite"></div>
	<div class="apprex-chat__quick" id="apprex-chat-quick">
		<button type="button" data-q="料金を教えて"><?php esc_html_e( '料金を知りたい', 'apprex' ); ?></button>
		<button type="button" data-q="見積もりをしたい"><?php esc_html_e( '見積もりしたい', 'apprex' ); ?></button>
		<button type="button" data-q="どんなアプリが作れますか？"><?php esc_html_e( '作れるアプリは？', 'apprex' ); ?></button>
	</div>
	<form class="apprex-chat__form" id="apprex-chat-form">
		<input type="text" id="apprex-chat-input" autocomplete="off" placeholder="<?php esc_attr_e( 'メッセージを入力…', 'apprex' ); ?>" aria-label="<?php esc_attr_e( 'メッセージ', 'apprex' ); ?>">
		<button type="submit" aria-label="<?php esc_attr_e( '送信', 'apprex' ); ?>">➤</button>
	</form>
	<a class="apprex-chat__cta" href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>"><?php esc_html_e( '▶ 見積もり〜発注', 'apprex' ); ?></a>
	<a class="apprex-chat__cta apprex-chat__cta--alt" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( '▶ オンライン相談を予約', 'apprex' ); ?></a>
	<?php $apprex_line = apprex_line_url(); ?>
	<?php if ( $apprex_line ) : ?>
		<a class="apprex-chat__line" href="<?php echo esc_url( $apprex_line ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LINEで相談する', 'apprex' ); ?></a>
	<?php endif; ?>
</div>
<?php else : ?>
<div class="apprex-chat-window" id="apprex-chat-window" data-src="<?php echo esc_url( $apprex_zapier ); ?>"></div>
<?php endif; ?>
