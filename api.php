<?php
require __DIR__ . '/inc.php';
session_write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$a = (string)($_GET['a'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $o = parse_url((string)($_SERVER['HTTP_ORIGIN'] ?? ''), PHP_URL_HOST);
    if ($o && strcasecmp($o, explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) !== 0) { http_response_code(403); exit('{}'); }
    if (!rl('a' . ip(), 120, 600)) { http_response_code(429); exit('{}'); }
    $k = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['k'] ?? '')));
    switch ($a) {
        case 'hit':
            $p = basename((string)($_POST['p'] ?? ''), '.php');
            if (!in_array($p, ['index', 'contact', 'advertise'], true)) $p = 'index';
            if (empty($_POST['w'])) st_hit($p, !empty($_POST['f']), (string)($_POST['r'] ?? ''), (string)($_POST['u'] ?? ''));
            break;
        case 'click': if ($k !== '' && strlen($k) <= 30) st_click($k); break;
        case 'ev': if (in_array($k, ['install', 'standalone', 'notif_granted', 'notif_denied'], true)) st_ev($k); break;
        case 'sub': case 'unsub':
            $j = json_decode((string)file_get_contents('php://input'), true); $ep = (string)($j['endpoint'] ?? '');
            // السماح فقط بخوادم الإشعارات المعروفة (منع SSRF)
            if (!preg_match('~^https://([a-z0-9-]+\.)*(googleapis\.com|push\.services\.mozilla\.com|push\.apple\.com|notify\.windows\.com)/[^\s"<>]{5,500}$~i', $ep)) break;
            $dev = (ua_info((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?? [])[0] ?? 'desktop';
            jup('push.json', function ($p) use ($a, $ep, $dev) {
                $p['subs'] = array_values(array_filter($p['subs'] ?? [], fn($s) => $s['endpoint'] !== $ep));
                if ($a === 'sub' && count($p['subs']) < 20000) $p['subs'][] = ['endpoint' => $ep, 'ts' => time(), 'dev' => $dev];
                return $p;
            }, []);
            break;
    }
    echo '{"ok":1}'; exit;
}

if ($a === 'cron') {
    if (!hash_equals(cron_key(), (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('{}'); }
    site(); echo '{"ok":1,"t":' . time() . '}'; exit; // site() ينفّذ الانتقال التلقائي وتذكير المباراة
}
$s = site(); $L = $s['lv']['L']; $c = $s['lv']['c']; $m = $s['match']; $last = null;
if ($L['events']) { $v = end($L['events']); [$t, $d, $r] = ev_txt($v); $last = ['t' => $t . ' ' . $v['min'], 'x' => trim($d . ($v['team'] ? ' (' . ($v['team'] === 'home' ? $m['home_team'] : $m['away_team']) . ')' : '')), 'ts' => (int)($v['ts'] ?? 0)]; }
$ann = jget('push.json')['ann'] ?? null;
echo json_encode(['st' => $c['st'], 'pz' => $c['pz'], 'per' => $c['per'], 'e' => $c['e'], 'run' => $c['run'], 'half' => $c['half'], 'lim' => $c['lim'], 'n' => count($L['events']), 'rev' => (int)($L['rev'] ?? 0), 'hs' => (int)$s['home_score'], 'as' => (int)$s['away_score'], 'home' => $m['home_team'], 'away' => $m['away_team'], 'last' => $last, 'ann' => $ann, 'rm' => !empty($s['reminder']['on']) ? (int)$s['reminder']['min'] : 0, 'mk' => md5($m['date'] . $m['time'] . $m['home_team'] . $m['away_team']), 'logo' => 'icon.php?s=192', 'dk' => ($ks = strtotime(trim($m['date'] . ' ' . $m['time']))) ? $ks - time() : null], JSON_UNESCAPED_UNICODE);
