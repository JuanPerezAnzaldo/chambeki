const NOMBRE_CACHE = 'chambeki-cache-v6';

self.addEventListener('install', (evento) =>
{
    evento.waitUntil(
        caches.open(NOMBRE_CACHE)
            .then((cache) =>
            {
                return cache.add('/');
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
                    if (!respuestaRed || respuestaRed.status !== 200 || respuestaRed.type !== 'basic')
                    {
                        return respuestaRed;
                    }

                    const respuestaAClonar = respuestaRed.clone();
                    caches.open(NOMBRE_CACHE).then((cache) =>
                    {
                        cache.put(evento.request, respuestaAClonar);
                    });

                    return respuestaRed;
                }).catch(() =>
                {
                    return caches.match('/');
                });
            })
    );
});