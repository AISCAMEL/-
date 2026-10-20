
/**
 * APPREX AIスタジオ（会員システム）。
 * 設定は wp_localize_script の APPREX_STUDIO（inc/studio.php）。
 * ログイン前は無料会員登録フォーム、ログイン後はスタジオ本体を動かす。
 */
(function(){
'use strict';
var CFG=window.APPREX_STUDIO||{};

function api(path,body){
  return fetch(CFG.root+path,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':CFG.nonce},body:JSON.stringify(body||{})})
    .then(function(r){return r.json().then(function(b){ if(!r.ok||!b||b.ok===false){ throw new Error((b&&b.message)||'通信に失敗しました。時間をおいて再度お試しください。'); } return b; });});
}
function lead(detail){ try{ document.dispatchEvent(new CustomEvent('apprex:lead',{detail:detail})); }catch(e){} }

/* ---------- ログイン前：無料会員登録 ---------- */
if(!CFG.loggedIn){
  var rf=document.getElementById('studioReg');
  if(rf){
    rf.addEventListener('submit',function(e){
      e.preventDefault();
      var err=rf.querySelector('.reg-err'), btn=rf.querySelector('button[type=submit]');
      if(!rf.checkValidity()){ rf.reportValidity(); return; }
      var fd=new FormData(rf), label=btn.textContent;
      btn.disabled=true; btn.textContent='登録しています…'; err.hidden=true;
      api('studio/register',{name:fd.get('name'),company:fd.get('company'),email:fd.get('email'),password:fd.get('password'),website:fd.get('website')})
        .then(function(b){ if(b.lead){ lead(b.lead); } location.href=b.redirect||CFG.studioUrl; })
        .catch(function(x){ err.textContent=x.message; err.hidden=false; btn.disabled=false; btn.textContent=label; });
    });
  }
  return;
}

var KEY='apprex-studio-v2-'+(CFG.uid||0);
var DATA=window.APPREX_STUDIO_DATA||{}, IND=DATA.IND, COLORS=DATA.COLORS, TONES=DATA.TONES, SCREENS=DATA.SCREENS, FEAT=DATA.FEAT, PAGES=DATA.PAGES, PAGE_LIST=DATA.PAGE_LIST, GROUPS=DATA.GROUPS, pageName=DATA.pageName;
var STEPS=['会員登録','AIデザイン','AI仕様書','開発を相談','オンラインミーティング','お見積り・入金','制作','公開'];
var VIEWS=[['design','AIデザイン',1],['appmenu','アプリのメニュー',1],['demo','デモアプリで試す',1],['spec','AI仕様書',2],['docs','企画書・仕様書',2],['consult','開発を相談',3],['meeting','ミーティング予約',4],['quote','お見積り・入金',5],['prod','制作の進捗',6]];
var TIERS={trial:['トライアル',19800],start:['スタート',39800],biz:['ビジネス',59800]};
var YEN=function(n){return n.toLocaleString('ja-JP')+'円';};
var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};

function def(){
  var f={}; IND.food.feat.forEach(function(k){f[k]=true;});
  return {prog:1,view:'design',d:{ind:'food',name:'',goal:'常連のお客様の来店回数を増やしたい',target:'30〜50代の会社員・ご家族',tone:'luxury',color:1,custom:null,variant:'A',screen:'home',all:false,cols:3,fs:1,tall:false,resBig:false},
    vers:[{n:1,note:'AIが最初のデザインを作りました'}],feat:f,plan:'full',notes:'',meeting:null,met:false,payMethod:'card',paid:false,prod:0,msgs:[],
    reg:true,app:DATA.defaultApp('food')};
}
var S=def(), IMG={logo:null,photo:null};
(function(){
  var local=null;
  try{var raw=localStorage.getItem(KEY); if(raw){local=JSON.parse(raw);}}catch(e){}
  var srv=CFG.state&&CFG.state.d?CFG.state:null;
  // 新しい方を採用（別の端末で作業した場合はサーバー側が新しい）。
  if(srv&&(!local||!local.d||(srv._t||0)>=(local._t||0))){S=srv;} else if(local&&local.d){S=local;}
  var u=CFG.user||{};
  if(!S.d.name&&u.company){S.d.name=u.company;}
  try{var im=localStorage.getItem(KEY+'-img'); if(im){IMG=JSON.parse(im)||IMG;}}catch(e){}
  // アカウントに保存したロゴ・写真を優先（どの端末でも同じ）。
  var as=CFG.assets||{}; if(as.logo){IMG.logo=as.logo;} if(as.photo){IMG.photo=as.photo;}
})();
/* 管理画面で更新された進み具合を反映（2:ミーティング予約済 3:見積り送付 4:入金・制作中 5:公開） */
var STAGE=CFG.stage||0;
if(CFG.meeting){S.meeting=S.meeting||fmtMeeting(CFG.meeting);}
if(STAGE>=1){S.prog=Math.max(S.prog,4);}
S.met=STAGE>=3; S.paid=STAGE>=4;
S.prod=STAGE>=5?6:(STAGE>=4?Math.min(CFG.prod||0,6):0);
if(S.met){S.prog=Math.max(S.prog,5);} if(S.paid){S.prog=Math.max(S.prog,6);} if(S.prod>=6){S.prog=8;}
S.msgs=(CFG.msgs||[]).map(function(m){return [m[0],m[1],m[2]];});
if(S.view==='register'){S.view='design';}
if(!S.app||!S.app.menu){S.app=DATA.defaultApp(S.d.ind);} if(!S.app.names){S.app.names={};}
// 以前の形式（位置で保存したボタン名）は、ページとずれるため使わない。
if(S.d.ov&&S.d.ov.menus){ delete S.d.ov.menus; }
// フッターは縦メニューにあるページ（またはホーム）だけ。
S.app.footer=S.app.footer.map(function(k){ return k==='home'||S.app.menu.indexOf(k)>-1?k:'home'; });

function fmtMeeting(v){ var m=String(v).match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}:\d{2})/); if(!m) return v; var d=new Date(+m[1],+m[2]-1,+m[3]); return (+m[2])+'月'+(+m[3])+'日('+'日月火水木金土'.charAt(d.getDay())+') '+m[4]; }
var saveTimer=null;
function save(){
  S._t=Date.now();
  try{localStorage.setItem(KEY,JSON.stringify(S));}catch(e){}
  clearTimeout(saveTimer);
  saveTimer=setTimeout(function(){
    var copy=JSON.parse(JSON.stringify(S)); delete copy.msgs;
    api('studio/state',{state:JSON.stringify(copy)}).then(function(){ var el=document.getElementById('savedAt'); if(el){ var n=new Date(); el.textContent='保存しました '+n.getHours()+':'+('0'+n.getMinutes()).slice(-2); } }).catch(function(){});
  },1200);
}
function saveImg(){ try{localStorage.setItem(KEY+'-img',JSON.stringify(IMG));}catch(e){} }

function D(){var b=IND[S.d.ind], o={}; for(var k in b){o[k]=b[k];} if(S.d.ov&&S.d.ov.catch) o.catch=S.d.ov.catch;
  // ホームのボタン＝縦メニューの上から6つ（アプリの基盤のTOPアイコンと同じ並び）。
  if(S.app&&S.app.menu&&S.app.menu.length){ o.menus=S.app.menu.slice(0,6).map(function(k){return pageName(S.app,k);}); }
  return o;}
function syncFeat(){ (S.app.menu||[]).forEach(function(k){ var p=PAGES[k]; if(p&&p.feat){ S.feat[p.feat]=true; } }); }

/* ---------- AIデザイナー（サーバーのAIと連携） ---------- */
var AI=CFG.ai||{text:false,image:false,left:0,limit:0}, IMGS=CFG.images||[], GEN={busy:false,kind:''};
var KINDS=[['screens','画面デザイン（5画面）'],['icon','アプリアイコン'],['hero','メインビジュアル']];
function brief(req){
  var d=D(), p=palette();
  return {industry:d.l,name:storeName(),goal:S.d.goal,target:S.d.target,tone:S.d.tone,color:p[1],dark:p[0],catch:d.catch,menus:d.menus,pages:S.app.menu.slice(0,6).map(function(k){return PAGES[k].l;}),variant:S.d.variant,request:req||S.d.brief||''};
}
function applyAi(c){
  var ch=[];
  if(c.color){S.d.custom=c.color;ch.push('色');}
  if(c.tone&&TONES[c.tone]){S.d.tone=c.tone;ch.push('雰囲気');}
  if(c.variant){S.d.variant=c.variant;ch.push('レイアウト');}
  if(c.cols){S.d.cols=c.cols;}
  if(c.fs){S.d.fs=c.fs;}
  if(typeof c.tall==='boolean'){S.d.tall=c.tall;}
  if(typeof c.resBig==='boolean'){S.d.resBig=c.resBig;}
  if(c.catch||c.menus){S.d.ov=S.d.ov||{}; if(c.catch){S.d.ov.catch=c.catch;ch.push('キャッチコピー');} if(c.menus){ c.menus.forEach(function(l,i){ var k=S.app.menu[i]; if(k){S.app.names[k]=l;} }); ch.push('ボタン');}}
  return ch;
}
function pickedImg(kind){ for(var i=0;i<IMGS.length;i++){ if(IMGS[i].kind===kind&&IMGS[i].picked) return IMGS[i]; } return null; }
function mainImg(){ if(S._show){ for(var i=0;i<IMGS.length;i++){ if(IMGS[i].id===S._show) return IMGS[i]; } } return pickedImg('screens')||IMGS[0]||null; }
function aiPanel(){
  var m=mainImg();
  var gal;
  if(GEN.busy){
    gal='<div class="aid-main loading" role="status"><div class="aid-spin"></div><b>AIがデザインしています…</b><span>'+esc((KINDS.filter(function(k){return k[0]===GEN.kind;})[0]||[])[1]||'')+'を作成中。30〜60秒ほどかかります。</span></div>';
  }else if(m){
    gal='<figure class="aid-main"><button type="button" class="aid-zoom" data-zoom="'+esc(m.url)+'" aria-label="拡大して見る"><img src="'+esc(m.url)+'" alt="AIが生成した'+esc(m.label)+'"></button><figcaption><span class="aid-tag">'+esc(m.label)+(m.picked?'・採用中':'')+'</span><small>'+esc(m.time)+(m.note?'　'+esc(m.note):'')+'</small><button type="button" class="btn btn--sm '+(m.picked?'btn--line':'btn--pri')+'" data-pick="'+m.id+'" data-kind="'+m.kind+'">'+(m.picked?'採用を外す':'この案を採用')+'</button></figcaption></figure>';
  }else{
    gal='<div class="aid-main empty"><b>ここにAIのデザインが表示されます</b><span>左で要望を書いて「画面デザインを生成」を押すと、5画面のデザインカンプを本格的な画像で作ります。アイコンやメインビジュアルも作れます。</span></div>';
  }
  var th=IMGS.length>1?'<div class="aid-thumbs" aria-label="生成したデザイン">'+IMGS.map(function(im){return '<button type="button" class="aid-th'+(m&&im.id===m.id?' on':'')+'" data-show="'+im.id+'" title="'+esc(im.label+' '+im.time)+'"><img src="'+esc(im.url)+'" alt="">'+(im.picked?'<i>採用</i>':'')+'</button>';}).join('')+'</div>':'';
  var gen=KINDS.map(function(k){return '<button type="button" class="btn '+(k[0]==='screens'?'btn--cta':'btn--line')+' btn--sm" data-gen="'+k[0]+'"'+(GEN.busy||!AI.image||AI.left<=0?' disabled':'')+'>'+k[1]+'を生成</button>';}).join('');
  var sug=['高級感のある和モダンにして、写真を大きく','20代女性向けにかわいく、予約を目立たせて','シニアにも読みやすく、ボタンを大きく','黒とゴールドで会員制の雰囲気に','ナチュラルで温かい雰囲気、緑を基調に'];
  return '<div class="card aid">'
   +'<div class="aid-l"><div class="aid-h"><span class="aid-badge">AI DESIGNER</span><h3>AIデザイナーに頼む</h3><p>どんなアプリにしたいか、自由に書いてください。色・雰囲気・キャッチコピー・ボタンをAIが作り直し、本格的なデザイン画像も生成します。</p></div>'
   +'<label class="sr" for="aidText">デザインの要望</label><textarea id="aidText" rows="4" placeholder="例：落ち着いた高級焼肉店。黒と金で、ネット予約を一番目立たせたい。常連さん向けにポイントカードも。">'+esc(S.d.brief||'')+'</textarea>'
   +'<div class="ai-sug">'+sug.map(function(x){return '<button type="button" data-sug="'+esc(x)+'">'+esc(x)+'</button>';}).join('')+'</div>'
   +'<div class="aid-acts"><button type="button" class="btn btn--pri" data-go="aiDesign"'+(GEN.busy?' disabled':'')+'>AIで作り直す</button><label class="aid-chk"><input type="checkbox" id="aidAlso"'+(AI.image&&AI.left>0?' checked':' disabled')+'> 続けて画面デザイン画像も生成する</label></div>'
   +'<div class="aid-gen"><span class="sec-t" style="margin:0">本格デザイン画像</span><div class="aid-genb">'+gen+'</div><small>'+(AI.image?'本日あと'+AI.left+'回（1日'+AI.limit+'回まで・無料）':'AI画像の生成は準備中です')+'</small></div>'
   +'<div class="ai-msg" id="aidMsg" role="status">'+esc(S._aiMsg||'')+'</div></div>'
   +'<div class="aid-r">'+gal+th+'</div></div>';
}
function zoom(url){
  var o=document.createElement('div'); o.className='rn-studio aid-lb'; o.setAttribute('role','dialog'); o.setAttribute('aria-label','デザインを拡大');
  o.innerHTML='<button type="button" aria-label="閉じる">×</button><img src="'+esc(url)+'" alt="">';
  var close=function(){ document.removeEventListener('keydown',key); o.remove(); };
  var key=function(e){ if(e.key==='Escape') close(); };
  o.addEventListener('click',close); document.addEventListener('keydown',key); document.body.appendChild(o);
}
function runAiDesign(text,btn){
  text=(text||'').trim(); if(!text){ var ta=$('aidText'); if(ta) ta.focus(); return; }
  S.d.brief=text; var also=$('aidAlso'), doImg=also&&also.checked;
  if(btn){btn.disabled=true;btn.textContent='AIが考えています…';}
  api('studio/ai-design',{brief:brief(text)}).then(function(b){
    if(b.fallback){
      var r=revise(text);
      S._aiMsg=r?'AI：「'+r+'」を反映しました（版'+S.vers.length+'）':'AI：自動で直せない内容は担当者への申し送りに記録しました。';
      if(!r){ S.notes=(S.notes?S.notes+'\n':'')+'デザインの要望：'+text; }
    }else{
      var ch=applyAi(b.changes||{});
      S.vers.push({n:S.vers.length+1,note:'AIデザイナー：'+(b.reply||ch.join('・')||text)});
      if(b.note){ S.notes=(S.notes?S.notes+'\n':'')+'デザインの要望：'+b.note; }
      S._aiMsg='AI：'+(b.reply||'デザインを作り直しました。')+'（版'+S.vers.length+'）';
    }
    save(); render();
    if(doImg&&AI.image&&AI.left>0){ genImage('screens'); }
  }).catch(function(x){ S._aiMsg=x.message; render(); });
}
function genImage(kind){
  if(GEN.busy) return;
  if(!AI.image){ S._aiMsg='AI画像の生成は準備中です。'; render(); return; }
  GEN={busy:true,kind:kind}; render();
  api('studio/ai-image',{kind:kind,brief:brief()}).then(function(b){
    IMGS=b.images||IMGS; AI.left=typeof b.left==='number'?b.left:AI.left; GEN={busy:false,kind:''};
    S._show=null; S.vers.push({n:S.vers.length+1,note:'AIが'+((KINDS.filter(function(k){return k[0]===kind;})[0]||[])[1]||'デザイン')+'の画像を生成'});
    S._aiMsg='AI：デザイン画像ができました。気に入った案は「この案を採用」を押してください。'; save(); render();
  }).catch(function(x){ GEN={busy:false,kind:''}; S._aiMsg=x.message; render(); });
}
function storeName(){return (S.d.name||'').trim()||D().name;}
function shade(hex,k){var n=parseInt(hex.slice(1),16),r=n>>16,g=n>>8&255,b=n&255;r=Math.round(r*k);g=Math.round(g*k);b=Math.round(b*k);return '#'+((1<<24)+(r<<16)+(g<<8)+b).toString(16).slice(1);}
function palette(){ if(S.d.custom){return [shade(S.d.custom,.45),S.d.custom,'カスタム（'+S.d.custom+'）'];} var c=COLORS[S.d.color]; return [c[1],c[2],c[0]]; }

/* ---------- phone renderer ---------- */
function phone(screen,small){
  var d=D(), p=palette(), t=TONES[S.d.tone], name=esc(storeName());
  var logo=IMG.logo?'<img src="'+IMG.logo+'" alt="">':esc(storeName().charAt(0));
  var top='<div class="s-top"><span class="s-logo">'+logo+'</span><b>'+name+'</b></div>';
  var heroStyle=IMG.photo?' style="background-image:url('+IMG.photo+')"':'';
  var hero='<div class="s-hero'+(S.d.tall?' tall':'')+(IMG.photo?' photo':'')+'"'+heroStyle+'><small>WELCOME</small><b>'+esc(d.catch)+'</b></div>';
  var tabs=function(on){var a=(S.app&&S.app.footer&&S.app.footer.length)?S.app.footer.slice(0,4).map(function(k){return pageName(S.app,k);}):['ホーム','クーポン','予約','会員証']; on=on===0?0:-1;return '<div class="s-tab">'+a.map(function(x,i){return '<span'+(i===on?' class="on"':'')+'>'+x+'</span>';}).join('')+'</div>';};
  var body='';
  if(screen==='home'){
    var res=S.d.resBig?'<div class="s-res">今すぐ予約する</div>':'';
    if(S.d.variant==='B'){
      body=hero+res+'<div class="s-list">'+d.menus.map(function(m){return '<div class="s-li"><i>'+esc(m.charAt(0))+'</i>'+esc(m)+'<span>›</span></div>';}).join('')+'</div>';
    }else if(S.d.variant==='C'){
      body='<div class="s-hero tall'+(IMG.photo?' photo':'')+'"'+heroStyle+'><small>WELCOME</small><b>'+esc(d.catch)+'</b></div>'+res+'<div class="s-grid" style="grid-template-columns:repeat(2,1fr)">'+d.menus.slice(0,4).map(function(m){return '<div class="s-btn"><i>'+esc(m.charAt(0))+'</i>'+esc(m)+'</div>';}).join('')+'</div>';
    }else{
      body=hero+res+'<div class="s-grid" style="grid-template-columns:repeat('+S.d.cols+',1fr)">'+d.menus.map(function(m){return '<div class="s-btn"><i>'+esc(m.charAt(0))+'</i>'+esc(m)+'</div>';}).join('')+'</div><div class="s-cpn">'+esc(d.cps[0][0])+'</div>';
    }
    body+=tabs(0);
  }else if(screen==='menu'){
    body='<div class="s-h">メニュー</div>'+d.items.map(function(it){return '<div class="s-item"><i></i><div><b>'+esc(it[0])+'</b><span>'+esc(it[1])+'</span></div></div>';}).join('')+tabs(-1);
  }else if(screen==='coupon'){
    body='<div class="s-h">クーポン</div>'+d.cps.map(function(c){return '<div class="s-coupon"><div><b>'+esc(c[0])+'</b><small>'+esc(c[1])+'</small></div><em>使う</em></div>';}).join('')+'<div class="s-cpn">新しいクーポンはプッシュ通知でお知らせ</div>'+tabs(1);
  }else if(screen==='reserve'){
    var dd=['月<br>20','火<br>21','水<br>22','木<br>23','金<br>24'];
    body='<div class="s-h">ご予約</div><div class="s-dates">'+dd.map(function(x,i){return '<span'+(i===1?' class="on"':'')+'>'+x+'</span>';}).join('')+'</div><div class="s-times">'+['11:00','12:00','13:00','17:00','18:00','19:00'].map(function(x,i){return '<span class="'+(i===1?'x':(i===4?'on':''))+'">'+x+'</span>';}).join('')+'</div><div class="s-res">18:00で予約する</div>'+tabs(2);
  }else{
    body='<div class="s-card"><span>'+name+' 会員証</span><div class="s-pt"><span>保有ポイント</span><strong>1,250</strong></div><div class="s-bar"></div><small>No. 0001-2345-6789</small></div><div class="s-cpn">あと250ptで特典と交換できます</div>'+tabs(3);
  }
  return '<div class="ph'+(small?' sm':'')+'" style="--c1:'+p[0]+';--c2:'+p[1]+';--r:'+t.r+'px;--ff:'+t.ff+';--fs:'+S.d.fs+'"><span class="notch"></span><div class="scr">'+top+body+'</div></div>';
}

/* ---------- AI修正（言葉の指示を解釈してデザインに反映） ---------- */
function revise(text){
  var ch=[], s=text;
  if(/予約.*(大き|目立|強調)/.test(s)){S.d.resBig=true;ch.push('予約ボタンを大きく目立つ位置に追加');}
  if(/(文字|字).*(大き)|シニア|読みやす/.test(s)){S.d.fs=1.15;ch.push('文字を大きく');}
  if(/2列/.test(s)){S.d.variant='A';S.d.cols=2;ch.push('メニューを2列に');}
  if(/3列/.test(s)){S.d.variant='A';S.d.cols=3;ch.push('メニューを3列に');}
  if(/(写真|ビジュアル|画像).*(大き)/.test(s)){S.d.tall=true;ch.push('メインビジュアルを大きく');}
  if(/リスト|一覧/.test(s)){S.d.variant='B';ch.push('メニューをリスト型に');}
  var cmap=[[/緑|グリーン/,2],[/赤|ボルドー/,1],[/ピンク/,3],[/黒|ゴールド|金/,4],[/青|ネイビー/,0],[/オレンジ/,5]];
  cmap.forEach(function(m){ if(m[0].test(s)){S.d.color=m[1];S.d.custom=null;ch.push('色を'+COLORS[m[1]][0]+'に');} });
  var tmap=[[/高級/,'luxury'],[/かわい|ポップ/,'pop'],[/ナチュラル|やさし/,'natural'],[/スポーティ|元気/,'sporty'],[/信頼|誠実|清潔/,'trust']];
  tmap.forEach(function(m){ if(m[0].test(s)){S.d.tone=m[1];ch.push('雰囲気を「'+TONES[m[1]].n+'」に');} });
  if(/丸/.test(s)){S.d.tone='pop';ch.push('角を丸く');}
  if(!ch.length) return null;
  S.vers.push({n:S.vers.length+1,note:ch.join('、')}); save(); return ch.join('、');
}

/* ---------- estimate ---------- */
function estimate(){
  var tier='trial', opt=0, orig=[], std=0;
  FEAT.forEach(function(f){ if(!S.feat[f.k]) return;
    if(f.t==='orig'){orig.push(f);} else if(f.t==='opt'){opt+=f.p;} else {std++; if(f.tier==='biz') tier='biz'; else if(f.tier==='start'&&tier!=='biz') tier='start';}
  });
  var match=!!S.feat.match;
  return {tier:tier,monthly:TIERS[tier][1],tierName:TIERS[tier][0],opt:opt,orig:orig,match:match,std:std,rec:orig.length?'orig':S.plan==='self'?'self':'full'};
}

/* ---------- views ---------- */
function vDesign(){
  var d=D(), p=palette();
  var col=COLORS.map(function(c,i){return '<button type="button" class="sw" data-color="'+i+'" style="background:linear-gradient(135deg,'+c[1]+','+c[2]+')" aria-label="'+c[0]+'" aria-pressed="'+(!S.d.custom&&S.d.color===i)+'"></button>';}).join('');
  var tones=Object.keys(TONES).map(function(k){return '<button type="button" class="chip" data-tone="'+k+'" aria-pressed="'+(S.d.tone===k)+'">'+TONES[k].n+'</button>';}).join('');
  var inds=Object.keys(IND).map(function(k){return '<option value="'+k+'"'+(S.d.ind===k?' selected':'')+'>'+IND[k].l+'</option>';}).join('');
  var goals=['常連のお客様の来店回数を増やしたい','予約を増やしたい','電話対応を減らしたい','新しいお客様を増やしたい','会員を増やして囲い込みたい'].map(function(g){return '<option'+(S.d.goal===g?' selected':'')+'>'+g+'</option>';}).join('');
  var stabs=SCREENS.map(function(s){return '<button type="button" class="stab" role="tab" data-screen="'+s[0]+'" aria-selected="'+(!S.d.all&&S.d.screen===s[0])+'">'+s[1]+'</button>';}).join('')+'<button type="button" class="stab" data-screen="all" aria-selected="'+S.d.all+'">全画面を並べる</button>';
  var phones=S.d.all?SCREENS.map(function(s){return phone(s[0],true);}).join(''):phone(S.d.screen,false);
  var vers=S.vers.slice().reverse().map(function(v){return '<div class="ver"><b>版'+v.n+'</b><span>'+esc(v.note)+'</span></div>';}).join('');
  return '<div class="vh"><div><h2>AIデザイン</h2><p>AIデザイナーに要望を書くと、画面の設定とデザイン画像を作ります。下で細かい調整もできます（すぐにプレビューへ反映）。</p></div><div class="acts" style="margin:0"><button type="button" class="btn btn--line" data-view="demo">デモアプリで触ってみる</button><button type="button" class="btn btn--cta" data-go="confirmDesign">このデザインで決定して仕様書へ</button></div></div>'
  +aiPanel()
  +'<div class="grid2">'
  +'<div class="card">'
    +'<div class="sec-t"><i>1</i>お店の情報</div>'
    +'<div class="row2"><div class="field"><label for="f-ind">業種</label><select id="f-ind">'+inds+'</select></div><div class="field"><label for="f-name">店名・サービス名</label><input type="text" id="f-name" maxlength="24" value="'+esc(S.d.name)+'" placeholder="例：'+esc(d.name)+'"></div></div>'
    +'<div class="row2"><div class="field"><label for="f-goal">目的</label><select id="f-goal">'+goals+'</select></div><div class="field"><label for="f-target">お客様の層</label><input type="text" id="f-target" value="'+esc(S.d.target)+'"></div></div>'
    +'<div class="sec-t"><i>2</i>雰囲気と色</div>'
    +'<div class="field"><div class="chips">'+tones+'</div></div>'
    +'<div class="field"><div class="chips" style="align-items:center">'+col+'<span class="colorpick"><input type="color" id="f-custom" value="'+(S.d.custom||p[1])+'" aria-label="好きな色を選ぶ">好きな色</span></div><span style="font-weight:500">選択中：'+esc(p[2])+'</span></div>'
    +'<div class="sec-t"><i>3</i>ロゴと写真（任意）</div>'
    +'<div class="up field"><label class="upbox">'+(IMG.logo?'<img src="'+IMG.logo+'" alt="ロゴ">':'<b>ロゴをのせる</b>')+'<span>PNG・JPG</span><input type="file" accept="image/*" id="f-logo"></label><label class="upbox">'+(IMG.photo?'<img src="'+IMG.photo+'" alt="写真">':'<b>メイン写真をのせる</b>')+'<span>お店や商品の写真</span><input type="file" accept="image/*" id="f-photo"></label></div>'
    +'<div class="sec-t"><i>4</i>修正の履歴</div>'
    +'<div class="vers" aria-label="修正の履歴">'+vers+'</div>'
  +'</div>'
  +'<div class="card">'
    +'<div class="prev-top"><div class="stabs" role="tablist" aria-label="画面">'+stabs+'</div></div>'
    +'<div class="variants"><span>AIの別案：</span>'+[['A','グリッド'],['B','リスト'],['C','大きな写真']].map(function(v){return '<button type="button" class="vbtn" data-variant="'+v[0]+'" aria-pressed="'+(S.d.variant===v[0])+'">案'+v[0]+' '+v[1]+'</button>';}).join('')+'</div>'
    +'<div class="stage'+(S.d.all?' all':'')+'">'+phones+'</div>'
    +'<p style="font-size:.78rem;color:var(--muted);margin:0">この画面はデザインの方向性を決めるためのものです。制作では、これをもとにデザイナーが仕上げます。ロゴ・写真の権利は公開前にご確認ください。</p>'
  +'</div></div>';
}

function specText(){
  var e=estimate(), d=D();
  var on=FEAT.filter(function(f){return S.feat[f.k];}).map(function(f){return '・'+f.n+(f.t==='orig'?'（オリジナル開発）':f.t==='opt'?'（オプション）':'');}).join('\n');
  return '【アプリ仕様書（AI作成）】\nアプリ名：'+storeName()+'\n業種：'+d.l+'\n目的：'+S.d.goal+'\nお客様の層：'+S.d.target+'\nデザイン：'+TONES[S.d.tone].n+' / '+palette()[2]+' / 案'+S.d.variant+'\nフッター横メニュー：'+S.app.footer.map(function(k){return pageName(S.app,k);}).join('／')+'\n縦メニュー（表示順）：'+S.app.menu.map(function(k){return pageName(S.app,k)+(S.app.names[k]?'（'+PAGES[k].l+'）':'');}).join('、')+'\n機能：\n'+on+'\n想定プラン：'+(e.orig.length?'オリジナル開発（個別見積り）':e.tierName+'プラン 月額'+YEN(e.monthly));
}

/* ---------- アプリのメニュー（基盤のメニュー設定と同じ構成） ---------- */
function vAppMenu(){
  var A=S.app, opts=function(sel){ return ['home'].concat(A.menu).map(function(k){return '<option value="'+k+'"'+(sel===k?' selected':'')+'>'+esc(pageName(A,k))+'</option>';}).join(''); };
  var foot=A.footer.map(function(k,i){return '<label class="fslot"><span>'+(i+1)+'</span><select data-foot="'+i+'" aria-label="フッター'+(i+1)+'番目">'+opts(k)+'</select></label>';}).join('');
  var rows=A.menu.map(function(k,i){ var p=PAGES[k]; return '<li class="mrow'+(i<6?' top':'')+'"><span class="mno">'+(i+1)+'</span><input type="text" value="'+esc(pageName(A,k))+'" data-name="'+k+'" aria-label="表示名" maxlength="10"><small>'+esc(p.l)+'・'+esc(GROUPS[p.g])+'</small><span class="mops"><button type="button" data-mv="'+i+'" data-dir="-1" aria-label="上へ"'+(i?'':' disabled')+'>↑</button><button type="button" data-mv="'+i+'" data-dir="1" aria-label="下へ"'+(i<A.menu.length-1?'':' disabled')+'>↓</button><button type="button" data-rm="'+k+'" aria-label="外す">✕</button></span></li>'; }).join('');
  var add=Object.keys(GROUPS).map(function(g){ var items=PAGE_LIST.filter(function(p){return p[2]===g&&A.menu.indexOf(p[0])<0;}); if(!items.length) return ''; return '<div class="addg"><b>'+GROUPS[g]+'</b><div class="chips">'+items.map(function(p){return '<button type="button" class="chip" data-add="'+p[0]+'">＋ '+esc(p[1])+'</button>';}).join('')+'</div></div>'; }).join('');
  return '<div class="vh"><div><h2>アプリのメニュー</h2><p>アプリの基盤（APPREX管理画面）の「メニュー設定」と同じ構成で、下のタブ（フッター横メニュー）と縦メニューを決めます。縦メニューの上から6つがホームのボタンになります。</p></div><div class="acts" style="margin:0"><button type="button" class="btn btn--line" data-go="resetMenu">業種のおすすめに戻す</button><button type="button" class="btn btn--cta" data-view="demo">デモアプリで試す</button></div></div>'
  +'<div class="grid2"><div class="card"><div class="sec-t"><i>1</i>フッター横メニュー（下のタブ・4つ）</div><div class="fslots">'+foot+'</div>'
  +'<div class="sec-t" style="margin-top:16px"><i>2</i>縦メニュー（表示順・'+A.menu.length+'ページ）</div><p class="hint">名前は変えられます（10文字まで）。上から6つがホームのボタンです。</p><ol class="mrows">'+rows+'</ol>'
  +'<div class="sec-t" style="margin-top:16px"><i>3</i>ページを追加</div>'+add+'</div>'
  +'<div class="card"><div class="prev-top"><b style="color:var(--navy)">ホームのプレビュー</b></div><div class="stage">'+phone('home',false)+'</div><p class="hint">追加したページは、デモアプリの各画面と「設定シート」に反映されます。</p></div></div>';
}
function docSheet(){
  var A=S.app, p=palette(), t=TONES[S.d.tone], e=estimate(), pk=pickedImg('hero'), ic=pickedImg('icon');
  var basic=tbl(['項目','設定する内容'],[['アプリ名',esc(storeName())],['業種',esc(D().l)],['プラン（目安）',e.orig.length?'オリジナル開発':esc(e.tierName)+'プラン']]);
  var design=tbl(['管理画面','設定する内容'],[['デザイン → TOP デザイン','レイアウト：案'+S.d.variant+'（'+(S.d.variant==='B'?'リスト':S.d.variant==='C'?'大きな写真':'グリッド'+S.d.cols+'列')+'）／雰囲気：'+t.n],['デザイン → デザイン','メインカラー '+esc(p[1])+'／濃い色 '+esc(p[0])],['デザイン → ロゴ設定',IMG.logo?'AIスタジオでアップロードしたロゴ':'ロゴ画像（背景透過PNG）'],['TOP画像',(IMG.photo||pk)?'AIスタジオの写真'+(pk?'（AI生成・採用案）':''):'メイン写真（横長）'],['アプリアイコン',ic?'AI生成・採用案をもとに1024×1024で作成':'1024×1024の正方形']]);
  var foot=tbl(['順番','フッター横メニュー'],A.footer.map(function(k,i){return [String(i+1),esc(pageName(A,k))];}));
  var menu=tbl(['順番','縦メニュー（表示名）','管理画面のページ','表示'],A.menu.map(function(k,i){return [String(i+1),esc(pageName(A,k)),esc(PAGES[k].l),i<6?'ON（ホームのボタン）':'ON'];}));
  var pages=tbl(['ページ','管理画面での作り方','準備するもの'],A.menu.map(function(k){var q=PAGES[k];return ['<b>'+esc(pageName(A,k))+'</b>',esc(q.admin),esc(q.prep)];}));
  var cp=D().cps[0];
  var push=tbl(['管理画面','最初の設定'],[['プッシュ通知','公開日に「'+esc(storeName())+'のアプリができました」、翌週に「'+esc(cp[0])+'」のお知らせ'],['ポップアップ','初回起動時に「'+esc(cp[0])+'（'+esc(cp[1])+'）」を表示']].concat(A.menu.indexOf('reser')>-1?[['予約管理','予約メニュー・受付時間・枠数を登録']]:[]));
  var store=tbl(['管理画面','内容'],[['アプリ申請','アプリ名「'+esc(storeName())+'」、カテゴリ、スクリーンショット、プライバシーポリシーURL'],['ASO（ストア掲載文）','「'+esc(D().catch)+'」を軸に、'+esc(D().l)+'・地域名・主な機能（'+A.menu.slice(0,4).map(function(k){return esc(pageName(A,k));}).join('・')+'）を入れて作成']]);
  var body='<h2 class="dh"><span>1</span>基本設定</h2>'+basic+'<h2 class="dh"><span>2</span>デザイン</h2>'+design+'<h2 class="dh"><span>3</span>メニュー設定</h2><div class="two-doc">'+foot+'<div></div></div>'+menu;
  var body2='<h2 class="dh"><span>4</span>ページ作成</h2>'+pages+'<h2 class="dh"><span>5</span>配信・予約</h2>'+push+'<h2 class="dh"><span>6</span>アプリ申請・ASO</h2>'+store+'<p class="dnote">この設定シートは、APPREX管理画面のメニュー名に合わせています。制作では担当者がこのシートをもとに設定します（セルフプランの方はこのシートのとおりに登録してください）。</p>';
  return paper('<span class="kind">設定シート（APPREX管理画面）</span><h1 class="sheet-h">'+esc(storeName())+'</h1>'+body,1,2)+paper(body2,2,2);
}

/* ---------- デモアプリ（実際に触れる） ---------- */
function vDemo(){
  var url=CFG.demoUrl||'';
  var qr='';
  if(window.qrcode&&url){ try{ var q=window.qrcode(0,'M'); q.addData(url); q.make(); qr=q.createSvgTag({cellSize:4,margin:2,scalable:true}); }catch(e){} }
  return '<div class="vh"><div><h2>デモアプリで試す</h2><p>今のデザインが、実際に触れるアプリになりました。クーポン・スタンプ・予約・会員証・プッシュ通知の動きを試せます。</p></div><div class="acts" style="margin:0"><button type="button" class="btn btn--line" data-view="design">デザインを直す</button><button type="button" class="btn btn--cta" data-go="confirmDesign">このデザインで決定して仕様書へ</button></div></div>'
  +'<div class="grid2 demo-g"><div class="card demo-stage"><div class="demo-ph"><iframe id="demoFrame" src="'+esc(url)+'" title="デモアプリ" loading="lazy"></iframe></div><button type="button" class="btn btn--line btn--sm" data-go="reloadDemo">最新のデザインで表示しなおす</button></div>'
  +'<div class="card"><div class="sec-t">スマホで開く</div><div class="demo-qr"><div class="qr">'+qr+'</div><div><p>カメラでQRコードを読み取ると、お手元のスマホで開けます。<b>ホーム画面に追加</b>すると、アプリのように全画面で使えます。</p></div></div>'
  +'<div class="sec-t" style="margin-top:16px">共有リンク</div><div class="demo-url"><input type="text" readonly value="'+esc(url)+'" aria-label="デモアプリのURL" id="demoUrl"><button type="button" class="btn btn--pri btn--sm" id="copyDemo" data-label="コピー">コピー</button></div>'
  +'<p class="demo-note">リンクを知っている人だけが見られます（検索には出ません）。社内やご家族に見てもらうのにお使いください。</p>'
  +'<div class="acts"><a class="btn btn--cta btn--sm" href="'+esc(url)+'" target="_blank" rel="noopener">新しいタブで開く</a></div>'
  +'<div class="sec-t" style="margin-top:16px">試してみること</div><ul class="demo-try"><li>ホームのボタンから各画面へ</li><li>クーポンの「使う」→ スタッフに見せる画面</li><li>会員証の「来店スタンプを押す」→ 10個で特典</li>'+(S.feat.reserve?'<li>予約：日付と時間を選んで予約</li>':'')+'<li>その他 → 「通知のデモを受け取る」</li></ul>'
  +((CFG.preview&&(CFG.preview.ios||CFG.preview.android))?'<div class="sec-t" style="margin-top:16px">本番の画面を実機で確認</div><p class="demo-note" style="margin-top:4px">制作が始まると、APPREX管理画面で作ったアプリを「プレビューアプリ」で確認できます（担当者がサービスIDをお知らせします）。</p><div class="acts" style="margin-top:8px">'+(CFG.preview.ios?'<a class="btn btn--line btn--sm" href="'+esc(CFG.preview.ios)+'" target="_blank" rel="noopener">App Store</a>':'')+(CFG.preview.android?'<a class="btn btn--line btn--sm" href="'+esc(CFG.preview.android)+'" target="_blank" rel="noopener">Google Play</a>':'')+'</div>':'')
  +'<p class="demo-note">デモはWebで動く試作です。実際のアプリはiPhone・Androidのアプリとして制作し、ストアで公開します。スタンプや予約の操作はこの端末だけに保存され、お店には届きません。</p></div></div>';
}

function vSpec(){
  var e=estimate();
  var groups=[['std','標準機能（ノーコードで対応）'],['opt','オプション'],['orig','オリジナル開発が必要な機能']];
  var feats=groups.map(function(g){return '<div class="sec-t" style="margin-top:12px">'+g[1]+'</div><div class="feat">'+FEAT.filter(function(f){return f.t===g[0];}).map(function(f){return '<label class="fitem"><input type="checkbox" data-feat="'+f.k+'"'+(S.feat[f.k]?' checked':'')+'><span>'+esc(f.n)+'<span class="tag t-'+f.t+'">'+(f.t==='std'?'標準':f.t==='opt'?'オプション':'独自開発')+'</span><small>'+esc(f.d)+'</small></span></label>';}).join('')+'</div>';}).join('');
  var mats=[[!!IMG.logo,'ロゴ画像'],[!!IMG.photo,'メイン写真'],[true,'メニュー・商品の情報（AIのたたき台あり）'],[true,'店舗情報（住所・営業時間）'],[false,'アプリの説明文（ストア掲載用）'],[false,'Apple・Googleの開発者アカウント（おまかせなら代行）']];
  var matsH='<ul class="mats">'+mats.map(function(m){return '<li class="'+(m[0]?'ok':'')+'"><b>'+(m[0]?'✓':'')+'</b>'+m[1]+'</li>';}).join('')+'</ul>';
  var rows=SCREENS.map(function(s){return '<tr><td>'+s[1]+'</td><td>'+({home:'メインビジュアル、メニュー、クーポン案内',menu:'商品の写真・名前・価格',coupon:'配布中のクーポン一覧',reserve:'日付と時間を選んで予約',card:'会員番号・ポイント'}[s[0]])+'</td></tr>';}).join('');
  var self=(e.orig.length?'—':'0円'), full='300,000円〜（キャンペーン150,000円〜）';
  var est='<div class="est">'
    +'<div class="estc'+(e.rec==='self'?' recm':'')+'">'+(e.rec==='self'?'<span class="bd">おすすめ</span>':'')+'<h4>セルフプラン</h4><div class="big">初期 '+self+'</div><p>月額 '+YEN(e.monthly)+'（'+e.tierName+'）'+(e.opt?'＋オプション '+YEN(e.opt):'')+'</p><p>公開まで：最短2週間</p></div>'
    +'<div class="estc'+(e.rec==='full'?' recm':'')+'">'+(e.rec==='full'?'<span class="bd">おすすめ</span>':'')+'<h4>おまかせプラン</h4><div class="big" style="font-size:1.05rem">制作代行 '+full+'</div><p>月額 '+YEN(e.monthly)+'（'+e.tierName+'）'+(e.opt?'＋オプション '+YEN(e.opt):'')+'</p><p>公開まで：3〜4週間</p></div>'
    +'<div class="estc'+(e.rec==='orig'?' recm':'')+'">'+(e.rec==='orig'?'<span class="bd">おすすめ</span>':'')+'<h4>オリジナル開発</h4><div class="big">'+(e.match?'300万円〜':'個別見積り')+'</div><p>'+(e.match?'月額保守 7万円〜':'ミーティングで正式にお見積り')+'</p><p>公開まで：最短1ヶ月〜</p></div>'
    +'</div><p style="font-size:.78rem;color:var(--muted);margin:8px 0 0">税抜。アプリストア登録費（一律55,000円）が別途かかります。正式な金額はオンラインミーティングでお出しします。</p>';
  var note=e.orig.length?'<div class="warn" style="margin-top:12px">「'+e.orig.map(function(f){return f.n;}).join('」「')+'」はオリジナル開発になります。ミーティングで最適な作り方をご提案します。</div>':'';
  return '<div class="vh"><div><h2>AI仕様書</h2><p>デザインから、AIが仕様書と概算を作りました。機能のオン・オフで概算が変わります。</p></div><div class="acts" style="margin:0"><button type="button" class="btn btn--pri" data-view="docs">企画書・仕様書を見る</button><button type="button" class="btn btn--line" id="copySpec" data-label="仕様書をコピー">仕様書をコピー</button><button type="button" class="btn btn--cta" data-go="confirmSpec">この内容で開発を相談する</button></div></div>'
  +'<div class="grid2"><div class="card"><div class="sec-t">概要</div><dl class="kv"><dt>アプリ名</dt><dd>'+esc(storeName())+'</dd><dt>業種</dt><dd>'+D().l+'</dd><dt>目的</dt><dd>'+esc(S.d.goal)+'</dd><dt>お客様</dt><dd>'+esc(S.d.target)+'</dd><dt>デザイン</dt><dd>'+TONES[S.d.tone].n+'／'+esc(palette()[2])+'／案'+S.d.variant+'（版'+S.vers.length+'）</dd></dl>'
  +'<div class="sec-t" style="margin-top:16px">画面一覧</div><div class="tbl"><table><thead><tr><th>画面</th><th>内容</th></tr></thead><tbody>'+rows+'</tbody></table></div></div>'
  +'<div class="card"><div class="sec-t">用意するもの</div>'+matsH+'<div class="sec-t" style="margin-top:16px">概算</div>'+est+note+'</div></div>'
  +'<div class="card" style="margin-top:18px"><div class="sec-t">機能一覧</div>'+feats+'</div>';
}


/* ---------- 企画書・仕様書・画面構成図 ---------- */
var EFFECT={push:'新着情報やキャンペーンを直接届け、来店のきっかけを作る',news:'最新の情報をいつでも確認してもらえる',coupon:'来店や購入をあと押しする',stamp:'来店回数を増やし、常連のお客様を育てる',card:'会員の利用履歴を残し、顧客管理に活かす',reserve:'電話対応を減らし、24時間予約を受け付ける',menu:'商品やサービスの魅力を写真で伝える',access:'初めてのお客様の来店を助ける',contact:'問い合わせをアプリで受け付ける',catalog:'カタログをいつでも見てもらえる',ec:'アプリから注文・購入してもらえる',multi:'複数店舗の情報をまとめて管理する',gps:'お店の近くに来たお客様に通知する',chatf:'お客様と個別にやり取りする',match:'利用者どうしをつなぐ',pay:'アプリ内で決済・定期課金ができる',pos:'レジや在庫のデータと連動する',video:'会員限定の動画で継続して使ってもらう'};
var ADMIN={push:'プッシュ通知の作成・予約配信',news:'お知らせの作成・公開',coupon:'クーポンの作成（期間・回数の設定）',stamp:'スタンプ・ポイントの付与と特典設定',card:'会員一覧と利用履歴の確認',reserve:'予約枠の設定と予約一覧の確認',menu:'メニュー・商品の登録と価格の変更',catalog:'カタログの登録',ec:'注文・在庫の管理',multi:'店舗ごとの情報管理',gps:'通知するエリアの設定',chatf:'お客様とのメッセージ対応',match:'会員・取引の管理と本人確認の承認',pay:'売上と決済の確認',pos:'連携データの確認',video:'動画・限定コンテンツの登録'};
function today(){ var d=new Date(); return d.getFullYear()+'年'+(d.getMonth()+1)+'月'+d.getDate()+'日'; }
function onFeat(){ return FEAT.filter(function(f){return S.feat[f.k];}); }
function paper(inner,no,total,cls){ return '<section class="paper'+(cls?' '+cls:'')+'">'+inner+'<div class="pgno">'+no+' / '+total+'</div></section>'; }
function sched(){
  var e=estimate();
  if(e.orig.length) return [['1〜2週目','要件定義・画面設計（動く試作で確認）'],['3〜6週目','開発（アプリ・管理画面・サーバー）'],['7〜8週目','テスト・修正・ストア申請'],['9週目〜','公開・運営と改善']];
  if(S.plan==='self') return [['1週目','素材の準備と管理画面への登録'],['2週目','APPREXのチェック・修正'],['3週目','ストア申請'],['4週目','公開']];
  return [['1週目','キックオフ・デザインの確定'],['2〜3週目','制作と登録'],['3〜4週目','テスト版の確認・ストア申請'],['4週目〜','公開・3ヶ月の運用サポート']];
}
function costRows(){
  var e=estimate();
  if(e.orig.length) return [['開発費',e.match?'300万円〜':'ミーティングで確定'],['月額保守',e.match?'7万円〜':'ミーティングで確定'],['アプリストア登録費','55,000円']];
  var r=[[S.plan==='self'?'初期費用':'制作代行費',S.plan==='self'?'0円':'300,000円〜（キャンペーン150,000円〜）'],['月額利用料（'+e.tierName+'）',YEN(e.monthly)+'〜']];
  if(e.opt) r.push(['オプション',YEN(e.opt)]); r.push(['アプリストア登録費','55,000円']); return r;
}
function tbl(head,rows){ return '<table class="dt"><thead><tr>'+head.map(function(h){return '<th>'+h+'</th>';}).join('')+'</tr></thead><tbody>'+rows.map(function(r){return '<tr>'+r.map(function(c){return '<td>'+c+'</td>';}).join('')+'</tr>';}).join('')+'</tbody></table>'; }
function docPlan(){
  var d=D(), name=esc(storeName()), feats=onFeat();
  var cover='<div class="cv"><div class="cv-l"><span class="kind">アプリ企画書</span><h1>'+name+'<br>公式アプリ</h1><p class="cv-sub">'+esc(d.catch)+'</p><dl class="dkv"><dt>業種</dt><dd>'+d.l+'</dd><dt>目的</dt><dd>'+esc(S.d.goal)+'</dd><dt>作成日</dt><dd>'+today()+'</dd><dt>作成</dt><dd>APPREX AIスタジオ（デザイン版'+S.vers.length+'）</dd></dl></div>'
    +'<div class="cv-r"><div class="trio"><div class="t1">'+phone('coupon',true)+'</div><div class="t2">'+phone('home',false)+'</div><div class="t3">'+phone('card',true)+'</div></div></div></div>';
  var A=S.aiPlan||null;
  var p2='<h2 class="dh"><span>1</span>背景と目的</h2><p>'+(A&&A.background?esc(A.background):name+'では、'+esc(S.d.goal)+'と考えています。お客様のスマートフォンに自社のアプリを置き、お知らせやクーポンを直接届けることで、継続して利用していただく仕組みをつくります。')+'</p>'
    +'<h2 class="dh"><span>2</span>対象とするお客様</h2><p>'+(A&&A.target?esc(A.target):esc(S.d.target)+'。日常的にスマートフォンを使い、お得な情報や手軽な予約を求めている層を想定します。')+'</p>'
    +'<h2 class="dh"><span>3</span>アプリのコンセプト</h2><div class="concept"><b>「'+esc(d.catch)+'」</b><span>'+(A&&A.concept?esc(A.concept)+'<br>':'')+'雰囲気：'+TONES[S.d.tone].n+'　／　メインカラー：'+esc(palette()[2])+'</span></div>'
    +'<h2 class="dh"><span>4</span>主な機能と期待する効果</h2>'+tbl(['機能','期待する効果'],(A&&A.effects&&A.effects.length)?A.effects.map(function(e){return ['<b>'+esc(e[0])+'</b>',esc(e[1])];}):feats.map(function(f){return ['<b>'+esc(f.n)+'</b>',EFFECT[f.k]||''];}));
  var shots='<div class="shots">'+SCREENS.map(function(sc){return '<figure>'+phone(sc[0],true)+'<figcaption>'+sc[1]+'</figcaption></figure>';}).join('')+'</div>';
  var comp=pickedImg('screens')||null, icon=pickedImg('icon'), hero=pickedImg('hero');
  if(comp){ shots='<figure class="comp"><img src="'+esc(comp.url)+'" alt="AIが生成した画面デザイン"><figcaption>AIが生成したデザインカンプ（採用案）</figcaption></figure>'+shots; }
  if(icon||hero){ shots+='<div class="comp2">'+(icon?'<figure><img src="'+esc(icon.url)+'" alt=""><figcaption>アプリアイコン（AI生成）</figcaption></figure>':'')+(hero?'<figure><img src="'+esc(hero.url)+'" alt=""><figcaption>メインビジュアル（AI生成）</figcaption></figure>':'')+'</div>'; }
  var p3='<h2 class="dh"><span>5</span>アプリのイメージ</h2>'+shots
    +'<h2 class="dh"><span>6</span>運用のイメージ</h2>'+tbl(['配信するもの','頻度の目安','内容'],[['プッシュ通知','週1〜2回','新着情報、空き状況、キャンペーンのお知らせ'],['クーポン','月1〜2回','来店や購入のきっかけになる特典'],['お知らせ','随時','営業日、新商品、イベント'],['会員向け特典','誕生月など','常連のお客様へのお礼']])
    +'<h2 class="dh"><span>7</span>効果を測る指標</h2><ul class="dl">'+((A&&A.kpi&&A.kpi.length)?A.kpi.map(function(k){return '<li>'+esc(k)+'</li>';}).join(''):'<li>ダウンロード数</li><li>月に1回以上アプリを開いた人数</li><li>クーポンの利用数</li>'+(S.feat.reserve?'<li>アプリからの予約数</li>':'')+'<li>再来店・再購入の割合</li>')+'</ul>'+((A&&A.promo&&A.promo.length)?'<h2 class="dh"><span>+</span>アプリを広める施策</h2><ul class="dl">'+A.promo.map(function(k){return '<li>'+esc(k)+'</li>';}).join('')+'</ul>':'')
    +'<h2 class="dh"><span>8</span>スケジュールと概算費用</h2><div class="two-doc">'+tbl(['時期','内容'],sched())+tbl(['項目','金額（税抜）'],costRows())+'</div><p class="dnote">正式なスケジュールと金額は、オンラインミーティングで確定します。</p>';
  return paper(cover,1,3,'cover')+paper(p2,2,3)+paper(p3,3,3);
}
function docSpec(){
  var e=estimate(), p=palette(), t=TONES[S.d.tone], feats=onFeat();
  var screenRows=[['S-01','ホーム','メインビジュアル、メニューボタン、クーポン案内','起動時'],['S-02','メニュー','商品の写真・名前・価格','ホームのボタン'],['S-03','クーポン','配布中のクーポン一覧と利用ボタン','下部タブ'],['S-04','予約','日付と時間を選んで予約','下部タブ・ホームのボタン'],['S-05','会員証','会員番号・ポイント・バーコード','下部タブ']];
  var p1='<div class="sp-head"><span class="kind">アプリ仕様書</span><h1>'+esc(storeName())+' 公式アプリ</h1><p>作成日 '+today()+' ／ APPREX AIスタジオ（版'+S.vers.length+'）</p></div>'
    +'<h2 class="dh"><span>1</span>基本情報</h2>'+tbl(['項目','内容'],[['アプリ名',esc(storeName())],['業種',D().l],['対応OS','iOS・Android'],['想定する作り方',e.orig.length?'オリジナル開発':'ノーコード（'+e.tierName+'プラン）'],['目的',esc(S.d.goal)]])
    +'<h2 class="dh"><span>2</span>画面一覧</h2>'+tbl(['画面ID','画面名','主な要素','開き方'],screenRows)
    +'<h2 class="dh"><span>3</span>機能仕様</h2>'+tbl(['機能','内容','区分'],feats.map(function(f){return [esc(f.n),esc(f.d||EFFECT[f.k]||''),f.t==='std'?'標準':f.t==='opt'?'オプション':'オリジナル開発'];}));
  var sw=function(c,l){return '<span class="sw2"><i style="background:'+c+'"></i>'+l+'<code>'+c+'</code></span>';};
  var p2='<h2 class="dh"><span>4</span>デザイン仕様</h2><div class="dspec"><div>'+sw(p[0],'ベースカラー')+sw(p[1],'アクセントカラー')+'</div>'+tbl(['項目','指定'],[['雰囲気',t.n],['書体',t.ff.split(',')[0].replace(/'/g,'')],['角の丸み',t.r+'px'],['文字の大きさ',S.d.fs>1?'標準より大きめ（1.15倍）':'標準'],['ホームのレイアウト','案'+S.d.variant+'（'+({A:'グリッド'+S.d.cols+'列',B:'リスト',C:'大きな写真'}[S.d.variant])+'）'],['予約ボタン',S.d.resBig?'ホームに大きく配置':'メニュー内に配置']])+'</div>'
    +'<h2 class="dh"><span>5</span>管理画面でできること</h2><ul class="dl">'+feats.filter(function(f){return ADMIN[f.k];}).map(function(f){return '<li>'+ADMIN[f.k]+'</li>';}).join('')+'</ul>'
    +'<h2 class="dh"><span>6</span>用意する素材</h2><ul class="dl"><li>ロゴ画像'+(IMG.logo?'（受領済み）':'')+'</li><li>メイン写真'+(IMG.photo?'（受領済み）':'')+'</li><li>メニュー・商品の情報（写真・名前・価格）</li><li>店舗情報（住所・営業時間・電話番号）</li><li>アプリストアに載せる説明文</li></ul>'
    +'<h2 class="dh"><span>7</span>備考</h2><p>本仕様書はAIスタジオで作成したたたき台です。正式な仕様は、オンラインミーティングで内容を確認したうえで確定します。</p>';
  return paper(p1,1,2)+paper(p2,2,2);
}
function docFlow(){
  var nodes=SCREENS.slice(1).map(function(sc,i){return '<div class="tn"><span class="tid">S-0'+(i+2)+'</span>'+phone(sc[0],true)+'<b>'+sc[1]+'</b><small>'+(['ホームのボタンから','下部タブから','下部タブ・ホームのボタンから','下部タブから'][i])+'</small></div>';}).join('');
  var inner='<h2 class="dh" style="margin-top:0"><span>図</span>画面構成図</h2><p>アプリを開いてから、どの画面にどう移動するかを示した図です。</p>'
    +'<div class="tree"><div class="tn root"><span class="tid">S-01</span>'+phone('home',true)+'<b>ホーム</b><small>アプリを開くと最初に表示</small></div><div class="tlink" aria-hidden="true"></div><div class="trow">'+nodes+'</div></div>'
    +'<div class="legend"><b>下部タブ</b>ホーム／クーポン／予約／会員証 は、どの画面からでも1タップで移動できます。<br><b>プッシュ通知</b>通知をタップすると、該当するお知らせ・クーポンの画面が開きます。</div>';
  return paper(inner,1,1,'flow');
}
function vDocs(){
  var tab=S._doc||'plan';
  var tabs=[['plan','企画書'],['spec','仕様書'],['flow','画面構成図'],['sheet','設定シート（管理画面）']].map(function(x){return '<button type="button" class="stab" role="tab" data-doc="'+x[0]+'" aria-selected="'+(tab===x[0])+'">'+x[1]+'</button>';}).join('');
  var body=tab==='plan'?docPlan():tab==='spec'?docSpec():tab==='sheet'?docSheet():docFlow();
  return '<div class="vh"><div><h2>企画書・仕様書</h2><p>AIが作ったデザインと仕様から、社内やご家族にそのまま見せられる書類を作りました。ミーティングでもこの書類を使います。</p></div><div class="acts" style="margin:0">'+(AI.text?'<button type="button" class="btn btn--pri" data-go="aiPlan">'+(S.aiPlan?'AIで企画書を書き直す':'AIで企画書の文章を作る')+'</button>':'')+'<button type="button" class="btn btn--line" id="copyDoc" data-label="本文をコピー">本文をコピー</button><button type="button" class="btn btn--cta" data-go="confirmSpec">この内容で開発を相談する</button></div></div>'
   +'<div class="stabs" role="tablist" aria-label="書類" style="margin-bottom:16px">'+tabs+'</div><div class="docs">'+body+'</div>';
}

function vConsult(){
  var e=estimate(), orig=e.orig.length>0;
  if(orig) S.plan='orig'; else if(S.plan==='orig') S.plan='full';
  var pl=[['self','セルフプラン','初期0円。登録は自分で、APPREXがチェック',orig],['full','おまかせプラン','制作代行300,000円〜（キャンペーン150,000円〜）',orig],['orig','オリジナル開発','独自機能を開発。ミーティングで見積り',!orig]];
  var plH=pl.map(function(p){return '<label class="pl"><input type="radio" name="plan" value="'+p[0]+'"'+(S.plan===p[0]?' checked':'')+(p[3]?' disabled':'')+'><b>'+p[1]+'</b><span>'+p[2]+'</span></label>';}).join('');
  return '<div class="vh"><div><h2>開発を相談</h2><p>作ったデザインと仕様書を、そのまま担当者に送ります。次にオンラインミーティングの日時を選びます。</p></div></div>'
  +'<div class="card"><div class="sum">'+phone('home',true)+'<div><div class="sec-t">送る内容</div><dl class="kv"><dt>アプリ名</dt><dd>'+esc(storeName())+'（'+D().l+'）</dd><dt>デザイン</dt><dd>5画面・版'+S.vers.length+'（'+TONES[S.d.tone].n+'／案'+S.d.variant+'）</dd><dt>機能</dt><dd>'+FEAT.filter(function(f){return S.feat[f.k];}).map(function(f){return esc(f.n);}).join('、')+'</dd><dt>添付</dt><dd>企画書・仕様書・画面構成図（AI作成）'+(IMGS.length?'、AIデザイン画像（'+(IMGS.filter(function(i){return i.picked;}).length?'採用案':'最新3枚')+'）':'')+'</dd><dt>概算</dt><dd>'+(orig?(e.match?'オリジナル開発 300万円〜':'オリジナル開発 個別見積り'):'月額 '+YEN(e.monthly)+'（'+e.tierName+'）')+'</dd></dl></div></div>'
  +'<div class="sec-t" style="margin-top:18px">進め方</div><div class="plans">'+plH+'</div>'
  +'<div class="field" style="margin-top:14px"><label for="f-notes">担当者に伝えたいこと（任意）</label><textarea id="f-notes" placeholder="例：来年3月のリニューアルオープンに合わせて公開したい">'+esc(S.notes)+'</textarea></div>'
  +'<div class="acts"><button type="button" class="btn btn--cta" data-go="sendConsult">相談を送ってミーティングを予約する</button></div></div>';
}

function weekdays(){
  var out=[], dt=new Date(); dt.setHours(0,0,0,0);
  while(out.length<7){ dt.setDate(dt.getDate()+1); var w=dt.getDay(); if(w!==0&&w!==6){ out.push(new Date(dt)); } }
  return out;
}
var WD=['日','月','火','水','木','金','土'];
function vMeeting(){
  if(S.prog<4) return gate('ミーティングの予約は、開発の相談を送ったあとにできます。','consult','開発を相談へ');
  if(S.meeting){
    return '<div class="vh"><div><h2>オンラインミーティング</h2><p>予約が完了しています。</p></div></div><div class="confirm"><span>ご予約日時</span><b>'+esc(S.meeting)+'（60分）</b><span>Google Meet で行います。参加用のURLは確認メールでお送りします。当日は、AIで作ったデザインと仕様書を画面で見ながら、正式なお見積りまでご案内します。</span></div>'
    +(S.met?'<div class="flash" style="margin-top:12px">ミーティングが終わり、お見積りが届いています。</div><div class="acts"><button type="button" class="btn btn--cta" data-view="quote">お見積りを確認する</button></div>'
    :'<p style="font-size:.84rem;color:var(--body);margin-top:12px">日時の変更は、下の「制作の進捗」のメッセージか、確認メールへの返信でお知らせください。</p>');
  }
  var days=weekdays(), sel=S._day==null?0:S._day;
  var dH=days.map(function(d,i){return '<button type="button" class="day" data-day="'+i+'" aria-pressed="'+(sel===i)+'"><small>'+(d.getMonth()+1)+'月</small>'+d.getDate()+'日('+WD[d.getDay()]+')</button>';}).join('');
  var times=['10:00','11:00','13:00','14:00','15:00','16:00','17:00'];
  var tH=times.map(function(t,i){var busy=false;return '<button type="button" class="time" data-time="'+t+'"'+(busy?' disabled':'')+' aria-pressed="'+(S._time===t)+'">'+t+'</button>';}).join('');
  return '<div class="vh"><div><h2>オンラインミーティングを予約</h2><p>平日10時〜18時。60分ほどで、デザインと仕様書を見ながらお見積りまでご案内します。</p></div></div>'
  +'<div class="card"><div class="sec-t">日付</div><div class="days">'+dH+'</div><div class="sec-t" style="margin-top:14px">時間</div><div class="times">'+tH+'</div>'
  +'<div class="acts"><button type="button" class="btn btn--cta" data-go="book"'+(S._time?'':' disabled')+'>この日時で予約する</button></div><div class="err" id="bookErr" hidden></div></div>';
}

function quoteItems(){
  var e=estimate(), it=[];
  if(S.plan==='orig'){ var dev=e.match?3000000:1000000; it.push(['オリジナル開発 着手金（開発費の50%）'+(e.match?'':'　※金額は例'),dev/2]); it.push(['アプリストア登録費',55000]); }
  else{ if(S.plan==='full'){it.push(['制作代行費（キャンペーン適用：300,000円→150,000円）',150000]);} else {it.push(['初期設定費（キャンペーンで0円）',0]);} it.push(['アプリストア登録費',55000]); it.push(['初月利用料（'+e.tierName+'プラン）',e.monthly]); if(e.opt) it.push(['オプション',e.opt]); }
  return it;
}
function vQuote(){
  if(!S.met) return gate('お見積りは、オンラインミーティングのあとに担当者がお送りし、ここでご確認いただけます。','meeting','ミーティング予約へ');
  var it=quoteItems(), sub=it.reduce(function(a,b){return a+b[1];},0);
  var rows=it.map(function(r){return '<tr><td>'+esc(r[0])+'</td><td class="r">'+YEN(r[1])+'</td></tr>';}).join('');
  var q=CFG.quote||{}, url=q.url||CFG.mypage;
  if(S.paid){ return '<div class="vh"><div><h2>お見積り・ご入金</h2></div></div><div class="flash">ご入金を確認しました。制作を進めています。進み具合は「制作の進捗」で確認できます。</div><div class="acts"><button type="button" class="btn btn--cta" data-view="prod">制作の進捗を見る</button><a class="btn btn--line" href="'+esc(CFG.mypage)+'">契約・お支払い（マイページ）</a></div>'; }
  return '<div class="vh"><div><h2>お見積り・ご入金</h2><p>ミーティングの内容をもとに、担当者が正式なお見積りをお送りしました。内容をご確認のうえ、お支払いへお進みください。</p></div></div>'
  +'<div class="card">'+(q.note?'<div class="flash" style="margin-bottom:14px;white-space:pre-wrap">'+esc(q.note)+'</div>':'')
  +'<div class="acts" style="margin-top:0"><a class="btn btn--cta" href="'+esc(url)+'"'+(q.url?' target="_blank" rel="noopener"':'')+'>お見積りを確認してお支払いへ</a></div>'
  +'<div class="sec-t" style="margin-top:18px">参考：AIの概算（税抜）</div><div class="tbl"><table><thead><tr><th>項目</th><th class="r">金額（税抜）</th></tr></thead><tbody>'+rows+'<tr class="rec"><td><b>概算 小計</b></td><td class="r"><b>'+YEN(sub)+'</b></td></tr></tbody></table></div>'
  +'<p style="font-size:.78rem;color:var(--muted);margin:8px 0 0">正式な金額は担当者からのお見積りが優先されます。お支払いはクレジットカード（Square）または銀行振込に対応しています。</p></div>';
}

var PROD=[['キックオフ','担当者と進め方を確認'],['デザイン確定','AIデザインを仕上げて確定'],['開発・登録','機能の設定と画面の作り込み'],['テスト版の確認','お手元のスマホで確認'],['ストア申請','App Store・Google Play の審査'],['公開','アプリをダウンロードできる状態に']];
function vProd(){
  if(!S.paid) return gate('制作の進捗は、ご入金の確認後にここで見られます。','quote','お見積り・入金へ');
  var tl=PROD.map(function(p,i){var c=i<S.prod?'done':(i===S.prod?'now':'');return '<li class="'+c+'"><i>'+(i<S.prod?'✓':i+1)+'</i><div><b>'+p[0]+'</b><small>'+p[1]+'</small></div><em>'+(c==='done'?'完了':c==='now'?'進行中':'これから')+'</em></li>';}).join('');
  var fin=S.prod>=PROD.length;
  var msgs=S.msgs.length?S.msgs.map(function(m){return '<div class="m '+(m[0]==='staff'?'staff':'me')+'"><small>'+(m[0]==='staff'?'APPREX 担当':'あなた')+(m[2]?'　'+esc(m[2]):'')+'</small>'+esc(m[1])+'</div>';}).join(''):'<p style="font-size:.84rem;color:var(--muted)">ご質問やご要望を、ここから担当者に送れます。</p>';
  return (fin?'<div class="card done-hero" style="margin-bottom:18px"><b>アプリが公開されました</b>お客様にダウンロードを案内しましょう。お知らせやクーポンの配信は管理画面からできます。</div>':'')
  +'<div class="vh"><div><h2>制作の進捗</h2><p>今どこまで進んでいるかを、いつでも確認できます。</p></div></div>'
  +'<div class="grid2"><div class="card"><ol class="tl">'+tl+'</ol></div><div class="card"><div class="sec-t">担当者とのやりとり</div><div class="msgs" id="msgs">'+msgs+'</div><form class="msg-in" id="msgForm"><input id="msgText" type="text" placeholder="メッセージを入力" aria-label="メッセージ"><button class="btn btn--pri btn--sm" type="submit">送信</button></form></div></div>';
}

function gate(msg,view,label){ return '<div class="card"><div class="warn">'+msg+'</div><div class="acts"><button type="button" class="btn btn--pri" data-view="'+view+'">'+label+'</button></div></div>'; }

/* ---------- render ---------- */
var $=function(id){return document.getElementById(id);};
function renderProg(){
  var cur=Math.min(S.prog,STEPS.length-1), allDone=S.prod>=PROD.length;
  $('prog').innerHTML=STEPS.map(function(s,i){var c=(i<S.prog||allDone)?'done':(i===cur?'cur':'');return '<li class="'+c+'"><span class="n">'+(c==='done'?'✓':i+1)+'</span>'+s+'</li>';}).join('');
}
var MANUAL_ANCHOR={appmenu:'appmenu',demo:'demo',design:'design',spec:'spec',docs:'docs',consult:'consult',meeting:'meeting',quote:'quote',prod:'prod'};
function renderSide(){
  $('side').innerHTML=VIEWS.map(function(v,i){var done=S.prog>v[2]||(v[0]==='prod'&&S.prod>=PROD.length);return '<button type="button" class="nav" data-view="'+v[0]+'"'+(S.view===v[0]?' aria-current="page"':'')+'><i>'+(i+1)+'</i>'+v[1]+(done?'<span class="st">完了</span>':'')+'</button>';}).join('')
  +'<div class="help"><b style="color:var(--navy)">困ったときは</b><br>今の画面の使い方を、AIガイドがその場で案内します。<div class="help-acts"><button type="button" class="btn btn--pri btn--sm" data-go="askGuide">AIガイドに聞く</button>'+(CFG.manual?'<a class="btn btn--line btn--sm" href="'+esc(CFG.manual+'#'+(MANUAL_ANCHOR[S.view]||''))+'" target="_blank" rel="noopener">この画面の使い方</a>':'')+'</div></div>';
}
function render(){
  renderProg(); renderSide();
  var v=S.view, h='';
  if(v==='design') h=vDesign(); else if(v==='demo') h=vDemo(); else if(v==='appmenu') h=vAppMenu(); else if(v==='spec') h=vSpec(); else if(v==='docs') h=vDocs(); else if(v==='consult') h=vConsult(); else if(v==='meeting') h=vMeeting(); else if(v==='quote') h=vQuote(); else h=vProd();
  $('stMain').innerHTML=h; bind(); save();
  try{ document.dispatchEvent(new CustomEvent('apprex:studio-step',{detail:{view:S.view}})); }catch(e){}
  var ms=$('msgs'); if(ms) ms.scrollTop=ms.scrollHeight;
}
function go(view){ S.view=view; S._aiMsg=''; render(); try{window.scrollTo({top:0,behavior:'smooth'});}catch(e){window.scrollTo(0,0);} }

function readImg(input,key){
  var f=input.files&&input.files[0]; if(!f) return;
  if(f.size>5*1024*1024){ alertBox('5MB以下の画像を選んでください。'); return; }
  var r=new FileReader(); r.onload=function(){
    IMG[key]=r.result; saveImg(); S.vers.push({n:S.vers.length+1,note:(key==='logo'?'ロゴ':'メイン写真')+'をデザインに反映'}); render();
    // アカウントにも保存（デモアプリや別の端末に反映）。
    api('studio/upload',{kind:key,data:r.result}).then(function(b){ var a=b.assets||{}; if(a[key]){ IMG[key]=a[key]; saveImg(); } }).catch(function(x){ alertBox(x.message); });
  }; r.readAsDataURL(f);
}
function alertBox(t){ var m=$('aidMsg'); if(m){ m.textContent=t; } }

function copyText(text,btn){
  var label=btn.getAttribute('data-label')||btn.textContent;
  function ok(){btn.textContent='コピーしました';setTimeout(function(){btn.textContent=label;},1600);}
  function fb(){var ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();try{document.execCommand('copy');ok();}catch(e){btn.textContent='選択してコピーしてください';}document.body.removeChild(ta);}
  try{navigator.clipboard.writeText(text).then(ok,fb);}catch(e){fb();}
}

function bind(){
  var m=$('stMain');
  var on=function(id,ev,fn){var el=$(id); if(el) el.addEventListener(ev,fn);};
  on('f-ind','change',function(e){ S.d.ind=e.target.value; S.app=DATA.defaultApp(S.d.ind); if(S.d.ov){delete S.d.ov.catch;} var f={}; IND[S.d.ind].feat.forEach(function(k){f[k]=true;}); S.feat=f; S.vers.push({n:S.vers.length+1,note:IND[S.d.ind].l+'向けに作り直し'}); render(); });
  on('f-name','input',function(e){ S.d.name=e.target.value; save(); m.querySelectorAll('.s-top b').forEach(function(b){b.textContent=storeName();}); });
  on('f-name','change',function(){ render(); });
  on('f-goal','change',function(e){ S.d.goal=e.target.value; save(); });
  on('f-target','change',function(e){ S.d.target=e.target.value; save(); });
  on('f-custom','change',function(e){ S.d.custom=e.target.value; S.vers.push({n:S.vers.length+1,note:'色を'+e.target.value+'に'}); render(); });
  on('f-logo','change',function(e){ readImg(e.target,'logo'); });
  on('f-photo','change',function(e){ readImg(e.target,'photo'); });
  on('copyDemo','click',function(e){ copyText(CFG.demoUrl||'', e.currentTarget); });
  on('copySpec','click',function(e){ copyText(specText(), e.currentTarget); });
  on('copyDoc','click',function(e){ var d=document.querySelector('.docs'); if(d) copyText(d.innerText, e.currentTarget); });
  on('aidText','input',function(e){ S.d.brief=e.target.value; save(); });
  on('f-notes','input',function(e){ S.notes=e.target.value; save(); });
  on('msgForm','submit',function(e){ e.preventDefault(); var inp=$('msgText'), t=inp.value.trim(); if(!t) return; inp.disabled=true;
    api('studio/message',{text:t}).then(function(b){ S.msgs=(b.msgs||[]).map(function(m){return [m[0],m[1],m[2]];}); render(); }).catch(function(x){ inp.disabled=false; alert(x.message); }); });
}

document.addEventListener('click',function(e){
  var t=e.target.closest('button,[data-view]'); if(!t) return;
  if(t.dataset.view){ go(t.dataset.view); return; }
  if(t.dataset.color){ S.d.color=+t.dataset.color; S.d.custom=null; S.vers.push({n:S.vers.length+1,note:'色を'+COLORS[S.d.color][0]+'に'}); render(); return; }
  if(t.dataset.tone){ S.d.tone=t.dataset.tone; S.vers.push({n:S.vers.length+1,note:'雰囲気を「'+TONES[S.d.tone].n+'」に'}); render(); return; }
  if(t.dataset.doc){ S._doc=t.dataset.doc; render(); return; }
  if(t.dataset.screen){ if(t.dataset.screen==='all'){S.d.all=!S.d.all;} else {S.d.all=false;S.d.screen=t.dataset.screen;} render(); return; }
  if(t.dataset.variant){ S.d.variant=t.dataset.variant; S.vers.push({n:S.vers.length+1,note:'AIの別案「案'+t.dataset.variant+'」を選択'}); render(); return; }
  if(t.dataset.sug){ var i=$('aidText'); if(i){ i.value=t.dataset.sug; i.focus(); } return; }
  if(t.dataset.gen){ genImage(t.dataset.gen); return; }
  if(t.dataset.zoom){ zoom(t.dataset.zoom); return; }
  if(t.dataset.show){ S._show=+t.dataset.show; render(); return; }
  if(t.dataset.pick){ t.disabled=true; api('studio/ai-image',{pick:+t.dataset.pick,kind:t.dataset.kind}).then(function(b){ IMGS=b.images||IMGS; save(); render(); }).catch(function(x){ alert(x.message); t.disabled=false; }); return; }
  if(t.dataset.day){ S._day=+t.dataset.day; S._time=null; render(); return; }
  if(t.dataset.time){ S._time=t.dataset.time; render(); return; }
  var g=t.dataset.go;
  if(g==='askGuide'){ try{ document.dispatchEvent(new CustomEvent('apprex:chat-open')); }catch(e){} return; }
  if(t.dataset.add){ S.app.menu.push(t.dataset.add); syncFeat(); S.vers.push({n:S.vers.length+1,note:'メニューに「'+PAGES[t.dataset.add].l+'」を追加'}); render(); return; }
  if(t.dataset.rm){ if(S.app.menu.length<=4){ alert('縦メニューは4ページ以上にしてください。'); return; } S.app.menu=S.app.menu.filter(function(k){return k!==t.dataset.rm;}); S.app.footer=S.app.footer.map(function(k){return S.app.menu.indexOf(k)>-1||k==='home'?k:'home';}); render(); return; }
  if(t.dataset.mv){ var i=+t.dataset.mv, j=i+(+t.dataset.dir), m=S.app.menu; if(j<0||j>=m.length) return; var x=m[i]; m[i]=m[j]; m[j]=x; render(); return; }
  if(g==='resetMenu'){ if(!window.confirm('縦メニューとフッターを、業種のおすすめ構成に戻します。')) return; S.app=DATA.defaultApp(S.d.ind); render(); return; }
  if(g==='reloadDemo'){ save(); var fr=$('demoFrame'); t.disabled=true; t.textContent='保存しています…'; setTimeout(function(){ if(fr){ fr.src=CFG.demoUrl+(CFG.demoUrl.indexOf('?')>-1?'&':'?')+'t='+Date.now(); } t.disabled=false; t.textContent='最新のデザインで表示しなおす'; },1600); return; }
  if(g==='aiDesign'){ var ta=$('aidText'); runAiDesign(ta?ta.value:'',t); return; }
  if(g==='aiPlan'){ t.disabled=true; t.textContent='AIが書いています…';
    api('studio/ai-plan',{brief:brief(),features:onFeat().map(function(f){return f.n;})}).then(function(b){ S.aiPlan=b.plan; S._doc='plan'; save(); render(); }).catch(function(x){ t.disabled=false; t.textContent='AIで企画書の文章を作る'; alert(x.message); }); return; }
  if(g==='confirmDesign'){ S.prog=Math.max(S.prog,2); go('spec'); }
  else if(g==='confirmSpec'){ S.prog=Math.max(S.prog,3); go('consult'); }
  else if(g==='sendConsult'){
    var r=document.querySelector('input[name=plan]:checked'); if(r) S.plan=r.value;
    var nt=$('f-notes'); if(nt) S.notes=nt.value;
    var PLAN={self:'セルフプラン',full:'おまかせプラン',orig:'オリジナル開発'};
    t.disabled=true; t.textContent='送信しています…';
    api('studio/consult',{plan:PLAN[S.plan]||S.plan,summary:specText()+(S.notes?'\n\n【担当者に伝えたいこと】\n'+S.notes:'')})
      .then(function(b){ if(b.lead){ lead(b.lead); } S.consulted=Date.now(); S.prog=Math.max(S.prog,4); go('meeting'); })
      .catch(function(x){ t.disabled=false; t.textContent='相談を送ってミーティングを予約する'; alert(x.message); });
  }
  else if(g==='book'){
    var d=weekdays()[S._day||0], pad=function(n){return ('0'+n).slice(-2);};
    var iso=d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+S._time;
    t.disabled=true; t.textContent='予約しています…';
    api('studio/meeting',{meeting_at:iso})
      .then(function(){ S.meeting=fmtMeeting(iso); render(); })
      .catch(function(x){ t.disabled=false; t.textContent='この日時で予約する'; var be=$('bookErr'); if(be){ be.textContent=x.message; be.hidden=false; } });
  }
});
document.addEventListener('change',function(e){
  if(e.target.dataset&&e.target.dataset.foot){ S.app.footer[+e.target.dataset.foot]=e.target.value; save(); return; }
  if(e.target.dataset&&e.target.dataset.name){ var v=e.target.value.trim(), k=e.target.dataset.name; if(v&&v!==PAGES[k].l){ S.app.names[k]=v.slice(0,10); } else { delete S.app.names[k]; } render(); return; }
  if(e.target.name==='plan'){ S.plan=e.target.value; save(); }
  if(e.target.dataset&&e.target.dataset.feat){ S.feat[e.target.dataset.feat]=e.target.checked; render(); }
});
$('reset').addEventListener('click',function(){
  if(!window.confirm('デザインと仕様の内容を最初の状態に戻します。よろしいですか？（相談・予約の記録は残ります）')) return;
  var keep={prog:S.prog,meeting:S.meeting,met:S.met,paid:S.paid,prod:S.prod,msgs:S.msgs,consulted:S.consulted};
  S=def(); Object.keys(keep).forEach(function(k){ S[k]=keep[k]; }); IMG={logo:null,photo:null}; saveImg(); render();
});
render();
})();
