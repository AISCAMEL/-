/* AIS Corporate — AIチャット（OpenRouter 連携、サーバー側プロキシ経由） */
(function () {
  "use strict";
  if (typeof AIS_CHAT === "undefined") return;

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.getElementById("ais-chat");
    if (!root) return;
    var toggle = document.getElementById("ais-chat-toggle");
    var panel = document.getElementById("ais-chat-panel");
    var log = document.getElementById("ais-chat-log");
    var form = document.getElementById("ais-chat-form");
    var input = document.getElementById("ais-chat-input");
    var sendBtn = form.querySelector('button[type="submit"]');
    var iconOpen = toggle.querySelector("[data-ais-chat-open]");
    var iconShut = toggle.querySelector("[data-ais-chat-shut]");

    var history = []; // {role, content}
    var busy = false;
    var greeted = false;

    function openPanel() {
      panel.classList.remove("hidden");
      toggle.setAttribute("aria-expanded", "true");
      iconOpen.classList.add("hidden");
      iconShut.classList.remove("hidden");
      if (!greeted) {
        greeted = true;
        if (AIS_CHAT.greeting) addBubble("assistant", AIS_CHAT.greeting);
      }
      setTimeout(function () { input.focus(); }, 50);
    }
    function closePanel() {
      panel.classList.add("hidden");
      toggle.setAttribute("aria-expanded", "false");
      iconOpen.classList.remove("hidden");
      iconShut.classList.add("hidden");
    }
    function togglePanel() {
      if (panel.classList.contains("hidden")) openPanel();
      else closePanel();
    }

    toggle.addEventListener("click", togglePanel);
    root.querySelectorAll("[data-ais-chat-close]").forEach(function (b) {
      b.addEventListener("click", closePanel);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !panel.classList.contains("hidden")) closePanel();
    });

    // 自動リサイズ + Enter送信（Shift+Enterで改行）
    input.addEventListener("input", function () {
      input.style.height = "auto";
      input.style.height = Math.min(input.scrollHeight, 112) + "px";
    });
    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        form.requestSubmit();
      }
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (busy) return;
      var text = input.value.trim();
      if (!text) return;
      input.value = "";
      input.style.height = "auto";
      addBubble("user", text);
      history.push({ role: "user", content: text });
      sendToServer();
    });

    function sendToServer() {
      busy = true;
      sendBtn.disabled = true;
      var typing = addTyping();

      fetch(AIS_CHAT.restUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": AIS_CHAT.nonce,
        },
        body: JSON.stringify({ messages: history.slice(-20) }),
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (r) {
          typing.remove();
          if (r.ok && r.data && r.data.reply) {
            addBubble("assistant", r.data.reply);
            history.push({ role: "assistant", content: r.data.reply });
          } else {
            var msg = (r.data && r.data.error) || "申し訳ありません。応答に失敗しました。お手数ですがお問い合わせフォームをご利用ください。";
            addBubble("assistant", msg);
          }
        })
        .catch(function () {
          typing.remove();
          addBubble("assistant", "通信エラーが発生しました。時間をおいて再度お試しください。");
        })
        .finally(function () {
          busy = false;
          sendBtn.disabled = false;
          input.focus();
        });
    }

    function avatarEl() {
      var a = document.createElement("span");
      a.className = "grid h-7 w-7 flex-none place-items-center self-end overflow-hidden rounded-full bg-brand-50 ring-1 ring-slate-200";
      a.innerHTML = AIS_CHAT.avatar || "";
      return a;
    }

    function addBubble(role, text) {
      var wrap = document.createElement("div");
      wrap.className = "flex items-end gap-2 " + (role === "user" ? "justify-end" : "justify-start");
      if (role === "assistant") wrap.appendChild(avatarEl());
      var b = document.createElement("div");
      b.className =
        role === "user"
          ? "max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-br-sm bg-brand-600 px-3.5 py-2 text-sm leading-relaxed text-white"
          : "max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-bl-sm border border-slate-200 bg-white px-3.5 py-2 text-sm leading-relaxed text-ink-800";
      b.textContent = text;
      wrap.appendChild(b);
      log.appendChild(wrap);
      log.scrollTop = log.scrollHeight;
      return wrap;
    }

    function addTyping() {
      var wrap = document.createElement("div");
      wrap.className = "flex items-end gap-2 justify-start";
      wrap.appendChild(avatarEl());
      var t = document.createElement("div");
      t.innerHTML =
        '<div class="flex items-center gap-1 rounded-2xl rounded-bl-sm border border-slate-200 bg-white px-4 py-3">' +
        '<span class="h-2 w-2 animate-bounce rounded-full bg-slate-400" style="animation-delay:0ms"></span>' +
        '<span class="h-2 w-2 animate-bounce rounded-full bg-slate-400" style="animation-delay:120ms"></span>' +
        '<span class="h-2 w-2 animate-bounce rounded-full bg-slate-400" style="animation-delay:240ms"></span>' +
        "</div>";
      wrap.appendChild(t);
      log.appendChild(wrap);
      log.scrollTop = log.scrollHeight;
      return wrap;
    }
  });
})();
