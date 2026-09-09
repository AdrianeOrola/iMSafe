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

try { $reports = app()->incidents->all(); $analytics = app()->incidents->analytics(); } catch (Throwable $exception) { log_app_error($exception); $reports = []; $analytics = ['total' => 0, 'active' => 0, 'criticalActive' => 0, 'dispatched' => 0, 'resolved' => 0, 'unassignedActive' => 0, 'resolutionRate' => 0, 'countsByType' => [], 'countsByLegend' => [], 'countsByStatus' => [], 'affectedAreas' => [], 'dailyCounts' => []]; $error = 'The monitoring database could not be read. Import schema.sql and confirm the environment settings.'; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Incident Monitor · iMSafe v2.0</title><link rel="stylesheet" href="assets/app.css?v=9"><link rel="stylesheet" href="assets/navigation.css?v=9"><link rel="stylesheet" href="assets/atmosphere.css?v=5"><link rel="stylesheet" href="assets/dashboard.css?v=15"><link rel="stylesheet" href="assets/imassist.css?v=5"></head><body class="admin-body"><?php require __DIR__ . '/partials/header.php'; ?>
<?php require __DIR__ . '/partials/operations.php'; ?><?php require __DIR__ . '/partials/footer.php'; ?>
</body></html>
