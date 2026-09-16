<?php
declare(strict_types=1);
// Synthetic view fixture served only by the dedicated CLI test router.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/partials/charts.php';
function current_local_user(): ?array { return ['id' => 1, 'display_name' => 'Synthetic tester']; }
function is_admin(): bool { return true; }
function csrf_token(): string { return 'synthetic-token-not-valid-in-application'; }
$message = 'Synthetic UI fixture. No real incident or account is represented.';
$error = '';
$teams = ['Municipal Response Unit', 'Barangay Response Team', 'Medical Assistance Unit', 'Evacuation & Shelter Team', 'Engineering & Road Clearing'];
$statusCopy = ['received' => ['Report received', 'blue'], 'verified' => ['Report verified', 'orange'], 'dispatched' => ['Response in motion', 'violet'], 'resolved' => ['Report resolved', 'green']];
$reports = [[
    'id' => 1, 'reference_code' => 'IMS-SYNTHETIC-TESTONLY',
    'summary' => 'Synthetic flood assessment for layout testing only.',
    'created_at' => '2026-09-05 10:00:00', 'barangay_name' => 'Synthetic barangay',
    'municipality_name' => 'Synthetic city / municipality', 'province_name' => 'Synthetic province', 'region_name' => 'Synthetic region',
    'specific_type' => 'Flood', 'general_type' => 'Weather and water disasters', 'legend' => 'Red',
    'impact_detail' => 'Evacuation required', 'current_situation' => 'People are in immediate danger',
    'reporter_description' => 'Synthetic long description used to verify readable survey details and operational controls.',
    'reporter_name' => 'Synthetic reporter', 'contact_number' => null, 'evidence_note' => str_repeat('a', 40) . '.jpg',
    'details' => ['particular_type' => 'Flash flood', 'water_level' => 'Waist-deep', 'water_trend' => 'Rising', 'road_passability' => 'Not passable', 'people_stranded' => '12', 'houses_affected' => '8', 'evacuation_needed' => 'true', 'rescue_needed' => 'true'],
    'status' => 'received', 'team_name' => null, 'note' => 'Synthetic status note. No live event.'
]];
$analytics = [
    'total' => 1, 'active' => 1, 'criticalActive' => 1, 'dispatched' => 0,
    'resolved' => 0, 'unassignedActive' => 1, 'awaitingVerification' => 1, 'assignedActive' => 0, 'resolutionRate' => 0, 'verificationRate' => 0, 'assignmentRate' => 0,
    'countsByLegend' => ['Red' => 1], 'countsByType' => ['Flood' => 1], 'countsByGroup' => ['Weather and water disasters' => 1], 'countsByStatus' => ['received' => 1],
    'affectedAreas' => [['barangay' => 'Synthetic barangay', 'municipality' => 'Synthetic city / municipality', 'province' => 'Synthetic province', 'total' => 1, 'active' => 1, 'critical' => 1, 'attentionScore' => 4]],
    'dailyCounts' => [
        ['date' => '2026-08-30', 'label' => 'Sun', 'shortDate' => 'Aug 30', 'count' => 0, 'Green' => 0, 'Orange' => 0, 'Red' => 0],
        ['date' => '2026-08-31', 'label' => 'Mon', 'shortDate' => 'Aug 31', 'count' => 0, 'Green' => 0, 'Orange' => 0, 'Red' => 0],
        ['date' => '2026-09-01', 'label' => 'Tue', 'shortDate' => 'Sep 1', 'count' => 0, 'Green' => 0, 'Orange' => 0, 'Red' => 0],
        ['date' => '2026-09-02', 'label' => 'Wed', 'shortDate' => 'Sep 2', 'count' => 0, 'Green' => 0, 'Orange' => 0, 'Red' => 0],
        ['date' => '2026-09-03', 'label' => 'Thu', 'shortDate' => 'Sep 3', 'count' => 0, 'Green' => 0, 'Orange' => 0, 'Red' => 0],
        ['date' => '2026-09-04', 'label' => 'Fri', 'shortDate' => 'Sep 4', 'count' => 0, 'Green' => 0, 'Orange' => 0, 'Red' => 0],
        ['date' => '2026-09-05', 'label' => 'Sat', 'shortDate' => 'Sep 5', 'count' => 1, 'Green' => 0, 'Orange' => 0, 'Red' => 1],
    ],
    'disasterPriority' => ['Flood' => ['Green' => 0, 'Orange' => 0, 'Red' => 1, 'total' => 1]],
    'statusPriority' => ['received' => ['Green' => 0, 'Orange' => 0, 'Red' => 1, 'total' => 1]],
    'needs' => ['Rescue team' => 1, 'Evacuation transport' => 1],
    'ageBuckets' => ['Under 1 hour' => 0, '1–4 hours' => 0, '4–24 hours' => 0, '1–3 days' => 0, 'Over 3 days' => 1],
    'oldestActiveHours' => 264,
    'responseTimes' => ['verificationMinutes' => null, 'verificationSamples' => 0, 'dispatchMinutes' => null, 'dispatchSamples' => 0, 'resolutionMinutes' => null, 'resolutionSamples' => 0],
];
$allReports = $reports;
$filters = ['period' => 'all', 'disaster' => '', 'legend' => '', 'status' => '', 'municipality' => '', 'barangay' => ''];
$filterOptions = ['disasters' => ['Flood'], 'municipalities' => ['Synthetic city / municipality'], 'barangays' => ['Synthetic barangay']];
$pagasa = ['issuedAt' => 'Synthetic preview', 'synopsis' => 'Weather panel layout fixture, not a weather advisory.', 'conditions' => [], 'sourceUrl' => 'https://www.pagasa.dost.gov.ph/'];
$advisories = ['unavailable' => true, 'items' => []];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic operations UI fixture</title><link rel="stylesheet" href="/assets/app.css?v=9"><link rel="stylesheet" href="/assets/navigation.css"><link rel="stylesheet" href="/assets/atmosphere.css?v=5"><link rel="stylesheet" href="/assets/dashboard.css?v=17"></head><body class="admin-body">
<?php require dirname(__DIR__) . '/partials/header.php'; ?>
<?php require dirname(__DIR__) . '/partials/operations.php'; ?>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
<?php if (($_GET['export_open'] ?? '') === '1'): ?><script>document.querySelector('.dashboard-export-menu')?.setAttribute('open', '');</script><?php endif; ?>
</body></html>
