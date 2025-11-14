const CACHE = 'abbis-offline-v2';
const OFFLINE_URLS = [
  '/abbis3.2/offline/',
  '/abbis3.2/offline/index.html',
  '/abbis3.2/assets/js/offline-reports.js',
  '/abbis3.2/assets/css/styles.css',
  '/abbis3.2/manifest.webmanifest'
];

// Install event - cache offline resources
self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then(cache => {
      return cache.addAll(OFFLINE_URLS).catch(err => {
        console.log('Cache addAll failed:', err);
      });
    })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (e) => {
  // Skip non-GET requests
  if (e.request.method !== 'GET') {
    return;
  }
  
  // Skip API requests (let them fail gracefully when offline)
  if (e.request.url.includes('/api/')) {
    return;
  }
  
  e.respondWith(
    caches.match(e.request).then(response => {
      // Return cached version if available
      if (response) {
        return response;
      }
      
      // Try to fetch from network
      return fetch(e.request).then(response => {
        // Cache successful responses
        if (response.status === 200) {
          const responseToCache = response.clone();
          caches.open(CACHE).then(cache => {
            cache.put(e.request, responseToCache);
          });
        }
        return response;
      }).catch(() => {
        // If offline and no cache, return offline page for navigation requests
        if (e.request.mode === 'navigate') {
          return caches.match('/abbis3.2/offline/') || caches.match('/abbis3.2/offline/index.html');
        }
      });
    })
  );
});

// Background sync for offline reports (if supported)
self.addEventListener('sync', (e) => {
  if (e.tag === 'sync-offline-reports') {
    e.waitUntil(syncOfflineReports());
  }
});

// Function to sync offline reports (called by background sync)
async function syncOfflineReports() {
  // This would be called by the background sync API
  // The actual sync is handled by the main JavaScript
  return Promise.resolve();
}

