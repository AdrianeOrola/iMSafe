<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

use ImSafe\Services\ReportExporter;

if (!is_admin()) {
    header('Location: account.php?mode=login');
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? '')));
if (!in_array($format, ['csv', 'xlsx', 'pdf'], true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Choose a supported report export format.');
}

session_write_close();

try {
    $reports = app()->incidents->exportAll();
    $timestamp = date('Y-m-d-His');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="imsafe-overall-reports-' . $timestamp . '.csv"');
        echo ReportExporter::csv($reports);
        exit;
    }
    if ($format === 'xlsx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="imsafe-overall-reports-' . $timestamp . '.xlsx"');
        echo ReportExporter::xlsx($reports);
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="imsafe-overall-reports-' . $timestamp . '.pdf"');
    echo ReportExporter::pdf($reports);
} catch (Throwable $exception) {
    log_app_error($exception);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The report export could not be generated. Return to the dashboard and try again.';
}
