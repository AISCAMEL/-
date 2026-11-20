<?php
/**
 * カーメル：車両比較（最大3台を横並び）
 * ---------------------------------------------------------------------------
 * ・詳細ページ／一覧カードに「⇄ 比較」。選ぶと localStorage に最大3台まで保存。
 * ・1台以上で右下に「⇄ 比較する(件数)」バー → モーダルで横並び比較
 *   （画像・車名・お支払総額・月々目安・年式・走行・リンク）。
 * ・「この中からまとめて問い合わせ」も用意。保存はブラウザ(ログイン不要)。
 *   固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_compare_render' ) ) {
	function carmelx_compare_render() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-cmp-css">
:root{--ccmp:#009de0;}
.ccmp-toggle{display:inline-flex;align-items:center;gap:7px;cursor:pointer;border:1.5px solid #bfe3f3;background:#fff;color:#0782b9;
	border-radius:999px;padding:9px 16px;font-size:13px;font-weight:800;line-height:1;-webkit-tap-highlight-color:transparent;}
.ccmp-toggle svg{width:16px;height:16px;fill:#0782b9;}
.ccmp-toggle.is-on{background:#e8f6fd;border-color:var(--ccmp);color:var(--ccmp);}
.ccmp-mini{position:absolute;top:47px;right:8px;z-index:6;height:28px;padding:0 10px;border-radius:999px;border:none;
	background:rgba(255,255,255,.92);box-shadow:0 1px 5px rgba(0,0,0,.2);cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:800;color:#0782b9;}
.ccmp-mini svg{width:13px;height:13px;fill:#0782b9;}
.ccmp-mini.is-on{background:var(--ccmp);color:#fff;}.ccmp-mini.is-on svg{fill:#fff;}
/* バー */
.ccmp-bar{position:fixed;right:16px;bottom:16px;z-index:99982;display:none;align-items:center;gap:10px;background:#fff;
	border:1.5px solid #bfe3f3;border-radius:999px;padding:8px 9px 8px 16px;box-shadow:0 3px 16px rgba(0,0,0,.17);}
.ccmp-bar.show{display:inline-flex;}
.ccmp-bar__t{font-size:13px;font-weight:800;color:#0782b9;}
.ccmp-bar__go{background:var(--ccmp);color:#fff;border:none;border-radius:999px;padding:9px 16px;font-size:13px;font-weight:900;cursor:pointer;}
.ccmp-bar__go:disabled{background:#b9d9e8;cursor:default;}
@media(max-width:782px){.ccmp-bar{right:12px;bottom:74px;}}
/* モーダル */
.ccmp-ov{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99993;opacity:0;visibility:hidden;transition:.2s;}
.ccmp-ov.open{opacity:1;visibility:visible;}
.ccmp-modal{position:fixed;inset:0;z-index:99994;display:flex;align-items:center;justify-content:center;padding:16px;pointer-events:none;}
.ccmp-card{background:#fff;border-radius:14px;max-width:760px;width:100%;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;
	box-shadow:0 18px 50px rgba(0,0,0,.3);transform:translateY(16px);opacity:0;transition:.22s;pointer-events:auto;}
.ccmp-ov.open ~ .ccmp-modal .ccmp-card{transform:translateY(0);opacity:1;}
.ccmp-hd{background:var(--ccmp);color:#fff;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;}
.ccmp-hd b{font-size:15px;font-weight:900;}
.ccmp-x{background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:2px 6px;}
.ccmp-body{overflow:auto;padding:0;}
.ccmp-tbl{width:100%;border-collapse:collapse;min-width:420px;}
.ccmp-tbl th,.ccmp-tbl td{border:1px solid #eceff2;padding:9px 8px;font-size:12.5px;vertical-align:middle;text-align:center;}
.ccmp-tbl thead td{padding:10px 8px;position:relative;background:#fafbfc;}
.ccmp-tbl .ccmp-rl{background:#f4f8fb;color:#56616c;font-weight:800;text-align:left;white-space:nowrap;width:72px;}
.ccmp-img{width:100%;height:84px;background:#eef0f3 center/cover no-repeat;border-radius:7px;}
.ccmp-ttl{font-size:11.5px;font-weight:800;color:#233;line-height:1.35;margin-top:6px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.ccmp-rm{position:absolute;top:4px;right:4px;background:#fff;border:1px solid #e3e6ea;color:#9aa1aa;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:13px;line-height:1;padding:0;}
.ccmp-price{color:#1c7a3a;font-weight:900;font-size:14px;}
.ccmp-mo{color:#e8500a;font-weight:900;}
.ccmp-go{display:inline-block;margin-top:2px;font-size:11px;font-weight:800;color:var(--ccmp);text-decoration:underline;}
.ccmp-ft{border-top:1px solid #eceff2;padding:12px;background:#fff;}
.ccmp-ft a{display:block;text-align:center;background:#ff7a00;color:#fff;font-weight:900;font-size:13.5px;text-decoration:none;border-radius:9px;padding:12px;}
</style>
<script id="carmel-cmp-js">
(function(){
	'use strict';
	var KEY='carmel_compare', MAX=3;
	var ICON='<svg viewBox="0 0 24 24"><path d="M9 3L5 7h3v7h2V7h3L9 3zm6 14v-7h-2v7h-3l4 4 4-4h-3z"/></svg>';
	function read(){ try{ return JSON.parse(localStorage.getItem(KEY)||'[]'); }catch(e){ return []; } }
	function write(a){ try{ localStorage.setItem(KEY, JSON.stringify(a)); }catch(e){} paint(); }
	function has(id,url){ return read().some(function(x){ return (id&&x.id===id)||x.url===url; }); }
	function rm(id,url){ write(read().filter(function(x){ return !((id&&x.id===id)||x.url===url); })); }
	function clean(s){ return (s||'').replace(/\s*[|｜].*$/,'').replace(/^信用回復ローン\s*/,'').trim(); }
	function esc(s){ return String(s||'').replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
	function toast(m){ var t=document.getElementById('cmx-toast2')||document.getElementById('cfav-toast'); if(t){ t.textContent=m; t.classList.add('show'); clearTimeout(t._t); t._t=setTimeout(function(){t.classList.remove('show');},1600);} }
	function isDetail(){ return document.body.classList.contains('single-portfolio') || /\/search\/\d+/.test(location.pathname) || !!document.querySelector('.carmel-lg'); }

	function tableVal(label){
		var out=''; document.querySelectorAll('.c-table01 th, .c-table01 td').forEach(function(){});
		var ths=document.querySelectorAll('.c-table01 th');
		ths.forEach(function(th){ if(!out && th.textContent.trim().indexOf(label)!==-1){ var td=th.nextElementSibling; if(td)out=td.textContent.trim(); } });
		return out;
	}
	function detailSnapshot(){
		var url=((document.querySelector('link[rel=canonical]')||{}).href)||location.href; url=url.split('#')[0].split('?')[0];
		var title=''; var og=document.querySelector('meta[property="og:title"]'); if(og)title=og.getAttribute('content')||'';
		if(!title){ var h=document.querySelector('.single-portfolio h1, article h1, h1'); if(h)title=h.textContent; }
		title=clean(title)||clean(document.title);
		var img=''; var oi=document.querySelector('meta[property="og:image"]'); if(oi)img=oi.getAttribute('content')||'';
		if(!img){ var im=document.querySelector('.single-portfolio img, .wpex-slider img, article img'); if(im)img=im.currentSrc||im.src||''; }
		var id=''; var m=location.pathname.match(/\/search\/(\d+)/); if(m)id=m[1];
		if(!id){ var bc=(document.body.className.match(/postid-(\d+)/)||[]); id=bc[1]||''; }
		var total=''; var pe=document.querySelector('.carmel-lg__hd-price'); if(pe)total=pe.textContent.replace(/\s+/g,'');
		var mo=''; var mc=document.querySelector('.carmel-lg__chip-m, .carmel-lg__sim-amount'); if(mc)mo=mc.textContent.replace(/\s+/g,'');
		return {id:id,title:title,url:url,img:img,price:total,mo:mo,year:tableVal('年式'),km:tableVal('走行'),ts:Date.now()};
	}

	function add(c){ var a=read(); if(a.some(function(x){return (c.id&&x.id===c.id)||x.url===c.url;})) return true;
		if(a.length>=MAX){ toast('比較は最大'+MAX+'台までです'); return false; } a.push(c); write(a); return true; }

	/* 詳細トグル */
	function buildToggle(){
		if(!isDetail()||document.getElementById('ccmp-toggle')) return;
		var snap=detailSnapshot(); if(!snap.url) return;
		var b=document.createElement('button'); b.type='button'; b.id='ccmp-toggle'; b.className='ccmp-toggle';
		function sync(){ var on=has(snap.id,snap.url); b.classList.toggle('is-on',on); b.innerHTML=ICON+'<span>'+(on?'比較中':'比較に追加')+'</span>'; }
		b.addEventListener('click',function(){ if(has(snap.id,snap.url)){ rm(snap.id,snap.url); toast('比較から外しました'); } else { if(add(detailSnapshot())) toast('比較に追加しました'); } sync(); });
		sync();
		var shareBox=document.getElementById('cmx-share'); var favT=document.getElementById('cfav-toggle');
		var anchor=shareBox||favT;
		if(anchor&&anchor.parentNode){ anchor.parentNode.insertBefore(b, anchor.nextSibling); }
		else { var lg=document.querySelector('.carmel-lg'); if(lg&&lg.parentNode){ lg.parentNode.insertBefore(b, lg); } }
	}
	/* 一覧カード mini */
	function buildMinis(){
		document.querySelectorAll('a[href*="/search/"]').forEach(function(a){
			var m=(a.getAttribute('href')||'').match(/\/search\/(\d+)/); if(!m) return; var id=m[1];
			var card=a.closest('li,article,.wpex-grid-li,.vcex-portfolio-entry,.grid-item,.col'); if(!card) card=a.parentElement;
			if(!card||card.getAttribute('data-ccmp')==='1') return; var img=card.querySelector('img'); if(!img) return;
			card.setAttribute('data-ccmp','1'); if(getComputedStyle(card).position==='static') card.style.position='relative';
			var url=a.href.split('#')[0];
			var mb=document.createElement('button'); mb.type='button'; mb.className='ccmp-mini';
			function sync(){ var on=has(id,url); mb.classList.toggle('is-on',on); mb.innerHTML=ICON+'<span>比較</span>'; }
			mb.addEventListener('click',function(e){ e.preventDefault(); e.stopPropagation();
				if(has(id,url)){ rm(id,url); toast('比較から外しました'); }
				else{ var t=clean((card.querySelector('.entry-title,.vcex-portfolio-entry-title,h2,h3,.title')||{}).textContent||img.alt||'');
					var pe=card.querySelector('[class*="price"],.money'); var price=pe?pe.textContent.replace(/\s+/g,''):'';
					add({id:id,title:t,url:url,img:(img.currentSrc||img.src||''),price:price,mo:'',year:'',km:'',ts:Date.now()}); }
				sync();
			});
			sync(); card.appendChild(mb);
		});
	}
	/* バー＋モーダル */
	function buildBar(){
		if(document.getElementById('ccmp-bar')) return;
		var bar=document.createElement('div'); bar.id='ccmp-bar'; bar.className='ccmp-bar';
		bar.innerHTML='<span class="ccmp-bar__t">⇄ 比較 <b id="ccmp-n">0</b>台</span><button class="ccmp-bar__go" id="ccmp-go">比較する</button>';
		document.body.appendChild(bar);
		document.getElementById('ccmp-go').addEventListener('click',openModal);
		var ov=document.createElement('div'); ov.id='ccmp-ov'; ov.className='ccmp-ov';
		var modal=document.createElement('div'); modal.id='ccmp-modal'; modal.className='ccmp-modal';
		modal.innerHTML='<div class="ccmp-card"><div class="ccmp-hd"><b>車両を比較</b><button class="ccmp-x" id="ccmp-x">×</button></div><div class="ccmp-body" id="ccmp-body"></div><div class="ccmp-ft" id="ccmp-ft"></div></div>';
		document.body.appendChild(ov); document.body.appendChild(modal);
		ov.addEventListener('click',closeModal); document.getElementById('ccmp-x').addEventListener('click',closeModal);
	}
	function openModal(){ renderModal(); document.getElementById('ccmp-ov').classList.add('open'); }
	function closeModal(){ document.getElementById('ccmp-ov').classList.remove('open'); }
	function row(label, vals){ return '<tr><td class="ccmp-rl">'+label+'</td>'+vals.map(function(v){ return '<td>'+v+'</td>'; }).join('')+'</tr>'; }
	function renderModal(){
		var a=read(); var body=document.getElementById('ccmp-body'); var ft=document.getElementById('ccmp-ft'); if(!body) return;
		if(!a.length){ body.innerHTML='<div style="padding:30px;text-align:center;color:#8a929c;font-size:13px;">比較する車を選んでください。</div>'; ft.innerHTML=''; return; }
		var heads=a.map(function(x,i){ return '<td><button class="ccmp-rm" data-u="'+esc(x.url)+'" data-i="'+esc(x.id||'')+'">×</button><div class="ccmp-img" style="'+(x.img?'background-image:url('+esc(x.img)+')':'')+'"></div><div class="ccmp-ttl">'+esc(x.title||'車両')+'</div></td>'; });
		var dash=function(v){ return v?esc(v):'<span style="color:#c3c8ce">—</span>'; };
		var html='<table class="ccmp-tbl"><thead><tr><td class="ccmp-rl"></td>'+heads.join('')+'</tr></thead><tbody>';
		html+=row('お支払総額', a.map(function(x){ return x.price?'<span class="ccmp-price">'+esc(x.price)+'</span>':dash(''); }));
		html+=row('月々目安', a.map(function(x){ return x.mo?'<span class="ccmp-mo">'+esc(x.mo)+'</span>':dash(''); }));
		html+=row('年式', a.map(function(x){ return dash(x.year); }));
		html+=row('走行距離', a.map(function(x){ return dash(x.km); }));
		html+=row('詳細', a.map(function(x){ return '<a class="ccmp-go" href="'+esc(x.url)+'">この車を見る</a>'; }));
		html+='</tbody></table>';
		body.innerHTML=html;
		body.querySelectorAll('.ccmp-rm').forEach(function(b){ b.addEventListener('click',function(){ rm(b.getAttribute('data-i'),b.getAttribute('data-u')); renderModal(); }); });
		/* まとめて問い合わせ：先頭車を vehicle に、他はメッセージ用に付記 */
		var first=a[0]; var titles=a.map(function(x){return x.title;}).join(' ／ ');
		var q='vehicle-id='+encodeURIComponent(first.id||'')+'&vehicle-title='+encodeURIComponent(first.title||'')+'&vehicle-url='+encodeURIComponent(first.url||'')+'&inquiry-source='+encodeURIComponent('compare')+'&cta-label='+encodeURIComponent('比較から問い合わせ')+'&compare='+encodeURIComponent(titles);
		ft.innerHTML='<a href="https://carmelonline.jp/contact-form?'+esc(q)+'">この中からまとめて問い合わせる</a>';
	}
	function paint(){ var n=read().length; var el=document.getElementById('ccmp-n'); var bar=document.getElementById('ccmp-bar'); var go=document.getElementById('ccmp-go');
		if(el)el.textContent=n; if(bar)bar.classList.toggle('show', n>=1); if(go)go.disabled=(n<2);
		/* 同期 */
		document.querySelectorAll('.ccmp-mini').forEach(function(){});
		if(document.getElementById('ccmp-ov')&&document.getElementById('ccmp-ov').classList.contains('open')) renderModal();
	}
	function run(){ buildBar(); buildToggle(); buildMinis(); paint(); }
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){ setTimeout(run,180); });
	else setTimeout(run,180);
	setTimeout(buildMinis,1300);
	window.addEventListener('storage',paint);
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_compare_render', 27 );
}
