<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Controllers\IncidentController;
use ImSafe\Services\EvidenceStorage;
use ImSafe\Support\Config;

$checks = 0;
$expect = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
};

$reflection = new ReflectionClass(IncidentController::class);
$disasters = $reflection->getConstant('DISASTERS');
$expect(is_array($disasters) && isset($disasters['Flood'], $disasters['Earthquake'], $disasters['Fire']), 'main disaster choices exist');
$expect($disasters['Flood']['category'] === 'Hydrometeorological', 'flood category derived server-side');
$expect(in_array('Flash flood', $disasters['Flood']['particulars'], true), 'flood particular allowlist');
$expect(in_array('Ground shaking', $disasters['Earthquake']['particulars'], true), 'earthquake particular allowlist');

$controller = $reflection->newInstanceWithoutConstructor();
$coordinatesMethod = $reflection->getMethod('browserCoordinates');
$coordinatesMethod->setAccessible(true);
$coordinates = $coordinatesMethod->invoke($controller, ['latitude' => '14.5995124', 'longitude' => '120.9842195']);
$expect($coordinates === ['latitude' => 14.5995124, 'longitude' => 120.9842195], 'valid browser coordinates');
$expect($coordinatesMethod->invoke($controller, []) === null, 'empty coordinates remain optional');
try {
    $coordinatesMethod->invoke($controller, ['latitude' => '91', 'longitude' => '120']);
    $expect(false, 'out-of-range coordinates rejected');
} catch (ReflectionException|RuntimeException $error) {
    $expect(str_contains($error->getMessage(), 'outside'), 'out-of-range coordinates rejected');
}

$storage = new EvidenceStorage(new Config());
$expect($storage->coordinates(null) === null, 'missing photo metadata is safe');

echo "PASS: $checks disaster-taxonomy and coordinate checks.\n";
