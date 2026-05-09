<?php
// VARIANT A+C — Upload + InitContainer šablóna
// FS interakcia: zapisovanie (move_uploaded_file), čítanie (file_get_contents,
//                scandir), zápis počítadla
// InitContainer: pred štartom skopíruje welcome.txt z ConfigMap-u na PVC

$appName     = getenv('APP_NAME')           ?: 'Upload + Welcome';
$maxMb       = (int)(getenv('MAX_UPLOAD_MB') ?: 5);
$dataDir     = '/var/www/html/data';
$uploadsDir  = $dataDir . '/uploads';
$welcomeFile = $dataDir . '/welcome.txt';
$counterFile = $dataDir . '/visits.txt';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

// Inkrementuj počítadlo (zápis)
$visits = (int) @file_get_contents($counterFile);
$visits++;
file_put_contents($counterFile, (string) $visits);

// Načítaj šablónu pripravenú InitContainer-om (čítanie)
$welcome = file_exists($welcomeFile)
    ? file_get_contents($welcomeFile)
    : '(welcome.txt zatiaľ neexistuje — InitContainer ho mal pripraviť)';

// Spracuj upload (zápis)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Chyba pri uploade';
    } elseif ($f['size'] > $maxMb * 1024 * 1024) {
        $msg = 'Súbor presahuje ' . $maxMb . ' MB';
    } else {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name']));
        $dest = $uploadsDir . '/' . time() . '_' . $name;
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $msg = 'Nahrané: ' . htmlspecialchars(basename($dest));
        }
    }
}

$files = array_diff(scandir($uploadsDir, SCANDIR_SORT_DESCENDING) ?: [], ['.', '..']);
?>
<!doctype html>
<html lang="sk"><head><meta charset="utf-8">
<title><?= htmlspecialchars($appName) ?></title>
<style>body{font-family:sans-serif;max-width:760px;margin:2rem auto;padding:0 1rem}
.welcome{background:#fff8dc;border-left:4px solid #ffa500;padding:1rem;
  border-radius:4px;white-space:pre-wrap;font-family:monospace;font-size:.9em}
.box{background:#f4f4f4;padding:1rem;border-radius:6px;margin:1rem 0}
.flash{background:#dff0d8;border:1px solid #5cb85c;padding:.5rem;border-radius:4px}</style>
</head><body>

<h1><?= htmlspecialchars($appName) ?></h1>
<p>Pod: <b><?= gethostname() ?></b> ·
   max upload: <b><?= $maxMb ?> MB</b> ·
   návštev: <b><?= $visits ?></b></p>

<div class="welcome"><?= htmlspecialchars($welcome) ?></div>

<?php if ($msg): ?><p class="flash"><b><?= $msg ?></b></p><?php endif; ?>

<div class="box">
  <h2>Upload súboru</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <button type="submit">Nahrať</button>
  </form>
</div>

<div class="box">
  <h2>Nahrané súbory (<?= count($files) ?>)</h2>
  <?php if (!$files): ?>
    <p>Zatiaľ žiadne súbory.</p>
  <?php else: ?>
    <ul>
    <?php foreach ($files as $f): ?>
      <li><?= htmlspecialchars($f) ?>
          (<?= filesize($uploadsDir.'/'.$f) ?> B)</li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<p><small>Šablóna <code>welcome.txt</code> je generovaná InitContainer-om
   z hodnoty <code>WELCOME_TEXT</code> v ConfigMap-e <code>app-config</code>.</small></p>
</body></html>
