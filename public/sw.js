const CACHE = 'crm-zcrb-pwa-v3';
const STATIC = ['/manifest.webmanifest','/pwa-icon.svg','/offline.html','/pwa-runtime.js'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(STATIC)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (STATIC.includes(url.pathname)) {
    event.respondWith(caches.match(request).then(hit => hit || fetch(request)));
    return;
  }

  // Рабочие страницы и API никогда не кэшируем.
  // Для обычной навигации при отсутствии сети отдаём только безопасный offline-shell.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // AJAX, файлы и прочие запросы должны честно завершиться ошибкой при отсутствии сети.
  event.respondWith(fetch(request));
});

self.addEventListener('push', event => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; }
  catch (_) { data = { title: 'CRM ЗЦРБ', body: event.data ? event.data.text() : '' }; }

  const title = data.title || 'CRM ЗЦРБ';
  const options = {
    body: data.body || '',
    icon: '/pwa-icon.svg',
    badge: '/pwa-icon.svg',
    tag: data.notification_id ? `crm-${data.notification_id}` : undefined,
    renotify: false,
    data: {
      url: data.url || '/',
      notification_id: data.notification_id || null,
      type: data.type || null,
    },
    actions: [{ action: 'open', title: 'Открыть CRM' }],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const target = event.notification.data?.url || '/';
  const targetUrl = new URL(target, self.location.origin).href;

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of windows) {
      try {
        const clientUrl = new URL(client.url);
        if (clientUrl.origin === self.location.origin) {
          if ('navigate' in client) await client.navigate(targetUrl);
          return client.focus();
        }
      } catch (_) {}
    }
    return self.clients.openWindow(targetUrl);
  })());
});
