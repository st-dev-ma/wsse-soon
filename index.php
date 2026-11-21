<?php
require __DIR__ . '/inc.php';
require __DIR__ . '/parts.php';
guard_query(['archive', 'ap', 'pm', 'album', 'fanvote', 'matchpredictions', 'match', 'team', 'tag', 'tags', 'lineup']);
$BK = '';
$fb = $_SESSION['fb'] ?? null; unset($_SESSION['fb']);
if (empty($_SESSION['ft'])) $_SESSION['ft'] = time();
$S = site(); $m = $S['match']; $past = $S['past']; $mo = $S['motm'];
$mid = mid($m);
$lvx = $S['lv']; $lk = $lvx['c']; $L = $lvx['L']; $closed = in_array($lk['st'], ['first', 'half', 'second', 'finished', 'cancelled'], true);
$ks = strtotime(trim($m['date'] . ' ' . $m['time'])); $pclosed = $closed || ($ks && time() >= $ks); // إغلاق التوقعات من الخادم

function done($msg, $type) { $_SESSION['fb'] = [$msg, $type]; header('Location: ' . pg_back($GLOBALS['BK'] ?? '')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $BK = in_array($_POST['bk'] ?? '', ['fanvote', 'matchpredictions'], true) ? $_POST['bk'] : '';
    if (!empty($_POST['web'])) done('تم.', 'success');
    if (!rl('p' . ip(), 10, 600)) done('محاولات كثيرة، أعد المحاولة لاحقاً.', 'error');
    $a = $_POST['action'] ?? '';
    if ($a === 'predict') {
        if ($pclosed) done('باب التوقعات مغلق لهذه المباراة.', 'warning');
        if (time() - (int)($_SESSION['ft'] ?? 0) < 3) done('أعد المحاولة بعد لحظات.', 'warning');
        $name = mb_substr(trim((string)($_POST['fan_name'] ?? '')), 0, 60);
        $con = mb_substr(trim((string)($_POST['fan_contact'] ?? '')), 0, 80);
        $okc = filter_var($con, FILTER_VALIDATE_EMAIL) || preg_match('/^\+?[0-9 ]{8,15}$/', $con);
        $rg = ['options' => ['min_range' => 0, 'max_range' => 20]];
        $h = filter_var($_POST['pred_home'] ?? null, FILTER_VALIDATE_INT, $rg); $w = filter_var($_POST['pred_away'] ?? null, FILTER_VALIDATE_INT, $rg);
        if ($name === '' || !$okc) done('يرجى إدخال اسم صحيح وبريد إلكتروني أو رقم هاتف صحيح.', 'error');
        if ($h === false || $w === false) done('أدخل نتيجة صحيحة (أرقام من 0 إلى 20).', 'error');
        if (isset($_COOKIE['pr_' . $mid])) done('لقد سجّلت توقعك لهذه المباراة مسبقاً.', 'warning');
        if (!rl('pd' . ip(), 3, 3600)) done('تجاوزت عدد محاولات التوقع المسموحة، حاول لاحقاً.', 'error');
        $ck = ckey($con); $ih = ip(); $res = '';
        jup('predictions.json', function ($p) use ($name, $con, $ck, $h, $w, $mid, $ih, &$res) {
            $n = 0;
            foreach ($p as $r) {
                if (($r['mid'] ?? '') !== $mid) continue;
                if (($r['ck'] ?? ckey($r['contact'] ?? '')) === $ck) { $res = 'dup'; return $p; }
                if (($r['iph'] ?? '') === $ih) $n++;
            }
            if ($n >= 3) { $res = 'ip'; return $p; }
            if (count($p) >= 20000) { $res = 'full'; return $p; }
            $p[] = ['mid' => $mid, 'name' => $name, 'contact' => $con, 'ck' => $ck, 'iph' => $ih, 'home' => $h, 'away' => $w, 'date' => date('Y-m-d H:i')];
            $res = 'ok'; return array_values($p);
        }, []);
        if ($res === 'dup') done('هذا البريد/الرقم سجّل توقعه لهذه المباراة مسبقاً، ولا يُسمح بأكثر من توقع واحد.', 'warning');
        if ($res === 'ip') done('تم بلوغ الحد الأقصى للتوقعات من هذا الاتصال.', 'warning');
        if ($res !== 'ok') done('تعذر تسجيل التوقع.', 'error');
        setcookie('pr_' . $mid, '1', ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        done('تم تسجيل توقعك بنجاح!', 'success');
    }
    if ($a === 'subscribe') {
        $em = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if (!$em || strlen($em) > 120) done('بريد إلكتروني غير صحيح.', 'error');
        $new = false;
        jup('subscribers.json', function ($s) use ($em, &$new) {
            foreach ($s as $x) if (strcasecmp($x, $em) === 0) return $s;
            $s[] = $em; $new = true; return $s;
        }, []);
        $new ? done('تم اشتراكك بنجاح في القائمة البريدية!', 'success') : done('هذا البريد مسجل مسبقاً.', 'warning');
    }
    if ($a === 'vote' && $mo['active']) {
        $i = (int)($_POST['candidate_index'] ?? -1); $pk = 'p' . $mo['poll']; $ck = 'v' . $mo['poll']; $ih = ip();
        if (isset($_COOKIE[$ck])) done('لقد صوّتَّ مسبقاً.', 'warning');
        $ok = false;
        jup('voters.json', function ($v) use ($pk, $ih, &$ok) { if (in_array($ih, $v[$pk] ?? [], true)) return $v; $v[$pk][] = $ih; $ok = true; return $v; }, []);
        if (!$ok) done('لقد صوّتَّ مسبقاً.', 'warning');
        $hit = false;
        jup('data.json', function ($d) use ($i, &$hit) { if (isset($d['motm']['candidates'][$i])) { $d['motm']['candidates'][$i]['votes']++; $hit = true; } return $d; }, []);
        if (!$hit) done('اختيار غير صالح.', 'error');
        setcookie($ck, '1', ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        done('شكراً لك، تم تسجيل صوتك بنجاح!', 'success');
    }
    done('طلب غير صالح.', 'error');
}

if (isset($_GET['archive'])) {
    $per = 15; $tp = max(1, (int)ceil(count($past) / $per)); $apg = max(1, min($tp, (int)($_GET['ap'] ?? 1)));
    $rows = array_slice(array_reverse($past), ($apg - 1) * $per, $per); $cn = seo_name($S);
    $cu = abs_url('index.php?archive=1' . ($apg > 1 ? '&ap=' . $apg : ''));
    seo_set(['canonical' => $cu, 'desc' => 'أرشيف نتائج مباريات ' . $cn . ' كاملاً: ' . count($past) . ' مباراة مع الشعارات والتواريخ والملاعب والبطولات' . ($apg > 1 ? ' (الصفحة ' . $apg . ')' : '') . '.',
        'ld' => [['@type' => 'CollectionPage', 'name' => 'أرشيف مباريات ' . $cn, 'url' => $cu, 'inLanguage' => 'ar', 'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_map(fn($x, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url(u_match($x['id'])), 'name' => $x['home_team'] . ' ' . (int)$x['home_score'] . ' - ' . (int)$x['away_score'] . ' ' . $x['away_team']], $rows, array_keys($rows))]], ld_bc([['الرئيسية', 'index.php'], ['أرشيف المباريات', 'index.php?archive=1']])]]);
    head('أرشيف نتائج مباريات ' . $cn . ($apg > 1 ? ' - صفحة ' . $apg : '')); ?>
<div class="max-w-3xl w-full mx-auto my-auto">
<div class="bg-white/10 backdrop-blur-md border border-white/20 p-6 rounded-3xl shadow-2xl space-y-4">
<?= crumbs_html([['الرئيسية', 'index.php'], ['أرشيف المباريات', '']]) ?>
<h1 class="text-2xl font-black text-center">أرشيف المباريات (<?= count($past) ?>)</h1>
<?php if (!$rows): ?><p class="text-center text-xs text-gray-200">لا توجد مباريات مؤرشفة بعد.</p><?php endif; ?>
<?php foreach ($rows as $x) echo match_card($x); ?>
<?php if ($tp > 1): ?><nav aria-label="ترقيم الصفحات" class="flex flex-wrap justify-center gap-1.5 text-xs"><?php for ($i = 1; $i <= $tp; $i++): ?><a href="index.php?archive=1<?= $i > 1 ? '&amp;ap=' . $i : '' ?>" class="px-3 py-1.5 rounded-lg <?= $i === $apg ? 'bg-amber-400 text-red-950 font-bold' : 'bg-white/15' ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
<div class="flex flex-wrap justify-center gap-2"><?= chip('index.php?tags=1', 'الأندية والوسوم') . chip('index.php?album=1', 'ألبوم الصور') ?></div>
<div class="text-center pt-2"><a href="index.php" data-track="back_home" class="text-xs text-red-200 hover:underline">العودة للرئيسية</a></div>
</div></div>
<?php foot(); exit; }

$cn = seo_name($S);
if (isset($_GET['album'])) {
    $G = $S['gal']; $dc = 'صورة من ألبوم ' . $cn;
    $io = array_map(fn($g) => array_filter(['@type' => 'ImageObject', 'contentUrl' => abs_url(img($g['url'])), 'caption' => (string)($g['caption'] ?? '') ?: $dc, 'name' => (string)($g['caption'] ?? '') ?: $dc, 'uploadDate' => !empty($g['ts']) ? date('c', (int)$g['ts']) : '']), array_slice($G, 0, 100));
    seo_set(['canonical' => abs_url('index.php?album=1'), 'desc' => 'ألبوم صور ' . $cn . ': ' . count($G) . ' صورة مرتبة حسب تاريخ الرفع.', 'image' => $G ? abs_url(img($G[0]['url'])) : '', 'big' => (bool)$G, 'ld' => [['@type' => 'ImageGallery', 'name' => 'ألبوم صور ' . $cn, 'url' => abs_url('index.php?album=1'), 'inLanguage' => 'ar', 'associatedMedia' => $io], ld_bc([['الرئيسية', 'index.php'], ['ألبوم الصور', 'index.php?album=1']])]]);
    std_open('ألبوم صور ' . $cn, $fb, 'max-w-5xl'); echo album_page_html($S); std_close();
}
if (isset($_GET['fanvote'])) {
    seo_set(['canonical' => abs_url('index.php?fanvote=1'), 'desc' => 'صوّت لأفضل لاعب في مباراة ' . $cn . ($mo['question'] !== '' ? ': ' . $mo['question'] : '') . ' — النتائج والترتيب لحظياً.', 'ld' => [ld_bc([['الرئيسية', 'index.php'], ['صوت الجماهير', 'index.php?fanvote=1']])]]);
    std_open('صوت الجماهير: أفضل لاعب في المباراة | ' . $cn, $fb); echo vote_page_html($S, isset($_COOKIE['v' . $mo['poll']])); std_close();
}
if (isset($_GET['matchpredictions'])) {
    $xm = array_merge($m, ['id' => match_id($m), 'status' => 'upcoming']);
    seo_set(['canonical' => abs_url('index.php?matchpredictions=1'), 'desc' => 'توقّع نتيجة مباراة ' . $m['home_team'] . ' و' . $m['away_team'] . ($m['date'] !== '' ? ' بتاريخ ' . $m['date'] : '') . ' وشارك في توقعات الجماهير وإحصائياتها.', 'ld' => [ld_event($xm), ld_bc([['الرئيسية', 'index.php'], ['توقعات الجماهير', 'index.php?matchpredictions=1']])]]);
    std_open('توقعات مباراة ' . $m['home_team'] . ' و' . $m['away_team'] . ' | ' . $cn, $fb); echo mp_page_html($S, $m, $mid, $pclosed, $ks); std_close();
}
if (isset($_GET['lineup'])) {
    seo_set(['canonical' => abs_url('index.php?lineup=1'), 'desc' => 'تشكيلة ' . $cn . ' في مباراة ' . $m['home_team'] . ' و' . $m['away_team'] . ': التشكيلة الأساسية والبدلاء والجهاز الفني.', 'ld' => [ld_bc([['الرئيسية', 'index.php'], ['تشكيلة المباراة', 'index.php?lineup=1']])]]);
    std_open('تشكيلة مباراة ' . $m['home_team'] . ' و' . $m['away_team'] . ' | ' . $cn, $fb, 'max-w-5xl');
    echo '<div class="bg-white/10 backdrop-blur-md border border-white/20 p-5 md:p-6 rounded-3xl shadow-2xl space-y-4"><h1 class="text-xl md:text-2xl font-black text-center">تشكيلة المباراة: ' . e($m['home_team']) . ' × ' . e($m['away_team']) . '</h1>' . lineup_html($S) . '</div>'; std_close();
}
if (isset($_GET['tags'])) {
    seo_set(['canonical' => abs_url('index.php?tags=1'), 'desc' => 'جميع الأندية التي واجهها ' . $cn . ' ووسوم البطولات والمدن والملاعب والجولات مع الشعارات.', 'ld' => [['@type' => 'CollectionPage', 'name' => 'الأندية والوسوم', 'url' => abs_url('index.php?tags=1'), 'inLanguage' => 'ar'], ld_bc([['الرئيسية', 'index.php'], ['الأندية والوسوم', 'index.php?tags=1']])]]);
    std_open('الأندية والوسوم | ' . $cn, $fb); echo tags_index_html($S); std_close();
}
if (isset($_GET['match'])) {
    $all = matches_all($S); $id = is_string($_GET['match']) ? $_GET['match'] : ''; if (!isset($all[$id])) error_page(404);
    $x = $all[$id]; $fin = $x['status'] === 'finished';
    $tt = $x['home_team'] . ($fin ? ' ' . (int)$x['home_score'] . ' - ' . (int)$x['away_score'] . ' ' : ' × ') . $x['away_team'];
    seo_set(['canonical' => abs_url(u_match($id)), 'desc' => match_desc($x), 'image' => logo_abs((string)$x['home_logo']), 'type' => 'article', 'ld' => [ld_event($x), ld_bc([['الرئيسية', 'index.php'], ['أرشيف المباريات', 'index.php?archive=1'], [$tt, u_match($id)]])]]);
    std_open($tt . ($x['competition'] !== '' ? ' | ' . $x['competition'] : '') . ' | ' . $cn, $fb); echo match_page_html($S, $x); std_close();
}
if (isset($_GET['team'])) {
    $T = teams_all($S); $k = is_string($_GET['team']) ? mb_strtolower(trim($_GET['team'])) : ''; if (!isset($T[$k])) error_page(404);
    $t = $T[$k]; $tv = team_vs($S, $t); $me = site_team($S)['name'];
    seo_set(['canonical' => abs_url(u_team($t['name'])), 'desc' => ($me !== '' && mb_strtolower($me) !== mb_strtolower($t['name'])) ? 'مواجهات نادي ' . $t['name'] . ' ضد ' . $me . ': ' . count($tv) . ' مباراة مع الشعار والنتائج.' : 'صفحة نادي ' . $t['name'] . ': ' . count($tv) . ' مباراة مع الشعار والنتائج.', 'image' => logo_abs((string)$t['logo']), 'ld' => [array_merge(ld_team($t['name'], (string)$t['logo']), ['url' => abs_url(u_team($t['name']))]), ld_bc([['الرئيسية', 'index.php'], ['الأندية والوسوم', 'index.php?tags=1'], [$t['name'], u_team($t['name'])]])]]);
    std_open('نادي ' . $t['name'] . ' - المواجهات والنتائج | ' . $cn, $fb); echo team_page_html($S, $t); std_close();
}
if (isset($_GET['tag'])) {
    $TG = tags_all($S); $k = is_string($_GET['tag']) ? mb_strtolower(trim($_GET['tag'])) : ''; if (!isset($TG[$k])) error_page(404);
    $tg = $TG[$k];
    seo_set(['canonical' => abs_url(u_tag($tg['text'])), 'desc' => 'مباريات ' . $cn . ' المرتبطة بوسم «' . $tg['text'] . '»: ' . count($tg['ms']) . ' مباراة مع النتائج والشعارات.', 'ld' => [['@type' => 'CollectionPage', 'name' => $tg['text'], 'url' => abs_url(u_tag($tg['text'])), 'inLanguage' => 'ar', 'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_map(fn($x, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => abs_url(u_match($x['id']))], array_values($tg['ms']), array_keys(array_values($tg['ms'])))]], ld_bc([['الرئيسية', 'index.php'], ['الأندية والوسوم', 'index.php?tags=1'], [$tg['text'], u_tag($tg['text'])]])]]);
    std_open('وسم ' . $tg['text'] . ' - مباريات ' . $cn, $fb); echo tag_page_html($S, $tg); std_close();
}

$vb = !empty($S['banners']['vertical_active']) && trim($S['banners']['vertical_code']) !== '';
$hb = !empty($S['banners']['horizontal_active']) && trim($S['banners']['horizontal_code']) !== '';
$good = [];
if ($S['show_correct_predictions'] && $S['finished'] && $S['final_home'] !== '' && $S['final_away'] !== '')
    foreach (jget('predictions.json') as $p) if (($p['mid'] ?? '') === $mid && $p['home'] == $S['final_home'] && $p['away'] == $S['final_away']) $good[] = $p['name'];
$mt = $m['date'] . 'T' . $m['time'] . ':00';
$tot = array_sum(array_column($mo['candidates'], 'votes'));
$wi = '🌤️'; foreach (['مطر' => '🌧️', 'أمطار' => '🌧️', 'رعد' => '⛈️', 'عواصف' => '⛈️', 'ثلج' => '❄️', 'غائم' => '☁️', 'غيوم' => '☁️', 'رياح' => '💨', 'عاصف' => '💨', 'صاف' => '☀️', 'مشمس' => '☀️'] as $wk => $wv) if (mb_strpos($m['weather'], $wk) !== false) { $wi = $wv; break; }
$ei = ['stadium' => '🏟️', 'city' => '📍', 'date' => '📅', 'weather' => $wi];

$stb = standings_html($S); $stp = $S['standings']['pos'];
$sld = banner_slides_html($S);
$rmc = $S['reminder']; $dkp = $ks ? $ks - time() : null;
$showRem = !empty($rmc['on']) && $dkp !== null && $dkp > 0 && $dkp <= max(5, (int)$rmc['min']) * 60 && $lk['st'] === 'idle';
$cn = seo_name($S); $hs = $S['seo'];
$ttl = $hs['title'] !== '' ? $hs['title'] : (strpos($S['site_title'], 'تحت الإنشاء') !== false ? $cn . ' | نتائج المباريات المباشرة، الترتيب، الأرشيف والتوقعات' : $S['site_title']);
$ldH = [['@type' => 'WebPage', '@id' => base_url() . '#webpage', 'url' => base_url(), 'name' => $ttl, 'inLanguage' => 'ar', 'isPartOf' => ['@id' => base_url() . '#site'], 'about' => ['@id' => base_url() . '#team']]];
if ($m['home_team'] !== '' || $m['away_team'] !== '') { $ev = array_merge($m, ['id' => match_id($m), 'status' => $lk['st'] === 'cancelled' ? 'cancelled' : ($lk['st'] === 'finished' ? 'finished' : 'upcoming'), 'home_score' => (int)$S['home_score'], 'away_score' => (int)$S['away_score']]); $ldH[] = ld_event($ev); }
seo_set(['canonical' => base_url(), 'ld' => $ldH]);
head($ttl);
?>
<div class="max-w-6xl w-full mx-auto space-y-6">
<?php if ($sld === '' && $hb): ?><div class="w-full overflow-hidden" data-track="banner_h"><?= $S['banners']['horizontal_code'] ?></div><?php endif; ?>
<?php if ($fb): ?><div class="p-4 rounded-2xl text-center font-bold shadow-lg <?= $fb[1] === 'success' ? 'bg-emerald-600/90 border border-emerald-400' : ($fb[1] === 'warning' ? 'bg-amber-600/90 border border-amber-400' : 'bg-rose-600/90 border border-rose-400') ?>"><?= e($fb[0]) ?></div><?php endif; ?>

<?php if ($showRem): ?>
<div id="rembar" data-dk="<?= (int)$dkp ?>" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 rounded-2xl border border-amber-300/50 bg-amber-500/20 backdrop-blur-md px-4 py-3 text-sm font-bold shadow-lg text-center"><span class="text-xl">⏰</span><span>تنطلق المباراة بعد <b id="remt" class="text-amber-300 tabular-nums" dir="ltr"></b> — ساعة الانطلاقة <b class="text-amber-300 tabular-nums" dir="ltr"><?= e($m['time']) ?></b></span><span class="text-xs text-amber-100 font-normal">تابع البث الحي هنا</span></div>
<?php endif; ?>
<?php if ($vb): ?><div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start"><div class="lg:col-span-3 space-y-6"><?php else: ?><div class="max-w-4xl mx-auto space-y-6"><?php endif; ?>
<?= $sld ?>

<?= $stp === 'top' ? $stb : '' ?>
<section class="text-center space-y-4 bg-white/10 backdrop-blur-md border border-white/20 p-8 rounded-3xl shadow-2xl relative overflow-hidden">
<?php if (empty($S['seo']['hide_uc'])): ?><span class="absolute top-4 left-4 bg-amber-400 text-red-950 text-xs font-black px-3 py-1 rounded-full shadow animate-pulse">الموقع تحت الإنشاء</span><?php endif; ?>
<div class="flex justify-center pt-4"><img src="<?= e(img($S['logo'])) ?>" alt="شعار <?= e(seo_name($S)) ?>" data-dbg class="h-28 md:h-36 w-auto object-contain rounded-2xl logo-glow"></div>
<h1 class="text-2xl md:text-4xl font-black tracking-wide drop-shadow"><?= e($S['main_heading']) ?></h1>
<p class="text-sm md:text-base text-red-100 max-w-xl mx-auto leading-relaxed"><?= nl2br(e($S['sub_message'])) ?></p>
</section>


<?= $stp !== 'top' ? $stb : '' ?>
<section class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-6 md:p-8 shadow-2xl">
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/15 pb-4 mb-6 text-sm">
<div class="flex items-center gap-2 font-bold text-amber-300"><i data-lucide="trophy" class="w-4 h-4"></i><span><?= e($m['competition']) ?><?= $m['round'] !== '' ? ' - ' . e($m['round']) : '' ?></span></div>
<?php $ph = ['first' => 1, 'second' => 1, 'half' => 1]; if (isset($ph[$lk['st']])): ?><span class="<?= $lk['pz'] ? 'bg-amber-600' : 'bg-red-600 animate-pulse' ?> text-white px-3 py-1 rounded-full text-xs font-extrabold flex items-center gap-1.5"><span class="w-2 h-2 bg-white rounded-full"></span><?= $lk['pz'] ? 'المباراة موقوفة' : ($lk['st'] === 'half' ? 'استراحة بين الشوطين' : 'مباشر الآن') ?><?php if ($lk['run']): ?> (<span class="clk" dir="ltr"><?= e($lk['clk']) ?></span>)<?php endif; ?></span>
<?php elseif ($lk['st'] === 'finished'): ?><span class="bg-emerald-600 text-white px-3 py-1 rounded-full text-xs font-semibold">انتهت المباراة</span>
<?php elseif ($lk['st'] === 'cancelled'): ?><span class="bg-gray-700 text-white px-3 py-1 rounded-full text-xs font-semibold">المباراة ملغاة</span>
<?php else: ?><span class="bg-white/15 text-white px-3 py-1 rounded-full text-xs font-semibold">المباراة المقبلة</span><?php endif; ?>
</div>
<div class="grid grid-cols-3 items-center text-center gap-2 my-6">
<div class="flex flex-col items-center space-y-2"><img src="<?= e(img($m['home_logo'])) ?>" alt="شعار <?= e($m['home_team']) ?>" class="h-16 md:h-20 w-auto object-contain rounded-xl"><div><h2 class="text-sm md:text-lg font-extrabold"><a href="<?= e(u_team($m['home_team'])) ?>" class="hover:underline"><?= e($m['home_team']) ?></a></h2><?php if ($m['home_rank'] !== ''): ?><span class="text-[10px] bg-amber-400 text-red-950 px-2 py-0.5 rounded-full font-bold"><?= e($m['home_rank']) ?></span><?php endif; ?></div></div>
<div class="flex flex-col items-center justify-center">
<?php if (in_array($lk['st'], ['first', 'half', 'second', 'finished'], true)): $a1 = (int)$S['home_score']; $a2 = (int)$S['away_score']; ?>
<?php if (in_array($lk['st'], ['first', 'second', 'half'], true)): ?>
<div class="mb-2 inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm md:text-base font-black tabular-nums shadow text-white <?= $lk['st'] === 'half' ? 'bg-sky-700/70 border-sky-300/40' : ($lk['pz'] ? 'bg-amber-600/80 border-amber-300/40' : 'bg-red-600/80 border-red-300/40') ?>">
<?php if ($lk['st'] === 'half'): ?><span>☕</span><span>استراحة</span>
<?php else: ?><span class="w-2 h-2 rounded-full bg-white <?= $lk['pz'] ? '' : 'animate-pulse' ?>"></span><span class="clk" dir="ltr"><?= e($lk['clk']) ?></span><?php if ($lk['pz']): ?><span class="text-[10px] font-bold">موقوفة</span><?php endif; ?><?php endif; ?>
</div>
<?php endif; ?>
<div class="text-3xl md:text-5xl font-black tracking-wider bg-black/30 px-4 py-2 rounded-2xl border border-white/15"><?= $a1 ?><span class="mx-1 text-red-400">-</span><?= $a2 ?></div>
<?php else: ?><div class="text-xl md:text-3xl font-black text-amber-300 mb-1"><?= $lk['st'] === 'cancelled' ? 'ملغاة' : 'VS' ?></div><div class="bg-black/30 px-3 py-1.5 rounded-xl border border-white/10 text-xs md:text-sm"><div class="font-bold"><?= e($m['time']) ?></div><div class="text-gray-300 text-[10px]"><?= e($m['date']) ?></div></div><?php endif; ?>
</div>
<div class="flex flex-col items-center space-y-2"><img src="<?= e(img($m['away_logo'])) ?>" alt="شعار <?= e($m['away_team']) ?>" class="h-16 md:h-20 w-auto object-contain rounded-xl bg-white/90 p-1.5"><div><h2 class="text-sm md:text-lg font-extrabold"><a href="<?= e(u_team($m['away_team'])) ?>" class="hover:underline"><?= e($m['away_team']) ?></a></h2><?php if ($m['away_rank'] !== ''): ?><span class="text-[10px] bg-white/20 text-white px-2 py-0.5 rounded-full font-bold"><?= e($m['away_rank']) ?></span><?php endif; ?></div></div>
</div>
<?php if ($m['date'] !== '' && $m['time'] !== ''): ?>
<div class="flex justify-center mb-3"><div class="inline-flex flex-wrap items-center justify-center gap-x-2 gap-y-0.5 bg-black/30 border border-amber-300/30 rounded-full px-4 py-1.5 text-xs md:text-sm shadow"><span class="text-base">⏰</span><span class="text-gray-300">ساعة الانطلاقة</span><b class="text-amber-300 text-sm md:text-base tabular-nums" dir="ltr"><?= e($m['time']) ?></b><span class="text-gray-400 text-[11px]"><?= e($m['date']) ?></span><a href="<?= e(u_match(match_id($m))) ?>" class="text-amber-300 text-[11px] hover:underline">تفاصيل المباراة</a></div></div>
<?php endif; ?>
<div class="flex justify-center mb-3"><button type="button" data-track="lineup" onclick="const b=document.getElementById('lubox');b.classList.toggle('hidden');this.querySelector('b').textContent=b.classList.contains('hidden')?'عرض تشكيلة المباراة':'إخفاء التشكيلة'" class="inline-flex items-center gap-2 bg-gradient-to-l from-amber-400 to-amber-300 text-red-950 font-black px-6 py-2.5 rounded-full text-sm shadow-lg ring-2 ring-white/30 hover:scale-105 transition cursor-pointer <?= lineup_ready($S) ? 'animate-pulse' : '' ?>"><span class="text-lg">👕</span><b>عرض تشكيلة المباراة</b></button></div>
<div id="lubox" class="hidden mb-4"><?= lineup_html($S) ?></div>
<?php if (!$closed): ?>
<div id="countdown" class="grid grid-cols-4 gap-2 max-w-sm mx-auto my-4 text-center">
<?php foreach (['days' => 'يوم', 'hours' => 'ساعة', 'minutes' => 'دقيقة', 'seconds' => 'ثانية'] as $k => $l): ?><div class="bg-black/30 rounded-xl p-2 border border-white/10"><span id="<?= $k ?>" class="block text-lg font-black <?= $k === 'seconds' ? 'text-amber-300' : '' ?>">00</span><span class="text-[10px] text-gray-300"><?= $l ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-2 pt-4 border-t border-white/15 text-xs">
<?php foreach (['stadium' => 'الملعب', 'city' => 'المدينة', 'date' => 'التاريخ', 'weather' => 'حالة الطقس'] as $k => $l): if ($m[$k] === '') continue; ?><div class="bg-black/20 p-2.5 rounded-xl"><span class="block text-gray-300 text-[10px]"><span class="text-sm"><?= $ei[$k] ?></span> <?= $l ?></span><span class="font-bold <?= $k === 'weather' ? 'text-amber-300' : '' ?>"><?= e($m[$k]) ?></span></div><?php endforeach; ?>
</div>
</section>

<?php if ($lk['st'] !== 'idle' && $L['events']): ?>
<section class="bg-black/25 backdrop-blur-md border border-white/15 rounded-3xl p-6 shadow-xl">
<h3 class="text-base font-bold mb-4 flex items-center gap-2"><i data-lucide="activity" class="w-4 h-4 text-amber-300"></i> أحداث المباراة</h3>
<div class="space-y-2.5">
<?php foreach (array_reverse($L['events']) as $v): [$tt, $dt, $rs] = ev_txt($v); $void = !empty($v['void']);
$box = '<div class="' . ($void ? 'line-through opacity-60' : '') . '"><div class="font-bold">' . e($tt) . '</div>' . ($dt !== '' ? '<div class="text-[11px] text-gray-300">' . e($dt) . '</div>' : '') . ($rs !== '' ? '<div class="text-[10px] text-amber-200">' . e($rs) . '</div>' : '') . '</div>';
$pill = '<div class="flex items-center gap-1.5 bg-black/30 border border-white/10 rounded-full px-2.5 py-1"><span class="text-amber-300 font-bold text-[11px]">' . e($v['min']) . '</span>' . ev_ico($void ? 'goal_cancel' : $v['type']) . '</div>';
if ($v['team'] === ''): ?>
<div class="flex justify-center"><div class="flex items-center gap-2 bg-black/30 border border-white/10 rounded-full px-3 py-1.5 text-xs"><span class="text-amber-300 font-bold"><?= e($v['min']) ?></span><?= ev_ico($v['type']) ?><span class="font-bold"><?= e($tt) ?><?= $dt !== '' ? ' ' . e($dt) : '' ?></span><?php if ($rs !== ''): ?><span class="text-[10px] text-amber-200">— <?= e($rs) ?></span><?php endif; ?></div></div>
<?php else: ?>
<div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-xs"><div><?= $v['team'] === 'home' ? $box : '' ?></div><?= $pill ?><div class="text-left"><?= $v['team'] === 'away' ? $box : '' ?></div></div>
<?php endif; endforeach; ?>
</div>
</section>
<?php endif; ?>

<?php if ($good): ?>
<section class="bg-emerald-950/80 backdrop-blur-md border border-emerald-500/40 rounded-3xl p-6 shadow-xl space-y-3">
<h3 class="text-base font-bold text-emerald-300 flex items-center gap-2"><i data-lucide="check-circle-2" class="w-5 h-5"></i> لوحة الشرف: أصحاب التوقع الصحيح (<?= e($S['final_home'] . ' - ' . $S['final_away']) ?>)</h3>
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-xs"><?php foreach ($good as $n): ?><div class="bg-black/30 p-3 rounded-xl text-center font-bold text-emerald-300 border border-emerald-500/20"><?= e($n) ?></div><?php endforeach; ?></div>
</section>
<?php endif; ?>

<?= vote_teaser_html($S) ?>

<?= gallery_home_html($S) ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<section class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-6 shadow-xl">
<h3 class="text-base font-bold mb-3 flex items-center gap-2"><i data-lucide="help-circle" class="w-4 h-4 text-amber-300"></i> توقع نتيجة المباراة</h3>
<?= predict_form_html($m, $pclosed) ?>
<a href="index.php?matchpredictions=1" data-track="mp_page" class="block text-center text-[11px] text-amber-300 hover:underline mt-3">إحصائيات التوقعات وصفحة المباراة ←</a>
</section>
<section class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
<div><h3 class="text-base font-bold mb-2 flex items-center gap-2"><i data-lucide="mail" class="w-4 h-4 text-amber-300"></i> القائمة البريدية</h3><p class="text-xs text-gray-200 leading-relaxed mb-4">اشترك لتصلك إشعارات النتائج والأهداف فور صافرة النهاية.</p></div>
<form method="POST" class="space-y-3">
<input type="hidden" name="t" value="<?= tok() ?>"><input type="hidden" name="action" value="subscribe">
<input type="text" name="web" class="hidden" tabindex="-1" autocomplete="off">
<input type="email" name="email" required maxlength="120" placeholder="example@email.com" dir="ltr" class="w-full bg-black/30 border border-white/20 rounded-xl px-3 py-2.5 text-xs text-white text-right">
<button type="submit" class="w-full bg-amber-400 text-red-950 font-extrabold py-2.5 rounded-xl text-xs hover:bg-amber-300 transition cursor-pointer">اشتراك فوري</button>
</form>
</section>
</div>

<?php if ($past): $N = count($past); $pm = max(1, min($N, (int)($_GET['pm'] ?? $N))); $lm = $past[$pm - 1];
$pgs = array_values(array_unique(array_filter([1, $pm - 2, $pm - 1, $pm, $pm + 1, $pm + 2, $N], fn($q) => $q >= 1 && $q <= $N))); sort($pgs); ?>
<section id="past" class="bg-black/25 backdrop-blur-md border border-white/15 rounded-3xl p-6 shadow-xl">
<div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-3 mb-4 text-xs font-bold text-gray-300">
<div class="flex items-center gap-1.5 text-amber-300"><i data-lucide="history" class="w-4 h-4"></i><span>نتيجة المباراة السابقة<?= $lm['competition'] !== '' ? ' (' . e($lm['competition']) . ($lm['round'] !== '' ? ' - ' . e($lm['round']) : '') . ')' : '' ?></span></div>
<div class="flex items-center gap-2"><span class="bg-amber-400 text-red-950 text-[10px] font-black px-2 py-0.5 rounded-full">المباراة رقم <?= (int)$lm['no'] ?> من <?= $N ?></span><span>📅 <?= e($lm['date']) ?></span><a href="<?= e(u_match($lm['id'])) ?>" class="text-amber-300 hover:underline">تفاصيل المباراة</a></div>
</div>
<div class="grid grid-cols-3 items-center text-center gap-2">
<div class="flex flex-col items-center space-y-1.5"><img src="<?= e(img($lm['home_logo'])) ?>" alt="شعار <?= e($lm['home_team']) ?>" class="h-12 w-auto object-contain"><a href="<?= e(u_team($lm['home_team'])) ?>" class="text-xs md:text-sm font-bold hover:underline"><?= e($lm['home_team']) ?></a></div>
<div class="text-2xl md:text-3xl font-black tracking-widest bg-white/10 px-4 py-1.5 rounded-xl"><?= (int)$lm['home_score'] ?><span class="text-red-400 mx-1">-</span><?= (int)$lm['away_score'] ?></div>
<div class="flex flex-col items-center space-y-1.5"><img src="<?= e(img($lm['away_logo'])) ?>" alt="شعار <?= e($lm['away_team']) ?>" class="h-12 w-auto object-contain bg-white/90 p-1 rounded"><a href="<?= e(u_team($lm['away_team'])) ?>" class="text-xs md:text-sm font-bold hover:underline"><?= e($lm['away_team']) ?></a></div>
</div>
<?php if ($lm['stadium'] !== '' || $lm['city'] !== ''): ?><div class="flex flex-wrap justify-center gap-4 mt-3 text-[11px] text-gray-300"><?php if ($lm['stadium'] !== ''): ?><span><span class="text-sm">🏟️</span> الملعب: <?= e($lm['stadium']) ?></span><?php endif; ?><?php if ($lm['city'] !== ''): ?><span><span class="text-sm">📍</span> المدينة: <?= e($lm['city']) ?></span><?php endif; ?></div><?php endif; ?>
<?php if ($N > 1): ?>
<nav class="flex flex-wrap items-center justify-center gap-1.5 mt-5 text-xs" aria-label="ترقيم المباريات">
<?php if ($pm > 1): ?><a href="index.php?pm=<?= $pm - 1 ?>#past" class="px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/25">الأقدم</a><?php endif; ?>
<?php $pv = 0; foreach ($pgs as $q): if ($q - $pv > 1): ?><span class="px-1">…</span><?php endif; $pv = $q; ?><a href="index.php?pm=<?= $q ?>#past" class="px-3 py-1.5 rounded-lg <?= $q === $pm ? 'bg-amber-400 text-red-950 font-bold' : 'bg-white/15 hover:bg-white/25' ?>"><?= $q ?></a><?php endforeach; ?>
<?php if ($pm < $N): ?><a href="index.php?pm=<?= $pm + 1 ?>#past" class="px-3 py-1.5 rounded-lg bg-white/15 hover:bg-white/25">الأحدث</a><?php endif; ?>
</nav>
<div class="text-center mt-3"><a href="index.php?archive=1" class="text-[11px] text-amber-300 hover:underline">عرض أرشيف جميع المباريات</a></div>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if ($vb): ?></div><div class="space-y-6"><div class="w-full lg:sticky lg:top-6 overflow-hidden" data-track="banner_v"><?= $S['banners']['vertical_code'] ?></div></div></div><?php else: ?></div><?php endif; ?>
</div>
<script>window.CLK=<?= json_encode(['st' => $lk['st'], 'per' => $lk['per'], 'e' => $lk['e'], 'run' => $lk['run'], 'half' => $lk['half'], 'lim' => $lk['lim']]) ?>;CLK.t0=Date.now();</script>
<script>
(()=>{const t=new Date("<?= e($mt) ?>").getTime(),c=document.getElementById("countdown");if(!c||isNaN(t))return;
const p=n=>String(n).padStart(2,"0"),g=i=>document.getElementById(i);
const f=()=>{const d=Math.max(0,t-Date.now());g("days").textContent=p(Math.floor(d/864e5));g("hours").textContent=p(Math.floor(d%864e5/36e5));g("minutes").textContent=p(Math.floor(d%36e5/6e4));g("seconds").textContent=p(Math.floor(d%6e4/1e3))};f();setInterval(f,1000)})();
</script>
<script>
(()=>{const b=document.getElementById('stbox');if(!b)return;let L;try{L=localStorage}catch(e){return}const K='wsse_st_'+(b.dataset.team||''),els=[...b.querySelectorAll('[data-st]')],cur={};
els.forEach(e=>cur[e.dataset.st]=+e.dataset.v);
let base=null;try{base=JSON.parse(L[K]||'null')}catch(e){}
if(!base){try{L[K]=JSON.stringify(cur)}catch(e){}return}
let fix=false;for(const k in cur)if(cur[k]<(base[k]||0)){base[k]=cur[k];fix=true}
if(fix)try{L[K]=JSON.stringify(base)}catch(e){}
const mk=document.getElementById('stmark');let any=false;
els.forEach(e=>{const d=cur[e.dataset.st]-(base[e.dataset.st]||0);if(d>0){any=true;const s=document.createElement('sup');s.className='stnew text-emerald-300 text-[10px] font-black ms-1';s.textContent='+'+d;e.after(s);e.parentElement.classList.add('bg-emerald-500/15')}});
if(any&&mk){mk.classList.remove('hidden');mk.onclick=()=>{try{L[K]=JSON.stringify(cur)}catch(e){}b.querySelectorAll('.stnew').forEach(x=>x.remove());els.forEach(e=>e.parentElement.classList.remove('bg-emerald-500/15'));mk.classList.add('hidden')}}})();
</script>
<script>
(()=>{const r=document.getElementById('rembar'),t=document.getElementById('remt');if(!r||!t)return;const dk=+r.dataset.dk,t0=Date.now(),p=n=>String(n).padStart(2,'0');
const f=()=>{const s=Math.floor(dk-(Date.now()-t0)/1000);if(s<=0){r.remove();return false}const h=Math.floor(s/3600),m=Math.floor(s%3600/60);t.textContent=(h?p(h)+':':'')+p(m)+':'+p(s%60);return true};
if(f()){const i=setInterval(()=>{if(!f())clearInterval(i)},1000)}})();
</script>
<?php foot();
