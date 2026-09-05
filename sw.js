const NOMBRE_CACHE = 'chambeki-cache-v1';
const RECURSOS_PRECACHE = [
    '/',
    '/offline.html',
    '/user/assets/css/global.css',
    '/user/assets/css/home.css',
    '/user/assets/js/theme.js'
];

self.addEventListener('install', (evento) =>
{
    evento.waitUntil(
        caches.open(NOMBRE_CACHE).then((cache) =>
        {
            return cache.addAll(RECURSOS_PRECACHE);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (evento) =>
{
    evento.waitUntil(
        caches.keys().then((claves) =>
        {
            return Promise.all(
                claves.map((clave) =>
                {
                    if (clave !== NOMBRE_CACHE)
                    {
                        return caches.delete(clave);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', (evento) =>
{
    evento.respondWith(
        fetch(evento.request)
            .then((respuestaRed) =>
            {
                return respuestaRed;
            })
            .catch(async () =>
            {
                const respuestaCache = await caches.match(evento.request);
                if (respuestaCache)
                {
                    return respuestaCache;
                }

                if (evento.request.mode === 'navigate')
                {
                    const paginaOffline = await caches.match('/offline.html');
                    if (paginaOffline)
                    {
                        return paginaOffline;
                    }
                }

                return new Response('Sin conexión a Internet', {
                    status: 503,
                    statusText: 'Servicio no disponible',
                    headers: new Headers({ 'Content-Type': 'text/plain; charset=utf-8' })
                });
            })
    );
});