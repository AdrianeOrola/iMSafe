<?php
$percentage = static fn(int $value, int $total): int => $total > 0 ? (int)round(($value / $total) * 100) : 0;
$legendCounts = [
    'Green' => (int)($analytics['countsByLegend']['Green'] ?? 0),
    'Orange' => (int)($analytics['countsByLegend']['Orange'] ?? 0),
    'Red' => (int)($analytics['countsByLegend']['Red'] ?? 0),
];
$affectedAreas = array_slice($analytics['affectedAreas'] ?? [], 0, 8);
$dailyCounts = $analytics['dailyCounts'] ?? [];
$disasterPriority = array_slice($analytics['disasterPriority'] ?? [], 0, 8, true);
$disasterHigh = max(1, ...array_values(array_map(static fn(array $row): int => (int)$row['total'], $disasterPriority ?: [['total' => 1]])));
$ageBuckets = $analytics['ageBuckets'] ?? [];
$ageHigh = max(1, ...array_values($ageBuckets ?: [1]));
$needCounts = array_slice($analytics['needs'] ?? [], 0, 6, true);
$needHigh = max(1, ...array_values($needCounts ?: [1]));
$responseTimes = $analytics['responseTimes'] ?? [];
$activeFilterCount = count(array_filter($filters, static fn(string $value, string $key): bool => $value !== '' && !($key === 'period' && $value === 'all'), ARRAY_FILTER_USE_BOTH));
$topDisaster = array_key_first($analytics['countsByType'] ?? []) ?: 'No reports in view';
$trendLabel = count($dailyCounts) . '-day incident trend';
$filterNames = ['period' => 'Date', 'disaster' => 'Disaster', 'legend' => 'Priority', 'status' => 'Status', 'municipality' => 'Municipality', 'barangay' => 'Barangay'];
$filterDisplay = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
$filterUrl = static function (array $changes) use ($filters): string {
    $next = array_merge($filters, $changes);
    $next = array_filter($next, static fn(string $value, string $key): bool => $value !== '' && !($key === 'period' && $value === 'all'), ARRAY_FILTER_USE_BOTH);
    return 'dashboard.php' . ($next ? '?' . http_build_query($next) : '');
};
$operationalAlert = (int)($analytics['criticalActive'] ?? 0) > 0
    ? ['critical', (int)$analytics['criticalActive'] . ' critical active ' . ((int)$analytics['criticalActive'] === 1 ? 'report requires' : 'reports require') . ' immediate review.']
    : ((int)($analytics['unassignedActive'] ?? 0) > 0
        ? ['warning', (int)$analytics['unassignedActive'] . ' active ' . ((int)$analytics['unassignedActive'] === 1 ? 'report has' : 'reports have') . ' no assigned response team.']
        : ['clear', 'No critical or unassigned active reports in the selected scope.']);
?>

<header class="dashboard-intro bi-titlebar">
  <div>
    <span class="dashboard-eyebrow">Operational intelligence</span>
    <h1>Incident command dashboard</h1>
    <p>Monitor incident demand, response bottlenecks, priority, location concentration, and queue aging from one filtered view.</p>
  </div>
  <div class="dashboard-actions">
    <span>Refreshed <?= h(date('M j, Y · g:i A')) ?></span>
    <details class="dashboard-export-menu">
      <summary>Export data <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary>
      <div class="dashboard-export-options" aria-label="Choose export format">
        <a href="export.php?format=xlsx"><strong>Excel workbook</strong><small>Styled sheets with reporter, location, coordinates, and priority colors</small></a>
        <a href="export.php?format=pdf"><strong>PDF report</strong><small>Printable incident directory with complete response details</small></a>
      </div>
    </details>
    <a class="dashboard-refresh" href="<?= h($filterUrl([])) ?>">Refresh data</a>
  </div>
</header>

<?php if ($message): ?><div class="notice success" role="status"><?= h($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>

<section class="bi-slicer-panel" aria-labelledby="analytics-filter-title">
  <div class="bi-slicer-heading">
    <div><span class="visual-kicker">Report slicers</span><h2 id="analytics-filter-title">Filter the whole dashboard</h2></div>
    <span class="filter-count"><?= $activeFilterCount ?> active <?= $activeFilterCount === 1 ? 'filter' : 'filters' ?></span>
  </div>
  <form class="dashboard-filters bi-slicers" method="get">
    <label>Date range<select name="period"><option value="all" <?= $filters['period'] === 'all' ? 'selected' : '' ?>>All reports</option><option value="7" <?= $filters['period'] === '7' ? 'selected' : '' ?>>Last 7 days</option><option value="30" <?= $filters['period'] === '30' ? 'selected' : '' ?>>Last 30 days</option><option value="90" <?= $filters['period'] === '90' ? 'selected' : '' ?>>Last 90 days</option></select></label>
    <label>Disaster<select name="disaster"><option value="">All disasters</option><?php foreach ($filterOptions['disasters'] as $option): ?><option value="<?= h($option) ?>" <?= $filters['disaster'] === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
    <label>Priority<select name="legend"><option value="">All priorities</option><?php foreach (['Green', 'Orange', 'Red'] as $option): ?><option value="<?= h($option) ?>" <?= $filters['legend'] === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
    <label>Response stage<select name="status"><option value="">All stages</option><?php foreach ($statusOrder as $key => [$label]): ?><option value="<?= h($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
    <label>Municipality<select name="municipality"><option value="">All municipalities</option><?php foreach ($filterOptions['municipalities'] as $option): ?><option value="<?= h($option) ?>" <?= $filters['municipality'] === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
    <label>Barangay<select name="barangay"><option value="">All barangays</option><?php foreach ($filterOptions['barangays'] as $option): ?><option value="<?= h($option) ?>" <?= $filters['barangay'] === $option ? 'selected' : '' ?>><?= h($option) ?></option><?php endforeach; ?></select></label>
    <div class="filter-actions"><button type="submit">Apply slicers</button><a href="dashboard.php">Reset</a></div>
  </form>
  <?php if ($activeFilterCount > 0): ?><div class="active-filter-list" aria-label="Active filters"><?php foreach ($filters as $key => $value): if ($value === '' || ($key === 'period' && $value === 'all')) continue; ?><a href="<?= h($filterUrl([$key => $key === 'period' ? 'all' : ''])) ?>"><span><?= h($filterNames[$key] ?? ucfirst($key)) ?>:</span> <?= h($filterDisplay[$value] ?? $value) ?><b aria-hidden="true">×</b><span class="sr-only">Remove filter</span></a><?php endforeach; ?></div><?php endif; ?>
</section>

<section class="bi-context-row" aria-label="Selected report scope">
  <div><span>Selected scope</span><b><?= (int)$analytics['total'] ?> of <?= count($allReports) ?> reports</b></div>
  <div class="bi-operational-alert <?= h($operationalAlert[0]) ?>"><span aria-hidden="true"></span><b><?= h($operationalAlert[1]) ?></b></div>
</section>

<section class="bi-kpi-grid" aria-label="Key performance indicators">
  <article class="bi-kpi kpi-total"><span>Total reports</span><b><?= (int)$analytics['total'] ?></b><small>Submitted in selected scope</small></article>
  <article class="bi-kpi kpi-active"><span>Active queue</span><b><?= (int)$analytics['active'] ?></b><small><?= $percentage((int)$analytics['active'], max(1, (int)$analytics['total'])) ?>% remain unresolved</small></article>
  <article class="bi-kpi kpi-critical"><span>Critical active</span><b><?= (int)($analytics['criticalActive'] ?? 0) ?></b><small>Red-priority reports</small></article>
  <article class="bi-kpi kpi-review"><span>Awaiting verification</span><b><?= (int)($analytics['awaitingVerification'] ?? 0) ?></b><small>Still at received stage</small></article>
  <article class="bi-kpi kpi-team"><span>Without team</span><b><?= (int)($analytics['unassignedActive'] ?? 0) ?></b><small>Active reports unassigned</small></article>
  <article class="bi-kpi kpi-resolved"><span>Resolved</span><b><?= (int)($analytics['resolved'] ?? 0) ?></b><small><?= (int)($analytics['resolutionRate'] ?? 0) ?>% completion rate</small></article>
</section>

<section class="bi-visual-grid" aria-label="Interactive incident analytics">
  <article class="bi-visual bi-span-8 trend-visual">
    <header class="bi-visual-header"><div><span class="visual-kicker">Volume and priority</span><h2><?= h($trendLabel) ?></h2><p>Daily stacked priority volume with a total-incident trend line.</p></div><span class="visual-total"><?= array_sum(array_column($dailyCounts, 'count')) ?> reports</span></header>
    <?php bi_trend_chart($dailyCounts); ?>
  </article>

  <article class="bi-visual bi-span-4 pipeline-visual">
    <header class="bi-visual-header"><div><span class="visual-kicker">Current workload</span><h2>Response stage</h2><p>Where reports are now in the operational workflow.</p></div></header>
    <div class="bi-funnel">
      <?php foreach ($statusOrder as $status => [$label, $color]): $count = (int)($analytics['countsByStatus'][$status] ?? 0); $share = $percentage($count, (int)$analytics['total']); ?>
        <a href="<?= h($filterUrl(['status' => $status])) ?>" class="bi-funnel-row <?= $filters['status'] === $status ? 'selected' : '' ?>">
          <div><span><?= h($label) ?></span><b><?= $count ?></b></div>
          <span class="bi-funnel-track"><i style="width:<?= max($count > 0 ? 8 : 0, $share) ?>%;background:<?= h($color) ?>"></i></span>
          <small><?= $share ?>% of selected reports</small>
        </a>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="bi-visual bi-span-7 disaster-stack-visual">
    <header class="bi-visual-header"><div><span class="visual-kicker">Cross-filter visual</span><h2>Disaster demand by priority</h2><p>Select a row to filter the full dashboard by disaster.</p></div><div class="priority-legend"><span class="green">Green</span><span class="orange">Orange</span><span class="red">Red</span></div></header>
    <?php if (!$disasterPriority): ?><p class="dashboard-empty">Disaster demand appears after reports are submitted.</p><?php else: ?><div class="bi-stacked-list">
      <?php foreach ($disasterPriority as $disaster => $mix): ?>
        <a href="<?= h($filterUrl(['disaster' => (string)$disaster])) ?>" class="bi-stack-row <?= $filters['disaster'] === $disaster ? 'selected' : '' ?>">
          <div class="bi-stack-label"><b><?= h((string)$disaster) ?></b><span><?= (int)$mix['total'] ?> report<?= (int)$mix['total'] === 1 ? '' : 's' ?></span></div>
          <span class="bi-stack-track" aria-label="<?= h((string)$disaster) ?>: <?= (int)$mix['Green'] ?> green, <?= (int)$mix['Orange'] ?> orange, <?= (int)$mix['Red'] ?> red">
            <i class="green" style="width:<?= ((int)$mix['Green'] / $disasterHigh) * 100 ?>%"></i><i class="orange" style="width:<?= ((int)$mix['Orange'] / $disasterHigh) * 100 ?>%"></i><i class="red" style="width:<?= ((int)$mix['Red'] / $disasterHigh) * 100 ?>%"></i>
          </span>
        </a>
      <?php endforeach; ?>
    </div><?php endif; ?>
  </article>

  <article class="bi-visual bi-span-5 response-health-visual">
    <header class="bi-visual-header"><div><span class="visual-kicker">Conversion and timing</span><h2>Response performance</h2><p>Completion rates and measured time from submission.</p></div></header>
    <div class="bi-gauge-grid">
      <?php foreach ([['Verification', (int)($analytics['verificationRate'] ?? 0), '#3478c7'], ['Team assignment', (int)($analytics['assignmentRate'] ?? 0), '#8056b3'], ['Resolution', (int)($analytics['resolutionRate'] ?? 0), '#1d9064']] as [$label, $value, $color]): ?>
        <div class="bi-gauge" style="--value:<?= $value ?>;--gauge-color:<?= h($color) ?>"><div><b><?= $value ?>%</b></div><span><?= h($label) ?></span></div>
      <?php endforeach; ?>
    </div>
    <dl class="bi-time-grid">
      <div><dt>Average to verify</dt><dd><?= h(bi_duration($responseTimes['verificationMinutes'] ?? null)) ?></dd><small><?= (int)($responseTimes['verificationSamples'] ?? 0) ?> completed sample<?= (int)($responseTimes['verificationSamples'] ?? 0) === 1 ? '' : 's' ?></small></div>
      <div><dt>Average to dispatch</dt><dd><?= h(bi_duration($responseTimes['dispatchMinutes'] ?? null)) ?></dd><small><?= (int)($responseTimes['dispatchSamples'] ?? 0) ?> completed sample<?= (int)($responseTimes['dispatchSamples'] ?? 0) === 1 ? '' : 's' ?></small></div>
      <div><dt>Average to resolve</dt><dd><?= h(bi_duration($responseTimes['resolutionMinutes'] ?? null)) ?></dd><small><?= (int)($responseTimes['resolutionSamples'] ?? 0) ?> completed sample<?= (int)($responseTimes['resolutionSamples'] ?? 0) === 1 ? '' : 's' ?></small></div>
    </dl>
  </article>

  <article class="bi-visual bi-span-7 area-visual">
    <header class="bi-visual-header"><div><span class="visual-kicker">Geographic concentration</span><h2>Barangays requiring attention</h2><p>Ranked using report volume, active workload, and critical incidents.</p></div><span class="visual-total">Top <?= count($affectedAreas) ?></span></header>
    <?php if (!$affectedAreas): ?><p class="dashboard-empty">Area analytics appear after reports are submitted.</p><?php else: ?><div class="bi-area-table-wrap"><table class="bi-area-table"><thead><tr><th>#</th><th>Barangay</th><th>Reports</th><th>Active</th><th>Critical</th><th>Load score</th></tr></thead><tbody>
      <?php $areaHigh = max(1, ...array_column($affectedAreas, 'attentionScore')); foreach ($affectedAreas as $index => $area): ?><tr style="--area-share:<?= ((int)$area['attentionScore'] / $areaHigh) * 100 ?>%"><td><?= $index + 1 ?></td><td><a href="<?= h($filterUrl(['municipality' => (string)$area['municipality'], 'barangay' => (string)$area['barangay']])) ?>"><b><?= h((string)$area['barangay']) ?></b><small><?= h((string)$area['municipality']) ?><?= $area['province'] !== '' ? ', ' . h((string)$area['province']) : '' ?></small></a></td><td><?= (int)$area['total'] ?></td><td><?= (int)$area['active'] ?></td><td><span class="critical-count <?= (int)$area['critical'] > 0 ? 'has-critical' : '' ?>"><?= (int)$area['critical'] ?></span></td><td><b><?= (int)$area['attentionScore'] ?></b></td></tr><?php endforeach; ?>
    </tbody></table></div><p class="visual-footnote">Load score = reports + active reports + 2 × critical active reports. It is an operational workload indicator, not a hazard forecast.</p><?php endif; ?>
  </article>

  <article class="bi-visual bi-span-5 workload-visual">
    <header class="bi-visual-header"><div><span class="visual-kicker">Backlog health</span><h2>Active queue aging</h2><p>How long unresolved reports have remained open.</p></div><span class="visual-total">Oldest <?= (int)($analytics['oldestActiveHours'] ?? 0) ?> hr</span></header>
    <div class="bi-aging-list"><?php foreach ($ageBuckets as $label => $count): ?><div><div><span><?= h((string)$label) ?></span><b><?= (int)$count ?></b></div><span><i class="<?= in_array($label, ['1–3 days', 'Over 3 days'], true) ? 'late' : '' ?>" style="width:<?= ((int)$count / $ageHigh) * 100 ?>%"></i></span></div><?php endforeach; ?></div>
    <div class="bi-needs-block"><h3>Requested assistance</h3><?php if (!$needCounts): ?><p>No specific assistance needs in this scope.</p><?php else: ?><div class="bi-need-list"><?php foreach ($needCounts as $need => $count): ?><div><span title="<?= h((string)$need) ?>"><?= h((string)$need) ?></span><span><i style="width:<?= ((int)$count / $needHigh) * 100 ?>%"></i></span><b><?= (int)$count ?></b></div><?php endforeach; ?></div><?php endif; ?></div>
  </article>
</section>
