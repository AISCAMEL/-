<?php
/**
 * カーメル：お気に入り（♡）＋ 一覧パネル
 * ---------------------------------------------------------------------------
 * ・車両詳細ページ…タイトル付近に「♡ お気に入りに追加／♥ お気に入り済み」トグル。
 * ・在庫一覧（/search など）…各車カードに小さな♡を付与（best-effort）。
 * ・全ページ右下に「♡ お気に入り（件数）」フローティング。押すと保存した車の
 *   一覧パネル（画像・車名・価格・ページリンク）がスライド表示。
 * 保存はブラウザ(localStorage)。ログイン不要・サーバー不要。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_favorite_render' ) ) {
	function carmelx_favorite_render() {
		if ( is_admin() ) { return; }
		?>
<style id="carmel-fav-css">
:root{--cfav-red:#e8384f;--cfav-grn:#2cac44;}
/* 詳細ページ ♡ トグル */
.cfav-toggle{display:inline-flex;align-items:center;gap:8px;cursor:pointer;border:1.5px solid #e0b3ba;background:#fff;color:#c23047;
	border-radius:999px;padding:9px 18px;font-size:14px;font-weight:800;line-height:1;margin:10px 0 4px;transition:.15s;-webkit-tap-highlight-color:transparent;}
.cfav-toggle:hover{background:#fff5f6;}
.cfav-toggle svg{width:17px;height:17px;}
.cfav-toggle.is-on{background:#fdecef;border-color:var(--cfav-red);color:var(--cfav-red);}
.cfav-toggle.is-on svg path{fill:var(--cfav-red);}
.cfav-toggle svg path{fill:none;stroke:currentColor;stroke-width:2;}
/* 一覧カードの小♡ */
.cfav-mini{position:absolute;top:8px;right:8px;z-index:6;width:34px;height:34px;border-radius:50%;border:none;
	background:rgba(255,255,255,.92);box-shadow:0 1px 5px rgba(0,0,0,.2);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;}
.cfav-mini svg{width:18px;height:18px;}
.cfav-mini svg path{fill:none;stroke:#c23047;stroke-width:2;}
.cfav-mini.is-on svg path{fill:var(--cfav-red);stroke:var(--cfav-red);}
/* フローティング */
.cfav-fab{position:fixed;left:16px;bottom:16px;z-index:99980;display:inline-flex;align-items:center;gap:7px;
	background:#fff;color:#c23047;border:1.5px solid #e0b3ba;border-radius:999px;padding:11px 16px;font-size:13.5px;font-weight:800;
	box-shadow:0 3px 14px rgba(0,0,0,.16);cursor:pointer;line-height:1;}
.cfav-fab svg{width:18px;height:18px;}
.cfav-fab svg path{fill:var(--cfav-red);stroke:var(--cfav-red);stroke-width:2;}
.cfav-fab__n{background:var(--cfav-red);color:#fff;border-radius:999px;min-width:19px;height:19px;padding:0 5px;font-size:12px;
	display:inline-flex;align-items:center;justify-content:center;}
.cfav-fab[hidden]{display:none;}
@media(max-width:782px){.cfav-fab{bottom:74px;}} /* 下部CTAバーの上へ */
/* パネル */
.cfav-ov{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:99990;opacity:0;visibility:hidden;transition:.2s;}
.cfav-ov.open{opacity:1;visibility:visible;}
.cfav-panel{position:fixed;top:0;right:0;height:100%;width:360px;max-width:88vw;background:#f6f7f9;z-index:99991;
	transform:translateX(100%);transition:transform .25s ease;display:flex;flex-direction:column;box-shadow:-4px 0 20px rgba(0,0,0,.18);}
.cfav-panel.open{transform:translateX(0);}
.cfav-hd{background:var(--cfav-grn);color:#fff;padding:15px 16px;display:flex;align-items:center;justify-content:space-between;}
.cfav-hd b{font-size:15.5px;font-weight:900;display:flex;align-items:center;gap:8px;}
.cfav-hd b svg{width:18px;height:18px;}.cfav-hd b svg path{fill:#fff;}
.cfav-x{background:none;border:none;color:#fff;font-size:22px;line-height:1;cursor:pointer;padding:2px 6px;}
.cfav-body{flex:1;overflow-y:auto;padding:12px;}
.cfav-empty{text-align:center;color:#8a929c;font-size:13px;padding:40px 16px;line-height:1.7;}
.cfav-item{display:flex;gap:11px;background:#fff;border:1px solid #e7eaef;border-radius:11px;padding:9px;margin-bottom:10px;position:relative;}
.cfav-item__img{width:92px;height:69px;flex:0 0 92px;border-radius:7px;background:#eef0f3 center/cover no-repeat;}
.cfav-item__meta{flex:1;min-width:0;display:flex;flex-direction:column;}
.cfav-item__ttl{font-size:13px;font-weight:800;color:#222;line-height:1.35;margin:1px 0 5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.cfav-item__price{font-size:14px;font-weight:900;color:var(--cfav-grn);margin-top:auto;}
.cfav-item__price small{font-size:10.5px;color:#888;font-weight:700;margin-left:3px;}
.cfav-item__go{display:inline-block;margin-top:6px;font-size:12px;font-weight:800;color:#fff;background:var(--cfav-grn);
	border-radius:7px;padding:6px 12px;text-decoration:none;text-align:center;align-self:flex-start;}
.cfav-item__rm{position:absolute;top:7px;right:7px;background:#f1f2f4;border:none;color:#9aa1aa;width:24px;height:24px;border-radius:50%;
	cursor:pointer;font-size:15px;line-height:1;padding:0;}
.cfav-item__rm:hover{background:#ffe3e6;color:var(--cfav-red);}
.cfav-ft{padding:11px 12px;border-top:1px solid #e3e6ea;background:#fff;}
.cfav-ft a{display:block;text-align:center;background:#ff7a00;color:#fff;font-weight:900;font-size:13.5px;text-decoration:none;border-radius:9px;padding:12px;}
.cfav-ft[hidden]{display:none;}
.cfav-toast{position:fixed;left:50%;bottom:90px;transform:translateX(-50%);background:#222;color:#fff;font-size:13px;font-weight:700;
	padding:10px 18px;border-radius:999px;z-index:99999;opacity:0;transition:.2s;pointer-events:none;white-space:nowrap;}
.cfav-toast.show{opacity:.95;}
</style>
<script id="carmel-fav-js">
(function(){
	'use strict';
	var KEY='carmel_favs';
	var HEART='<svg viewBox="0 0 24 24"><path d="M12 21s-7.5-4.6-10-9.1C.5 8.4 2 5 5.2 5c1.9 0 3.2 1 3.8 2 .6-1 1.9-2 3.8-2C16 5 17.5 8.4 16 11.9 13.5 16.4 12 21 12 21z" transform="scale(1)"/></svg>';
	function read(){ try{ return JSON.parse(localStorage.getItem(KEY)||'[]'); }catch(e){ return []; } }
	function write(a){ try{ localStorage.setItem(KEY, JSON.stringify(a)); }catch(e){} paint(); }
	function has(id,url){ return read().some(function(x){ return (id&&x.id===id)||(x.url===url); }); }
	function removeOne(id,url){ write(read().filter(function(x){ return !((id&&x.id===id)||x.url===url); })); }
	function add(c){ var a=read(); if(a.some(function(x){return (c.id&&x.id===c.id)||x.url===c.url;})) return; a.unshift(c); write(a.slice(0,60)); }

	function clean(s){ return (s||'').replace(/\s*[|｜].*$/,'').replace(/^信用回復ローン\s*/,'').trim(); }
	function currentCar(){
		var url=((document.querySelector('link[rel=canonical]')||{}).href)||location.href; url=url.split('#')[0].split('?')[0];
		var title=''; var og=document.querySelector('meta[property="og:title"]'); if(og)title=og.getAttribute('content')||'';
		if(!title){ var h=document.querySelector('.single-portfolio h1, article h1, h1'); if(h)title=h.textContent; }
		if(!title)title=document.title; title=clean(title);
		var img=''; var oi=document.querySelector('meta[property="og:image"]'); if(oi)img=oi.getAttribute('content')||'';
		if(!img){ var im=document.querySelector('.single-portfolio img, .wpex-slider img, article img'); if(im)img=im.currentSrc||im.src||''; }
		var id=''; var m=location.pathname.match(/\/search\/(\d+)/); if(m)id=m[1];
		if(!id){ var bc=(document.body.className.match(/postid-(\d+)/)||[]); id=bc[1]||''; }
		var price=''; var pe=document.querySelector('.carmel-lg__hd-price, .carmel-lg__hd-sub b, .carmel-lg__price-val'); if(pe)price=pe.textContent.replace(/\s+/g,'');
		return {id:id,title:title,url:url,img:img,price:price,ts:Date.now()};
	}
	function isDetail(){ return document.body.classList.contains('single-portfolio') || /\/search\/\d+/.test(location.pathname) || !!document.querySelector('.carmel-lg'); }

	function toast(msg){ var t=document.getElementById('cfav-toast'); if(!t){ t=document.createElement('div'); t.id='cfav-toast'; t.className='cfav-toast'; document.body.appendChild(t); } t.textContent=msg; t.classList.add('show'); clearTimeout(t._t); t._t=setTimeout(function(){ t.classList.remove('show'); },1600); }

	/* ---- 詳細ページ トグル ---- */
	function placeDetailToggle(){
		if(!isDetail()) return;
		if(document.getElementById('cfav-toggle')) return;
		var car=currentCar(); if(!car.url) return;
		var btn=document.createElement('button'); btn.type='button'; btn.id='cfav-toggle'; btn.className='cfav-toggle';
		function sync(){ var on=has(car.id,car.url); btn.classList.toggle('is-on',on); btn.innerHTML=HEART+'<span>'+(on?'お気に入り済み':'お気に入りに追加')+'</span>'; }
		btn.addEventListener('click',function(){ if(has(car.id,car.url)){ removeOne(car.id,car.url); toast('お気に入りから削除しました'); } else { add(currentCar()); toast('お気に入りに追加しました'); } sync(); });
		sync();
		/* 設置場所：ローン枠の直前 → なければ最初のh1の直後 */
		var lg=document.querySelector('.carmel-lg');
		if(lg&&lg.parentNode){ lg.parentNode.insertBefore(btn, lg); }
		else { var h=document.querySelector('.single-portfolio h1, article h1, h1'); if(h&&h.parentNode){ h.parentNode.insertBefore(btn, h.nextSibling); } else { document.body.appendChild(btn); } }
	}

	/* ---- 一覧カードの小♡（best-effort）---- */
	function placeMiniHearts(){
		var links=document.querySelectorAll('a[href*="/search/"]');
		var seen={};
		links.forEach(function(a){
			var m=(a.getAttribute('href')||'').match(/\/search\/(\d+)/); if(!m) return; var id=m[1];
			/* カード容器を推定：画像を含む近い祖先 */
			var card=a.closest('li,article,.wpex-grid-li,.vcex-portfolio-entry,.grid-item,.col'); if(!card) card=a.parentElement;
			if(!card||card.getAttribute('data-cfav')==='1') return;
			var img=card.querySelector('img'); if(!img) return; /* 画像の無いテキストリンクは除外 */
			if(seen[id]&&seen[id]===card) return; seen[id]=card;
			card.setAttribute('data-cfav','1');
			if(getComputedStyle(card).position==='static') card.style.position='relative';
			var url=a.href.split('#')[0];
			var mb=document.createElement('button'); mb.type='button'; mb.className='cfav-mini'; mb.innerHTML=HEART;
			function sync(){ mb.classList.toggle('is-on', has(id,url)); }
			mb.addEventListener('click',function(e){ e.preventDefault(); e.stopPropagation();
				if(has(id,url)){ removeOne(id,url); toast('削除しました'); }
				else{
					var t=clean((card.querySelector('.entry-title,.vcex-portfolio-entry-title,h2,h3,.title')||{}).textContent||img.alt||'');
					var pe=card.querySelector('[class*="price"],.money,.vcex-price'); var price=pe?pe.textContent.replace(/\s+/g,''):'';
					add({id:id,title:t,url:url,img:(img.currentSrc||img.src||''),price:price,ts:Date.now()}); toast('お気に入りに追加しました');
				}
				sync();
			});
			sync(); card.appendChild(mb);
		});
	}

	/* ---- フローティング＋パネル ---- */
	function buildFab(){
		if(document.getElementById('cfav-fab')) return;
		var fab=document.createElement('button'); fab.type='button'; fab.id='cfav-fab'; fab.className='cfav-fab';
		fab.innerHTML=HEART+'<span>お気に入り</span><span class="cfav-fab__n" id="cfav-n">0</span>';
		fab.addEventListener('click',openPanel); document.body.appendChild(fab);

		var ov=document.createElement('div'); ov.id='cfav-ov'; ov.className='cfav-ov';
		var panel=document.createElement('div'); panel.id='cfav-panel'; panel.className='cfav-panel';
		panel.innerHTML='<div class="cfav-hd"><b>'+HEART+'お気に入りの車</b><button class="cfav-x" id="cfav-x" aria-label="閉じる">×</button></div><div class="cfav-body" id="cfav-body"></div><div class="cfav-ft" id="cfav-ft"><a href="https://carmelonline.jp/search/">ほかの在庫を探す</a></div>';
		document.body.appendChild(ov); document.body.appendChild(panel);
		ov.addEventListener('click',closePanel); document.getElementById('cfav-x').addEventListener('click',closePanel);
	}
	function openPanel(){ renderList(); document.getElementById('cfav-ov').classList.add('open'); document.getElementById('cfav-panel').classList.add('open'); }
	function closePanel(){ document.getElementById('cfav-ov').classList.remove('open'); document.getElementById('cfav-panel').classList.remove('open'); }
	function esc(s){ return String(s||'').replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
	function renderList(){
		var body=document.getElementById('cfav-body'); if(!body) return; var a=read();
		if(!a.length){ body.innerHTML='<div class="cfav-empty">まだお気に入りはありません。<br>気になる車の ♡ を押すと、ここに保存されます。</div>'; return; }
		body.innerHTML=a.map(function(x){
			var img=x.img?('style="background-image:url('+esc(x.img)+')"'):'';
			var price=x.price?('<div class="cfav-item__price">'+esc(x.price)+'</div>'):'';
			return '<div class="cfav-item"><div class="cfav-item__img" '+img+'></div><div class="cfav-item__meta"><div class="cfav-item__ttl">'+esc(x.title||'車両')+'</div>'+price+'<a class="cfav-item__go" href="'+esc(x.url)+'">この車を見る</a></div><button class="cfav-item__rm" data-u="'+esc(x.url)+'" data-i="'+esc(x.id||'')+'" aria-label="削除">×</button></div>';
		}).join('');
		body.querySelectorAll('.cfav-item__rm').forEach(function(b){ b.addEventListener('click',function(){ removeOne(b.getAttribute('data-i'),b.getAttribute('data-u')); renderList(); }); });
	}
	function paint(){
		var n=read().length; var el=document.getElementById('cfav-n'); var fab=document.getElementById('cfav-fab');
		if(el) el.textContent=n; if(fab) fab.hidden = (n===0 && !isDetail() && !document.querySelector('.cfav-mini'));
		/* 詳細トグル同期 */
		var t=document.getElementById('cfav-toggle'); if(t){ var c=currentCar(); var on=has(c.id,c.url); t.classList.toggle('is-on',on); var sp=t.querySelector('span'); if(sp)sp.textContent=on?'お気に入り済み':'お気に入りに追加'; }
		if(document.getElementById('cfav-ov')&&document.getElementById('cfav-ov').classList.contains('open')) renderList();
	}

	function run(){ buildFab(); placeDetailToggle(); placeMiniHearts(); paint(); }
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){ setTimeout(run,120); });
	else setTimeout(run,120);
	setTimeout(placeMiniHearts,1200); /* 遅延読み込みの一覧に追従 */
	window.addEventListener('storage',paint); /* 別タブ同期 */
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_favorite_render', 24 );
}
