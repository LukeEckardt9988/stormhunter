const CACHE_NAME = 'bibel-app-cache-v2'; // NEUE VERSION HIER!
// Liste der Dateien, die immer gecacht werden sollen (die "App-Hülle")
// Tipp: Fügen Sie hier auch Cache-Busting Parameter hinzu, wenn sich Dateien ändern
const urlsToCache = [
  '/',
  '/index.php',
  '/styles.css?v=2', // Beispiel: ?v=2 wenn sich styles.css geändert hat
  '/app.js?v=2',     // Beispiel: ?v=2 wenn sich app.js geändert hat
  '/192.png',        // Wenn Icons sich nicht ändern, kein ?v= nötig
  '/512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
];

// Event: Installation des Service Workers
self.addEventListener('install', event => {
  console.log('Service Worker: Installiere Version', CACHE_NAME);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache geöffnet:', CACHE_NAME);
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        // Optional, aber oft empfohlen für schnellere Aktivierung neuer SWs,
        // besonders wenn man die "Update verfügbar"-Benachrichtigung (skipWaiting) nicht manuell implementiert.
        // Wenn Sie skipWaiting hier verwenden, wird der neue SW schneller aktiv,
        // aber Clients könnten kurzzeitig eine Mischung aus altem Client-Code und neuem Cache haben.
        // Für die robusteste Lösung mit User-Benachrichtigung (siehe meine vorige ausführliche Antwort) lassen Sie dies hier weg
        // und steuern skipWaiting über eine postMessage von der Client-Seite.
        // Für eine einfache Lösung KÖNNEN Sie es hier aktivieren:
        // return self.skipWaiting(); 
      })
  );
});

// NEU: Event zum Aufräumen alter Caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Aktiviere Version', CACHE_NAME);
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) { // Alle Caches löschen, die nicht dem aktuellen CACHE_NAME entsprechen
            console.log('Service Worker: Lösche alten Cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      // Nachdem alte Caches gelöscht wurden, kann der neue SW die Kontrolle über alle offenen Clients beanspruchen.
      return self.clients.claim();
    })
  );
});

// Event: Abfangen von Netzwerk-Anfragen (Ihre bestehende Logik ist hier gut für "Cache First")
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response; // Aus dem Cache bedienen
        }
        // Wenn nicht im Cache, vom Netzwerk holen
        // Optional: Die Netzwerkantwort hier auch zum Cache hinzufügen für zukünftige Offline-Nutzung
        return fetch(event.request).then(
          networkResponse => {
            // Überprüfen, ob wir eine gültige Antwort erhalten haben
            // und ob es sich um eine GET-Anfrage handelt (andere Typen wie POST sollten nicht gecacht werden)
            if(!networkResponse || networkResponse.status !== 200 || event.request.method !== 'GET') {
              return networkResponse;
            }

            // WICHTIG: Klonen Sie die Antwort. Eine Antwort ist ein Stream und
            // kann nur einmal konsumiert werden. Wir müssen eine Kopie für den Browser
            // und eine für den Cache erstellen.
            // Wir wollen hier aber nur die "App-Hülle" aggressiv cachen,
            // andere dynamische Inhalte oder API-Aufrufe sollten eine andere Cache-Strategie haben.
            // Für die in urlsToCache definierten Dateien ist das okay.
            // Für andere Anfragen könnte man hier entscheiden, sie nicht zu cachen oder eine Network-First-Strategie zu fahren.
            // In Ihrem Fall, da Sie hauptsächlich die urlsToCache bedienen, ist die Logik oben i.O.

            /* Beispiel für dynamisches Caching von Netzwerk-Antworten (vorsichtig einsetzen):
            const responseToCache = networkResponse.clone();
            caches.open(CACHE_NAME)
              .then(cache => {
                // Nur bestimmte Dateitypen oder Pfade cachen, um nicht alles vollzumüllen
                if (event.request.url.includes('.png') || event.request.url.includes('.jpg')) {
                     cache.put(event.request, responseToCache);
                }
              });
            */
            return networkResponse;
          }
        );
      }
    )
  );
});

// NEU (Optional, aber empfohlen für die "Update verfügbar"-Benachrichtigung):
// Listener für Nachrichten von der Client-Seite (z.B. von app.js)
self.addEventListener('message', event => {
  if (event.data && event.data.action === 'skipWaiting') {
    console.log('Service Worker: skipWaiting wurde von Client aufgerufen.');
    self.skipWaiting();
  }
});

/*const CACHE_NAME = 'bibel-app-cache-v1';
// Liste der Dateien, die immer gecacht werden sollen (die "App-Hülle")
const urlsToCache = [
  '/',
  '/index.php',
  '/styles.css',
  '/app.js',
  '/192.png',
  '/512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
];

// Event: Installation des Service Workers
self.addEventListener('install', event => {
  // Warte, bis der Cache geöffnet und alle Dateien hinzugefügt wurden
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache geöffnet');
        return cache.addAll(urlsToCache);
      })
  );
});

// Event: Abfangen von Netzwerk-Anfragen
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Wenn die angeforderte Ressource im Cache gefunden wird, gib sie von dort zurück
        if (response) {
          return response;
        }
        // Ansonsten, führe die normale Netzwerkanfrage aus
        return fetch(event.request);
      }
    )
  );
});*/