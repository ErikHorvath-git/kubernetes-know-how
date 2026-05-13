<?php
// VARIANT B — API ktoré loguje každý request do .log súboru
// FS interakcia: zapisovanie (file_put_contents APPEND), čítanie (file)

$appName  = getenv('APP_NAME')  ?: 'Request Logger API';
$logLevel = getenv('LOG_LEVEL') ?: 'INFO';
$logDir   = '/var/www/html/data/logs';
$logFile  = $logDir . '/app.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

// Zaloguj každý request
$line = sprintf("[%s] [%s] %s %s %s — UA: %s\n",
    date('Y-m-d H:i:s'),
    $logLevel,
    $_SERVER['REMOTE_ADDR']     ?? '-',
    $_SERVER['REQUEST_METHOD']  ?? '-',
    $_SERVER['REQUEST_URI']     ?? '-',
    substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 60)
);
file_put_contents($logFile, $line, FILE_APPEND);

// Vyčistenie logov cez ?clear=1
if (isset($_GET['clear'])) {
    file_put_contents($logFile, '');
    header('Location: /');
    exit;
}

// Zobraz posledných N záznamov
$recent = file_exists($logFile)
    ? array_slice(file($logFile), -30)
    : [];
$count = file_exists($logFile) ? count(file($logFile)) : 0;
?>
<!doctype html>
<html lang="sk"><head><meta charset="utf-8">
<title><?= htmlspecialchars($appName) ?></title>
<style>body{font-family:sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem}
pre{background:#1e1e1e;color:#dcdcdc;padding:1rem;border-radius:6px;overflow-x:auto;
font-size:.85em;white-space:pre-wrap}</style></head><body>
<h1><?= htmlspecialchars($appName) ?></h1>
<p>Log level: <b><?= htmlspecialchars($logLevel) ?></b> ·
   Záznamov: <b><?= $count ?></b> ·
   <a href="/?clear=1">vyčistiť</a></p>

<h2>Posledných <?= count($recent) ?> requestov</h2>
<pre><?= htmlspecialchars(implode('', $recent)) ?: '(prázdne — refreshni stránku)' ?></pre>

<h2>Vyskúšaj ďalšie endpointy</h2>
<ul>
  <li><a href="/api/users">/api/users</a></li>
  <li><a href="/api/products?id=42">/api/products?id=42</a></li>
  <li><a href="/health">/health</a></li>
</ul>
</body></html>
