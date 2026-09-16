<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Controllers\IncidentController;
use ImSafe\Services\EvidenceStorage;
use ImSafe\Support\Config;
use ImSafe\Support\DisasterCatalog;

$checks = 0;
$expect = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
};

$groups = DisasterCatalog::groups();
$disasters = DisasterCatalog::disasters();
$expect(count($groups) === 3, 'three understandable disaster groups exist');
$expect(isset($groups['Weather and water disasters'], $groups['Earth and ground disasters'], $groups['Fire and dangerous material incidents']), 'plain-language disaster groups');
$expect(!str_contains(implode(' ', array_keys($groups)), 'Hydro') && !str_contains(implode(' ', array_keys($groups)), 'Geological'), 'technical group names are not shown');
$expect(DisasterCatalog::friendlyGroup('Hydrometeorological') === 'Weather and water disasters', 'legacy weather category is presented in plain language');
$expect(DisasterCatalog::friendlyDisaster('Tropical Cyclone') === 'Typhoon', 'legacy cyclone label is presented as typhoon');
$expect(DisasterCatalog::friendlyEffect('Pyroclastic flow') === 'Fast-moving hot ash or gas', 'legacy technical effect is presented in plain language');
$expect(isset($disasters['Flood'], $disasters['Earthquake'], $disasters['Fire']), 'main disaster choices exist');
$expect(in_array('Flash flood', $disasters['Flood']['effects'], true), 'flood effect allowlist');
$expect(in_array('Ground shaking', $disasters['Earthquake']['effects'], true), 'earthquake effect allowlist');
$earthquakeKeys = array_column($disasters['Earthquake']['questions'], 'key');
$expect(in_array('earthquake_magnitude', $earthquakeKeys, true) && in_array('shaking_strength', $earthquakeKeys, true), 'earthquake asks magnitude and shaking strength');
foreach ($disasters as $name => $definition) $expect(count($definition['questions']) >= 5, $name . ' has tailored situation questions');
$floodDetails = DisasterCatalog::validate('Weather and water disasters', 'Flood', 'Flash flood', [
    'water_level' => 'Ankle-deep', 'water_trend' => 'Rising', 'road_passability' => 'Not passable',
    'people_stranded' => '2', 'houses_affected' => '4', 'rescue_needed' => '1',
]);
$expect($floodDetails['road_passability'] === 'Not passable' && $floodDetails['rescue_needed'] === true, 'flood situation is validated');
try {
    DisasterCatalog::validate('Earth and ground disasters', 'Earthquake', 'Ground shaking', ['earthquake_magnitude' => '12']);
    $expect(false, 'invalid earthquake magnitude rejected');
} catch (RuntimeException $error) {
    $expect(str_contains($error->getMessage(), 'magnitude') || str_contains($error->getMessage(), 'Magnitude'), 'invalid earthquake magnitude rejected');
}

$reflection = new ReflectionClass(IncidentController::class);

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
