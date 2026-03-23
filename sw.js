/** AWA Service Worker v2.0.0 — Stale-While-Revalidate Strategy (2026-03-23) */
const CACHE_VERSION = 'awa-v20260323';
const FONT_CACHE = 'awa-fonts-v1';

/* Patterns to cache with stale-while-revalidate */
const CACHEABLE_PATTERNS = [
  /\/css\/awa-bundle-[\w-]+\.css$/,
  /\/css\/awa-visual-fixes-critical\.css$/,
  /\/css\/swiper-bundle\.min\.css$/,
  /\/css\/themes5\.css$/,
  /\/fonts\/rubik\/rubik-\d{3}\.woff2$/
];

/* Patterns for font caching (immutable — cache-first, long TTL) */
const FONT_PATTERNS = [
  /\.woff2$/
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names
          .filter((name) => name !== CACHE_VERSION && name !== FONT_CACHE)
          .map((name) => caches.delete(name))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = event.request.url;

  /* Fonts: cache-first (immutable content, long TTL) */
  if (FONT_PATTERNS.some((re) => re.test(url))) {
    event.respondWith(
      caches.open(FONT_CACHE).then((cache) =>
        cache.match(event.request).then((cached) => {
          if (cached) return cached;
          return fetch(event.request).then((response) => {
            if (response.ok) cache.put(event.request, response.clone());
            return response;
          });
        })
      )
    );
    return;
  }

  /* CSS bundles: stale-while-revalidate (serve cached, update in background) */
  if (CACHEABLE_PATTERNS.some((re) => re.test(url))) {
    event.respondWith(
      caches.open(CACHE_VERSION).then((cache) =>
        cache.match(event.request).then((cached) => {
          const networkFetch = fetch(event.request).then((response) => {
            if (response.ok) cache.put(event.request, response.clone());
            return response;
          });
          return cached || networkFetch;
        })
      )
    );
    return;
  }

  /* Everything else: network-only (no caching) */
});
