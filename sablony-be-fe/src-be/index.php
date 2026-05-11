<?php
// BE API — beží v BE pode, odpovedá JSONom.
// Pristupuje sa cez FE nginx proxy: /api/  ──►  exam-be-svc:80/

header('Content-Type: application/json');

$appName    = getenv('APP_NAME')           ?: 'Exam BE';
$maxMb      = (int)(getenv('MAX_UPLOAD_MB') ?: 5);
$logLevel   = getenv('LOG_LEVEL')          ?: 'INFO';

$dataDir    = '/var/www/html/data';
$uploadsDir = $dataDir . '/uploads';
$logsDir = $dataDir . '/logs';
$logFile    = $logsDir . '/api.log';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

if (!is_dir($logsDir)) {
    mkdir($logsDir, 0775, true);
}

function log_line(string $logFile, string $level, string $msg): void {
    $line = sprintf("[%s] %s %s\n", date('c'), $level, $msg);
    file_put_contents($logFile, $line, FILE_APPEND);
}

$method = $_SERVER['REQUEST_METHOD'];
log_line($logFile, $logLevel, "$method " . ($_SERVER['REQUEST_URI'] ?? '/'));

// POST = upload súboru
if ($method === 'POST' && !empty($_FILES['file']['name'])) {
    $f = $_FILES['file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'upload error']);
        exit;
    }
    if ($f['size'] > $maxMb * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => "file > {$maxMb} MB"]);
        exit;
    }
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name']));
    $dest = $uploadsDir . '/' . time() . '_' . $name;
    move_uploaded_file($f['tmp_name'], $dest);
    echo json_encode(['ok' => true, 'file' => basename($dest)]);
    exit;
}

// GET = zoznam súborov
$files = array_values(array_diff(
    scandir($uploadsDir, SCANDIR_SORT_DESCENDING) ?: [],
    ['.', '..']
));
echo json_encode([
    'app'     => $appName,
    'maxMb'   => $maxMb,
    'pod'     => gethostname(),
    'count'   => count($files),
    'files'   => $files,
]);
