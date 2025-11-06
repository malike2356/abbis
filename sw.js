const CACHE = 'abbis-offline-v1';
const OFFLINE_URLS = [
  '/abbis3.2/offline-field-report.html',
  '/abbis3.2/assets/js/location-picker.js',
  '/abbis3.2/assets/css/styles.css'
];
self.addEventListener('install', (e)=>{
  e.waitUntil(caches.open(CACHE).then(c=>c.addAll(OFFLINE_URLS)));
});
self.addEventListener('fetch', (e)=>{
  e.respondWith(
    caches.match(e.request).then(res=> res || fetch(e.request).catch(()=> caches.match('/abbis3.2/offline-field-report.html')))
  );
});

