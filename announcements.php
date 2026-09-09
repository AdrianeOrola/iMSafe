<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
$pagasa = app()->pagasa->current();
$advisories = app()->advisories->current();
$checkedAt = date('M j, Y · g:i A');
$officialSources = [
    ['agency' => 'DOST-PAGASA', 'scope' => 'Daily weather, tropical cyclones, rainfall, flood and active weather warnings', 'url' => 'https://www.pagasa.dost.gov.ph/weather'],
    ['agency' => 'DOST-PHIVOLCS Earthquakes', 'scope' => 'Latest Philippine earthquake information from the Philippine Seismic Network', 'url' => 'https://earthquake.phivolcs.dost.gov.ph/EQLatest.html'],
    ['agency' => 'DOST-PHIVOLCS Bulletins', 'scope' => 'Latest volcano and tsunami bulletins and official hazard information', 'url' => 'https://www.phivolcs.dost.gov.ph/'],
    ['agency' => 'NDRRMC', 'scope' => 'National disaster situation reports and preparedness updates', 'url' => 'https://ndrrmc.gov.ph/index.php/8-ndrrmc-update'],
    ['agency' => 'GDACS', 'scope' => 'International disaster detection and coordination signals', 'url' => 'https://www.gdacs.org/'],
];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Official-source Announcements · iMSafe v2.0</title><link rel="stylesheet" href="assets/app.css?v=5"><link rel="stylesheet" href="assets/navigation.css?v=10"><link rel="stylesheet" href="assets/atmosphere.css?v=5"><link rel="stylesheet" href="assets/announcements.css?v=2"><link rel="stylesheet" href="assets/imassist.css?v=2"></head><body class="announcement-body"><?php require __DIR__ . '/partials/header.php'; ?>
<?php require __DIR__ . '/partials/announcements.php'; ?>
<?php require __DIR__ . '/partials/footer.php'; ?></body></html>
