// sw.js

// 1. CACHE-NAMEN ERHÖHEN (von v7 auf v8)
const CACHE_NAME = 'bibel-app-cache-v9'; 

// Liste der Dateien, die immer gecacht werden sollen (die "App-Hülle")
const urlsToCache = [
  '/',                        
  '/index.php',
  '/farblegende.php',         // NEU: Farblegende zur App-Hülle hinzugefügt
  // '/dashboard.php',        
  // '/news_article.php',     

  // Cache-Busting für geänderte Assets:
  '/styles.css?v=3',          // Unverändert, falls keine CSS-Änderungen
  '/app.js?v=7',              // 2. VERSION FÜR APP.JS ERHÖHEN (von v5 auf v6)
  '/dashboard.js?v=1',        
  
  // Statische Assets (Icons etc.)
  '/512ohneBG.png',           
  '/192.png',
  '/512.png',
  '/manifest.json',           

  // Externe Bibliotheken
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'
];

// Event: Installation des Service Workers
self.addEventListener('install', event => {
  console.log('Service Worker: Installiere Version', CACHE_NAME);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache geöffnet:', CACHE_NAME);
        return cache.addAll(urlsToCache.map(url => new Request(url, { cache: 'reload' }))); 
      })
      .then(() => {
        console.log('Service Worker: Installation abgeschlossen für', CACHE_NAME);
      })
      .catch(error => {
        console.error('Service Worker: Fehler bei der Installation und dem Cachen der App-Shell:', error);
      })
  );
});

// Event: Aktivierung des Service Workers (Aufräumen alter Caches)
self.addEventListener('activate', event => {
  console.log('Service Worker: Aktiviere Version', CACHE_NAME);
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Lösche alten Cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Alte Caches gelöscht, beanspruche Clients.');
      return self.clients.claim();
    })
  );
});

// Event: Abfangen von Netzwerk-Anfragen (Dieser Teil bleibt unverändert)
self.addEventListener('fetch', event => {
  const requestUrl = new URL(event.request.url);

  if (urlsToCache.some(cachedUrl => requestUrl.pathname === new URL(cachedUrl, self.location.origin).pathname || requestUrl.href === cachedUrl )) {
    event.respondWith(
      caches.match(event.request)
        .then(response => {
          if (response) {
            return response;
          }
          return fetch(event.request).then(networkResponse => {
            return networkResponse;
          });
        })
    );
    return;
  }

  if (requestUrl.pathname.endsWith('.php') || requestUrl.pathname.includes('ajax_handler')) {
    event.respondWith(
      fetch(event.request)
        .catch(() => {
          console.warn('Service Worker: Netzwerk-Anfrage fehlgeschlagen, keine Cache-Alternative für', event.request.url);
        })
    );
    return;
  }
  
  event.respondWith(fetch(event.request));
});


// Listener für Nachrichten von der Client-Seite (Dieser Teil bleibt unverändert)
self.addEventListener('message', event => {
  if (event.data && event.data.action === 'skipWaiting') {
    console.log('Service Worker: skipWaiting wurde von Client aufgerufen.');
    self.skipWaiting();
  }
});