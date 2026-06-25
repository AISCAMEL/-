/* BUYMO チャットアシスタント（自動応答・サーバー不要）
   設置: <script src="/widgets/chatbot.js" data-avatar="/images/characters/camel-face.png"
            data-tel="0120000000" data-tel-disp="0120-000-000"
            data-form="/index.html" data-area="/area/"></script> */
(function(){
  var s=document.currentScript||(function(){var a=document.getElementsByTagName('script');return a[a.length-1]})();
  var D=s.dataset||{};
  var cfg={
    avatar:D.avatar||'/images/characters/camel-face.png',
    tel:D.tel||'0120000000', telDisp:D.telDisp||'0120-000-000',
    form:D.form||'/index.html', area:D.area||'/area/'
  };
  var OR='#f5821f', ORD='#e2670c', NAVY='#243149';
  var css=''
  +'.bmc-l{position:fixed;right:18px;bottom:18px;z-index:9999;display:flex;align-items:center;gap:8px;cursor:pointer}'
  +'.bmc-l .b{width:62px;height:62px;border-radius:50%;background:linear-gradient(180deg,#ff9b3a,'+ORD+');box-shadow:0 10px 26px -6px rgba(226,103,12,.6);display:flex;align-items:center;justify-content:center;overflow:hidden;border:3px solid #fff}'
  +'.bmc-l .b img{width:78%;height:78%;object-fit:contain}'
  +'.bmc-l .t{background:#fff;color:'+NAVY+';font-weight:800;font-size:12.5px;padding:7px 12px;border-radius:14px 14px 2px 14px;box-shadow:0 8px 20px -8px rgba(0,0,0,.3);font-family:"Zen Maru Gothic",sans-serif}'
  +'@keyframes bmcbob{50%{transform:translateY(-5px)}} .bmc-l .b{animation:bmcbob 2.6s ease-in-out infinite}'
  +'.bmc-p{position:fixed;right:18px;bottom:18px;z-index:10000;width:360px;max-width:calc(100vw - 24px);height:560px;max-height:calc(100vh - 36px);background:#fff;border-radius:18px;box-shadow:0 30px 70px -20px rgba(0,0,0,.45);display:none;flex-direction:column;overflow:hidden;font-family:"Zen Maru Gothic",sans-serif}'
  +'.bmc-p.open{display:flex}'
  +'.bmc-h{background:linear-gradient(120deg,#ff9b3a,'+ORD+');color:#fff;padding:13px 14px;display:flex;align-items:center;gap:10px}'
  +'.bmc-h .av{width:42px;height:42px;border-radius:50%;background:#fff;overflow:hidden;flex:0 0 42px;display:flex;align-items:center;justify-content:center}'
  +'.bmc-h .av img{width:88%}'
  +'.bmc-h b{font-size:15px;display:block;line-height:1.2}.bmc-h small{font-size:11px;opacity:.9}'
  +'.bmc-h .x{margin-left:auto;background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:50%;font-size:18px;cursor:pointer}'
  +'.bmc-body{flex:1;overflow-y:auto;padding:14px;background:#fff6ee}'
  +'.bmc-row{display:flex;margin-bottom:10px;gap:8px}'
  +'.bmc-row.u{justify-content:flex-end}'
  +'.bmc-av{width:30px;height:30px;border-radius:50%;background:#fff;border:1px solid #f0e4d6;overflow:hidden;flex:0 0 30px;display:flex;align-items:center;justify-content:center}'
  +'.bmc-av img{width:86%}'
  +'.bmc-msg{max-width:78%;padding:10px 13px;border-radius:14px;font-size:13.5px;line-height:1.7;white-space:pre-line}'
  +'.bmc-row.a .bmc-msg{background:#fff;color:#2c3346;border:1px solid #f0e4d6;border-top-left-radius:3px}'
  +'.bmc-row.u .bmc-msg{background:'+NAVY+';color:#fff;border-top-right-radius:3px}'
  +'.bmc-chips{display:flex;flex-wrap:wrap;gap:7px;margin:2px 0 6px 38px}'
  +'.bmc-chips button{background:#fff;border:1.5px solid '+OR+';color:'+ORD+';font-weight:800;font-size:12.5px;padding:7px 12px;border-radius:999px;cursor:pointer;font-family:inherit}'
  +'.bmc-chips button:hover{background:'+OR+';color:#fff}'
  +'.bmc-cta{display:flex;flex-wrap:wrap;gap:7px;margin:2px 0 8px 38px}'
  +'.bmc-cta a{text-decoration:none;font-weight:800;font-size:12.5px;padding:8px 14px;border-radius:999px;color:#fff}'
  +'.bmc-cta a.f{background:linear-gradient(180deg,#ff9b3a,'+ORD+')}'
  +'.bmc-cta a.t{background:#06c755}'
  +'.bmc-cta a.ar{background:'+NAVY+'}'
  +'.bmc-foot{border-top:1px solid #f0e4d6;padding:9px;display:flex;gap:7px;background:#fff}'
  +'.bmc-foot input{flex:1;border:1.5px solid #e9dcc9;border-radius:999px;padding:10px 14px;font-size:13.5px;font-family:inherit;outline:none}'
  +'.bmc-foot input:focus{border-color:'+OR+'}'
  +'.bmc-foot button{background:linear-gradient(180deg,#ff9b3a,'+ORD+');border:none;color:#fff;width:42px;height:42px;border-radius:50%;font-size:17px;cursor:pointer;flex:0 0 42px}'
  +'.bmc-typ{display:inline-flex;gap:3px;padding:12px 14px}.bmc-typ i{width:7px;height:7px;border-radius:50%;background:#c3b4a0;animation:bmct 1s infinite}.bmc-typ i:nth-child(2){animation-delay:.2s}.bmc-typ i:nth-child(3){animation-delay:.4s}@keyframes bmct{50%{transform:translateY(-4px);opacity:.5}}'
  +'@media(max-width:520px){.bmc-l{bottom:64px}.bmc-l .t{display:none}.bmc-p{bottom:0;right:0;height:100vh;max-height:100vh;width:100vw;max-width:100vw;border-radius:0}}';
  var st=document.createElement('style');st.textContent=css;document.head.appendChild(st);

  // knowledge base
  var KB=[
   {k:['査定','無料','いくら','高く','売','金額','見積'],a:'査定は完全無料です！スマホでかんたん入力（約1分）、最短即日で査定額をご提示します😊\n下のボタンから今すぐ無料査定できます。',cta:['form','tel'],c:['買取の流れ','料金は？']},
   {k:['廃車','事故','不動','動か','水没','古い','ボロ','故障','10年','過走行'],a:'もちろん買取OKです🚗\n不動車・事故車・廃車も自社レッカーで無料引取。買取金額0円以上保証、廃車手続きも無料代行、自動車税の還付金もお返しします。',cta:['form'],c:['必要な書類は？','買取の流れ','還付金について']},
   {k:['流れ','手順','ステップ','どうやって','方法','進め'],a:'かんたん4ステップです👇\n① 無料査定申込み\n② スタッフが査定\n③ ご成約・お支払い\n④ 引き取り・お引渡し\n最短即日で完了します！',cta:['form'],c:['必要な書類は？','料金は？']},
   {k:['書類','必要','用意','準備'],a:'ご契約時に必要なのは主に【車検証・印鑑（実印）】などです📄\n書類を紛失している場合もサポートしますのでご安心ください。',cta:['form'],c:['買取の流れ','電話したい']},
   {k:['エリア','地域','対応','全国','どこ','近く','店舗','出張'],a:'全国47都道府県に対応しています🗾\n出張・引取もすべて無料。地域別のご案内ページもございます。',cta:['area','form'],c:['料金は？','買取の流れ']},
   {k:['料金','費用','手数料','タダ','無料','かかる','お金'],a:'査定・出張・引取はすべて0円💰\n手数料は一切いただきません。提示額からの減額もない明朗会計です。',cta:['form'],c:['買取の流れ','電話したい']},
   {k:['電話','問い合わせ','オペレ','担当','人','話し'],a:'お電話でもお気軽にご相談ください📞\n'+cfg.telDisp+'（受付 9:00–20:00／年中無休）',cta:['tel','form'],c:['買取の流れ']},
   {k:['ローン','残債','残り'],a:'ローンが残っているお車も、状況により対応可能です。まずはお気軽にご相談ください😊',cta:['form','tel'],c:['必要な書類は？']},
   {k:['還付','税金','自動車税','自賠責'],a:'前払いされた自動車税・自賠責保険の未経過分（還付金）を計算して、買取金額と合わせてお返しします💴',cta:['form'],c:['廃車について','買取の流れ']},
   {k:['時間','営業','受付','何時','いつ'],a:'受付は9:00–20:00、年中無休です⏰\nオンラインの無料査定申込みは24時間いつでもOKです！',cta:['form','tel'],c:['買取の流れ']}
  ];
  var GREET='こんにちは！BUYMOのカーメルです🐪\nクルマの買取について、何でもお気軽に聞いてくださいね！';
  var INIT=['無料査定したい','廃車・事故車は？','買取の流れ','対応エリア','料金は？','電話したい'];
  var FB={a:'ありがとうございます！詳しくは担当が丁寧にご案内します😊\nまずは無料査定（約1分）か、お電話がスムーズです。',cta:['form','tel'],c:INIT.slice(0,3)};

  function find(t){t=(t||'').toLowerCase();for(var i=0;i<KB.length;i++){for(var j=0;j<KB[i].k.length;j++){if(t.indexOf(KB[i].k[j].toLowerCase())>=0)return KB[i]}}return FB}

  // build UI
  var L=document.createElement('div');L.className='bmc-l';
  L.innerHTML='<div class="t">査定の相談はこちら💬</div><div class="b"><img src="'+cfg.avatar+'" alt="BUYMOチャット"></div>';
  var P=document.createElement('div');P.className='bmc-p';
  P.innerHTML='<div class="bmc-h"><div class="av"><img src="'+cfg.avatar+'"></div><div><b>BUYMO AIサポート</b><small>カーメルが自動でお答えします</small></div><button class="x" aria-label="閉じる">×</button></div>'
   +'<div class="bmc-body" id="bmcBody"></div>'
   +'<div class="bmc-foot"><input id="bmcIn" placeholder="メッセージを入力…" autocomplete="off"><button id="bmcSend" aria-label="送信">➤</button></div>';
  document.body.appendChild(L);document.body.appendChild(P);
  var body=P.querySelector('#bmcBody'),inp=P.querySelector('#bmcIn');

  function esc(x){return x.replace(/&/g,'&amp;').replace(/</g,'&lt;')}
  function row(who,html){var r=document.createElement('div');r.className='bmc-row '+who;
    r.innerHTML=(who=='a'?'<div class="bmc-av"><img src="'+cfg.avatar+'"></div>':'')+'<div class="bmc-msg">'+html+'</div>';
    body.appendChild(r);body.scrollTop=body.scrollHeight;return r}
  function ctas(list){if(!list||!list.length)return;var d=document.createElement('div');d.className='bmc-cta';var m={
     form:'<a class="f" href="'+cfg.form+'">無料査定する ›</a>',
     tel:'<a class="t" href="tel:'+cfg.tel+'">📞 電話する</a>',
     area:'<a class="ar" href="'+cfg.area+'">対応エリアを見る ›</a>'};
    d.innerHTML=list.map(function(x){return m[x]||''}).join('');body.appendChild(d);body.scrollTop=body.scrollHeight}
  function chips(list){if(!list||!list.length)return;var d=document.createElement('div');d.className='bmc-chips';
    list.forEach(function(lb){var b=document.createElement('button');b.textContent=lb;b.onclick=function(){ask(lb)};d.appendChild(b)});
    body.appendChild(d);body.scrollTop=body.scrollHeight}
  function typing(cb){var r=document.createElement('div');r.className='bmc-row a';
    r.innerHTML='<div class="bmc-av"><img src="'+cfg.avatar+'"></div><div class="bmc-msg"><span class="bmc-typ"><i></i><i></i><i></i></span></div>';
    body.appendChild(r);body.scrollTop=body.scrollHeight;setTimeout(function(){body.removeChild(r);cb()},650)}
  function answer(t){typing(function(){var e=find(t);row('a',esc(e.a));ctas(e.cta);chips(e.c)})}
  function ask(t){row('u',esc(t));answer(t)}

  var started=false;
  function start(){if(started)return;started=true;typing(function(){row('a',esc(GREET));chips(INIT)})}
  function open(){P.classList.add('open');L.style.display='none';start();setTimeout(function(){inp.focus()},100)}
  function close(){P.classList.remove('open');L.style.display='flex'}
  L.onclick=open;P.querySelector('.x').onclick=close;
  function send(){var v=inp.value.trim();if(!v)return;inp.value='';ask(v)}
  P.querySelector('#bmcSend').onclick=send;
  inp.addEventListener('keydown',function(e){if(e.key==='Enter')send()});
})();
