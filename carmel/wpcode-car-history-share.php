<?php
/**
 * カーメル：最近見た車（閲覧履歴）＋ この車を共有（LINE/メール/コピー）
 * ---------------------------------------------------------------------------
 * ・閲覧履歴…車両ページを開くと localStorage に自動保存。詳細ページ下部に
 *   「最近見た車」を横並び表示（現在の車は除外）。ログイン不要。
 * ・共有…詳細ページに「LINEで送る／メール／リンクをコピー」。家族・連帯保証人
 *   との相談用。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_histshare_render' ) ) {
	function carmelx_histshare_render() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-hs-css">
/* 共有 */
.cmx-share{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:8px 0 2px;}
.cmx-share__lb{font-size:12px;font-weight:800;color:#667;margin-right:1px;}
.cmx-share__b{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:8px 14px;font-size:12.5px;font-weight:800;
	cursor:pointer;border:1.5px solid transparent;text-decoration:none;line-height:1;}
.cmx-share__b svg{width:15px;height:15px;}
.cmx-share__line{background:#06c755;color:#fff;}
.cmx-share__mail{background:#fff;color:#4a545e;border-color:#d7dce2;}
.cmx-share__mail svg{fill:#6b7684;}
.cmx-share__copy{background:#fff;color:#4a545e;border-color:#d7dce2;}
.cmx-share__copy svg{fill:#6b7684;}
.cmx-share__b:active{filter:brightness(.96);}
/* 最近見た車 */
.cmx-hist{margin:26px 0 8px;}
.cmx-hist__hd{font-size:15px;font-weight:900;color:#17331f;margin:0 0 11px;display:flex;align-items:center;gap:8px;}
.cmx-hist__hd svg{width:18px;height:18px;fill:#2cac44;}
.cmx-hist__row{display:flex;gap:11px;overflow-x:auto;padding:2px 2px 8px;-webkit-overflow-scrolling:touch;}
.cmx-hist__card{flex:0 0 150px;width:150px;background:#fff;border:1px solid #e7eaef;border-radius:11px;overflow:hidden;text-decoration:none;color:inherit;}
.cmx-hist__card:hover{box-shadow:0 2px 10px rgba(0,0,0,.08);}
.cmx-hist__img{width:100%;height:98px;background:#eef0f3 center/cover no-repeat;}
.cmx-hist__meta{padding:8px 9px 10px;}
.cmx-hist__ttl{font-size:11.5px;font-weight:700;line-height:1.35;color:#333;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:31px;}
.cmx-hist__price{font-size:13px;font-weight:900;color:#1c7a3a;margin-top:5px;}
.cmx-toast2{position:fixed;left:50%;bottom:90px;transform:translateX(-50%);background:#222;color:#fff;font-size:13px;font-weight:700;padding:10px 18px;border-radius:999px;z-index:99999;opacity:0;transition:.2s;pointer-events:none;white-space:nowrap;}
.cmx-toast2.show{opacity:.95;}
</style>
<script id="carmel-hs-js">
(function(){
	'use strict';
	var HKEY='carmel_history';
	function clean(s){ return (s||'').replace(/\s*[|｜].*$/,'').replace(/^信用回復ローン\s*/,'').trim(); }
	function read(){ try{ return JSON.parse(localStorage.getItem(HKEY)||'[]'); }catch(e){ return []; } }
	function write(a){ try{ localStorage.setItem(HKEY, JSON.stringify(a)); }catch(e){} }
	function isDetail(){ return document.body.classList.contains('single-portfolio') || /\/search\/\d+/.test(location.pathname) || !!document.querySelector('.carmel-lg'); }
	function currentCar(){
		var url=((document.querySelector('link[rel=canonical]')||{}).href)||location.href; url=url.split('#')[0].split('?')[0];
		var title=''; var og=document.querySelector('meta[property="og:title"]'); if(og)title=og.getAttribute('content')||'';
		if(!title){ var h=document.querySelector('.single-portfolio h1, article h1, h1'); if(h)title=h.textContent; }
		title=clean(title)||clean(document.title);
		var img=''; var oi=document.querySelector('meta[property="og:image"]'); if(oi)img=oi.getAttribute('content')||'';
		if(!img){ var im=document.querySelector('.single-portfolio img, .wpex-slider img, article img'); if(im)img=im.currentSrc||im.src||''; }
		var id=''; var m=location.pathname.match(/\/search\/(\d+)/); if(m)id=m[1];
		if(!id){ var bc=(document.body.className.match(/postid-(\d+)/)||[]); id=bc[1]||''; }
		var price=''; var pe=document.querySelector('.carmel-lg__hd-price, .carmel-lg__hd-sub b'); if(pe)price=pe.textContent.replace(/\s+/g,'');
		return {id:id,title:title,url:url,img:img,price:price,ts:Date.now()};
	}
	function record(){
		if(!isDetail()) return; var c=currentCar(); if(!c.url||!c.title) return;
		var a=read().filter(function(x){ return x.url!==c.url && !(c.id&&x.id===c.id); });
		a.unshift(c); write(a.slice(0,20));
	}
	function esc(s){ return String(s||'').replace(/[&<>"]/g,function(ch){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch];}); }
	function toast(m){ var t=document.getElementById('cmx-toast2'); if(!t){ t=document.createElement('div'); t.id='cmx-toast2'; t.className='cmx-toast2'; document.body.appendChild(t);} t.textContent=m; t.classList.add('show'); clearTimeout(t._t); t._t=setTimeout(function(){t.classList.remove('show');},1600); }

	/* ---- 共有ボタン（詳細のみ）---- */
	function buildShare(){
		if(!isDetail()||document.getElementById('cmx-share')) return;
		var c=currentCar(); if(!c.url) return;
		var text=c.title+'｜カーメル';
		var box=document.createElement('div'); box.id='cmx-share'; box.className='cmx-share';
		var lineU='https://social-plugins.line.me/lineit/share?url='+encodeURIComponent(c.url);
		var mailU='mailto:?subject='+encodeURIComponent(text)+'&body='+encodeURIComponent(c.title+'\n'+c.url);
		box.innerHTML=''
			+'<span class="cmx-share__lb">共有：</span>'
			+'<a class="cmx-share__b cmx-share__line" href="'+esc(lineU)+'" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.5 2 2 5.6 2 10.1c0 4 3.6 7.4 8.5 8 .3.1.8.2.9.5.1.3.1.7 0 1l-.1.9c0 .3-.2 1 .9.6s5.9-3.5 8-6c1.4-1.6 2-3.2 2-5C22 5.6 17.5 2 12 2z"/></svg>LINEで送る</a>'
			+'<a class="cmx-share__b cmx-share__mail" href="'+esc(mailU)+'"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>メール</a>'
			+'<button type="button" class="cmx-share__b cmx-share__copy" id="cmx-copy"><svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>リンクをコピー</button>';
		box.querySelector('#cmx-copy').addEventListener('click',function(){
			var done=function(){ toast('リンクをコピーしました'); };
			if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(c.url).then(done,function(){ fallback(); }); }
			else fallback();
			function fallback(){ var i=document.createElement('input'); i.value=c.url; document.body.appendChild(i); i.select(); try{document.execCommand('copy');}catch(e){} i.remove(); done(); }
		});
		var anchor=document.getElementById('cfav-toggle');
		if(anchor&&anchor.parentNode){ anchor.parentNode.insertBefore(box, anchor.nextSibling); }
		else { var lg=document.querySelector('.carmel-lg'); if(lg&&lg.parentNode){ lg.parentNode.insertBefore(box, lg); } }
	}

	/* ---- 最近見た車（詳細のみ）---- */
	function buildHistory(){
		if(!isDetail()||document.getElementById('cmx-hist')) return;
		var cur=((document.querySelector('link[rel=canonical]')||{}).href||location.href).split('#')[0].split('?')[0];
		var list=read().filter(function(x){ return x.url!==cur; }).slice(0,10);
		if(list.length<1) return;
		var sec=document.createElement('section'); sec.id='cmx-hist'; sec.className='cmx-hist';
		sec.innerHTML='<div class="cmx-hist__hd"><svg viewBox="0 0 24 24"><path d="M13 3a9 9 0 00-9 9H1l4 4 4-4H6a7 7 0 117 7 7 7 0 01-5-2l-1.4 1.4A9 9 0 1013 3zm-1 5v5l4 2 .7-1.2-3.2-1.9V8z"/></svg>最近見た車</div>'
			+'<div class="cmx-hist__row">'+list.map(function(x){
				var img=x.img?('style="background-image:url('+esc(x.img)+')"'):'';
				var price=x.price?('<div class="cmx-hist__price">'+esc(x.price)+'</div>'):'';
				return '<a class="cmx-hist__card" href="'+esc(x.url)+'"><div class="cmx-hist__img" '+img+'></div><div class="cmx-hist__meta"><div class="cmx-hist__ttl">'+esc(x.title||'車両')+'</div>'+price+'</div></a>';
			}).join('')+'</div>';
		/* 設置：FAQの後 → なければ本文末尾 */
		var anchor=document.querySelector('.cmx-faq')||document.querySelector('.cmx-bd');
		if(anchor&&anchor.parentNode){ anchor.parentNode.insertBefore(sec, anchor.nextSibling); return; }
		var main=document.querySelector('.single-portfolio .wpb_row:last-of-type, .single-content, article');
		if(main){ main.appendChild(sec); } else { document.body.appendChild(sec); }
	}

	function run(){ record(); buildShare(); buildHistory(); }
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){ setTimeout(run,170); });
	else setTimeout(run,170);
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_histshare_render', 26 );
}
