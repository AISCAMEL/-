/**
 * APPREX theme interactions.
 * Vanilla JS, no dependencies. Mobile-first, accessible.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initMobileNav();
		initTabs();
		initAccordion();
		initReveal();
		initCounters();
		initChatbot();
	});

	/* ---- Zapier chatbot fallback (lazy iframe). Native AI widget is in chat.js ---- */
	function initChatbot() {
		var toggle = document.querySelector('.apprex-chat-toggle');
		var win = document.querySelector('.apprex-chat-window'); // Zapier variant only.
		if (!toggle || !win) {
			return;
		}
		toggle.addEventListener('click', function () {
			var open = win.classList.toggle('is-open');
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open && !win.querySelector('iframe') && win.dataset.src) {
				var f = document.createElement('iframe');
				f.src = win.dataset.src;
				f.title = 'APPREX チャット';
				f.loading = 'lazy';
				win.appendChild(f);
			}
		});
	}

	/* ---- Mobile drawer ---- */
	function initMobileNav() {
		var toggle = document.querySelector('.nav-toggle');
		var drawer = document.getElementById('mobile-drawer');
		var overlay = document.getElementById('mobile-drawer-overlay');
		if (!toggle || !drawer) {
			return;
		}
		function setOpen(open) {
			drawer.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (overlay) { overlay.hidden = !open; }
			document.body.style.overflow = open ? 'hidden' : '';
		}
		toggle.addEventListener('click', function () {
			setOpen(!drawer.classList.contains('is-open'));
		});
		drawer.addEventListener('click', function (e) {
			if (e.target.tagName === 'A') {
				setOpen(false);
			}
		});
		if (overlay) {
			overlay.addEventListener('click', function () {
				setOpen(false);
			});
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
				setOpen(false);
			}
		});
	}

	/* ---- Tab switch (functions section) ---- */
	function initTabs() {
		document.querySelectorAll('[data-tabs]').forEach(function (wrap) {
			var tabs = wrap.querySelectorAll('[role="tab"]');
			var panels = wrap.querySelectorAll('[role="tabpanel"]');
			tabs.forEach(function (tab) {
				tab.addEventListener('click', function () {
					tabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
					panels.forEach(function (p) {
						p.classList.remove('is-active');
						p.setAttribute('hidden', '');
					});
					tab.setAttribute('aria-selected', 'true');
					var panel = document.getElementById(tab.getAttribute('aria-controls'));
					if (panel) {
						panel.classList.add('is-active');
						panel.removeAttribute('hidden');
					}
				});
			});
		});
	}

	/* ---- FAQ accordion ---- */
	function initAccordion() {
		document.querySelectorAll('.faq-item__q').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var expanded = btn.getAttribute('aria-expanded') === 'true';
				var panel = document.getElementById(btn.getAttribute('aria-controls'));
				btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
				if (panel) {
					panel.style.maxHeight = expanded ? null : panel.scrollHeight + 'px';
				}
			});
		});
	}

	/* ---- Scroll reveal ---- */
	function initReveal() {
		var els = document.querySelectorAll('.is-reveal');
		if (!('IntersectionObserver' in window) || !els.length) {
			els.forEach(function (el) { el.classList.add('is-visible'); });
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					io.unobserve(entry.target);
				}
			});
		}, { threshold: 0.12 });
		els.forEach(function (el) { io.observe(el); });
	}

	/* ---- Stats counter animation ---- */
	function initCounters() {
		var nums = document.querySelectorAll('[data-counter]');
		if (!nums.length) {
			return;
		}
		var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (reduce || !('IntersectionObserver' in window)) {
			return; // Static value already rendered server-side.
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) {
					return;
				}
				animate(entry.target);
				io.unobserve(entry.target);
			});
		}, { threshold: 0.5 });
		nums.forEach(function (n) { io.observe(n); });

		function animate(el) {
			var raw = el.getAttribute('data-counter');
			var match = raw.replace(/,/g, '').match(/^(\d+(?:\.\d+)?)/);
			if (!match) {
				return; // Non-numeric (e.g. "1/10") — leave as-is.
			}
			var target = parseFloat(match[1]);
			var decimals = (match[1].split('.')[1] || '').length;
			var small = el.querySelector('small');
			var suffix = small ? small.outerHTML : '';
			var start = null;
			var duration = 1400;
			function step(ts) {
				if (start === null) { start = ts; }
				var p = Math.min((ts - start) / duration, 1);
				var eased = 1 - Math.pow(1 - p, 3);
				var current = (target * eased).toFixed(decimals);
				el.innerHTML = Number(current).toLocaleString('ja-JP') + suffix;
				if (p < 1) {
					requestAnimationFrame(step);
				} else {
					el.innerHTML = Number(target).toLocaleString('ja-JP') + suffix;
				}
			}
			requestAnimationFrame(step);
		}
	}
})();
