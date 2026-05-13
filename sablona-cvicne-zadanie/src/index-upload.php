<?php
// VARIANT A — Web s uploadom súborov do uploads/
// FS interakcia: zapisovanie (move_uploaded_file), čítanie (scandir)

$appName    = getenv('APP_NAME')           ?: 'Upload App';
$maxMb      = (int)(getenv('MAX_UPLOAD_MB') ?: 5);
$uploadsDir = '/var/www/html/data/uploads';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

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
<style>body{font-family:sans-serif;max-width:700px;margin:2rem auto;padding:0 1rem}
.box{background:#f4f4f4;padding:1rem;border-radius:6px;margin:1rem 0}</style></head>
<body>
<h1><?= htmlspecialchars($appName) ?></h1>
<p>max: <b><?= $maxMb ?> MB</b></p>
<?php if ($msg): ?><p><b><?= $msg ?></b></p><?php endif; ?>

<div class="box">
  <h2>Upload</h2>
  <form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <button type="submit">Nahrať</button>
  </form>
</div>

<div class="box">
  <h2>Súbory (<?= count($files) ?>)</h2>
  <ul>
  <?php foreach ($files as $f): ?>
    <li><?= htmlspecialchars($f) ?>
        (<?= filesize($uploadsDir.'/'.$f) ?> B)</li>
  <?php endforeach; ?>
  </ul>
</div>
</body></html>
