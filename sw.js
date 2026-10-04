const NOMBRE_CACHE = 'chambeki-cache-v7';
const PAGINA_OFFLINE = '/offline.html';

self.addEventListener('install', (evento) =>
{
    evento.waitUntil(
        caches.open(NOMBRE_CACHE)
            .then((cache) => cache.add(PAGINA_OFFLINE))
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
    const peticion = evento.request;

    if (peticion.method !== 'GET')
    {
        return;
    }

    const url = new URL(peticion.url);

    //solo se manejan peticiones del mismo origen
    if (url.origin !== self.location.origin)
    {
        return;
    }

    //Paginas PHP (dependen de la sesion): siempre red, nunca cache.
    //Si no hay conexion se muestra offline.html
    if (peticion.mode === 'navigate')
    {
        evento.respondWith(
            fetch(peticion).catch(() => caches.match(PAGINA_OFFLINE))
        );
        return;
    }

    //Estaticos (css, js, imagenes, fuentes): responde rapido desde cache
    //y se actualiza en segundo plano
    const esEstatico = ['style', 'script', 'image', 'font'].includes(peticion.destination);

    if (!esEstatico)
    {
        return;
    }

    evento.respondWith(
        caches.open(NOMBRE_CACHE).then((cache) =>
        {
            return cache.match(peticion).then((respuestaCache) =>
            {
                const peticionRed = fetch(peticion).then((respuestaRed) =>
                {
                    if (respuestaRed && respuestaRed.status === 200 && respuestaRed.type === 'basic')
                    {
                        cache.put(peticion, respuestaRed.clone());
                    }

                    return respuestaRed;
                }).catch(() => respuestaCache);

                return respuestaCache || peticionRed;
            });
        })
    );
});

