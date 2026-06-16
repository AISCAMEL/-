/* AIS Corporate theme — UI scripts (vanilla JS) */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    initMobileMenu();
    initReveal();
    initAccordions();
    initSliders();
  });

  /* モバイルメニュー開閉 */
  function initMobileMenu() {
    var menu = document.querySelector("[data-ais-menu]");
    if (!menu) return;
    var openBtn = document.querySelector("[data-ais-menu-open]");
    var closers = document.querySelectorAll("[data-ais-menu-close]");

    function open() {
      menu.classList.remove("hidden");
      document.body.style.overflow = "hidden";
    }
    function close() {
      menu.classList.add("hidden");
      document.body.style.overflow = "";
    }
    if (openBtn) openBtn.addEventListener("click", open);
    closers.forEach(function (c) {
      c.addEventListener("click", close);
    });
    menu.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", close);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
    });
  }

  /* スクロールで .reveal をフェードアップ表示 */
  function initReveal() {
    var els = document.querySelectorAll(".reveal");
    if (!els.length) return;
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduce || !("IntersectionObserver" in window)) {
      els.forEach(function (el) {
        el.classList.add("is-visible");
      });
      return;
    }
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -8% 0px" }
    );
    els.forEach(function (el) {
      io.observe(el);
    });
  }

  /* FAQ アコーディオン */
  function initAccordions() {
    document.querySelectorAll("[data-ais-accordion]").forEach(function (acc) {
      var triggers = acc.querySelectorAll("[data-ais-acc-trigger]");
      triggers.forEach(function (btn) {
        btn.addEventListener("click", function () {
          var panel = btn.closest("[data-ais-acc-item]").querySelector("[data-ais-acc-panel]");
          var icon = btn.querySelector("[data-ais-acc-icon]");
          var isOpen = btn.getAttribute("aria-expanded") === "true";
          // 単一開閉（同時に1つ）
          triggers.forEach(function (other) {
            if (other === btn) return;
            other.setAttribute("aria-expanded", "false");
            var op = other.closest("[data-ais-acc-item]").querySelector("[data-ais-acc-panel]");
            var oi = other.querySelector("[data-ais-acc-icon]");
            setPanel(op, false);
            if (oi) oi.classList.remove("rotate-180");
          });
          btn.setAttribute("aria-expanded", String(!isOpen));
          setPanel(panel, !isOpen);
          if (icon) icon.classList.toggle("rotate-180", !isOpen);
        });
      });
    });
  }

  function setPanel(panel, open) {
    if (!panel) return;
    panel.classList.toggle("grid-rows-[1fr]", open);
    panel.classList.toggle("opacity-100", open);
    panel.classList.toggle("grid-rows-[0fr]", !open);
    panel.classList.toggle("opacity-0", !open);
  }

  /* ブランドスライダー */
  function initSliders() {
    document.querySelectorAll("[data-ais-slider]").forEach(function (root) {
      var track = root.querySelector("[data-ais-track]");
      if (!track) return;
      var slides = track.children;
      var count = slides.length;
      if (count <= 1) return;
      var dots = root.querySelectorAll("[data-ais-dot]");
      var prev = root.querySelector("[data-ais-prev]");
      var next = root.querySelector("[data-ais-next]");
      var index = 0;
      var timer = null;
      var AUTOPLAY = 5000;
      var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

      function render() {
        track.style.transform = "translateX(-" + index * 100 + "%)";
        dots.forEach(function (d, i) {
          var active = i === index;
          d.classList.toggle("w-6", active);
          d.classList.toggle("bg-brand-600", active);
          d.classList.toggle("w-2", !active);
          d.classList.toggle("bg-slate-300", !active);
          if (active) {
            d.setAttribute("aria-current", "true");
          } else {
            d.removeAttribute("aria-current");
          }
        });
      }
      function go(i) {
        index = ((i % count) + count) % count;
        render();
      }
      function start() {
        if (reduce) return;
        stop();
        timer = setInterval(function () {
          go(index + 1);
        }, AUTOPLAY);
      }
      function stop() {
        if (timer) clearInterval(timer);
        timer = null;
      }

      if (prev) prev.addEventListener("click", function () { go(index - 1); start(); });
      if (next) next.addEventListener("click", function () { go(index + 1); start(); });
      dots.forEach(function (d, i) {
        d.addEventListener("click", function () { go(i); start(); });
      });
      root.addEventListener("mouseenter", stop);
      root.addEventListener("mouseleave", start);

      render();
      start();
    });
  }
})();
