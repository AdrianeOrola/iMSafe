<section class="dashboard-panel incident-panel" aria-labelledby="queue-title">
  <div class="panel-title queue-title"><div><span class="visual-kicker">Drill-through records</span><h2 id="queue-title">Incident response queue</h2><p>Newest reports first. Open a record to inspect its assessment or update its response stage.</p></div><span class="secure-chip"><?= count($reports) ?> <?= count($reports) === 1 ? 'record' : 'records' ?></span></div>
  <?php if (!$reports): ?>
    <div class="dashboard-empty larger"><b>No matching incident reports</b><span>Adjust the dashboard slicers or wait for a new public report.</span></div>
  <?php else: ?>
    <p class="table-scroll-hint">Scroll sideways to see the full response queue on smaller screens.</p>
    <div class="queue-scroll" tabindex="0" role="region" aria-label="Incident reports, horizontally scrollable">
      <table class="incident-table">
        <thead><tr><th>Incident</th><th>Area</th><th>Priority</th><th>Response</th></tr></thead>
        <tbody>
        <?php foreach ($reports as $report):
            $state = $statusCopy[$report['status']] ?? $statusCopy['received'];
            $recordDetails = is_array($report['details'] ?? null) ? $report['details'] : [];
            $surveyDetails = ['impact' => $report['impact_detail'], 'situation' => $report['current_situation'], 'description' => $report['reporter_description'], 'reporter' => $report['reporter_name'], 'phone' => $report['contact_number'], 'evidence' => $report['evidence_note'] ?? null, 'particular' => $recordDetails['particular_type'] ?? '', 'latitude' => $recordDetails['latitude'] ?? '', 'longitude' => $recordDetails['longitude'] ?? '', 'coordinate_source' => $recordDetails['coordinate_source'] ?? ''];
            $situationRows = \ImSafe\Support\DisasterCatalog::displayDetails((string)$report['specific_type'], $recordDetails);
            $displaySummary = $report['legend'] . ' priority | ' . $report['specific_type'] . ($surveyDetails['particular'] !== '' ? ': ' . $surveyDetails['particular'] : '') . ' | ' . $report['barangay_name'] . ', ' . $report['municipality_name'];
            $hasStoredEvidence = is_string($surveyDetails['evidence']) && preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/', $surveyDetails['evidence']) === 1;
            $currentRank = $statusRank[$report['status']] ?? 0;
        ?>
          <tr>
            <td data-label="Incident"><b class="incident-reference"><?= h($report['reference_code']) ?></b><p class="incident-summary"><?= h($displaySummary) ?></p><small><?= h(date('M j, Y · g:i A', strtotime($report['created_at']))) ?></small>
              <details class="survey-disclosure"><summary>View assessment details</summary><dl>
                <div><dt>Disaster group</dt><dd><?= h((string)$report['general_type']) ?></dd></div>
                <div><dt>Particular disaster</dt><dd><?= h((string)$report['specific_type']) ?></dd></div>
                <div><dt>Observed effect</dt><dd><?= h((string)($surveyDetails['particular'] ?: 'Not recorded')) ?></dd></div>
                <?php foreach ($situationRows as $detailRow): ?><div><dt><?= h($detailRow['label']) ?></dt><dd><?= h($detailRow['value']) ?></dd></div><?php endforeach; ?>
                <div><dt>Overall impact</dt><dd><?= h((string)$surveyDetails['impact']) ?></dd></div>
                <div><dt>Response situation</dt><dd><?= h((string)$surveyDetails['situation']) ?></dd></div>
                <div><dt>Description</dt><dd><?= h((string)$surveyDetails['description']) ?></dd></div>
                <div><dt>Reporter</dt><dd><?= h((string)($surveyDetails['reporter'] ?: 'Anonymous')) ?></dd></div>
                <div><dt>Contact</dt><dd><?= h((string)($surveyDetails['phone'] ?: 'Not provided')) ?></dd></div>
                <div><dt>Photo coordinates</dt><dd><?= $surveyDetails['latitude'] !== '' && $surveyDetails['longitude'] !== '' ? h($surveyDetails['latitude'] . ', ' . $surveyDetails['longitude'] . ($surveyDetails['coordinate_source'] !== '' ? ' | ' . $surveyDetails['coordinate_source'] : '')) : 'Not recorded' ?></dd></div>
                <div><dt>Photo evidence</dt><dd><?php if ($hasStoredEvidence): ?><figure class="admin-evidence"><img src="evidence.php?file=<?= rawurlencode((string)$surveyDetails['evidence']) ?>" alt="Uploaded photo evidence for report <?= h((string)$report['reference_code']) ?>" loading="lazy" decoding="async"><figcaption>Uploaded photo evidence</figcaption></figure><?php elseif ($surveyDetails['evidence']): ?>Not stored (legacy report)<?php else: ?>Not provided<?php endif; ?></dd></div>
              </dl></details>
            </td>
            <td data-label="Area"><b><?= h($report['barangay_name']) ?></b><p><?= h($report['municipality_name']) ?><br><?= h($report['province_name'] ?? '') ?><br><small><?= h($report['region_name']) ?></small></p></td>
            <td data-label="Priority"><span class="legend-badge <?= strtolower(h($report['legend'])) ?>"><?= h($report['legend']) ?></span><b class="hazard-name"><?= h($report['specific_type']) ?></b><small><?= h((string)($surveyDetails['particular'] ?: 'Effect not recorded')) ?></small><small><?= h($report['general_type']) ?></small></td>
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
