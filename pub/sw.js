/** AWA Service Worker v2.4.3 — Multi-strategy Cache (2026-04-05) */
const CACHE_VERSION = 'awa-v20260405-1';
const FONT_CACHE = 'awa-fonts-v1';
const IMAGE_CACHE = 'awa-images-v1';
const IMAGE_CACHE_MAX = 300; // max entries

/* CSS: cache-first (no background revalidation to prevent FOUC) */
const CSS_PATTERNS = [
  /\/css\/awa-bundle-[\w-]+\.css$/,
  /\/css\/awa-visual-fixes-critical\.css$/,
  /\/css\/awa-polish-sweep\.css$/,
  /\/css\/awa-pdp-b2b-pro\.css$/,
  /\/css\/swiper-bundle\.min\.css$/,
  /\/css\/themes5\.css$/
];

/* JS: stale-while-revalidate (safe for background updates) */
const JS_PATTERNS = [
  /\/js\/awa-bundle\.min\.js$/,
  /\/js\/swiper-bundle\.min\.js$/
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
          }).catch(() => cached || Response.error());
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
          }).catch((err) => {
            // Silently handle missing images (404, network errors)
            // Return cached version if available, otherwise transparent 1x1 placeholder
            if (cached) return cached;
            // Return a minimal transparent 1x1 PNG instead of Response.error()
            const placeholder = new Response(
              new Blob([new Uint8Array([137,80,78,71,13,10,26,10,0,0,0,13,73,72,68,82,0,0,0,1,0,0,0,1,8,6,0,0,0,31,21,196,137,0,0,0,10,73,68,65,84,120,156,99,0,1,0,0,5,0,1,13,10,45,180,0,0,0,0,73,69,78,68,174,66,96,130])],
              { type: 'image/png' }),
              { status: 200, statusText: 'OK', headers: { 'Content-Type': 'image/png', 'Cache-Control': 'no-store' } }
            );
            return placeholder;
          });
        })
      )
    );
    return;
  }

  /* CSS: cache-first (NO background revalidation to prevent FOUC during page load) */
  if (CSS_PATTERNS.some((re) => re.test(url))) {
    event.respondWith(
      caches.open(CACHE_VERSION).then((cache) =>
        cache.match(event.request).then((cached) => {
          if (cached) return cached;
          return fetch(event.request).then((response) => {
            if (response.ok) cache.put(event.request, response.clone());
            return response;
          }).catch(() => cached || Response.error());
        })
      )
    );
    return;
  }

  /* JS: stale-while-revalidate (serve cached, update in background) */
  if (JS_PATTERNS.some((re) => re.test(url))) {
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
