var urlsToCache = [
  './assets/css/bootstrap.min.css',
  './offline.html',
  './assets/img/avatar.png'
];

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll(urlsToCache);
    })
  );
});

self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request).then(function(response) {
      // Cache hit - return response
      if (response) {
        return response;
      }

      var fetchRequest = event.request.clone();

      return fetch(fetchRequest)
        .then(function(response) {
          // Check if we received a valid response
          if (
            !response ||
            response.status !== 200 ||
            response.type !== 'basic'
          ) {
            return response;
          }

          if ((event.request.url.indexOf('sw.js') !== -1) || 
              (event.request.url.indexOf('notify.json') !== -1)
          ) {
            return response;
          }

          var responseToCache = response.clone();

          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(event.request, responseToCache);
          });

          return response;
        })
        .catch(function(err) {
          if (event.request.headers.get('Accept').indexOf('text/html') !== -1) {
            return caches.match('./offline.html');
          } else if (event.request.headers.get('Accept').indexOf('image') !== -1) {
            return caches.match('./assets/img/avatar.png');
          } else {
            console.log(err);
          }
        });
    })
  );
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            console.log('Deleting cache ' + cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

self.addEventListener('message', function(event) {
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
});
