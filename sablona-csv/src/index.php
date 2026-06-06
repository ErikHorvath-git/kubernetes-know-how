<?php

$appName     = getenv('APP_NAME')    ?: 'CSV Processor';
$dataDir     = '/var/www/html/data';
$inputDir    = getenv('INPUT_DIR')   ?: $dataDir . '/input';
$outputDir   = getenv('OUTPUT_DIR')  ?: $dataDir . '/output';
$marker      = '# PROCESSED:';

if (!is_dir($inputDir))  { mkdir($inputDir,  0775, true); }
if (!is_dir($outputDir)) { mkdir($outputDir, 0775, true); }

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
</head><body>

<h1><?= htmlspecialchars($appName) ?></h1>
<p>vstup: <code><?= htmlspecialchars($inputDir) ?></code> ·
   výstup: <code><?= htmlspecialchars($outputDir) ?></code></p>

<h2>CSV súbory (<?= count($results) ?>)</h2>
<?php if (!$results): ?>
  <p>V priečinku <code>input/</code> nie sú žiadne <code>.csv</code> súbory.</p>
<?php else: ?>
  <?php foreach ($results as $name => $r): ?>
    <h3><?= htmlspecialchars($name) ?> — <?= htmlspecialchars($r['status']) ?></h3>
    <?php if (!$r['rows']): ?>
      <p><i>prázdny súbor</i></p>
    <?php else: ?>
      <table border="1">
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

<h2>Obsah <code>output/</code></h2>
<?php if (!$outputListing): ?>
  <p><i>(output/ je prázdny)</i></p>
<?php else: ?>
  <ul>
    <?php foreach ($outputListing as $f): ?>
      <li><?= htmlspecialchars($f['name']) ?> — <?= $f['size'] ?> B</li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

</body></html>
