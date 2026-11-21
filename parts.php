<?php
// عناصر العرض الخاصة بالصفحة الرئيسية والصفحات المستقلة (index.php)
function pg_back($bk) { return in_array($bk, ['fanvote', 'matchpredictions'], true) ? 'index.php?' . $bk . '=1' : 'index.php'; }
function fb_html($fb) {
    if (!$fb) return '';
    return '<div class="p-4 rounded-2xl text-center font-bold shadow-lg ' . ($fb[1] === 'success' ? 'bg-emerald-600/90 border border-emerald-400' : ($fb[1] === 'warning' ? 'bg-amber-600/90 border border-amber-400' : 'bg-rose-600/90 border border-rose-400')) . '">' . e($fb[0]) . '</div>';
}
function std_open($title, $fb, $w = 'max-w-3xl') { head($title); echo '<div class="' . e($w) . ' w-full mx-auto my-auto space-y-4">' . fb_html($fb); }
function std_close() { echo '<div class="text-center pt-2"><a href="index.php" data-track="back_home" class="text-xs text-red-200 hover:underline">العودة للرئيسية</a></div></div>'; foot(); exit; }

// ---------- التوقعات ----------
function predict_form_html($m, $pclosed, $bk = '') {
    if ($pclosed) return '<p class="text-xs text-gray-200">باب التوقعات مغلق لهذه المباراة.</p>';
    ob_start(); ?>
<form method="POST" class="space-y-3">
<input type="hidden" name="t" value="<?= tok() ?>"><input type="hidden" name="action" value="predict"><input type="hidden" name="bk" value="<?= e($bk) ?>">
<input type="text" name="web" class="hidden" tabindex="-1" autocomplete="off">
<input type="text" name="fan_name" required maxlength="60" placeholder="اسمك الكريم" class="w-full bg-black/30 border border-white/20 rounded-xl px-3 py-2.5 text-xs text-white placeholder-gray-300">
<input type="text" name="fan_contact" required maxlength="80" placeholder="البريد الإلكتروني أو رقم الهاتف" dir="ltr" class="w-full bg-black/30 border border-white/20 rounded-xl px-3 py-2.5 text-xs text-white placeholder-gray-300 text-right">
<div class="flex items-center justify-between gap-2 bg-black/20 p-2.5 rounded-xl">
<div class="text-center flex-1"><img src="<?= e(img($m['home_logo'])) ?>" alt="شعار <?= e($m['home_team']) ?>" class="h-9 w-9 object-contain mx-auto mb-1 rounded"><label class="block text-[10px] mb-1 font-bold"><?= e($m['home_team']) ?></label><input type="number" name="pred_home" min="0" max="20" inputmode="numeric" value="1" required class="w-14 text-center bg-white text-red-900 font-bold rounded-lg py-1"></div>
<span class="font-bold">-</span>
<div class="text-center flex-1"><img src="<?= e(img($m['away_logo'])) ?>" alt="شعار <?= e($m['away_team']) ?>" class="h-9 w-9 object-contain mx-auto mb-1 rounded bg-white/90 p-0.5"><label class="block text-[10px] mb-1 font-bold"><?= e($m['away_team']) ?></label><input type="number" name="pred_away" min="0" max="20" inputmode="numeric" value="0" required class="w-14 text-center bg-white text-red-900 font-bold rounded-lg py-1"></div>
</div>
<button type="submit" class="w-full bg-white text-red-800 hover:bg-gray-100 font-extrabold py-2.5 rounded-xl text-xs transition cursor-pointer">إرسال التوقع</button>
</form>
<?php return ob_get_clean(); }

function pred_stats_html($m, $mid) {
    $st = pred_stats($mid); $n = $st['n'];
    ob_start(); ?>
<div class="bg-black/25 border border-white/15 rounded-2xl p-4 space-y-3">
<div class="flex items-center justify-between text-xs"><span class="font-bold text-amber-300">إحصائيات توقعات الجماهير</span><span class="bg-white/10 rounded-full px-2.5 py-0.5 tabular-nums"><?= $n ?> توقع</span></div>
<?php if (!$n): ?><p class="text-xs text-gray-300">لا توجد توقعات بعد، كن أول من يتوقع!</p>
<?php else: foreach ([[$m['home_team'] . ' يفوز', $st['h']], ['تعادل', $st['d']], [$m['away_team'] . ' يفوز', $st['a']]] as [$l, $c]): $pc = round($c * 100 / $n); ?>
<div class="text-xs"><div class="flex justify-between"><span><?= e($l) ?></span><b class="tabular-nums"><?= $pc ?>% (<?= $c ?>)</b></div><div class="h-2 bg-white/10 rounded-full overflow-hidden mt-1"><div class="h-full bg-amber-400" style="width:<?= $pc ?>%"></div></div></div>
<?php endforeach; ?>
<div class="flex flex-wrap items-center gap-2 text-[11px]"><span class="text-gray-300">الأكثر توقعاً:</span><?php foreach (array_slice($st['sc'], 0, 3, true) as $k => $c): ?><span class="bg-white/10 rounded-full px-2.5 py-0.5 font-bold tabular-nums" dir="ltr"><?= e($k) ?> × <?= $c ?></span><?php endforeach; ?><span class="text-gray-300 mr-auto">المتوسط: <b class="tabular-nums" dir="ltr"><?= round($st['ah'] / $n, 1) ?> - <?= round($st['aa'] / $n, 1) ?></b></span></div>
<?php endif; ?>
</div>
<?php return ob_get_clean(); }

function mp_page_html($S, $m, $mid, $pclosed, $ks) {
    $lk = $S['lv']['c']; $code = strtoupper(substr($mid, 0, 6)); $good = [];
    if ($S['show_correct_predictions'] && $S['finished'] && $S['final_home'] !== '' && $S['final_away'] !== '')
        foreach (jget('predictions.json') as $p) if (($p['mid'] ?? '') === $mid && $p['home'] == $S['final_home'] && $p['away'] == $S['final_away']) $good[] = $p['name'];
    $fx = array_values(array_filter((array)$S['fixtures'], 'is_array')); usort($fx, fn($a, $b) => strcmp(($a['date'] ?? '') . ($a['time'] ?? ''), ($b['date'] ?? '') . ($b['time'] ?? ''))); $nx = $fx[0] ?? null;
    $started = in_array($lk['st'], ['first', 'half', 'second', 'finished'], true);
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-5">
<h1 class="text-2xl font-black text-center flex items-center justify-center gap-2"><i data-lucide="help-circle" class="w-6 h-6 text-amber-300"></i> توقعات الجماهير</h1>
<div class="bg-black/25 border border-white/15 rounded-2xl p-4 space-y-3">
<div class="flex flex-wrap items-center justify-between gap-2 text-[11px]"><span class="text-amber-300 font-bold"><?= e($m['competition'] . ($m['round'] !== '' ? ' - ' . $m['round'] : '')) ?></span><span class="bg-white/10 rounded-full px-2.5 py-0.5 tabular-nums" dir="ltr">مباراة #<?= e($code) ?></span><span class="px-2.5 py-0.5 rounded-full font-bold <?= $pclosed ? 'bg-rose-600' : 'bg-emerald-600' ?>"><?= $pclosed ? 'باب التوقعات مغلق' : 'باب التوقعات مفتوح' ?></span></div>
<div class="grid grid-cols-3 items-center text-center gap-2">
<div class="flex flex-col items-center gap-1"><img src="<?= e(img($m['home_logo'])) ?>" alt="شعار <?= e($m['home_team']) ?>" class="h-14 w-auto object-contain"><span class="text-sm font-extrabold"><?= e($m['home_team']) ?></span></div>
<div class="text-2xl font-black <?= $started ? 'bg-black/30 rounded-xl py-1 px-2' : 'text-amber-300' ?>"><?= $started ? (int)$S['home_score'] . ' - ' . (int)$S['away_score'] : 'VS' ?></div>
<div class="flex flex-col items-center gap-1"><img src="<?= e(img($m['away_logo'])) ?>" alt="شعار <?= e($m['away_team']) ?>" class="h-14 w-auto object-contain bg-white/90 p-1 rounded"><span class="text-sm font-extrabold"><?= e($m['away_team']) ?></span></div>
</div>
<?php if ($m['date'] !== ''): ?><div class="flex flex-wrap justify-center gap-4 text-xs text-gray-200"><span>📅 <?= e($m['date']) ?></span><?php if ($m['time'] !== ''): ?><span>⏰ ساعة الانطلاقة <b class="text-amber-300 tabular-nums" dir="ltr"><?= e($m['time']) ?></b></span><?php endif; ?><?php if ($m['stadium'] !== ''): ?><span>🏟️ <?= e($m['stadium']) ?></span><?php endif; ?></div><?php endif; ?>
</div>
<?php if ($pclosed): ?>
<div class="bg-rose-900/40 border border-rose-400/30 rounded-2xl p-4 text-xs leading-6 text-center">أُغلق باب التوقعات لهذه المباراة (<?= $lk['st'] === 'finished' ? 'انتهت المباراة' : ($lk['st'] === 'cancelled' ? 'أُلغيت المباراة' : 'بدأت المباراة أو حان موعدها') ?>).
<?php if ($nx): ?><br>سيُفتح الباب تلقائياً للمباراة المقبلة: <b><?= e(($nx['home_team'] ?? '') . ' × ' . ($nx['away_team'] ?? '')) ?></b><?= !empty($nx['date']) ? ' — ' . e($nx['date']) : '' ?>.<?php else: ?><br>سيُفتح الباب فور الإعلان عن المباراة المقبلة.<?php endif; ?></div>
<?php else: ?><div class="bg-black/20 border border-white/10 rounded-2xl p-4"><?= predict_form_html($m, false, 'matchpredictions') ?><p class="text-[11px] text-gray-300 mt-3 leading-5">توقع واحد لكل شخص (بريد أو رقم هاتف) لهذه المباراة، وتُغلق التوقعات عند الانطلاقة.</p></div><?php endif; ?>
<?= pred_stats_html($m, $mid) ?>
<?php if ($good): ?><div class="bg-emerald-950/80 border border-emerald-500/40 rounded-2xl p-4 space-y-2"><h3 class="text-sm font-bold text-emerald-300">لوحة الشرف: أصحاب التوقع الصحيح (<?= e($S['final_home'] . ' - ' . $S['final_away']) ?>)</h3><div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs"><?php foreach ($good as $n): ?><div class="bg-black/30 p-2.5 rounded-xl text-center font-bold text-emerald-300"><?= e($n) ?></div><?php endforeach; ?></div></div><?php endif; ?>
</div>
<?php return ob_get_clean(); }

// ---------- صوت الجماهير ----------
function vote_page_html($S, $voted) {
    $mo = $S['motm']; $tot = array_sum(array_column($mo['candidates'], 'votes'));
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-4">
<h1 class="text-2xl font-black text-center flex items-center justify-center gap-2"><i data-lucide="award" class="w-6 h-6 text-amber-300"></i> صوت الجماهير</h1>
<?php if (!$mo['active'] || !$mo['candidates']): ?><p class="text-center text-sm text-gray-200">التصويت غير متاح حالياً.</p>
<?php else: ?>
<p class="text-center text-sm text-gray-100"><?= e($mo['question']) ?></p>
<?php if ($voted): ?><div class="bg-emerald-600/30 border border-emerald-400/40 rounded-xl p-2.5 text-center text-xs font-bold">شكراً، سُجّل صوتك. هذه النتائج الحالية:</div><?php endif; ?>
<form method="POST" class="space-y-3">
<input type="hidden" name="t" value="<?= tok() ?>"><input type="hidden" name="action" value="vote"><input type="hidden" name="bk" value="fanvote">
<div class="space-y-2.5">
<?php foreach ($mo['candidates'] as $i => $c): $pc = $tot ? round($c['votes'] * 100 / $tot) : 0; ?>
<label class="block bg-black/30 border border-white/15 p-3 rounded-xl transition <?= $voted ? '' : 'cursor-pointer hover:bg-black/40' ?>"><div class="flex items-center justify-between gap-2"><div class="flex items-center gap-2 text-sm font-bold"><?php if (!$voted): ?><input type="radio" name="candidate_index" value="<?= $i ?>" required class="accent-red-600"><?php endif; ?><span><?= e($c['name']) ?></span></div><span class="text-[11px] bg-white/10 px-2 py-0.5 rounded-full tabular-nums"><?= (int)$c['votes'] ?> صوت • <?= $pc ?>%</span></div><div class="mt-2 h-2 bg-white/10 rounded-full overflow-hidden"><div class="h-full bg-amber-400" style="width:<?= $pc ?>%"></div></div></label>
<?php endforeach; ?>
</div>
<?php if (!$voted): ?><input type="text" name="web" class="hidden" tabindex="-1" autocomplete="off"><button type="submit" class="w-full bg-amber-400 text-red-950 font-extrabold py-2.5 rounded-xl text-xs hover:bg-amber-300 transition cursor-pointer">تصويت الآن</button><?php endif; ?>
</form>
<p class="text-center text-[11px] text-gray-300">إجمالي الأصوات: <?= (int)$tot ?></p>
<?php endif; ?>
</div>
<?php return ob_get_clean(); }

function vote_teaser_html($S) {
    $mo = $S['motm']; if (!$mo['active'] || !$mo['candidates']) return '';
    $tot = array_sum(array_column($mo['candidates'], 'votes')); $top = $mo['candidates']; usort($top, fn($a, $b) => $b['votes'] <=> $a['votes']); $t = $top[0];
    ob_start(); ?>
<section class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-6 shadow-xl flex flex-wrap items-center justify-between gap-4">
<div class="min-w-0"><h3 class="text-base font-bold flex items-center gap-2"><i data-lucide="award" class="w-4 h-4 text-amber-300"></i> صوت الجماهير: أفضل لاعب في المباراة</h3><p class="text-xs text-gray-200 mt-1"><?= e($mo['question']) ?></p><?php if ($tot): ?><p class="text-[11px] text-amber-200 mt-1">الأعلى حالياً: <b><?= e($t['name']) ?></b> (<?= round($t['votes'] * 100 / $tot) ?>%) • <?= (int)$tot ?> صوت</p><?php endif; ?></div>
<a href="index.php?fanvote=1" data-track="fanvote" class="bg-amber-400 text-red-950 font-extrabold px-5 py-2.5 rounded-xl text-xs hover:bg-amber-300 transition">صوّت الآن</a>
</section>
<?php return ob_get_clean(); }

// ---------- ألبوم الصور ----------
function gal_item($x) {
    $u = img($x['url']); $cap = (string)($x['caption'] ?? ''); $alt = $cap !== '' ? $cap : 'صورة من ألبوم ' . seo_name();
    return '<div class="bg-black/30 rounded-2xl overflow-hidden border border-white/10"><a href="' . e($u) . '" data-lb data-cap="' . e($cap) . '" class="group block overflow-hidden"><img src="' . e($u) . '" alt="' . e($alt) . '" title="' . e($alt) . '" loading="lazy" decoding="async" class="w-full h-36 sm:h-44 object-cover transition duration-300 group-hover:scale-105"></a>' . ($cap !== '' ? '<div class="p-2.5 text-[11px] font-semibold text-center">' . e($cap) . '</div>' : '') . '</div>';
}
function gallery_home_html($S) {
    $g = $S['gal']; if (!$g) return '';
    ob_start(); ?>
<section class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-6 shadow-xl">
<h3 class="text-base font-bold mb-4 flex items-center gap-2"><i data-lucide="image" class="w-4 h-4 text-amber-300"></i> ألبوم الصور</h3>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3"><?php foreach (array_slice($g, 0, 4) as $x) echo gal_item($x); ?></div>
<?php if (count($g) > 4): ?><div class="text-center mt-5"><a href="index.php?album=1" data-track="album" class="inline-flex items-center gap-2 bg-amber-400 text-red-950 font-extrabold px-6 py-2.5 rounded-xl text-xs hover:bg-amber-300 transition">عرض المزيد (<?= count($g) - 4 ?>)</a></div><?php endif; ?>
</section>
<?php return ob_get_clean(); }
function album_page_html($S) {
    $g = $S['gal']; $grp = [];
    foreach ($g as $x) $grp[!empty($x['ts']) ? date('Y-m-d', (int)$x['ts']) : 'old'][] = $x;
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-5">
<h1 class="text-2xl font-black text-center flex items-center justify-center gap-2"><i data-lucide="image" class="w-6 h-6 text-amber-300"></i> ألبوم الصور (<?= count($g) ?>)</h1>
<?php if (!$g): ?><p class="text-center text-sm text-gray-200">لا توجد صور بعد.</p><?php endif; ?>
<?php foreach ($grp as $k => $items): ?>
<div><h2 class="text-xs font-bold text-amber-300 mb-2"><?= $k === 'old' ? 'صور أقدم' : '📅 ' . e($k) ?> <span class="text-gray-300 font-normal">(<?= count($items) ?>)</span></h2>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3"><?php foreach ($items as $x) echo gal_item($x); ?></div></div>
<?php endforeach; ?>
</div>
<?php return ob_get_clean(); }

// ---------- سلايدشو البانر الأفقي (مصغّر بعرض صندوق المباراة، سكربته مدمج فلا يتأثر بكاش app.js) ----------
function banner_slides_html($S) {
    $b = $S['banners']; $sl = array_values(array_filter((array)($b['slides'] ?? []), fn($x) => is_array($x) && !empty($x['img'])));
    if (empty($b['slides_active']) || !$sl) return '';
    $rk = ((int)($b['slide_v'] ?? 0) >= 2) ? ($b['slide_ratio'] ?? 'compact') : 'compact';
    $sz = ['compact' => 'height:clamp(48px,7vw,76px)', 'leader' => 'aspect-ratio:728 / 90', 'wide' => 'aspect-ratio:970 / 250', 'square' => 'aspect-ratio:16 / 9'][$rk] ?? 'height:clamp(48px,7vw,76px)';
    $sec = max(2, min(30, (int)($b['slide_sec'] ?? 5))); $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]; $n = count($sl);
    $svg = fn($p) => '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $p . '"/></svg>';
    ob_start(); ?>
<div id="adz" data-sec="<?= $sec ?>" dir="ltr" role="region" aria-roledescription="carousel" aria-label="إعلانات" class="relative w-full overflow-hidden rounded-2xl border border-white/25 shadow-xl bg-black/30" style="<?= e($sz) ?>">
<span class="absolute top-1 left-1 z-20 text-[9px] leading-none bg-black/55 text-white/85 px-1.5 py-0.5 rounded-full pointer-events-none">إعلان</span>
<?php foreach ($sl as $i => $x): $u = lnk($x['url'] ?? ''); $ext = $u !== '' && preg_match('~^https?://~i', $u) && strcasecmp((string)parse_url($u, PHP_URL_HOST), $host) !== 0;
$st = 'adz-s transition-opacity duration-700 absolute inset-0 block'; $sty = $i === 0 ? 'opacity:1;z-index:10' : 'opacity:0;z-index:0;pointer-events:none';
$im = '<img src="' . e(img($x['img'])) . '" alt="' . e($x['alt'] ?? '') . '" class="w-full h-full object-cover" ' . ($i === 0 ? 'fetchpriority="high"' : 'loading="eager"') . ' decoding="async" draggable="false">';
if ($u !== ''): ?><a href="<?= e($u) ?>"<?= $ext ? ' target="_blank" rel="noopener sponsored"' : '' ?> data-track="bh_<?= $i + 1 ?>" class="<?= $st ?>" style="<?= $sty ?>" aria-label="<?= e($x['alt'] ?? 'إعلان') ?>"><?= $im ?></a>
<?php else: ?><div class="<?= $st ?>" style="<?= $sty ?>"><?= $im ?></div><?php endif; endforeach; ?>
<?php if ($n > 1): /* RTL: السابق يمين (سهم لليمين) والتالي يسار (سهم لليسار) */ ?>
<button type="button" class="adz-p absolute right-1.5 top-1/2 -translate-y-1/2 z-30 w-7 h-7 rounded-full bg-black/50 hover:bg-black/75 text-white flex items-center justify-center cursor-pointer" aria-label="السابق"><?= $svg('M9 6l6 6-6 6') ?></button>
<button type="button" class="adz-n absolute left-1.5 top-1/2 -translate-y-1/2 z-30 w-7 h-7 rounded-full bg-black/50 hover:bg-black/75 text-white flex items-center justify-center cursor-pointer" aria-label="التالي"><?= $svg('M15 6l-6 6 6 6') ?></button>
<div class="absolute bottom-1 inset-x-0 z-30 flex justify-center items-center gap-1.5 pointer-events-none"><?php for ($i = 0; $i < $n; $i++): ?><button type="button" class="adz-d h-[6px] rounded-full bg-white transition-all pointer-events-auto cursor-pointer" style="width:<?= $i === 0 ? 16 : 6 ?>px;opacity:<?= $i === 0 ? 1 : .5 ?>" aria-label="إعلان <?= $i + 1 ?>"></button><?php endfor; ?></div>
<?php endif; ?>
</div>
<?php if ($n > 1): ?>
<script>
(()=>{const z=document.getElementById('adz');if(!z||z.dataset.on)return;z.dataset.on=1;
const s=[...z.querySelectorAll('.adz-s')],d=[...z.querySelectorAll('.adz-d')],sec=Math.max(2,+z.dataset.sec||5)*1000;let i=0,tm=0,hold=false,x0=null;
const go=n=>{i=(n+s.length)%s.length;s.forEach((e,k)=>{const a=k===i;e.style.opacity=a?1:0;e.style.zIndex=a?10:0;e.style.pointerEvents=a?'auto':'none';e.setAttribute('aria-hidden',a?'false':'true')});d.forEach((e,k)=>{e.style.opacity=k===i?1:.5;e.style.width=k===i?'16px':'6px'})};
const run=()=>{clearTimeout(tm);if(hold||document.hidden)return;tm=setTimeout(()=>{go(i+1);run()},sec)};
z.addEventListener('click',e=>{const b=e.target.closest&&e.target.closest('button');if(!b||!z.contains(b))return;e.preventDefault();e.stopPropagation();
if(b.classList.contains('adz-n'))go(i+1);else if(b.classList.contains('adz-p'))go(i-1);else{const k=d.indexOf(b);if(k>-1)go(k)}run()});
z.addEventListener('touchstart',e=>{x0=e.touches[0].clientX;hold=true;clearTimeout(tm)},{passive:true});
z.addEventListener('touchend',e=>{if(x0!==null){const dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40)go(dx>0?i+1:i-1)}x0=null;hold=false;run()});
document.addEventListener('visibilitychange',run);
if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)s.forEach(e=>e.style.transition='none');
go(0);run()})();
</script>
<?php endif; ?>
<?php return ob_get_clean(); }

// ===================== SEO: بطاقة مباراة + صفحات المباراة / النادي / الوسم / فهرس الوسوم =====================
function logo_tag($u, $name, $cls) { return '<img src="' . e(img($u)) . '" alt="شعار ' . e($name) . '" title="' . e($name) . '" loading="lazy" decoding="async" class="' . e($cls) . '">'; }
function chip($href, $label, $ico = '') { return '<a href="' . e($href) . '" class="inline-flex items-center gap-1 bg-white/15 hover:bg-white/25 transition rounded-full px-3 py-1 text-[11px] font-bold">' . $ico . e($label) . '</a>'; }
function match_card($x) {
    $fin = ($x['status'] ?? 'finished') === 'finished'; $sc = $fin ? (int)$x['home_score'] . ' - ' . (int)$x['away_score'] : 'VS';
    $bd = $fin ? '#' . (int)$x['no'] : (['live' => 'مباشر', 'cancelled' => 'ملغاة'][$x['status'] ?? ''] ?? 'قادمة');
    ob_start(); ?>
<a href="<?= e(u_match($x['id'])) ?>" class="block bg-black/25 border border-white/15 rounded-2xl p-4 hover:bg-black/35 transition">
<div class="flex flex-wrap justify-between gap-2 text-[11px] text-gray-300 mb-2"><span class="bg-amber-400 text-red-950 font-black px-2 py-0.5 rounded-full"><?= e($bd) ?></span><span><?= e(trim($x['competition'] . ($x['round'] !== '' ? ' - ' . $x['round'] : ''))) ?></span><span>📅 <?= e($x['date']) ?></span></div>
<div class="grid grid-cols-3 items-center text-center gap-2">
<div class="flex flex-col items-center gap-1"><?= logo_tag($x['home_logo'], $x['home_team'], 'h-9 w-auto object-contain') ?><span class="text-xs font-bold"><?= e($x['home_team']) ?></span></div>
<div class="text-xl font-black bg-white/10 rounded-xl py-1" dir="ltr"><?= e($sc) ?></div>
<div class="flex flex-col items-center gap-1"><?= logo_tag($x['away_logo'], $x['away_team'], 'h-9 w-auto object-contain bg-white/90 p-0.5 rounded') ?><span class="text-xs font-bold"><?= e($x['away_team']) ?></span></div>
</div></a>
<?php return ob_get_clean(); }

function crumbs_html($items) {
    $o = '<nav aria-label="مسار التنقل" class="flex flex-wrap items-center gap-1.5 text-[11px] text-red-100">';
    foreach ($items as $i => $it) $o .= ($i ? '<span class="opacity-60">›</span>' : '') . ($it[1] !== '' ? '<a href="' . e($it[1]) . '" class="hover:text-white underline-offset-2 hover:underline">' . e($it[0]) . '</a>' : '<span class="text-white font-bold">' . e($it[0]) . '</span>');
    return $o . '</nav>';
}

function match_page_html($S, $x) {
    $all = array_values(matches_all($S)); $ids = array_column($all, 'id'); $pos = array_search($x['id'], $ids, true);
    $newer = $pos > 0 ? $all[$pos - 1] : null; $older = ($pos !== false && isset($all[$pos + 1])) ? $all[$pos + 1] : null;
    $fin = $x['status'] === 'finished'; $cur = $x['id'] === match_id($S['match']);
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-5">
<?= crumbs_html([['الرئيسية', 'index.php'], ['أرشيف المباريات', 'index.php?archive=1'], [$x['home_team'] . ' ضد ' . $x['away_team'], '']]) ?>
<h1 class="text-xl md:text-2xl font-black text-center leading-relaxed"><?= e($x['home_team']) ?> <span class="text-amber-300 tabular-nums" dir="ltr"><?= $fin ? (int)$x['home_score'] . ' - ' . (int)$x['away_score'] : '×' ?></span> <?= e($x['away_team']) ?></h1>
<div class="grid grid-cols-3 items-center text-center gap-2">
<a href="<?= e(u_team($x['home_team'])) ?>" class="flex flex-col items-center gap-2"><?= logo_tag($x['home_logo'], $x['home_team'], 'h-16 md:h-20 w-auto object-contain') ?><span class="text-sm font-extrabold"><?= e($x['home_team']) ?></span></a>
<div class="text-3xl md:text-4xl font-black bg-black/30 rounded-2xl py-2" dir="ltr"><?= $fin ? (int)$x['home_score'] . ' - ' . (int)$x['away_score'] : 'VS' ?></div>
<a href="<?= e(u_team($x['away_team'])) ?>" class="flex flex-col items-center gap-2"><?= logo_tag($x['away_logo'], $x['away_team'], 'h-16 md:h-20 w-auto object-contain bg-white/90 p-1 rounded') ?><span class="text-sm font-extrabold"><?= e($x['away_team']) ?></span></a>
</div>
<p class="text-sm leading-7 text-red-50"><?= e(match_desc($x)) ?></p>
<div class="flex flex-wrap gap-2">
<?php foreach (['competition' => '🏆', 'round' => '🔢', 'city' => '📍', 'stadium' => '🏟️'] as $f => $ic) if (trim((string)($x[$f] ?? '')) !== '') echo chip(u_tag($x[$f]), $x[$f], '<span>' . $ic . '</span>'); ?>
<?= chip(u_team($x['home_team']), $x['home_team']) . chip(u_team($x['away_team']), $x['away_team']) ?>
</div>
<?php if ($cur && !$fin): ?><div class="text-center"><a href="index.php?matchpredictions=1" class="inline-block bg-amber-400 text-red-950 font-extrabold px-6 py-2.5 rounded-xl text-xs hover:bg-amber-300 transition">توقّع نتيجة هذه المباراة</a></div><?php endif; ?>
<?php if ($newer || $older): ?><div class="grid sm:grid-cols-2 gap-3 pt-2"><?= $older ? '<div><div class="text-[11px] text-gray-300 mb-1">المباراة الأقدم</div>' . match_card($older) . '</div>' : '<span></span>' ?><?= $newer ? '<div><div class="text-[11px] text-gray-300 mb-1">المباراة الأحدث</div>' . match_card($newer) . '</div>' : '' ?></div><?php endif; ?>
</div>
<?php return ob_get_clean(); }

function team_page_html($S, $t) {
    $ms = team_vs($S, $t); $me = site_team($S)['name']; $self = mb_strtolower($me) === mb_strtolower($t['name']); $tags = [];
    foreach ($ms as $x) foreach (['competition', 'city'] as $f) if (trim((string)($x[$f] ?? '')) !== '') $tags[$x[$f]] = 1;
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-5">
<?= crumbs_html([['الرئيسية', 'index.php'], ['الأندية والوسوم', 'index.php?tags=1'], [$t['name'], '']]) ?>
<div class="flex flex-col items-center gap-3 text-center"><?= logo_tag($t['logo'], $t['name'], 'h-24 w-auto object-contain rounded-2xl logo-glow') ?><h1 class="text-2xl md:text-3xl font-black"><?= e($t['name']) ?></h1></div>
<p class="text-sm leading-7 text-red-50 text-center"><?= $self || $me === '' ? 'جميع مباريات ' . e($t['name']) . ' (' . count($ms) . ').' : 'مواجهات ' . e($t['name']) . ' ضد ' . e($me) . ' (' . count($ms) . ' مباراة).' ?></p>
<?php if ($tags): ?><div class="flex flex-wrap justify-center gap-2"><?php foreach (array_keys($tags) as $tg) echo chip(u_tag($tg), $tg, '<span>🏷️</span>'); ?></div><?php endif; ?>
<div class="space-y-3"><?php foreach ($ms as $x) echo match_card($x); ?><?php if (!$ms): ?><p class="text-center text-xs text-gray-300">لا توجد مواجهات مسجّلة بعد.</p><?php endif; ?></div>
</div>
<?php return ob_get_clean(); }

function tag_page_html($S, $tg) {
    $ms = array_values($tg['ms']);
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-5">
<?= crumbs_html([['الرئيسية', 'index.php'], ['الأندية والوسوم', 'index.php?tags=1'], [$tg['text'], '']]) ?>
<h1 class="text-2xl font-black text-center flex items-center justify-center gap-2"><span>🏷️</span> <?= e($tg['text']) ?></h1>
<p class="text-sm leading-7 text-red-50 text-center">وسم «<?= e($tg['text']) ?>» (<?= e(implode('، ', array_keys($tg['kinds']))) ?>): <?= count($ms) ?> مباراة مرتبطة به.</p>
<div class="space-y-3"><?php foreach ($ms as $x) echo match_card($x); ?></div>
</div>
<?php return ob_get_clean(); }

function tags_index_html($S) {
    $T = teams_all($S); $G = tags_all($S); $kinds = [];
    foreach ($G as $g) foreach (array_keys($g['kinds']) as $k) $kinds[$k][] = $g;
    ob_start(); ?>
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-6">
<?= crumbs_html([['الرئيسية', 'index.php'], ['الأندية والوسوم', '']]) ?>
<h1 class="text-2xl font-black text-center">الأندية والوسوم</h1>
<section><h2 class="text-sm font-bold text-amber-300 mb-3">الأندية (<?= count($T) ?>)</h2>
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3"><?php foreach ($T as $t): ?><a href="<?= e(u_team($t['name'])) ?>" class="flex flex-col items-center gap-2 bg-black/25 border border-white/15 rounded-2xl p-3 hover:bg-black/35 transition text-center"><?= logo_tag($t['logo'], $t['name'], 'h-12 w-auto object-contain') ?><span class="text-xs font-bold"><?= e($t['name']) ?></span><span class="text-[10px] text-gray-300"><?= count(team_vs($S, $t)) ?> مباراة</span></a><?php endforeach; ?></div></section>
<?php foreach ($kinds as $k => $list): ?><section><h2 class="text-sm font-bold text-amber-300 mb-3"><?= e($k) ?></h2><div class="flex flex-wrap gap-2"><?php foreach ($list as $g) echo chip(u_tag($g['text']), $g['text'] . ' (' . count($g['ms']) . ')', '<span>🏷️</span>'); ?></div></section><?php endforeach; ?>
<?php if (!$T && !$kinds): ?><p class="text-center text-xs text-gray-300">لا توجد بيانات بعد.</p><?php endif; ?>
</div>
<?php return ob_get_clean(); }

// ===================== الطاقم والتشكيلة (الملعب: صورة النيون، حارس يساراً والهجوم يميناً) =====================
function formation_pos($f) {
    $rows = array_map('intval', explode('-', $f)); $n = count($rows); $o = [[7, 50]];
    foreach ($rows as $i => $c) { $x = $n > 1 ? 22 + 60 * $i / ($n - 1) : 50; for ($j = 0; $j < $c; $j++) $o[] = [round($x, 1), round(($j + 1) / ($c + 1) * 100, 1)]; }
    return array_slice($o, 0, 11);
}
function lineup_ready($S) { $lu = $S['lineup']; return !empty($lu['published']) && ($lu['mid'] ?? '') === match_id($S['match']) && count(array_filter((array)$lu['xi'])) > 0; }
function lu_ad($a, $k) {
    if (empty($a['img'])) return '';
    $tag = '<img src="' . e(img($a['img'])) . '" alt="' . e($a['alt'] ?: 'إعلان') . '" loading="lazy" class="w-full h-full object-cover"><span class="absolute top-1 left-1 text-[9px] leading-none bg-black/55 text-white/85 px-1.5 py-0.5 rounded-full">إعلان</span>';
    $u = lnk($a['url'] ?? ''); $cl = 'relative block rounded-2xl overflow-hidden border border-white/20 bg-black/30 shadow-lg h-24 lg:h-full lg:min-h-[320px]';
    return $u !== '' ? '<a href="' . e($u) . '"' . (preg_match('~^https?://~i', $u) ? ' target="_blank" rel="noopener sponsored"' : '') . ' data-track="' . $k . '" class="' . $cl . '">' . $tag . '</a>' : '<div class="' . $cl . '">' . $tag . '</div>';
}
function lineup_html($S) {
    $sq = $S['squad']; $lu = $S['lineup']; $pm = []; foreach ($sq['players'] as $p) $pm[$p['id']] = $p;
    if (!lineup_ready($S)) return '<div class="rounded-2xl border border-amber-300/40 bg-amber-500/15 p-6 text-center space-y-2"><div class="text-3xl">⏳</div><div class="font-black text-amber-200">لم تُعلن التشكيلة الرسمية بعد</div><p class="text-xs text-gray-200">ستظهر التشكيلة هنا فور إعلانها من الإدارة.</p></div>';
    $f = $lu['formation'] ?: '4-4-2'; $pos = formation_pos($f); $adl = $lu['ads']['active'] ?? false ? lu_ad($lu['ads']['l'] ?? [], 'lu_l') : ''; $adr = ($lu['ads']['active'] ?? false) ? lu_ad($lu['ads']['r'] ?? [], 'lu_r') : '';
    $pz = ['GK' => 'حراس المرمى', 'DF' => 'المدافعون', 'MF' => 'خط الوسط', 'FW' => 'خط الهجوم'];
    ob_start(); ?>
<div class="space-y-4">
<div class="flex flex-col lg:flex-row gap-3 items-stretch">
<?php if ($adl): ?><div class="order-2 lg:order-1 lg:w-40 shrink-0"><?= $adl ?></div><?php endif; ?>
<div dir="ltr" class="order-1 lg:order-2 flex-1 relative w-full aspect-[4/3] sm:aspect-[16/9] rounded-2xl overflow-hidden border border-emerald-400/40 shadow-[0_0_30px_rgba(34,197,94,.25)] bg-black" style="background-image:url('WSSE_files/pitch.jpg');background-size:cover;background-position:center">
<span class="absolute top-2 right-2 z-10 text-[10px] md:text-xs font-black bg-black/60 text-emerald-300 border border-emerald-400/40 rounded-full px-2.5 py-0.5"><?= e($f) ?></span>
<?php foreach ($pos as $i => [$px, $py]): $p = $pm[$lu['xi'][$i] ?? ''] ?? null; if (!$p) continue; ?>
<div class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center" style="left:<?= $px ?>%;top:<?= $py ?>%"><span class="w-7 h-7 sm:w-9 sm:h-9 md:w-10 md:h-10 rounded-full border-2 border-white text-white font-black text-[11px] sm:text-sm flex items-center justify-center shadow-[0_0_12px_rgba(74,222,128,.85)] <?= $p['pos'] === 'GK' ? 'bg-amber-500' : 'bg-red-600' ?>"><?= (int)$p['no'] ?></span><span class="mt-0.5 text-[9px] sm:text-[10px] md:text-xs font-bold text-white text-center leading-tight max-w-[64px] sm:max-w-[90px] truncate" style="text-shadow:0 1px 3px #000,0 0 6px #000" dir="auto"><?= e($p['name']) ?></span></div>
<?php endforeach; ?>
</div>
<?php if ($adr): ?><div class="order-3 lg:w-40 shrink-0"><?= $adr ?></div><?php endif; ?>
</div>
<?php $subs = array_filter(array_map(fn($id) => $pm[$id] ?? null, (array)$lu['subs'])); if ($subs): ?>
<div><div class="text-xs font-bold text-amber-300 mb-2">دكّة البدلاء</div><div class="flex flex-wrap gap-2"><?php foreach ($subs as $p): ?><span class="inline-flex items-center gap-1.5 bg-black/30 border border-white/15 rounded-full ps-1 pe-3 py-1 text-xs font-bold"><span class="w-6 h-6 rounded-full bg-white/15 flex items-center justify-center text-[11px] font-black"><?= (int)$p['no'] ?></span><?= e($p['name']) ?></span><?php endforeach; ?></div></div><?php endif; ?>
<?php if ($sq['coach'] !== '' || $sq['assistant'] !== ''): ?><div class="grid sm:grid-cols-2 gap-2 text-xs"><?php if ($sq['coach'] !== ''): ?><div class="bg-black/25 border border-white/10 rounded-xl p-2.5">🧑‍💼 المدرب: <b><?= e($sq['coach']) ?></b></div><?php endif; ?><?php if ($sq['assistant'] !== ''): ?><div class="bg-black/25 border border-white/10 rounded-xl p-2.5">🤝 المدرب المساعد: <b><?= e($sq['assistant']) ?></b></div><?php endif; ?></div><?php endif; ?>
<?php if ($sq['players']): ?><details class="bg-black/20 border border-white/10 rounded-xl p-3"><summary class="text-xs font-bold cursor-pointer text-amber-300">قائمة اللاعبين (<?= count($sq['players']) ?>)</summary>
<div class="grid sm:grid-cols-2 gap-3 mt-3"><?php foreach ($pz as $k => $l): $g = array_filter($sq['players'], fn($p) => $p['pos'] === $k); if (!$g) continue; usort($g, fn($a, $b) => $a['no'] <=> $b['no']); ?><div><div class="text-[11px] text-gray-300 mb-1"><?= $l ?></div><?php foreach ($g as $p): ?><div class="flex items-center gap-2 text-xs py-0.5"><span class="w-6 text-center font-black text-amber-300 tabular-nums"><?= (int)$p['no'] ?></span><span><?= e($p['name']) ?></span></div><?php endforeach; ?></div><?php endforeach; ?></div></details><?php endif; ?>
</div>
<?php return ob_get_clean(); }
