/* HRIS ROWELMARK GROUP - Service Worker */
const CACHE_NAME = 'ciah-hris-v1';
const STATIC_ASSETS = [
  './',
  './assets/style.css',
  './assets/app.js',
  './assets/whatsapp-pdf.js',
  './assets/logo.jpg',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
  './assets/icons/apple-touch-icon.png'
];

// Install: pre-cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Add each asset individually; ignore failures for any missing file
      return Promise.all(
        STATIC_ASSETS.map((url) =>
          cache.add(url).catch(() => {})
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// Activate: clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

// Fetch: network-first for navigations/APIs, cache-first for static assets
self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only handle same-origin GET requests
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Static assets (with query strings from cache-busting): cache-first
  if (STATIC_ASSETS.some((a) => {
    const assetPath = a.split('?')[0];
    const cleanAsset = assetPath.replace(/^\.\//, '');
    // Compare path portion to asset (account for cache-busted ?v=...)
    const reqPath = url.pathname.replace(/^\/+/, '');
    return reqPath === cleanAsset || a === './' + reqPath;
  })) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) return cached;
        return fetch(request).then((response) => {
          if (response && response.ok) {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          }
          return response;
        }).catch(() => caches.match('./'));
      })
    );
    return;
  }

  // Everything else (PHP pages, API): network-first, fall back to cache
  event.respondWith(
    fetch(request)
      .then((response) => {
        if (response && response.ok && response.type === 'basic') {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      })
      .catch(() =>
        caches.match(request).then((cached) => cached || caches.match('./'))
      )
  );
});
