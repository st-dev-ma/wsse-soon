<?php
// يولّد أيقونة PNG (192 / 512 / maskable) من شعار الموقع؛ بديل نقي بدون GD إن لزم
define('NOSESS', 1);
require __DIR__ . '/inc.php';
$z = ((int)($_GET['s'] ?? 192)) === 512 ? 512 : 192; $mk = !empty($_GET['m']); $fr = $mk ? .6 : .8;
$S = site(); $lg = img($S['logo']);
$hc = preg_match('/^#[0-9a-f]{6}$/i', $S['bg_color']) ? $S['bg_color'] : '#991b1b';
[$r, $g, $b] = sscanf($hc, '#%02x%02x%02x');
$dir = __DIR__ . '/WSSE_files/icons'; $f = $dir . '/i' . $z . ($mk ? 'm' : '') . '-' . substr(md5($lg . $hc), 0, 8) . '.png';
header('Cache-Control: public, max-age=86400'); header_remove('Pragma'); header_remove('Expires');

function shield($z, $k) { $o = []; foreach ([[.5, .12], [.85, .25], [.85, .55], [.5, .9], [.15, .55], [.15, .25]] as $p) $o[] = [(.5 + ($p[0] - .5) * $k) * $z, (.5 + ($p[1] - .5) * $k) * $z]; return $o; }
function png_fallback($z, $rgb, $k) {
    $pts = shield($z, $k); $n = count($pts); $raw = ''; $bg = chr($rgb[0]) . chr($rgb[1]) . chr($rgb[2]);
    for ($y = 0; $y < $z; $y++) {
        $raw .= "\0";
        for ($x = 0; $x < $z; $x++) {
            $in = false;
            for ($i = 0, $j = $n - 1; $i < $n; $j = $i++)
                if ((($pts[$i][1] > $y) !== ($pts[$j][1] > $y)) && ($x < ($pts[$j][0] - $pts[$i][0]) * ($y - $pts[$i][1]) / ($pts[$j][1] - $pts[$i][1]) + $pts[$i][0])) $in = !$in;
            $raw .= $in ? "\xff\xff\xff" : $bg;
        }
    }
    $ch = fn($t, $d) => pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d));
    return "\x89PNG\r\n\x1a\n" . $ch('IHDR', pack('NNCCCCC', $z, $z, 8, 2, 0, 0, 0)) . $ch('IDAT', gzcompress($raw, 6)) . $ch('IEND', '');
}

if (!is_file($f)) {
    $png = null;
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor($z, $z); imagealphablending($im, true);
        imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
        $src = null; $p = __DIR__ . '/' . $lg;
        if (!preg_match('~^https?://~i', $lg) && is_file($p) && strtolower(pathinfo($p, PATHINFO_EXTENSION)) !== 'svg') $src = @imagecreatefromstring((string)file_get_contents($p));
        if ($src) {
            $w = imagesx($src); $h = imagesy($src); $m = (int)($z * $fr); $k = min($m / $w, $m / $h); $nw = max(1, (int)($w * $k)); $nh = max(1, (int)($h * $k));
            imagecopyresampled($im, $src, (int)(($z - $nw) / 2), (int)(($z - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        } else {
            $pts = []; foreach (shield($z, $fr / .8) as $q) { $pts[] = (int)$q[0]; $pts[] = (int)$q[1]; }
            $wh = imagecolorallocate($im, 255, 255, 255);
            PHP_VERSION_ID >= 80100 ? imagefilledpolygon($im, $pts, $wh) : imagefilledpolygon($im, $pts, 6, $wh);
        }
        ob_start(); imagepng($im); $png = ob_get_clean();
    } elseif (function_exists('gzcompress')) $png = png_fallback($z, [$r, $g, $b], $fr / .8);
    if ($png === null) { header('Location: ' . $lg); exit; }
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (is_dir($dir) && is_writable($dir)) @file_put_contents($f, $png);
    header('Content-Type: image/png'); echo $png; exit;
}
header('Content-Type: image/png'); readfile($f);
