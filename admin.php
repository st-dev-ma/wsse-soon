<?php
require __DIR__ . '/inc.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
$cfg = jget('config.json');
function flash($m, $tab = '') { $_SESSION['fl'] = $m; header('Location: admin.php' . ($tab ? '#' . $tab : '')); exit; }
function shell($t) { ?><!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow"><title><?= e($t) ?></title><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap"><script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script><style>body{font-family:'Cairo',sans-serif}</style></head><?php }

function st_flat($ST) {
    $f = ['v' => 0, 's' => 0, 'u' => 0, 'k' => 0];
    foreach (($ST['days'] ?? []) as $x) {
        foreach (['v', 's', 'u'] as $q) $f[$q] += (int)($x[$q] ?? 0);
        foreach (['p', 'c', 'd', 'o', 'b', 'r', 'k'] as $q) foreach (($x[$q] ?? []) as $kk => $vv) { $f[$q . ':' . $kk] = ($f[$q . ':' . $kk] ?? 0) + $vv; if ($q === 'k') $f['k'] += $vv; }
    }
    foreach (($ST['ev'] ?? []) as $kk => $vv) $f['ev:' . $kk] = (int)$vv;
    return $f;
}
// ===== نظام الترقية: قائمة مسارات مسموحة + نسخ احتياطي + استرجاع =====
function upg_ok($n) { return in_array($n, ['inc.php', 'api.php', 'admin.php', 'index.php', 'contact.php', 'advertise.php', 'manifest.php', 'icon.php', 'parts.php', 'error.php', 'seo.php', 'sitemap.php', 'rss.php', 'robots.php', 'sw.js', 'WSSE_files/app.js', 'WSSE_files/logo.svg'], true); }
function chk_pw($pw) { $h = jget('config.json')['hash'] ?? ''; return $h !== '' ? password_verify($pw, $h) : hash_equals('wsse2026', $pw); }
function upg_read($zp) {
    $z = new ZipArchive; if ($z->open($zp) !== true) return [null, 'ملف ZIP غير صالح.'];
    if ($z->numFiles < 1 || $z->numFiles > 40) return [null, 'عدد الملفات في الحزمة غير مقبول.'];
    $files = []; $bad = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $st = $z->statIndex($i); $n = str_replace('\\', '/', (string)$st['name']);
        if (substr($n, -1) === '/') continue;
        if (strpos($n, 'wsse/') === 0) $n = substr($n, 5);
        if ($n === 'UPGRADE.txt') continue;
        if (!upg_ok($n)) { $bad[] = $n . ' (مسار غير مسموح)'; continue; }
        if ($st['size'] > 2097152) { $bad[] = $n . ' (أكبر من 2MB)'; continue; }
        $d = $z->getFromIndex($i); if ($d === false || $d === '') { $bad[] = $n . ' (فارغ)'; continue; }
        if (isset($files[$n])) { $bad[] = $n . ' (مكرر)'; continue; }
        if (substr($n, -4) === '.php' && function_exists('token_get_all')) { try { token_get_all($d, TOKEN_PARSE); } catch (Throwable $e) { $bad[] = $n . ' (خطأ صياغة PHP سطر ' . $e->getLine() . ')'; continue; } }
        $files[$n] = $d;
    }
    $z->close();
    if ($bad) return [null, 'رُفضت الحزمة ولم يُغيَّر شيء: ' . implode('، ', array_slice($bad, 0, 8))];
    if (!$files) return [null, 'لا توجد ملفات مسموحة في الحزمة.'];
    return [$files, ''];
}
function upg_apply($files, $label) {
    $dir = DD . 'backups/'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) return 'مجلد data/backups غير قابل للكتابة.';
    $bn = 'upg-' . date('Ymd-His') . '.zip'; $bz = new ZipArchive; $new = [];
    if ($bz->open($dir . $bn, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return 'تعذر إنشاء النسخة الاحتياطية.';
    foreach ($files as $n => $d) { $p = __DIR__ . '/' . $n; if (is_file($p)) $bz->addFile($p, $n); else $new[] = $n; }
    $bz->addFromString('_manifest.json', json_encode(['date' => date('Y-m-d H:i:s'), 'label' => $label, 'files' => array_keys($files), 'new' => $new], JSON_UNESCAPED_UNICODE));
    if (!$bz->close()) return 'تعذر حفظ النسخة الاحتياطية، لم يُغيَّر شيء.';
    $tmp = [];
    foreach ($files as $n => $d) {
        $p = __DIR__ . '/' . $n; if (!is_dir(dirname($p))) @mkdir(dirname($p), 0775, true);
        if (@file_put_contents($p . '.upg', $d) === false) { foreach ($tmp as $t) @unlink($t); @unlink($p . '.upg'); return 'تعذرت الكتابة في ' . $n . ' (تحقق من الصلاحيات)، لم يُغيَّر شيء.'; }
        $tmp[$n] = $p . '.upg';
    }
    foreach ($tmp as $n => $t) {
        $p = __DIR__ . '/' . $n; @chmod($t, is_file($p) ? (fileperms($p) & 0777) : 0644);
        if (!@rename($t, $p)) { @unlink($t); return 'تعذر استبدال ' . $n . ' — استرجع النسخة ' . $bn . ' من تبويب الترقية.'; }
        if (function_exists('opcache_invalidate')) @opcache_invalidate($p, true);
    }
    $all = glob($dir . 'upg-*.zip') ?: []; sort($all);
    foreach (array_slice($all, 0, max(0, count($all) - 10)) as $o) @unlink($o);
    return '';
}
function upg_rollback($bn) {
    $f = DD . 'backups/' . $bn;
    if (!preg_match('/^upg-\d{8}-\d{6}\.zip$/', $bn) || !is_file($f)) return 'النسخة غير موجودة.';
    $z = new ZipArchive; if ($z->open($f) !== true) return 'النسخة تالفة.';
    $m = json_decode((string)$z->getFromName('_manifest.json'), true) ?: []; $files = [];
    for ($i = 0; $i < $z->numFiles; $i++) { $n = (string)$z->getNameIndex($i); if (upg_ok($n)) { $d = $z->getFromIndex($i); if ($d !== false && $d !== '') $files[$n] = $d; } }
    $z->close();
    if ($files && ($r = upg_apply($files, 'استرجاع ' . $bn)) !== '') return $r;
    foreach (($m['new'] ?? []) as $n) if (upg_ok($n) && is_file(__DIR__ . '/' . $n)) @unlink(__DIR__ . '/' . $n);
    return '';
}

if (isset($_GET['logout']) && hash_equals(tok(), (string)$_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

if (!empty($_SESSION['adm']) && $_SESSION['adm'] < time() - 7200) unset($_SESSION['adm']);
if (empty($_SESSION['adm'])) {
    $err = '';
    if (($_POST['act'] ?? '') === 'login') {
        chk();
        if (!rl('l' . ip(), 5, 900)) $err = 'محاولات كثيرة، أعد المحاولة بعد 15 دقيقة.';
        else {
            $pw = (string)($_POST['password'] ?? '');
            $ok = !empty($cfg['hash']) ? password_verify($pw, $cfg['hash']) : hash_equals('wsse2026', $pw);
            if ($ok) { session_regenerate_id(true); $_SESSION['adm'] = time(); header('Location: admin.php'); exit; }
            $err = 'كلمة المرور غير صحيحة.';
        }
    }
    shell('تسجيل دخول المشرف'); ?>
<body class="bg-white text-gray-800 min-h-screen flex items-center justify-center p-4">
<form method="POST" class="bg-gray-50 border border-gray-200 shadow-sm p-8 rounded-3xl max-w-sm w-full space-y-4 text-center">
<input type="hidden" name="t" value="<?= tok() ?>"><input type="hidden" name="act" value="login">
<h1 class="text-xl font-bold text-red-800">لوحة تحكم المشرف</h1>
<?php if ($err): ?><div class="text-xs text-red-600 font-bold"><?= e($err) ?></div><?php endif; ?>
<input type="password" name="password" placeholder="كلمة المرور" required autocomplete="current-password" class="w-full bg-white border border-gray-300 rounded-xl px-4 py-2.5 text-center text-sm">
<button type="submit" class="w-full bg-red-700 text-white font-bold py-2.5 rounded-xl hover:bg-red-600 cursor-pointer text-sm">دخول</button>
<a href="index.php" class="block text-xs text-gray-500 hover:underline pt-2">العودة للموقع الرئيسي</a>
</form></body></html>
<?php exit; }

$_SESSION['adm'] = time(); vapid();
$LM = ['home_team' => 'المستضيف', 'home_logo' => 'شعار المستضيف', 'home_score' => 'أهداف المستضيف', 'away_team' => 'الضيف', 'away_logo' => 'شعار الضيف', 'away_score' => 'أهداف الضيف', 'date' => 'التاريخ', 'stadium' => 'الملعب', 'city' => 'المدينة', 'competition' => 'البطولة', 'round' => 'الجولة'];
$M = ['home_team' => 'الفريق المستضيف', 'home_logo' => 'شعار المستضيف', 'home_rank' => 'ترتيب المستضيف (مثال: المركز 2)', 'away_team' => 'الفريق الضيف', 'away_logo' => 'شعار الضيف', 'away_rank' => 'ترتيب الضيف (مثال: المركز 5)', 'date' => 'التاريخ', 'time' => 'الوقت', 'stadium' => 'الملعب', 'city' => 'المدينة', 'competition' => 'البطولة', 'round' => 'الجولة', 'weather' => 'حالة الطقس المتوقعة'];
$ty = ['date' => 'date', 'time' => 'time', 'home_score' => 'number', 'away_score' => 'number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    chk();
    $a = (string)($_POST['act'] ?? '');
    $P = fn($k) => trim((string)($_POST[$k] ?? ''));
    $I = (int)($_POST['i'] ?? -1);
    $fn = null; $tab = 't1';
    switch ($a) {
        case 'logo':
            if ($n = up('logo_file', 'logo_')) $fn = fn($d) => array_merge($d, ['logo' => $n]); else flash('تعذر رفع الشعار: ' . $GLOBALS['ue'], 't1');
            break;
        case 'identity':
            $v = ['site_title' => $P('site_title'), 'main_heading' => $P('main_heading'), 'sub_message' => $P('sub_message'), 'bg_color' => preg_match('/^#[0-9a-f]{6}$/i', $P('bg_color')) ? $P('bg_color') : '#991b1b'];
            if ($f = up('bg_file', 'bg_', 8388608)) $v['bg_image'] = $f;
            elseif (isset($_POST['bg_none'])) $v['bg_image'] = '';
            elseif ($P('bg_url') !== '') $v['bg_image'] = su($P('bg_url'));
            if (!empty($GLOBALS['ue'])) flash('تعذر رفع الخلفية: ' . $GLOBALS['ue'], 't1');
            $tl = up('team_logo_f', 'team_'); if (!empty($GLOBALS['ue'])) flash('تعذر رفع شعار الفريق: ' . $GLOBALS['ue'], 't1');
            $v['team'] = ['name' => mb_substr($P('team_name'), 0, 60), 'logo' => $tl ?: ($P('team_logo') === '' ? '' : img($P('team_logo')))];
            $fn = fn($d) => array_merge($d, $v);
            if ($P('new_pass') !== '') { if (strlen($P('new_pass')) < 8) flash('كلمة المرور يجب ألا تقل عن 8 أحرف.', 't1'); $cfg['hash'] = password_hash($P('new_pass'), PASSWORD_DEFAULT); jup('config.json', fn($z) => array_merge($z, ['hash' => $cfg['hash']]), []); }
            break;
        case 'social':
            $tab = 't2'; $v = ['social' => ['active' => isset($_POST['social_active']), 'facebook' => su($P('soc_fb')), 'twitter' => su($P('soc_tw')), 'instagram' => su($P('soc_ig')), 'youtube' => su($P('soc_yt'))]];
            $fn = fn($d) => array_merge($d, $v); break;
        case 'banners':
            $tab = 't3'; $v = ['banners' => ['horizontal_active' => isset($_POST['bh']), 'horizontal_code' => (string)($_POST['bh_code'] ?? ''), 'vertical_active' => isset($_POST['bv']), 'vertical_code' => (string)($_POST['bv_code'] ?? '')]];
            $fn = fn($d) => array_merge($d, $v); break;
        case 'lv_kickoff': case 'lv_half': case 'lv_second': case 'lv_pause': case 'lv_resume': case 'lv_finish': case 'lv_cancel': case 'lv_stop': case 'lv_event': case 'lv_edit_ev': case 'lv_reset':
            $tab = 't4'; $in = $_POST; $fn = fn($d) => lv_act($d, $a, $in); break;
        case 'lv_del_ev':
            $tab = 't4'; $in = ['id' => (string)($_POST['i'] ?? '')]; $fn = fn($d) => lv_act($d, $a, $in); break;
        case 'next_match': case 'last_match': case 'add_past': case 'edit_past': case 'add_fixture':
            $fx = $a === 'add_fixture'; $nx = $a === 'next_match' || $fx; [$fields, $g, $key] = $fx ? [$M, 'fx', 'fixtures'] : ($nx ? [$M, 'm', 'match'] : [$LM, 'lm', 'last_match']); $tab = $nx ? 't5' : 't6'; $v = [];
            foreach ($fields as $k => $l) {
                $x = (string)($_POST[$g][$k] ?? '');
                if (substr($k, -5) === 'score') $v[$k] = max(0, (int)$x);
                elseif (substr($k, -4) === 'logo') { $v[$k] = ($f = up($g . '_' . $k . '_f', 'team_')) ? $f : (trim($x) === '' ? '' : img($x)); if (!empty($GLOBALS['ue'])) flash('تعذر رفع الشعار: ' . $GLOBALS['ue'], $tab); }
                else $v[$k] = trim($x);
            }
            if ($a === 'add_fixture') $fn = function ($d) use ($v) { $d['fixtures'][] = $v; return $d; };
            elseif ($a === 'add_past') $fn = function ($d) use ($v) { $d['archive'][] = $v; return $d; };
            elseif ($a === 'edit_past') $fn = function ($d) use ($v, $I) { if (isset($d['archive'][$I])) $d['archive'][$I] = $v; return $d; };
            else $fn = function ($d) use ($key, $v) {
                $old = $d['match'] ?? []; $d = array_merge($d, [$key => $v]);
                // مباراة جديدة بعد انتهاء السابقة: تهيئة البث الحي تلقائياً (نتيجة السابقة مؤرشفة مسبقاً)
                if ($key === 'match' && in_array($d['live']['st'] ?? 'idle', ['finished', 'cancelled'], true) && md5(($old['date'] ?? '') . ($old['home_team'] ?? '') . ($old['away_team'] ?? '')) !== md5(($v['date'] ?? '') . ($v['home_team'] ?? '') . ($v['away_team'] ?? ''))) $d = lv_act($d, 'lv_reset', []);
                return $d;
            };
            break;
        case 'footer':
            $tab = 't7'; $v = ['footer' => ['copyright' => $P('copyright'), 'show_contact' => isset($_POST['show_contact']), 'show_advertise' => isset($_POST['show_advertise']), 'email' => $P('f_email'), 'contact_intro' => $P('contact_intro'), 'advertise_text' => $P('advertise_text')]];
            $fn = fn($d) => array_merge($d, $v); break;
        case 'del_msg': jup('messages.json', function ($x) use ($I) { unset($x[$I]); return array_values($x); }, []); flash('تم الحذف.', 't13');
        case 'clear_msgs': jup('messages.json', fn() => [], []); flash('تم الحذف.', 't13');
        case 'read_msg': jup('messages.json', function ($x) use ($I) { if (isset($x[$I])) $x[$I]['read'] = true; return $x; }, []); flash('تم التعليم كمقروءة.', 't13');
        case 'read_all': jup('messages.json', function ($x) { foreach ($x as &$r) $r['read'] = true; return $x; }, []); flash('تم تعليم الكل كمقروء.', 't13');
        case 'push_msg':
            $pt = mb_substr($P('pt'), 0, 80); $pb = mb_substr($P('pb'), 0, 200);
            if ($pt === '') flash('أدخل عنوان الإشعار.', 't12');
            jup('push.json', function ($x) use ($pt, $pb) { $x['ann'] = ['t' => $pt, 'b' => $pb, 'ts' => time()]; return $x; }, []);
            push_later(true); flash('جارٍ إرسال الإشعار إلى ' . count(jget('push.json')['subs'] ?? []) . ' جهاز.', 't12');
        case 'backup':
            if (!class_exists('ZipArchive')) flash('امتداد ZipArchive غير مفعّل على الخادم.', 't14');
            $tmp = tempnam(sys_get_temp_dir(), 'wsse'); $z = new ZipArchive;
            if ($z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) flash('تعذر إنشاء الأرشيف.', 't14');
            foreach (glob(DD . '*.json') as $f) $z->addFile($f, 'data/' . basename($f));
            if (($_POST['what'] ?? '') === 'all') foreach (glob(__DIR__ . '/WSSE_files/uploads/*') as $f) if (is_file($f)) $z->addFile($f, 'WSSE_files/uploads/' . basename($f));
            $z->close();
            header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="wsse-backup-' . date('Ymd-His') . '.zip"'); header('Content-Length: ' . filesize($tmp));
            readfile($tmp); unlink($tmp); exit;
        case 'del_sub': jup('subscribers.json', function ($x) use ($I) { unset($x[$I]); return array_values($x); }, []); flash('تم حذف المشترك.', 't8');
        case 'clear_subs': jup('subscribers.json', fn() => [], []); flash('تم حذف القائمة.', 't8');
        case 'mail':
            $sj = str_replace(["\r", "\n"], ' ', $P('subject')); $bd = trim((string)($_POST['body'] ?? '')); $subs = jget('subscribers.json');
            if ($sj === '' || $bd === '' || !$subs) flash('أدخل العنوان والنص ويجب أن تكون القائمة غير فارغة.', 't8');
            $host = preg_replace('/[^a-z0-9.\-]/i', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]); $n = 0;
            foreach (array_chunk($subs, 40) as $ch) {
                $h = "From: WSSE <noreply@$host>\r\nBcc: " . implode(',', array_filter($ch, fn($z) => filter_var($z, FILTER_VALIDATE_EMAIL))) . "\r\nContent-Type: text/plain; charset=UTF-8";
                if (@mail('noreply@' . $host, '=?UTF-8?B?' . base64_encode($sj) . '?=', $bd, $h)) $n += count($ch);
            }
            flash("تم إرسال الرسالة إلى $n مشترك.", 't8');
        case 'preds_show': $tab = 't9'; $v = ['show_correct_predictions' => isset($_POST['show_correct'])]; $fn = fn($d) => array_merge($d, $v); break;
        case 'del_pred': jup('predictions.json', function ($x) use ($I) { unset($x[$I]); return array_values($x); }, []); flash('تم الحذف.', 't9');
        case 'clear_preds': jup('predictions.json', fn() => [], []); flash('تم حذف جميع التوقعات.', 't9');
        case 'motm':
            $tab = 't10'; $names = (array)($_POST['cand'] ?? []); $new = $P('new_cand'); $q = $P('motm_question'); $act = isset($_POST['motm_active']);
            $fn = function ($d) use ($names, $new, $q, $act) {
                foreach ($names as $k => $nm) if (isset($d['motm']['candidates'][$k]) && trim($nm) !== '') $d['motm']['candidates'][$k]['name'] = mb_substr(trim($nm), 0, 60);
                if ($new !== '' && count($d['motm']['candidates'] ?? []) < 20) $d['motm']['candidates'][] = ['name' => mb_substr($new, 0, 60), 'votes' => 0];
                $d['motm']['question'] = $q; $d['motm']['active'] = $act; return $d;
            }; break;
        case 'del_cand': $tab = 't10'; $fn = function ($d) use ($I) { unset($d['motm']['candidates'][$I]); $d['motm']['candidates'] = array_values($d['motm']['candidates'] ?? []); return $d; }; break;
        case 'reset_votes':
            $tab = 't10'; $fn = function ($d) { foreach ($d['motm']['candidates'] as &$c) $c['votes'] = 0; $d['motm']['poll'] = (int)($d['motm']['poll'] ?? 1) + 1; return $d; }; break;
        case 'gallery':
            $tab = 't11'; $cap = mb_substr($P('gallery_caption'), 0, 120);
            if ($n = up('gallery_img', 'gal_')) $fn = function ($d) use ($n, $cap) { $d['gallery'][] = ['url' => $n, 'caption' => $cap, 'ts' => time()]; return $d; }; else flash('تعذر رفع الصورة: ' . $GLOBALS['ue'], $tab);
            break;
        case 'del_gal':
            $tab = 't11'; $fn = function ($d) use ($I) { $u = $d['gallery'][$I]['url'] ?? ''; if (strpos($u, 'WSSE_files/uploads/') === 0 && is_file(__DIR__ . '/' . $u)) @unlink(__DIR__ . '/' . $u); unset($d['gallery'][$I]); $d['gallery'] = array_values($d['gallery'] ?? []); return $d; }; break;
        case 'squad_save':
            $tab = 't19'; $pz = ['GK', 'DF', 'MF', 'FW']; $pl = [];
            foreach ((array)($_POST['pl'] ?? []) as $id => $r) {
                if (!preg_match('/^[a-f0-9]{6}$/', (string)$id) || isset($_POST['pl_del'][$id]) || !is_array($r)) continue;
                $nm = mb_substr(trim((string)($r['name'] ?? '')), 0, 40); if ($nm === '') continue;
                $pl[] = ['id' => (string)$id, 'name' => $nm, 'no' => max(0, min(99, (int)($r['no'] ?? 0))), 'pos' => in_array($r['pos'] ?? '', $pz, true) ? $r['pos'] : 'MF'];
            }
            foreach ((array)($_POST['nw'] ?? []) as $r) {
                if (!is_array($r)) continue; $nm = mb_substr(trim((string)($r['name'] ?? '')), 0, 40); if ($nm === '') continue;
                $pl[] = ['id' => bin2hex(random_bytes(3)), 'name' => $nm, 'no' => max(0, min(99, (int)($r['no'] ?? 0))), 'pos' => in_array($r['pos'] ?? '', $pz, true) ? $r['pos'] : 'MF'];
            }
            $co = mb_substr($P('coach'), 0, 60); $as2 = mb_substr($P('assistant'), 0, 60);
            $fn = function ($d) use ($pl, $co, $as2) {
                $d['squad'] = ['coach' => $co, 'assistant' => $as2, 'players' => $pl]; $ids = array_column($pl, 'id'); $lu = $d['lineup'] ?? [];
                $lu['xi'] = array_map(fn($i) => in_array($i, $ids, true) ? $i : '', (array)($lu['xi'] ?? [])); $lu['subs'] = array_values(array_intersect((array)($lu['subs'] ?? []), $ids)); $d['lineup'] = $lu; return $d;
            }; break;
        case 'lineup_save':
            $tab = 't19'; $fs = ['4-4-2', '4-3-3', '4-2-3-1', '3-5-2', '3-4-3', '5-3-2', '4-1-4-1', '4-5-1'];
            $f = in_array($_POST['lu_f'] ?? '', $fs, true) ? $_POST['lu_f'] : '4-4-2';
            $ul = up('lu_adl_f', 'lu_'); if (!$ul && !empty($GLOBALS['ue'])) flash('تعذر رفع صورة اللوحة اليسرى: ' . $GLOBALS['ue'], $tab);
            $ur = up('lu_adr_f', 'lu_'); if (!$ur && !empty($GLOBALS['ue'])) flash('تعذر رفع صورة اللوحة اليمنى: ' . $GLOBALS['ue'], $tab);
            $ad = function ($k, $up) use ($P) { $im = $up ?: $P($k . '_img'); $im = (preg_match('~^https?://[^\s"<>]+$~i', $im) || preg_match('~^WSSE_files/[\w./-]+$~', $im)) ? $im : ''; return ['img' => $im, 'url' => lnk($P($k . '_url')), 'alt' => mb_substr($P($k . '_alt'), 0, 100)]; };
            $adl = $ad('lu_adl', $ul); $adr = $ad('lu_adr', $ur); $adon = isset($_POST['lu_ads']); $pub = isset($_POST['lu_pub']);
            $xiIn = (array)($_POST['lu_xi'] ?? []); $subIn = array_map('strval', (array)($_POST['lu_sub'] ?? []));
            $fn = function ($d) use ($f, $adl, $adr, $adon, $pub, $xiIn, $subIn) {
                $ids = array_column($d['squad']['players'] ?? [], 'id'); $xi = [];
                for ($i = 0; $i < 11; $i++) { $id = (string)($xiIn[$i] ?? ''); $xi[] = (in_array($id, $ids, true) && !in_array($id, $xi, true)) ? $id : ''; }
                $subs = array_values(array_diff(array_intersect($ids, $subIn), $xi));
                $d['lineup'] = ['formation' => $f, 'xi' => $xi, 'subs' => $subs, 'published' => $pub, 'mid' => match_id($d['match'] ?? []), 'ads' => ['active' => $adon, 'l' => $adl, 'r' => $adr]]; return $d;
            }; break;
        case 'seo_save':
            $tab = 't18'; $og = up('seo_og_f', 'og_'); if (!empty($GLOBALS['ue'])) flash('تعذر رفع الصورة: ' . $GLOBALS['ue'], $tab);
            $bu = $P('seo_base'); if ($bu !== '' && !preg_match('~^https?://[a-z0-9.-]+(:\d+)?(/[\w./-]*)?$~i', $bu)) flash('رابط الموقع الأساسي غير صالح (مثال: https://example.com).', $tab);
            $ogu = $P('seo_ogu'); $ogv = $og ?: ((preg_match('~^https?://[^\s"<>]+$~i', $ogu) || preg_match('~^WSSE_files/[\w./-]+$~', $ogu)) ? $ogu : '');
            $cl2 = fn($k, $n) => mb_substr(preg_replace('/[^A-Za-z0-9_\-.]/', '', $P($k)), 0, $n);
            $v = ['seo' => ['base_url' => rtrim($bu, '/'), 'title' => mb_substr($P('seo_title'), 0, 70), 'desc' => mb_substr($P('seo_desc'), 0, 300), 'keywords' => mb_substr($P('seo_kw'), 0, 300), 'og_image' => $ogv, 'twitter' => $cl2('seo_tw', 15), 'gverify' => $cl2('seo_gv', 100), 'bverify' => $cl2('seo_bv', 100), 'org_alt' => mb_substr($P('seo_alt'), 0, 200), 'country' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $P('seo_cc')) ?: 'MA', 0, 2)), 'noindex' => isset($_POST['seo_noindex']), 'hide_uc' => isset($_POST['seo_hideuc'])]];
            $fn = fn($d) => array_merge($d, $v); break;
        case 'slides_cfg':
            $tab = 't3'; $v = ['slides_active' => isset($_POST['sl_on']), 'slide_sec' => max(2, min(30, (int)($_POST['sl_sec'] ?? 5))), 'slide_v' => 2, 'slide_ratio' => in_array($_POST['sl_ratio'] ?? '', ['compact', 'leader', 'wide', 'square'], true) ? $_POST['sl_ratio'] : 'compact'];
            $fn = function ($d) use ($v) { $d['banners'] = array_merge($d['banners'] ?? [], $v); return $d; }; break;
        case 'slide_add':
            $tab = 't3'; $im = up('sl_file', 'bn_', 6291456); $iu = $P('sl_img');
            if (!$im && !empty($GLOBALS['ue'])) flash('تعذر رفع الصورة: ' . $GLOBALS['ue'], $tab);
            if (!$im) $im = (preg_match('~^https?://[^\s"<>]+$~i', $iu) || preg_match('~^WSSE_files/[\w./-]+$~', $iu)) ? $iu : '';
            if (!$im) flash('ارفع صورة أو أدخل رابطها.', $tab);
            $sk = lnk($P('sl_url')); $sa = mb_substr($P('sl_alt'), 0, 120);
            if ($P('sl_url') !== '' && $sk === '') flash('رابط التوجيه غير صالح (https://... أو مسار داخلي مثل index.php?album=1).', $tab);
            $fn = function ($d) use ($im, $sk, $sa) { $d['banners']['slides'][] = ['img' => $im, 'url' => $sk, 'alt' => $sa]; return $d; }; break;
        case 'slide_edit':
            $tab = 't3'; $sk = lnk($P('sl_url')); $sa = mb_substr($P('sl_alt'), 0, 120);
            if ($P('sl_url') !== '' && $sk === '') flash('رابط التوجيه غير صالح.', $tab);
            $fn = function ($d) use ($I, $sk, $sa) { if (isset($d['banners']['slides'][$I])) { $d['banners']['slides'][$I]['url'] = $sk; $d['banners']['slides'][$I]['alt'] = $sa; } return $d; }; break;
        case 'slide_del':
            $tab = 't3'; $fn = function ($d) use ($I) { $u = $d['banners']['slides'][$I]['img'] ?? ''; if (strpos($u, 'WSSE_files/uploads/bn_') === 0 && is_file(__DIR__ . '/' . $u)) @unlink(__DIR__ . '/' . $u); unset($d['banners']['slides'][$I]); $d['banners']['slides'] = array_values($d['banners']['slides'] ?? []); return $d; }; break;
        case 'slide_mv':
            $tab = 't3'; $dr = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
            $fn = function ($d) use ($I, $dr) { $s = $d['banners']['slides'] ?? []; $j = $I + $dr; if (isset($s[$I], $s[$j])) { [$s[$I], $s[$j]] = [$s[$j], $s[$I]]; $d['banners']['slides'] = $s; } return $d; }; break;
        case 'del_fixture':
            $tab = 't5'; $fn = function ($d) use ($I) { unset($d['fixtures'][$I]); $d['fixtures'] = array_values($d['fixtures'] ?? []); return $d; }; break;
        case 'advance':
            $tab = in_array($_POST['tb'] ?? '', ['t5', 't9'], true) ? $_POST['tb'] : 't9'; $fn = fn($d) => fx_advance($d); break;
        case 'reminder_save':
            $tab = 't12'; $v = ['reminder' => ['on' => isset($_POST['rm_on']), 'min' => max(5, min(180, (int)($_POST['rm_min'] ?? 60)))]];
            $fn = fn($d) => array_merge($d, $v); break;
        case 'standings':
            $tab = 't17';
            $nn = fn($k, $hi = 999) => max(0, min($hi, (int)($_POST[$k] ?? 0)));
            $ff = preg_replace('/[^WDL]/', '', strtoupper(strtr($P('m_form'), ['ف' => 'W', 'ت' => 'D', 'خ' => 'L'])));
            $v = ['standings' => ['active' => isset($_POST['st_active']), 'pos' => ($_POST['st_pos'] ?? '') === 'after_hero' ? 'after_hero' : 'top', 'rank' => $P('st_rank') === '' ? '' : max(1, $nn('st_rank', 99)), 'competition' => mb_substr($P('st_comp'), 0, 80), 'mode' => ($_POST['st_mode'] ?? '') === 'manual' ? 'manual' : 'auto', 'win_pts' => $nn('st_win', 10), 'draw_pts' => $nn('st_draw', 10),
                'manual' => ['p' => $nn('m_p'), 'w' => $nn('m_w'), 'd' => $nn('m_d'), 'l' => $nn('m_l'), 'gf' => $nn('m_gf'), 'ga' => $nn('m_ga'), 'pts' => $nn('m_pts', 9999), 'form' => substr($ff, -5)]]];
            $fn = fn($d) => array_merge($d, $v); break;
        case 'stats_mark':
            $fl = st_flat(jget('stats.json')); $fl['push'] = count(jget('push.json')['subs'] ?? []); $fl['inbox'] = count(jget('messages.json'));
            jup('stats.json', function ($s) use ($fl) { $s['snap'] = ['t' => date('Y-m-d H:i'), 'v' => $fl]; return $s; }, []);
            flash('تم تعليم الإحصائيات كمقروءة.', 't12');
        case 'archive_next':
            $tab = 't6'; $fn = fn($d) => lv_archive($d, (int)($d['home_score'] ?? 0), (int)($d['away_score'] ?? 0)); break;
        case 'del_past':
            $tab = 't6'; $fn = function ($d) use ($I) { unset($d['archive'][$I]); $d['archive'] = array_values($d['archive'] ?? []); return $d; }; break;
        case 'upgrade': case 'upgrade_rollback':
            if (!rl('u' . ip(), 5, 900)) flash('محاولات كثيرة، أعد المحاولة بعد 15 دقيقة.', 't15');
            if (!chk_pw((string)($_POST['pw'] ?? ''))) flash('كلمة مرور المشرف غير صحيحة.', 't15');
            if ($a === 'upgrade_rollback') { $er = upg_rollback($P('bk')); flash($er === '' ? 'تم استرجاع النسخة بنجاح.' : $er, 't15'); }
            if (!class_exists('ZipArchive')) flash('امتداد ZipArchive غير مفعّل على الخادم.', 't15');
            $uf = $_FILES['pkg'] ?? null;
            if (!$uf || ($uf['error'] ?? 4) !== 0 || $uf['size'] > 20971520 || !is_uploaded_file($uf['tmp_name'])) flash('فشل رفع الملف (الحد 20MB وقد يمنعه إعداد upload_max_filesize).', 't15');
            [$pf, $er] = upg_read($uf['tmp_name']);
            if (!$pf) flash($er, 't15');
            $er = upg_apply($pf, mb_substr(basename((string)$uf['name']), 0, 60));
            flash($er === '' ? 'تمت الترقية بنجاح (' . count($pf) . ' ملف): ' . implode('، ', array_keys($pf)) : $er, 't15');
        case 'stats_reset':
            $w = (string)($_POST['what'] ?? 'all');
            if ($w === 'push') { jup('push.json', function ($x) { $x['subs'] = []; return $x; }, []); flash('تم حذف اشتراكات الإشعارات (العدّاد = 0).', 't12'); }
            jup('stats.json', function ($s) use ($w) {
                if ($w === 'all') return [];
                if ($w === 'visits') { unset($s['seen']); foreach ($s['days'] ?? [] as $d => $x) { if (!empty($x['k'])) $s['days'][$d] = ['k' => $x['k']]; else unset($s['days'][$d]); } }
                if ($w === 'clicks') { foreach ($s['days'] ?? [] as $d => $x) unset($s['days'][$d]['k']); }
                if ($w === 'app') unset($s['ev']);
                return $s;
            }, []);
            flash('تم تصفير الإحصائيات.', 't12');
        default: flash('طلب غير صالح.');
    }
    if ($fn) jup('data.json', $fn, []);
    if (strpos($a, 'lv_') === 0) push_later();
    flash('تم الحفظ بنجاح!', $tab);
}

$lg0 = jget('data.json')['last_match'] ?? [];
if (($lg0['home_team'] ?? '') !== '' || ($lg0['away_team'] ?? '') !== '') jup('data.json', function ($d) { $l = $d['last_match']; $k = md5(($l['date'] ?? '') . ($l['home_team'] ?? '') . ($l['away_team'] ?? '')); $has = false; foreach (($d['archive'] ?? []) as $x) if (md5(($x['date'] ?? '') . ($x['home_team'] ?? '') . ($x['away_team'] ?? '')) === $k) $has = true; if (!$has) $d['archive'][] = $l; $d['last_match'] = []; return $d; }, []);
$S = site(); $subs = jget('subscribers.json'); $preds = jget('predictions.json'); $msgs = jget('messages.json'); $mid = mid($S['match']);
$L = $S['lv']['L']; $ck = $S['lv']['c']; $st = $ck['st']; $m = $S['match'];
$fl = $_SESSION['fl'] ?? ''; unset($_SESSION['fl']);
$c = 'w-full bg-white border border-gray-300 rounded-xl p-2.5 text-xs text-gray-800';
function EF($v, $m, $c, $sv) {
    $pl = in_array($v['type'], ['goal', 'penalty', 'yellow', 'red', 'sub', 'injury'], true);
    $o = '<form id="ed_' . e($v['id']) . '" method="POST" class="hidden border-t border-gray-200 pt-2">' . H('lv_edit_ev') . '<input type="hidden" name="id" value="' . e($v['id']) . '"><div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs items-end">';
    if ($pl) {
        $o .= '<div><label class="block text-gray-600 mb-1">الفريق</label><select name="ev_team" class="' . $c . '"><option value="home"' . ($v['team'] === 'home' ? ' selected' : '') . '>' . e($m['home_team']) . '</option><option value="away"' . ($v['team'] === 'away' ? ' selected' : '') . '>' . e($m['away_team']) . '</option></select></div>';
        $o .= F('ev_p1', $v['p1'], $v['type'] === 'sub' ? 'اللاعب الداخل' : 'اللاعب', $c, 'text', '', ' list="pl" maxlength="60"');
        if ($v['type'] === 'sub') $o .= F('ev_p2', $v['p2'], 'اللاعب الخارج', $c, 'text', '', ' list="pl" maxlength="60"');
        if ($v['type'] === 'penalty') { $rs = $v['res'] ?? 'scored'; $o .= '<div><label class="block text-gray-600 mb-1">نتيجة الضربة</label><select name="ev_res" class="' . $c . '">'; foreach (['scored' => 'سُجّلت', 'missed' => 'أُضيعت', 'saved' => 'تصدّى لها الحارس'] as $k => $l) $o .= '<option value="' . $k . '"' . ($rs === $k ? ' selected' : '') . '>' . $l . '</option>'; $o .= '</select></div>'; }
    }
    $o .= F('ev_min', $v['min'], 'الدقيقة', $c, 'text', 'ltr', ' maxlength="10"') . F('ev_reason', $v['reason'] ?? '', 'السبب / ملاحظة', $c);
    return $o . '<button class="' . $sv . ' !w-auto px-4">حفظ التعديل</button></div></form>';
}
function F($n, $v, $l, $c, $t = 'text', $dir = '', $extra = '') { return '<div><label class="block text-gray-600 mb-1 text-xs">' . e($l) . '</label><input type="' . $t . '" name="' . e($n) . '" value="' . e($v) . '" class="' . $c . '"' . ($dir ? ' dir="' . $dir . '"' : '') . $extra . '></div>'; }
function B($act, $label, $i = null, $conf = '', $cls = 'bg-red-700') { return '<form method="POST" class="inline"><input type="hidden" name="t" value="' . tok() . '"><input type="hidden" name="act" value="' . $act . '">' . ($i !== null ? '<input type="hidden" name="i" value="' . e($i) . '">' : '') . '<button class="' . $cls . ' text-white text-xs px-3 py-1.5 rounded-lg cursor-pointer"' . ($conf ? ' onclick="return confirm(\'' . $conf . '\')"' : '') . '>' . $label . '</button></form>'; }
function R($act, $label, $cls, $ph) { return '<form method="POST" class="inline-flex gap-1 items-center"><input type="hidden" name="t" value="' . tok() . '"><input type="hidden" name="act" value="' . $act . '"><input type="text" name="reason" maxlength="200" placeholder="' . $ph . '" class="bg-white border border-gray-300 rounded-lg p-1.5 text-xs w-40"><button class="' . $cls . ' text-white text-xs px-3 py-1.5 rounded-lg cursor-pointer">' . $label . '</button></form>'; }
function H($act) { return '<input type="hidden" name="t" value="' . tok() . '"><input type="hidden" name="act" value="' . $act . '">'; }
$sv = 'w-full bg-red-700 hover:bg-red-600 text-white font-black py-2.5 rounded-xl cursor-pointer text-sm';
$box = 'bg-gray-50 p-5 rounded-2xl border border-gray-200 space-y-4';
$in2 = 'bg-white border border-gray-200 rounded-xl p-4';
$tabs = ['t1' => 'هوية الموقع والخلفية', 't2' => 'التواصل الاجتماعي', 't3' => 'الدعاية والإعلان', 't4' => 'البث الحي', 't5' => 'المباراة المقبلة', 't6' => 'المباريات السابقة (الأرشيف)', 't7' => 'الصفحات والفوتر', 't8' => 'المشتركون', 't9' => 'توقعات الجماهير', 't10' => 'صوت الجماهير', 't11' => 'ألبوم الصور', 't12' => 'الإحصائيات والإشعارات', 't13' => 'صندوق الوارد', 't14' => 'نسخة البيانات', 't15' => 'ترقية', 't16' => 'فحص تثبيت التطبيق', 't17' => 'جدول الترتيب', 't18' => 'SEO والأرشفة', 't19' => 'الطاقم والتشكيلة'];
$stl = ['idle' => 'لم تنطلق', 'first' => 'الشوط الأول', 'half' => 'الاستراحة', 'second' => 'الشوط الثاني', 'finished' => 'انتهت المباراة', 'cancelled' => 'ملغاة'][$st] . ($ck['pz'] ? ' (موقوفة)' : '');
$players = array_unique(array_filter(array_merge(array_column($S['motm']['candidates'], 'name'), array_column($L['events'], 'p1'), array_column($L['events'], 'p2'))));
$tn = ['home' => $m['home_team'], 'away' => $m['away_team'], '' => ''];
$unread = count(array_filter($msgs, fn($r) => empty($r['read']))); if ($unread) $tabs['t13'] .= " ($unread)";
$stm = site_team($S); $cps = array_values(array_unique(array_filter(array_merge([$S['match']['competition']], array_column($S['past'], 'competition')))));
$ST = jget('stats.json'); $psn = count(jget('push.json')['subs'] ?? []);
$pst = pred_stats($mid); $pall = !empty($_GET['pa']); $nfx = count($S['fixtures']);
$kso = strtotime(trim($m['date'] . ' ' . $m['time'])); $pcl = in_array($st, ['first', 'half', 'second', 'finished', 'cancelled'], true) || ($kso && time() >= $kso);
$sch = (!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$cu = $sch . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/api.php?a=cron&k=' . cron_key();
$rg = in_array($_GET['r'] ?? '', ['1', '7', '30', '120'], true) ? (int)$_GET['r'] : 7;
$ag = ['v' => 0, 'u' => 0, 's' => 0, 'p' => [], 'c' => [], 'd' => [], 'o' => [], 'b' => [], 'r' => [], 'k' => []]; $dv = [];
for ($i = $rg - 1; $i >= 0; $i--) {
    $dk = date('Y-m-d', strtotime("-$i day")); $x = $ST['days'][$dk] ?? []; $dv[$dk] = (int)($x['v'] ?? 0);
    foreach (['v', 'u', 's'] as $q) $ag[$q] += (int)($x[$q] ?? 0);
    foreach (['p', 'c', 'd', 'o', 'b', 'r', 'k'] as $q) foreach (($x[$q] ?? []) as $kk => $vv) $ag[$q][$kk] = ($ag[$q][$kk] ?? 0) + $vv;
}
$sn = $ST['snap']['v'] ?? null; $cf = st_flat($ST); $cf['push'] = $psn; $cf['inbox'] = count($msgs);
$dl = function ($key) use ($sn, $cf) { if ($sn === null) return ''; $d = ($cf[$key] ?? 0) - ($sn[$key] ?? 0); return $d > 0 ? ' <span class="text-emerald-700 font-black text-[10px] bg-emerald-50 border border-emerald-300 rounded-full px-1.5 align-middle">+' . $d . '</span>' : ''; };
$dvl = ['mobile' => 'هاتف', 'tablet' => 'لوحي', 'desktop' => 'حاسوب'];
$cl = ['contact' => 'رابط «راسلنا»', 'advertise' => 'رابط «أعلن معنا»', 'back_home' => 'العودة للرئيسية', 'ad_mail' => 'بريد المعلنين', 'fanvote' => 'صفحة صوت الجماهير', 'album' => 'ألبوم: عرض المزيد', 'mp_page' => 'صفحة توقعات المباراة', 'banner_h' => 'البانر الأفقي', 'banner_v' => 'البانر العمودي', 'soc_facebook' => 'فيسبوك', 'soc_twitter' => 'تويتر / X', 'soc_instagram' => 'انستغرام', 'soc_youtube' => 'يوتيوب'];
function cn($c) { $ap = substr($c, -1) === '*'; $k = rtrim($c, '*'); if ($k === '--') return 'غير معروف'; $n = class_exists('Locale') ? Locale::getDisplayRegion('ar_' . $k, 'ar') : $k; return ($n && $n !== $k ? $n : $k) . ' (' . $k . ')' . ($ap ? ' ≈' : ''); }
function bars($arr, $lab = null, $top = 10, $dlt = null) { arsort($arr); $arr = array_slice($arr, 0, $top, true); $mx = max($arr ?: [1]) ?: 1; $o = ''; foreach ($arr as $k => $v) { $dh = $dlt ? $dlt($k) : ''; $o .= '<div class="text-xs' . ($dh ? ' bg-emerald-50 rounded-lg px-1.5 py-1' : '') . '"><div class="flex justify-between"><span>' . e($lab ? $lab($k) : $k) . '</span><b>' . (int)$v . $dh . '</b></div><div class="h-1.5 bg-gray-200 rounded-full"><div class="h-full bg-red-600 rounded-full" style="width:' . round($v * 100 / $mx) . '%"></div></div></div>'; } return $o ?: '<p class="text-xs text-gray-400">لا توجد بيانات بعد.</p>'; }
shell('لوحة التحكم الشاملة'); ?>
<body class="bg-white text-gray-800 min-h-screen py-4 px-3">
<div class="max-w-6xl mx-auto space-y-4">
<div class="flex flex-wrap items-center justify-between gap-3 bg-red-800 text-white p-4 rounded-2xl shadow">
<h1 class="text-lg font-black">لوحة التحكم</h1>
<div class="flex gap-2 text-xs"><a href="index.php" target="_blank" class="bg-white text-red-800 font-bold px-4 py-2 rounded-xl">معاينة الموقع</a><a href="?logout=<?= tok() ?>" class="bg-black/30 px-4 py-2 rounded-xl">خروج</a></div>
</div>
<?php if ($fl): ?><div class="bg-emerald-600 text-white p-3 rounded-xl text-center font-bold text-sm"><?= e($fl) ?></div><?php endif; ?>
<?php if (empty($cfg['hash'])): ?><div class="bg-red-600 text-white p-3 rounded-xl text-center font-bold text-sm">تنبيه أمني: كلمة المرور الافتراضية ما زالت مستخدمة. غيّرها من تبويب «هوية الموقع والخلفية».</div><?php endif; ?>
<div class="flex flex-col md:flex-row gap-4 items-start">
<aside class="w-full md:w-60 md:shrink-0"><nav class="md:sticky md:top-4 flex md:flex-col gap-1 overflow-x-auto md:overflow-visible bg-gray-50 border border-gray-200 rounded-2xl p-2 text-sm whitespace-nowrap">
<?php foreach ($tabs as $k => $l): ?><button type="button" data-t="<?= $k ?>" class="tb text-right px-3 py-2.5 rounded-xl cursor-pointer font-semibold"><?= $l ?></button><?php endforeach; ?>
</nav></aside>
<main class="flex-1 min-w-0 w-full space-y-4">

<section id="t1" class="tab <?= $box ?>">
<h2 class="font-bold text-red-800">هوية الموقع: العنوان - الشعار - الخلفية - الحالة</h2>
<form method="POST" enctype="multipart/form-data" class="flex items-center gap-4 <?= $in2 ?>"><?= H('logo') ?>
<img src="<?= e(img($S['logo'])) ?>" alt="" class="h-16 w-16 object-contain bg-gray-100 rounded-lg p-1">
<div class="flex-1"><label class="block text-xs text-gray-600 mb-1">اختر شعاراً جديداً (يُرفع تلقائياً)</label><input type="file" name="logo_file" accept="image/*" onchange="this.form.submit()" class="w-full text-xs text-gray-600 file:ml-4 file:py-2 file:px-4 file:rounded-xl file:bg-red-700 file:text-white cursor-pointer"></div></form>
<form method="POST" enctype="multipart/form-data" class="space-y-3"><?= H('identity') ?>
<div class="grid md:grid-cols-2 gap-3"><?= F('site_title', $S['site_title'], 'عنوان المتصفح', $c) . F('main_heading', $S['main_heading'], 'العنوان الرئيسي', $c) ?></div>
<div><label class="block text-xs text-gray-600 mb-1">نص حالة الموقع (تحت الإنشاء)</label><textarea name="sub_message" rows="2" class="<?= $c ?>"><?= e($S['sub_message']) ?></textarea></div>
<div class="<?= $in2 ?> space-y-3"><span class="font-bold text-xs text-red-800">خلفية الصفحة الأولى</span>
<?php if (trim($S['bg_image']) !== ''): ?><img src="<?= e(img($S['bg_image'])) ?>" alt="" class="h-24 w-full object-cover rounded-xl border"><?php endif; ?>
<div class="grid md:grid-cols-2 gap-3"><div><label class="block text-gray-600 mb-1 text-xs">رفع صورة خلفية (حتى 8MB)</label><input type="file" name="bg_file" accept="image/*" class="w-full text-xs text-gray-600"></div><?= F('bg_url', '', 'أو رابط صورة', $c, 'url', 'ltr') ?></div>
<div class="flex flex-wrap items-center gap-6 text-xs"><label class="flex items-center gap-2">لون الخلفية <input type="color" name="bg_color" value="<?= e($S['bg_color']) ?>" class="h-8 w-12 border rounded"></label><label class="cursor-pointer"><input type="checkbox" name="bg_none"> إزالة الصورة (لون فقط)</label></div></div>
<div class="<?= $in2 ?> space-y-3"><span class="font-bold text-xs text-red-800">فريق الموقع (يُستخدم تلقائياً عند إضافة المباريات وفي جدول الترتيب والنتائج)</span>
<?php if (trim($S['team']['logo']) !== ''): ?><img src="<?= e(img($S['team']['logo'])) ?>" alt="" class="h-14 w-14 object-contain bg-gray-100 rounded-lg p-1"><?php endif; ?>
<div class="grid md:grid-cols-2 gap-3"><?= F('team_name', $S['team']['name'], 'اسم الفريق (كما سيُكتب في المباريات)', $c) . F('team_logo', $S['team']['logo'], 'شعار الفريق (رابط) — أو ارفع ملفاً', $c, 'text', 'ltr') ?><div><label class="block text-gray-600 mb-1 text-xs">رفع شعار الفريق</label><input type="file" name="team_logo_f" accept="image/*" class="w-full text-xs text-gray-600"></div></div></div>
<?= F('new_pass', '', 'كلمة مرور جديدة للمشرف (اتركها فارغة بدون تغيير، 8 أحرف على الأقل)', $c, 'password') ?>
<button class="<?= $sv ?>">حفظ</button></form>
</section>

<section id="t2" class="tab hidden <?= $box ?>"><form method="POST" class="space-y-3"><?= H('social') ?>
<div class="flex items-center justify-between"><h2 class="font-bold text-red-800">روابط التواصل الاجتماعي (اختيارية)</h2><label class="flex items-center gap-2 text-xs cursor-pointer"><input type="checkbox" name="social_active" <?= $S['social']['active'] ? 'checked' : '' ?>> تفعيل بالفوتر</label></div>
<div class="grid md:grid-cols-2 gap-3"><?= F('soc_fb', $S['social']['facebook'], 'فيسبوك', $c, 'url', 'ltr') . F('soc_tw', $S['social']['twitter'], 'تويتر / X', $c, 'url', 'ltr') . F('soc_ig', $S['social']['instagram'], 'انستغرام', $c, 'url', 'ltr') . F('soc_yt', $S['social']['youtube'], 'يوتيوب', $c, 'url', 'ltr') ?></div>
<button class="<?= $sv ?>">حفظ</button></form></section>

<section id="t3" class="tab hidden <?= $box ?>"><form method="POST" class="space-y-3"><?= H('banners') ?>
<h2 class="font-bold text-red-800">نظام الدعاية والإعلان</h2>
<?php foreach (['bh' => ['البانر الأفقي (أعلى الصفحة)', 'horizontal'], 'bv' => ['البانر العمودي (جانب الصفحة، عند تعطيله يتوسط المحتوى)', 'vertical']] as $k => [$l, $n]): ?>
<div class="<?= $in2 ?> space-y-2"><div class="flex justify-between text-xs"><span class="font-bold"><?= $l ?></span><label class="cursor-pointer"><input type="checkbox" name="<?= $k ?>" <?= $S['banners'][$n . '_active'] ? 'checked' : '' ?>> تفعيل</label></div>
<textarea name="<?= $k ?>_code" rows="3" dir="ltr" placeholder="كود HTML / AdSense" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs font-mono"><?= e($S['banners'][$n . '_code']) ?></textarea></div>
<?php endforeach; ?>
<div class="<?= $in2 ?> space-y-3"><span class="font-bold text-xs text-red-800">مولّد كود البانر (صورة + رابط)</span>
<div class="grid md:grid-cols-2 gap-3 text-xs">
<div><label class="block text-gray-600 mb-1">رابط صورة البانر (https://... أو WSSE_files/uploads/...)</label><input id="bg_img" dir="ltr" class="<?= $c ?>"></div>
<div><label class="block text-gray-600 mb-1">رابط الوجهة عند النقر</label><input id="bg_url" dir="ltr" class="<?= $c ?>"></div>
<div><label class="block text-gray-600 mb-1">النص البديل للصورة</label><input id="bg_alt" class="<?= $c ?>"></div>
<div><label class="block text-gray-600 mb-1">أقصى عرض</label><select id="bg_sz" class="<?= $c ?>"><option value="">عرض كامل (متجاوب)</option><option value="970">970px</option><option value="728">728px</option><option value="468">468px</option><option value="300">300px</option></select></div></div>
<textarea id="bg_out" rows="3" readonly dir="ltr" placeholder="يظهر الكود المولّد هنا" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-2 text-xs font-mono"></textarea>
<div class="flex flex-wrap gap-2 text-xs"><button type="button" onclick="bgen()" class="bg-sky-700 text-white px-3 py-1.5 rounded-lg cursor-pointer">توليد الكود</button><button type="button" onclick="bput('bh_code')" class="bg-gray-800 text-white px-3 py-1.5 rounded-lg cursor-pointer">وضعه في البانر الأفقي</button><button type="button" onclick="bput('bv_code')" class="bg-gray-800 text-white px-3 py-1.5 rounded-lg cursor-pointer">وضعه في البانر العمودي</button><button type="button" onclick="bgen();navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('bg_out').value)" class="bg-gray-500 text-white px-3 py-1.5 rounded-lg cursor-pointer">نسخ</button></div></div>
<button class="<?= $sv ?>">حفظ</button></form><?php $bn = $S['banners']; $sls = array_values(array_filter((array)$bn['slides'], 'is_array')); ?>
<div class="<?= $in2 ?> space-y-3"><h3 class="font-bold text-sm text-red-800">سلايدشو البانر الأفقي (عدة مواد إعلانية بالتناوب)</h3>
<form method="POST" class="flex flex-wrap items-end gap-3 text-xs"><?= H('slides_cfg') ?>
<label class="cursor-pointer"><input type="checkbox" name="sl_on" <?= !empty($bn['slides_active']) ? 'checked' : '' ?>> تفعيل السلايدشو (يحلّ محل كود البانر الأفقي)</label>
<div><label class="block text-gray-600 mb-1">مدة كل إعلان (ثانية)</label><input type="number" name="sl_sec" min="2" max="30" value="<?= (int)$bn['slide_sec'] ?>" class="<?= $c ?> !w-24"></div>
<div><label class="block text-gray-600 mb-1">نسبة العرض</label><select name="sl_ratio" class="<?= $c ?> !w-auto"><?php foreach (['compact' => 'مصغّر بعرض صندوق المباراة (الأنسب)', 'leader' => 'شريطي 728×90', 'wide' => 'عريض 970×250', 'square' => 'سينمائي 16:9'] as $k => $l): ?><option value="<?= $k ?>" <?= (((int)($bn['slide_v'] ?? 0) >= 2) ? ($bn['slide_ratio'] ?? 'compact') : 'compact') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
<button class="bg-red-700 text-white px-4 py-2 rounded-lg cursor-pointer">حفظ الإعدادات</button></form>
<?php foreach ($sls as $k => $x): ?>
<div class="bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2"><div class="flex items-center gap-3"><img src="<?= e(img($x['img'])) ?>" alt="" class="h-14 w-28 object-cover rounded-lg border bg-gray-100"><b class="text-xs text-red-700">إعلان #<?= $k + 1 ?></b>
<div class="flex gap-1 mr-auto"><?php foreach (['up' => '↑', 'down' => '↓'] as $dr => $ar): ?><form method="POST" class="inline"><?= H('slide_mv') ?><input type="hidden" name="i" value="<?= $k ?>"><input type="hidden" name="dir" value="<?= $dr ?>"><button class="bg-gray-200 text-gray-700 text-xs px-2.5 py-1.5 rounded-lg cursor-pointer"><?= $ar ?></button></form><?php endforeach; ?><?= B('slide_del', 'حذف', $k, 'حذف هذا الإعلان؟') ?></div></div>
<form method="POST" class="grid md:grid-cols-3 gap-2 items-end text-xs"><?= H('slide_edit') ?><input type="hidden" name="i" value="<?= $k ?>"><?= F('sl_url', $x['url'] ?? '', 'رابط التوجيه (خارجي https://... أو داخلي index.php?album=1)', $c, 'text', 'ltr') . F('sl_alt', $x['alt'] ?? '', 'النص البديل / الوصف', $c) ?><button class="bg-sky-700 text-white py-2.5 rounded-xl cursor-pointer">حفظ الرابط</button></form></div>
<?php endforeach; ?>
<form method="POST" enctype="multipart/form-data" class="border-t border-gray-200 pt-3 space-y-3 text-xs"><?= H('slide_add') ?><span class="font-bold text-red-800">إضافة مادة إعلانية</span>
<div class="grid md:grid-cols-2 gap-3"><div><label class="block text-gray-600 mb-1">رفع صورة (JPG/PNG/WEBP حتى 6MB)</label><input type="file" name="sl_file" accept="image/*" class="w-full text-xs text-gray-600"></div><?= F('sl_img', '', 'أو رابط الصورة', $c, 'text', 'ltr') . F('sl_url', '', 'رابط التوجيه عند النقر (اختياري)', $c, 'text', 'ltr') . F('sl_alt', '', 'النص البديل / الوصف', $c) ?></div>
<button class="<?= $sv ?>">إضافة للسلايدشو</button></form></div>
</section>

<section id="t4" class="tab hidden <?= $box ?>">
<h2 class="font-bold text-red-800">البث الحي والنتيجة المباشرة</h2>
<div class="<?= $in2 ?> text-center space-y-2">
<div class="flex items-center justify-center gap-3 text-sm font-bold"><span><?= e($m['home_team']) ?></span><span class="text-3xl font-black bg-gray-100 px-4 py-1 rounded-xl"><?= (int)$S['home_score'] ?> - <?= (int)$S['away_score'] ?></span><span><?= e($m['away_team']) ?></span></div>
<div class="text-xs"><span class="bg-red-100 text-red-800 font-bold px-3 py-1 rounded-full"><?= $stl ?></span>
<?php if ($ck['run']): ?> <span class="font-black text-lg text-red-700" id="aclk"><?= e($ck['clk']) ?></span><?php endif; ?></div></div>
<div class="flex flex-wrap gap-2 items-center">
<?php if (in_array($st, ['idle', 'finished', 'cancelled'], true)) echo B('lv_kickoff', '⚽ انطلاقة المباراة', null, $st === 'idle' ? '' : 'بدء مباراة جديدة وحذف أحداث السابقة؟', 'bg-emerald-600');
if ($st === 'first') echo B('lv_half', 'إنهاء الشوط الأول الآن', null, '', 'bg-sky-700');
if ($st === 'half') echo B('lv_second', 'بدء الشوط الثاني', null, '', 'bg-emerald-600');
if (in_array($st, ['first', 'second'], true) && !$ck['pz']) echo R('lv_pause', 'توقيف المباراة', 'bg-amber-600', 'سبب التوقيف');
if ($ck['pz']) echo B('lv_resume', 'استئناف المباراة', null, '', 'bg-emerald-600');
if (in_array($st, ['first', 'half', 'second'], true)) echo B('lv_finish', 'إنهاء المباراة', null, 'تأكيد نهاية المباراة؟', 'bg-gray-800') . R('lv_cancel', 'إلغاء المباراة', 'bg-red-700', 'سبب الإلغاء');
if ($st !== 'idle') echo B('lv_reset', 'تهيئة (حذف الأحداث)', null, 'حذف كل أحداث المباراة؟', 'bg-gray-500'); ?>
</div>
<p class="text-xs text-gray-500">العداد يستمر تلقائياً بعد 45' و90' بصيغة 45+N' / 90+N' ولا يتوقف إلا بإنهاء الشوط أو المباراة يدوياً.</p>
<form method="POST" class="<?= $in2 ?> grid grid-cols-2 md:grid-cols-4 gap-3 items-end"><?= H('lv_stop') ?>
<?= F('half', $L['half'], 'مدة الشوط (دقيقة)', $c, 'number') . F('stop1', $L['stop1'], 'بدل ضائع الشوط الأول', $c, 'number') . F('stop2', $L['stop2'], 'بدل ضائع الشوط الثاني', $c, 'number') ?>
<button class="bg-sky-700 text-white text-xs py-2.5 rounded-xl cursor-pointer">تحديث</button></form>
<?php if ($st !== 'idle'): ?>
<form method="POST" class="<?= $in2 ?> space-y-3"><?= H('lv_event') ?><span class="font-bold text-xs text-red-800">إضافة حدث</span>
<datalist id="pl"><?php foreach ($players as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
<div><label class="block text-gray-600 mb-1">الحدث</label><select name="ev_type" onchange="evf(this.value)" class="<?= $c ?>"><?php foreach (['goal' => 'هدف', 'penalty' => 'ضربة جزاء', 'goal_cancel' => 'إلغاء هدف', 'yellow' => 'بطاقة صفراء', 'red' => 'بطاقة حمراء', 'sub' => 'تبديل (دخول / خروج)', 'injury' => 'إصابة (خروج)'] as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
<div id="f_team"><label class="block text-gray-600 mb-1">الفريق</label><select name="ev_team" class="<?= $c ?>"><option value="home"><?= e($m['home_team']) ?></option><option value="away"><?= e($m['away_team']) ?></option></select></div>
<div id="f_res" class="hidden"><label class="block text-gray-600 mb-1">نتيجة الضربة</label><select name="ev_res" class="<?= $c ?>"><option value="scored">سُجّلت</option><option value="missed">أُضيعت</option><option value="saved">تصدّى لها الحارس</option></select></div>
<div id="f_ref" class="hidden"><label class="block text-gray-600 mb-1">الهدف الملغى</label><select name="ev_ref" class="<?= $c ?>"><?php foreach ($L['events'] as $v) if (empty($v['void']) && ($v['type'] === 'goal' || ($v['type'] === 'penalty' && ($v['res'] ?? '') === 'scored'))): ?><option value="<?= e($v['id']) ?>"><?= e($v['min'] . ' ' . $tn[$v['team']] . ' ' . $v['p1']) ?></option><?php endif; ?></select></div>
<div><label class="block text-gray-600 mb-1" id="l_p1">اللاعب</label><input name="ev_p1" list="pl" maxlength="60" class="<?= $c ?>"></div>
<div id="f_p2" class="hidden"><label class="block text-gray-600 mb-1">اللاعب الخارج</label><input name="ev_p2" list="pl" maxlength="60" class="<?= $c ?>"></div>
<div><label class="block text-gray-600 mb-1">الدقيقة (اختياري، تلقائي)</label><input name="ev_min" maxlength="10" placeholder="مثال: 45+2'" class="<?= $c ?>" dir="ltr"></div>
</div>
<?= F('ev_reason', '', 'السبب / ملاحظة (مفتوح)', $c) ?>
<button class="<?= $sv ?>">تسجيل الحدث</button></form>
<?php endif; ?>
<div class="space-y-2 text-xs"><?php foreach (array_reverse($L['events']) as $v): [$tt, $dt, $rs] = ev_txt($v); $vd = !empty($v['void']); ?>
<div class="<?= $in2 ?> !p-2.5 space-y-2"><div class="flex items-center justify-between gap-2"><div class="flex items-center gap-2 <?= $vd ? 'line-through opacity-60' : '' ?>"><b class="text-red-700 w-12"><?= e($v['min']) ?></b><?= ev_ico($vd ? 'goal_cancel' : $v['type'], 22) ?><span><b><?= e($tt) ?></b> <?= $v['team'] ? '(' . e($tn[$v['team']]) . ')' : '' ?> <?= e($dt) ?> <?= $rs !== '' ? '<i class="text-gray-500">— ' . e($rs) . '</i>' : '' ?></span></div><div class="flex gap-1 shrink-0"><button type="button" onclick="document.getElementById('ed_<?= e($v['id']) ?>').classList.toggle('hidden')" class="bg-sky-700 text-white text-xs px-3 py-1.5 rounded-lg cursor-pointer">تعديل</button><?= B('lv_del_ev', 'حذف', $v['id'], 'حذف هذا الحدث؟') ?></div></div><?= EF($v, $m, $c, $sv) ?></div>
<?php endforeach; ?></div>
</section>

<?php $mf = function ($g, $fields, $vals) use ($c, $ty) { foreach ($fields as $k => $l) { echo F($g . '[' . $k . ']', $vals[$k] ?? '', $l, $c, $ty[$k] ?? 'text', substr($k, -4) === 'logo' ? 'ltr' : '', $k === 'competition' ? ' list="cmp"' : ''); if (substr($k, -4) === 'logo') echo '<div><label class="block text-gray-600 mb-1 text-xs">أو ارفع ' . e($l) . '</label><input type="file" name="' . $g . '_' . $k . '_f" accept="image/*" class="w-full text-xs text-gray-600"></div>'; } }; ?>
<datalist id="cmp"><?php foreach ($cps as $cpv): ?><option value="<?= e($cpv) ?>"><?php endforeach; ?></datalist>
<section id="t5" class="tab hidden <?= $box ?>"><form method="POST" enctype="multipart/form-data" class="space-y-3"><?= H('next_match') ?>
<h2 class="font-bold text-red-800">تفاصيل المباراة المقبلة (الشعارات والترتيب والطقس)</h2>
<div class="grid md:grid-cols-2 gap-3"><div><label class="block text-gray-600 mb-1 text-xs">فريقنا (<?= e($stm['name'] ?: 'غير مسجّل') ?>) يلعب</label><select onchange="setSide(this,'m')" class="<?= $c ?>"><option value="">—</option><option value="home">مستضيفاً</option><option value="away">ضيفاً</option></select></div><?php $mf('m', $M, $S['match']); ?></div>
<button class="<?= $sv ?>">حفظ</button></form><?php $fxs = (array)$S['fixtures']; uasort($fxs, fn($a1, $b1) => strcmp(($a1['date'] ?? '') . ($a1['time'] ?? ''), ($b1['date'] ?? '') . ($b1['time'] ?? ''))); ?>
<div class="<?= $in2 ?> space-y-3"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold text-sm text-red-800">جدول المباريات القادمة (<?= $nfx ?>)</h3>
<?php if ($nfx): ?><form method="POST" class="inline"><?= H('advance') ?><input type="hidden" name="tb" value="t5"><button class="bg-sky-700 text-white text-xs px-3 py-1.5 rounded-lg cursor-pointer" onclick="return confirm('الانتقال إلى أقرب مباراة في الجدول وتهيئة البث؟')">الانتقال للمباراة التالية الآن</button></form><?php endif; ?></div>
<p class="text-xs text-gray-500 leading-5">عند «إنهاء المباراة» (أو إلغائها) ينتقل الموقع تلقائياً بعد 30 دقيقة إلى أقرب مباراة في هذا الجدول فيُفتح باب التوقعات لها. نتيجة المباراة المنتهية تُؤرشف وتُحتسب في الترتيب فور الانتهاء.</p>
<?php foreach ($fxs as $k => $x): ?><div class="bg-gray-50 border border-gray-200 rounded-xl p-2.5 flex flex-wrap items-center justify-between gap-2 text-xs"><span><b><?= e(($x['home_team'] ?? '') . ' × ' . ($x['away_team'] ?? '')) ?></b> • <?= e(($x['date'] ?? '') . ' ' . ($x['time'] ?? '')) ?> • <?= e($x['competition'] ?? '') ?></span><?= B('del_fixture', 'حذف', $k, 'حذف المباراة من الجدول؟') ?></div><?php endforeach; ?>
<details><summary class="font-bold text-xs cursor-pointer">+ إضافة مباراة قادمة</summary><form method="POST" enctype="multipart/form-data" class="space-y-3 mt-3"><?= H('add_fixture') ?><div class="grid md:grid-cols-2 gap-3"><div><label class="block text-gray-600 mb-1 text-xs">فريقنا (<?= e($stm['name'] ?: 'غير مسجّل') ?>) يلعب</label><select onchange="setSide(this,'fx')" class="<?= $c ?>"><option value="">—</option><option value="home">مستضيفاً</option><option value="away">ضيفاً</option></select></div><?php $mf('fx', $M, ['home_team' => $stm['name'], 'home_logo' => $stm['logo'], 'time' => '20:00']); ?></div><button class="<?= $sv ?>">إضافة للجدول</button></form></details></div>
</section>

<section id="t6" class="tab hidden <?= $box ?>">
<h2 class="font-bold text-red-800">أرشيف المباريات السابقة (<?= count($S['archive']) ?>)</h2>
<p class="text-xs text-gray-500">الترقيم تلقائي حسب التاريخ (الأقدم = 1). تظهر أحدث مباراة في الصفحة الرئيسية مع ترقيم للتنقل بين الباقي وصفحة أرشيف كاملة.</p>
<div class="flex justify-end"><?= B('archive_next', 'أرشفة المباراة المقبلة بنتيجة البث الحالية', null, 'نسخ المباراة المقبلة إلى الأرشيف بالنتيجة الحالية؟', 'bg-sky-700') ?></div>
<details class="<?= $in2 ?>"><summary class="font-bold text-sm cursor-pointer">+ إضافة مباراة سابقة</summary><form method="POST" enctype="multipart/form-data" class="space-y-3 mt-3"><?= H('add_past') ?><div class="grid md:grid-cols-2 gap-3"><div><label class="block text-gray-600 mb-1 text-xs">فريقنا (<?= e($stm['name'] ?: 'غير مسجّل') ?>) يلعب</label><select onchange="setSide(this,'lm')" class="<?= $c ?>"><option value="">—</option><option value="home">مستضيفاً</option><option value="away">ضيفاً</option></select></div><?php $mf('lm', $LM, ['home_team' => $stm['name'], 'home_logo' => $stm['logo']]); ?></div><button class="<?= $sv ?>">إضافة للأرشيف</button></form></details>
<?php $ord = $S['archive']; uasort($ord, fn($a, $b) => strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''))); $rk = []; $rn = 0; foreach ($ord as $ak => $x) $rk[$ak] = ++$rn;
foreach (array_reverse($ord, true) as $ak => $x): ?>
<details class="<?= $in2 ?>"><summary class="cursor-pointer text-xs font-bold">#<?= $rk[$ak] ?> • <?= e($x['date'] ?? '') ?> • <?= e(($x['home_team'] ?? '') . ' ' . (int)($x['home_score'] ?? 0) . ' - ' . (int)($x['away_score'] ?? 0) . ' ' . ($x['away_team'] ?? '')) ?></summary>
<form method="POST" enctype="multipart/form-data" class="space-y-3 mt-3"><?= H('edit_past') ?><input type="hidden" name="i" value="<?= $ak ?>"><div class="grid md:grid-cols-2 gap-3"><?php $mf('lm', $LM, $x); ?></div><button class="<?= $sv ?>">حفظ التعديل</button></form>
<div class="mt-2"><?= B('del_past', 'حذف المباراة', $ak, 'حذف هذه المباراة من الأرشيف؟') ?></div></details>
<?php endforeach; ?>
</section>

<section id="t7" class="tab hidden <?= $box ?>"><form method="POST" class="space-y-3"><?= H('footer') ?>
<h2 class="font-bold text-red-800">صفحات راسلنا وأعلن معنا والفوتر</h2>
<div class="flex gap-6 text-xs"><label class="cursor-pointer"><input type="checkbox" name="show_contact" <?= $S['footer']['show_contact'] ? 'checked' : '' ?>> إظهار «راسلنا»</label><label class="cursor-pointer"><input type="checkbox" name="show_advertise" <?= $S['footer']['show_advertise'] ? 'checked' : '' ?>> إظهار «أعلن معنا»</label></div>
<?= F('copyright', $S['footer']['copyright'], 'نص الحقوق ({year} = السنة الحالية)', $c) . F('f_email', $S['footer']['email'], 'البريد المستقبل للرسائل والإعلانات', $c, 'email', 'ltr') ?>
<div><label class="block text-xs text-gray-600 mb-1">مقدمة صفحة راسلنا</label><textarea name="contact_intro" rows="2" class="<?= $c ?>"><?= e($S['footer']['contact_intro']) ?></textarea></div>
<div><label class="block text-xs text-gray-600 mb-1">نص صفحة أعلن معنا</label><textarea name="advertise_text" rows="3" class="<?= $c ?>"><?= e($S['footer']['advertise_text']) ?></textarea></div>
<button class="<?= $sv ?>">حفظ</button></form>
</section>

<section id="t8" class="tab hidden <?= $box ?>">
<div class="flex justify-between items-center"><h2 class="font-bold text-red-800">المشتركون بالبريد (<?= count($subs) ?>)</h2><?= $subs ? B('clear_subs', 'حذف الكل', null, 'حذف جميع المشتركين؟') : '' ?></div>
<div class="max-h-48 overflow-y-auto space-y-1 text-xs"><?php foreach ($subs as $k => $s): ?><div class="<?= $in2 ?> !p-2 flex justify-between items-center"><span dir="ltr"><?= e($s) ?></span><?= B('del_sub', 'حذف', $k) ?></div><?php endforeach; ?></div>
<form method="POST" class="space-y-3 border-t border-gray-200 pt-3"><?= H('mail') ?><h3 class="font-bold text-sm">مراسلة جميع المشتركين</h3><?= F('subject', '', 'العنوان', $c) ?><textarea name="body" rows="5" required placeholder="نص الرسالة" class="<?= $c ?>"></textarea><button class="<?= $sv ?>" onclick="return confirm('إرسال الرسالة لجميع المشتركين؟')">إرسال</button></form>
</section>

<section id="t9" class="tab hidden <?= $box ?>">
<form method="POST" class="flex flex-wrap justify-between items-center gap-2"><?= H('preds_show') ?><h2 class="font-bold text-red-800">توقعات الجماهير (<?= count($preds) ?>)</h2>
<label class="flex items-center gap-2 text-xs bg-emerald-50 text-emerald-900 px-3 py-1.5 rounded-lg border border-emerald-300 cursor-pointer"><input type="checkbox" name="show_correct" <?= $S['show_correct_predictions'] ? 'checked' : '' ?>> عرض أصحاب التوقع الصحيح في الصفحة الأولى بعد انتهاء المباراة</label><button class="bg-emerald-700 text-white text-xs px-3 py-1.5 rounded cursor-pointer">حفظ</button></form>
<div class="<?= $in2 ?> space-y-3">
<div class="flex flex-wrap items-center justify-between gap-2"><h3 class="font-bold text-sm text-red-800">توقعات المباراة المقبلة: <?= e($m['home_team'] . ' × ' . $m['away_team']) ?> <span class="text-[10px] bg-gray-100 rounded px-1.5 font-mono" dir="ltr">#<?= e(strtoupper(substr($mid, 0, 6))) ?></span></h3><span class="text-xs">باب التوقعات: <b class="<?= $pcl ? 'text-red-700' : 'text-emerald-700' ?>"><?= $pcl ? 'مغلق' : 'مفتوح' ?></b></span></div>
<?php $pn = max(1, $pst['n']); ?>
<div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-center text-xs"><?php foreach ([['عدد التوقعات', $pst['n']], [$m['home_team'] . ' يفوز', round($pst['h'] * 100 / $pn) . '%'], ['تعادل', round($pst['d'] * 100 / $pn) . '%'], [$m['away_team'] . ' يفوز', round($pst['a'] * 100 / $pn) . '%']] as [$q1, $q2]): ?><div class="bg-gray-50 border border-gray-200 rounded-xl p-2.5"><div class="text-lg font-black text-red-700"><?= e($q2) ?></div><div class="text-[11px] text-gray-600"><?= e($q1) ?></div></div><?php endforeach; ?></div>
<?php if ($pst['n']): ?><div class="text-xs text-gray-700 flex flex-wrap gap-2 items-center">الأكثر توقعاً: <?php foreach (array_slice($pst['sc'], 0, 5, true) as $k => $v): ?><span class="bg-red-50 text-red-800 rounded-full px-2.5 py-0.5 font-bold" dir="ltr"><?= e($k) ?> × <?= (int)$v ?></span><?php endforeach; ?><span class="mr-auto">المتوسط: <b dir="ltr"><?= round($pst['ah'] / $pn, 1) ?> - <?= round($pst['aa'] / $pn, 1) ?></b></span></div><?php endif; ?>
<div class="flex flex-wrap gap-2 text-xs items-center"><a href="index.php?matchpredictions=1" target="_blank" class="bg-white border border-gray-300 px-3 py-1.5 rounded-lg">الصفحة العامة للتوقعات</a><a href="?<?= $pall ? '' : 'pa=1' ?>#t9" class="bg-white border border-gray-300 px-3 py-1.5 rounded-lg"><?= $pall ? 'عرض توقعات هذه المباراة فقط' : 'عرض توقعات كل المباريات' ?></a>
<?= $nfx ? B('advance', 'فتح باب التوقعات للمباراة التالية', null, 'الانتقال إلى المباراة القادمة من الجدول وتهيئة البث؟', 'bg-sky-700') : ($pcl ? '<span class="text-[11px] text-amber-700">لا توجد مباراة في «جدول المباريات القادمة»؛ حدّث «المباراة المقبلة» لفتح الباب.</span>' : '') ?></div></div>
<p class="text-xs text-gray-500">تُحتسب النتيجة النهائية تلقائياً عند إنهاء المباراة من تبويب البث الحي. ✓ = توقع صحيح.</p>
<div class="flex justify-end"><?= $preds ? B('clear_preds', 'حذف الكل', null, 'حذف جميع التوقعات؟') : '' ?></div>
<div class="max-h-96 overflow-y-auto space-y-2 text-xs"><?php foreach (array_reverse($preds, true) as $k => $p): if (!$pall && ($p['mid'] ?? '') !== $mid) continue; $ok = ($p['mid'] ?? '') === $mid && $S['final_home'] !== '' && $p['home'] == $S['final_home'] && $p['away'] == $S['final_away']; ?>
<div class="<?= $in2 ?> !p-2 flex justify-between items-center gap-2"><div><b><?= e($p['name']) ?></b> <span class="text-gray-500 text-[10px]" dir="ltr"><?= e($p['contact']) ?></span></div><div class="flex items-center gap-2"><span class="font-bold text-red-700"><?= (int)$p['home'] ?> - <?= (int)$p['away'] ?></span><?= $ok ? '<span class="text-emerald-600 font-black">✓</span>' : '' ?><?= B('del_pred', 'حذف', $k) ?></div></div><?php endforeach; ?></div>
</section>

<section id="t10" class="tab hidden <?= $box ?>"><form method="POST" class="space-y-3"><?= H('motm') ?>
<div class="flex items-center justify-between"><h2 class="font-bold text-red-800">صوت الجماهير: أفضل لاعب</h2><label class="text-xs cursor-pointer"><input type="checkbox" name="motm_active" <?= $S['motm']['active'] ? 'checked' : '' ?>> تفعيل التصويت</label></div>
<?= F('motm_question', $S['motm']['question'], 'سؤال التصويت', $c) ?>
<div class="text-xs text-gray-700 font-bold">المرشحون ونتائجهم:</div>
<?php foreach ($S['motm']['candidates'] as $k => $cd): ?><div class="flex gap-2 items-center"><input type="text" name="cand[<?= $k ?>]" value="<?= e($cd['name']) ?>" maxlength="60" class="<?= $c ?>"><span class="text-red-700 text-xs font-bold whitespace-nowrap"><?= (int)$cd['votes'] ?> صوت</span><button type="submit" form="dc<?= $k ?>" onclick="return confirm('حذف المرشح؟')" class="bg-red-700 text-white text-xs px-3 py-1.5 rounded-lg cursor-pointer">حذف</button></div><?php endforeach; ?>
<?= F('new_cand', '', 'إضافة مرشح جديد (اكتب الاسم ثم اضغط حفظ)', $c, 'text', '', ' maxlength="60"') ?>
<button class="<?= $sv ?>">حفظ</button></form>
<?php /* نماذج الحذف خارج النموذج الرئيسي: النماذج المتداخلة غير مسموحة في HTML وكانت تقطع النموذج وتمنع إضافة مرشح */ foreach ($S['motm']['candidates'] as $k => $cd): ?><form id="dc<?= $k ?>" method="POST" class="hidden"><?= H('del_cand') ?><input type="hidden" name="i" value="<?= $k ?>"></form><?php endforeach; ?>
<div class="flex flex-wrap items-center justify-between gap-2"><a href="index.php?fanvote=1" target="_blank" class="text-xs text-red-700 underline">فتح صفحة التصويت العامة (index.php?fanvote=1)</a><?= B('reset_votes', 'تصفير الأصوات وبدء تصويت جديد', null, 'تصفير جميع الأصوات؟', 'bg-amber-700') ?></div></section>

<section id="t11" class="tab hidden <?= $box ?>"><form method="POST" enctype="multipart/form-data" class="space-y-3"><?= H('gallery') ?>
<h2 class="font-bold text-red-800">ألبوم الصور</h2>
<div class="grid md:grid-cols-2 gap-3"><div><label class="block text-gray-600 mb-1 text-xs">رفع صورة جديدة</label><input type="file" name="gallery_img" accept="image/*" required class="w-full text-xs text-gray-600"></div><?= F('gallery_caption', '', 'تعليق الصورة', $c) ?></div>
<button class="<?= $sv ?>">رفع</button></form>
<div class="grid grid-cols-2 md:grid-cols-3 gap-3"><?php foreach ($S['gallery'] as $k => $g): ?><div class="<?= $in2 ?> !p-0 overflow-hidden"><img src="<?= e(img($g['url'])) ?>" alt="" class="h-28 w-full object-cover"><div class="p-2 text-[11px] flex justify-between items-center gap-1"><span class="truncate"><?= e($g['caption'] ?? '') ?><?= !empty($g['ts']) ? ' • ' . date('Y-m-d', (int)$g['ts']) : '' ?></span><?= B('del_gal', 'حذف', $k, 'حذف الصورة؟') ?></div></div><?php endforeach; ?></div></section>
<section id="t12" class="tab hidden <?= $box ?>">
<div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-bold text-red-800">إحصائيات الزوار والتطبيق والإشعارات</h2>
<div class="flex gap-1 text-xs"><?php foreach (['1' => 'اليوم', '7' => '7 أيام', '30' => '30 يوماً', '120' => 'الكل'] as $k => $l): ?><a href="?r=<?= $k ?>#t12" class="px-3 py-1.5 rounded-lg <?= $rg == $k ? 'bg-red-700 text-white' : 'bg-white border border-gray-300' ?>"><?= $l ?></a><?php endforeach; ?></div></div>
<div class="<?= $in2 ?> flex flex-wrap items-center justify-between gap-2 text-xs"><span><?= $sn === null ? 'لم تُحفظ نقطة مرجعية بعد؛ اضغط الزر لبدء تتبّع الجديد.' : 'الجديد منذ آخر تعليم (' . e($ST['snap']['t']) . '): المؤشر الأخضر +N = الزيادة منذ ذلك الوقت.' ?></span><?= B('stats_mark', 'تعليم الإحصائيات كمقروءة', null, '', 'bg-emerald-700') ?></div>
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-center">
<?php foreach ([['مشاهدات الصفحات', $ag['v'], 'v'], ['الزيارات (جلسات)', $ag['s'], 's'], ['زوار فريدون (مجموع يومي)', $ag['u'], 'u'], ['النقرات المسجلة', array_sum($ag['k']), 'k'], ['مثبّتو التطبيق (حدث التثبيت)', $ST['ev']['install'] ?? 0, 'ev:install'], ['أجهزة تفتح التطبيق المثبّت', $ST['ev']['standalone'] ?? 0, 'ev:standalone'], ['مفعّلو جرس الإشعارات حالياً', $psn, 'push'], ['فعّلوا / رفضوا الإشعارات', ($ST['ev']['notif_granted'] ?? 0) . ' / ' . ($ST['ev']['notif_denied'] ?? 0), 'ev:notif_granted']] as [$ql, $qv, $qk]): ?>
<div class="<?= $in2 ?>"><div class="text-xl font-black text-red-700"><?= e($qv) ?><?= $dl($qk) ?></div><div class="text-[11px] text-gray-600"><?= $ql ?></div></div><?php endforeach; ?></div>
<div class="<?= $in2 ?>"><div class="text-xs font-bold mb-2">المشاهدات اليومية</div><div class="flex items-end gap-1 h-24 overflow-x-auto"><?php $mx = max($dv ?: [1]) ?: 1; foreach ($dv as $dk => $vv): ?><div class="flex-1 min-w-[6px] bg-red-600 rounded-t" style="height:<?= max(2, round($vv * 100 / $mx)) ?>%" title="<?= e($dk . ': ' . $vv) ?>"></div><?php endforeach; ?></div></div>
<div class="grid md:grid-cols-2 gap-3">
<?php foreach ([['الدول (من أين جاء الزائر)', bars($ag['c'], 'cn', 10, fn($k) => $dl('c:' . $k))], ['الأجهزة', bars($ag['d'], fn($k) => $dvl[$k] ?? $k, 10, fn($k) => $dl('d:' . $k))], ['أنظمة التشغيل', bars($ag['o'], null, 10, fn($k) => $dl('o:' . $k))], ['المتصفحات', bars($ag['b'], null, 10, fn($k) => $dl('b:' . $k))], ['مصادر الزيارات', bars($ag['r'], null, 10, fn($k) => $dl('r:' . $k))], ['الصفحات الأكثر مشاهدة', bars($ag['p'], fn($k) => $k . '.php', 10, fn($k) => $dl('p:' . $k))], ['النقر على الروابط والبانرات', bars($ag['k'], fn($k) => $cl[$k] ?? (preg_match('/^bh_(\d+)$/', $k, $mm) ? 'سلايد أفقي #' . $mm[1] : $k), 15, fn($k) => $dl('k:' . $k))]] as [$ttl, $html]): ?>
<div class="<?= $in2 ?> space-y-2"><div class="text-xs font-bold text-red-800"><?= $ttl ?></div><?= $html ?></div><?php endforeach; ?></div>
<form method="POST" class="<?= $in2 ?> flex flex-wrap items-center gap-2 text-xs"><?= H('stats_reset') ?><span class="font-bold text-red-800">تصفير الإحصائيات:</span>
<select name="what" class="<?= $c ?> !w-auto"><option value="all">كل الإحصائيات (زيارات + نقرات + عدّادات)</option><option value="visits">الزيارات والدول والأجهزة فقط</option><option value="clicks">النقرات فقط</option><option value="app">عدّادات التثبيت والإشعارات فقط</option><option value="push">مشتركو الإشعارات الحاليون (حذف الاشتراكات)</option></select>
<button class="bg-amber-700 text-white px-3 py-1.5 rounded-lg cursor-pointer" onclick="return confirm('تأكيد التصفير؟ لا يمكن التراجع.')">تصفير</button></form>
<p class="text-[11px] text-gray-500">الدولة بعلامة ≈ تقدير من لغة المتصفح. للدقة الكاملة فعّل Cloudflare (ترويسة CF-IPCountry) أو GeoIP على الخادم. لا تُخزَّن عناوين IP.</p>
<div class="<?= $in2 ?> space-y-3"><h3 class="font-bold text-sm text-red-800">الإشعارات (<?= $psn ?> جهاز مشترك)</h3>
<?php if (!function_exists('curl_multi_init') || !function_exists('openssl_sign') || empty(vapid()['pub'])): ?><div class="bg-red-50 text-red-800 text-xs p-2 rounded-lg">تنبيه: إشعارات الخلفية تتطلب امتدادي cURL و OpenSSL وHTTPS على الخادم.</div><?php endif; ?>
<p class="text-xs text-gray-500">تُرسل الإشعارات تلقائياً عند كل حدث في البث الحي (هدف، بطاقة، نهاية...) حتى لو كان الموقع مغلقاً.</p>
<form method="POST" class="grid md:grid-cols-3 gap-2 items-end"><?= H('push_msg') ?><?= F('pt', '', 'عنوان إشعار يدوي', $c) . F('pb', '', 'النص', $c) ?><button class="bg-red-700 text-white text-xs py-2.5 rounded-xl cursor-pointer" onclick="return confirm('إرسال لكل المشتركين؟')">إرسال إشعار</button></form></div>
<div class="<?= $in2 ?> space-y-3"><h3 class="font-bold text-sm text-red-800">تذكير اقتراب المباراة</h3>
<form method="POST" class="flex flex-wrap items-end gap-3 text-xs"><?= H('reminder_save') ?><label class="cursor-pointer"><input type="checkbox" name="rm_on" <?= !empty($S['reminder']['on']) ? 'checked' : '' ?>> إشعار تلقائي قبل الانطلاقة (Push + تنبيه داخل الصفحة)</label>
<div><label class="block text-gray-600 mb-1">قبل كم دقيقة (5–180)</label><input type="number" name="rm_min" min="5" max="180" value="<?= (int)$S['reminder']['min'] ?>" class="<?= $c ?> !w-24"></div><button class="bg-red-700 text-white px-4 py-2 rounded-lg cursor-pointer">حفظ</button></form>
<p class="text-[11px] text-gray-500 leading-5">يُرسل مرة واحدة لكل مباراة عند أول طلب للموقع داخل النافذة الزمنية. لضمان الإرسال حتى بلا زوار، أضف مهمة Cron كل 5 دقائق تستدعي الرابط التالي:</p>
<input readonly dir="ltr" value="<?= e($cu) ?>" onclick="this.select()" class="<?= $c ?> font-mono"></div>
</section>

<?php $mt = in_array($_GET['m'] ?? '', ['ad', 'contact'], true) ? $_GET['m'] : ''; ?>
<section id="t13" class="tab hidden <?= $box ?>">
<div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-bold text-red-800">صندوق الوارد (<?= count($msgs) ?> • غير مقروء: <?= $unread ?>)</h2>
<div class="flex flex-wrap gap-1 text-xs items-center"><?php foreach (['' => 'الكل', 'contact' => 'راسلنا', 'ad' => 'الإعلانات'] as $k => $l): ?><a href="?<?= $k ? 'm=' . $k : '' ?>#t13" class="px-3 py-1.5 rounded-lg <?= $mt === $k ? 'bg-red-700 text-white' : 'bg-white border border-gray-300' ?>"><?= $l ?></a><?php endforeach; ?>
<?= $unread ? B('read_all', 'تعليم الكل مقروءاً', null, '', 'bg-sky-700') : '' ?><?= $msgs ? B('clear_msgs', 'حذف الكل', null, 'حذف جميع الرسائل؟') : '' ?></div></div>
<div class="max-h-[32rem] overflow-y-auto space-y-2 text-xs">
<?php foreach (array_reverse($msgs, true) as $k => $r): $ty = $r['type'] ?? 'contact'; if ($mt && $ty !== $mt) continue; $un = empty($r['read']); ?>
<div class="<?= $in2 ?> !p-3 space-y-1 <?= $un ? '!bg-amber-50 !border-amber-300' : '' ?>">
<div class="flex flex-wrap justify-between items-center gap-2"><div><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $ty === 'ad' ? 'bg-amber-200 text-amber-900' : 'bg-sky-100 text-sky-800' ?>"><?= $ty === 'ad' ? 'طلب إعلان' : 'رسالة' ?></span> <b><?= e($r['name']) ?></b> <a href="mailto:<?= e($r['email']) ?>" class="text-red-700" dir="ltr"><?= e($r['email']) ?></a><?= !empty($r['company']) ? ' • ' . e($r['company']) : '' ?><?= !empty($r['phone']) ? ' • <span dir="ltr">' . e($r['phone']) . '</span>' : '' ?></div>
<div class="flex items-center gap-2 text-gray-500"><?= e($r['date']) ?><?= $un ? B('read_msg', 'مقروءة', $k, '', 'bg-sky-700') : '' ?><?= B('del_msg', 'حذف', $k, 'حذف الرسالة؟') ?></div></div>
<p class="whitespace-pre-wrap"><?= e($r['message']) ?></p></div><?php endforeach; ?></div>
</section>

<section id="t14" class="tab hidden <?= $box ?>">
<h2 class="font-bold text-red-800">تحميل نسخة من البيانات</h2>
<ul class="text-xs space-y-1"><?php foreach (glob(DD . '*.json') as $f): ?><li class="<?= $in2 ?> !p-2 flex justify-between"><span dir="ltr"><?= e(basename($f)) ?></span><span><?= round(filesize($f) / 1024, 1) ?> KB • <?= date('Y-m-d H:i', filemtime($f)) ?></span></li><?php endforeach; ?></ul>
<form method="POST" class="flex flex-wrap gap-2"><?= H('backup') ?><button name="what" value="data" class="bg-red-700 text-white text-xs px-4 py-2.5 rounded-xl cursor-pointer">تحميل ملفات البيانات (ZIP)</button><button name="what" value="all" class="bg-gray-800 text-white text-xs px-4 py-2.5 rounded-xl cursor-pointer">البيانات + الصور المرفوعة (ZIP)</button></form>
<p class="text-[11px] text-amber-700">تحتوي النسخة على config.json (هاش كلمة المرور ومفتاح الإشعارات الخاص) وبريد المشتركين، فاحفظها في مكان آمن.</p>
</section>
<section id="t15" class="tab hidden <?= $box ?>">
<h2 class="font-bold text-red-800">ترقية الموقع من ملف ZIP</h2>
<p class="text-xs text-gray-600">ارفع حزمة تحتوي الملفات المتغيرة فقط. تُحفظ نسخة من الملفات القديمة قبل الاستبدال ويمكنك الاسترجاع. تُرفض الحزمة كاملة إن احتوت أي مسار غير مسموح أو خطأ صياغة PHP.</p>
<div class="bg-sky-50 border border-sky-200 text-sky-900 text-[11px] p-3 rounded-xl leading-relaxed" dir="ltr">Allowed: inc.php, api.php, admin.php, index.php, contact.php, advertise.php, manifest.php, icon.php, sw.js, WSSE_files/app.js, WSSE_files/logo.svg<br>Never touched: data/, WSSE_files/uploads/, .htaccess</div>
<form method="POST" enctype="multipart/form-data" class="<?= $in2 ?> space-y-3"><?= H('upgrade') ?>
<div><label class="block text-gray-600 mb-1 text-xs">ملف الترقية (ZIP، حتى 20MB)</label><input type="file" name="pkg" accept=".zip" required class="w-full text-xs text-gray-600"></div>
<?= F('pw', '', 'كلمة مرور المشرف للتأكيد', $c, 'password') ?>
<button class="<?= $sv ?>" onclick="return confirm('تثبيت الترقية؟')">تثبيت الترقية</button></form>
<h3 class="font-bold text-sm">النسخ الاحتياطية (آخر 10)</h3>
<?php $bks = glob(DD . 'backups/upg-*.zip') ?: []; rsort($bks); if (!$bks): ?><p class="text-xs text-gray-400">لا توجد ترقيات سابقة.</p><?php endif;
foreach ($bks as $bf): $bz = new ZipArchive; $bm = []; if ($bz->open($bf) === true) { $bm = json_decode((string)$bz->getFromName('_manifest.json'), true) ?: []; $bz->close(); } ?>
<div class="<?= $in2 ?> !p-3 text-xs space-y-2"><div class="flex flex-wrap justify-between gap-2"><b dir="ltr"><?= e(basename($bf)) ?></b><span><?= e($bm['label'] ?? '') ?></span></div><div class="text-gray-500" dir="ltr"><?= e(implode(', ', $bm['files'] ?? [])) ?></div>
<form method="POST" class="flex gap-2 items-center"><?= H('upgrade_rollback') ?><input type="hidden" name="bk" value="<?= e(basename($bf)) ?>"><input type="password" name="pw" required placeholder="كلمة مرور المشرف" class="bg-white border border-gray-300 rounded-lg p-1.5 text-xs w-44"><button class="bg-amber-700 text-white text-xs px-3 py-1.5 rounded-lg cursor-pointer" onclick="return confirm('استرجاع هذه النسخة؟')">استرجاع</button></form></div>
<?php endforeach; ?>
</section>
<section id="t16" class="tab hidden <?= $box ?>">
<h2 class="font-bold text-red-800">فحص جاهزية تثبيت التطبيق (PWA)</h2>
<?php $lgx = strtolower(pathinfo(img($S['logo']), PATHINFO_EXTENSION));
$pc = [['HTTPS على الخادم', !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'], ['امتداد GD (توليد الأيقونات بجودة أفضل)', function_exists('imagecreatetruecolor')], ['امتداد zlib (بديل عند غياب GD)', function_exists('gzcompress')], ['ملف sw.js موجود', is_file(__DIR__ . '/sw.js')], ['شعار نقطي PNG/JPG/WEBP (يُفضّل ≥ 512px)', in_array($lgx, ['png', 'jpg', 'jpeg', 'webp'], true)]]; ?>
<div class="space-y-2 text-xs"><?php foreach ($pc as [$pl, $pv]): ?><div class="<?= $in2 ?> !p-2.5"><?= $pv ? '✅' : '❌' ?> <?= e($pl) ?></div><?php endforeach; ?></div>
<button type="button" onclick="pwaCheck()" class="<?= $sv ?>">فحص الموقع الحي من متصفحك</button>
<div id="pwa_out" class="space-y-2 text-xs"></div>
<div class="<?= $in2 ?> text-xs leading-6"><b>اختبار الاقتراح فعلياً:</b> افتح <a href="index.php#pwadebug" target="_blank" class="text-red-700 underline" dir="ltr">index.php#pwadebug</a> من Chrome على الهاتف؛ تظهر لوحة تبيّن هل وصل حدث التثبيت، وتُلغي الإغلاق المؤقت للاقتراح.</div>
</section>
<section id="t17" class="tab hidden <?= $box ?>"><form method="POST" enctype="multipart/form-data" class="space-y-3"><?= H('standings') ?>
<?php $sd = $S['standings']; $stc = st_calc($S); ?>
<div class="flex flex-wrap items-center justify-between gap-2"><h2 class="font-bold text-red-800">بوكس ترتيب الفريق</h2><label class="text-xs cursor-pointer"><input type="checkbox" name="st_active" <?= $sd['active'] ? 'checked' : '' ?>> إظهار في الصفحة الرئيسية</label></div>
<div class="<?= $in2 ?> overflow-x-auto"><table class="w-full text-center text-xs min-w-[520px]"><thead class="text-gray-500"><tr><?php foreach (['الفريق', 'لعب', 'فاز', 'تعادل', 'خسر', 'له', 'عليه', 'فارق', 'نقط'] as $hh): ?><th class="p-1"><?= $hh ?></th><?php endforeach; ?></tr></thead><tbody><tr class="font-bold"><td><?= e($stc['team']) ?></td><?php foreach (['p', 'w', 'd', 'l', 'gf', 'ga', 'gd', 'pts'] as $kk): ?><td><?= (int)$stc[$kk] ?></td><?php endforeach; ?></tr></tbody></table><p class="text-[11px] text-gray-500 mt-2">معاينة الأرقام الحالية (الوضع: <?= $sd['mode'] === 'manual' ? 'يدوي' : 'تلقائي من أرشيف المباريات السابقة' ?>).</p></div>
<div class="grid md:grid-cols-2 gap-3 text-xs">
<div class="md:col-span-2 bg-sky-50 border border-sky-200 text-sky-900 p-2.5 rounded-xl">الفريق: <b><?= e($stc['team'] ?: 'غير مسجّل') ?></b> — يُؤخذ من «هوية الموقع والخلفية». تُحتسب تلقائياً كل المباريات المؤرشفة له، وتُضاف المباراة المقبلة فور إنهائها من البث الحي.</div>
<?= F('st_rank', $sd['rank'], 'الترتيب في البطولة (رقم)', $c, 'number') ?>
<?= F('st_comp', $sd['competition'], 'احتساب مباريات بطولة محددة فقط (فارغ = كل المباريات)', $c) ?>
<div><label class="block text-gray-600 mb-1">موضع البوكس</label><select name="st_pos" class="<?= $c ?>"><option value="top" <?= $sd['pos'] === 'top' ? 'selected' : '' ?>>أعلى الصفحة (قبل الترويسة)</option><option value="after_hero" <?= $sd['pos'] === 'after_hero' ? 'selected' : '' ?>>أعلى الصفحة (بعد الترويسة)</option></select></div>
<div><label class="block text-gray-600 mb-1">مصدر الأرقام</label><select name="st_mode" class="<?= $c ?>"><option value="auto" <?= $sd['mode'] === 'auto' ? 'selected' : '' ?>>تلقائي من أرشيف المباريات السابقة</option><option value="manual" <?= $sd['mode'] === 'manual' ? 'selected' : '' ?>>يدوي</option></select></div>
<?= F('st_win', $sd['win_pts'], 'نقاط الفوز', $c, 'number') . F('st_draw', $sd['draw_pts'], 'نقاط التعادل', $c, 'number') ?>
</div>
<div class="<?= $in2 ?> space-y-3"><span class="font-bold text-xs text-red-800">القيم اليدوية (تُستخدم في الوضع اليدوي فقط)</span>
<div class="grid grid-cols-4 md:grid-cols-7 gap-2"><?php foreach (['p' => 'لعب', 'w' => 'فاز', 'd' => 'تعادل', 'l' => 'خسر', 'gf' => 'له', 'ga' => 'عليه', 'pts' => 'نقط'] as $k => $l) echo F('m_' . $k, $sd['manual'][$k] ?? 0, $l, $c, 'number'); ?></div>
<?= F('m_form', $sd['manual']['form'] ?? '', 'آخر 5 نتائج (W فوز / D تعادل / L خسارة، أو ف / ت / خ — من الأقدم للأحدث)', $c, 'text', 'ltr') ?></div>
<button class="<?= $sv ?>">حفظ</button></form></section>
<section id="t18" class="tab hidden <?= $box ?>">
<?php $sd = $S['seo']; $sM = matches_all($S); $sT = teams_all($S); $sG = tags_all($S); $nUrl = 8 + count($sM) + count($sT) + count($sG);
$sNoLogo = count(array_filter($sT, fn($t) => trim((string)$t['logo']) === '')); $sNoCap = count(array_filter($S['gal'], fn($g) => trim((string)($g['caption'] ?? '')) === ''));
$sCk = [['رابط الموقع الأساسي المعتمد: ' . base_url(), $sd['base_url'] !== ''], ['الموقع على HTTPS', strpos(base_url(), 'https://') === 0], ['الموقع مفتوح لمحركات البحث (غير مخفي)', empty($sd['noindex'])], ['وصف الموقع (description) مكتوب', trim($sd['desc']) !== ''], ['اسم فريق الموقع مسجّل', site_team($S)['name'] !== ''], ['شارة «تحت الإنشاء» مخفية', !empty($sd['hide_uc'])], ['كل الأندية لها شعار (' . $sNoLogo . ' بلا شعار)', $sNoLogo === 0], ['صور الألبوم لها تعليقات وصفية (' . $sNoCap . ' بلا تعليق)', $sNoCap === 0], ['ملف sw/manifest/أيقونات PNG جاهزة', is_file(__DIR__ . '/icon.php')]]; ?>
<h2 class="font-bold text-red-800">SEO والأرشفة في محركات البحث</h2>
<div class="grid md:grid-cols-2 gap-2 text-xs"><?php foreach ($sCk as [$sl, $sv2]): ?><div class="<?= $in2 ?> !p-2.5"><?= $sv2 ? '✅' : '⚠️' ?> <?= e($sl) ?></div><?php endforeach; ?></div>
<div class="<?= $in2 ?> text-xs leading-6 space-y-1"><div>تُفهرس تلقائياً: <b><?= $nUrl ?></b> رابطاً (مباريات <?= count($sM) ?> • أندية <?= count($sT) ?> • وسوم <?= count($sG) ?> • صور <?= count($S['gal']) ?>) داخل خريطة الموقع.</div>
<div class="flex flex-wrap gap-2 pt-1"><a href="sitemap.xml" target="_blank" class="bg-white border border-gray-300 px-3 py-1.5 rounded-lg">sitemap.xml</a><a href="rss.xml" target="_blank" class="bg-white border border-gray-300 px-3 py-1.5 rounded-lg">rss.xml</a><a href="robots.txt" target="_blank" class="bg-white border border-gray-300 px-3 py-1.5 rounded-lg">robots.txt</a><a href="index.php?tags=1" target="_blank" class="bg-white border border-gray-300 px-3 py-1.5 rounded-lg">الأندية والوسوم</a>
<a href="https://search.google.com/test/rich-results?url=<?= e(rawurlencode(base_url())) ?>" target="_blank" rel="noopener" class="bg-sky-50 border border-sky-300 text-sky-800 px-3 py-1.5 rounded-lg">اختبار البيانات المنظّمة</a><a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="bg-sky-50 border border-sky-300 text-sky-800 px-3 py-1.5 rounded-lg">Google Search Console</a><a href="https://www.bing.com/webmasters" target="_blank" rel="noopener" class="bg-sky-50 border border-sky-300 text-sky-800 px-3 py-1.5 rounded-lg">Bing Webmaster</a></div></div>
<form method="POST" enctype="multipart/form-data" class="space-y-3"><?= H('seo_save') ?>
<div class="grid md:grid-cols-2 gap-3 text-xs">
<?= F('seo_base', $sd['base_url'], 'رابط الموقع الأساسي المعتمد (مهم جداً) مثال: https://www.example.com', $c, 'text', 'ltr') . F('seo_title', $sd['title'], 'عنوان الصفحة الرئيسية (title) — فارغ = توليد تلقائي', $c, 'text', '', ' maxlength="70"') ?>
<div class="md:col-span-2"><label class="block text-gray-600 mb-1">وصف الموقع (description) — جملة أو جملتان بكلمات البحث المهمة (حتى 300 حرف)</label><textarea name="seo_desc" rows="2" maxlength="300" class="<?= $c ?>"><?= e($sd['desc']) ?></textarea></div>
<?= F('seo_kw', $sd['keywords'], 'كلمات مفتاحية إضافية (مفصولة بفاصلة)', $c) . F('seo_alt', $sd['org_alt'], 'أسماء بديلة للفريق (مفصولة بفاصلة) مثال: WSSE, وفاق أزغنغان, Wifaq Azghanghan', $c) ?>
<?= F('seo_ogu', $sd['og_image'], 'صورة المشاركة الافتراضية OG (رابط أو مسار) — فارغ = أيقونة الموقع', $c, 'text', 'ltr') ?><div><label class="block text-gray-600 mb-1">أو ارفع صورة مشاركة (1200×630 يُفضّل)</label><input type="file" name="seo_og_f" accept="image/*" class="w-full text-xs text-gray-600"></div>
<?= F('seo_tw', $sd['twitter'], 'حساب X/تويتر (بدون @)', $c, 'text', 'ltr') . F('seo_cc', $sd['country'], 'رمز الدولة (مثال: MA)', $c, 'text', 'ltr', ' maxlength="2"') ?>
<?= F('seo_gv', $sd['gverify'], 'رمز التحقق من Google Search Console (قيمة content فقط)', $c, 'text', 'ltr') . F('seo_bv', $sd['bverify'], 'رمز التحقق من Bing Webmaster', $c, 'text', 'ltr') ?>
</div>
<div class="flex flex-wrap gap-6 text-xs"><label class="cursor-pointer"><input type="checkbox" name="seo_hideuc" <?= !empty($sd['hide_uc']) ? 'checked' : '' ?>> إخفاء شارة «الموقع تحت الإنشاء»</label><label class="cursor-pointer text-red-700"><input type="checkbox" name="seo_noindex" <?= !empty($sd['noindex']) ? 'checked' : '' ?>> إخفاء الموقع عن محركات البحث (للتجربة فقط)</label></div>
<button class="<?= $sv ?>">حفظ إعدادات SEO</button></form>
<div class="<?= $in2 ?> text-xs leading-7 space-y-1"><b class="text-red-800">خطوات الظهور في نتائج البحث</b>
<div>1) اضبط «رابط الموقع الأساسي» وفعّل HTTPS، واحذف «تحت الإنشاء» من العنوان والعنوان الرئيسي.</div><div>2) سجّل الموقع في Google Search Console وBing Webmaster، والصق رمز التحقق أعلاه، ثم أرسل <b dir="ltr"><?= e(base_url()) ?>sitemap.xml</b>.</div><div>3) استخدم «طلب الفهرسة» لصفحتك الرئيسية وصفحات المباريات الجديدة، وأضف مباراة ونتيجة بانتظام (المحتوى المتجدد يرفع الأرشفة).</div><div>4) اكتب تعليقاً وصفياً لكل صورة، وسجّل اسم الفريق وشعاره في «هوية الموقع».</div><div>5) اربط الموقع من حساباتك الاجتماعية ومن مواقع الأخبار الرياضية المحلية؛ الروابط الخارجية أهم عامل ترتيب.</div></div>
</section>
<section id="t19" class="tab hidden <?= $box ?>">
<?php $sq = $S['squad']; $lu = $S['lineup']; $pls = $sq['players']; $fl = ['4-4-2', '4-3-3', '4-2-3-1', '3-5-2', '3-4-3', '5-3-2', '4-1-4-1', '4-5-1']; $pp = ['GK' => 'حارس', 'DF' => 'مدافع', 'MF' => 'وسط', 'FW' => 'مهاجم']; ?>
<form method="POST" class="space-y-3"><?= H('squad_save') ?>
<h2 class="font-bold text-red-800">طاقم اللاعبين والجهاز الفني</h2>
<div class="grid md:grid-cols-2 gap-3"><?= F('coach', $sq['coach'], 'المدرب', $c) . F('assistant', $sq['assistant'], 'المدرب المساعد', $c) ?></div>
<div class="space-y-2 text-xs">
<?php foreach ($pls as $p): $pi = e($p['id']); ?><div class="flex gap-2 items-center"><input type="number" name="pl[<?= $pi ?>][no]" value="<?= (int)$p['no'] ?>" min="0" max="99" class="<?= $c ?> !w-16 text-center"><input type="text" name="pl[<?= $pi ?>][name]" value="<?= e($p['name']) ?>" maxlength="40" class="<?= $c ?>"><select name="pl[<?= $pi ?>][pos]" class="<?= $c ?> !w-auto"><?php foreach ($pp as $k => $l): ?><option value="<?= $k ?>" <?= $p['pos'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><label class="whitespace-nowrap text-red-700 cursor-pointer"><input type="checkbox" name="pl_del[<?= $pi ?>]"> حذف</label></div><?php endforeach; ?>
<div class="text-gray-600 font-bold pt-2">إضافة لاعبين (الرقم + الاسم + المركز):</div>
<?php for ($i = 0; $i < 3; $i++): ?><div class="flex gap-2 items-center"><input type="number" name="nw[<?= $i ?>][no]" placeholder="الرقم" min="0" max="99" class="<?= $c ?> !w-16 text-center"><input type="text" name="nw[<?= $i ?>][name]" placeholder="اسم اللاعب" maxlength="40" class="<?= $c ?>"><select name="nw[<?= $i ?>][pos]" class="<?= $c ?> !w-auto"><?php foreach ($pp as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div><?php endfor; ?>
</div><button class="<?= $sv ?>">حفظ الطاقم</button></form>
<form method="POST" enctype="multipart/form-data" class="space-y-3 border-t border-gray-200 pt-4"><?= H('lineup_save') ?>
<h2 class="font-bold text-red-800">الرسم التكتيكي: التشكيلة الأساسية (11) والبدلاء</h2>
<?php if (!$pls): ?><p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2">أضف اللاعبين أولاً ثم احفظ الطاقم لتظهر أسماؤهم هنا.</p><?php endif; ?>
<div class="flex flex-wrap gap-3 items-end text-xs"><div><label class="block text-gray-600 mb-1">الخطة</label><select name="lu_f" id="lu_f" onchange="luLab()" class="<?= $c ?> !w-auto"><?php foreach ($fl as $f2): ?><option value="<?= $f2 ?>" <?= $lu['formation'] === $f2 ? 'selected' : '' ?>><?= $f2 ?></option><?php endforeach; ?></select></div>
<label class="cursor-pointer font-bold text-emerald-800 bg-emerald-50 border border-emerald-300 px-3 py-2.5 rounded-lg"><input type="checkbox" name="lu_pub" <?= lineup_ready($S) ? 'checked' : '' ?>> إعلان التشكيلة الرسمية (<?= e($m['home_team'] . ' × ' . $m['away_team']) ?>)</label></div>
<div class="grid sm:grid-cols-2 md:grid-cols-3 gap-2 text-xs"><?php for ($i = 0; $i < 11; $i++): $cur = $lu['xi'][$i] ?? ''; ?><div><label class="block text-gray-600 mb-1 lu-l"><?= $i + 1 ?></label><select name="lu_xi[<?= $i ?>]" class="<?= $c ?>"><option value="">—</option><?php foreach ($pls as $p): ?><option value="<?= e($p['id']) ?>" <?= $cur === $p['id'] ? 'selected' : '' ?>>#<?= (int)$p['no'] ?> <?= e($p['name']) ?></option><?php endforeach; ?></select></div><?php endfor; ?></div>
<div><div class="text-xs font-bold text-gray-700 mb-1">البدلاء</div><div class="flex flex-wrap gap-2 text-xs"><?php foreach ($pls as $p): ?><label class="bg-white border border-gray-200 rounded-lg px-2.5 py-1.5 cursor-pointer"><input type="checkbox" name="lu_sub[]" value="<?= e($p['id']) ?>" <?= in_array($p['id'], (array)$lu['subs'], true) ? 'checked' : '' ?>> #<?= (int)$p['no'] ?> <?= e($p['name']) ?></label><?php endforeach; ?></div></div>
<div class="<?= $in2 ?> space-y-3"><label class="text-xs font-bold cursor-pointer"><input type="checkbox" name="lu_ads" <?= !empty($lu['ads']['active']) ? 'checked' : '' ?>> عرض لوحات إشهارية على جانبي رسم التشكيلة</label>
<div class="grid md:grid-cols-2 gap-3 text-xs"><?php foreach ([['lu_adl', 'اللوحة اليسرى', $lu['ads']['l'] ?? []], ['lu_adr', 'اللوحة اليمنى', $lu['ads']['r'] ?? []]] as [$ak, $al, $av]): ?><div class="space-y-2 bg-gray-50 border border-gray-200 rounded-xl p-3"><b class="text-red-800"><?= $al ?></b><?= F($ak . '_img', $av['img'] ?? '', 'رابط الصورة (أو ارفع ملفاً)', $c, 'text', 'ltr') ?><input type="file" name="<?= $ak ?>_f" accept="image/*" class="w-full text-xs text-gray-600"><?= F($ak . '_url', $av['url'] ?? '', 'رابط التوجيه', $c, 'text', 'ltr') . F($ak . '_alt', $av['alt'] ?? '', 'نص بديل', $c) ?></div><?php endforeach; ?></div></div>
<button class="<?= $sv ?>">حفظ التشكيلة</button>
<p class="text-[11px] text-gray-500">تظهر التشكيلة للزوار داخل زر «عرض تشكيلة المباراة» في بوكس النقل الحي وعلى الرابط index.php?lineup=1 عند الإعلان فقط، وتُخفى تلقائياً عند تغيير المباراة المقبلة.</p></form>
</section>
</main></div></div>
<script>
const CK=<?= json_encode(['per' => $ck['per'], 'e' => $ck['e'], 'run' => $ck['run'], 'half' => $ck['half']]) ?>,t0=Date.now();
setInterval(()=>{const x=document.getElementById('aclk');if(!x||!CK.run)return;const e=Math.floor(CK.e+(Date.now()-t0)/1000),b=CK.per==2?CK.half:0,m=Math.floor(e/60),p=n=>String(n).padStart(2,'0');if(m>=CK.half){const y=e-CK.half*60;x.textContent=(b+CK.half)+'+'+p(Math.floor(y/60))+':'+p(y%60)}else x.textContent=p(b+m)+':'+p(e%60)},500);
function evf(v){const g=i=>document.getElementById(i);g('f_res').classList.toggle('hidden',v!=='penalty');g('f_ref').classList.toggle('hidden',v!=='goal_cancel');g('f_p2').classList.toggle('hidden',v!=='sub');g('f_team').classList.toggle('hidden',v==='goal_cancel');g('l_p1').textContent=v==='sub'?'اللاعب الداخل':'اللاعب'}
const T=[...document.querySelectorAll('.tab')],Bn=[...document.querySelectorAll('.tb')];
function show(i){if(!document.getElementById(i))i='t1';T.forEach(s=>s.classList.toggle('hidden',s.id!==i));Bn.forEach(b=>{const a=b.dataset.t===i;b.classList.toggle('bg-red-700',a);b.classList.toggle('text-white',a);b.classList.toggle('hover:bg-gray-200',!a)})}
Bn.forEach(b=>b.onclick=()=>{location.hash=b.dataset.t});addEventListener('hashchange',()=>show(location.hash.slice(1)));show(location.hash.slice(1)||'t1');
const esc=s=>s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function bgen(){const g=i=>document.getElementById(i).value.trim(),im=g('bg_img'),u=g('bg_url'),w=g('bg_sz');if(!/^(https?:\/\/|WSSE_files\/)/i.test(im)){alert('أدخل رابط الصورة أو مسارها داخل الموقع');return}if(u&&!/^https?:\/\//i.test(u)){alert('رابط الوجهة يجب أن يبدأ بـ https://');return}const t='<img src="'+esc(im)+'" alt="'+esc(g('bg_alt'))+'" loading="lazy" style="display:block;margin:auto;height:auto;width:100%'+(w?';max-width:'+w+'px':'')+'">';document.getElementById('bg_out').value=u?'<a href="'+esc(u)+'" target="_blank" rel="noopener sponsored">'+t+'</a>':t}
function bput(n){bgen();const o=document.getElementById('bg_out').value;if(!o)return;document.querySelector('[name='+n+']').value=o;document.querySelector('[name='+(n==='bh_code'?'bh':'bv')+']').checked=true;alert('تم وضع الكود في الحقل. اضغط حفظ لتطبيقه.')}
async function pwaCheck(){const out=document.getElementById('pwa_out');out.textContent='جارٍ الفحص...';const rows=[],add=(t,v,m,n)=>rows.push('<div class="flex justify-between gap-2 bg-white border border-gray-200 rounded-xl p-2.5"><span>'+(n?'ℹ️ ':(v?'✅ ':'❌ '))+t+'</span><span class="text-gray-500" dir="ltr">'+esc(String(m||''))+'</span></div>');
add('سياق آمن (HTTPS)',isSecureContext,location.protocol);let mf=null;
try{const r=await fetch('manifest.php',{cache:'no-store'}),ct=r.headers.get('content-type')||'';mf=await r.json();add('المانيفست يُحمَّل بصيغة صحيحة',r.ok&&/json/.test(ct),ct)}catch(e){add('المانيفست',false,e.message)}
if(mf){add('name / start_url / display',!!(mf.name&&mf.start_url&&['standalone','fullscreen','minimal-ui'].includes(mf.display)),mf.display);
for(const sz of [192,512]){const ic=(mf.icons||[]).find(i=>i.sizes===sz+'x'+sz&&(i.purpose||'any').includes('any'));if(!ic){add('أيقونة '+sz+'px',false,'غير معلنة');continue}
try{const r=await fetch(new URL(ic.src,location.href),{cache:'no-store'}),t=r.headers.get('content-type')||'',bm=await createImageBitmap(await r.blob());add('أيقونة '+sz+'px (PNG فعلي)',r.ok&&/image\/png/.test(t)&&bm.width>=sz&&bm.height>=sz,t+' '+bm.width+'x'+bm.height)}catch(e){add('أيقونة '+sz+'px',false,'تعذّر التحميل أو فك الترميز')}}}
try{const reg=await navigator.serviceWorker.getRegistration('./');add('Service Worker مسجّل ونشط',!!(reg&&reg.active),reg?(reg.active?'active':'waiting'):'افتح الصفحة الرئيسية ثم أعد الفحص')}catch(e){add('Service Worker',false,e.message)}
{const sa=matchMedia('(display-mode: standalone)').matches;add('وضع العرض الحالي (معلومة فقط)',sa,sa?'داخل التطبيق المثبّت':'داخل المتصفح (طبيعي)',1)};out.innerHTML=rows.join('')}
const ST=<?= json_encode(['n' => $stm['name'], 'l' => $stm['logo']], JSON_UNESCAPED_UNICODE) ?>;
function setSide(sel,g){const f=sel.form,v=sel.value;if(!v)return;const o=v==='home'?'away':'home',q=(s,k)=>f.querySelector('[name="'+g+'['+s+'_'+k+']"]');q(v,'team').value=ST.n;q(v,'logo').value=ST.l;const t=q(o,'team'),l=q(o,'logo');if(t&&t.value===ST.n){t.value='';if(l)l.value=''}}
function luLab(){const f=document.getElementById('lu_f');if(!f)return;const r=f.value.split('-').map(Number),L=['حارس المرمى'];r.forEach((n,i)=>{const t=i===0?'مدافع':(i===r.length-1?'مهاجم':'وسط');for(let j=1;j<=n;j++)L.push(t+' '+j+' (خط '+(i+1)+')')});document.querySelectorAll('.lu-l').forEach((e,i)=>e.textContent=(i+1)+' - '+(L[i]||''))}
luLab();
</script>
</body></html>
