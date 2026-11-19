/* TimeGuardian – Service-Worker. Fasst ausschließlich Dateien der App an (js, css, img). Niemals Seitenaufrufe,
 * niemals /api/, niemals HTML: Navigationen laufen immer direkt zum Server. Es gibt bewusst keine Offline-Seite. */
'use strict';
var CACHE = 'tg-shell-v5';
var ASSET_RE = /\/timeguardian\/(js|css|img)\/[^?#]+\.(js|css|png|svg|webp|ico)$/;

// Alte Version sofort ersetzen (auch einen fehlerhaften Vorgänger mit Offline-Seite)
self.addEventListener('install', function () { self.skipWaiting(); });

self.addEventListener('activate', function (event) {
  event.waitUntil(caches.keys().then(function (keys) {
    return Promise.all(keys.filter(function (k) { return k.indexOf('tg-shell-') === 0 && k !== CACHE; }).map(function (k) { return caches.delete(k); }));
  }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (event) {
  var req = event.request;
  if (req.method !== 'GET' || req.mode === 'navigate') { return; }
  var url;
  try { url = new URL(req.url); } catch (e) { return; }
  if (url.origin !== self.location.origin) { return; }
  var path = url.pathname;
  if (path.indexOf('/api/') >= 0 || !ASSET_RE.test(path)) { return; }
  // Stale-while-revalidate, nur für Shell-Dateien. Bei Netzfehler ohne Treffer läuft die Anfrage normal ins Leere.
  event.respondWith(caches.open(CACHE).then(function (cache) {
    return cache.match(req).then(function (hit) {
      var net = fetch(req).then(function (res) {
        if (res && res.ok && res.type === 'basic') { cache.put(req, res.clone()); }
        return res;
      }).catch(function () { return hit || Response.error(); });
      return hit || net;
    });
  }));
});
