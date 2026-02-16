const CACHE_NAME = "rcp-system-v3";

// Ścieżki względne – zadziałają i dla /Rcp/, i dla root
const FILES_TO_CACHE = [
  "index.html",
  "panel.php",
  "manifest.json"
];

self.addEventListener("install", event => {
  console.log('[SW] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW] Caching files');
        return cache.addAll(FILES_TO_CACHE);
      })
      .catch(err => {
        console.error('[SW] Cache failed:', err);
      })
  );
  self.skipWaiting();
});

self.addEventListener("activate", event => {
  console.log('[SW] Activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('[SW] Deleting old cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

self.addEventListener('fetch', event => {
  // Network first, jeśli błąd sieci – bezpieczny fallback do cache
  event.respondWith((async () => {
    try {
      const networkResponse = await fetch(event.request);
      return networkResponse;
    } catch (e) {
      const cached = await caches.match(event.request);
      if (cached) {
        return cached;
      }
      // Zwracamy poprawny Response, żeby uniknąć błędów typu
      // "object that was not a Response was passed to respondWith()"
      return new Response('Offline', {
        status: 503,
        statusText: 'Service Unavailable'
      });
    }
  })());
});

// Obsługa zdarzenia PUSH (Web Push)
self.addEventListener('push', event => {
  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { body: event.data.text() };
    }
  }

  const title = data.title || 'RCP System';
  const options = {
    body: data.body || '',
    icon: 'icons/icon-192.png',
    badge: 'icons/icon-72.png',
    // Używamy ścieżki względnej (panel.php)
    data: data.url || 'panel.php',
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Reakcja na kliknięcie w powiadomienie
self.addEventListener('notificationclick', event => {
  event.notification.close();

  // Ścieżka względna – dopasuje się do scope SW
  const targetUrl = event.notification.data || 'index.html';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      for (const client of clientList) {
        // Jeśli jest już otwarte jakieś okno tej aplikacji, użyj go
        if (client.url.includes('panel.php') || client.url.includes('index.html')) {
          client.focus();
          client.navigate(targetUrl);
          return;
        }
      }
      return clients.openWindow(targetUrl);
    })
  );
});

// ====== WEWNĘTRZNY SCHEDULER ======
// Ping schedulera co 15 minut (900000ms)
let schedulerInterval;

// Funkcja pingująca scheduler
const pingScheduler = async () => {
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
    
    const response = await fetch('cron/scheduler_hook.php', {
      method: 'GET',
      headers: {
        'X-Scheduler-Trigger': 'service-worker'
      },
      signal: controller.signal
    });
    
    clearTimeout(timeoutId);
    
    if (response.ok) {
      const data = await response.json();
      console.log('[SW] Scheduler pinged successfully:', data.timestamp);
    } else {
      console.warn('[SW] Scheduler ping failed:', response.status);
      // Spróbuj odczytać błąd
      try {
        const error = await response.json();
        console.warn('[SW] Error details:', error);
      } catch (e) {
        console.warn('[SW] Could not parse error response');
      }
    }
  } catch (error) {
    if (error.name === 'AbortError') {
      console.warn('[SW] Scheduler ping timeout');
    } else {
      console.error('[SW] Scheduler ping error:', error.message);
    }
  }
};

// Uruchom scheduler przy instalacji SW
self.addEventListener('install', event => {
  console.log('[SW] Installing with scheduler support...');
  // Nie czekaj na ping schedulera przy instalacji - pozwól SW zainstalować się szybko
  self.skipWaiting();
});

// Ustaw interwał przy aktywacji
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'START_SCHEDULER') {
    // Wyczyść poprzedni interwał jeśli istnieje
    if (schedulerInterval) {
      clearInterval(schedulerInterval);
    }
    
    // Wykonaj natychmiast (nie czekaj na wynik)
    pingScheduler().catch(err => {
      console.error('[SW] Failed to start scheduler:', err);
    });
    
    // Następnie co 15 minut
    schedulerInterval = setInterval(() => {
      pingScheduler().catch(err => {
        console.error('[SW] Scheduler ping failed:', err);
      });
    }, 15 * 60 * 1000);
    
    console.log('[SW] Scheduler interval started (15 min)');
  }
  // Nie zwracaj nic - nie oczekujemy odpowiedzi
});

