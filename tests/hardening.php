<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$source = dirname(__DIR__);
$runtime = sys_get_temp_dir() . '/nexapress-hardening-' . bin2hex(random_bytes(8));
mkdir($runtime . '/config', 0700, true); mkdir($runtime . '/storage/logs', 0700, true);
define('BASE_PATH', $runtime);
require $source . '/app/Core/Debug.php';
$checks = 0;
function verify(bool $value, string $label): void { global $checks; if (!$value) { throw new RuntimeException('FAIL: ' . $label); } $checks++; }
try {
    verify((require $source . '/config/debug.php')['enabled'] === false, 'debug default is disabled');
    file_put_contents($runtime . '/config/debug.php', '<?php return ["enabled"=>true];');
    app\Core\Debug::log('Request', ['uri'=>'https://user:secret@example.test/post/a?token=query-secret#fragment', 'password'=>'private-value', 'nested'=>['authorization'=>'Bearer secret'], 'params'=>['route-secret'], 'method'=>'GET']);
    $log = file_get_contents($runtime . '/storage/logs/debug.log');
    foreach (['query-secret','private-value','route-secret','Bearer secret','user:secret','fragment'] as $secret) { verify(!str_contains($log, $secret), 'sensitive value not persisted'); }
    verify(str_contains($log, '/post/a') && str_contains($log, 'GET'), 'useful context remains');
    file_put_contents($runtime . '/storage/logs/debug.log', str_repeat('x',2097152));
    app\Core\Debug::log('After rotation');
    verify(filesize($runtime . '/storage/logs/debug.log') < 2097152, 'oversized log rotated');
    for ($i=0;$i<4;$i++) { $path=$runtime.'/storage/logs/debug-20000101-'.$i.'.log';file_put_contents($path,'old');touch($path,time()-20*86400); }
    app\Core\Debug::log('Cleanup');
    verify(count(glob($runtime.'/storage/logs/debug-*.log'))<=2, 'old archives bounded');
    $css=file_get_contents($source.'/public/assets/css/style.css');
    verify(!preg_match('/admin|login\.css/',$css), 'public CSS excludes admin and login imports');
    foreach (['.htaccess','public/.htaccess'] as $relative) { $rules=file_get_contents($source.'/'.$relative);verify(str_contains($rules,'Options -Indexes')&&str_contains($rules,'storage|extensions'), 'private folders and indexes protected'); }
    echo "Core hardening checks: $checks passed\n";
} finally {
    $resolved = realpath($runtime);
    if ($resolved && str_starts_with($resolved, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)) {
        foreach (glob($runtime.'/storage/logs/*') ?: [] as $path) { if (is_file($path) && !is_link($path)) { unlink($path); } }
        unlink($runtime.'/config/debug.php'); rmdir($runtime.'/config');rmdir($runtime.'/storage/logs');rmdir($runtime.'/storage');rmdir($runtime);
    }
}
