<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/malformed')) { echo '{not-json'; exit; }
if (str_starts_with($path, '/down')) { http_response_code(503); echo '{}'; exit; }
if (str_starts_with($path, '/empty')) { echo '{"data":[]}'; exit; }
if (str_starts_with($path, '/partial')) { echo '{"total":2,"data":[{"code":"0400000000","name":"CALABARZON"}]}'; exit; }
if (str_starts_with($path, '/invalid')) { echo '{"data":[{"code":"bad","name":"Invalid"}]}'; exit; }
if (str_starts_with($path, '/duplicate')) { echo '{"data":[{"code":"0400000000","name":"CALABARZON"},{"code":"0400000000","name":"CALABARZON"}]}'; exit; }
if (str_starts_with($path, '/timeout')) { sleep(13); echo '{}'; exit; }
$path = substr($path, strlen('/primary'));
$regions = [['code' => '0400000000', 'name' => 'CALABARZON'], ['code' => '1300000000', 'name' => 'NCR']];
$provinces = [['code' => '0402100000', 'name' => 'Cavite'], ['code' => '0405800000', 'name' => 'Rizal']];
$cities = [['code' => '0402103000', 'name' => 'City of Bacoor', 'type' => 'City', 'province' => 'WRONG LABEL'], ['code' => '0405801000', 'name' => 'Angono', 'type' => 'Mun']];
$ncr = [['code' => '1380600000', 'name' => 'City of Manila', 'type' => 'City', 'province' => 'Sarangani'], ['code' => '1380601000', 'name' => 'Tondo I/II', 'type' => 'SubMun']];
$records = match ($path) {
    '/regions' => $regions,
    '/regions/0400000000/provinces' => $provinces,
    '/regions/1300000000/provinces' => [],
    '/regions/0400000000/cities-municipalities' => $cities,
    '/regions/1300000000/cities-municipalities' => $ncr,
    '/provinces/0402100000/cities-municipalities' => [$cities[0]],
    '/provinces/0405800000/cities-municipalities' => [$cities[1]],
    '/cities-municipalities/0402103000/barangays' => [['code' => '0402103001', 'name' => 'Alima']],
    '/cities-municipalities/1380601000/barangays' => [['code' => '1380601001', 'name' => 'Barangay 1']],
    default => null,
};
if ($records === null) { http_response_code(404); echo '{}'; exit; }
echo json_encode(['data' => $records]);
