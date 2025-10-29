/**
 * Service Worker для Geniled KP Generator
 * =========================================
 * ✅ Кэширование статических ресурсов
 * ✅ Offline fallback
 * ✅ Стратегия Cache-First для статики
 * ✅ Network-First для данных
 */

const CACHE_VERSION = 'kp-gen-v1.0';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DATA_CACHE = `${CACHE_VERSION}-data`;

// Файлы для кэширования
const STATIC_FILES = [
    '/local/tools/kp_gen4/',
    '/local/tools/kp_gen4/index.php',
    '/local/tools/kp_gen4/assets/css/styles.css',
    '/local/tools/kp_gen4/assets/js/app.js',
    '/local/tools/kp_gen4/assets/js/ui.js',
    '/local/tools/kp_gen4/assets/js/product-manager.js',
];

// Установка Service Worker
self.addEventListener('install', (event) => {
    console.log('[SW] Установка Service Worker версии', CACHE_VERSION);

    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Кэширование статических файлов');
                return cache.addAll(STATIC_FILES);
            })
            .then(() => self.skipWaiting())
    );
});

// Активация Service Worker
self.addEventListener('activate', (event) => {
    console.log('[SW] Активация Service Worker');

    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames
                        .filter(cacheName => {
                            // Удаляем старые версии кэша
                            return cacheName.startsWith('kp-gen-') &&
                                cacheName !== STATIC_CACHE &&
                                cacheName !== DATA_CACHE;
                        })
                        .map(cacheName => {
                            console.log('[SW] Удаление старого кэша:', cacheName);
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(() => self.clients.claim())
    );
});

// Обработка запросов
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Пропускаем запросы к другим доменам
    if (url.origin !== location.origin) {
        return;
    }

    // Определяем стратегию кэширования
    if (isStaticAsset(url.pathname)) {
        // Cache-First для статики
        event.respondWith(cacheFirst(request));
    } else if (isDataRequest(request)) {
        // Network-First для данных
        event.respondWith(networkFirst(request));
    } else {
        // По умолчанию - сеть
        event.respondWith(fetch(request));
    }
});

/**
 * Проверка, является ли файл статическим ресурсом
 */
function isStaticAsset(pathname) {
    return pathname.endsWith('.css') ||
        pathname.endsWith('.js') ||
        pathname.endsWith('.png') ||
        pathname.endsWith('.jpg') ||
        pathname.endsWith('.jpeg') ||
        pathname.endsWith('.svg') ||
        pathname.endsWith('.webp') || // Добавлено для поддержки нового формата изображений
        pathname.endsWith('.woff') ||
        pathname.endsWith('.woff2');
}

/**
 * Проверка, является ли запрос за данными
 */
function isDataRequest(request) {
    return request.method === 'POST' ||
        request.url.includes('ajax') ||
        request.url.includes('api');
}

/**
 * Стратегия Cache-First
 * Сначала проверяем кэш, если нет - загружаем из сети
 */
async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        console.log('[SW] Cache-First HIT:', request.url);
        return cached;
    }

    console.log('[SW] Cache-First MISS:', request.url);

    try {
        const response = await fetch(request);

        // Кэшируем успешный ответ
        if (response.ok) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.error('[SW] Ошибка загрузки (сеть недоступна или сбой):', request.url, error);

        // 1. Возвращаем офлайн-страницу для HTML
        if (request.destination === 'document') {
            return caches.match('/offline.html');
        }

        // 2. Возвращаем серый SVG-плейсхолдер для изображений, если сетевой запрос не удался
        if (request.destination === 'image' || request.url.match(/\.(png|jpg|jpeg|svg|webp)$/i)) {
            // Создаем простой SVG-плейсхолдер
            const svg = `<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg" style="background:#f0f0f0; border:1px dashed #ccc;">
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="10" fill="#999">Offline</text>
            </svg>`;
            return new Response(svg, {
                headers: { 'Content-Type': 'image/svg+xml' },
                status: 503,
                statusText: 'Service Unavailable (Offline Placeholder)'
            });
        }

        // 3. Для остальных ресурсов (шрифты, скрипты, стили)
        // Возвращаем синтетический ответ 503
        return new Response('', {
            status: 503,
            statusText: 'Service Unavailable (Offline Cache Fail)'
        });
    }
}

/**
 * Стратегия Network-First
 * Сначала пытаемся загрузить из сети, при ошибке - из кэша
 */
async function networkFirst(request) {
    const cache = await caches.open(DATA_CACHE);

    try {
        const response = await fetch(request);

        // Кэшируем только успешные GET-запросы
        if (response.ok && request.method === 'GET') {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.log('[SW] Network-First fallback к кэшу:', request.url);

        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }

        // Если нет ни сети, ни кэша, возвращаем синтетический ответ
        return new Response('', {
            status: 503,
            statusText: 'Service Unavailable (No Cache, No Network)'
        });
    }
}

/**
 * Обработка сообщений от клиента
 */
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => caches.delete(cacheName))
                );
            }).then(() => {
                event.ports[0].postMessage({ success: true });
            })
        );
    }
});
