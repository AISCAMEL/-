<?php
/**
 * Static placeholder form shown until a real form plugin shortcode is added.
 *
 * NOTE: This form does not submit anywhere. Replace it by pasting a
 * Contact Form 7 / WPForms shortcode into the page editor (spec §10 公開前
 * 確認 — フォーム送信テスト).
 *
 * @package APPREX
 *
 * @var array $args Passed via get_template_part(): ['type' => 'trial'|'contact'].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_type = isset( $args['type'] ) ? $args['type'] : 'contact';
$apprex_submit = ( 'trial' === $apprex_type ) ? __( '30日間 無料体験を始める', 'apprex' ) : __( '送信する', 'apprex' );
?>
<div style="background:var(--color-primary-light);border:1px dashed var(--color-primary);border-radius:var(--radius);padding:14px 18px;margin-bottom:24px;font-size:.9rem">
	<?php esc_html_e( '⚠ これは仮フォームです。本番では Contact Form 7 / WPForms のショートカットをこのページに貼り付けてください。', 'apprex' ); ?>
</div>

<form class="apprex-form" onsubmit="return false" style="display:grid;gap:18px">
	<label style="font-weight:600">
		<?php esc_html_e( '会社名', 'apprex' ); ?>
		<input type="text" name="company" style="width:100%;min-height:48px;padding:0 14px;border:1px solid var(--color-line);border-radius:var(--radius-sm);margin-top:6px">
	</label>
	<label style="font-weight:600">
		<?php esc_html_e( 'お名前', 'apprex' ); ?> <span style="color:var(--color-accent)">*</span>
		<input type="text" name="name" required style="width:100%;min-height:48px;padding:0 14px;border:1px solid var(--color-line);border-radius:var(--radius-sm);margin-top:6px">
	</label>
	<label style="font-weight:600">
		<?php esc_html_e( 'メールアドレス', 'apprex' ); ?> <span style="color:var(--color-accent)">*</span>
		<input type="email" name="email" required style="width:100%;min-height:48px;padding:0 14px;border:1px solid var(--color-line);border-radius:var(--radius-sm);margin-top:6px">
	</label>
	<label style="font-weight:600">
		<?php esc_html_e( 'ご相談内容', 'apprex' ); ?>
		<textarea name="message" rows="5" style="width:100%;padding:14px;border:1px solid var(--color-line);border-radius:var(--radius-sm);margin-top:6px"></textarea>
	</label>
	<button type="submit" class="btn btn--primary btn--block"><?php echo esc_html( $apprex_submit ); ?></button>
</form>
