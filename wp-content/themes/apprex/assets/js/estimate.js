/**
 * APPREX 見積もり → 発注 calculator（APPREX仕様）。
 * 初期設定費は今月キャンペーンで0円、月額は通常価格を取り消し線で表示、
 * オプションは一回費用として加算。
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.getElementById('apprex-estimate');
		if (!root || !window.APPREX_PRICING) { return; }
		var cfg = APPREX_PRICING.config;
		var rest = APPREX_PRICING.rest || {};
		var services = cfg.services;
		var toZero = !!(cfg.campaign && cfg.campaign.initial_to_zero);

		var elServices = document.getElementById('est-services');
		var elPlans = document.getElementById('est-plans');
		var elOptions = document.getElementById('est-options');
		var elTotal = document.getElementById('est-total');
		var orderForm = document.getElementById('est-order');
		var doneBox = document.getElementById('est-done');

		var state = { service: null, plan: null, options: [] };
		function yen(n) { return '¥' + Number(n).toLocaleString('ja-JP'); }

		function chip(label, active, sub) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'est-chip' + (active ? ' is-active' : '');
			b.innerHTML = '<span class="est-chip__label">' + label + '</span>' + (sub ? '<span class="est-chip__sub">' + sub + '</span>' : '');
			return b;
		}

		function renderServices() {
			elServices.innerHTML = '';
			Object.keys(services).forEach(function (key) {
				var svc = services[key];
				var b = chip(svc.label, state.service === key, '月額制・初期費用0円キャンペーン');
				b.addEventListener('click', function () {
					state.service = key; state.plan = null; state.options = [];
					renderServices(); renderPlans(); renderOptions(); renderTotal();
				});
				elServices.appendChild(b);
			});
		}

		function renderPlans() {
			elPlans.innerHTML = '';
			if (!state.service) { elPlans.innerHTML = '<p class="estimate__hint">先にサービスを選択してください。</p>'; return; }
			var plans = services[state.service].plans;
			Object.keys(plans).forEach(function (key) {
				var p = plans[key];
				var b = chip(p.label, state.plan === key, '月額 ' + Number(p.monthly).toLocaleString('ja-JP') + '円〜');
				b.addEventListener('click', function () { state.plan = key; renderPlans(); renderTotal(); });
				elPlans.appendChild(b);
			});
		}

		function renderOptions() {
			elOptions.innerHTML = '';
			if (!state.service) { elOptions.innerHTML = '<p class="estimate__hint">—</p>'; return; }
			var opts = services[state.service].options || {};
			var keys = Object.keys(opts);
			if (!keys.length) { elOptions.innerHTML = '<p class="estimate__hint">追加オプションはありません。</p>'; return; }
			keys.forEach(function (key) {
				var o = opts[key];
				var wrap = document.createElement('label');
				wrap.className = 'est-opt';
				wrap.innerHTML = '<input type="checkbox" value="' + key + '"><span>' + o.label + '</span><b>+' + Number(o.price).toLocaleString('ja-JP') + '円（一回）</b>';
				wrap.querySelector('input').addEventListener('change', function (e) {
					if (e.target.checked) { state.options.push(key); }
					else { state.options = state.options.filter(function (k) { return k !== key; }); }
					renderTotal();
				});
				elOptions.appendChild(wrap);
			});
		}

		function compute() {
			if (!state.service || !state.plan) { return null; }
			var svc = services[state.service];
			var p = svc.plans[state.plan];
			var optTotal = 0, optLines = [];
			state.options.forEach(function (k) {
				if (svc.options && svc.options[k]) { optTotal += svc.options[k].price; optLines.push(svc.options[k]); }
			});
			var initialRegular = p.initial || 0;
			var initial = toZero ? 0 : initialRegular;
			return {
				svcLabel: svc.label, planLabel: p.label,
				monthly: p.monthly, monthlyRegular: p.monthly_regular || p.monthly,
				initialRegular: initialRegular, initial: initial,
				optLines: optLines, optTotal: optTotal,
				initialTotal: initial + optTotal,
				annual: p.monthly * 12 + initial + optTotal
			};
		}

		function renderTotal() {
			var e = compute();
			if (!e) { elTotal.innerHTML = '<p class="estimate__hint">サービスとプランを選択してください。</p>'; orderForm.hidden = true; return; }
			var html = '<ul class="estimate__lines">';
			html += '<li><span>' + e.svcLabel + ' / ' + e.planLabel + '</span><b>' + yen(e.monthly) + '/月</b></li>';
			e.optLines.forEach(function (o) { html += '<li class="is-opt"><span>+ ' + o.label + '（一回）</span><b>' + yen(o.price) + '</b></li>'; });
			html += '</ul>';
			html += '<div class="estimate__big">' + yen(e.monthly) + '<small> / 月（税抜）</small></div>';
			html += '<ul class="estimate__sub">';
			if (e.monthlyRegular > e.monthly) { html += '<li>月額：<s>' + yen(e.monthlyRegular) + '</s> → <b>' + yen(e.monthly) + '</b> <em>キャンペーン</em></li>'; }
			if (e.initialRegular > e.initial) { html += '<li>初期設定費：<s>' + yen(e.initialRegular) + '</s> → <b>' + yen(e.initial) + '</b> <em>今月0円</em></li>'; }
			else { html += '<li>初期設定費：' + yen(e.initial) + '</li>'; }
			if (e.optTotal) { html += '<li>一回オプション：' + yen(e.optTotal) + '</li>'; }
			html += '<li>初期お支払い目安：<b>' + yen(e.initialTotal) + '</b></li>';
			html += '<li>初年度概算：' + yen(e.annual) + '</li>';
			html += '<li style="color:var(--color-muted)">最低利用期間：1年契約（12ヶ月）</li>';
			html += '</ul>';
			elTotal.innerHTML = html;
			orderForm.hidden = false;
		}

		orderForm.addEventListener('submit', function (ev) {
			ev.preventDefault();
			if (!compute()) { return; }
			var btn = orderForm.querySelector('button[type="submit"]');
			btn.disabled = true; btn.textContent = '送信中…';
			var fd = new FormData(orderForm);
			var payload = {
				service: state.service, plan: state.plan, options: state.options,
				name: fd.get('name'), company: fd.get('company'), email: fd.get('email'),
				message: fd.get('message'), source_url: location.href
			};
			fetch(rest.root + 'order', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rest.nonce },
				body: JSON.stringify(payload)
			})
				.then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
				.then(function (res) {
					if (res.ok && res.body && res.body.ok) {
						orderForm.hidden = true; doneBox.hidden = false;
						doneBox.innerHTML = '<div class="estimate__success"><h4>✅ お申し込みを受け付けました</h4><p>' + (res.body.message || '') + '</p>' + (res.body.summary || '') + '</div>';
						doneBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
					} else {
						alert((res.body && res.body.message) ? res.body.message : '送信に失敗しました。');
						btn.disabled = false; btn.textContent = 'この内容で発注する';
					}
				})
				.catch(function () { alert('通信エラーが発生しました。'); btn.disabled = false; btn.textContent = 'この内容で発注する'; });
		});

		renderServices(); renderPlans(); renderOptions(); renderTotal();
	});
})();
