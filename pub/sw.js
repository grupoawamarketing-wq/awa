/**
 * AWA Motos Service Worker v2.1.0
 *
 * Estratégias de Cache:
 * - Shell: Cache First (HTML base, manifest)
 * - Static: Stale-While-Revalidate (CSS, JS, fonts)
 * - Images: Cache First com fallback (imagens de produtos)
 * - API/Dynamic: Network First com fallback cache
 * - Navigation: Network First com fallback para offline.html
 *
 * @version 2.1.0
 * @author AWA Motos Dev Team
 */

const SW_VERSION = '3.0.0';
const CACHE_STATIC = 'awamotos-static-v3';
const CACHE_IMAGES = 'awamotos-images-v3';
const CACHE_PAGES = 'awamotos-pages-v3';

// Assets críticos para funcionar offline
const PRECACHE_ASSETS = [
    '/',
    '/offline.html',
    '/manifest.json'
];

// Padrões para identificar tipos de request
const STATIC_EXTENSIONS = /\.(css|js|woff2?|ttf|eot)$/i;
const IMAGE_EXTENSIONS = /\.(jpe?g|png|gif|webp|avif|svg|ico)$/i;
const ADMIN_PATTERN = /\/(admin|checkout|customer\/account)/i;

/**
 * Install Event - Precache assets críticos
 */
self.addEventListener('install', (event) => {
    console.log(`[SW ${SW_VERSION}] Installing...`);

    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then((cache) => {
                // Cache com tolerância a falhas
                return Promise.allSettled(
                    PRECACHE_ASSETS.map(url =>
                        cache.add(url).catch(err => {
                            console.warn(`[SW] Failed to cache: ${url}`, err);
                        })
                    )
                );
            })
            .then(() => {
                console.log(`[SW ${SW_VERSION}] Precache complete`);
                return self.skipWaiting();
            })
    );
});

/**
 * Activate Event - Limpar caches antigos
 */
self.addEventListener('activate', (event) => {
    console.log(`[SW ${SW_VERSION}] Activating...`);

    const currentCaches = [CACHE_STATIC, CACHE_IMAGES, CACHE_PAGES];

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter(name => !currentCaches.includes(name))
                        .map(name => {
                            console.log(`[SW] Deleting old cache: ${name}`);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log(`[SW ${SW_VERSION}] Claiming clients`);
                return self.clients.claim();
            })
    );
});

/**
 * Fetch Event - Roteamento de estratégias
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ignorar non-GET e cross-origin
    if (request.method !== 'GET') return;
    if (url.origin !== self.location.origin) return;

    // Ignorar rotas sensíveis (admin, checkout, account)
    if (ADMIN_PATTERN.test(url.pathname)) return;

    // Ignorar API requests
    if (url.pathname.startsWith('/rest/') || url.pathname.startsWith('/graphql')) return;

    // Roteamento por tipo de asset
    if (STATIC_EXTENSIONS.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(request, CACHE_STATIC));
    } else if (IMAGE_EXTENSIONS.test(url.pathname)) {
        event.respondWith(cacheFirstWithFallback(request, CACHE_IMAGES));
    } else if (request.mode === 'navigate') {
        event.respondWith(networkFirstWithOffline(request));
    }
});

/**
 * Stale-While-Revalidate Strategy
 * Retorna cache imediatamente e atualiza em background
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    // Fetch em background
    const fetchPromise = fetch(request)
        .then((networkResponse) => {
            if (networkResponse && networkResponse.ok) {
                cache.put(request, networkResponse.clone());
            }
            return networkResponse;
        })
        .catch(() => cachedResponse);

    return cachedResponse || fetchPromise;
}

/**
 * Cache First Strategy
 * Para imagens - prioriza cache, fallback para network
 */
async function cacheFirstWithFallback(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);

    if (cachedResponse) {
        return cachedResponse;
    }

    const tryFallbackOriginal = async () => {
        const url = new URL(request.url);

        // Magento: /media/catalog/product/cache/<hash>/<resto>
        // Fallback: /media/catalog/product/<resto>
        const cachePrefixRe = /^\/media\/catalog\/product\/cache\/[^/]+\//i;
        if (!cachePrefixRe.test(url.pathname)) {
            return null;
        }

        url.pathname = url.pathname.replace(cachePrefixRe, '/media/catalog/product/');

        try {
            const fallbackResponse = await fetch(url.toString(), {
                // Imagens geralmente não precisam de headers customizados.
                // Mantemos credenciais same-origin para não quebrar setups com cookies.
                credentials: 'same-origin'
            });

            if (fallbackResponse && fallbackResponse.ok) {
                // Cachear a resposta sob a URL original (a do cache) para evitar repetição do 404.
                cache.put(request, fallbackResponse.clone());
                return fallbackResponse;
            }
        } catch (_) {
            // Ignorar e cair no 404 final.
        }

        return null;
    };

    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
            cache.put(request, networkResponse.clone());
            return networkResponse;
        }

        // Se veio 404 (ou outro erro) em thumb cacheada, tenta original.
        const fallback = await tryFallbackOriginal();
        if (fallback) {
            return fallback;
        }

        return networkResponse;
    } catch (error) {
        const fallback = await tryFallbackOriginal();
        if (fallback) {
            return fallback;
        }

        return new Response('', { status: 404 });
    }
}

/**
 * Network First with Offline Fallback
 * Para navegação - tenta rede primeiro, fallback offline.html
 */
async function networkFirstWithOffline(request) {
    const cache = await caches.open(CACHE_PAGES);

    try {
        const networkResponse = await fetch(request);

        // Cache páginas navegáveis bem-sucedidas
        if (networkResponse && networkResponse.ok) {
            const responseToCache = networkResponse.clone();
            cache.put(request, responseToCache);
        }

        return networkResponse;
    } catch (error) {
        // Tentar cache primeiro
        const cachedResponse = await cache.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Fallback para página offline
        const offlinePage = await caches.match('/offline.html');
        if (offlinePage) {
            return offlinePage;
        }

        // Último recurso: resposta de erro básica
        return new Response(
            '<html><body><h1>Sem conexão</h1><p>Verifique sua internet e tente novamente.</p></body></html>',
            {
                headers: { 'Content-Type': 'text/html' },
                status: 503
            }
        );
    }
}

/**
 * Message Event - Comunicação com a página
 */
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: SW_VERSION });
    }

    if (event.data && event.data.type === 'CLEAR_CACHE') {
        caches.keys().then((names) => {
            Promise.all(names.map(name => caches.delete(name)));
        });
    }
});

console.log(`[SW ${SW_VERSION}] Loaded`);
