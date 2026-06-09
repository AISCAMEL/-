/**
 * analytics.js
 * ------------------------------------------------------------------
 * 計測連携。window.dataLayer が存在すれば push し、存在しなくても
 * エラーにしない。計測失敗が UI 挙動や CTA 遷移を阻害しないことを保証する。
 * (仕様定義書 第2部 8.7 計測連携方針 / 18. 障害時のフォールバック)
 */

import { CHAT_CONFIG } from './config.js';

/**
 * 計測イベントを送出する。例外は握りつぶし、呼び出し側を決して止めない。
 * @param {string} eventName 計測イベント名
 * @param {Object} [params]  付随パラメータ
 */
export function track(eventName, params = {}) {
  if (!eventName) return;
  try {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ event: eventName, ...params });
    if (CHAT_CONFIG.debug) {
      // eslint-disable-next-line no-console
      console.debug('[carmel-lp][track]', eventName, params);
    }
  } catch (_err) {
    // 計測失敗は無視。CTA遷移・UI挙動を優先する。
  }
}

export default track;
