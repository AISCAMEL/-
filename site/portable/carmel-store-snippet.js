/* ============================================================
 *  CARMEL 店舗スニペット（移植用・フロント）  v1
 *  ------------------------------------------------------------
 *  ★どのフォーム（HTML）にも貼れます。これだけで：
 *    1) URL の ?store=xxx を読み取り
 *    2) window.CARMEL_STORE = {key,label,tel,hours} を作り
 *    3) <div id="store-badge"> があれば店舗バッジを表示
 *
 *  使い方：
 *    (A) フォームのどこかに  <div id="store-badge"></div> を置く（任意）
 *    (B) このファイルを <script src="carmel-store-snippet.js"></script> で読み込む
 *        （または中身を <script> ... </script> で貼り付け）
 *    (C) フォーム送信時に、サーバー(GAS)へ送る payload に店舗を入れる：
 *          payload.meta.store    = (window.CARMEL_STORE||{}).label || '';
 *          payload.meta.storeKey = (window.CARMEL_STORE||{}).key   || '';
 *        → GAS 側は resolveStore_(meta.storeKey || meta.store) で解決できる
 *
 *  店舗を増やすとき：下の STORES に1行足すだけ（GAS 側の store キーと合わせる）
 * ============================================================ */
(function () {
  // 画面表示用の最小情報（通知先やAsana等はGAS側のマスターが持つ）
  var STORES = {
    fukushima: { label: 'カーメル福島本店', short: '福島本店', tel: '050-1793-5554', hours: '10:00〜18:00' },
    chiba:     { label: 'カーメル千葉店',   short: '千葉店',   tel: '050-5236-2588', hours: '9:00〜20:00' },
    odawara:   { label: 'カーメル小田原店', short: '小田原店', tel: '0465-20-4286',  hours: '10:00〜20:00' },
    yamanashi: { label: 'カーメル山梨店',   short: '山梨店',   tel: '080-7566-2556', hours: '9:30〜18:30' }
    // ,sendai: { label:'カーメル仙台店', short:'仙台店', tel:'022-000-0000', hours:'10:00〜19:00' }
  };
  var DEFAULT = 'fukushima';

  // ブランド色（オレンジ）。他ブランドへ移植する時はここだけ変える
  var BRAND = '#f47920', NAVY = '#1b2935', SUB = '#64748b';

  var q = new URLSearchParams(location.search);
  var k = (q.get('store') || q.get('s') || '').toLowerCase().trim();
  var key = STORES[k] ? k : DEFAULT;
  var st = STORES[key];

  // どのフォームからでも参照できるグローバル
  window.CARMEL_STORE = { key: key, label: st.label, short: st.short, tel: st.tel, hours: st.hours };

  // 店舗バッジ（<div id="store-badge"> があれば描画）：案4ミニマル下線スタイル
  var b = document.getElementById('store-badge');
  if (b) {
    b.innerHTML =
      '<span style="display:inline-flex;flex-direction:column;align-items:center;gap:5px;">' +
        '<span style="display:inline-flex;align-items:center;gap:8px;">' +
          '<span style="width:9px;height:9px;border-radius:50%;background:' + BRAND + ';box-shadow:0 0 0 4px rgba(244,121,32,.18);flex-shrink:0;"></span>' +
          '<span style="font-weight:900;font-size:1.3rem;color:' + NAVY + ';padding-bottom:4px;border-bottom:3px solid ' + BRAND + ';">' + st.label + '</span>' +
        '</span>' +
        '<span style="font-size:.76rem;color:' + SUB + ';font-weight:600;">TEL ' + st.tel + '　/　' + st.hours + '</span>' +
      '</span>';
    b.style.display = '';
  }

  // 送信時に呼ぶと meta に店舗を差し込むヘルパー（任意）
  //   例：var meta = CARMEL_withStore({ name:..., email:... });
  window.CARMEL_withStore = function (meta) {
    meta = meta || {};
    meta.store = window.CARMEL_STORE.label;
    meta.storeKey = window.CARMEL_STORE.key;
    return meta;
  };
})();
