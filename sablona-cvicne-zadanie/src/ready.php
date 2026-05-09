<?php
// BONUS — endpoint pre readinessProbe
// Overuje že PVC je writable — ak nie, Service nepošle traffic na pod
header('Content-Type: text/plain');
$dir = '/var/www/html/data';
if (is_writable($dir)) {
    echo "READY\n";
} else {
    http_response_code(503);
    echo "NOT READY: $dir not writable\n";
}
