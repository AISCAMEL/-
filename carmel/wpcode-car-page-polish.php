<?php
/**
 * カーメル：車両ページ 仕上げ（モック準拠・Playwright検証済み）
 * ---------------------------------------------------------------------------
 * 車両詳細ページ（single-portfolio）を整える。実ページで目視検証済み。
 *   ・不要要素の除去／基本情報の緑表
 *   ・ローン枠：旧版は再構築、新版(v4.0)はそのまま（崩れ防止ガード）
 *   ・返済シミュをローン枠内に収納／上段の幅・余白・カード統一
 *   ・担当スタッフ＋販売店ブロックを上段直後へ移動
 *   ・上段の問い合わせカードは削除し、お問い合わせ導線を店舗ブロックへ集約
 * ・固有名＋function_exists ガード。front のみ出力。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_page_polish_css' ) ) {
	function carmelx_page_polish_css() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-page-polish">
/* hides */
.wpb_wrapper:has(> .wpb_text_column > .wpb_wrapper > .carmel-lg) > .wpb_text_column.has-custom-color,
.wpb_wrapper:has(> .wpb_text_column > .wpb_wrapper > .cmx-loan) > .wpb_text_column.has-custom-color { display:none !important; }
.vc_section.vc_custom_carmelplan:not(:has(img, table, a, li, .carmel-plan)) { display:none !important; }
.vc_custom_1729509516503 { display:none !important; }
/* 上段の問い合わせカードは削除し、下の担当・店舗ブロック(上に移動)に導線を集約 */
.vc_custom_1707390645994 { display:none !important; }

/* 基本情報・スペック表 */
.c-table01{width:100%;border-collapse:collapse;}
.c-table01 th{background:#f1f9f4 !important;color:#6a7572 !important;font-weight:700 !important;font-size:13px;text-align:left;padding:10px 12px !important;border:1px solid #e2e8e4 !important;white-space:nowrap;width:42%;}
.c-table01 td{font-size:13px;font-weight:700 !important;color:#20292b !important;padding:10px 12px !important;border:1px solid #e2e8e4 !important;}
.p-single-sec2__table{display:grid;grid-template-columns:1fr 1fr;gap:0 20px;}
@media(max-width:640px){.p-single-sec2__table{grid-template-columns:1fr;}}

/* シミュレーション */
.carmel-lg__sim-fullwide{max-width:1040px;margin-left:auto;margin-right:auto;}
.carmel-lg__sim-badge{background:#2cac44 !important;color:#fff !important;}
.carmel-lg__sim-amount b{color:#2cac44 !important;}
.carmel-lg__sim-fullwide .carmel-lg__sim-title{display:none !important;}

/* スタッフ微調整 */
.cx-staff__role{background:#e8500a !important;color:#fff !important;}
.cx-staff__name{font-weight:900 !important;}

/* 問い合わせ SVG */
.cmx-contact{text-align:left;}
.cmx-contact h3{margin:0 0 10px;font-size:13px;text-align:center;color:#6a7572;font-weight:800;}
.cmx-c{display:flex;align-items:center;gap:11px;padding:12px 15px;margin-bottom:9px;border:1.5px solid #e2e8e4;border-radius:12px;text-decoration:none;color:#20292b;font-weight:800;font-size:14.5px;line-height:1.3;}
.cmx-c:last-child{margin-bottom:0;}
.cmx-c svg{width:22px;height:22px;flex-shrink:0;color:#2cac44;}
.cmx-c__t{display:flex;flex-direction:column;}
.cmx-c__t small{font-size:11px;font-weight:600;color:#6a7572;margin-top:2px;}
.cmx-c--tel{background:#fffdf4;border-color:#f0e4b4;}
.cmx-c--tel svg{color:#e8a400;}
.cmx-c--mail{background:#fff;}

/* ===== ローンボックス 作り直し（cmx-loan） ===== */
.cmx-loan{background:#fff;border:1px solid #cfe7d8;border-radius:16px;overflow:hidden;box-shadow:0 6px 22px -14px rgba(28,122,58,.45);}
.cmx-loan__hd{background:linear-gradient(135deg,#1c7a3a,#2cac44);color:#fff;padding:15px 18px;}
.cmx-loan__price-row{display:flex;align-items:baseline;justify-content:space-between;gap:8px;}
.cmx-loan__lab{font-size:11px;font-weight:700;opacity:.92;letter-spacing:.04em;}
.cmx-loan__price{font-size:26px;font-weight:900;line-height:1;}
.cmx-loan__price small{font-size:13px;font-weight:700;opacity:.9;margin-left:2px;}
.cmx-loan__month{margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.28);display:flex;align-items:baseline;justify-content:space-between;}
.cmx-loan__ml{font-size:12px;font-weight:700;opacity:.95;}
.cmx-loan__mv{font-size:27px;font-weight:900;line-height:1;}
.cmx-loan__mv small{font-size:13px;margin-left:1px;font-weight:700;}
.cmx-loan__bd{padding:14px 18px 18px;}
.cmx-loan__t{font-size:12px;font-weight:800;color:#1c7a3a;margin-bottom:8px;}
.cmx-chips{display:flex;gap:7px;}
.cmx-chip{flex:1;background:#f1f9f4;border:1px solid #cfe7d8;border-radius:10px;padding:8px 3px;text-align:center;}
.cmx-chip__c{display:block;font-size:11px;color:#1c7a3a;font-weight:800;}
.cmx-chip__m{display:block;font-size:13.5px;font-weight:900;color:#20292b;}
.cmx-loan__note{margin-top:8px;font-size:11px;color:#6a7572;}
.cmx-cta{display:flex;flex-direction:column;gap:8px;margin-top:13px;}
.cmx-cta__btn{display:block;text-align:center;padding:13px;border-radius:10px;font-weight:800;font-size:14.5px;text-decoration:none;color:#fff;}
.cmx-cta__btn--shinsa{background:#e8500a;}
.cmx-cta__btn--line{background:#06c755;}
.cmx-cta__sub{font-size:11px;color:#6a7572;text-align:center;margin:2px 0 0;}
.carmel-lg__warranty-slots{display:flex;flex-wrap:wrap;gap:6px;margin-top:11px;}
.carmel-lg__warranty-badge{display:inline-flex;align-items:center;gap:5px;background:#f1f9f4;border:1px solid #cfe7d8;color:#1c7a3a;border-radius:999px;padding:5px 11px;font-size:12px;font-weight:700;}


/* ===== 全体バランス調整 ===== */
/* ① 上段：ローン枠を右カラムの幅いっぱいに（下のスペック表と幅をそろえる） */
.vc_column_container.vc_col-xs-8:has(.carmel-lg), .vc_column_container.vc_col-xs-8:has(.cmx-loan){
  width:100% !important; max-width:100% !important; flex-basis:100% !important;
}
/* ② シミュをローン枠内に戻した後の体裁（全幅ラッパ用CSSは無効化） */
.carmel-lg__sim-fullwide{ all:unset; display:block; }
/* ③ セクション間の余白を少し詰める */
.single-portfolio .vc_section.wpex-vc_section-has-fill{ margin-top:0 !important; }
.single-portfolio .vc_row.vc_custom_1707905591743,
.single-portfolio .vc_row.vc_custom_1707913463113{ padding-top:6px !important; }
/* ④ カード統一：主要セクションの角丸・影・枠をそろえる */
.single-portfolio .wpex-vc_section-has-fill > .vc_row{ border-radius:16px; }
</style>
<script id="carmel-page-polish-js">
(function(){
  function rebuildLoan(){
    var lg = document.querySelector('.carmel-lg');
    if(!lg || lg.getAttribute('data-cmx')==='1') return;
    var priceEl = lg.querySelector('.carmel-lg__price-val');
    var priceHtml = priceEl ? priceEl.innerHTML : '';
    var rows = [].slice.call(lg.querySelectorAll('.carmel-lg__row')).map(function(r){
      var b = r.querySelector('b'); var count = b ? b.textContent.trim() : '';
      var amt = r.textContent.replace(count,'').replace(/月々/g,'').trim();
      return { count: count, amt: amt };
    });
    /* v4.0など別マークアップ（price-val/row が無い＝既にきれいな緑デザイン）では
       作り直さず元のまま残す。空ボックス化を防ぐ崩れ防止ガード。 */
    if(!priceHtml || rows.length === 0) return;
    var m84 = '';
    rows.forEach(function(r){ if(r.count.indexOf('84')!==-1) m84 = r.amt; });
    var noteEl = lg.querySelector('.carmel-lg__note');
    var note = noteEl ? noteEl.textContent.trim() : '';
    var shinsa = lg.querySelector('.carmel-lg__btn--shinsa');
    var lineB = lg.querySelector('.carmel-lg__btn--line');
    var shinsaHref = shinsa ? shinsa.getAttribute('href') : '#';
    var lineHref = lineB ? lineB.getAttribute('href') : '#';
    // 元のCTA（.carmel-lg の兄弟要素）は重複するので除去
    document.querySelectorAll('.carmel-lg__cta').forEach(function(e){ e.parentNode && e.parentNode.removeChild(e); });
    var slots = lg.querySelector('.carmel-lg__warranty-slots');
    var slotsHtml = slots ? slots.outerHTML : '<div class="carmel-lg__warranty-slots"></div>';

    var chips = rows.map(function(r){
      return '<div class="cmx-chip"><span class="cmx-chip__c">'+r.count+'</span><span class="cmx-chip__m">'+r.amt+'</span></div>';
    }).join('');

    var monthLine = m84 ? '<div class="cmx-loan__month"><span class="cmx-loan__ml">月々のお支払い（84回）</span><span class="cmx-loan__mv">'+m84.replace('円','')+'<small>円〜</small></span></div>' : '';

    lg.setAttribute('data-cmx','1');
    lg.className = 'cmx-loan';
    lg.innerHTML =
      '<div class="cmx-loan__hd">'+
        '<div class="cmx-loan__price-row"><span class="cmx-loan__lab">車両本体価格</span><span class="cmx-loan__price">'+priceHtml+'</span></div>'+
        monthLine +
      '</div>'+
      '<div class="cmx-loan__bd">'+
        '<div class="cmx-loan__t">支払い回数別の目安</div>'+
        '<div class="cmx-chips">'+chips+'</div>'+
        (note ? '<div class="cmx-loan__note">'+note+'</div>' : '')+
        slotsHtml +
        '<div class="cmx-cta">'+
          '<a class="cmx-cta__btn cmx-cta__btn--shinsa" href="'+shinsaHref+'" target="_blank" rel="noopener">かんたん審査申込（無料）</a>'+
          '<a class="cmx-cta__btn cmx-cta__btn--line" href="'+lineHref+'" target="_blank" rel="noopener">LINEで相談</a>'+
          '<p class="cmx-cta__sub">最短即日審査・秘密厳守・在籍確認なし</p>'+
        '</div>'+
      '</div>';
  }

  function rebuildContact(){
    var head=null, cols=document.querySelectorAll('.wpb_wrapper > .wpb_text_column');
    for(var i=0;i<cols.length;i++){ if(cols[i].textContent.indexOf('このクルマに関するお問い合わせ')!==-1){head=cols[i];break;} }
    if(!head) return;
    var wrap=head.parentNode;
    if(!wrap || wrap.getAttribute('data-cmx-contact')==='1') return;
    var telA=wrap.querySelector('a[href^="tel:"]');
    var mailA=wrap.querySelector('a[href*="forms.gle"], a[href*="forms.google"], a[href^="mailto:"]');
    var tel=telA?telA.getAttribute('href'):'tel:050-1793-5554';
    var mail=mailA?mailA.getAttribute('href'):'#';
    var ext=(mail.indexOf('http')===0);
    var ph='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>';
    var ml='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>';
    wrap.setAttribute('data-cmx-contact','1');
    wrap.innerHTML='<div class="cmx-contact"><h3>このクルマのお問い合わせ</h3>'+
      '<a class="cmx-c cmx-c--tel" href="'+tel+'">'+ph+'<span class="cmx-c__t">電話で問い合わせ<small>受付 10:00〜18:00</small></span></a>'+
      '<a class="cmx-c cmx-c--mail" href="'+mail+'"'+(ext?' target="_blank" rel="noopener noreferrer"':'')+'>'+ml+'<span class="cmx-c__t">メールで相談<small>24時間受付OK</small></span></a></div>';
  }


  function restoreSim(){
    var sim = document.querySelector('.carmel-lg__sim');
    if(!sim) return;
    var bd = document.querySelector('.carmel-lg__bd') || document.querySelector('.cmx-loan__bd');
    if(!bd) return;
    if(!bd.contains(sim)){
      var cta = bd.querySelector('.cmx-cta, .carmel-lg__cta');
      if(cta) bd.insertBefore(sim, cta); else bd.appendChild(sim);
    }
    var fw = document.querySelector('.carmel-lg__sim-fullwide');
    if(fw && !fw.querySelector('.carmel-lg__sim') && fw.parentNode){ fw.parentNode.removeChild(fw); }
  }


  function moveStaffUp(){
    var secs = document.querySelectorAll('.custom-singular-template > .vc_section, .single-portfolio .vc_section');
    var top=null, staff=null;
    secs.forEach(function(s){
      if(!top && s.querySelector('.carmel-lg, .cmx-loan')) top = s;
      if(!staff && s.querySelector('.cx-staff')) staff = s;
    });
    if(top && staff && staff!==top && top.parentNode && staff.getAttribute('data-cmx-moved')!=='1'){
      staff.setAttribute('data-cmx-moved','1');
      top.parentNode.insertBefore(staff, top.nextSibling);
    }
  }

  function run(){ rebuildContact(); rebuildLoan(); restoreSim(); moveStaffUp(); }
  // moveSim(既存) がシミュを .carmel-lg の外へ移すので、その後に実行
  if(document.readyState==='loading') window.addEventListener('DOMContentLoaded', function(){ setTimeout(run,60); });
  else setTimeout(run,60);
})();
</script>
		<?php
	}
	add_action( 'wp_head', 'carmelx_page_polish_css', 99 );
}
