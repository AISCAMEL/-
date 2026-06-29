<?php
/**
 * 簡易事前審査（セルフ診断）ウィジェット。
 *
 * ショートコード [carmel_prescreen]。属性入力（希望価格・頭金・年収・雇用形態・
 * 勤続年数・他社借入）から、借入可能額/月々/判定の「目安」をその場で提示する。
 * 実際の与信審査ではなく参考値（クライアント計算）で、信販審査の可否は別途決定。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Prescreen {

	/** @var Carmel_Prescreen|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_prescreen';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	public function render() {
		$d      = class_exists( 'Carmel_Sales_Support' ) ? Carmel_Sales_Support::finance_defaults() : array( 'loan_rate' => 8.9, 'loan_months' => 60 );
		$rate   = isset( $d['loan_rate'] ) ? (float) $d['loan_rate'] : 8.9;
		$months = isset( $d['loan_months'] ) ? (int) $d['loan_months'] : 60;
		$apply  = home_url( '/' . ltrim( apply_filters( 'carmel_apply_page_slug', 'apply' ), '/' ) );

		ob_start();
		?>
<style>
.carmel-ps{font-size:14px;max-width:560px;border:1px solid #ddd2f5;border-radius:12px;padding:1em 1.2em;background:#faf9fc}
.carmel-ps h2{margin:.1em 0 .2em;font-size:1.15em}
.carmel-ps .lead{color:#7a7488;font-size:.85em;margin:0 0 .8em}
.carmel-ps label{display:block;font-size:.82em;color:#555;margin:.45em 0}
.carmel-ps input,.carmel-ps select{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.45em;margin-top:.2em}
.carmel-ps-row{display:flex;gap:.7em}.carmel-ps-row label{flex:1}
.carmel-ps-result{margin-top:1em;border-top:1px dashed #ddd2f5;padding-top:.8em}
.carmel-ps-judge{font-size:1.1em;font-weight:bold;margin:.3em 0}
.carmel-ps-ok{color:#16a085}.carmel-ps-mid{color:#e67e22}.carmel-ps-ng{color:#c0392b}
.carmel-ps-line{font-size:.95em;margin:.2em 0}
.carmel-ps-line strong{color:#6b4fbb}
.carmel-ps-note{font-size:.76em;color:#888;margin:.6em 0}
.carmel-ps-btn{display:inline-block;background:#6b4fbb;color:#fff;border-radius:.3em;padding:.6em 1.2em;text-decoration:none;margin-top:.4em}
</style>
<div class="carmel-ps" data-rate="<?php echo esc_attr( $rate ); ?>" data-months="<?php echo (int) $months; ?>">
	<h2>かんたん事前診断</h2>
	<p class="lead">入力するとお支払いの目安をその場で表示します（参考値・審査ではありません）。</p>
	<div class="carmel-ps-row">
		<label>希望車両価格（円）<input type="number" class="ps-price" value="1500000" min="0" step="10000"></label>
		<label>頭金（円）<input type="number" class="ps-down" value="0" min="0" step="10000"></label>
	</div>
	<div class="carmel-ps-row">
		<label>年収（万円）<input type="number" class="ps-income" value="350" min="0" step="10"></label>
		<label>他社借入（万円）<input type="number" class="ps-debt" value="0" min="0" step="10"></label>
	</div>
	<div class="carmel-ps-row">
		<label>雇用形態
			<select class="ps-emp">
				<option value="5">正社員・公務員</option>
				<option value="4">契約・派遣</option>
				<option value="4">自営業</option>
				<option value="3">パート・アルバイト</option>
				<option value="3">その他</option>
			</select>
		</label>
		<label>回数<input type="number" class="ps-months" value="<?php echo (int) $months; ?>" min="1" step="1"></label>
	</div>

	<div class="carmel-ps-result">
		<div class="carmel-ps-judge ps-judge">—</div>
		<div class="carmel-ps-line">借入可能額の目安：<strong class="ps-capacity">¥0</strong></div>
		<div class="carmel-ps-line">必要なお借入：<strong class="ps-need">¥0</strong></div>
		<div class="carmel-ps-line">月々のお支払い目安：<strong class="ps-monthly">¥0</strong> <span class="ps-times"></span></div>
	</div>
	<p class="carmel-ps-note">※ 目安です。最終的な可否・金利・条件は信販会社の審査により決定します。借入可能額は年収・雇用形態からの簡易試算で、実際の与信枠とは異なります。</p>
	<a class="carmel-ps-btn" data-apply="<?php echo esc_url( $apply ); ?>" href="<?php echo esc_url( $apply ); ?>">この内容で審査を申し込む</a>
</div>
<script>
(function(){
	var w=document.currentScript.previousElementSibling;
	if(!w||!w.classList.contains('carmel-ps'))return;
	var RATE=parseFloat(w.getAttribute('data-rate'))||8.9;
	function yen(n){return '¥'+(Math.round(n)).toLocaleString('ja-JP');}
	function pmt(p,m,annual){p=Math.max(0,p);m=Math.max(1,m);var r=annual/100/12;if(r<=0)return Math.round(p/m);var k=Math.pow(1+r,m);return Math.round(p*r*k/(k-1));}
	function calc(){
		var price=parseFloat(w.querySelector('.ps-price').value)||0;
		var down=parseFloat(w.querySelector('.ps-down').value)||0;
		var income=(parseFloat(w.querySelector('.ps-income').value)||0)*10000;
		var debt=(parseFloat(w.querySelector('.ps-debt').value)||0)*10000;
		var coef=parseFloat(w.querySelector('.ps-emp').value)||3;
		var months=parseInt(w.querySelector('.ps-months').value)||1;
		var capacity=Math.max(0,income*coef-debt);
		var need=Math.max(0,price-down);
		var mo=pmt(need,months,RATE);
		var ratio=income>0?(mo*12/income):1;
		w.querySelector('.ps-capacity').textContent=yen(capacity);
		w.querySelector('.ps-need').textContent=yen(need);
		w.querySelector('.ps-monthly').textContent=yen(mo);
		w.querySelector('.ps-times').textContent='× '+months+'回';
		var j=w.querySelector('.ps-judge'),cls='carmel-ps-judge ps-judge ',txt;
		if(need>capacity){cls+='carmel-ps-ng';txt='⚠ ご希望額が目安を上回ります（頭金の追加や条件のご相談を）';}
		else if(ratio<=0.25){cls+='carmel-ps-ok';txt='◎ 余裕をもってご利用いただける目安です';}
		else if(ratio<=0.35){cls+='carmel-ps-ok';txt='○ 標準的なご利用範囲の目安です';}
		else{cls+='carmel-ps-mid';txt='△ ややご負担が大きめ。頭金・回数の調整をおすすめします';}
		j.className=cls;j.textContent=txt;
		// 申込CTAに希望条件を引き継ぐ。
		var btn=w.querySelector('.carmel-ps-btn');
		if(btn){var base=btn.getAttribute('data-apply')||'';var sep=base.indexOf('?')>=0?'&':'?';
			btn.href=base+sep+'price='+Math.round(price)+'&down='+Math.round(down)+'&months='+months;}
	}
	w.addEventListener('input',calc);w.addEventListener('change',calc);calc();
})();
</script>
		<?php
		return ob_get_clean();
	}
}
