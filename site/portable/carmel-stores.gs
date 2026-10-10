// ============================================================
//  CARMEL 店舗マスター（移植用・共有モジュール）  v1
//  ------------------------------------------------------------
//  ★このファイル1つを、別プロジェクトのGASにコピペすれば
//    「店舗の認識・振り分け」が使えるようになります。
//
//  使い方：
//    var st = resolveStore_(payload.meta.storeKey || payload.meta.store);
//    // → st.label / st.tel / st.hours / st.emails[] / st.lineUrl /
//    //    st.replyFromName / st.site / st.slackWebhook /
//    //    st.asanaProjectId / st.asanaSectionId / st.asanaGuarantorSectionId
//
//  店舗を増やすとき：STORES に1ブロック足すだけ（他は触らない）
//  ※Asanaを使わない作業なら asana* は空 '' でOK。
//  ※LINE・メール等も、その作業で使う項目だけ埋めればOK。
// ============================================================

var STORES = {
  fukushima: {
    label: 'カーメル福島本店', short: '福島本店', tel: '050-1793-5554', hours: '10:00〜18:00',
    emails: ['carmelbuzzzzz@aisjaltd.com'],
    replyFromName: 'カーメル 福島本店', site: 'https://carmelonline.jp/', lineUrl: 'https://lin.ee/y4QcSnq',
    slackWebhook: '', asanaProjectId: '1212957542191186', asanaSectionId: '1212957542191187', asanaGuarantorSectionId: '1213077965542210'
  },
  chiba: {
    label: 'カーメル千葉店', short: '千葉店', tel: '050-5236-2588', hours: '9:00〜20:00',
    emails: ['chiba@carmelonline.jp', 'carmelbuzzzzz@aisjaltd.com'],
    replyFromName: 'カーメル 千葉店', site: 'https://chiba.carmelonline.jp/', lineUrl: 'https://lin.ee/y4QcSnq',
    slackWebhook: '', asanaProjectId: '1213077965542227', asanaSectionId: '1213077965542228', asanaGuarantorSectionId: '1213078356866757'
  },
  odawara: {
    label: 'カーメル小田原店', short: '小田原店', tel: '0465-20-4286', hours: '10:00〜20:00',
    emails: ['odawara@carmelonline.jp', 'carmelbuzzzzz@aisjaltd.com'],
    replyFromName: 'カーメル 小田原店', site: 'https://odawara.carmelonline.jp/', lineUrl: 'https://lin.ee/x5Ne4jf',
    slackWebhook: '', asanaProjectId: '1213077965542236', asanaSectionId: '1213077965542237', asanaGuarantorSectionId: '1213078354859839'
  },
  yamanashi: {
    label: 'カーメル山梨店', short: '山梨店', tel: '080-7566-2556', hours: '9:30〜18:30',
    emails: ['yamanashi@carmelonline.jp', 'carmelbuzzzzz@aisjaltd.com'],
    replyFromName: 'カーメル 山梨店', site: 'https://yamanashi.carmelonline.jp/', lineUrl: 'https://lin.ee/Y1nymrL',
    slackWebhook: '', asanaProjectId: '1213077965542217', asanaSectionId: '1213077965542218', asanaGuarantorSectionId: '1213078349498750'
  }

  // ▼ 新しい加盟店はここに足す（カンマ区切り）。例：
  // ,sendai: {
  //   label: 'カーメル仙台店', short: '仙台店', tel: '022-000-0000', hours: '10:00〜19:00',
  //   emails: ['sendai@carmelonline.jp', 'carmelbuzzzzz@aisjaltd.com'],
  //   replyFromName: 'カーメル 仙台店', site: 'https://sendai.carmelonline.jp/', lineUrl: 'https://lin.ee/XXXXXXX',
  //   slackWebhook: '', asanaProjectId: '', asanaSectionId: '', asanaGuarantorSectionId: ''
  // }
};

var DEFAULT_STORE_KEY = 'fukushima'; // store未指定のときの既定店舗

// キー（例: 'yamanashi'）または店舗名（例: 'カーメル山梨店'）から店舗情報を取得。
// 見つからなければ既定店舗を返す（必ず何か返る）。
function resolveStore_(key) {
  var k = String(key || '').toLowerCase().trim();
  // 店舗名（label）で来た場合もキーに変換して解決
  if (!STORES[k]) {
    for (var kk in STORES) { if (STORES[kk].label === key) { k = kk; break; } }
  }
  var base = STORES[k] ? STORES[k] : STORES[DEFAULT_STORE_KEY];
  var out = { key: (STORES[k] ? k : DEFAULT_STORE_KEY) };
  Object.keys(base).forEach(function(p){ out[p] = base[p]; });
  return out;
}

// 便利：全店舗キーの配列（例：フォームのプルダウン生成などに）
function STORE_KEYS_() { return Object.keys(STORES); }

// 便利：店舗ごとの通知先メール（配列）。未設定なら第2引数をフォールバックに。
function storeEmails_(st, fallbackEmail) {
  if (st && st.emails && st.emails.length) return st.emails.join(',');
  return fallbackEmail || '';
}
