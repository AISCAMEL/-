/**
 * lp-chat.js
 * ------------------------------------------------------------------
 * 追加チャット導線ウィジェットの挙動を一元管理するモジュール。
 *
 * 担当範囲（責務）:
 *   - 状態管理（表示/非表示/展開/抑止）
 *   - 手動ポップアップの開閉
 *   - 3秒後自動ポップアップのタイマー制御
 *   - イベント処理（click/tap, keydown, resize, visibilitychange）
 *   - 計測連携
 *   - ARIA 属性の開閉同期
 *
 * 非責務:
 *   - 文書構造の定義（HTML 側）
 *   - 見た目（CSS 側）
 *
 * 本モジュールは LP 本体から完全に独立しており、chat-layer と
 * 関連 CSS/JS を取り除けば LP 本体は無傷で復旧できる（ロールバック容易性）。
 * (仕様定義書 第2部 1. 実装方針 / 8. JavaScript設計 / 9. 処理フロー / 11. 状態遷移表)
 */

import { CHAT_CONFIG } from './config.js';
import { track } from './analytics.js';

/** モジュールスコープの多重初期化ガード (8.5 多重起動防止) */
let initialized = false;

/** 内部状態 (8.2 状態管理) */
const state = {
  widgetVisible: true,
  popupOpen: false,
  autoPopupShown: false,
  autoPopupDismissed: false,
  sessionSuppressed: false,
  timerId: null,
  /** 一時停止時の残り時間(ms)。pause-resume ポリシーで使用 */
  timerRemaining: CHAT_CONFIG.autoPopupDelay,
  timerStartedAt: 0
};

/** DOM 参照キャッシュ */
const els = {
  layer: null,
  widget: null,
  popup: null,
  autoPopup: null
};

/* ============================================================
 * Storage（sessionStorage 不可環境でも落ちないようガード）
 * (8.6 Storage使用方針)
 * ========================================================== */

function safeSessionGet(key) {
  try {
    return window.sessionStorage.getItem(key);
  } catch (_e) {
    return null;
  }
}

function safeSessionSet(key, value) {
  try {
    window.sessionStorage.setItem(key, value);
  } catch (_e) {
    /* プライベートモード等。抑止制御が効かないだけで挙動は継続 */
  }
}

function readSessionState() {
  const { autoShown, autoDismissed } = CHAT_CONFIG.sessionKeys;
  return {
    autoPopupShown: safeSessionGet(autoShown) === 'true',
    autoPopupDismissed: safeSessionGet(autoDismissed) === 'true'
  };
}

/* ============================================================
 * DOM ヘルパ
 * ========================================================== */

function getElements() {
  return {
    layer: document.querySelector('[data-role="chat-widget"]')?.closest('.chat-layer')
      || document.getElementById('chat-layer'),
    widget: document.querySelector('[data-role="chat-widget"]'),
    popup: document.querySelector('[data-role="chat-popup"]'),
    autoPopup: document.querySelector('[data-role="auto-popup"]')
  };
}

/** 手動ポップアップの表示状態と ARIA を同期 (15. アクセシビリティ要件) */
function setManualPopupVisible(visible) {
  if (!els.popup || !els.widget) return;
  els.popup.classList.toggle('is-open', visible);
  els.popup.setAttribute('aria-hidden', visible ? 'false' : 'true');
  els.widget.setAttribute('aria-expanded', visible ? 'true' : 'false');
}

function setAutoPopupVisible(visible) {
  if (!els.autoPopup) return;
  els.autoPopup.classList.toggle('is-visible', visible);
  els.autoPopup.setAttribute('aria-hidden', visible ? 'false' : 'true');
}

/* ============================================================
 * 自動ポップアップ
 * ========================================================== */

function clearExistingTimer() {
  if (state.timerId !== null) {
    clearTimeout(state.timerId);
    state.timerId = null;
  }
}

function showAutoPopup() {
  // 手動ポップアップが開いている場合は抑止 (12.6 timer)
  if (state.popupOpen) return;
  if (state.autoPopupDismissed || state.sessionSuppressed) return;

  setAutoPopupVisible(true);
  state.autoPopupShown = true;
  if (CHAT_CONFIG.autoPopupOncePerSession) {
    safeSessionSet(CHAT_CONFIG.sessionKeys.autoShown, 'true');
  }
  track(CHAT_CONFIG.events.autoPopupImpression);
}

function startAutoPopupTimer(delay) {
  clearExistingTimer();
  state.timerRemaining = delay;
  state.timerStartedAt = Date.now();
  state.timerId = setTimeout(() => {
    state.timerId = null;
    showAutoPopup();
  }, delay);
}

function closeAutoPopup({ userDismissed = false } = {}) {
  if (!els.autoPopup) return;
  setAutoPopupVisible(false);
  if (userDismissed) {
    state.autoPopupDismissed = true;
    safeSessionSet(CHAT_CONFIG.sessionKeys.autoDismissed, 'true');
    track(CHAT_CONFIG.events.autoPopupClose);
  }
}

/* ============================================================
 * 手動ポップアップ
 * ========================================================== */

function openManualPopup() {
  // 開く際に残っている自動ポップアップタイマーは破棄 (8.4 タイマー管理)
  clearExistingTimer();
  closeAutoPopup();
  setManualPopupVisible(true);
  state.popupOpen = true;
  track(CHAT_CONFIG.events.popupOpen);

  // フォーカスを閉じるボタンへ移動（厳格モーダルではなく最小限） (6.3 フォーカス方針)
  els.popup?.querySelector('[data-action="close-popup"]')?.focus();
}

function closeManualPopup() {
  setManualPopupVisible(false);
  state.popupOpen = false;
  track(CHAT_CONFIG.events.popupClose);
  // 閉じたらウィジェットへフォーカスを戻す
  els.widget?.focus();
}

function toggleManualPopup() {
  track(CHAT_CONFIG.events.widgetClick);
  if (state.popupOpen) {
    closeManualPopup();
  } else {
    openManualPopup();
  }
}

/* ============================================================
 * イベント処理（chat-layer 単位でのイベント委譲） (8.3)
 * ========================================================== */

function onLayerClick(event) {
  const actionEl = event.target.closest('[data-action]');
  if (!actionEl || !els.layer.contains(actionEl)) return;

  switch (actionEl.dataset.action) {
    case 'toggle-popup':
      toggleManualPopup();
      break;
    case 'close-popup':
      closeManualPopup();
      break;
    case 'close-auto-popup':
      closeAutoPopup({ userDismissed: true });
      break;
    case 'cta-line':
      // 計測を優先しつつ通常遷移を阻害しない (12.2)
      track(CHAT_CONFIG.events.ctaLineClick);
      break;
    case 'cta-tel':
      track(CHAT_CONFIG.events.ctaTelClick);
      break;
    default:
      break;
  }
}

/** 外側クリックで手動ポップアップを閉じる (11. 状態遷移表 click outside) */
function onDocumentClick(event) {
  if (!state.popupOpen) return;
  const insidePopup = els.popup?.contains(event.target);
  const onWidget = els.widget?.contains(event.target);
  if (!insidePopup && !onWidget) {
    closeManualPopup();
  }
}

/** Esc で閉じる（PC操作時） (12.3 keydown) */
function onKeydown(event) {
  if (event.key === 'Escape' && state.popupOpen) {
    closeManualPopup();
  }
}

/** バックグラウンド移行時のタイマー制御 (12.5 visibilitychange) */
function onVisibilityChange() {
  if (CHAT_CONFIG.timerVisibilityPolicy !== 'pause-resume') return;
  if (state.autoPopupShown || state.autoPopupDismissed) return;

  if (document.hidden) {
    // 残り時間を退避してタイマー停止
    if (state.timerId !== null) {
      const elapsed = Date.now() - state.timerStartedAt;
      state.timerRemaining = Math.max(0, state.timerRemaining - elapsed);
      clearExistingTimer();
    }
  } else if (!state.popupOpen) {
    // 復帰後に残り時間で再開
    startAutoPopupTimer(state.timerRemaining);
  }
}

function bindEvents() {
  els.layer.addEventListener('click', onLayerClick);
  document.addEventListener('click', onDocumentClick);
  document.addEventListener('keydown', onKeydown);
  document.addEventListener('visibilitychange', onVisibilityChange);
}

/* ============================================================
 * 初期化 (9.1 / 8.1 初期化順序)
 * ========================================================== */

function applyInitialState(persisted) {
  state.autoPopupShown = persisted.autoPopupShown;
  state.autoPopupDismissed = persisted.autoPopupDismissed;
  if (CHAT_CONFIG.autoPopupOncePerSession && persisted.autoPopupShown) {
    state.sessionSuppressed = true;
  }

  // バッジ表示制御
  const badge = els.widget?.querySelector('.chat-widget__badge');
  if (badge && !CHAT_CONFIG.showBadge) {
    badge.hidden = true;
  }

  // 初期 ARIA
  setManualPopupVisible(false);
  setAutoPopupVisible(false);

  // アバター画像の取得失敗フォールバック (18. 障害時のフォールバック)
  const avatar = els.widget?.querySelector('.chat-widget__avatar');
  if (avatar) {
    avatar.addEventListener('error', () => {
      avatar.replaceWith(createAvatarFallback());
    }, { once: true });
  }
}

function createAvatarFallback() {
  const span = document.createElement('span');
  span.className = 'chat-widget__avatar chat-widget__avatar--fallback';
  span.textContent = CHAT_CONFIG.avatarFallbackText;
  span.setAttribute('aria-hidden', 'true');
  return span;
}

/**
 * チャット機能を初期化する。多重呼び出しは無視。
 * @returns {boolean} 初期化を実行したら true
 */
export function initChatFeature() {
  if (initialized) return false;

  const found = getElements();
  if (!found.layer || !found.widget) {
    // chat-layer が無いページでは何もしない（非侵襲）
    return false;
  }
  if (found.layer.dataset.initialized === 'true') return false;

  Object.assign(els, found);
  initialized = true;
  els.layer.dataset.initialized = 'true';

  const persisted = readSessionState();
  applyInitialState(persisted);
  bindEvents();

  track(CHAT_CONFIG.events.widgetImpression);

  // 未表示かつ未拒否のときのみ自動ポップアップタイマー開始
  if (!state.autoPopupDismissed && !state.sessionSuppressed) {
    startAutoPopupTimer(CHAT_CONFIG.autoPopupDelay);
  }
  return true;
}

// テスト用に内部を限定公開
export const __internals = { state, els };

export default initChatFeature;
