// sw.js - Service Worker for offline caching and retro 2011 reveal assets.
// Place at site root and register from pages that need it.
const CACHE_NAME = 'kidsmaster-v1';
const ASSETS = [
  '/',
  '/index.php',
  '/assets/css/style.css',
  '/assets/css/retro2011.css',
  '/assets/js/retro2011.js',
  '/assets/js/compat.js',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => {
      if (k !== CACHE_NAME) return caches.delete(k);
    })))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  // network-first for API endpoints, cache-first for assets
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/api') || url.pathname.startsWith('/ajax')) {
    event.respondWith(fetch(event.request).catch(()=>caches.match('/offline.html')));
    return;
  }
  event.respondWith(
    caches.match(event.request).then(cached => cached || fetch(event.request).then(resp => {
      // cache fetched asset if same-origin and GET
      if (resp && resp.type === 'basic' && event.request.method === 'GET') {
        const copy = resp.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
      }
      return resp;
    }).catch(()=>caches.match('/offline.html')))
  );
});