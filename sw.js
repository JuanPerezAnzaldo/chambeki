const NOMBRE_CACHE = 'chambeki-cache-v5';
const ARCHIVOS_CACHE = [
    '/',
    '/user/assets/css/global.css',
    '/user/assets/css/home.css',
    '/user/assets/js/app.js',
    '/user/assets/js/ubicacion.js',
    '/user/assets/js/theme.js'
];

window.addEventListener = undefined; // Previene errores si se copia código de navegador

self.addEventListener('install', (evento) =>
{
    evento.waitUntil(
        caches.open(NOMBRE_CACHE)
            .then((cache) =>
            {
                return cache.addAll(ARCHIVOS_CACHE);
            })
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (evento) =>
{
    evento.waitUntil(
        caches.keys().then((nombresCaches) =>
        {
            return Promise.all(
                nombresCaches.map((cache) =>
                {
                    if (cache !== NOMBRE_CACHE)
                    {
                        return caches.delete(cache);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (evento) =>
{
    // Solo intercepta peticiones GET estándar
    if (evento.request.method !== 'GET')
    {
        return;
    }

    evento.respondWith(
        caches.match(evento.request)
            .then((respuestaCache) =>
            {
                if (respuestaCache)
                {
                    return respuestaCache;
                }

                return fetch(evento.request).then((respuestaRed) =>
                {
                    // Si la respuesta es inválida o externa, la retorna directamente sin cachear
                    if (!respuestaRed || respuestaRed.status !== 200 || respuestaRed.type !== 'basic')
                    {
                        return respuestaRed;
                    }

                    const respuestaAEliminarCache = respuestaRed.clone();
                    caches.open(NOMBRE_CACHE).then((cache) =>
                    {
                        cache.put(evento.request, respuestaAEliminarCache);
                    });

                    return respuestaRed;
                }).catch(() =>
                {
                    // Fallback offline genérico si falla la red
                    return new Response('Conexión perdida con CHAMBEKI', {
                        status: 503,
                        statusText: 'Service Unavailable',
                        headers: new Headers({ 'Content-Type': 'text/plain; charset=utf-8' })
                    });
                });
            })
    );
});