<?php
$statusOrder = [
    'received' => ['Received', '#3278e8'],
    'verified' => ['Verified', '#dc8618'],
    'dispatched' => ['Dispatched', '#7453b8'],
    'resolved' => ['Resolved', '#16845a'],
];
$statusRank = ['received' => 0, 'verified' => 1, 'dispatched' => 2, 'resolved' => 3];
?>
<main id="main-content" tabindex="-1" class="dashboard-shell bi-dashboard-shell">
  <?php require __DIR__ . '/analytics-workspace.php'; ?>
  <?php require __DIR__ . '/operations-queue.php'; ?>
</main>
