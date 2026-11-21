(() => {
  const L = localStorage, S = sessionStorage, box = document.getElementById('pwa');
  const logo = (document.querySelector('link[rel=icon]') || {}).href || '';
  const sa = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
  let reg, dp;
  if (window.lucide) lucide.createIcons();

  // ---- إرسال إحصائيات خفيفة (beacon) ----
  const bc = (a, o) => { try { const f = new FormData(); for (const k in o) f.append(k, o[k]); if (!navigator.sendBeacon('api.php?a=' + a, f)) fetch('api.php?a=' + a, { method: 'POST', body: f, keepalive: true }); } catch (e) {} };
  try {
    if (S.rl) S.rl = '';
    else {
      const pg = location.pathname.split('/').pop().replace('.php', '') || 'index', k = 'h_' + pg, f = !S.vs;
      if (!(Date.now() - (+S[k] || 0) < 18e5)) { S[k] = Date.now(); S.vs = 1; bc('hit', { p: pg, f: f ? 1 : '', r: f ? document.referrer : '', u: new URLSearchParams(location.search).get('utm_source') || '', w: navigator.webdriver ? 1 : '' }); }
    }
  } catch (e) {}
  const tk = t => { const k = (t.dataset.track || '').replace(/[^a-z0-9_]/gi, '').toLowerCase(); if (k) bc('click', { k }); };
  document.addEventListener('click', e => { const t = e.target.closest && e.target.closest('[data-track]'); if (t) tk(t); }, true);
  addEventListener('blur', () => setTimeout(() => { const a = document.activeElement; if (a && a.tagName === 'IFRAME') { const t = a.closest('[data-track]'); if (t && !t._c) { t._c = 1; tk(t); setTimeout(() => t._c = 0, 1500); } } }, 0));
  if (sa && !L.sa1) { L.sa1 = 1; bc('ev', { k: 'standalone' }); }

  // ---- Web Push: اشتراك الجهاز ليصله الإشعار والموقع مغلق ----
  const u8 = s => Uint8Array.from(atob((s + '='.repeat((4 - s.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
  async function psub() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.VAPID || !('Notification' in window) || Notification.permission !== 'granted') return;
    try {
      const r = await navigator.serviceWorker.ready;
      let s = await r.pushManager.getSubscription();
      if (!s) s = await r.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: u8(VAPID) });
      if (!L.psx || Date.now() > +L.psx) { await fetch('api.php?a=sub', { method: 'POST', body: JSON.stringify(s.toJSON()) }); L.psx = Date.now() + 3 * 864e5; }
      L.ps = '1';
      if (!L.ng) { L.ng = 1; bc('ev', { k: 'notif_granted' }); }
    } catch (e) {}
  }
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').then(r => { reg = r; psub(); }).catch(() => {});

  const bar = h => { const d = document.createElement('div'); d.className = 'bg-white text-red-900 rounded-2xl shadow-2xl p-4 text-xs flex items-center gap-3'; d.innerHTML = h; box.appendChild(d); return d; };
  const hide = (d, k, days) => { L[k] = Date.now() + days * 864e5; d.remove(); };
  const showInstall = () => {
    if (sa || Date.now() < +L.inst || !dp) return;
    const d = bar('<img src="' + logo + '" class="h-10 w-10 rounded-lg object-contain" alt=""><div class="flex-1 font-bold">ثبّت تطبيق WSSE على هاتفك للوصول السريع</div><button class="bg-red-700 text-white px-3 py-1.5 rounded-lg font-bold">تثبيت</button><button class="text-gray-500 px-1" aria-label="إغلاق">✕</button>');
    const [b, x] = d.querySelectorAll('button');
    b.onclick = async () => { dp.prompt(); await dp.userChoice; dp = null; d.remove(); };
    x.onclick = () => hide(d, 'inst', 3);
  };
  addEventListener('beforeinstallprompt', e => { e.preventDefault(); dp = e; showInstall(); });
  addEventListener('appinstalled', () => { box.innerHTML = ''; if (!L.i1) { L.i1 = 1; bc('ev', { k: 'install' }); } });
  if (location.hash === '#pwadebug') {
    L.removeItem('inst');
    const d = bar('<div class="flex-1 text-[11px] leading-5" id="pwad"></div>');
    const up = () => { d.querySelector('#pwad').innerHTML = ['حدث beforeinstallprompt: ' + (dp ? '✅ وصل (يمكن التثبيت)' : '⏳ لم يصل'), 'سياق آمن HTTPS: ' + (isSecureContext ? '✅' : '❌'), 'وضع التطبيق الحالي: ' + (sa ? 'نعم (مثبّت)' : 'لا'), 'خدمة العامل: ' + (navigator.serviceWorker && navigator.serviceWorker.controller ? '✅ تتحكم بالصفحة' : '⏳ أعد تحميل الصفحة'), 'تم إلغاء إغلاق الاقتراح المؤقت'].join('<br>'); };
    up(); setInterval(up, 1500);
  }
  if ((/iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) && !sa && !(Date.now() < +L.inst)) {
    const d = bar('<div class="flex-1 font-bold">لتثبيت الموقع: اضغط زر المشاركة ثم «إضافة إلى الشاشة الرئيسية»</div><button class="text-gray-500 px-1" aria-label="إغلاق">✕</button>');
    d.querySelector('button').onclick = () => hide(d, 'inst', 7);
  }
  if ('Notification' in window && Notification.permission === 'default' && !(Date.now() < +L.nt)) {
    const d = bar('<div class="flex-1 font-bold">فعّل الإشعارات لتصلك نتائج وأهداف المباريات</div><button class="bg-amber-400 text-red-950 px-3 py-1.5 rounded-lg font-bold">تفعيل</button><button class="text-gray-500 px-1" aria-label="إغلاق">✕</button>');
    const [b, x] = d.querySelectorAll('button');
    b.onclick = async () => { const p = await Notification.requestPermission(); d.remove(); if (p === 'granted') psub(); else if (p === 'denied') bc('ev', { k: 'notif_denied' }); poll(); };
    x.onclick = () => hide(d, 'nt', 3);
  }
  // تحديث تكيّفي: سريع وقت المباراة فقط، بطيء خارجه، ومتوقف عند إخفاء التبويب
  let delay = 30000, tm;
  const nextDelay = s => {
    if (s.st === 'first' || s.st === 'second') return s.pz ? 15000 : 10000;
    if (s.st === 'half') return 20000;
    if (s.st === 'finished' || s.st === 'cancelled') return 600000;
    const dk = s.dk; if (dk == null) return 300000;
    if (dk > 1800) return Math.min(300, Math.max(30, dk - 1800)) * 1000;
    return dk > -10800 ? 30000 : 300000;
  };
  const idx = document.body.dataset.idx;
  const fm = (per, e, h, run) => { const m = run ? Math.floor(e / 60) + 1 : Math.max(1, Math.ceil(e / 60)), b = per == 2 ? h : 0; return (m > h ? (b + h) + '+' + (m - h) : b + m) + "'"; };
  setInterval(() => {
    const c = window.CLK; if (!c || document.hidden) return;
    const e = c.e + (c.run ? (Date.now() - c.t0) / 1000 : 0);
    document.querySelectorAll('.clk').forEach(x => x.textContent = fm(c.per, e, c.half, c.run));
  }, 1000);
  async function poll() {
    try {
      const s = await (await fetch('api.php', { cache: 'no-store' })).json();
      delay = nextDelay(s);
      const p = JSON.parse(L.ws || 'null'); L.ws = JSON.stringify(s);
      window.CLK = { st: s.st, per: s.per, e: s.e, run: s.run, half: s.half, lim: s.lim, t0: Date.now() };
      if (!p) return;
      const ch = s.n !== p.n || s.st !== p.st || s.pz !== p.pz || (s.rev || 0) !== (p.rev || 0);
      // عند وجود اشتراك Push يتولى الخادم الإشعار، فلا نكرره محلياً
      if (s.n > p.n && s.last && 'Notification' in window && Notification.permission === 'granted' && reg && L.ps !== '1')
        reg.showNotification(s.last.t, { body: s.home + ' ' + s.hs + ' - ' + s.as + ' ' + s.away + (s.last.x ? ' | ' + s.last.x : ''), icon: logo, badge: logo, dir: 'rtl', lang: 'ar', tag: 'wsse', vibrate: [200, 100, 200] });
      if (idx && ch) setTimeout(() => { try { S.rl = 1; } catch (e) {} location.reload(); }, 800);
    } catch (e) {}
  }
  const bgOk = () => 'Notification' in window && Notification.permission === 'granted' && L.ps !== '1';
  function loop() {
    clearTimeout(tm);
    if (document.hidden && !bgOk()) return;
    tm = setTimeout(async () => { await poll(); loop(); }, document.hidden ? Math.max(delay, 30000) : delay);
  }
  document.addEventListener('visibilitychange', () => { if (document.hidden) loop(); else if (idx || bgOk()) poll().then(loop); });
  if (idx || bgOk()) poll().then(loop);
})();
