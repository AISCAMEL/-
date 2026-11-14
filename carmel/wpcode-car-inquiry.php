<?php
/**
 * カーメル：お問い合わせ「車両連動」
 * ---------------------------------------------------------------------------
 * 目的：車両ページから「お問い合わせ」した時、どの車かが担当者に確実に伝わるようにする。
 *
 * 仕組み（既存を活かす・プラグイン非改造）：
 *  (1) 車両詳細ページで、/contact-form および /shinsa 系リンクに車両情報をクエリ付与
 *      ?vehicle-id= &vehicle-title= &vehicle-url= &page-title= &page-url=
 *      &inquiry-source=vehicle-detail &cta-label=<ボタン文言>
 *  (2) 問い合わせページ側で、そのクエリを Contact Form 7 の既存隠し項目
 *      （vehicle-id / vehicle-title / vehicle-url / page-title / page-url /
 *        inquiry-source / cta-label）へ自動入力。
 *  (3) フォーム上部に「お問い合わせ車両：◯◯」を表示（お客様・担当者ともに分かる）。
 *
 * ※ CF7フォーム側・チャットボット側のプラグインは変更しない。隠し項目が既にあるので
 *   フロント側から値を入れるだけ。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_inquiry_render' ) ) {
	function carmelx_inquiry_render() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-inq-css">
.cinq-banner{display:flex;align-items:center;gap:12px;background:#eef7f1;border:1px solid #cfe7d8;border-left:4px solid #2cac44;
	border-radius:10px;padding:11px 14px;margin:0 0 18px;}
.cinq-banner__ic{flex:0 0 auto;width:34px;height:34px;border-radius:8px;background:#2cac44;display:flex;align-items:center;justify-content:center;}
.cinq-banner__ic svg{width:19px;height:19px;fill:#fff;}
.cinq-banner__tx{min-width:0;line-height:1.4;}
.cinq-banner__lb{font-size:11px;font-weight:800;color:#1c7a3a;letter-spacing:.03em;}
.cinq-banner__ti{font-size:14px;font-weight:900;color:#17331f;overflow:hidden;text-overflow:ellipsis;}
.cinq-banner__ti a{color:#1c7a3a;text-decoration:underline;}
</style>
<script id="carmel-inq-js">
(function(){
	'use strict';
	var CONTACT_RE=/\/contact-form|\/contact(\/|$|\?)|\/shinsa/i; /* 付与対象リンク */

	function clean(s){ return (s||'').replace(/\s*[|｜].*$/,'').replace(/^信用回復ローン\s*/,'').trim(); }
	function carData(){
		var url=((document.querySelector('link[rel=canonical]')||{}).href)||location.href; url=url.split('#')[0].split('?')[0];
		var title=''; var og=document.querySelector('meta[property="og:title"]'); if(og)title=og.getAttribute('content')||'';
		if(!title){ var h=document.querySelector('.single-portfolio h1, article h1, h1'); if(h)title=h.textContent; }
		title=clean(title)||clean(document.title);
		var id=''; var m=location.pathname.match(/\/search\/(\d+)/); if(m)id=m[1];
		if(!id){ var bc=(document.body.className.match(/postid-(\d+)/)||[]); id=bc[1]||''; }
		/* 管理番号（CM-xxxx）があれば拾ってタイトルに添える手がかり */
		var mg=''; var bodytxt=document.body.innerText||''; var mm=bodytxt.match(/\bCM-\d{3,}\b/); if(mm)mg=mm[0];
		return {id:id,title:title,url:url,mg:mg};
	}
	function isDetail(){ return document.body.classList.contains('single-portfolio') || /\/search\/\d+/.test(location.pathname) || !!document.querySelector('.carmel-lg'); }

	/* ---- (1) 車両ページ：問い合わせリンクへ車両情報を付与 ---- */
	function tagLinks(){
		if(!isDetail()) return;
		var c=carData(); if(!c.title) return;
		var vid = c.id || c.mg || '';
		var vtitle = c.title + (c.mg && c.title.indexOf(c.mg)===-1 ? (' ('+c.mg+')') : '');
		document.querySelectorAll('a[href]').forEach(function(a){
			var href=a.getAttribute('href')||''; if(!href||!CONTACT_RE.test(href)) return;
			if(a.getAttribute('data-cinq')==='1') return; a.setAttribute('data-cinq','1');
			var label=clean((a.textContent||'').replace(/\s+/g,' ')).slice(0,40);
			var sep=href.indexOf('?')===-1?'?':'&';
			var q='vehicle-id='+encodeURIComponent(vid)
				+'&vehicle-title='+encodeURIComponent(vtitle)
				+'&vehicle-url='+encodeURIComponent(c.url)
				+'&page-title='+encodeURIComponent(vtitle)
				+'&page-url='+encodeURIComponent(c.url)
				+'&inquiry-source='+encodeURIComponent('vehicle-detail')
				+'&cta-label='+encodeURIComponent(label||'お問い合わせ');
			a.setAttribute('href', href+sep+q);
		});
	}

	/* ---- (2)(3) 問い合わせページ：CF7隠し項目へ投入＋バナー表示 ---- */
	function qp(){ var o={}; (location.search.replace(/^\?/,'').split('&')).forEach(function(kv){ if(!kv)return; var i=kv.indexOf('='); var k=decodeURIComponent(kv.slice(0,i)); var v=decodeURIComponent(kv.slice(i+1).replace(/\+/g,' ')); o[k]=v; }); return o; }
	function setField(form,name,val,force){ if(val==null||val==='')return; var el=form.querySelector('[name="'+name+'"]'); if(el&&(force||!el.value)){ el.value=val; } }
	function fillForm(){
		var forms=document.querySelectorAll('form.wpcf7-form, form .wpcf7, .wpcf7 form, form');
		var p=qp();
		/* CF7隠し項目があるフォームだけ対象 */
		document.querySelectorAll('form').forEach(function(form){
			if(!form.querySelector('[name="vehicle-title"]')) return;
			setField(form,'vehicle-id',p['vehicle-id']);
			setField(form,'vehicle-title',p['vehicle-title']);
			setField(form,'vehicle-url',p['vehicle-url']);
			setField(form,'page-title',p['page-title']);
			setField(form,'page-url',p['page-url']);
			if(p['inquiry-source']) setField(form,'inquiry-source',p['inquiry-source'],true);
			setField(form,'cta-label',p['cta-label']);
			/* バナー（車名があれば）フォーム上部に1回だけ */
			if(p['vehicle-title'] && !document.getElementById('cinq-banner')){
				var wrap=form.closest('.wpcf7')||form;
				var b=document.createElement('div'); b.id='cinq-banner'; b.className='cinq-banner';
				var link=p['vehicle-url']?('<a href="'+encHtml(p['vehicle-url'])+'">'+encHtml(p['vehicle-title'])+'</a>'):encHtml(p['vehicle-title']);
				b.innerHTML='<div class="cinq-banner__ic"><svg viewBox="0 0 24 24"><path d="M18.9 6.5C18.7 5.6 17.9 5 17 5H7c-.9 0-1.6.6-1.9 1.5L3 12v8c0 .6.4 1 1 1h1c.6 0 1-.4 1-1v-1h12v1c0 .6.4 1 1 1h1c.6 0 1-.4 1-1v-8l-2.1-5.5zM7 16c-.8 0-1.5-.7-1.5-1.5S6.2 13 7 13s1.5.7 1.5 1.5S7.8 16 7 16zm10 0c-.8 0-1.5-.7-1.5-1.5S16.2 13 17 13s1.5.7 1.5 1.5S17.8 16 17 16zM5.8 11l1.2-3.5h10L18.2 11H5.8z"/></svg></div>'
					+'<div class="cinq-banner__tx"><div class="cinq-banner__lb">お問い合わせ車両</div><div class="cinq-banner__ti">'+link+'</div></div>';
				wrap.parentNode.insertBefore(b, wrap);
			}
		});
	}
	function encHtml(s){ return String(s||'').replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

	function run(){ tagLinks(); fillForm(); }
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){ setTimeout(run,100); });
	else setTimeout(run,100);
	setTimeout(tagLinks,900); /* 遅延生成リンクにも付与 */
	if(window.MutationObserver){ try{ new MutationObserver(function(){ tagLinks(); }).observe(document.body,{childList:true,subtree:true}); }catch(e){} }
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_inquiry_render', 23 );
}
