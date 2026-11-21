<?php
const DD = __DIR__ . '/data/';
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
if (!defined('NOSESS')) session_start();
date_default_timezone_set('Africa/Casablanca');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function jget($f, $d = []) { if (isset($GLOBALS['JC'][$f])) return $GLOBALS['JC'][$f]; $p = DD . $f; if (!is_file($p)) return $d; $j = json_decode((string)file_get_contents($p), true); return is_array($j) ? ($GLOBALS['JC'][$f] = $j) : $d; }
function jup($f, $fn, $d = []) {
    $h = fopen(DD . $f, 'c+'); if (!$h) return $d;
    flock($h, LOCK_EX);
    $a = json_decode((string)stream_get_contents($h), true); if (!is_array($a)) $a = $d;
    $a = $fn($a);
    ftruncate($h, 0); rewind($h);
    fwrite($h, json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($h); flock($h, LOCK_UN); fclose($h); unset($GLOBALS['JC'][$f]);
    return $a;
}
function tok() { return $_SESSION['t'] ??= bin2hex(random_bytes(16)); }
function chk() { if (!hash_equals($_SESSION['t'] ?? '', (string)($_POST['t'] ?? ''))) { http_response_code(403); exit('403'); } }
function ip() { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'wsse'); }
function rl($k, $max, $win) {
    $ok = true;
    jup('rl.json', function ($a) use ($k, $max, $win, &$ok) {
        $n = time();
        foreach ($a as $kk => $v) { $a[$kk] = array_values(array_filter($v, fn($t) => $t > $n - 900)); if (!$a[$kk]) unset($a[$kk]); }
        $r = array_values(array_filter($a[$k] ?? [], fn($t) => $t > $n - $win));
        if (count($r) >= $max) $ok = false; else $r[] = $n;
        $a[$k] = $r; return $a;
    }, []);
    return $ok;
}
function su($u) { $u = trim((string)$u); return preg_match('~^https?://[^\s"<>]+$~i', $u) ? $u : ''; }
function img($u) { $u = trim((string)$u); $d = 'WSSE_files/logo.svg'; if (preg_match('~^https?://[^\s"<>]+$~i', $u)) return $u; return (preg_match('~^WSSE_files/[\w./-]+$~', $u) && is_file(__DIR__ . '/' . $u)) ? $u : $d; }
function up($f, $pre, $max = 4194304) {
    $GLOBALS['ue'] = '';
    if (empty($_FILES[$f]) || ($_FILES[$f]['error'] ?? 4) === 4) return null;
    $er = $_FILES[$f]['error'];
    if ($er === 1 || $er === 2) { $GLOBALS['ue'] = 'حجم الملف أكبر من الحد المسموح في إعدادات الخادم (upload_max_filesize).'; return null; }
    if ($er !== 0) { $GLOBALS['ue'] = 'فشل الرفع (رمز ' . $er . ').'; return null; }
    if ($_FILES[$f]['size'] > $max) { $GLOBALS['ue'] = 'حجم الصورة أكبر من ' . round($max / 1048576) . 'MB.'; return null; }
    $i = @getimagesize($_FILES[$f]['tmp_name']);
    $m = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
    if (!$i || !isset($m[$i['mime']])) { $GLOBALS['ue'] = 'صيغة غير مدعومة (JPG, PNG, WEBP, GIF, AVIF).'; return null; }
    $dir = __DIR__ . '/WSSE_files/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_writable($dir)) { $GLOBALS['ue'] = 'المجلد WSSE_files/uploads غير قابل للكتابة (امنحه الصلاحية 755 أو 775).'; return null; }
    $n = 'WSSE_files/uploads/' . $pre . bin2hex(random_bytes(6)) . '.' . $m[$i['mime']];
    if (!move_uploaded_file($_FILES[$f]['tmp_name'], __DIR__ . '/' . $n)) { $GLOBALS['ue'] = 'تعذر حفظ الملف على الخادم.'; return null; }
    @chmod(__DIR__ . '/' . $n, 0644);
    return $n;
}
function mid($m) { return md5(($m['date'] ?? '') . ($m['home_team'] ?? '') . ($m['away_team'] ?? '')); }
function site() {
    static $s;
    if ($s) return $s;
    lv_sync();
    $d = [
        'site_title' => 'WSSE | الموقع تحت الإنشاء', 'main_heading' => 'موقعنا قيد الإنطلاق والتطوير', 'sub_message' => '',
        'logo' => 'WSSE_files/6d221651f903d5a9_WBtj.jpg', 'is_live' => false, 'live_minute' => '', 'home_score' => 0, 'away_score' => 0,
        'finished' => false, 'final_home' => '', 'final_away' => '', 'show_correct_predictions' => false,
        'social' => ['active' => true, 'facebook' => '', 'twitter' => '', 'instagram' => '', 'youtube' => ''],
        'banners' => ['horizontal_active' => false, 'horizontal_code' => '', 'vertical_active' => false, 'vertical_code' => '', 'slides' => [], 'slides_active' => false, 'slide_sec' => 5, 'slide_ratio' => 'compact'],
        'footer' => ['copyright' => '© {year} WSSE - جميع الحقوق محفوظة', 'show_contact' => true, 'show_advertise' => true, 'email' => '', 'contact_intro' => '', 'advertise_text' => ''],
        'last_match' => ['home_team' => '', 'home_logo' => '', 'home_score' => 0, 'away_team' => '', 'away_logo' => '', 'away_score' => 0, 'date' => '', 'stadium' => '', 'city' => '', 'competition' => '', 'round' => ''],
        'match' => ['home_team' => '', 'home_logo' => '', 'home_rank' => '', 'away_team' => '', 'away_logo' => '', 'away_rank' => '', 'date' => '', 'time' => '00:00', 'stadium' => '', 'city' => '', 'competition' => '', 'round' => '', 'weather' => ''],
        'bg_image' => 'WSSE_files/pexels-photo-114296_WBtj.avif', 'bg_color' => '#991b1b', 'live' => lv_def(),
        'motm' => ['active' => false, 'poll' => 1, 'question' => '', 'candidates' => []], 'gallery' => [], 'archive' => [],
        'team' => ['name' => '', 'logo' => ''], 'squad' => ['coach' => '', 'assistant' => '', 'players' => []], 'lineup' => ['formation' => '4-4-2', 'xi' => [], 'subs' => [], 'published' => false, 'mid' => '', 'ads' => ['active' => false, 'l' => ['img' => '', 'url' => '', 'alt' => ''], 'r' => ['img' => '', 'url' => '', 'alt' => '']]], 'seo' => ['base_url' => '', 'title' => '', 'desc' => '', 'keywords' => '', 'og_image' => '', 'twitter' => '', 'gverify' => '', 'bverify' => '', 'org_alt' => '', 'country' => 'MA', 'noindex' => false, 'hide_uc' => false], 'fixtures' => [], 'advance_at' => 0, 'reminder' => ['on' => true, 'min' => 60],
        'standings' => ['active' => true, 'pos' => 'top', 'team' => '', 'logo' => '', 'rank' => '', 'competition' => '', 'mode' => 'auto', 'win_pts' => 3, 'draw_pts' => 1, 'manual' => ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'pts' => 0, 'form' => '']],
    ];
    $s = array_replace_recursive($d, jget('data.json'));
    $pa = array_values(array_filter((array)$s['archive'], 'is_array')); $lmx = $s['last_match'];
    if ($lmx['home_team'] !== '' || $lmx['away_team'] !== '') { $kx = md5($lmx['date'] . $lmx['home_team'] . $lmx['away_team']); $dup = false; foreach ($pa as $x) if (md5(($x['date'] ?? '') . ($x['home_team'] ?? '') . ($x['away_team'] ?? '')) === $kx) $dup = true; if (!$dup) $pa[] = $lmx; }
    usort($pa, fn($a, $b) => strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? '')));
    foreach ($pa as $i => $x) $pa[$i] = array_merge(['home_team' => '', 'home_logo' => '', 'home_score' => 0, 'away_team' => '', 'away_logo' => '', 'away_score' => 0, 'date' => '', 'stadium' => '', 'city' => '', 'competition' => '', 'round' => ''], $x, ['no' => $i + 1, 'id' => substr(md5(($x['date'] ?? '') . ($x['home_team'] ?? '') . ($x['away_team'] ?? '')), 0, 8)]);
    $s['past'] = $pa;
    $gl = array_values(array_filter((array)$s['gallery'], 'is_array')); foreach ($gl as $i => $x) $gl[$i]['_i'] = $i;
    usort($gl, fn($a, $b) => ((int)($b['ts'] ?? 0) <=> (int)($a['ts'] ?? 0)) ?: ($b['_i'] <=> $a['_i']));
    $s['gal'] = $gl;
    $L = array_merge(lv_def(), $s['live']); $c = lv_clock($L);
    if ($s['is_live']) $s['live_minute'] = $c['min'];
    $s['lv'] = ['L' => $L, 'c' => $c];
    return $s;
}
function head($t, $bg = true, $ex = '') {
    $s = site(); $lg = img($s['logo']);
    $hc = preg_match('/^#[0-9a-f]{6}$/i', $s['bg_color']) ? $s['bg_color'] : '#991b1b';
    [$r, $g, $b] = sscanf($hc, '#%02x%02x%02x');
    $bi = trim($s['bg_image']); $bi = (preg_match('~^https?://[^\s"\'<>()]+$~', $bi) || (preg_match('~^WSSE_files/[\w./-]+$~', $bi) && is_file(__DIR__ . '/' . $bi))) ? $bi : '';
    $bgc = "background-color:$hc;background-image:linear-gradient(135deg,rgba($r,$g,$b,.95),rgba(" . (int)($r * .45) . ',' . (int)($g * .45) . ',' . (int)($b * .45) . ',.98))' . ($bi ? ",url('" . $bi . "')" : '') . ';background-size:cover;background-position:center;background-attachment:fixed'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<?= $ex ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= e($t) ?></title>
<meta name="theme-color" content="<?= e($hc) ?>">
<?= seo_head($t) ?>
<script>window.VAPID=<?= json_encode(vapid()['pub'] ?? '') ?>;</script>
<link rel="manifest" href="manifest.php">
<link rel="icon" href="<?= e($lg) ?>">
<link rel="apple-touch-icon" href="icon.php?s=192">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<script src="https://cdn.jsdelivr.net/npm/lucide@0.469.0/dist/umd/lucide.min.js"></script>
<style>
body{font-family:'Cairo',sans-serif;<?= $bgc ?>;padding-bottom:env(safe-area-inset-bottom)}
.logo-glow{filter:drop-shadow(0 10px 20px rgba(0,0,0,.5))}
input,textarea,button{font-family:inherit}
</style>
</head>
<body class="min-h-screen text-white py-6 px-4 flex flex-col justify-between" <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'data-idx="1"' : '' ?>>
<?php }
function foot() { $s = site(); $f = $s['footer']; $so = $s['social']; ?>
<nav aria-label="روابط الموقع" class="max-w-6xl w-full mx-auto mt-10 flex flex-wrap justify-center gap-x-5 gap-y-1.5 text-[11px] font-semibold text-red-100"><a href="index.php" class="hover:text-white">الرئيسية</a><a href="index.php?archive=1" class="hover:text-white">أرشيف المباريات</a><a href="index.php?lineup=1" class="hover:text-white">التشكيلة</a><a href="index.php?tags=1" class="hover:text-white">الأندية والوسوم</a><a href="index.php?album=1" class="hover:text-white">ألبوم الصور</a><a href="index.php?matchpredictions=1" class="hover:text-white">توقعات الجماهير</a><a href="index.php?fanvote=1" class="hover:text-white">صوت الجماهير</a><a href="rss.php" class="hover:text-white">RSS</a></nav>
<footer class="max-w-6xl w-full mx-auto mt-6 pt-6 border-t border-white/15 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-red-200">
<div data-dbg><?= e(str_replace('{year}', date('Y'), $f['copyright'])) ?></div>
<?php if ($f['show_contact'] || $f['show_advertise']): ?>
<div class="flex items-center gap-4 font-semibold">
<?php if ($f['show_contact']): ?><a href="contact.php" data-track="contact" class="hover:text-white transition">راسلنا</a><?php endif; ?>
<?php if ($f['show_contact'] && $f['show_advertise']): ?><span>•</span><?php endif; ?>
<?php if ($f['show_advertise']): ?><a href="advertise.php" data-track="advertise" class="hover:text-white transition">أعلن معنا</a><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($so['active']): $L = ['facebook' => 'فيسبوك', 'twitter' => 'تويتر (X)', 'instagram' => 'انستغرام', 'youtube' => 'يوتيوب']; $x = array_filter(array_map('su', array_intersect_key($so, $L))); if ($x): ?>
<div class="flex items-center gap-4">
<?php foreach ($x as $k => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer" data-track="soc_<?= e($k) ?>" title="<?= e($L[$k]) ?>" aria-label="<?= e($L[$k]) ?>" class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/25 hover:text-white transition"><i data-lucide="<?= e($k) ?>" class="w-4 h-4"></i></a><?php endforeach; ?>
</div>
<?php endif; endif; ?>
</footer>
<div id="pwa" class="fixed inset-x-3 bottom-3 z-50 max-w-md mx-auto flex flex-col gap-2" style="bottom:calc(.75rem + env(safe-area-inset-bottom))"></div>
<script src="WSSE_files/app.js?v=<?= (int)@filemtime(__DIR__ . '/WSSE_files/app.js') ?>"></script>
</body>
</html>
<?php }

function lv_def() { return ['st' => 'idle', 'pz' => false, 'per' => 1, 'run' => false, 'seg' => 0, 'acc' => 0, 'half' => 45, 'stop1' => 0, 'stop2' => 0, 'rev' => 0, 'events' => []]; }
function lv_min($per, $e, $half, $run = true) {
    $m = $run ? (int)floor($e / 60) + 1 : max(1, (int)ceil($e / 60)); $b = (int)$per === 2 ? $half : 0;
    return ($m > $half ? ($b + $half) . '+' . ($m - $half) : $b + $m) . "'";
}
// ساعة المباراة بالثواني: 42:15 | 45+02:30 | 90+04:12
function lv_clk($per, $e, $half) {
    $e = max(0, (int)$e); $b = (int)$per === 2 ? (int)$half : 0; $m = intdiv($e, 60);
    if ($m >= $half) { $x = $e - $half * 60; return ($b + $half) . '+' . sprintf('%02d:%02d', intdiv($x, 60), $x % 60); }
    return sprintf('%02d:%02d', $b + $m, $e % 60);
}
function lv_clock($L) {
    $e = (int)$L['acc'] + ($L['run'] ? time() - (int)$L['seg'] : 0); $per = (int)$L['per']; $h = (int)$L['half'];
    return ['st' => $L['st'], 'pz' => (bool)$L['pz'], 'per' => $per, 'e' => $e, 'run' => (bool)$L['run'], 'half' => $h, 'lim' => ($h + (int)$L['stop1']) * 60, 'min' => lv_min($per, $e, $h, (bool)$L['run']), 'clk' => lv_clk($per, $e, $h)];
}
function lv_score($L) {
    $h = $a = 0;
    foreach ($L['events'] as $v) {
        if (!empty($v['void'])) continue;
        if ($v['type'] === 'goal' || ($v['type'] === 'penalty' && ($v['res'] ?? '') === 'scored')) { if ($v['team'] === 'home') $h++; else $a++; }
    }
    return [$h, $a];
}
// العداد يستمر تلقائياً (45+N' / 90+N') ولا يتوقف إلا يدوياً من لوحة التحكم
function lv_sync() {
    static $x; if ($x) return; $x = 1;
    $d = jget('data.json'); $at = (int)($d['advance_at'] ?? 0);
    if ($at && time() >= $at && in_array($d['live']['st'] ?? '', ['finished', 'cancelled'], true)) jup('data.json', fn($z) => fx_advance($z), []);
    reminder_check();
}
function lv_act($d, $a, $in) {
    $L = array_merge(lv_def(), $d['live'] ?? []); $n = time(); $st = $L['st']; $live = ['first', 'half', 'second'];
    $el = function () use (&$L, $n) { return (int)$L['acc'] + ($L['run'] ? $n - (int)$L['seg'] : 0); };
    $add = function ($ty, $tm = '', $p1 = '', $p2 = '', $rs = '', $mi = null, $x = []) use (&$L, $el) {
        $L['events'][] = array_merge(['id' => bin2hex(random_bytes(4)), 'type' => $ty, 'team' => $tm, 'p1' => $p1, 'p2' => $p2, 'reason' => $rs, 'min' => $mi ?? lv_min((int)$L['per'], $el(), (int)$L['half'], (bool)$L['run']), 'ts' => time()], $x);
    };
    $halt = function () use (&$L, $el) { $L['acc'] = $el(); $L['run'] = false; };
    $cl = fn($v, $lo, $hi) => max($lo, min($hi, (int)$v));
    $tx = fn($k, $l = 200) => mb_substr(trim((string)($in[$k] ?? '')), 0, $l);
    switch ($a) {
        case 'lv_kickoff':
            if (in_array($st, ['idle', 'finished', 'cancelled'], true)) { $h = $L['half']; $L = lv_def(); $L['half'] = $h; $L['st'] = 'first'; $L['run'] = true; $L['seg'] = $n; $add('kickoff', '', '', '', '', "0'"); }
            break;
        case 'lv_half':
            if ($st === 'first') { $halt(); $L['st'] = 'half'; $L['pz'] = false; $add('halftime', '', '', '', '', 'HT'); }
            break;
        case 'lv_autohalf':
            if ($st === 'first' && $L['run']) { $lim = ((int)$L['half'] + (int)$L['stop1']) * 60; if ($el() >= $lim) { $L['acc'] = $lim; $L['run'] = false; $L['st'] = 'half'; $add('halftime', '', '', '', '', 'HT'); } }
            break;
        case 'lv_second':
            if ($st === 'half') { $L['st'] = 'second'; $L['per'] = 2; $L['acc'] = 0; $L['seg'] = $n; $L['run'] = true; $add('second', '', '', '', '', ($L['half'] + 1) . "'"); }
            break;
        case 'lv_pause':
            if (in_array($st, ['first', 'second'], true) && $L['run']) { $halt(); $L['pz'] = true; $add('suspend', '', '', '', $tx('reason')); }
            break;
        case 'lv_resume':
            if ($L['pz']) { $L['seg'] = $n; $L['run'] = true; $L['pz'] = false; $add('resume'); }
            break;
        case 'lv_finish':
            if (in_array($st, $live, true)) { if ($L['run']) $halt(); $L['pz'] = false; $L['st'] = 'finished'; $add('full'); }
            break;
        case 'lv_cancel':
            if (in_array($st, $live, true)) { $halt(); $L['pz'] = false; $L['st'] = 'cancelled'; $add('cancel', '', '', '', $tx('reason')); }
            break;
        case 'lv_stop':
            $L['half'] = $cl($in['half'] ?? 45, 5, 60); $k = 'stop' . (int)$L['per']; $o = $L[$k];
            $L['stop1'] = $cl($in['stop1'] ?? 0, 0, 30); $L['stop2'] = $cl($in['stop2'] ?? 0, 0, 30);
            if ($st !== 'idle' && $L[$k] !== $o && $L[$k] > 0) $add('stoppage', '', '+' . $L[$k]);
            break;
        case 'lv_event':
            $ty = $in['ev_type'] ?? '';
            if ($st !== 'idle' && in_array($ty, ['goal', 'penalty', 'goal_cancel', 'yellow', 'red', 'sub', 'injury'], true)) {
                $tm = ($in['ev_team'] ?? '') === 'away' ? 'away' : 'home'; $x = [];
                if ($ty === 'penalty') { $r = $in['ev_res'] ?? 'scored'; $x['res'] = in_array($r, ['scored', 'missed', 'saved'], true) ? $r : 'scored'; }
                if ($ty === 'goal_cancel') {
                    $ok = false;
                    foreach ($L['events'] as &$ev) { if ($ev['id'] === ($in['ev_ref'] ?? '') && empty($ev['void'])) { $ev['void'] = true; $tm = $ev['team']; $ok = true; break; } }
                    unset($ev);
                    if (!$ok) break;
                    $x['ref'] = $in['ev_ref'];
                }
                $mi = $tx('ev_min', 10);
                $add($ty, $tm, $tx('ev_p1', 60), $tx('ev_p2', 60), $tx('ev_reason'), $mi !== '' ? $mi : null, $x);
            }
            break;
        case 'lv_edit_ev':
            foreach ($L['events'] as $k => $ev) {
                if ($ev['id'] !== ($in['id'] ?? '')) continue;
                $ty = $ev['type']; $mi = $tx('ev_min', 10); if ($mi !== '') $ev['min'] = $mi;
                $ev['reason'] = $tx('ev_reason');
                if (in_array($ty, ['goal', 'penalty', 'yellow', 'red', 'sub', 'injury'], true)) {
                    $ev['team'] = ($in['ev_team'] ?? '') === 'away' ? 'away' : 'home';
                    $ev['p1'] = $tx('ev_p1', 60); if ($ty === 'sub') $ev['p2'] = $tx('ev_p2', 60);
                    if ($ty === 'penalty') { $r = $in['ev_res'] ?? 'scored'; $ev['res'] = in_array($r, ['scored', 'missed', 'saved'], true) ? $r : 'scored'; }
                    foreach ($L['events'] as $k2 => $o) if (($o['ref'] ?? '') === $ev['id']) $L['events'][$k2]['team'] = $ev['team'];
                }
                $L['events'][$k] = $ev; break;
            }
            break;
        case 'lv_del_ev':
            foreach ($L['events'] as $k => $ev) {
                if ($ev['id'] === ($in['id'] ?? '')) {
                    if ($ev['type'] === 'goal_cancel') { foreach ($L['events'] as &$o) { if ($o['id'] === ($ev['ref'] ?? '')) unset($o['void']); } unset($o); }
                    unset($L['events'][$k]); break;
                }
            }
            $L['events'] = array_values($L['events']);
            break;
        case 'lv_reset':
            $h = $L['half']; $L = lv_def(); $L['half'] = $h;
            break;
    }
    if (in_array($a, ['lv_finish', 'lv_cancel'], true) && !empty($d['fixtures'])) $d['advance_at'] = time() + 1800; // الانتقال للمباراة التالية بعد 30 دقيقة
    if (in_array($a, ['lv_kickoff', 'lv_reset'], true)) $d['advance_at'] = 0;
    $L['rev'] = (int)(microtime(true) * 10);
    [$hs, $as] = lv_score($L);
    $d['live'] = $L; $d['home_score'] = $hs; $d['away_score'] = $as; $d['is_live'] = in_array($L['st'], $live, true); $d['finished'] = $L['st'] === 'finished';
    if ($L['st'] === 'finished') { $d['final_home'] = $hs; $d['final_away'] = $as; $d = lv_archive($d, $hs, $as); }
    elseif (in_array($L['st'], ['idle', 'first'], true)) { $d['final_home'] = ''; $d['final_away'] = ''; }
    return $d;
}
function ev_txt($v) {
    $T = ['goal' => 'هدف', 'penalty' => 'ضربة جزاء', 'goal_cancel' => 'إلغاء هدف', 'yellow' => 'بطاقة صفراء', 'red' => 'بطاقة حمراء', 'sub' => 'تبديل', 'injury' => 'إصابة', 'suspend' => 'توقيف المباراة', 'resume' => 'استئناف المباراة', 'cancel' => 'إلغاء المباراة', 'kickoff' => 'انطلاق المباراة', 'halftime' => 'استراحة بين الشوطين', 'second' => 'انطلاق الشوط الثاني', 'full' => 'نهاية المباراة', 'stoppage' => 'وقت بدل ضائع'];
    $t = $T[$v['type']] ?? ''; $d = [];
    if ($v['type'] === 'penalty') { $r = ['scored' => 'سُجّلت', 'missed' => 'أُضيعت', 'saved' => 'تصدّى لها الحارس'][$v['res'] ?? ''] ?? ''; if ($r) $t .= " ($r)"; }
    if ($v['type'] === 'sub') { if ($v['p1'] !== '') $d[] = 'دخول: ' . $v['p1']; if ($v['p2'] !== '') $d[] = 'خروج: ' . $v['p2']; }
    elseif ($v['type'] === 'stoppage') $d[] = $v['p1'] . ' دقائق';
    elseif ($v['p1'] !== '') $d[] = $v['p1'];
    return [$t, implode(' • ', $d), $v['reason'] ?? ''];
}
function ev_ico($t, $z = 20) {
    $ball = '<circle cx="12" cy="12" r="10" fill="#fff" stroke="#111" stroke-width="1.5"/><path d="M12 7.5l4 2.9-1.5 4.7h-5L8 10.4z" fill="#111"/><path d="M12 7.5V2.5M16 10.4l4.6-1.5M14.5 15.1l2.9 4M9.5 15.1l-2.9 4M8 10.4 3.4 8.9" stroke="#111" stroke-width="1.2" fill="none"/>';
    $whistle = '<circle cx="9" cy="14" r="6" fill="#e5e7eb" stroke="#111" stroke-width="1.4"/><path d="M13 9l9-4v6l-8 1" fill="#e5e7eb" stroke="#111" stroke-width="1.4"/><circle cx="9" cy="14" r="1.6" fill="#111"/>';
    $i = [
        'goal' => $ball,
        'goal_cancel' => '<g opacity=".5">' . $ball . '</g><path d="M3 3l18 18" stroke="#dc2626" stroke-width="2.8" stroke-linecap="round"/>',
        'yellow' => '<rect x="6" y="2.5" width="12" height="19" rx="2" fill="#facc15" stroke="#a16207"/>',
        'red' => '<rect x="6" y="2.5" width="12" height="19" rx="2" fill="#dc2626" stroke="#7f1d1d"/>',
        'injury' => '<rect x="1" y="6.5" width="14" height="10" rx="1.5" fill="#fff" stroke="#111" stroke-width="1.3"/><path d="M15 9.5h4l3.5 3.5v3.5H15z" fill="#fff" stroke="#111" stroke-width="1.3"/><circle cx="6" cy="18" r="2.2" fill="#111"/><circle cx="18" cy="18" r="2.2" fill="#111"/><path d="M1 12h14" stroke="#2563eb" stroke-width="1.6"/><rect x="6" y="3" width="4" height="2.5" rx="1" fill="#dc2626"/>',
        'penalty' => '<path d="M2 20V5h20v15" fill="none" stroke="#16a34a" stroke-width="1.8"/><path d="M5 20V8h14v12" fill="none" stroke="#16a34a" stroke-opacity=".6"/><circle cx="12" cy="14" r="3.2" fill="#fff" stroke="#111"/><circle cx="12" cy="14" r="1" fill="#111"/>',
        'halftime' => '<path d="M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z" fill="#fde68a" stroke="#92400e" stroke-width="1.4"/><path d="M16 10h1.5a2.5 2.5 0 0 1 0 5H16" fill="none" stroke="#92400e" stroke-width="1.4"/><path d="M8 3.5c-1 1 1 2 0 3M12 3.5c-1 1 1 2 0 3" stroke="#92400e" fill="none" stroke-linecap="round"/>',
        'sub' => '<path d="M8 20V5M8 5L4.5 8.5M8 5l3.5 3.5" stroke="#16a34a" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 4v15M16 19l-3.5-3.5M16 19l3.5-3.5" stroke="#dc2626" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
        'suspend' => '<rect x="6" y="4" width="4" height="16" rx="1" fill="#f59e0b"/><rect x="14" y="4" width="4" height="16" rx="1" fill="#f59e0b"/>',
        'resume' => '<path d="M7 4l13 8-13 8z" fill="#16a34a"/>',
        'cancel' => '<circle cx="12" cy="12" r="10" fill="#dc2626"/><path d="M8 8l8 8M16 8l-8 8" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>',
        'kickoff' => $whistle, 'second' => $whistle,
        'full' => '<path d="M5 22V3" stroke="#111" stroke-width="2"/><path d="M5 4h14l-3 4 3 4H5z" fill="#16a34a"/>',
        'stoppage' => '<circle cx="12" cy="12" r="9.5" fill="#fff" stroke="#111" stroke-width="1.5"/><path d="M12 6v6l4 2" stroke="#111" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
    ];
    return '<svg viewBox="0 0 24 24" width="' . $z . '" height="' . $z . '" aria-hidden="true">' . ($i[$t] ?? $i['stoppage']) . '</svg>';
}


// ===================== Inbox =====================
function inbox_add($type, $n, $em, $tx, $x = []) {
    jup('messages.json', function ($a) use ($type, $n, $em, $tx, $x) { $a[] = array_merge(['type' => $type, 'name' => $n, 'email' => $em, 'message' => $tx, 'date' => date('Y-m-d H:i'), 'read' => false], $x); return array_slice($a, -500); }, []);
    $to = filter_var(site()['footer']['email'], FILTER_VALIDATE_EMAIL);
    if ($to) @mail($to, '=?UTF-8?B?' . base64_encode(($type === 'ad' ? 'طلب إعلان جديد: ' : 'رسالة جديدة من الموقع: ') . str_replace(["\r", "\n"], ' ', $n)) . '?=', "$tx\n\n$n <$em>", "Reply-To: $em\r\nContent-Type: text/plain; charset=UTF-8");
}

// ===================== Web Push (VAPID بدون مكتبات) =====================
function b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function vapid() {
    $c = jget('config.json');
    if (empty($c['vapid']['pub'])) {
        if (!function_exists('openssl_pkey_new')) return null;
        $k = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$k) return null;
        $d = openssl_pkey_get_details($k)['ec']; openssl_pkey_export($k, $pem);
        $v = ['pub' => b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)), 'pem' => $pem];
        $c = jup('config.json', function ($a) use ($v) { if (empty($a['vapid']['pub'])) $a['vapid'] = $v; return $a; }, []);
    }
    return $c['vapid'] ?? null;
}
function vapid_jwt($aud) {
    $v = vapid(); if (!$v) return null;
    $em = filter_var(site()['footer']['email'], FILTER_VALIDATE_EMAIL) ?: 'admin@example.com';
    $h = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])); $p = b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => 'mailto:' . $em]));
    if (!openssl_sign("$h.$p", $sig, $v['pem'], OPENSSL_ALGO_SHA256)) return null;
    $rl = ord($sig[3]); $r = substr($sig, 4, $rl); $sl = ord($sig[5 + $rl]); $s = substr($sig, 6 + $rl, $sl);
    $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT); $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    return "$h.$p." . b64u($r . $s);
}
function push_send() {
    $subs = jget('push.json')['subs'] ?? []; $v = vapid();
    if (!$subs || !$v || !function_exists('curl_multi_init')) return;
    $mh = curl_multi_init(); $hs = []; $jw = [];
    foreach ($subs as $k => $s) {
        $u = parse_url($s['endpoint']); if (empty($u['host'])) continue;
        $aud = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
        $jw[$aud] ??= vapid_jwt($aud); if (!$jw[$aud]) continue;
        $ch = curl_init($s['endpoint']);
        curl_setopt_array($ch, [CURLOPT_POST => 1, CURLOPT_POSTFIELDS => '', CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Authorization: vapid t=' . $jw[$aud] . ', k=' . $v['pub'], 'TTL: 3600', 'Urgency: high', 'Content-Length: 0']]);
        curl_multi_add_handle($mh, $ch); $hs[$k] = $ch;
    }
    do { curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1); } while ($run);
    $dead = [];
    foreach ($hs as $k => $ch) { $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); if ($c == 404 || $c == 410) $dead[] = $subs[$k]['endpoint']; curl_multi_remove_handle($mh, $ch); curl_close($ch); }
    curl_multi_close($mh);
    if ($dead) jup('push.json', function ($a) use ($dead) { $a['subs'] = array_values(array_filter($a['subs'] ?? [], fn($s) => !in_array($s['endpoint'], $dead, true))); return $a; }, []);
}
function push_check() {
    $L = jget('data.json')['live'] ?? null; if (!$L) return;
    $ev = $L['events'] ?? []; $n = count($ev); $last = $n ? end($ev) : null; $go = false;
    jup('push.json', function ($a) use ($n, $last, &$go) { $p = $a['n'] ?? null; $a['n'] = $n; if ($p !== null && $n > $p && ($last['type'] ?? '') !== 'stoppage') $go = true; return $a; }, []);
    if ($go) push_send();
}
function push_later($force = false) {
    static $r = []; if (isset($r[(int)$force])) return; $r[(int)$force] = 1;
    register_shutdown_function(function () use ($force) {
        ignore_user_abort(true); @set_time_limit(90);
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        $force ? push_send() : push_check();
    });
}

// ===================== Statistics =====================
function st_bump(&$a, $k, $n = 1) { $a[$k] = ($a[$k] ?? 0) + $n; }
function ua_info($ua) {
    $ua = (string)$ua;
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|facebot|preview|lighthouse|headless|curl|wget|python|monitor|http|axios|okhttp|libwww|scrapy|phantom|puppeteer|playwright|selenium|pingdom|uptime|gtmetrix|semrush|ahrefs|yandex|baidu|bytespider|whatsapp|telegram|discord|slack|embedly|validator/i', $ua)) return null;
    $d = preg_match('/ipad|tablet|android(?!.*mobile)/i', $ua) ? 'tablet' : (preg_match('/mobi|iphone|ipod|android/i', $ua) ? 'mobile' : 'desktop');
    $o = preg_match('/iphone|ipad|ipod/i', $ua) ? 'iOS' : (preg_match('/android/i', $ua) ? 'Android' : (preg_match('/windows/i', $ua) ? 'Windows' : (preg_match('/mac os/i', $ua) ? 'macOS' : (preg_match('/linux/i', $ua) ? 'Linux' : 'أخرى'))));
    $b = preg_match('/edg\//i', $ua) ? 'Edge' : (preg_match('/opr\/|opera/i', $ua) ? 'Opera' : (preg_match('/samsungbrowser/i', $ua) ? 'Samsung' : (preg_match('/chrome|crios/i', $ua) ? 'Chrome' : (preg_match('/firefox|fxios/i', $ua) ? 'Firefox' : (preg_match('/safari/i', $ua) ? 'Safari' : 'أخرى')))));
    return [$d, $o, $b];
}
function geo() {
    foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE'] as $h) { $c = strtoupper((string)($_SERVER[$h] ?? '')); if (preg_match('/^[A-Z]{2}$/', $c) && $c !== 'XX' && $c !== 'T1') return $c; }
    if (preg_match('/[-_]([A-Za-z]{2})\b/', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $m)) return strtoupper($m[1]) . '*';
    return '--';
}
function ref_label($ref, $utm) {
    $utm = strtolower(preg_replace('/[^a-z0-9._-]/i', '', (string)$utm));
    if ($utm !== '') return 'utm: ' . mb_substr($utm, 0, 30);
    $h = preg_replace('/^(www|m|l|lm|mobile)\./', '', strtolower((string)parse_url((string)$ref, PHP_URL_HOST)));
    if ($h === '') return 'مباشر / غير معروف';
    if ($h === preg_replace('/^www\./', '', strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]))) return 'داخلي';
    foreach (['google' => 'Google', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'instagram' => 'Instagram', 'youtu' => 'YouTube', 't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)', 'bing' => 'Bing', 'whatsapp' => 'WhatsApp', 't.me' => 'Telegram', 'telegram' => 'Telegram', 'tiktok' => 'TikTok'] as $k => $v) if (strpos($h, $k) !== false) return $v;
    return mb_substr($h, 0, 40);
}
function st_hit($page, $first, $ref, $utm) {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? ''); $i = ua_info($ua); if (!$i || empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return;
    $day = date('Y-m-d'); $vid = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . $ua . $day . 'wsse'), 0, 10);
    $c = geo(); $r = $first ? ref_label($ref, $utm) : '';
    jup('stats.json', function ($s) use ($day, $vid, $i, $c, $page, $first, $r) {
        if (($s['seen']['d'] ?? '') !== $day) $s['seen'] = ['d' => $day, 'ids' => []];
        $now = time(); foreach ($s['seen']['last'] ?? [] as $kk => $tt) if ($tt < $now - 1800) unset($s['seen']['last'][$kk]);
        if (isset($s['seen']['last'][$vid . $page])) return $s; // تحديث متكرر خلال 30 دقيقة: لا يُحتسب
        $s['seen']['last'][$vid . $page] = $now;
        $new = !isset($s['seen']['ids'][$vid]); if ($new && count($s['seen']['ids']) < 10000) $s['seen']['ids'][$vid] = 1;
        $d = $s['days'][$day] ?? [];
        st_bump($d, 'v'); if ($new) st_bump($d, 'u'); st_bump($d['p'], $page);
        if ($first) { st_bump($d, 's'); st_bump($d['c'], $c); st_bump($d['d'], $i[0]); st_bump($d['o'], $i[1]); st_bump($d['b'], $i[2]); st_bump($d['r'], $r); }
        $s['days'][$day] = $d; ksort($s['days']); $s['days'] = array_slice($s['days'], -120, null, true);
        return $s;
    }, []);
}
function st_click($k) {
    $day = date('Y-m-d');
    jup('stats.json', function ($s) use ($k, $day) { if (isset($s['days'][$day]['k'][$k]) || count($s['days'][$day]['k'] ?? []) < 40) st_bump($s['days'][$day]['k'], $k); return $s; }, []);
}
function st_ev($k) { jup('stats.json', function ($s) use ($k) { st_bump($s['ev'], $k); return $s; }, []); }

// مفتاح موحّد لهوية المتوقّع (بريد أو هاتف) لمنع التكرار بالتلاعب بالصيغة
function ckey($c) {
    $c = trim((string)$c);
    if (filter_var($c, FILTER_VALIDATE_EMAIL)) {
        [$u, $d] = explode('@', strtolower($c), 2); $u = explode('+', $u)[0];
        if ($d === 'gmail.com' || $d === 'googlemail.com') { $u = str_replace('.', '', $u); $d = 'gmail.com'; }
        return $u . '@' . $d;
    }
    return 'p' . substr(preg_replace('/\D/', '', $c), -9);
}

// ===================== بوكس ترتيب الفريق الواحد =====================
function st_calc($S) {
    $st = $S['standings']; $m = $S['match'];
    $stn = site_team($S)['name']; $team = trim((string)($stn !== '' ? $stn : ($st['team'] !== '' ? $st['team'] : $m['home_team'])));
    $o = ['team' => $team, 'rank' => $st['rank'], 'p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0, 'pts' => 0, 'form' => []];
    if ($st['mode'] === 'manual') {
        $mn = $st['manual']; foreach (['p', 'w', 'd', 'l', 'gf', 'ga', 'pts'] as $k) $o[$k] = (int)($mn[$k] ?? 0);
        foreach (str_split(substr(strtoupper(preg_replace('/[^WDLwdl]/', '', (string)($mn['form'] ?? ''))), -5)) as $ch) if ($ch !== '') $o['form'][] = ['r' => strtolower($ch), 't' => ''];
    } else {
        $tn = mb_strtolower($team); $cp = mb_strtolower(trim((string)$st['competition'])); $rows = [];
        foreach ($S['past'] as $x) {
            $h = mb_strtolower(trim($x['home_team'])) === $tn; $a = mb_strtolower(trim($x['away_team'])) === $tn;
            if ($h === $a) continue;
            if ($cp !== '' && mb_strtolower(trim($x['competition'])) !== $cp) continue;
            $gf = (int)($h ? $x['home_score'] : $x['away_score']); $ga = (int)($h ? $x['away_score'] : $x['home_score']);
            $rows[] = ['gf' => $gf, 'ga' => $ga, 'r' => $gf > $ga ? 'w' : ($gf < $ga ? 'l' : 'd'), 't' => ($h ? $x['away_team'] : $x['home_team']) . ' ' . $gf . '-' . $ga];
        }
        foreach ($rows as $x) { $o['p']++; $o[$x['r']]++; $o['gf'] += $x['gf']; $o['ga'] += $x['ga']; }
        $o['pts'] = $o['w'] * (int)$st['win_pts'] + $o['d'] * (int)$st['draw_pts'];
        $o['form'] = array_map(fn($x) => ['r' => $x['r'], 't' => $x['t']], array_slice($rows, -5));
    }
    $o['gd'] = $o['gf'] - $o['ga'];
    return $o;
}
function standings_html($S) {
    $st = $S['standings']; if (empty($st['active'])) return '';
    $r = st_calc($S); $lg = img(site_team($S)['logo']);
    $cols = ['p' => 'لعب', 'w' => 'فاز', 'd' => 'تعادل', 'l' => 'خسر', 'gf' => 'له', 'ga' => 'عليه', 'gd' => 'فارق', 'pts' => 'نقط'];
    $fc = ['w' => ['bg-emerald-500', 'ف', 'فوز'], 'd' => ['bg-amber-400 !text-red-950', 'ت', 'تعادل'], 'l' => ['bg-rose-600 ring-1 ring-white/40', 'خ', 'خسارة']];
    ob_start(); ?>
<section id="stbox" data-team="<?= e(md5($r['team'])) ?>" class="bg-white/10 backdrop-blur-md border border-white/20 rounded-3xl p-4 md:p-6 shadow-2xl">
<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
<div class="flex items-center gap-2 font-bold text-amber-300 text-sm"><i data-lucide="list-ordered" class="w-4 h-4"></i><span>ترتيب الفريق<?= $st['competition'] !== '' ? ' - ' . e($st['competition']) : '' ?></span></div>
<button type="button" id="stmark" class="hidden text-[11px] bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1.5 rounded-full cursor-pointer">تعليم الإحصائيات كمقروءة</button>
</div>
<div class="overflow-x-auto rounded-2xl border border-white/10">
<table class="w-full min-w-[680px] text-center text-xs md:text-sm">
<thead class="bg-black/30 text-gray-200 text-[11px] md:text-xs"><tr>
<th class="p-2.5 font-bold">الترتيب</th><th class="p-2.5 font-bold text-right">الفريق</th>
<?php foreach ($cols as $l): ?><th class="p-2.5 font-bold"><?= $l ?></th><?php endforeach; ?>
<th class="p-2.5 font-bold">النتائج السابقة</th></tr></thead>
<tbody><tr class="bg-black/20">
<td class="p-2.5"><span class="inline-flex items-center justify-center min-w-7 h-7 px-2 rounded-full bg-amber-400 text-red-950 font-black"><?= $r['rank'] !== '' ? (int)$r['rank'] : '—' ?></span></td>
<td class="p-2.5"><div class="flex items-center gap-2 justify-start"><img src="<?= e($lg) ?>" alt="شعار <?= e($r['team']) ?>" title="<?= e($r['team']) ?>" class="h-7 w-7 object-contain rounded"><span class="font-extrabold whitespace-nowrap"><?= e($r['team']) ?></span></div></td>
<?php foreach ($cols as $k => $l): $v = (int)$r[$k]; ?><td class="p-2.5 font-bold tabular-nums <?= $k === 'pts' ? 'text-amber-300 text-base' : '' ?> <?= $k === 'gd' ? ($v > 0 ? 'text-emerald-300' : ($v < 0 ? 'text-rose-300' : '')) : '' ?>"><span data-st="<?= $k ?>" data-v="<?= $v ?>"><?= $k === 'gd' && $v > 0 ? '+' . $v : $v ?></span></td><?php endforeach; ?>
<td class="p-2.5"><div class="flex items-center justify-center gap-1" dir="ltr"><?php foreach ($r['form'] as $f): [$bc, $ch, $tt] = $fc[$f['r']]; ?><span title="<?= e($f['t'] !== '' ? $tt . ': ' . $f['t'] : $tt) ?>" class="w-5 h-5 md:w-6 md:h-6 rounded-md text-[10px] md:text-xs font-black flex items-center justify-center text-white <?= $bc ?>"><?= $ch ?></span><?php endforeach; ?><?php if (!$r['form']): ?><span class="text-gray-300">—</span><?php endif; ?></div></td>
</tr></tbody></table></div>
</section>
<?php return ob_get_clean(); }

// فريق الموقع المسجّل في «هوية الموقع» (الشعار يرجع لشعار الموقع إن لم يُحدَّد)
function site_team($S) { $t = $S['team'] ?? []; $l = trim((string)($t['logo'] ?? '')); return ['name' => trim((string)($t['name'] ?? '')), 'logo' => $l !== '' ? $l : (string)$S['logo']]; }

// أرشفة المباراة المقبلة بنتيجتها فور انتهائها (أو تحديث نتيجتها إن كانت مؤرشفة) فتدخل في الترتيب مباشرة
function lv_archive($d, $hs, $as) {
    $m = $d['match'] ?? []; $g = fn($k) => (string)($m[$k] ?? '');
    if ($g('home_team') === '' && $g('away_team') === '') return $d;
    $date = $g('date') !== '' ? $g('date') : date('Y-m-d');
    $key = md5($date . $g('home_team') . $g('away_team'));
    foreach (($d['archive'] ?? []) as $i => $x) {
        if (md5(($x['date'] ?? '') . ($x['home_team'] ?? '') . ($x['away_team'] ?? '')) === $key) { $d['archive'][$i]['home_score'] = $hs; $d['archive'][$i]['away_score'] = $as; return $d; }
    }
    $d['archive'][] = ['home_team' => $g('home_team'), 'home_logo' => $g('home_logo'), 'home_score' => $hs, 'away_team' => $g('away_team'), 'away_logo' => $g('away_logo'), 'away_score' => $as, 'date' => $date, 'stadium' => $g('stadium'), 'city' => $g('city'), 'competition' => $g('competition'), 'round' => $g('round')];
    return $d;
}

// ===================== جدول المباريات القادمة والانتقال التلقائي =====================
function fx_advance($d) {
    $d['advance_at'] = 0; $fx = array_values(array_filter((array)($d['fixtures'] ?? []), 'is_array'));
    if (!$fx) return $d;
    usort($fx, fn($a, $b) => strcmp(($a['date'] ?? '') . ' ' . ($a['time'] ?? ''), ($b['date'] ?? '') . ' ' . ($b['time'] ?? '')));
    $n = array_shift($fx); $d['fixtures'] = $fx;
    $d['match'] = array_merge(['home_team' => '', 'home_logo' => '', 'home_rank' => '', 'away_team' => '', 'away_logo' => '', 'away_rank' => '', 'date' => '', 'time' => '00:00', 'stadium' => '', 'city' => '', 'competition' => '', 'round' => '', 'weather' => ''], $n);
    return lv_act($d, 'lv_reset', []);
}

// ===================== تذكير قبل المباراة (إشعار Push مرة واحدة لكل مباراة) =====================
function cron_key() {
    $c = jget('config.json');
    if (empty($c['cron'])) { $k = bin2hex(random_bytes(12)); $c = jup('config.json', function ($a) use ($k) { if (empty($a['cron'])) $a['cron'] = $k; return $a; }, []); }
    return (string)($c['cron'] ?? '');
}
function reminder_check() {
    $d = jget('data.json'); $r = $d['reminder'] ?? ['on' => true, 'min' => 60];
    if (empty($r['on']) || ($d['live']['st'] ?? 'idle') !== 'idle') return;
    $m = $d['match'] ?? []; $ks = strtotime(trim(($m['date'] ?? '') . ' ' . ($m['time'] ?? ''))); if (!$ks) return;
    $dk = $ks - time(); $win = max(5, min(180, (int)($r['min'] ?? 60))) * 60;
    if ($dk <= 0 || $dk > $win) return;
    $key = md5(($m['date'] ?? '') . ($m['time'] ?? '') . ($m['home_team'] ?? '') . ($m['away_team'] ?? '')); $go = false;
    $mins = max(1, (int)round($dk / 60)); $when = abs($mins - 60) <= 5 ? 'بعد ساعة' : 'بعد ' . $mins . ' دقيقة';
    jup('push.json', function ($a) use ($key, $m, $when, &$go) {
        if (($a['rem'] ?? '') === $key) return $a;
        $a['rem'] = $key; $go = true;
        $a['ann'] = ['t' => '⏰ تنطلق المباراة ' . $when, 'b' => trim(($m['home_team'] ?? '') . ' × ' . ($m['away_team'] ?? '')) . ' — ساعة الانطلاقة ' . ($m['time'] ?? '') . '. تابع البث الحي.', 'ts' => time()];
        return $a;
    }, []);
    if ($go) push_later(true);
}

// رابط توجيه آمن: http(s) كامل أو مسار داخلي بسيط (بدون javascript: أو //)
function lnk($u) {
    $u = trim((string)$u); if ($u === '') return '';
    if (preg_match('~^https?://[^\s"<>]+$~i', $u)) return $u;
    return preg_match('~^(?!//)[A-Za-z0-9_./?=&%#@~+-]+$~', $u) ? $u : '';
}

// إحصائيات توقعات مباراة محددة (حسب معرّفها)
function pred_stats($mid) {
    $o = ['n' => 0, 'h' => 0, 'd' => 0, 'a' => 0, 'sc' => [], 'ah' => 0, 'aa' => 0];
    foreach (jget('predictions.json') as $p) {
        if (($p['mid'] ?? '') !== $mid) continue;
        $h = (int)$p['home']; $a = (int)$p['away']; $o['n']++; $o['ah'] += $h; $o['aa'] += $a;
        $h > $a ? $o['h']++ : ($h < $a ? $o['a']++ : $o['d']++);
        $k = $h . '-' . $a; $o['sc'][$k] = ($o['sc'][$k] ?? 0) + 1;
    }
    arsort($o['sc']); return $o;
}

// ===================== صفحة الخطأ + حماية الروابط =====================
function error_page($code = 404) {
    $code = in_array((int)$code, [403, 404, 405, 500], true) ? (int)$code : 404;
    http_response_code($code); seo_set(['noindex' => true]);
    $t = [403 => ['غير مسموح بالوصول', 'هذا المسار غير متاح للعرض.'], 404 => ['الصفحة غير موجودة', 'الرابط الذي طلبته غير صحيح أو لم يعد متاحاً.'], 405 => ['طلب غير مسموح', 'هذا الطلب غير مدعوم لهذا المسار.'], 500 => ['خطأ في الخادم', 'حدث خطأ غير متوقع، حاول مجدداً بعد قليل.']][$code];
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    head($t[0] . ' - WSSE', true, '<base href="' . e($base) . '">'); ?>
<script>window.NOHIT=1</script>
<div class="max-w-xl w-full mx-auto my-auto bg-white/10 backdrop-blur-md border border-white/20 p-8 rounded-3xl shadow-2xl text-center space-y-5">
<img src="<?= e(img(site()['logo'])) ?>" alt="" class="h-20 w-auto object-contain mx-auto rounded-2xl logo-glow" data-dbg>
<div class="text-6xl md:text-7xl font-black text-amber-300 tracking-widest drop-shadow" dir="ltr"><?= $code ?></div>
<h1 class="text-2xl font-black"><?= e($t[0]) ?></h1>
<p class="text-sm text-red-100 leading-relaxed"><?= e($t[1]) ?></p>
<div class="flex flex-wrap justify-center gap-2 text-xs font-bold">
<a href="index.php" class="bg-white text-red-900 px-5 py-2.5 rounded-xl hover:bg-gray-100 transition">الصفحة الرئيسية</a>
<a href="index.php?archive=1" class="bg-white/15 px-4 py-2.5 rounded-xl hover:bg-white/25 transition">أرشيف المباريات</a>
<a href="index.php?album=1" class="bg-white/15 px-4 py-2.5 rounded-xl hover:bg-white/25 transition">ألبوم الصور</a>
<a href="contact.php" class="bg-white/15 px-4 py-2.5 rounded-xl hover:bg-white/25 transition">راسلنا</a>
</div></div>
<?php foot(); exit;
}
// أي معامل في الرابط غير معتمد => صفحة الخطأ (مع السماح بمعاملات التتبع الشائعة ووضع التصحيح)
function guard_query($allow = []) {
    $ok = array_merge($allow, ['pwadebug', 'rwadebug', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'igshid', 'ref']);
    foreach (array_keys($_GET) as $k) if (!in_array((string)$k, $ok, true)) error_page(404);
}

require_once __DIR__ . '/seo.php';
