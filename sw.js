self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open('mi-cache-offline').then((cache) => {
            return cache.addAll([
                '/offline.html' 
            ]);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    console.log('Service Worker activado');
});

self.addEventListener('fetch', (e) => {
    e.respondWith(
        fetch(e.request).catch(() => {
            return caches.match(e.request).then((respuestaCacheadas) => {
                if (respuestaCacheadas) {
                    return respuestaCacheadas;
                }
                
                // Si intenta entrar a la página principal o navegar y no hay red, devolvemos el offline.html
                if (e.request.mode === 'navigate') {
                    return caches.match('/offline.html');
                }
            });
        })
    );
});