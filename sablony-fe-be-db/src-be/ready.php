<?php
// readiness — overí že DB je dostupná. Bez DB BE neslúži.
header('Content-Type: text/plain');

$dbHost = getenv('DB_HOST') ?: 'exam-db-svc';
$dbName = getenv('DB_NAME') ?: 'examdb';
$dbUser = getenv('DB_USER') ?: 'appuser';
$dbPass = getenv('MYSQL_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
    );
    $pdo->query("SELECT 1");
    echo "READY\n";
} catch (PDOException $e) {
    http_response_code(503);
    echo "NOT READY: " . $e->getMessage() . "\n";
}
