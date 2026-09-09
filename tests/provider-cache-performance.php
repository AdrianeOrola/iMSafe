<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Services\GlobalAdvisoryService;
use ImSafe\Services\HttpClient;
use ImSafe\Services\PagasaService;
use ImSafe\Support\Config;

function cache_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$storage = dirname(__DIR__) . '/.impeccable/provider-cache-test';
if (!is_dir($storage) && !mkdir($storage, 0775, true) && !is_dir($storage)) {
    throw new RuntimeException('Could not create the provider cache test directory.');
}
putenv('IMSAFE_STORAGE_PATH=' . $storage);

$pagasaFixture = [
    'issuedAt' => 'Cache test issuance',
    'synopsis' => 'PAGASA cache marker',
    'conditions' => [],
    'sourceUrl' => 'https://www.pagasa.dost.gov.ph/weather',
    'fromCache' => false,
    'cachedAt' => gmdate(DATE_ATOM),
];
$gdacsFixture = [
    'source' => 'GDACS Global Disaster Alert and Coordination System',
    'refreshedAt' => gmdate(DATE_ATOM),
    'fromCache' => false,
    'items' => [['id' => 'cache-marker', 'title' => 'GDACS cache marker', 'description' => '', 'publishedAt' => '', 'url' => 'https://www.gdacs.org/', 'level' => 'unknown', 'hazard' => 'Natural hazard']],
];

file_put_contents($storage . '/pagasa.json', json_encode($pagasaFixture, JSON_THROW_ON_ERROR), LOCK_EX);
file_put_contents($storage . '/gdacs.json', json_encode($gdacsFixture, JSON_THROW_ON_ERROR), LOCK_EX);
touch($storage . '/pagasa.json');
touch($storage . '/gdacs.json');

$http = new HttpClient();
$config = new Config();
$started = microtime(true);
$pagasa = (new PagasaService($http, $config))->current();
$gdacs = (new GlobalAdvisoryService($http, $config))->current();
$elapsed = microtime(true) - $started;

cache_expect($elapsed < 0.5, 'Fresh provider caches must be returned without waiting for the network. Elapsed: ' . number_format($elapsed, 3) . 's');
cache_expect(($pagasa['synopsis'] ?? '') === 'PAGASA cache marker' && !empty($pagasa['fromCache']), 'PAGASA must return its fresh cache immediately.');
cache_expect(($gdacs['items'][0]['title'] ?? '') === 'GDACS cache marker' && !empty($gdacs['fromCache']), 'GDACS must return its fresh cache immediately.');

touch($storage . '/pagasa.json', time() - 86400);
touch($storage . '/gdacs.json', time() - 86400);
$started = microtime(true);
$stalePagasa = (new PagasaService($http, $config))->current(false);
$staleGdacs = (new GlobalAdvisoryService($http, $config))->current(false);
$staleElapsed = microtime(true) - $started;

cache_expect($staleElapsed < 0.5, 'Cache-only reads must return stale data without waiting for providers. Elapsed: ' . number_format($staleElapsed, 3) . 's');
cache_expect(!empty($stalePagasa['fromCache']) && !empty($stalePagasa['stale']), 'PAGASA must label a stale cache-only response.');
cache_expect(!empty($staleGdacs['fromCache']) && !empty($staleGdacs['stale']), 'GDACS must label a stale cache-only response.');

echo "PASS: fresh and cache-only provider reads complete without network delay.\n";
