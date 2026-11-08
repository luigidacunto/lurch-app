const CACHE    = 'lurch-v2';
const OFFLINE  = '/offline.html';

self.addEventListener('install', (e) => {
    e.waitUntil(
        caches.open(CACHE).then((cache) => cache.add(OFFLINE))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    // Rimuove le versioni precedenti della cache
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    // Intercetta solo richieste di navigazione (pagine HTML, non asset/API)
    if (e.request.mode !== 'navigate') return;

    e.respondWith(
        fetch(e.request).catch(() => caches.match(OFFLINE))
    );
});
