// sw.js

const CACHE_NAME = 'bibel-app-cache-v7'; 

// Liste der Dateien, die immer gecacht werden sollen (die "App-Hülle")
const urlsToCache = [
  '/',                        // Deine Startseite (oft index.php)
  '/index.php',               // Explizit, falls unterschiedlich zu '/'
  //'/dashboard.php',           // Wichtige, geänderte Seite
  // '/news_article.php',     // Die *Grundstruktur* der Artikelseite, falls gewünscht.
                              // Die individuellen Artikel selbst werden nicht hier gecacht.

  // Cache-Busting für geänderte Assets:
  '/styles.css?v=3',          // Erhöhe Version, wenn styles.css geändert wurde
  '/app.js?v=5',              // Erhöhe Version, wenn app.js geändert wurde
  '/dashboard.js?v=1',        // Erhöhe Version, wenn dashboard.js geändert wurde (oder v=1 wenn neu)
  // '/helpers.php',          // PHP-Dateien, die nur via include genutzt werden, müssen NICHT hier rein.
                              // Deren Änderungen wirken sich auf die PHP-Seiten aus, die sie includen (z.B. dashboard.php)

  // Statische Assets (Icons etc.)
  '/512ohneBG.png',           // Dein Logo, falls es sich geändert hat oder neu ist
  '/192.png',
  '/512.png',
  '/manifest.json',           // Die Manifest-Datei

  // Externe Bibliotheken (werden vom CDN gecacht, aber hier für die App-Shell-Definition)
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' // Wenn du Bootstrap JS auch offline brauchst
];

// Event: Installation des Service Workers
self.addEventListener('install', event => {
  console.log('Service Worker: Installiere Version', CACHE_NAME);
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache geöffnet:', CACHE_NAME);
        // Wichtig: addAll ist atomar. Wenn eine Datei nicht geladen werden kann, schlägt das ganze addAll fehl.
        // Stelle sicher, dass alle URLs korrekt sind und erreichbar.
        return cache.addAll(urlsToCache.map(url => new Request(url, { cache: 'reload' }))); // 'reload' um sicherzustellen, dass frische Kopien vom Server geholt werden
      })
      .then(() => {
        // Optional: self.skipWaiting() hier aufrufen, wenn der neue SW sofort aktiv werden soll.
        // Besser ist es oft, dies über eine User-Interaktion (z.B. "Update installieren"-Button) zu steuern.
        // Für eine einfache Lösung kannst du es hier aktivieren:
        // return self.skipWaiting();
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
      // Nachdem alte Caches gelöscht wurden, kann der neue SW die Kontrolle über alle offenen Clients beanspruchen.
      return self.clients.claim();
    })
  );
});

// Event: Abfangen von Netzwerk-Anfragen
self.addEventListener('fetch', event => {
  // Wir wollen PHP-Seiten und API-Aufrufe (wie ajax_handler_news.php) in der Regel
  // nicht aggressiv aus dem Cache bedienen, es sei denn, sie sind explizit als App-Shell-Teil definiert
  // oder wir haben eine "Network falling back to Cache" Strategie für sie.

  const requestUrl = new URL(event.request.url);

  // Für Anfragen an CDNs (Bootstrap etc.), die in urlsToCache sind, ist Cache-First gut.
  // Für lokale Assets (CSS, JS, Bilder), die in urlsToCache sind, ebenfalls.
  if (urlsToCache.some(cachedUrl => requestUrl.pathname === new URL(cachedUrl, self.location.origin).pathname || requestUrl.href === cachedUrl )) {
    event.respondWith(
      caches.match(event.request)
        .then(response => {
          if (response) {
            return response; // Aus dem Cache bedienen
          }
          // Wenn eine in urlsToCache definierte Ressource nicht im Cache ist (sollte nicht passieren nach erfolgreicher Installation),
          // hole sie vom Netzwerk. Hier könntest du sie optional auch zum Cache hinzufügen.
          return fetch(event.request).then(networkResponse => {
            // Optional: Hier die Antwort zum Cache hinzufügen, wenn es eine Shell-Ressource war, die fehlte.
            // Aber normalerweise sollte sie bei der Installation gecacht worden sein.
            return networkResponse;
          });
        })
    );
    return; // Wichtig: Bearbeitung hier beenden
  }

  // Für andere Anfragen (z.B. dynamische PHP-Seiten, API-Calls): Network First Strategie
  // Dies stellt sicher, dass du immer die neuesten Daten von news_article.php oder ajax_handler_news.php bekommst,
  // solange eine Netzwerkverbindung besteht.
  // Optional: Bei Fehlschlag auf Cache zurückgreifen (Network falling back to Cache).
  if (requestUrl.pathname.endsWith('.php') || requestUrl.pathname.includes('ajax_handler')) {
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          // Optional: Die erfolgreiche Netzwerkantwort für bestimmte dynamische Inhalte cachen.
          // Dies ist komplexer, da man entscheiden muss, wie lange und welche Antworten gecacht werden.
          // Beispiel:
          // if (networkResponse && networkResponse.ok && event.request.method === 'GET' && requestUrl.pathname.startsWith('/news_article.php')) {
          //   const responseToCache = networkResponse.clone();
          //   caches.open(CACHE_NAME + '-dynamic').then(cache => { // Eigener Cache für dynamische Inhalte
          //     cache.put(event.request, responseToCache);
          //   });
          // }
          return networkResponse;
        })
        .catch(() => {
          // Netzwerkfehler: Versuche, aus dem Cache zu antworten (falls zuvor gecacht)
          // Dies ist nützlich, wenn du eine Offline-Version einer Artikelseite haben möchtest,
          // die der Nutzer bereits einmal online besucht hat.
          // return caches.match(event.request);
          // Oder eine generische Offline-Seite anzeigen:
          // return caches.match('/offline.html'); // Wenn du eine offline.html gecacht hast
          
          // Für den Moment: Einfach den Netzwerkfehler durchlassen, wenn keine spezielle Offline-Logik implementiert ist
          // für dynamische Seiten. Der Browser zeigt dann seine Standard-Offline-Meldung.
          console.warn('Service Worker: Netzwerk-Anfrage fehlgeschlagen, keine Cache-Alternative für', event.request.url);
          // Hier könntest du eine Response mit einem Fehlerstatus erstellen, wenn gewollt
        })
    );
    return;
  }

  // Standard-Verhalten für alle anderen Anfragen: Versuche Cache, dann Netzwerk (wie oben für urlsToCache)
  // oder einfach nur Netzwerk, wenn es sich nicht um bekannte Shell-Assets handelt.
  // Die Logik oben deckt bereits die urlsToCache ab.
  // Für alles andere, was nicht explizit behandelt wird, hier einfach Netzwerk:
  event.respondWith(fetch(event.request));
});


// Listener für Nachrichten von der Client-Seite (z.B. von app.js für skipWaiting)
self.addEventListener('message', event => {
  if (event.data && event.data.action === 'skipWaiting') {
    console.log('Service Worker: skipWaiting wurde von Client aufgerufen.');
    self.skipWaiting();
  }
});