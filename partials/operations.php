<?php
$legendCounts = [
    'Red' => (int)($analytics['countsByLegend']['Red'] ?? 0),
    'Orange' => (int)($analytics['countsByLegend']['Orange'] ?? 0),
    'Green' => (int)($analytics['countsByLegend']['Green'] ?? 0),
];
$severityTotal = array_sum($legendCounts);
$percentage = static fn(int $value, int $total): int => $total > 0 ? (int)round(($value / $total) * 100) : 0;
$redEnd = $percentage($legendCounts['Red'], $severityTotal);
$orangeEnd = $redEnd + $percentage($legendCounts['Orange'], $severityTotal);
$affectedAreas = array_slice($analytics['affectedAreas'] ?? [], 0, 8);
$dailyCounts = $analytics['dailyCounts'] ?? [];
$dailyHigh = max(1, ...array_map(static fn(array $day): int => (int)$day['count'], $dailyCounts ?: [['count' => 1]]));
$weeklyTotal = array_sum(array_map(static fn(array $day): int => (int)$day['count'], $dailyCounts));
$busiestDay = $dailyCounts ? array_reduce($dailyCounts, static fn(?array $carry, array $day): array => $carry === null || (int)$day['count'] > (int)$carry['count'] ? $day : $carry) : null;
$statusOrder = [
    'received' => ['Received', '#3278e8'],
    'verified' => ['Verified', '#dc8618'],
    'dispatched' => ['Dispatched', '#7453b8'],
    'resolved' => ['Resolved', '#16845a'],
];
$statusRank = ['received' => 0, 'verified' => 1, 'dispatched' => 2, 'resolved' => 3];
?>
<main id="main-content" tabindex="-1" class="dashboard-shell">
  <header class="dashboard-intro">
    <div>
      <h1>Incident monitor</h1>
      <p>See the community risk picture first, then verify reports, assign response teams, and publish clear progress updates.</p>
    </div>
    <div class="dashboard-actions">
      <span>Updated <?= h(date('M j, Y · g:i A')) ?></span>
      <details class="dashboard-export-menu">
        <summary>Export reports <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4"/></svg></summary>
        <div class="dashboard-export-options" aria-label="Choose export format">
          <a href="export.php?format=xlsx"><strong>Excel workbook</strong><small>4 styled sheets with reporter, location, coordinates, filters, and priority colors</small></a>
          <a href="export.php?format=pdf"><strong>PDF report</strong><small>Readable directory plus complete incident, reporter, location, and response sections</small></a>
        </div>
      </details>
      <a class="dashboard-refresh" href="dashboard.php">Refresh data</a>
    </div>
  </header>

  <?php if ($message): ?>
    <div class="notice success" role="status"><?= h($message) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="notice error" role="alert"><?= h($error) ?></div>
  <?php endif; ?>

  <section class="dashboard-overview" aria-labelledby="overview-title">
    <div class="overview-lead">
      <h2 id="overview-title">Response overview</h2>
      <p>Current operational picture from <?= (int)$analytics['total'] ?> submitted <?= (int)$analytics['total'] === 1 ? 'report' : 'reports' ?>.</p>
      <small><?= (int)($analytics['unassignedActive'] ?? 0) ?> active <?= (int)($analytics['unassignedActive'] ?? 0) === 1 ? 'report needs' : 'reports need' ?> team assignment</small>
    </div>
    <article class="metric-card metric-total"><span>Total reports</span><b><?= (int)$analytics['total'] ?></b><small>All submitted records</small></article>
    <article class="metric-card metric-active"><span>Active queue</span><b><?= (int)$analytics['active'] ?></b><small>Not yet resolved</small></article>
    <article class="metric-card metric-critical"><span>Critical active</span><b><?= (int)($analytics['criticalActive'] ?? 0) ?></b><small>Red-priority reports</small></article>
    <article class="metric-card metric-dispatched"><span>Dispatched</span><b><?= (int)($analytics['dispatched'] ?? 0) ?></b><small>Response in motion</small></article>
    <article class="metric-card metric-resolved"><span>Resolved</span><b><?= (int)($analytics['resolved'] ?? 0) ?></b><small><?= (int)($analytics['resolutionRate'] ?? 0) ?>% of all reports</small></article>
  </section>

  <section class="dashboard-command-grid" aria-label="Incident analytics">
    <article class="dashboard-panel severity-panel">
      <div class="panel-title"><div><h2>Severity distribution</h2><p>Rapid-assessment priority across all reports.</p></div><span class="panel-total"><?= $severityTotal ?> assessed</span></div>
      <?php if ($severityTotal === 0): ?>
        <p class="dashboard-empty">Severity analytics will appear after the first report is submitted.</p>
      <?php else: ?>
        <div class="severity-content">
          <div class="severity-ring" style="--red-end:<?= $redEnd ?>%;--orange-end:<?= $orangeEnd ?>%" role="img" aria-label="<?= $legendCounts['Red'] ?> red, <?= $legendCounts['Orange'] ?> orange, and <?= $legendCounts['Green'] ?> green reports"><div><b><?= $severityTotal ?></b><span>Total</span></div></div>
          <dl class="severity-key">
            <?php foreach ($legendCounts as $legend => $value): ?><div class="severity-<?= strtolower($legend) ?>"><dt><span aria-hidden="true"></span><?= h($legend) ?></dt><dd><b><?= $value ?></b><small><?= $percentage($value, $severityTotal) ?>%</small></dd></div><?php endforeach; ?>
          </dl>
        </div>
      <?php endif; ?>
    </article>

    <article class="dashboard-panel trend-panel">
      <div class="panel-title"><div><h2>Seven-day activity</h2><p>Reports received per day, including zero-report days.</p></div></div>
      <?php if (!$dailyCounts): ?><p class="dashboard-empty">Daily activity is unavailable.</p><?php else: ?>
        <p class="chart-summary"><?= $weeklyTotal ?> <?= $weeklyTotal === 1 ? 'report' : 'reports' ?> in seven days<?= $busiestDay && (int)$busiestDay['count'] > 0 ? '; busiest day was ' . h($busiestDay['shortDate']) . ' with ' . (int)$busiestDay['count'] : '' ?>.</p>
        <ol class="daily-chart" aria-label="Reports submitted during the last seven days">
          <?php foreach ($dailyCounts as $day): $height = (int)round(((int)$day['count'] / $dailyHigh) * 100); ?><li class="daily-column" aria-label="<?= h($day['shortDate']) ?>: <?= (int)$day['count'] ?> reports"><b><?= (int)$day['count'] ?></b><span class="daily-track"><i style="height:<?= max(4, $height) ?>%"></i></span><strong><?= h($day['label']) ?></strong><small><span class="wide-date"><?= h($day['shortDate']) ?></span><span class="compact-date"><?= h(date('n/j', strtotime($day['date']))) ?></span></small></li><?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </article>

    <article class="dashboard-panel pipeline-panel">
      <div class="panel-title"><div><h2>Response pipeline</h2><p>Where reports currently sit in the response process.</p></div></div>
      <div class="pipeline-list">
        <?php foreach ($statusOrder as $status => [$label, $color]): $count = (int)($analytics['countsByStatus'][$status] ?? 0); ?><div class="pipeline-row"><div><span><?= h($label) ?></span><b><?= $count ?></b></div><span class="pipeline-track"><i style="width:<?= $percentage($count, max(1, (int)$analytics['total'])) ?>%;background:<?= h($color) ?>"></i></span><small><?= $percentage($count, (int)$analytics['total']) ?>% of all reports</small></div><?php endforeach; ?>
      </div>
    </article>

    <article class="dashboard-panel areas-panel">
      <div class="panel-title"><div><h2>Most affected barangays</h2><p>Top locations ranked by total submitted reports.</p></div><span class="panel-total">Top <?= count($affectedAreas) ?></span></div>
      <?php if (!$affectedAreas): ?><p class="dashboard-empty">Affected-area rankings will appear as reports are submitted.</p><?php else: ?>
        <div class="area-table-wrap"><table class="area-table"><thead><tr><th>Barangay</th><th>Total</th><th>Active</th><th>Critical</th></tr></thead><tbody>
          <?php foreach ($affectedAreas as $index => $area): ?><tr><td data-label="Barangay"><span class="area-rank"><?= $index + 1 ?></span><span><b><?= h($area['barangay']) ?></b><small><?= h($area['municipality']) ?><?= $area['province'] !== '' ? ', ' . h($area['province']) : '' ?></small></span></td><td data-label="Total"><b><?= (int)$area['total'] ?></b></td><td data-label="Active"><?= (int)$area['active'] ?></td><td data-label="Critical"><span class="critical-count <?= (int)$area['critical'] > 0 ? 'has-critical' : '' ?>"><?= (int)$area['critical'] ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </article>

    <?php chart_panel('Hazard mix', $analytics['countsByType'], ['#0b7770', '#3079c5', '#dd8a23', '#9b4f68', '#7554b5'], 'Frequency by reported incident type.'); ?>
  </section>

  <section class="dashboard-panel incident-panel" aria-labelledby="queue-title">
    <div class="panel-title queue-title"><div><h2 id="queue-title">Incident response queue</h2><p>Newest reports first. Open a report to review its assessment or change its operational status.</p></div><span class="secure-chip"><?= count($reports) ?> <?= count($reports) === 1 ? 'record' : 'records' ?></span></div>
    <?php if (!$reports): ?>
      <div class="dashboard-empty larger"><b>No incident reports yet</b><span>New public reports will appear here with their location and rapid-assessment context.</span></div>
    <?php else: ?>
      <p class="table-scroll-hint">Scroll sideways to see the full response queue on smaller screens.</p>
      <div class="queue-scroll" tabindex="0" role="region" aria-label="Incident reports, horizontally scrollable">
        <table class="incident-table">
          <thead><tr><th>Incident</th><th>Area</th><th>Priority</th><th>Response</th></tr></thead>
          <tbody>
          <?php foreach ($reports as $report):
              $state = $statusCopy[$report['status']] ?? $statusCopy['received'];
              $surveyDetails = ['impact' => $report['impact_detail'], 'situation' => $report['current_situation'], 'description' => $report['reporter_description'], 'reporter' => $report['reporter_name'], 'phone' => $report['contact_number'], 'evidence' => $report['evidence_note'] ?? null, 'particular' => $report['particular_type'] ?? '', 'latitude' => $report['latitude'] ?? '', 'longitude' => $report['longitude'] ?? '', 'coordinate_source' => $report['coordinate_source'] ?? '', 'water_level' => $report['water_level'] ?? '', 'water_trend' => $report['water_trend'] ?? '', 'road_passability' => $report['road_passability'] ?? '', 'people_stranded' => $report['people_stranded'] ?? '0', 'houses_affected' => $report['houses_affected'] ?? '0', 'households_affected' => $report['households_affected'] ?? '0', 'evacuation_needed' => $report['evacuation_needed'] ?? 'false', 'rescue_needed' => $report['rescue_needed'] ?? 'false'];
              $hasStoredEvidence = is_string($surveyDetails['evidence']) && preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/', $surveyDetails['evidence']) === 1;
              $currentRank = $statusRank[$report['status']] ?? 0;
          ?>
            <tr>
              <td data-label="Incident"><b class="incident-reference"><?= h($report['reference_code']) ?></b><p class="incident-summary"><?= h($report['summary']) ?></p><small><?= h(date('M j, Y · g:i A', strtotime($report['created_at']))) ?></small>
                <details class="survey-disclosure"><summary>View assessment details</summary><dl><div><dt>Disaster</dt><dd><?= h((string)$report['specific_type']) ?></dd></div><div><dt>Particular incident</dt><dd><?= h((string)($surveyDetails['particular'] ?: 'Not recorded')) ?></dd></div><div><dt>Impact</dt><dd><?= h((string)$surveyDetails['impact']) ?></dd></div><div><dt>Situation</dt><dd><?= h((string)$surveyDetails['situation']) ?></dd></div><div><dt>Description</dt><dd><?= h((string)$surveyDetails['description']) ?></dd></div><?php if ($report['specific_type'] === 'Flood'): ?><div><dt>Estimated flood depth</dt><dd><?= h(\ImSafe\Support\FloodLevels::describe((string)$surveyDetails['water_level'])) ?></dd></div><div><dt>Water and road conditions</dt><dd><?= h((string)($surveyDetails['water_trend'] ?: 'Not provided')) ?> · <?= h((string)($surveyDetails['road_passability'] ?: 'Not provided')) ?></dd></div><div><dt>Affected counts</dt><dd><?= (int)$surveyDetails['people_stranded'] ?> people stranded · <?= (int)$surveyDetails['houses_affected'] ?> houses · <?= (int)$surveyDetails['households_affected'] ?> households</dd></div><div><dt>Emergency action</dt><dd>Evacuation: <?= $surveyDetails['evacuation_needed'] === 'true' ? 'Yes' : 'No' ?> · Rescue: <?= $surveyDetails['rescue_needed'] === 'true' ? 'Yes' : 'No' ?></dd></div><?php endif; ?><div><dt>Reporter</dt><dd><?= h((string)($surveyDetails['reporter'] ?: 'Anonymous')) ?></dd></div><div><dt>Contact</dt><dd><?= h((string)($surveyDetails['phone'] ?: 'Not provided')) ?></dd></div><div><dt>Photo coordinates</dt><dd><?= $surveyDetails['latitude'] !== '' && $surveyDetails['longitude'] !== '' ? h($surveyDetails['latitude'] . ', ' . $surveyDetails['longitude'] . ($surveyDetails['coordinate_source'] !== '' ? ' · ' . $surveyDetails['coordinate_source'] : '')) : 'Not recorded' ?></dd></div><div><dt>Photo evidence</dt><dd><?php if ($hasStoredEvidence): ?><figure class="admin-evidence"><img src="evidence.php?file=<?= rawurlencode((string)$surveyDetails['evidence']) ?>" alt="Uploaded photo evidence for report <?= h((string)$report['reference_code']) ?>" loading="lazy" decoding="async"><figcaption>Uploaded photo evidence</figcaption></figure><?php elseif ($surveyDetails['evidence']): ?>Not stored (legacy report)<?php else: ?>Not provided<?php endif; ?></dd></div></dl></details>
              </td>
              <td data-label="Area"><b><?= h($report['barangay_name']) ?></b><p><?= h($report['municipality_name']) ?><br><?= h($report['province_name'] ?? '') ?><br><small><?= h($report['region_name']) ?></small></p></td>
              <td data-label="Priority"><span class="legend-badge <?= strtolower(h($report['legend'])) ?>"><?= h($report['legend']) ?></span><b class="hazard-name"><?= h($report['specific_type']) ?></b><small><?= h((string)($report['particular_type'] ?: 'Particular not recorded')) ?></small><small><?= h($report['general_type']) ?></small></td>
              <td data-label="Response"><span class="status-chip <?= h($state[1]) ?>"><?= h($state[0]) ?></span><p class="assignment"><small>Assigned team</small><b><?= h($report['team_name'] ?: 'Unassigned') ?></b></p><?php if (!empty($report['note'])): ?><p class="latest-note"><small>Latest public update</small><?= h($report['note']) ?></p><?php endif; ?>
                <details class="operation-disclosure"><summary>Review and update</summary><form class="operation-form dashboard-operation" method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="incident_id" value="<?= (int)$report['id'] ?>"><label>Status<select name="status"><?php foreach ($statusOrder as $status => [$label]): ?><option value="<?= h($status) ?>" <?= $report['status'] === $status ? 'selected' : '' ?> <?= ($statusRank[$status] ?? 0) < $currentRank ? 'disabled' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label><label>Dispatch team<select name="assigned_team"><option value="">Unassigned</option><?php foreach ($teams as $team): ?><option value="<?= h($team) ?>" <?= $report['team_name'] === $team ? 'selected' : '' ?>><?= h($team) ?></option><?php endforeach; ?></select></label><label>Public update<textarea name="status_note" maxlength="1200" placeholder="Add a progress update for the reporter"></textarea></label><button type="submit">Save update</button></form></details>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</main>
