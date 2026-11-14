<?php
/**
 * カーメル：車両ページ 不安解消ミニFAQ（信用回復ローン向け）
 * ---------------------------------------------------------------------------
 * 「他社で断られた」「頭金なし」など、検討中のお客様の不安をその場で解消する開閉FAQ。
 * 車両詳細ページのローン枠付近に表示。問い合わせ前の離脱を防ぐ。
 * ※ 誇大表現は避け「必ず通る」等は書かない。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_faq_render' ) ) {
	function carmelx_faq_render() {
		if ( is_admin() || ! is_singular( 'portfolio' ) ) { return; }

		$faqs = array(
			array(
				'他社の審査に落ちた・断られたのですが、相談できますか？',
				'はい。他社様で審査に通らなかった方からのご相談を数多くいただいています。カーメルは信用回復に特化し、ご状況に合わせて「通るための進め方」を一緒に考えます。まずはお気軽にご相談ください。',
			),
			array(
				'頭金がなくても購入できますか？',
				'頭金なしでのご相談も可能です。月々のご負担を抑えたプランなど、無理のないお支払い方法をご提案します。',
			),
			array(
				'過去に延滞・債務整理・自己破産があっても大丈夫ですか？',
				'ご事情がある方のご相談も承っています。審査には与信枠などの条件がありますので、まずは状況をお聞かせください。秘密は厳守します。',
			),
			array(
				'月々の支払いはどのくらいになりますか？',
				'この車両ページのローン概算で、頭金や支払回数ごとの月々の目安をご確認いただけます。正式なお支払額は審査・プランにより決まりますので、シミュレーションを目安にご相談ください。',
			),
			array(
				'全国どこでも購入・納車できますか？',
				'全国対応しております。お住まいの地域や納車のご希望も、お問い合わせ時にお聞かせください。',
			),
			array(
				'保証やアフターサービスはありますか？',
				'保証をご用意しています。保証の有無・内容は車両により異なりますので、基本情報や内訳の「保証」欄、またはお問い合わせでご確認ください。',
			),
		);

		$plus  = '<svg class="cmx-faq__ic" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6z"/></svg>';
		?>
<style id="carmel-faq-css">
.cmx-faq{margin:16px 0 4px;}
.cmx-faq__hd{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:900;color:#17331f;margin:0 0 10px;}
.cmx-faq__hd .cmx-faq__badge{display:inline-flex;align-items:center;gap:5px;background:#009de0;color:#fff;border-radius:6px;padding:3px 9px;font-size:11px;font-weight:800;}
.cmx-faq__list{border:1px solid #e3e8ee;border-radius:12px;overflow:hidden;background:#fff;}
.cmx-faq__item{border-bottom:1px solid #eef1f4;}
.cmx-faq__item:last-child{border-bottom:none;}
.cmx-faq__q{list-style:none;cursor:pointer;display:flex;align-items:center;gap:11px;padding:14px 15px;font-size:13.5px;font-weight:800;color:#233;background:#fff;transition:.15s;}
.cmx-faq__q:hover{background:#f7fafc;}
.cmx-faq__q::-webkit-details-marker{display:none;}
.cmx-faq__q .cmx-faq__qm{flex:0 0 auto;width:22px;height:22px;border-radius:50%;background:#e8f4fb;color:#009de0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;}
.cmx-faq__ic{width:16px;height:16px;fill:#009de0;margin-left:auto;transition:.2s;flex:0 0 auto;}
.cmx-faq__item[open] .cmx-faq__ic{transform:rotate(45deg);}
.cmx-faq__a{padding:0 15px 15px 48px;font-size:13px;line-height:1.75;color:#4a545e;}
.cmx-faq__a:before{content:"A";display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#eef7f1;color:#2cac44;font-weight:900;font-size:12px;margin:0 8px -5px -34px;}
.cmx-faq__cta{display:flex;gap:9px;margin-top:11px;flex-wrap:wrap;}
.cmx-faq__cta a{flex:1;min-width:140px;text-align:center;text-decoration:none;font-weight:900;font-size:13px;border-radius:9px;padding:12px;}
.cmx-faq__cta .is-line{background:#06c755;color:#fff;}
.cmx-faq__cta .is-contact{background:#ff7a00;color:#fff;}
</style>
<div id="cmx-faq-src" style="display:none;">
	<section class="cmx-faq">
		<div class="cmx-faq__hd"><span class="cmx-faq__badge">安心</span>よくあるご不安・ご質問</div>
		<div class="cmx-faq__list">
			<?php foreach ( $faqs as $f ) : ?>
			<details class="cmx-faq__item">
				<summary class="cmx-faq__q"><span class="cmx-faq__qm">Q</span><span><?php echo esc_html( $f[0] ); ?></span><?php echo $plus; // phpcs:ignore ?></summary>
				<div class="cmx-faq__a"><?php echo esc_html( $f[1] ); ?></div>
			</details>
			<?php endforeach; ?>
		</div>
	</section>
</div>
<script id="carmel-faq-js">
(function(){
	function run(){
		var src=document.getElementById('cmx-faq-src'); if(!src) return; var node=src.firstElementChild; if(!node) return;
		/* 内訳(.cmx-bd)の後 → なければローン枠の後 → なければ最初のセクションの後 */
		var anchor=document.querySelector('.cmx-bd')||document.querySelector('.carmel-lg');
		if(anchor&&anchor.parentNode){ anchor.parentNode.insertBefore(node, anchor.nextSibling); src.remove(); return; }
		var sec=document.querySelector('.single-portfolio .vc_section, article .vc_section');
		if(sec&&sec.parentNode){ sec.parentNode.insertBefore(node, sec.nextSibling); src.remove(); }
	}
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){ setTimeout(run,160); });
	else setTimeout(run,160);
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_faq_render', 25 );
}
