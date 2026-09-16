<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$message = '';
$error = '';
$teams = ['Municipal Response Unit', 'Barangay Response Team', 'Medical Assistance Unit', 'Evacuation & Shelter Team', 'Engineering & Road Clearing'];
$statusCopy = ['received' => ['Report received', 'blue'], 'verified' => ['Report verified', 'orange'], 'dispatched' => ['Response in motion', 'violet'], 'resolved' => ['Report resolved', 'green']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    verify_csrf($_POST['csrf_token'] ?? null);
    session_destroy();
    header('Location: index.php');
    exit;
}
if (is_admin() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $status = (string)($_POST['status'] ?? 'received'); $team = trim((string)($_POST['assigned_team'] ?? '')); $note = trim((string)($_POST['status_note'] ?? ''));
    if (!isset($statusCopy[$status])) $error = 'Choose a valid operational status.';
    elseif ($team !== '' && !in_array($team, $teams, true)) $error = 'Choose a valid dispatch team.';
    elseif ($status === 'dispatched' && $team === '') $error = 'Choose a dispatch team before marking the incident dispatched.';
    elseif (strlen($note) > 1200) $error = 'Keep the public update under 1,200 characters.';
    else try { app()->incidents->updateOperations((int)($_POST['incident_id'] ?? 0), $status, $team, $note); $message = 'Operational update saved. The public report page now shows the latest update.'; } catch (Throwable $exception) { log_app_error($exception); $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The update could not be saved. Confirm the local database connection and try again.'; }
}

if (!is_admin()) { header('Location: account.php?mode=login'); exit; }

require __DIR__ . '/partials/charts.php';

try {
    $allReports = app()->incidents->all();
    $filterOptions = [
        'disasters' => array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row['specific_type'] ?? '')), $allReports)))),
        'municipalities' => array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row['municipality_name'] ?? '')), $allReports)))),
        'barangays' => array_values(array_unique(array_filter(array_map(static fn(array $row): string => trim((string)($row['barangay_name'] ?? '')), $allReports)))),
    ];
    sort($filterOptions['disasters'], SORT_NATURAL | SORT_FLAG_CASE);
    sort($filterOptions['municipalities'], SORT_NATURAL | SORT_FLAG_CASE);
    sort($filterOptions['barangays'], SORT_NATURAL | SORT_FLAG_CASE);
    $filters = [
        'period' => in_array((string)($_GET['period'] ?? 'all'), ['all', '7', '30', '90'], true) ? (string)($_GET['period'] ?? 'all') : 'all',
        'disaster' => in_array((string)($_GET['disaster'] ?? ''), $filterOptions['disasters'], true) ? (string)($_GET['disaster'] ?? '') : '',
        'legend' => in_array((string)($_GET['legend'] ?? ''), ['Green', 'Orange', 'Red'], true) ? (string)($_GET['legend'] ?? '') : '',
        'status' => isset($statusCopy[(string)($_GET['status'] ?? '')]) ? (string)$_GET['status'] : '',
        'municipality' => in_array((string)($_GET['municipality'] ?? ''), $filterOptions['municipalities'], true) ? (string)($_GET['municipality'] ?? '') : '',
        'barangay' => in_array((string)($_GET['barangay'] ?? ''), $filterOptions['barangays'], true) ? (string)($_GET['barangay'] ?? '') : '',
    ];
    $cutoff = $filters['period'] === 'all' ? null : (new DateTimeImmutable('today'))->modify('-' . ((int)$filters['period'] - 1) . ' days')->format('Y-m-d');
    $reports = array_values(array_filter($allReports, static function (array $report) use ($filters, $cutoff): bool {
        if ($cutoff !== null && substr((string)($report['created_at'] ?? ''), 0, 10) < $cutoff) return false;
        if ($filters['disaster'] !== '' && (string)($report['specific_type'] ?? '') !== $filters['disaster']) return false;
        if ($filters['legend'] !== '' && (string)($report['legend'] ?? '') !== $filters['legend']) return false;
        if ($filters['status'] !== '' && (string)($report['status'] ?? '') !== $filters['status']) return false;
        if ($filters['municipality'] !== '' && (string)($report['municipality_name'] ?? '') !== $filters['municipality']) return false;
        if ($filters['barangay'] !== '' && (string)($report['barangay_name'] ?? '') !== $filters['barangay']) return false;
        return true;
    }));
    $trendDays = $filters['period'] === '7' ? 7 : ($filters['period'] === '30' ? 30 : ($filters['period'] === '90' ? 90 : 14));
    $analytics = app()->incidents->analyticsFor($reports, $trendDays);
} catch (Throwable $exception) {
    log_app_error($exception);
    $allReports = $reports = [];
    $filters = ['period' => 'all', 'disaster' => '', 'legend' => '', 'status' => '', 'municipality' => '', 'barangay' => ''];
    $filterOptions = ['disasters' => [], 'municipalities' => [], 'barangays' => []];
    $analytics = ['total' => 0, 'active' => 0, 'criticalActive' => 0, 'dispatched' => 0, 'resolved' => 0, 'unassignedActive' => 0, 'awaitingVerification' => 0, 'assignedActive' => 0, 'resolutionRate' => 0, 'verificationRate' => 0, 'assignmentRate' => 0, 'countsByType' => [], 'countsByGroup' => [], 'countsByLegend' => [], 'countsByStatus' => [], 'affectedAreas' => [], 'dailyCounts' => [], 'disasterPriority' => [], 'statusPriority' => [], 'needs' => [], 'ageBuckets' => [], 'oldestActiveHours' => 0, 'responseTimes' => []];
    $error = 'The monitoring database could not be read. Import schema.sql and confirm the environment settings.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Incident Monitor · iMSafe v2.0</title><link rel="stylesheet" href="assets/app.css?v=9"><link rel="stylesheet" href="assets/navigation.css?v=9"><link rel="stylesheet" href="assets/atmosphere.css?v=5"><link rel="stylesheet" href="assets/dashboard.css?v=17"><link rel="stylesheet" href="assets/imassist.css?v=5"></head><body class="admin-body"><?php require __DIR__ . '/partials/header.php'; ?>
<?php require __DIR__ . '/partials/operations.php'; ?><?php require __DIR__ . '/partials/footer.php'; ?>
</body></html>
