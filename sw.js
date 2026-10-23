/* sw.js ― サービスワーカー（オフライン対応・PWA）
 * ネットワーク優先：オンライン時は常に最新を取得しつつキャッシュを更新、
 * オフライン時はキャッシュから配信（アプリシェル）。
 */
'use strict';
var CACHE = 'aizu-kaikei-v1';
var CORE = ['./', './index.html', './manifest.json', './assets/icon-192.png', './assets/icon-512.png'];

self.addEventListener('install', function (e) {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(CORE).catch(function () {}); }));
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) {
    return Promise.all(ks.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.origin !== location.origin) return; // 外部（API等）はそのまま
  e.respondWith(
    fetch(req).then(function (res) {
      var copy = res.clone();
      caches.open(CACHE).then(function (c) { c.put(req, copy); });
      return res;
    }).catch(function () {
      return caches.match(req).then(function (m) { return m || caches.match('./index.html'); });
    })
  );
});
