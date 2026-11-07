<?php
/**
 * plesk-selfcheck.php — عکس لحظه‌ای از برنامه، بدون SSH.
 *
 * آپلود: یک زیردامنهٔ تازه بسازید (مثلاً diag.arounidea.com) که Document Root آن
 *        یک پوشهٔ خالی باشد، این فایل را همان‌جا بگذارید و باز کنید:
 *        https://diag.arounidea.com/plesk-selfcheck.php?k=NOMREH-7k2q
 *
 * چه می‌کند: فقط می‌خواند. یک درخواست شبکه به کایا (اختیاری)، هیچ نوشتن، هیچ اجرا،
 *            هیچ کلید/رمزی چاپ نمی‌شود.
 */

$TOKEN = 'NOMREH-7k2q';
if (!isset($_GET['k']) || trim((string) $_GET['k']) !== $TOKEN) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found';
    exit;
}
header('Content-Type: text/plain; charset=utf-8');

function row($k, $v) { printf("%-26s %s\n", $k, $v); }
function say($t) { echo "\n--- $t ---\n"; }

row('time', gmdate('Y-m-d H:i:s') . ' UTC');
row('php', PHP_VERSION);
row('server_addr', isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '?');
row('doc_root', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '?');
row('curl_ext', function_exists('curl_init') ? 'yes' : 'no');
row('openssl_ext', extension_loaded('openssl') ? 'yes' : 'no');

/* ---------- DNS ---------- */
say('DNS');
$t = microtime(true);
$dns = @gethostbynamel('kayaai.ir');
row('kayaai.ir', $dns ? implode(',', $dns) . ' (' . round((microtime(true)-$t)*1000) . 'ms)' : 'FAIL');

/* ---------- TCP ---------- */
say('TCP');
foreach (array(443, 80) as $p) {
    $t = microtime(true);
    $e = 0; $s = '';
    $fp = @stream_socket_client('tcp://kayaai.ir:' . $p, $e, $s, 3);
    if ($fp) { row('tcp ' . $p, 'OPEN (' . round((microtime(true)-$t)*1000) . 'ms)'); fclose($fp); }
    else     { row('tcp ' . $p, 'FAIL (' . round((microtime(true)-$t)*1000) . 'ms) errno=' . $e); }
}

/* ---------- HTTP واقعی از طریق curl ---------- */
say('HTTP (curl, one request)');
if (function_exists('curl_init')) {
    $t = microtime(true);
    $ch = curl_init('https://kayaai.ir/api/models');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER     => array('Accept: application/json'),
    ));
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    row('http_code', $code ? $code : 'NO RESPONSE');
    row('time', round((microtime(true)-$t)*1000) . 'ms');
    row('curl_error', $cerr ? $cerr : '-');
    if (is_string($body)) row('body_len', strlen($body));
} else {
    row('curl', 'not available');
}

/* ---------- پیدا کردن برنامه ---------- */
say('APP');
$candidates = array();
$root = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : '';
$up = dirname($root);
foreach (array($root, $up, dirname($up), dirname(dirname($up))) as $d) {
    if ($d && $d !== '/' && @is_file($d . '/package.json')) $candidates[] = $d;
}
if ($candidates) {
    $app = $candidates[0];
    row('app_root', $app);
    $pj = json_decode(@file_get_contents($app . '/package.json'), true);
    row('name', isset($pj['name']) ? $pj['name'] : '?');
    row('version', isset($pj['version']) ? $pj['version'] : '?');
    $scripts = isset($pj['scripts']) && is_array($pj['scripts']) ? $pj['scripts'] : array();
    row('script_count', count($scripts));
    $want = array('build:plesk','restart:plesk','setup:plesk','kaya:test','kaya:selftest','diagnose','start');
    $have = array();
    $miss = array();
    foreach ($want as $w) { if (isset($scripts[$w])) $have[] = $w; else $miss[] = $w; }
    row('scripts_ok', $have ? implode(',', $have) : '-');
    row('scripts_missing', $miss ? implode(',', $miss) : '-');
    row('.next/BUILD_ID', @is_file($app . '/.next/BUILD_ID') ? @file_get_contents($app . '/.next/BUILD_ID') : 'MISSING');
    row('.next mtime', @is_dir($app . '/.next') ? gmdate('Y-m-d H:i', @filemtime($app . '/.next')) . ' UTC' : '-');
    row('node_modules', @is_dir($app . '/node_modules') ? 'present' : 'MISSING');
    row('.env.local', @is_file($app . '/.env.local') ? 'present' : 'absent');
    row('data/ dir', @is_dir($app . '/data') ? 'present' : 'absent');
    if (@is_dir($app . '/data')) {
        $names = array_values(array_diff(@scandir($app . '/data') ?: array(), array('.', '..')));
        row('data/ files', implode(', ', array_slice($names, 0, 12)));
    }
    row('providers.ts', @is_file($app . '/lib/server/providers.ts') ? 'present' : 'MISSING');
} else {
    row('app_root', 'NOT FOUND (package.json در سه سطح بالا نیست)');
}

/* ---------- نتیجه ---------- */
say('نتیجه');
echo "اگر tcp 443 FAIL شد: همان مشکل قبلی است، این زیردامنه هم فرقی نمی‌کند.\n";
echo "اگر tcp 443 OPEN شد: مشکل شبکه نیست، لایهٔ برنامه/تنظیمات است — لاگ بالا را بفرست.\n";
echo "\n(بعد از اسکرین‌شات این فایل را پاک کنید)\n";
