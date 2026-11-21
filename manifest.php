<?php
define('NOSESS', 1);
require __DIR__ . '/inc.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$S = site(); $v = substr(md5($S['logo']), 0, 6);
$hc = preg_match('/^#[0-9a-f]{6}$/i', $S['bg_color']) ? $S['bg_color'] : '#991b1b';
$ic = fn($s, $p) => ['src' => 'icon.php?s=' . $s . ($p === 'maskable' ? '&m=1' : '') . '&v=' . $v, 'sizes' => $s . 'x' . $s, 'type' => 'image/png', 'purpose' => $p];
echo json_encode(['id' => 'index.php', 'name' => 'وفاق أزغنغان WSSE', 'short_name' => 'WSSE', 'start_url' => 'index.php', 'scope' => './', 'display' => 'standalone', 'orientation' => 'portrait', 'dir' => 'rtl', 'lang' => 'ar', 'background_color' => $hc, 'theme_color' => $hc, 'categories' => ['sports'],
    'icons' => [$ic(192, 'any'), $ic(512, 'any'), $ic(512, 'maskable')],
    'shortcuts' => [['name' => 'الصفحة الرئيسية والبث الحي', 'short_name' => 'مباشر', 'url' => 'index.php'], ['name' => 'توقعات الجماهير', 'short_name' => 'توقعات', 'url' => 'index.php?matchpredictions=1'], ['name' => 'صوت الجماهير', 'short_name' => 'تصويت', 'url' => 'index.php?fanvote=1'], ['name' => 'ألبوم الصور', 'short_name' => 'الألبوم', 'url' => 'index.php?album=1'], ['name' => 'أرشيف المباريات', 'short_name' => 'الأرشيف', 'url' => 'index.php?archive=1']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
