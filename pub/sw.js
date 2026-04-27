/** AWA Service Worker v2.4.0 — Multi-strategy Cache (2026-03-29) */
const CACHE_VERSION = '20260427v2-logo-center';
const FONT_CACHE = 'awa-fonts-v1';
const IMAGE_CACHE = 'awa-images-v1';
const IMAGE_CACHE_MAX = 300; // max entries

/* Patterns to cache with stale-while-revalidate */
const CACHEABLE_PATTERNS = [
  /\/css\/awa-bundle-[\w-]+\.css$/,
  /\/css\/awa-visual-fixes-critical\.css$/,
  /\/css\/awa-polish-sweep\.css$/,
  /\/css\/awa-pdp-b2b-pro\.css$/,
  /\/css\/swiper-bundle\.min\.css$/,
  /\/css\/themes5\.css$/,
  // awa-bundle.min.js removed (file no longer exists — SW-A3 fix 2026-04-18)
  /\/js\/swiper-bundle\.min\.js$/,
  /\/fonts\/rubik\/rubik-\d{3}\.woff2$/
];

/* Patterns for font caching (immutable — cache-first, long TTL) */
const FONT_PATTERNS = [
  /\.woff2$/
];

/* Product image cache: cache-first with LRU eviction */
const IMAGE_PATTERNS = [
  /\/media\/catalog\/product\/cache\/.+\.(jpe?g|png|webp)$/,
  /\/media\/catalog\/product\/.+\.(jpe?g|png|webp)$/
];

/** LRU eviction: keep cache under IMAGE_CACHE_MAX entries */
async function trimImageCache(cache) {
  const keys = await cache.keys();
  if (keys.length > IMAGE_CACHE_MAX) {
    const toDelete = keys.slice(0, keys.length - IMAGE_CACHE_MAX);
    await Promise.all(toDelete.map((k) => cache.delete(k)));
  }
}

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(
        names
          .filter((name) => name !== CACHE_VERSION && name !== FONT_CACHE && name !== IMAGE_CACHE)
          .map((name) => caches.delete(name))
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = event.request.url;

  /* Skip admin, checkout, customer, and API routes entirely — never intercept */
  if (/\/(admin_|admin\/|checkout|customer\/|rest\/|graphql)/.test(url)) {
    return;
  }

  /* Skip non-GET requests */
  if (event.request.method !== 'GET') {
    return;
  }

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

  /* Product images: cache-first with LRU eviction */
  if (IMAGE_PATTERNS.some((re) => re.test(url))) {
    event.respondWith(
      caches.open(IMAGE_CACHE).then((cache) =>
        cache.match(event.request).then((cached) => {
          if (cached) return cached;
          return fetch(event.request).then((response) => {
            if (response.ok) {
              cache.put(event.request, response.clone());
              trimImageCache(cache);
            }
            return response;
          }).catch(() => cached || Response.error());
        })
      )
    );
    return;
  }

  /* CSS/JS bundles: stale-while-revalidate (serve cached, update in background) */
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
