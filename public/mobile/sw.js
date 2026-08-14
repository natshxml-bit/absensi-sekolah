var CACHE = 'absen-mobile-v1';
var ASSETS = [
  '/mobile/',
  '/mobile/index.html',
  '/mobile/manifest.json',
  '/mobile/css/app.css',
  '/mobile/js/api.js',
  '/mobile/js/ui.js',
  '/mobile/js/pages.js',
  '/mobile/js/app.js',
  '/mobile/icons/icon-192.png',
  '/mobile/icons/icon-512.png',
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE)
      .then(function (cache) { return cache.addAll(ASSETS); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys
          .filter(function (k) { return k !== CACHE; })
          .map(function (k) { return caches.delete(k); }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') { return; }
  var url = new URL(request.url);
  if (url.origin !== location.origin || url.pathname.indexOf('/api/') !== -1) { return; }

  event.respondWith(
    caches.match(request).then(function (hit) {
      var network = fetch(request).then(function (res) {
        if (res && res.status === 200 && res.type === 'basic') {
          var clone = res.clone();
          caches.open(CACHE).then(function (cache) { cache.put(request, clone); });
        }
        return res;
      }).catch(function () {
        return hit;
      });
      return hit || network;
    })
  );
});