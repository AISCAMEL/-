<?php
/**
 * リニューアル版トップ：よくある質問。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sec" id="faq">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">FAQ</span><h2>よくあるご質問</h2></div>
    <div class="faq">
      <?php foreach ( apprex_rn_faqs() as $apprex_i => $apprex_faq ) : ?>
      <details<?php echo 0 === $apprex_i ? ' open' : ''; ?>><summary><?php echo esc_html( $apprex_faq['q'] ); ?></summary><p><?php echo esc_html( $apprex_faq['a'] ); ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
