/**
 * AIスタジオとデモアプリで共通のデザインデータ（業種・色・雰囲気・機能）。
 */
(function(){
'use strict';
var IND={
  food:{l:'飲食店',name:'炭火焼肉 はなび',catch:'ご来店ごとにポイントが貯まる',menus:['ネット予約','お品書き','クーポン','スタンプ','お知らせ','アクセス'],items:[['上カルビ','1,480円'],['ハラミ','1,280円'],['厚切りタン塩','1,380円'],['石焼ビビンバ','980円']],cps:[['ドリンク1杯無料','アプリ会員限定'],['お会計10%OFF','平日17時まで']],feat:['push','news','coupon','stamp','reserve','menu','access']},
  beauty:{l:'美容サロン',name:'hair salon Luce',catch:'次回のご予約もアプリでかんたん',menus:['予約','メニュー・料金','スタイル','クーポン','会員証','スタッフ'],items:[['カット','4,400円'],['カット＋カラー','9,900円'],['髪質改善トリートメント','6,600円'],['ヘッドスパ','3,300円']],cps:[['初回カット20%OFF','ご新規様限定'],['トリートメント無料','カラーと同時で']],feat:['push','news','coupon','card','reserve','menu']},
  golf:{l:'ゴルフ・スポーツ',name:'SWING BASE',catch:'打席予約もポイントもこれひとつ',menus:['打席予約','料金プラン','コンペ案内','レッスン','ポイント','お知らせ'],items:[['打席60分','1,650円'],['月額会員（平日）','8,800円'],['個人レッスン30分','3,300円'],['スイング診断','2,200円']],cps:[['平日昼 30分無料','11時〜15時'],['友達紹介で1,000pt','紹介した方・された方']],feat:['push','news','coupon','stamp','reserve','card','gps']},
  clinic:{l:'クリニック',name:'さくら内科',catch:'診療予約と順番待ちをスマホで',menus:['診療予約','順番待ち','診療時間','お知らせ','Web問診','アクセス'],items:[['一般内科','保険診療'],['健康診断','5,500円〜'],['インフルエンザ予防接種','3,500円'],['オンライン診療','保険診療']],cps:[['待ち時間をお知らせ','順番が近づくと通知'],['健診の予約受付中','平日午後']],feat:['push','news','reserve','card','access','contact']},
  school:{l:'スクール・教室',name:'こども英語 Sunny',catch:'レッスン予約とお知らせを1つに',menus:['レッスン予約','コース案内','講師紹介','お知らせ','欠席連絡','会員証'],items:[['キッズ英会話（週1）','8,800円/月'],['フォニックス','6,600円/月'],['英検対策','9,900円/月'],['体験レッスン','無料']],cps:[['体験レッスン無料','初めての方'],['兄弟割 10%OFF','2人目から']],feat:['push','news','reserve','card','contact','video']},
  retail:{l:'小売・EC',name:'Select Shop MIKAN',catch:'会員限定セールをいち早く',menus:['オンラインストア','クーポン','会員証','新商品','ポイント','お知らせ'],items:[['リネンシャツ','8,800円'],['レザートート','16,500円'],['ソイキャンドル','2,750円'],['ギフトBOX','3,300円']],cps:[['会員限定500円OFF','5,000円以上'],['誕生月ポイント2倍','誕生月のみ']],feat:['push','news','coupon','stamp','card','ec','menu']}
};
var COLORS=[['ネイビー','#13306a','#2f66f5'],['ボルドー','#4a0d18','#c0392b'],['フォレスト','#0f3d2e','#1f9d6b'],['くすみピンク','#7a2350','#e0679a'],['ブラック×ゴールド','#111111','#b8912a'],['オレンジ','#7a2e00','#ff7a1a']];
var TONES={luxury:{n:'高級感',ff:"'Zen Old Mincho',serif",r:6},pop:{n:'かわいい・ポップ',ff:"'M PLUS Rounded 1c',sans-serif",r:20},natural:{n:'ナチュラル',ff:"'Zen Kaku Gothic New',sans-serif",r:14},sporty:{n:'スポーティ',ff:"'Outfit','Zen Kaku Gothic New',sans-serif",r:8},trust:{n:'信頼感',ff:"'Noto Sans JP',sans-serif",r:10}};
var SCREENS=[['home','ホーム'],['menu','メニュー'],['coupon','クーポン'],['reserve','予約'],['card','会員証']];
var FEAT=[
  {k:'push',n:'プッシュ通知',d:'回数無制限',t:'std'},{k:'news',n:'お知らせ',d:'画像つきで配信',t:'std'},{k:'coupon',n:'クーポン',d:'期間・回数を設定',t:'std'},
  {k:'stamp',n:'スタンプ・ポイント',d:'来店で貯まる',t:'std'},{k:'card',n:'デジタル会員証',d:'バーコード表示',t:'std'},{k:'reserve',n:'予約',d:'標準の予約機能',t:'std'},
  {k:'menu',n:'メニュー・商品一覧',d:'写真と価格',t:'std'},{k:'access',n:'アクセス・地図',d:'',t:'std'},{k:'contact',n:'お問い合わせ',d:'',t:'std'},
  {k:'catalog',n:'電子カタログ',d:'スタートプラン以上',t:'std',tier:'start'},{k:'ec',n:'EC・モバイルオーダー',d:'ビジネスプラン',t:'std',tier:'biz'},{k:'multi',n:'多店舗対応',d:'ビジネスプラン',t:'std',tier:'biz'},
  {k:'gps',n:'位置情報で通知（1カ所）',d:'オプション 20,000円',t:'opt',p:20000},{k:'chatf',n:'チャット機能',d:'オプション 300,000円',t:'opt',p:300000},
  {k:'match',n:'マッチング（利用者どうし）',d:'オリジナル開発',t:'orig'},{k:'pay',n:'アプリ内決済・サブスク',d:'オリジナル開発',t:'orig'},{k:'pos',n:'POS・在庫システム連携',d:'オリジナル開発',t:'orig'},{k:'video',n:'動画配信・会員限定コンテンツ',d:'オリジナル開発',t:'orig'}
];
/*
 * アプリの基盤（APPREX管理画面）で使えるページの種類。
 * [キー, 管理画面での名前, グループ, デモの画面タイプ, 概算の機能キー, 管理画面での作り方, 準備するもの]
 */
var PAGE_LIST=[
  ['homepage','HP','basic','web','','ページ作成 → HP','ホームページのURL'],
  ['company','会社概要','basic','page','','ページ作成 → 会社概要','会社名・所在地・設立・事業内容'],
  ['ceo','代表挨拶','basic','page','','ページ作成 → 代表挨拶','代表者の写真・あいさつ文'],
  ['employee','スタッフ','basic','staff','','ページ作成 → スタッフ','スタッフの写真・名前・紹介文'],
  ['philo','企業理念','basic','page','','ページ作成 → 企業理念','理念・大切にしていること'],
  ['intro','事業紹介','basic','page','','ページ作成 → 事業紹介','事業・サービスの説明と写真'],
  ['fee','料金','basic','menu','menu','ページ作成 → 料金','料金表（項目・金額）'],
  ['pedi','沿革','basic','history','','ページ作成 → 沿革','年表（年・出来事）'],
  ['store','店舗情報','basic','page','','ページ作成 → 店舗情報','営業時間・定休日・設備'],
  ['business','営業所','basic','page','','ページ作成 → 営業所','営業所の名前・住所・電話'],
  ['access','アクセス','basic','access','access','ページ作成 → アクセス（最大5か所）','住所・地図・行き方'],
  ['newcoupon','NEWクーポン','promo','coupon','coupon','ページ作成 → NEWクーポン','クーポンの内容・期間・利用回数'],
  ['stamp','スタンプ','promo','card','stamp','ページ作成 → スタンプ','スタンプの数と特典'],
  ['stamprally','スタンプラリー','promo','rally','stamp','ページ作成 → スタンプラリー','対象スポットと特典'],
  ['point','POINT','promo','card','stamp','ページ作成 → POINT','ポイントの付け方と交換特典'],
  ['membership','会員証','promo','card','card','会員機能 → 会員証','会員証のデザイン・ランク'],
  ['sns','友達紹介','promo','friend','','ページ作成 → 友達紹介','紹介した人・された人の特典'],
  ['registration','会員登録','promo','form','card','会員機能 → 会員登録','登録してもらう項目'],
  ['survey','アンケート','promo','form','','アンケート','質問と回答の選択肢'],
  ['reser','予約','sales','reserve','reserve','予約管理','予約できるメニュー・時間・枠数'],
  ['shop','SHOP','sales','shop','ec','ページ作成 → SHOP','商品の写真・名前・価格'],
  ['shopping_mall','ネットショップ','sales','shop','ec','ページ作成 → ネットショップ','ネットショップのURL・商品'],
  ['cart','買い物カゴ','sales','shop','ec','ページ作成 → 買い物カゴ','（SHOPと一緒に使います）'],
  ['takeout','テイクアウト','sales','menu','ec','ページ作成 → テイクアウト','テイクアウトの商品・受け取り時間'],
  ['application','申し込みフォーム','sales','form','contact','ページ作成 → 申し込みフォーム','申し込みの項目'],
  ['bookmark','お気に入り','sales','fav','','ページ作成 → お気に入り','（商品・記事をお気に入り登録）'],
  ['imgMenu','メニュー','content','menu','menu','ページ作成 → メニュー','メニューの写真・名前・価格'],
  ['news','ニュース','content','news','news','ページ作成 → ニュース','お知らせの記事'],
  ['blog','BLOG','content','news','news','ページ作成 → BLOG','ブログ記事'],
  ['movie','動画','content','movie','','ページ作成 → 動画','YouTubeなどの動画URL'],
  ['gallery','フォトギャラリー','content','gallery','','ページ作成 → フォトギャラリー','写真（10〜30枚）'],
  ['catalog','カタログ','content','catalog','catalog','カタログ → カタログ制作','カタログのPDF・画像'],
  ['html','HTMLページ','content','page','','ページ作成 → HTML1〜10','自由に作るページの内容'],
  ['webview','ウェブビュー','content','web','','ページ作成 → ウェブビュー1〜6','表示したいWebページのURL'],
  ['tel','電話','contact','tel','','ページ作成 → 電話','電話番号'],
  ['email','メール','contact','mail','contact','ページ作成 → メール','受け付けるメールアドレス'],
  ['inquiry','お問合せ','contact','form','contact','ページ作成 → お問合せ','問い合わせの項目・通知先'],
  ['instagram','INSTAGRAM','contact','sns','','ページ作成 → INSTAGRAM','InstagramのURL'],
  ['facebook','FACEBOOK','contact','sns','','ページ作成 → FACEBOOK','FacebookのURL'],
  ['twitter','X（旧Twitter）','contact','sns','','ページ作成 → TWITTER','XのURL'],
  ['chat','チャット','contact','chat','chatf','チャット','（お客様と個別にやり取り）'],
  ['attendance','出勤一覧','biz','schedule','','出勤一覧','スタッフの出勤予定'],
  ['schedule','週間スケジュール','biz','schedule','','週間スケジュール','1週間の予定・レッスン枠'],
  ['skill','スキルマーケット','biz','skill','match','スキルマーケット','提供するスキル・価格'],
  ['location','位置情報','biz','info','gps','位置情報','通知するエリア']
];
var GROUPS={basic:'基本ページ',promo:'集客・販促',sales:'予約・販売',content:'コンテンツ',contact:'連絡・SNS',biz:'業務・その他'};
var PAGES={}; PAGE_LIST.forEach(function(p){ PAGES[p[0]]={k:p[0],l:p[1],g:p[2],type:p[3],feat:p[4],admin:p[5],prep:p[6]}; });
/* 業種ごとのおすすめ構成（フッター横メニュー4つ・縦メニュー）。'home' はホーム。 */
var PRESET={
  food:{footer:['home','reser','newcoupon','membership'],menu:['reser','imgMenu','newcoupon','stamp','news','access','membership','takeout','gallery','tel','instagram']},
  beauty:{footer:['home','reser','newcoupon','membership'],menu:['reser','fee','gallery','newcoupon','membership','employee','news','access','instagram']},
  golf:{footer:['home','reser','point','news'],menu:['reser','fee','news','schedule','point','access','movie','inquiry']},
  clinic:{footer:['home','reser','news','access'],menu:['reser','store','news','application','access','tel','company']},
  school:{footer:['home','reser','news','membership'],menu:['reser','fee','employee','news','application','membership','movie','access']},
  retail:{footer:['home','shopping_mall','newcoupon','membership'],menu:['shopping_mall','newcoupon','membership','catalog','point','news','store','instagram']}
};
/* 月額プラン（ノーコード）。上のプランは下のプランの機能をすべて含む。add＝選んだときにメニューへ足すページ。 */
var PLANS={
  trial:{l:'トライアル',m:19800,days:'最短2週間',fit:'まず小さく始めたい',add:[]},
  start:{l:'スタート',m:39800,days:'最短2週間',fit:'カタログ・クーポンで集客',add:['catalog']},
  biz:{l:'ビジネス',m:59800,days:'最短1ヶ月',fit:'多店舗・ECまで',add:['shopping_mall','business'],names:{business:'店舗一覧'}}
};
var PLAN_ORDER=['trial','start','biz'];
/* 機能・ページに必要なもの：'trial'|'start'|'biz'（プラン）、'opt'（オプション）、'orig'（オリジナル開発）。 */
function featNeed(fk){ if(!fk) return 'trial'; for(var i=0;i<FEAT.length;i++){ var f=FEAT[i]; if(f.k===fk){ return f.t==='std'?(f.tier||'trial'):f.t; } } return 'trial'; }
function pageNeed(k){ return PAGES[k]?featNeed(PAGES[k].feat):'trial'; }
/* そのプランで追加料金なしに使えるか。 */
function inPlan(need,tier){ var a=PLAN_ORDER.indexOf(need); return a>-1&&a<=PLAN_ORDER.indexOf(tier||'trial'); }
function defaultApp(ind,tier){ var p=PRESET[ind]||PRESET.food, t=tier||'trial', ok=function(k){return k==='home'||inPlan(pageNeed(k),t);};
  var menu=p.menu.filter(ok);
  return {footer:fixFooter(p.footer,menu),menu:menu,names:{}}; }
/* フッター4つ：縦メニューにないページは、まだフッターにない縦メニューのページに入れ替える（ホームは1つだけ）。 */
function fixFooter(foot,menu){ var out=[]; foot.forEach(function(k,i){ if(i===0&&k==='home'){ out.push(k); return; } if(k!=='home'&&menu.indexOf(k)>-1&&out.indexOf(k)<0){ out.push(k); return; } var r=menu.filter(function(m){return out.indexOf(m)<0&&foot.indexOf(m)<0;})[0]; out.push(r||'home'); }); return out; }
/* 表示名（会員が付けた名前 → 管理画面の名前）。 */
function pageName(app,k){ if(k==='home') return 'ホーム'; return (app&&app.names&&app.names[k])||(PAGES[k]?PAGES[k].l:k); }
window.APPREX_STUDIO_DATA={IND:IND,COLORS:COLORS,TONES:TONES,SCREENS:SCREENS,FEAT:FEAT,PAGES:PAGES,PAGE_LIST:PAGE_LIST,GROUPS:GROUPS,PRESET:PRESET,PLANS:PLANS,PLAN_ORDER:PLAN_ORDER,featNeed:featNeed,pageNeed:pageNeed,inPlan:inPlan,fixFooter:fixFooter,defaultApp:defaultApp,pageName:pageName};
})();
