<?php
define('NOSESS', 1);
require __DIR__ . '/inc.php';
header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: public, max-age=3600');
$b = base_url(); $p = rtrim((string)parse_url($b, PHP_URL_PATH), '/') . '/'; $off = !empty(site()['seo']['noindex']);
echo "User-agent: *\n", $off ? "Disallow: /\n" : "Allow: /\nDisallow: {$p}admin.php\nDisallow: {$p}api.php\nDisallow: {$p}data/\nDisallow: {$p}error.php\nDisallow: {$p}*?pwadebug\nDisallow: {$p}*?rwadebug\n", "\n";
if (!$off) echo "Sitemap: {$b}sitemap.xml\n";
