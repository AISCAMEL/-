// IWASAWA SURF BASE — 最小サービスワーカー
// インストール可能（PWA）にするための fetch ハンドラ＋アプリシェルの軽いキャッシュ。
// 注意: 認証/データは常にネットワーク優先。静的アセットのみキャッシュする。

const CACHE = "iwasawa-v1";
const APP_SHELL = ["/", "/manifest.webmanifest", "/icon-192.png", "/icon-512.png"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(APP_SHELL)).catch(() => {}),
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))),
      ),
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const { request } = event;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  // 同一オリジンの静的アセットのみ stale-while-revalidate
  const isStatic =
    url.origin === self.location.origin &&
    /\.(?:png|jpg|jpeg|svg|webp|ico|css|js|woff2?)$/.test(url.pathname);

  if (!isStatic) return; // HTML/APIはネットワークにそのまま委譲（オフライン強制しない）

  event.respondWith(
    caches.open(CACHE).then(async (cache) => {
      const cached = await cache.match(request);
      const network = fetch(request)
        .then((res) => {
          if (res.ok) cache.put(request, res.clone());
          return res;
        })
        .catch(() => cached);
      return cached || network;
    }),
  );
});
