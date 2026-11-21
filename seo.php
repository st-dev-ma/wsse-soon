<?php
// ===================== SEO: روابط مطلقة، وسوم الوصف، JSON-LD، فهرسة المباريات والأندية والوسوم =====================
function base_url() {
    static $b; if ($b) return $b;
    $c = trim((string)(site()['seo']['base_url'] ?? ''));
    if (preg_match('~^https?://[a-z0-9.-]+(:\d+)?(/[\w./-]*)?$~i', $c)) return $b = rtrim($c, '/') . '/';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $h = strtolower((string)($_SERVER['HTTP_HOST'] ?? '')); if (!preg_match('/^[a-z0-9.-]+(:\d+)?$/', $h)) $h = 'localhost';
    $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $b = ($https ? 'https' : 'http') . '://' . $h . $d . '/';
}
function abs_url($u) { return preg_match('~^https?://~i', (string)$u) ? (string)$u : base_url() . ltrim((string)$u, '/'); }
// الشعار المطلق كصورة نقطية (الـ SVG يُستبدل بأيقونة PNG المولّدة)
function logo_abs($u) { $p = img($u); return abs_url(strtolower(pathinfo((string)parse_url($p, PHP_URL_PATH), PATHINFO_EXTENSION)) === 'svg' ? 'icon.php?s=512' : $p); }
function seo_set($a) { $GLOBALS['SEO'] = array_merge($GLOBALS['SEO'] ?? [], $a); }
function match_id($x) { return substr(md5(($x['date'] ?? '') . ($x['home_team'] ?? '') . ($x['away_team'] ?? '')), 0, 8); }
function u_match($id) { return 'index.php?match=' . rawurlencode($id); }
function u_team($n) { return 'index.php?team=' . rawurlencode($n); }
function u_tag($t) { return 'index.php?tag=' . rawurlencode($t); }
function seo_name($S = null) { $S = $S ?: site(); return site_team($S)['name'] ?: 'WSSE'; }

// كل المباريات (المنتهية + الحالية + القادمة) من الأحدث للأقدم
function matches_all($S) {
    $o = [];
    foreach ($S['past'] as $x) { $x['status'] = 'finished'; $o[$x['id']] = $x; }
    $add = function ($m, $st) use (&$o) {
        if (($m['home_team'] ?? '') === '' && ($m['away_team'] ?? '') === '') return;
        $id = match_id($m); if (isset($o[$id])) return;
        $o[$id] = array_merge(['home_score' => null, 'away_score' => null, 'stadium' => '', 'city' => '', 'competition' => '', 'round' => '', 'time' => '', 'home_logo' => '', 'away_logo' => '', 'date' => ''], $m, ['id' => $id, 'status' => $st, 'no' => 0]);
    };
    $cs = $S['lv']['c']['st'];
    $add($S['match'], $cs === 'cancelled' ? 'cancelled' : (in_array($cs, ['first', 'half', 'second'], true) ? 'live' : 'upcoming'));
    foreach ((array)$S['fixtures'] as $f) if (is_array($f)) $add($f, 'upcoming');
    uasort($o, fn($a, $b) => strcmp(($b['date'] ?? '') . ($b['time'] ?? ''), ($a['date'] ?? '') . ($a['time'] ?? '')));
    return $o;
}
// كل الأندية بأسمائها وشعاراتها ومبارياتها
function teams_all($S) {
    $T = []; $tm = site_team($S);
    $put = function ($n, $l) use (&$T) { $n = trim((string)$n); if ($n === '') return null; $k = mb_strtolower($n); if (!isset($T[$k])) $T[$k] = ['name' => $n, 'logo' => '', 'ms' => []]; if ($l !== '' && $T[$k]['logo'] === '') $T[$k]['logo'] = $l; return $k; };
    if ($tm['name'] !== '') $put($tm['name'], $tm['logo']);
    foreach (matches_all($S) as $x) foreach (['home', 'away'] as $sd) { $k = $put($x[$sd . '_team'], (string)$x[$sd . '_logo']); if ($k) $T[$k]['ms'][] = $x; }
    return $T;
}
function team_stats($t) {
    $k = mb_strtolower($t['name']); $o = ['p' => 0, 'w' => 0, 'd' => 0, 'l' => 0, 'gf' => 0, 'ga' => 0];
    foreach ($t['ms'] as $x) {
        if (($x['status'] ?? '') !== 'finished') continue;
        $h = mb_strtolower(trim($x['home_team'])) === $k; $gf = (int)($h ? $x['home_score'] : $x['away_score']); $ga = (int)($h ? $x['away_score'] : $x['home_score']);
        $o['p']++; $o['gf'] += $gf; $o['ga'] += $ga; $o[$gf > $ga ? 'w' : ($gf < $ga ? 'l' : 'd')]++;
    }
    return $o;
}
// وسوم تلقائية: البطولة، الجولة، المدينة، الملعب
function tags_all($S) {
    $T = [];
    foreach (matches_all($S) as $x) foreach (['competition' => 'البطولة', 'round' => 'الجولة', 'city' => 'المدينة', 'stadium' => 'الملعب'] as $f => $kind) {
        $v = trim((string)($x[$f] ?? '')); if ($v === '') continue;
        $k = mb_strtolower($v); if (!isset($T[$k])) $T[$k] = ['text' => $v, 'kinds' => [], 'ms' => []];
        $T[$k]['kinds'][$kind] = 1; $T[$k]['ms'][$x['id']] = $x;
    }
    return $T;
}
function match_desc($x) {
    $fin = ($x['status'] ?? 'finished') === 'finished'; $h = $x['home_team']; $a = $x['away_team'];
    $p = ($fin ? 'نتيجة مباراة ' : 'موعد مباراة ') . $h . ' و' . $a;
    if (!empty($x['competition'])) $p .= ' ضمن ' . $x['competition'] . (!empty($x['round']) ? ' (' . $x['round'] . ')' : '');
    if (!empty($x['date'])) $p .= ' بتاريخ ' . $x['date'];
    if (!$fin && !empty($x['time'])) $p .= ' الساعة ' . $x['time'];
    if (!empty($x['stadium'])) $p .= ' على ملعب ' . $x['stadium'];
    if (!empty($x['city'])) $p .= ' بمدينة ' . $x['city'];
    if ($fin) $p .= '. انتهت المباراة بنتيجة ' . $h . ' ' . (int)$x['home_score'] . ' - ' . (int)$x['away_score'] . ' ' . $a;
    return $p . '.';
}

// ---------- JSON-LD ----------
function ld_team($n, $l) { $o = ['@type' => 'SportsTeam', 'name' => $n, 'sport' => 'Soccer']; $o['logo'] = logo_abs((string)$l); return $o; }
function ld_event($x) {
    $c = site()['seo']; $st = $x['status'] ?? 'finished';
    $ev = ['@type' => 'SportsEvent', 'name' => $x['home_team'] . ' ضد ' . $x['away_team'], 'sport' => 'Soccer', 'url' => abs_url(u_match($x['id'])), 'inLanguage' => 'ar',
        'eventStatus' => 'https://schema.org/' . ($st === 'cancelled' ? 'EventCancelled' : 'EventScheduled'), 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'homeTeam' => ld_team($x['home_team'], (string)$x['home_logo']), 'awayTeam' => ld_team($x['away_team'], (string)$x['away_logo']),
        'image' => [logo_abs((string)$x['home_logo']), logo_abs((string)$x['away_logo'])], 'description' => match_desc($x)];
    if (!empty($x['date'])) { $ts = strtotime(trim($x['date'] . ' ' . ($st !== 'finished' ? ($x['time'] ?? '') : ''))); if ($ts) $ev['startDate'] = ($st !== 'finished' && !empty($x['time'])) ? date('c', $ts) : $x['date']; }
    $place = trim((string)($x['stadium'] ?? '')) ?: (trim((string)($x['city'] ?? '')) ?: 'ملعب');
    $ev['location'] = ['@type' => 'Place', 'name' => $place, 'address' => ['@type' => 'PostalAddress', 'addressLocality' => (string)($x['city'] ?? ''), 'addressCountry' => ($c['country'] ?? '') ?: 'MA']];
    if (!empty($x['competition'])) $ev['superEvent'] = ['@type' => 'SportsEvent', 'name' => trim($x['competition'] . (!empty($x['round']) ? ' - ' . $x['round'] : ''))];
    return $ev;
}
function ld_bc($items) {
    $l = []; foreach ($items as $i => $it) $l[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => abs_url($it[1])];
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $l];
}
function ld_json($n) { return '<script type="application/ld+json">' . json_encode($n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n"; }

// وسوم <head> الكاملة: وصف، canonical، robots، Open Graph، Twitter، تحقق المحركات، RSS، JSON-LD
function seo_head($t) {
    $S = site(); $c = $S['seo']; $g = $GLOBALS['SEO'] ?? []; $name = seo_name($S); $b = base_url();
    $desc = trim((string)($g['desc'] ?? '')) ?: trim((string)$c['desc']);
    if ($desc === '') $desc = 'الموقع الرسمي لـ ' . $name . ': نتائج المباريات المباشرة، جدول الترتيب، أرشيف المباريات، توقعات الجماهير وصور الفريق.';
    $desc = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($desc))), 0, 300);
    $canon = (string)($g['canonical'] ?? $b); $img = (string)($g['image'] ?? '');
    if ($img === '') $img = trim((string)$c['og_image']) !== '' ? abs_url(img($c['og_image'])) : abs_url('icon.php?s=512');
    $big = !empty($g['big']); $noindex = !empty($c['noindex']) || !empty($g['noindex']);
    $kw = trim(implode('، ', array_filter([$name, trim((string)$c['keywords'])])));
    $o = '<meta name="description" content="' . e($desc) . '">' . "\n";
    if ($kw !== '') $o .= '<meta name="keywords" content="' . e($kw) . '">' . "\n";
    $o .= '<link rel="canonical" href="' . e($canon) . '">' . "\n" . '<link rel="alternate" hreflang="ar" href="' . e($canon) . '">' . "\n" . '<link rel="alternate" hreflang="x-default" href="' . e($canon) . '">' . "\n";
    $o .= '<meta name="robots" content="' . ($noindex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1') . '">' . "\n";
    foreach (['og:type' => $g['type'] ?? 'website', 'og:site_name' => $name, 'og:locale' => 'ar_AR', 'og:title' => $t, 'og:description' => $desc, 'og:url' => $canon, 'og:image' => $img, 'og:image:alt' => $g['image_alt'] ?? $name] as $k => $v) $o .= '<meta property="' . $k . '" content="' . e($v) . '">' . "\n";
    $o .= '<meta name="twitter:card" content="' . ($big ? 'summary_large_image' : 'summary') . '"><meta name="twitter:title" content="' . e($t) . '"><meta name="twitter:description" content="' . e($desc) . '"><meta name="twitter:image" content="' . e($img) . '">' . "\n";
    if (trim((string)$c['twitter']) !== '') $o .= '<meta name="twitter:site" content="@' . e($c['twitter']) . '">' . "\n";
    if (trim((string)$c['gverify']) !== '') $o .= '<meta name="google-site-verification" content="' . e($c['gverify']) . '">' . "\n";
    if (trim((string)$c['bverify']) !== '') $o .= '<meta name="msvalidate.01" content="' . e($c['bverify']) . '">' . "\n";
    $o .= '<link rel="alternate" type="application/rss+xml" title="' . e($name) . ' - آخر المباريات" href="rss.php">' . "\n";
    // بيانات منظّمة عامة: الفريق + الموقع
    $tm = site_team($S); $soc = array_values(array_filter(array_map('su', array_intersect_key($S['social'], array_flip(['facebook', 'twitter', 'instagram', 'youtube'])))));
    $org = ['@type' => 'SportsTeam', '@id' => $b . '#team', 'name' => $name, 'url' => $b, 'sport' => 'Soccer', 'logo' => logo_abs($tm['logo']), 'image' => logo_abs($tm['logo'])];
    if (trim((string)$c['org_alt']) !== '') $org['alternateName'] = array_values(array_filter(array_map('trim', preg_split('/[,،]/u', $c['org_alt']))));
    if ($soc) $org['sameAs'] = $soc;
    $graph = [$org, ['@type' => 'WebSite', '@id' => $b . '#site', 'url' => $b, 'name' => $name, 'inLanguage' => 'ar', 'publisher' => ['@id' => $b . '#team']]];
    foreach ((array)($g['ld'] ?? []) as $n) $graph[] = $n;
    return $o . ld_json(['@context' => 'https://schema.org', '@graph' => $graph]);
}

// مباريات النادي ضد الفريق الرئيسي فقط (وللفريق الرئيسي نفسه: كل مبارياته)
function team_vs($S, $t) {
    $me = mb_strtolower(site_team($S)['name']); $k = mb_strtolower($t['name']);
    if ($me === '' || $me === $k) return $t['ms'];
    return array_values(array_filter($t['ms'], fn($x) => mb_strtolower(trim($x['home_team'])) === $me || mb_strtolower(trim($x['away_team'])) === $me));
}
