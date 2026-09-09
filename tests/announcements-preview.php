<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
function current_local_user(): ?array { return null; }
function is_admin(): bool { return false; }
function csrf_token(): string { return 'synthetic-token-not-valid-in-application'; }
$_SERVER['SCRIPT_NAME'] = '/announcements.php';
$checkedAt = 'Sep 6, 2026 · 9:00 AM';
$officialSources = [
    ['agency' => 'DOST-PAGASA', 'scope' => 'Daily weather, tropical cyclones, rainfall, flood and active weather warnings', 'url' => 'https://www.pagasa.dost.gov.ph/weather'],
    ['agency' => 'DOST-PHIVOLCS Earthquakes', 'scope' => 'Latest Philippine earthquake information from the Philippine Seismic Network', 'url' => 'https://earthquake.phivolcs.dost.gov.ph/EQLatest.html'],
    ['agency' => 'DOST-PHIVOLCS Bulletins', 'scope' => 'Latest volcano and tsunami bulletins and official hazard information', 'url' => 'https://www.phivolcs.dost.gov.ph/'],
    ['agency' => 'NDRRMC', 'scope' => 'National disaster situation reports and preparedness updates', 'url' => 'https://ndrrmc.gov.ph/index.php/8-ndrrmc-update'],
    ['agency' => 'GDACS', 'scope' => 'International disaster detection and coordination signals', 'url' => 'https://www.gdacs.org/'],
];
$pagasa = ['issuedAt' => '4:00 PM, 05 September 2026', 'synopsis' => 'Synthetic PAGASA layout fixture. Confirm conditions at the official source.', 'conditions' => [
    ['place' => 'Metro Manila and CALABARZON', 'condition' => 'Cloudy skies with scattered rains', 'impacts' => 'Possible flash floods or landslides due to moderate to heavy rains'],
    ['place' => 'The rest of Luzon', 'condition' => 'Partly cloudy to cloudy skies', 'impacts' => 'Possible localized flooding during severe thunderstorms'],
], 'sourceUrl' => 'https://www.pagasa.dost.gov.ph/weather', 'fromCache' => false];
$advisories = ['source' => 'GDACS', 'refreshedAt' => '2026-09-06T01:00:00+00:00', 'fromCache' => false, 'items' => [
    ['title' => 'Synthetic flood signal mentioning the Philippines', 'url' => 'https://www.gdacs.org/', 'level' => 'orange', 'hazard' => 'Flood', 'publishedAt' => '2026-09-06T00:30:00+00:00'],
    ['title' => 'Synthetic regional earthquake signal', 'url' => 'https://www.gdacs.org/', 'level' => 'green', 'hazard' => 'Earthquake', 'publishedAt' => '2026-09-05T22:00:00+00:00'],
]];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic announcements fixture</title><link rel="stylesheet" href="/assets/app.css"><link rel="stylesheet" href="/assets/navigation.css"><link rel="stylesheet" href="/assets/atmosphere.css"><link rel="stylesheet" href="/assets/announcements.css"></head><body class="announcement-body">
<?php require dirname(__DIR__) . '/partials/header.php'; ?>
<?php require dirname(__DIR__) . '/partials/announcements.php'; ?>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
</body></html>
