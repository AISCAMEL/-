/**
 * embed.js — WordPress等にチャットウィジェットを1行で設置するローダー
 * ------------------------------------------------------------------
 * 使い方（WordPressのフッターやカスタムHTMLに貼り付け）:
 *   <script src="https://YOUR-HOST/assets/embed.js"
 *           data-api-base="https://YOUR-HOST"
 *           data-line-url="https://lin.ee/xxxx"
 *           data-tel="050-1793-5554"></script>
 *
 * - data-api-base: /api/* を提供するNodeホスト（省略時はこのスクリプトの配信元）。
 * - data-line-url / data-tel: 導線リンクの上書き（任意）。
 *
 * このローダーは、widgetのCSSとHTMLを注入し、設定を window.CARMEL_CHAT に渡して
 * ES Module 版のチャット初期化(embed-entry.js)を動的importする。
 */
(function () {
  'use strict';

  if (window.__carmelChatLoaded) return; // 多重読み込みガード
  window.__carmelChatLoaded = true;

  var script =
    document.currentScript ||
    (function () {
      var s = document.getElementsByTagName('script');
      return s[s.length - 1];
    })();

  // 配信元(オリジン+ディレクトリ)を解決。embed.js は {host}/assets/embed.js を想定。
  var src = script.src;
  var assetsBase = src.replace(/embed\.js(?:\?.*)?$/, ''); // .../assets/
  var hostBase = assetsBase.replace(/assets\/$/, ''); // .../
  var data = script.dataset || {};
  var apiBase = (data.apiBase || hostBase).replace(/\/$/, '');

  // 設定をグローバルに渡す（config.js が参照）
  window.CARMEL_CHAT = {
    apiBase: apiBase,
    lineUrl: data.lineUrl || undefined,
    telUrl: data.tel ? 'tel:' + data.tel : undefined,
    telDisplay: data.tel || undefined
  };

  // 1) CSS を注入
  var link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = assetsBase + 'css/embed.css';
  document.head.appendChild(link);

  // 2) ウィジェットHTMLを注入（既に存在すれば二重注入しない）
  if (!document.getElementById('chat-layer')) {
    var avatar = assetsBase + 'img/widget/avatar.svg';
    var wrap = document.createElement('div');
    wrap.innerHTML = [
      '<div class="chat-layer" id="chat-layer" data-module="lp-chat">',
      '  <aside class="chat-popup" id="chat-popup" role="dialog" aria-modal="false" aria-labelledby="chat-popup-title" aria-hidden="true" data-role="chat-popup">',
      '    <header class="chat-popup__header">',
      '      <img src="' + avatar + '" alt="" class="chat-popup__avatar" />',
      '      <div class="chat-popup__heading">',
      '        <h2 class="chat-popup__title" id="chat-popup-title">カーメル相談AI</h2>',
      '        <p class="chat-popup__status"><span class="chat-popup__dot" aria-hidden="true"></span>オンライン・無料相談</p>',
      '      </div>',
      '      <button class="chat-popup__close" type="button" aria-label="相談ポップアップを閉じる" data-action="close-popup">×</button>',
      '    </header>',
      '    <div class="chat-thread" data-role="chat-thread" aria-live="polite" aria-label="相談チャット"></div>',
      '    <div class="chat-suggestions" data-role="chat-suggestions"></div>',
      '    <form class="chat-input" data-role="chat-form" autocomplete="off">',
      '      <label class="u-visually-hidden" for="chat-text">相談内容を入力</label>',
      '      <textarea id="chat-text" class="chat-input__field" data-role="chat-text" rows="1" placeholder="メッセージを入力…"></textarea>',
      '      <button class="chat-input__send" type="submit" data-action="chat-send" aria-label="送信">➤</button>',
      '    </form>',
      '    <div class="chat-popup__escalation">',
      '      <a href="#" class="btn btn--line" data-action="cta-line" data-cta="line">💬 LINEで相談</a>',
      '      <a href="#" class="btn btn--tel" data-action="cta-tel" data-cta="tel">📞 電話で相談</a>',
      '    </div>',
      '  </aside>',
      '  <aside class="auto-popup" id="auto-popup" role="status" aria-live="polite" aria-hidden="true" data-role="auto-popup">',
      '    <p class="auto-popup__message">👋 ローンのお悩みありますか？今すぐ相談できます！</p>',
      '    <button class="auto-popup__close" type="button" aria-label="自動ポップアップを閉じる" data-action="close-auto-popup">×</button>',
      '  </aside>',
      '  <button class="chat-widget" id="chat-widget" type="button" aria-label="相談チャットを開く" aria-expanded="false" aria-controls="chat-popup" data-role="chat-widget" data-action="toggle-popup">',
      '    <img src="' + avatar + '" alt="" class="chat-widget__avatar" />',
      '    <span class="chat-widget__badge" aria-label="未読1件">1</span>',
      '  </button>',
      '</div>'
    ].join('');
    document.body.appendChild(wrap.firstChild);
  }

  // 導線リンクを設定で上書き
  if (window.CARMEL_CHAT.lineUrl) {
    document.querySelectorAll('#chat-layer [data-cta="line"]').forEach(function (a) {
      a.setAttribute('href', window.CARMEL_CHAT.lineUrl);
    });
  }
  if (window.CARMEL_CHAT.telUrl) {
    document.querySelectorAll('#chat-layer [data-cta="tel"]').forEach(function (a) {
      a.setAttribute('href', window.CARMEL_CHAT.telUrl);
    });
  }

  // 3) チャット初期化（ES Module）を動的import
  function start() {
    import(assetsBase + 'js/embed-entry.js').catch(function (e) {
      // eslint-disable-next-line no-console
      console.error('[carmel-chat] failed to load chat module:', e);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
