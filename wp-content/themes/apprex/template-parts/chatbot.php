<?php
/**
 * Floating Zapier chatbot (修正要件 §2). Toggle button + lazy iframe.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_chat = apprex_chatbot_url();
if ( ! $apprex_chat ) {
	return;
}
?>
<button class="apprex-chat-toggle" type="button" aria-expanded="false" aria-controls="apprex-chat-window" aria-label="<?php esc_attr_e( 'チャットで相談', 'apprex' ); ?>">💬</button>
<div class="apprex-chat-window" id="apprex-chat-window" data-src="<?php echo esc_url( $apprex_chat ); ?>">
	<!-- iframe injected on first open to avoid loading on every page view -->
</div>
