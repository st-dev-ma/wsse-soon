<?php
// خلاصة RSS: آخر النتائج والمباريات القادمة وأحدث الصور
define('NOSESS', 1);
require __DIR__ . '/inc.php';
header('Content-Type: application/rss+xml; charset=utf-8'); header('Cache-Control: public, max-age=600');
$S = site(); $b = base_url(); $name = seo_name($S); $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$cd = fn($s) => '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$s) . ']]>';
$items = [];
if (empty($S['seo']['noindex'])) {
    foreach (array_slice(matches_all($S), 0, 40) as $m) {
        $fin = $m['status'] === 'finished'; $ts = strtotime(trim(($m['date'] ?? '') . ' ' . ($m['time'] ?? '')) ?: 'now') ?: time();
        $t = $fin ? $m['home_team'] . ' ' . (int)$m['home_score'] . ' - ' . (int)$m['away_score'] . ' ' . $m['away_team'] : ($m['status'] === 'live' ? 'مباشر: ' : 'قريباً: ') . $m['home_team'] . ' × ' . $m['away_team'];
        $d = '<p><img src="' . e(logo_abs((string)$m['home_logo'])) . '" alt="شعار ' . e($m['home_team']) . '" width="48"> <img src="' . e(logo_abs((string)$m['away_logo'])) . '" alt="شعار ' . e($m['away_team']) . '" width="48"></p><p>' . e(match_desc($m)) . '</p>';
        $cats = array_filter([$m['competition'] ?? '', $m['city'] ?? '', $m['stadium'] ?? '', $m['home_team'], $m['away_team']]);
        $items[] = [$ts, $t, abs_url(u_match($m['id'])), abs_url(u_match($m['id'])), $d, $cats];
    }
    foreach (array_slice($S['gal'], 0, 10) as $g) { $ts = (int)($g['ts'] ?? 0) ?: 0; if (!$ts) continue; $u = abs_url(img($g['url'])); $c = (string)($g['caption'] ?? '');
        $items[] = [$ts, 'صورة جديدة' . ($c !== '' ? ': ' . $c : ''), abs_url('index.php?album=1'), $u, '<p><img src="' . e($u) . '" alt="' . e($c ?: 'صورة من ألبوم ' . $name) . '" width="480"></p>', []]; }
    usort($items, fn($a, $z) => $z[0] <=> $a[0]);
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n";
echo '<title>' . $x($name . ' - نتائج وأخبار المباريات') . '</title><link>' . $x($b) . '</link><description>' . $x('آخر نتائج ومباريات ' . $name . ' وصور الفريق') . '</description><language>ar</language><lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate><atom:link href="' . $x($b . 'rss.php') . '" rel="self" type="application/rss+xml"/>';
echo '<image><url>' . $x(logo_abs(site_team($S)['logo'])) . '</url><title>' . $x($name) . '</title><link>' . $x($b) . '</link></image>' . "\n";
foreach ($items as [$ts, $t, $l, $g, $d, $cats]) {
    echo '<item><title>' . $x($t) . '</title><link>' . $x($l) . '</link><guid isPermaLink="' . ($g === $l ? 'true' : 'false') . '">' . $x($g) . '</guid><pubDate>' . date(DATE_RSS, $ts) . '</pubDate><description>' . $cd($d) . '</description>';
    foreach (array_unique($cats) as $c) echo '<category>' . $x($c) . '</category>';
    echo "</item>\n";
}
echo '</channel></rss>';
