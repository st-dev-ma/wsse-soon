<?php
// خريطة الموقع الديناميكية (مع خريطة الصور): الرئيسية، الأرشيف، الألبوم، المباريات، الأندية، الوسوم
define('NOSESS', 1);
require __DIR__ . '/inc.php';
header('Content-Type: application/xml; charset=utf-8'); header('Cache-Control: public, max-age=900');
$S = site(); $b = base_url(); $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$mt = @filemtime(DD . 'data.json') ?: time(); $U = [];
$add = function ($rel, $lm, $fr, $pr, $im = []) use (&$U, $b) { $U[] = [abs_url($rel), $lm, $fr, $pr, $im]; };
if (empty($S['seo']['noindex'])) {
    $name = seo_name($S); $ms = matches_all($S); $T = teams_all($S); $G = $S['gal'];
    $add('index.php', date('c', $mt), 'hourly', '1.0', [[logo_abs(site_team($S)['logo']), $name, 'شعار ' . $name]]);
    $add('index.php?archive=1', date('c', $mt), 'daily', '0.8');
    $add('index.php?tags=1', date('c', $mt), 'weekly', '0.6');
    if ($G) $add('index.php?album=1', date('c', (int)($G[0]['ts'] ?? $mt)), 'weekly', '0.6', array_map(fn($g) => [abs_url(img($g['url'])), (string)($g['caption'] ?? ''), (string)($g['caption'] ?? '') ?: 'صورة من ألبوم ' . $name], array_slice($G, 0, 1000)));
    $add('index.php?lineup=1', date('c', $mt), 'daily', '0.6');
    $add('index.php?fanvote=1', date('c', $mt), 'daily', '0.4');
    $add('index.php?matchpredictions=1', date('c', $mt), 'daily', '0.5');
    $add('contact.php', date('c', $mt), 'yearly', '0.2'); $add('advertise.php', date('c', $mt), 'yearly', '0.2');
    foreach ($ms as $m) $add(u_match($m['id']), ($m['date'] ?? '') !== '' ? date('c', strtotime($m['date']) ?: $mt) : date('c', $mt), $m['status'] === 'finished' ? 'monthly' : 'daily', $m['status'] === 'finished' ? '0.7' : '0.9', [[logo_abs((string)$m['home_logo']), $m['home_team'], 'شعار ' . $m['home_team']], [logo_abs((string)$m['away_logo']), $m['away_team'], 'شعار ' . $m['away_team']]]);
    foreach ($T as $t) $add(u_team($t['name']), date('c', $mt), 'weekly', '0.7', [[logo_abs((string)$t['logo']), $t['name'], 'شعار ' . $t['name']]]);
    foreach (tags_all($S) as $tg) $add(u_tag($tg['text']), date('c', $mt), 'weekly', '0.5');
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach (array_slice($U, 0, 45000) as [$loc, $lm, $fr, $pr, $im]) {
    echo '<url><loc>' . $x($loc) . '</loc><lastmod>' . $x($lm) . '</lastmod><changefreq>' . $fr . '</changefreq><priority>' . $pr . '</priority>';
    foreach ($im as [$iu, $ic, $it]) echo '<image:image><image:loc>' . $x($iu) . '</image:loc>' . ($it !== '' ? '<image:title>' . $x($it) . '</image:title>' : '') . ($ic !== '' ? '<image:caption>' . $x($ic) . '</image:caption>' : '') . '</image:image>';
    echo "</url>\n";
}
echo '</urlset>';
