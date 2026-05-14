<?php

$appName     = getenv('APP_NAME')    ?: 'CSV Processor';
$dataDir     = '/var/www/html/data';
$inputDir    = getenv('INPUT_DIR')   ?: $dataDir . '/input';
$outputDir   = getenv('OUTPUT_DIR')  ?: $dataDir . '/output';
$welcomeFile = $dataDir . '/welcome.txt';
$marker      = '# PROCESSED:';

if (!is_dir($inputDir))  { mkdir($inputDir,  0775, true); }
if (!is_dir($outputDir)) { mkdir($outputDir, 0775, true); }

$welcome = file_exists($welcomeFile)
    ? file_get_contents($welcomeFile)
    : '(welcome.txt zatiaľ neexistuje — InitContainer ho mal pripraviť)';

$csvFiles = array_values(array_filter(
    array_diff(scandir($inputDir) ?: [], ['.', '..']),
    fn($f) => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'csv'
));
sort($csvFiles);

$results = [];
foreach ($csvFiles as $file) {
    $srcPath = $inputDir  . '/' . $file;
    $dstPath = $outputDir . '/' . $file;
    $content = (string) file_get_contents($srcPath);
    $lines   = explode("\n", $content);

    $alreadyOut = file_exists($dstPath);

    if (!$alreadyOut) {
        $dataRows = 0;
        foreach ($lines as $l) {
            $t = trim($l);
            if ($t === '' || $t[0] === '#') continue;
            $dataRows++;
        }
        if ($dataRows > 0) $dataRows--;
        $stamp      = date('Y-m-d H:i:s');
        $header     = "$marker $stamp rows=$dataRows src=input/$file";
        $newContent = $header . "\n" . $content;
        file_put_contents($dstPath, $newContent);
        $status   = "spracované teraz · $stamp · rows=$dataRows";
        $shown    = $newContent;
    } else {
        $shown    = (string) file_get_contents($dstPath);
        $firstLn  = strtok($shown, "\n") ?: '';
        $status   = "už spracované · " . trim($firstLn);
    }

    $rows = [];
    foreach (explode("\n", $shown) as $line) {
        if ($line === '' || str_starts_with(ltrim($line), '#')) continue;
        $rows[] = str_getcsv($line);
    }

    $results[$file] = ['status' => $status, 'rows' => $rows];
}

$outputListing = [];
if (is_dir($outputDir)) {
    foreach (array_diff(scandir($outputDir) ?: [], ['.', '..']) as $f) {
        $p = $outputDir . '/' . $f;
        if (is_file($p)) $outputListing[] = ['name' => $f, 'size' => filesize($p)];
    }
}
?>
<!doctype html>
<html lang="sk"><head><meta charset="utf-8">
<title><?= htmlspecialchars($appName) ?></title>
<style>body{font-family:sans-serif;max-width:900px;margin:2rem auto;padding:0 1rem}
.welcome{background:#fff8dc;border-left:4px solid #ffa500;padding:1rem;
  border-radius:4px;white-space:pre-wrap;font-family:monospace;font-size:.9em}
.box{background:#f4f4f4;padding:1rem;border-radius:6px;margin:1rem 0}
table{border-collapse:collapse;width:100%;background:#fff;margin:.5rem 0}
th,td{border:1px solid #ccc;padding:.3rem .5rem;text-align:left;font-size:.9em}
th{background:#e8e8e8}
h3{margin:.5rem 0;font-family:monospace}
.tag{display:inline-block;padding:.1rem .5rem;border-radius:10px;font-size:.8em;
  background:#dff0d8;color:#3c763d;margin-left:.5rem}
ul.files{font-family:monospace;font-size:.9em}</style>
</head><body>

<h1><?= htmlspecialchars($appName) ?></h1>
<p>vstup: <code><?= htmlspecialchars($inputDir) ?></code> ·
   výstup: <code><?= htmlspecialchars($outputDir) ?></code></p>

<div class="welcome"><?= htmlspecialchars($welcome) ?></div>

<div class="box">
  <h2>CSV súbory (<?= count($results) ?>)</h2>
  <?php if (!$results): ?>
    <p>V priečinku <code>input/</code> nie sú žiadne <code>.csv</code> súbory.</p>
  <?php else: ?>
    <?php foreach ($results as $name => $r): ?>
      <h3><?= htmlspecialchars($name) ?>
          <span class="tag"><?= htmlspecialchars($r['status']) ?></span></h3>
      <?php if (!$r['rows']): ?>
        <p><i>prázdny súbor</i></p>
      <?php else: ?>
        <table>
          <?php $first = true; foreach ($r['rows'] as $row): ?>
            <tr>
              <?php foreach ($row as $cell): ?>
                <?php if ($first): ?>
                  <th><?= htmlspecialchars($cell) ?></th>
                <?php else: ?>
                  <td><?= htmlspecialchars($cell) ?></td>
                <?php endif; ?>
              <?php endforeach; ?>
            </tr>
          <?php $first = false; endforeach; ?>
        </table>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="box">
  <h2>Obsah <code>output/</code></h2>
  <?php if (!$outputListing): ?>
    <p><i>(output/ je prázdny)</i></p>
  <?php else: ?>
    <ul class="files">
      <?php foreach ($outputListing as $f): ?>
        <li><?= htmlspecialchars($f['name']) ?> — <?= $f['size'] ?> B</li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

</body></html>
