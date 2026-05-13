<?php
// BE API — beží v BE pode, odpovedá JSONom.
// FE → nginx proxy /api/ → exam-be-svc:80/ → tu
// Upload sa ukladá ako BLOB do MariaDB (exam-db-svc:3306), nie na disk.

header('Content-Type: application/json');

$appName  = getenv('APP_NAME')           ?: 'Exam BE';
$maxMb    = (int)(getenv('MAX_UPLOAD_MB') ?: 5);
$dbHost   = getenv('DB_HOST') ?: 'exam-db-svc';
$dbName   = getenv('DB_NAME') ?: 'examdb';
$dbUser   = getenv('DB_USER') ?: 'appuser';
$dbPass   = getenv('MYSQL_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['error' => 'DB connect failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// POST = upload súboru → INSERT BLOB
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
    $name    = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($f['name']));
    $content = file_get_contents($f['tmp_name']);
    $size    = $f['size'];

    $stmt = $pdo->prepare(
        "INSERT INTO uploads (name, size, content) VALUES (?, ?, ?)"
    );
    $stmt->bindValue(1, $name);
    $stmt->bindValue(2, $size, PDO::PARAM_INT);
    $stmt->bindValue(3, $content, PDO::PARAM_LOB);
    $stmt->execute();

    echo json_encode(['ok' => true, 'file' => $name, 'id' => $pdo->lastInsertId()]);
    exit;
}

// GET = zoznam súborov z DB (bez BLOB obsahu — len metadata)
$rows = $pdo->query(
    "SELECT id, name, size, created_at FROM uploads ORDER BY id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'app'   => $appName,
    'maxMb' => $maxMb,
    'db'    => $dbHost,
    'count' => count($rows),
    'files' => $rows,
]);
