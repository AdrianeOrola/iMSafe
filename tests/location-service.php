<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Services\HttpClient;
use ImSafe\Services\LocationService;
use ImSafe\Support\Config;

$base = getenv('IMSAFE_FIXTURE_URL') ?: 'http://127.0.0.1:8015';
$root = sys_get_temp_dir() . '/imsafe-location-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$checks = 0;
$expect = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
};
$service = static function (string $provider, string $suite) use ($base, $root): LocationService {
    putenv('IMSAFE_STORAGE_PATH=' . $root . '/' . $suite);
    putenv('IMSAFE_LOCATION_API_URL=' . $base . '/' . $provider);
    return new LocationService(new HttpClient(), new Config());
};
$rejects = static function (callable $action, string $name) use ($expect): void {
    try { $action(); } catch (RuntimeException $error) { $expect(true, $name); return; }
    $expect(false, $name);
};
try {
    $locations = $service('primary', 'normal');
    $expect(count($locations->regions()['items']) === 2, 'regions');
    $expect($locations->regions()['fromCache'] === true, 'fresh cache metadata');
    $expect(count($locations->provinces('0400000000')['items']) === 2, 'region provinces');
    $expect(count($locations->municipalities('0400000000', '0402100000')['items']) === 1, 'province endpoint children');
    $expect($locations->municipalities('0400000000', '0402100000')['items'][0]['code'] === '0402103000', 'ignores wrong province label');
    $expect($locations->provinces('1300000000')['items'][0]['code'] === 'none', 'NCR no province');
    $expect(count($locations->municipalities('1300000000', 'none')['items']) === 1, 'NCR excludes SubMun');
    $expect($locations->barangays('1380600000')['items'][0]['code'] === '1380601001', 'Manila aggregates sub-municipality barangays');
    $locations->assertSelection('0400000000', 'CALABARZON', '0402103000', 'City of Bacoor', '0402103001', 'Alima', '0402100000', 'Cavite');
    $expect(true, 'valid ancestry');
    $rejects(fn() => $locations->assertSelection('0400000000', 'CALABARZON', '0402103000', 'City of Bacoor', '0402103001', 'Alima', '0405800000', 'Rizal'), 'wrong province');
    $rejects(fn() => $locations->assertSelection('0400000000', 'CALABARZON', '0402103000', 'City of Bacoor', '0402103001', 'Tampered', '0402100000', 'Cavite'), 'tampered hidden name');
    $rejects(fn() => $locations->municipalities('0400000000', '9999900000'), 'unknown province');
    $rejects(fn() => $locations->provinces('9900000000'), 'unknown region');
    foreach (['malformed', 'empty', 'partial', 'invalid', 'duplicate', 'down'] as $kind) {
        $test = $service($kind, $kind);
        $rejects(fn() => $test->regions(), $kind . ' response rejected');
    }
    $offline = $service('primary', 'offline');
    $offline->regions();
    $cache = glob($root . '/offline/*.json')[0];
    touch($cache, time() - 90000);
    clearstatcache(true, $cache);
    $offline = $service('down', 'offline');
    $result = $offline->regions();
    $expect($result['stale'] === true && $result['fromCache'] === true, 'stale fallback metadata');
    // Corrupt cache must not count as a usable offline result.
    file_put_contents($cache, '{broken');
    clearstatcache(true, $cache);
    $rejects(fn() => $offline->regions(), 'corrupt cache fails safely');
    $timeout = $service('timeout', 'timeout');
    $started = microtime(true);
    $rejects(fn() => $timeout->regions(), 'provider timeout fails safely');
    $expect(microtime(true) - $started < 13, 'timeout bounded');
    echo "PASS: $checks location service checks.\n";
} finally {
    // Only fixtures created inside this exact random test directory.
    foreach (glob($root . '/*', GLOB_ONLYDIR) as $directory) {
        foreach (glob($directory . '/*.json') as $file) unlink($file);
        rmdir($directory);
    }
    rmdir($root);
}
