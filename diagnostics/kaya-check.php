<?php
/**
 * kaya-check.php — تشخیص یک‌بارهٔ لایهٔ شبکه از داخل همین هاست.
 *
 * چطور استفاده شود:
 *   1) این فایل را در ریشهٔ سایت (httpdocs یا public_html) آپلود کنید.
 *   2) در مرورگر باز کنید:  https://<دامنه>/kaya-check.php?k=NOMREH-7k2q
 *   3) اسکرین‌شات بگیرید، بعد فایل را پاک کنید.
 *
 * نکات امنیتی و منابع:
 *   - فقط با توکن درست کار می‌کند.
 *   - حداکثر یک درخواست خروجی به مقصد کایا می‌زند (سقف مشترک سرور را نمی‌شکند).
 *   - هیچ کلید/رمز/فایلی را نمی‌خواند، چاپ نمی‌کند و جایی نمی‌نویسد.
 *   - هیچ چیزی را تغییر نمی‌دهد. فقط می‌خواند.
 */

$TOKEN = 'NOMREH-7k2q';

if (!isset($_GET['k']) || !is_string($_GET['k']) || trim($_GET['k']) !== $TOKEN) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not found';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$HOST    = 'kayaai.ir';
$PORT    = 443;
$TIMEOUT = 8;

function line($label, $value)
{
    printf("%-22s %s\n", $label, $value);
}

function ms($t0)
{
    return round((microtime(true) - $t0) * 1000) . 'ms';
}

echo "=== kaya-check ===\n";
line('زمان', gmdate('Y-m-d H:i:s') . ' UTC');
line('PHP', PHP_VERSION);
line('سرور', isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '?');
line('آدرس دیده‌شده', isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '?');
echo "\n";

/* ---------- 1) DNS ---------- */
echo "--- 1) DNS ---\n";
$t0 = microtime(true);
$ips = @gethostbynamel($HOST);
if ($ips) {
    line('A record', implode(', ', $ips) . '  (' . ms($t0) . ')');
} else {
    line('A record', 'ناموفق (' . ms($t0) . ') — DNS');
}
echo "\n";

/* ---------- 2) TCP ---------- */
$tcpOk = false;
$errno = 0;
$errstr = '';
echo "--- 2) TCP ---\n";
$t0 = microtime(true);
$fp = @stream_socket_client(
    'tcp://' . $HOST . ':' . $PORT,
    $errno,
    $errstr,
    $TIMEOUT,
    STREAM_CLIENT_CONNECT
);
if ($fp) {
    $tcpOk = true;
    line('tcp ' . $PORT, 'OPEN (' . ms($t0) . ')');
    @fclose($fp);
} else {
    line('tcp ' . $PORT, 'FAILED (' . ms($t0) . ') errno=' . $errno . ' ' . $errstr);
}
echo "\n";

if (!$tcpOk) {
    echo "نتیجه: لایهٔ TCP بسته است. هیچ درخواستی فرستاده نشد.\n";
    echo "این لایه فقط با تغییر آدرس مبدأ/هاستینگ یا باز کردن فایروال کایا باز می‌شود.\n";
    exit;
}

/* ---------- 3) TLS ---------- */
$tlsOk = false;
echo "--- 3) TLS ---\n";
$t0 = microtime(true);
$ctx = stream_context_create(array(
    'ssl' => array(
        'verify_peer'       => false,
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
        'SNI_enabled'       => true,
        'peer_name'         => $HOST,
    ),
));
$ssl = @stream_socket_client(
    'ssl://' . $HOST . ':' . $PORT,
    $errno,
    $errstr,
    $TIMEOUT,
    STREAM_CLIENT_CONNECT,
    $ctx
);
if ($ssl) {
    $tlsOk = true;
    line('handshake', 'OK (' . ms($t0) . ')');
    $meta = stream_get_meta_data($ssl);
    $crypto = isset($meta['crypto']) ? $meta['crypto'] : array();
    line('TLS', (isset($crypto['protocol']) ? $crypto['protocol'] : '?') . ' / ' . (isset($crypto['cipher_name']) ? $crypto['cipher_name'] : '?'));
    @fclose($ssl);
} else {
    line('handshake', 'FAILED (' . ms($t0) . ') errno=' . $errno . ' ' . $errstr);
    echo "نتیجه: سوکت باز شد ولی دست‌دادن TLS قطع شد — همان الگوی فیلتر در لایهٔ شبکه.\n";
    echo "اگر همین را از یک سرور دیگر هم ببینید، مقصد/مسیر فیلتر شده است.\n";
    exit;
}
echo "\n";

/* ---------- 4) یک درخواست HTTP (تنها درخواست خروجی) ---------- */
echo "--- 4) HTTP GET /api/models  (یک درخواست) ---\n";
$t0 = microtime(true);
$body = '';
$status = 0;
$ctx2 = stream_context_create(array(
    'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $HOST),
    'http' => array(
        'method'        => 'GET',
        'timeout'       => $TIMEOUT,
        'ignore_errors' => true,
        'header'        => "Accept: application/json\r\nUser-Agent: nomreh-kaya-check/1\r\n",
    ),
));
$raw = @file_get_contents('https://' . $HOST . '/api/models', false, $ctx2);
if (isset($http_response_header) && is_array($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $status = (int) $m[1];
        }
    }
}
line('HTTP', ($status ? $status : 'NO RESPONSE') . ' (' . ms($t0) . ')');
if (is_string($raw) && $raw !== '') {
    $len = strlen($raw);
    line('body length', $len . ' bytes');
    line('body head', substr(preg_replace('/\s+/', ' ', $raw), 0, 220));
    $j = json_decode($raw, true);
    if (is_array($j)) {
        $ids = array();
        foreach ($j as $item) {
            if (is_array($item) && isset($item['id'])) {
                $ids[] = $item['id'];
            }
        }
        if ($ids) {
            line('models', count($ids) . ' → ' . implode(', ', array_slice($ids, 0, 5)));
        }
    }
} else {
    line('body', 'خالی');
}
echo "\n";

/* ---------- 5) نتیجه ---------- */
echo "--- نتیجه ---\n";
if ($status >= 200 && $status < 300) {
    echo "مسیر باز است، کاتالوگ جواب داد. مشکل شبکه/آدرس مبدأ نیست؛\n";
    echo "اگر برنامه کار نمی‌کند، لایهٔ بعدی است: کلید، مدل، یا حجم بدنه.\n";
} elseif ($status == 0) {
    echo "سوکت و TLS سالم بودند ولی پاسخ HTTP نرسید.\n";
    echo "یعنی مقصد درخواست را می‌گیرد و رها می‌کند — لایهٔ سرور/فایروال کایا.\n";
} else {
    echo "پاسخ HTTP " . $status . " رسید. مسیر باز است. کد خطا را برای تفسیر بفرستید.\n";
}

echo "\n(بعد از گرفتن اسکرین‌شات این فایل را از هاست پاک کنید)\n";
