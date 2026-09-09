<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (!is_admin()) {
    http_response_code(404);
    exit;
}

$storedName = strtolower(trim((string)($_GET['file'] ?? '')));
$evidence = app()->evidence->resolve($storedName);
if (!$evidence) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Photo evidence was not found.';
    exit;
}

header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Content-Type: ' . $evidence['mime']);
header('Content-Length: ' . (string)filesize($evidence['path']));
header('Content-Disposition: inline; filename="incident-evidence.' . pathinfo($storedName, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
readfile($evidence['path']);
