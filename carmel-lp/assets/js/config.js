/**
 * config.js
 * ------------------------------------------------------------------
 * 追加チャットウィジェットの設定値を一元管理する。
 * 文言・リンク先・表示秒数・セッションキーはすべてここに集約し、
 * 仕様変更時は原則このファイルだけを差し替えれば済む構造とする。
 * (仕様定義書 第2部 10. 実装上の設定値一覧 / 16. 変更容易性の設計方針)
 */

export const CHAT_CONFIG = {
  // ---- 主要導線（要確認: 本番値であること） ----
  lineUrl: 'https://lin.ee/u2tox5s',
  telUrl: 'tel:050-1793-5554',
  telDisplay: '050-1793-5554',

  // ---- 自動ポップアップ ----
  autoPopupDelay: 3000, // ページロード後の表示までの待機(ms)
  autoPopupMessage: '👋 ローンのお悩みありますか？今すぐ相談できます！',
  autoPopupOncePerSession: true, // セッション内1回のみ表示（推奨）

  // ---- 手動ポップアップ文言 ----
  popupTitle: 'カーメルスタッフがお待ちしています！',
  popupMessage: 'ローンのお悩み、何でもご相談ください。LINEで今すぐ無料相談できます！',
  popupLineLabel: '💬 LINEで無料相談する',
  popupTelLabel: '📞 電話で相談する',

  // ---- ウィジェット ----
  badgeText: '1',
  showBadge: true,
  avatarSrc: 'assets/img/widget/avatar.svg',
  avatarFallbackText: 'C', // 画像取得失敗時の代替文字

  // ---- visibilitychange 時のタイマー方針 ----
  // 'continue' | 'pause-resume'（バックグラウンド移行時の挙動。要確認）
  timerVisibilityPolicy: 'pause-resume',

  // ---- 計測（GA4/GTM命名規則が別途ある場合は要確認） ----
  events: {
    widgetImpression: 'chat_widget_impression',
    autoPopupImpression: 'auto_popup_impression',
    autoPopupClose: 'auto_popup_close',
    widgetClick: 'chat_widget_click',
    popupOpen: 'chat_popup_open',
    popupClose: 'chat_popup_close',
    ctaLineClick: 'cta_line_click',
    ctaTelClick: 'cta_tel_click'
  },

  // ---- セッション管理キー ----
  sessionKeys: {
    autoShown: 'carmel_lp_auto_popup_shown',
    autoDismissed: 'carmel_lp_auto_popup_dismissed'
  },

  // デバッグ時 true で計測内容を console に出力
  debug: false,

  // ---- AIチャットボット (OpenRouter 経由) ----
  // APIキーはサーバー側プロキシ(/api/chat)に保持。クライアントには置かない。
  chatbot: {
    enabled: true,
    endpoint: '/api/chat', // サーバープロキシのエンドポイント
    greeting:
      'こんにちは！カーメル相談AIです🚗\n自社ローン・信用回復ローンのご相談を承ります。審査の不安や手続きなど、お気軽にどうぞ。',
    // 初期サジェスト（タップで質問送信）
    suggestions: [
      '過去に滞納がありますが相談できますか？',
      '頭金がなくても大丈夫ですか？',
      '審査の流れを教えてください',
      'どんな車種を扱っていますか？'
    ],
    // 会話履歴の最大保持件数（user/assistant 合計）
    maxHistory: 12,
    // AIが応答できない/エラー時に表示するフォールバック文言
    fallbackMessage:
      'うまくお答えできませんでした。LINEまたはお電話で、担当スタッフが直接ご相談を承ります。',
    // 計測イベント名
    events: {
      chatStart: 'chatbot_start',
      messageSent: 'chatbot_message_sent',
      responseReceived: 'chatbot_response',
      error: 'chatbot_error',
      suggestionClick: 'chatbot_suggestion_click'
    }
  }
};

export default CHAT_CONFIG;
