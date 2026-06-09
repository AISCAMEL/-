/**
 * main.js
 * ------------------------------------------------------------------
 * エントリーポイント。LP本体の軽量インタラクション（FAQアコーディオン、
 * 5問診断のスムーズスクロール等）と、追加チャット機能の初期化を行う。
 *
 * チャット機能は lp-chat.js に隔離されており、ここでは起動するだけ。
 */

import { initChatFeature } from './lp-chat.js';
import { initChatbot } from './chatbot.js';

/* ---------- LP本体: FAQ アコーディオン ---------- */
function initFaq() {
  const items = document.querySelectorAll('.faq__item');
  items.forEach((item) => {
    const trigger = item.querySelector('.faq__question');
    const answer = item.querySelector('.faq__answer');
    if (!trigger || !answer) return;
    trigger.addEventListener('click', () => {
      const expanded = trigger.getAttribute('aria-expanded') === 'true';
      trigger.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      answer.hidden = expanded;
    });
  });
}

/* ---------- LP本体: 5問診断（簡易・自己関連化のための関心維持UI） ---------- */
function initDiagnostic() {
  const form = document.querySelector('[data-role="diagnostic"]');
  if (!form) return;
  const result = form.querySelector('.diagnostic__result');
  const questions = form.querySelectorAll('.diagnostic__question');
  const total = questions.length;

  function update() {
    const answered = form.querySelectorAll(
      '.diagnostic__question input:checked'
    ).length;
    if (answered === total && result) {
      result.hidden = false;
      result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
  form.addEventListener('change', update);
}

/* ---------- LP本体: アンカーのスムーズスクロール ---------- */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', (e) => {
      const id = link.getAttribute('href');
      if (id.length <= 1) return;
      const target = document.querySelector(id);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });
}

function boot() {
  initChatFeature();
  initChatbot();
  initFaq();
  initDiagnostic();
  initSmoothScroll();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
