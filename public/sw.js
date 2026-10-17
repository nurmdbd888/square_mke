/* Square Distributor Manager — offline shell for SR sales drafts */
const CACHE = 'mke-shell-v1';
const SHELL = [
  '/css/app.css',
  '/js/htmx.min.js',
  '/js/alpine.min.js',
  '/js/app.js',
  '/js/offline-sales.js',
  '/manifest.webmanifest',
  '/favicon.ico',
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }
  if (SHELL.some((path) => url.pathname === path || url.pathname.endsWith(path))) {
    event.respondWith(
      caches.match(request).then((cached) => cached || fetch(request).then((response) => {
        const copy = response.clone();
        caches.open(CACHE).then((cache) => cache.put(request, copy));
        return response;
      }))
    );
    return;
  }
  event.respondWith(
    fetch(request).catch(() => caches.match(request))
  );
});
