<?php
// VARIANT C — Aplikácia, ktorá pri štarte načíta súbor pripravený InitContainerom
// FS interakcia: čítanie (file_get_contents), zapisovanie (visit count)
//
// DOLEŽITÉ: Tento variant POUŽÍVA InitContainer, ktorý vygeneruje
//           welcome.txt z ConfigMap-u pred štartom Apache.

$appName     = getenv('APP_NAME') ?: 'Template Reader';
$dataDir     = '/var/www/html/data';
$welcomeFile = $dataDir . '/welcome.txt';
$counterFile = $dataDir . '/visits.txt';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}

// Inkrementuj počítadlo návštev
$visits = (int) @file_get_contents($counterFile);
$visits++;
file_put_contents($counterFile, (string) $visits);

// Načítaj šablónu pripravenú InitContainerom
$welcome = file_exists($welcomeFile)
    ? file_get_contents($welcomeFile)
    : '(welcome.txt neexistuje — InitContainer ho mal vytvoriť)';
?>
<!doctype html>
<html lang="sk"><head><meta charset="utf-8">
<title><?= htmlspecialchars($appName) ?></title>
<style>body{font-family:sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem}
.welcome{background:#fff8dc;border-left:4px solid #ffa500;padding:1rem;border-radius:4px;
white-space:pre-wrap;font-family:monospace}
.box{background:#f4f4f4;padding:1rem;border-radius:6px;margin:1rem 0}</style></head>
<body>
<h1><?= htmlspecialchars($appName) ?></h1>
<p>Pod: <b><?= gethostname() ?></b> · Návštev: <b><?= $visits ?></b></p>

<h2>Šablóna z InitContainer-u</h2>
<div class="welcome"><?= htmlspecialchars($welcome) ?></div>

<div class="box">
  <h2>Súbory v PVC</h2>
  <ul>
  <?php foreach (array_diff(scandir($dataDir) ?: [], ['.','..']) as $f): ?>
    <li><?= htmlspecialchars($f) ?>
        (<?= filesize($dataDir.'/'.$f) ?> B)</li>
  <?php endforeach; ?>
  </ul>
</div>

<p><small>Šablóna sa generuje InitContainer-om z hodnoty
   <code>WELCOME_TEXT</code> v ConfigMap-e <code>app-config</code>.</small></p>
</body></html>
