const CACHE = 'wsse2';
self.addEventListener('install', () => self.skipWaiting());
// حذف أي كاش قديم لتفادي تعارض النسخ ثم السيطرة على الصفحات
self.addEventListener('activate', e => e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => clients.claim())));
self.addEventListener('fetch', e => {
  const r = e.request;
  if (r.method !== 'GET' || r.mode !== 'navigate' || r.url.includes('admin.php')) return;
  e.respondWith(fetch(r).then(x => { if (x.ok && x.type === 'basic') { const c = x.clone(); caches.open(CACHE).then(k => k.put(r, c)).catch(() => {}); } return x; })
    .catch(() => caches.match(r).then(x => x || new Response('لا يوجد اتصال بالإنترنت', { status: 503, headers: { 'Content-Type': 'text/plain;charset=utf-8' } }))));
});
// إشعار يصل من الخادم (Web Push) حتى والموقع مغلق: نجلب آخر حدث/إعلان من api.php
self.addEventListener('push', e => e.waitUntil((async () => {
  let t = 'WSSE', b = '', ic;
  try {
    const s = await (await fetch('api.php', { cache: 'no-store' })).json(), a = s.ann, l = s.last; ic = s.logo;
    if (a && (!l || a.ts >= l.ts)) { t = a.t; b = a.b || ''; }
    else if (l) { t = l.t; b = s.home + ' ' + s.hs + ' - ' + s.as + ' ' + s.away + (l.x ? ' | ' + l.x : ''); }
  } catch (_) {}
  return self.registration.showNotification(t, { body: b, icon: ic, badge: ic, tag: 'wsse', renotify: true, dir: 'rtl', lang: 'ar', vibrate: [200, 100, 200] });
})()));
self.addEventListener('notificationclick', e => {
  e.notification.close();
  e.waitUntil(clients.matchAll({ type: 'window' }).then(l => l.length ? l[0].focus() : clients.openWindow('index.php')));
});
