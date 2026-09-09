<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli-server') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Services\EvidenceStorage;
use ImSafe\Support\Config;

header('Content-Type: application/json; charset=utf-8');

try {
    $storage = new EvidenceStorage(new Config());
    $camera = $_FILES['evidence_camera'] ?? null;
    $hasCamera = is_array($camera) && (int)($camera['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $upload = $hasCamera ? $camera : ($_FILES['evidence'] ?? null);
    $coordinates = $storage->coordinates($upload);
    $storedName = $storage->store($upload);
    if (!$storedName) throw new RuntimeException('No test image was received.');
    $stored = $storage->resolve($storedName);
    if (!$stored) throw new RuntimeException('The saved test image could not be resolved.');

    $result = [
        'stored' => true,
        'privateName' => $storedName,
        'mime' => $stored['mime'],
        'bytes' => filesize($stored['path']),
        'coordinates' => $coordinates,
    ];
    $storage->remove($storedName);
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(422);
    echo json_encode(['stored' => false, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR);
}
