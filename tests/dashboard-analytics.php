<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Repositories\IncidentRepository;

$checks = 0;
$expect = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
};
$now = new DateTimeImmutable();
$records = [
    ['status' => 'received', 'legend' => 'Red', 'specific_type' => 'Flood', 'general_type' => 'Weather and water disasters', 'created_at' => $now->modify('-20 minutes')->format('Y-m-d H:i:s'), 'team_name' => '', 'barangay_name' => 'Alpha', 'municipality_name' => 'City A', 'province_name' => 'Province A', 'needs' => ['Rescue team']],
    ['status' => 'verified', 'legend' => 'Orange', 'specific_type' => 'Earthquake', 'general_type' => 'Earth and ground disasters', 'created_at' => $now->modify('-2 hours')->format('Y-m-d H:i:s'), 'verified_at' => $now->modify('-90 minutes')->format('Y-m-d H:i:s'), 'team_name' => 'Municipal Response Unit', 'barangay_name' => 'Beta', 'municipality_name' => 'City A', 'province_name' => 'Province A', 'needs' => ['Medical assistance']],
    ['status' => 'resolved', 'legend' => 'Green', 'specific_type' => 'Fire', 'general_type' => 'Fire and dangerous material incidents', 'created_at' => $now->modify('-4 days')->format('Y-m-d H:i:s'), 'resolved_at' => $now->modify('-4 days +120 minutes')->format('Y-m-d H:i:s'), 'team_name' => '', 'barangay_name' => 'Gamma', 'municipality_name' => 'City B', 'province_name' => 'Province B', 'needs' => []],
];

$repository = (new ReflectionClass(IncidentRepository::class))->newInstanceWithoutConstructor();
$analytics = $repository->analyticsFor($records, 7);
$expect($analytics['total'] === 3 && $analytics['active'] === 2, 'totals and active workload');
$expect($analytics['criticalActive'] === 1 && $analytics['awaitingVerification'] === 1, 'critical and verification backlog');
$expect($analytics['disasterPriority']['Flood']['Red'] === 1, 'priority by disaster');
$expect($analytics['statusPriority']['verified']['Orange'] === 1, 'priority by response stage');
$expect($analytics['needs']['Rescue team'] === 1 && $analytics['needs']['Medical assistance'] === 1, 'assistance demand');
$expect($analytics['ageBuckets']['Under 1 hour'] === 1 && $analytics['ageBuckets']['1–4 hours'] === 1, 'active queue aging');
$expect($analytics['responseTimes']['verificationMinutes'] === 30, 'average verification time');
$expect($analytics['responseTimes']['resolutionMinutes'] === 120, 'average resolution time');
$expect($analytics['affectedAreas'][0]['attentionScore'] >= 1, 'area workload score');
$expect(array_sum(array_column($analytics['dailyCounts'], 'count')) === 3, 'daily total trend');
$today = $analytics['dailyCounts'][array_key_last($analytics['dailyCounts'])];
$expect($today['Red'] === 1 && $today['Orange'] === 1, 'daily priority series');

echo "PASS: $checks BI dashboard analytics checks.\n";
