<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
session_save_path(sys_get_temp_dir());
require dirname(__DIR__) . '/config.php';

use ImSafe\Services\ReportExporter;

$dashboardRows = app()->incidents->all();
$exportRows = app()->incidents->exportAll();
if (count($dashboardRows) !== count($exportRows)) throw new RuntimeException('Dashboard and export totals do not match.');
if ($exportRows) {
    foreach (['reference_code', 'incident_description', 'region_name', 'needs', 'latest_update'] as $key) {
        if (!array_key_exists($key, $exportRows[0])) throw new RuntimeException('Export field is missing: ' . $key);
    }
}
$csv = ReportExporter::csv($exportRows);
$xlsx = ReportExporter::xlsx($exportRows);
$pdf = ReportExporter::pdf($exportRows);
if (!str_starts_with($csv, "\xEF\xBB\xBF") || !str_starts_with($xlsx, 'PK') || !str_starts_with($pdf, '%PDF-1.4')) {
    throw new RuntimeException('A database export format is invalid.');
}
if (($argv[1] ?? '') === '--write-artifacts') {
    $directory = dirname(__DIR__) . '/tmp/pdfs';
    if (!is_dir($directory)) mkdir($directory, 0770, true);
    file_put_contents($directory . '/imsafe-reports.xlsx', $xlsx);
    file_put_contents($directory . '/imsafe-reports.pdf', $pdf);
}
echo 'PASS: generated CSV, Excel, and PDF from ' . count($exportRows) . " database reports without modifying data.\n";
