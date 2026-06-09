/**
 * APPREX estimate → order calculator.
 * Renders choices from APPREX_PRICING.config, computes a live estimate,
 * and submits the order to the WP REST endpoint.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.getElementById('apprex-estimate');
		if (!root || !window.APPREX_PRICING) {
			return;
		}
		var cfg = APPREX_PRICING.config;
		var rest = APPREX_PRICING.rest || {};
		var services = cfg.services;

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
				var b = chip(svc.label, state.service === key, svc.billing === 'monthly' ? '月額制' : '買い切り');
				b.addEventListener('click', function () {
					state.service = key; state.plan = null; state.options = [];
					renderServices(); renderPlans(); renderOptions(); renderTotal();
				});
				elServices.appendChild(b);
			});
		}

		function renderPlans() {
			elPlans.innerHTML = '';
			if (!state.service) {
				elPlans.innerHTML = '<p class="estimate__hint">先にサービスを選択してください。</p>';
				return;
			}
			var svc = services[state.service];
			var unit = svc.billing === 'monthly' ? '円/月' : '円';
			Object.keys(svc.plans).forEach(function (key) {
				var p = svc.plans[key];
				var b = chip(p.label, state.plan === key, Number(p.price).toLocaleString('ja-JP') + unit);
				b.addEventListener('click', function () {
					state.plan = key; renderPlans(); renderTotal();
				});
				elPlans.appendChild(b);
			});
		}

		function renderOptions() {
			elOptions.innerHTML = '';
			if (!state.service) {
				elOptions.innerHTML = '<p class="estimate__hint">—</p>';
				return;
			}
			var svc = services[state.service];
			var unit = svc.billing === 'monthly' ? '円/月' : '円';
			var keys = Object.keys(svc.options || {});
			if (!keys.length) {
				elOptions.innerHTML = '<p class="estimate__hint">このサービスに追加オプションはありません。</p>';
				return;
			}
			keys.forEach(function (key) {
				var o = svc.options[key];
				var id = 'opt-' + key;
				var wrap = document.createElement('label');
				wrap.className = 'est-opt';
				wrap.innerHTML = '<input type="checkbox" id="' + id + '" value="' + key + '">' +
					'<span>' + o.label + '</span><b>+' + Number(o.price).toLocaleString('ja-JP') + unit + '</b>';
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
			var base = svc.plans[state.plan].price;
			var optTotal = 0;
			var optLines = [];
			state.options.forEach(function (k) {
				if (svc.options && svc.options[k]) {
					optTotal += svc.options[k].price;
					optLines.push(svc.options[k]);
				}
			});
			var sum = base + optTotal;
			return {
				billing: svc.billing,
				svcLabel: svc.label,
				planLabel: svc.plans[state.plan].label,
				base: base,
				optLines: optLines,
				monthly: svc.billing === 'monthly' ? sum : 0,
				oneoff: svc.billing === 'oneoff' ? sum : 0,
				initial: cfg.campaign.initial_fee,
				normalInitial: cfg.campaign.normal_fee,
				minMonths: svc.min_months,
				annual: svc.billing === 'monthly' ? sum * 12 : 0
			};
		}

		function renderTotal() {
			var e = compute();
			if (!e) {
				elTotal.innerHTML = '<p class="estimate__hint">サービスとプランを選択してください。</p>';
				orderForm.hidden = true;
				return;
			}
			var html = '<ul class="estimate__lines">';
			html += '<li><span>' + e.svcLabel + ' / ' + e.planLabel + '</span><b>' + yen(e.base) + '</b></li>';
			e.optLines.forEach(function (o) {
				html += '<li class="is-opt"><span>+ ' + o.label + '</span><b>' + yen(o.price) + '</b></li>';
			});
			html += '</ul>';
			if (e.billing === 'monthly') {
				html += '<div class="estimate__big">' + yen(e.monthly) + '<small> / 月（税抜）</small></div>';
				html += '<ul class="estimate__sub">';
				html += '<li>初期費用：<b>' + yen(e.initial) + '</b> <s>' + yen(e.normalInitial) + '</s> <em>キャンペーン中</em></li>';
				html += '<li>年間概算：' + yen(e.annual) + '</li>';
				html += '<li>最低契約：' + e.minMonths + 'ヶ月</li>';
				html += '</ul>';
			} else {
				html += '<div class="estimate__big">' + yen(e.oneoff) + '<small> 買い切り（税抜）</small></div>';
			}
			elTotal.innerHTML = html;
			orderForm.hidden = false;
		}

		// Order submission.
		orderForm.addEventListener('submit', function (ev) {
			ev.preventDefault();
			var e = compute();
			if (!e) { return; }
			var btn = orderForm.querySelector('button[type="submit"]');
			btn.disabled = true; btn.textContent = '送信中…';

			var fd = new FormData(orderForm);
			var payload = {
				service: state.service,
				plan: state.plan,
				options: state.options,
				name: fd.get('name'),
				company: fd.get('company'),
				email: fd.get('email'),
				message: fd.get('message'),
				source_url: location.href
			};

			fetch(rest.root + 'order', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rest.nonce },
				body: JSON.stringify(payload)
			})
				.then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
				.then(function (res) {
					if (res.ok && res.body && res.body.ok) {
						orderForm.hidden = true;
						doneBox.hidden = false;
						doneBox.innerHTML = '<div class="estimate__success"><h4>✅ お申し込みを受け付けました</h4><p>' +
							(res.body.message || '') + '</p>' + (res.body.summary || '') + '</div>';
						doneBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
					} else {
						alert((res.body && res.body.message) ? res.body.message : '送信に失敗しました。時間をおいて再度お試しください。');
						btn.disabled = false; btn.textContent = 'この内容で発注する';
					}
				})
				.catch(function () {
					alert('通信エラーが発生しました。時間をおいて再度お試しください。');
					btn.disabled = false; btn.textContent = 'この内容で発注する';
				});
		});

		renderServices();
		renderPlans();
		renderOptions();
		renderTotal();
	});
})();
