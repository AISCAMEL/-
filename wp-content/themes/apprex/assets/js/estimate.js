/**
 * APPREX 見積もり依頼。
 * 画面に出す金額は、いちばん安いプランの「月額〜」だけ（APPREX_PRICING はサーバー側で金額を外して渡す）。
 * 初期費用・上位プラン・オプションは選んだ内容を送ってもらい、担当者が正式なお見積りを返す。
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.getElementById('apprex-estimate');
		if (!root || !window.APPREX_PRICING) { return; }
		var cfg = APPREX_PRICING.config;
		var rest = APPREX_PRICING.rest || {};
		var services = cfg.services;
		var quotePlans = APPREX_PRICING.quotePlans || {};
		var meetingUrl = APPREX_PRICING.meetingUrl || '';
		var contactUrl = APPREX_PRICING.contactUrl || '';

		var elServices = document.getElementById('est-services');
		var elPlans = document.getElementById('est-plans');
		var elOptions = document.getElementById('est-options');
		var elSetup = document.getElementById('est-setup');
		var elTotal = document.getElementById('est-total');
		var orderForm = document.getElementById('est-order');
		var doneBox = document.getElementById('est-done');

		// ?setup=ai でAIスタジオからの依頼を選んだ状態で開く。
		var qsSetup = (new URLSearchParams(location.search)).get('setup');
		var state = { service: null, plan: null, options: [], quote: null, setup: qsSetup === 'full' ? 'full' : 'ai' };
		function yen(n) { return '¥' + Number(n).toLocaleString('ja-JP'); }
		function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

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
				var from = fromMonthly(svc);
				var b = chip(svc.label, state.service === key && !state.quote, (from ? '月額' + from.toLocaleString('ja-JP') + '円〜・' : '') + (svc.setup ? '初期費用はお見積り' : '初期費用0円（1年契約）'));
				b.addEventListener('click', function () {
					state.service = key; state.plan = null; state.options = []; state.quote = null;
					renderServices(); renderPlans(); renderSetup(); renderOptions(); renderTotal();
				});
				elServices.appendChild(b);
			});
			// 要相談メニュー（オリジナルアプリ開発など）→ 価格は出さず、ミーティングへ誘導。
			Object.keys(quotePlans).forEach(function (qkey) {
				var q = quotePlans[qkey];
				var b = chip(q.label, state.quote === qkey, '個別お見積り（要相談）');
				b.classList.add('est-chip--quote');
				b.addEventListener('click', function () {
					state.quote = qkey; state.service = null; state.plan = null; state.options = [];
					renderServices(); renderPlans(); renderSetup(); renderOptions(); renderTotal();
				});
				elServices.appendChild(b);
			});
		}

		function renderPlans() {
			elPlans.innerHTML = '';
			if (state.quote) { elPlans.innerHTML = '<p class="estimate__hint">この内容は個別お見積り（要相談）です。右側の「ミーティングで相談」へお進みください。</p>'; return; }
			if (!state.service) { elPlans.innerHTML = '<p class="estimate__hint">先にサービスを選択してください。</p>'; return; }
			var plans = services[state.service].plans;
			Object.keys(plans).forEach(function (key) {
				var p = plans[key];
				var b = chip(p.label, state.plan === key, p.monthly ? '月額 ' + Number(p.monthly).toLocaleString('ja-JP') + '円〜' : '月額はお見積り');
				b.addEventListener('click', function () { state.plan = key; renderPlans(); renderTotal(); });
				elPlans.appendChild(b);
			});
		}

		function renderSetup() {
			if (!elSetup) { return; }
			elSetup.innerHTML = '';
			if (state.quote || !state.service) { elSetup.innerHTML = '<p class="estimate__hint">—</p>'; return; }
			var setups = services[state.service].setup;
			if (!setups) { elSetup.innerHTML = '<p class="estimate__hint">初期費用は0円です（1年契約）。</p>'; return; }
			Object.keys(setups).forEach(function (key) {
				var st = setups[key];
				var b = chip(st.label + (key === 'ai' ? '（おすすめ・費用を抑えられます）' : ''), state.setup === key, st.desc || '');
				b.addEventListener('click', function () { state.setup = key; renderSetup(); renderTotal(); });
				elSetup.appendChild(b);
			});
		}

		function renderOptions() {
			elOptions.innerHTML = '';
			if (state.quote) { elOptions.innerHTML = '<p class="estimate__hint">—</p>'; return; }
			if (!state.service) { elOptions.innerHTML = '<p class="estimate__hint">—</p>'; return; }
			var opts = services[state.service].options || {};
			var keys = Object.keys(opts);
			if (!keys.length) { elOptions.innerHTML = '<p class="estimate__hint">追加オプションはありません。</p>'; return; }
			keys.forEach(function (key) {
				var o = opts[key];
				var wrap = document.createElement('label');
				wrap.className = 'est-opt';
				wrap.innerHTML = '<input type="checkbox" value="' + key + '"><span>' + o.label + '</span><b>お見積り</b>';
				wrap.querySelector('input').addEventListener('change', function (e) {
					if (e.target.checked) { state.options.push(key); }
					else { state.options = state.options.filter(function (k) { return k !== key; }); }
					renderTotal();
				});
				elOptions.appendChild(wrap);
			});
		}

		function fromMonthly(svc) {
			var min = 0;
			Object.keys(svc.plans || {}).forEach(function (k) { var m = svc.plans[k].monthly; if (m && (!min || m < min)) { min = m; } });
			return min;
		}

		function compute() {
			if (!state.service || !state.plan) { return null; }
			var svc = services[state.service];
			var p = svc.plans[state.plan];
			var st = svc.setup && svc.setup[state.setup] ? svc.setup[state.setup] : null;
			var optLines = [];
			state.options.forEach(function (k) { if (svc.options && svc.options[k]) { optLines.push(svc.options[k]); } });
			return { svc: svc, svcLabel: svc.label, planLabel: p.label, monthly: p.monthly || 0, from: fromMonthly(svc), setupLabel: st ? st.label : '', optLines: optLines };
		}

		function renderTotal() {
			// 要相談メニュー（オリジナルアプリ開発など）：価格は出さずミーティング予約へ誘導。
			if (state.quote) {
				var q = quotePlans[state.quote] || {};
				var h = '<div class="estimate__quote">';
				h += '<h4>' + esc(q.label || 'オリジナルアプリ開発') + '</h4>';
				if (q.lead) { h += '<p>' + esc(q.lead) + '</p>'; }
				h += '<p class="estimate__hint">機能に合わせて個別にお見積りします。まずは無料のオンライン相談で要件をお聞かせください。AIスタジオで試作と仕様を作っていただくと、より早く正確なお見積りをお出しできます。</p>';
				if (meetingUrl) { h += '<a class="btn btn--primary btn--block" href="' + meetingUrl + '">📅 ミーティングで相談（無料）</a>'; }
				if (contactUrl) { h += '<a class="btn btn--ghost btn--block" href="' + contactUrl + '" style="margin-top:8px">メールで相談する</a>'; }
				h += '</div>';
				elTotal.innerHTML = h;
				orderForm.hidden = true;
				doneBox.hidden = true;
				return;
			}
			var e = compute();
			if (!e) { elTotal.innerHTML = '<p class="estimate__hint">サービスとプランを選択してください。</p>'; orderForm.hidden = true; return; }
			var html = '<ul class="estimate__lines">';
			html += '<li><span>' + esc(e.svcLabel) + ' / ' + esc(e.planLabel) + '</span><b>' + (e.monthly ? yen(e.monthly) + '〜/月' : 'お見積り') + '</b></li>';
			e.optLines.forEach(function (o) { html += '<li class="is-opt"><span>+ ' + esc(o.label) + '</span><b>お見積り</b></li>'; });
			html += '</ul>';
			html += '<div class="estimate__big">' + (e.monthly ? yen(e.monthly) + '<small> 〜 / 月（税抜）</small>' : '<small>月額 </small>お見積り') + '</div>';
			html += '<ul class="estimate__sub">';
			if (!e.monthly && e.from) { html += '<li>このサービスは月額' + yen(e.from) + '〜です</li>'; }
			if (e.svc.setup) { html += '<li>初期費用：<b>お見積り</b>' + (e.setupLabel ? '（' + esc(e.setupLabel) + '）' : '') + '</li>'; }
			else { html += '<li>初期費用：0円</li>'; }
			if (e.optLines.length) { html += '<li>オプション：お見積り</li>'; }
			html += '<li style="color:var(--color-muted)">最低利用期間：1年契約（12ヶ月）</li>';
			html += '<li>正式なお見積りは、担当者から2営業日以内にお送りします（無料）。</li>';
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
				service: state.service, plan: state.plan, options: state.options, setup: state.setup,
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
						doneBox.innerHTML = '<div class="estimate__success"><h4>✅ お見積りのご依頼を受け付けました</h4><p>' + (res.body.message || '') + '</p>' + (res.body.summary || '') + '</div>';
						doneBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
					} else {
						alert((res.body && res.body.message) ? res.body.message : '送信に失敗しました。');
						btn.disabled = false; btn.textContent = 'この内容で見積りを依頼する';
					}
				})
				.catch(function () { alert('通信エラーが発生しました。'); btn.disabled = false; btn.textContent = 'この内容で見積りを依頼する'; });
		});

		renderServices(); renderPlans(); renderSetup(); renderOptions(); renderTotal();
	});
})();
