<?php
$pagasaUnavailable = !empty($pagasa['unavailable']);
$pagasaCached = !$pagasaUnavailable && !empty($pagasa['fromCache']);
$pagasaStale = $pagasaCached && !empty($pagasa['stale']);
$pagasaState = $pagasaUnavailable ? 'Unavailable' : ($pagasaCached ? ($pagasaStale ? 'Older cached copy' : 'Recent cached copy') : 'Source reached');
$pagasaStateClass = $pagasaUnavailable ? 'unavailable' : ($pagasaCached ? 'cached' : 'available');
$gdacsUnavailable = !empty($advisories['unavailable']);
$gdacsCached = !$gdacsUnavailable && !empty($advisories['fromCache']);
$gdacsStale = $gdacsCached && !empty($advisories['stale']);
$gdacsState = $gdacsUnavailable ? 'Unavailable' : ($gdacsCached ? ($gdacsStale ? 'Older cached copy' : 'Recent cached copy') : 'Source reached');
$gdacsStateClass = $gdacsUnavailable ? 'unavailable' : ($gdacsCached ? 'cached' : 'available');
$gdacsItems = $advisories['items'] ?? [];
$philippineSignals = count(array_filter($gdacsItems, static fn(array $item): bool => stripos((string)($item['title'] ?? ''), 'philippin') !== false));
$formatSourceTime = static function (?string $value): string {
    if (!$value) return 'Not provided by source';
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y · g:i A') . ' PHT';
    } catch (Throwable) {
        return $value;
    }
};
?>
<main id="main-content" tabindex="-1" class="announcements-shell">
  <header class="announcements-intro">
    <div><h1>Official-source announcements</h1><p>Latest available weather and hazard information from official government and international disaster sources.</p></div>
    <div class="announcement-actions"><span>Page opened <?= h($checkedAt) ?> PHT</span><span id="sourceRefreshStatus" role="status" aria-live="polite">Showing saved data immediately. Checking official feeds in the background.</span><a id="refreshSources" href="announcements.php">Refresh sources</a></div>
  </header>

  <section class="source-assurance" aria-label="Source policy">
    <strong>Official-source policy</strong>
    <p>iMSafe v2.0 shows the issuing agency, source timestamp, and connection state. Cached information is labeled and should be confirmed at the linked official portal before operational use.</p>
  </section>

  <section class="source-status-grid" aria-label="Feed connection status">
    <article><span class="source-dot <?= h($pagasaStateClass) ?>" aria-hidden="true"></span><div><h2>DOST-PAGASA</h2><p><?= h($pagasaState) ?> · Issued <?= h($pagasa['issuedAt'] ?? 'time unavailable') ?> PHT</p></div><b><?= count($pagasa['conditions'] ?? []) ?> forecast areas</b></article>
    <article><span class="source-dot <?= h($gdacsStateClass) ?>" aria-hidden="true"></span><div><h2>GDACS</h2><p><?= h($gdacsState) ?> · Refreshed <?= h($formatSourceTime($advisories['refreshedAt'] ?? null)) ?></p></div><b><?= count($gdacsItems) ?> signals</b></article>
    <article><span class="source-dot directory" aria-hidden="true"></span><div><h2>Official directory</h2><p>Direct access to Philippine weather, seismic, volcanic, tsunami and disaster reports.</p></div><b><?= count($officialSources) ?> portals</b></article>
  </section>

  <section class="announcement-feed-grid" aria-label="Latest source announcements">
    <article class="announcement-panel weather-feed">
      <header><div><h2>Philippine weather outlook</h2><p>DOST-PAGASA national forecast and stated possible impacts.</p></div><span class="source-badge <?= h($pagasaStateClass) ?>"><?= h($pagasaState) ?></span></header>
      <div class="feed-content">
        <dl class="feed-meta"><div><dt>Issuing agency</dt><dd>DOST-PAGASA</dd></div><div><dt>Source issue time</dt><dd><?= h($pagasa['issuedAt'] ?? 'Unavailable') ?> PHT</dd></div></dl>
        <h3>Synopsis</h3><p class="weather-synopsis"><?= h($pagasa['synopsis'] ?? 'The official forecast is unavailable right now.') ?></p>
        <?php if (!empty($pagasa['conditions'])): ?><div class="condition-list"><?php foreach (array_slice($pagasa['conditions'], 0, 6) as $condition): ?><section><h3><?= h($condition['place']) ?></h3><p><strong><?= h($condition['condition']) ?></strong></p><p><?= h($condition['impacts']) ?></p></section><?php endforeach; ?></div><?php elseif ($pagasaUnavailable): ?><p class="feed-empty">PAGASA could not be reached and no usable cached forecast is available.</p><?php else: ?><p class="feed-empty">The latest successful PAGASA response did not include area conditions.</p><?php endif; ?>
        <a class="source-link" href="<?= h($pagasa['sourceUrl'] ?? 'https://www.pagasa.dost.gov.ph/weather') ?>" target="_blank" rel="noopener noreferrer">Open the official PAGASA forecast</a>
      </div>
    </article>

    <article class="announcement-panel hazard-feed">
      <header><div><h2>Global hazard signals</h2><p>GDACS signals with Philippine mentions identified for faster scanning.</p></div><span class="source-badge <?= h($gdacsStateClass) ?>"><?= h($gdacsState) ?></span></header>
      <div class="hazard-summary"><div><b><?= count($gdacsItems) ?></b><span>Latest signals</span></div><div><b><?= $philippineSignals ?></b><span>Mention Philippines</span></div></div>
      <?php if ($gdacsItems): ?><div class="signal-list"><?php foreach (array_slice($gdacsItems, 0, 8) as $item): $mentionsPhilippines = stripos((string)$item['title'], 'philippin') !== false; ?><a href="<?= h($item['url'] ?: 'https://www.gdacs.org/') ?>" target="_blank" rel="noopener noreferrer"><span class="signal-flags"><i class="signal-level <?= h($item['level']) ?>"><?= h(ucfirst($item['level'])) ?></i><?php if ($mentionsPhilippines): ?><i class="philippines-flag">Philippines</i><?php endif; ?></span><b><?= h($item['title']) ?></b><small><?= h($item['hazard']) ?> · Published <?= h($formatSourceTime($item['publishedAt'])) ?></small></a><?php endforeach; ?></div><?php elseif ($gdacsUnavailable): ?><p class="feed-empty">GDACS is unavailable and no usable cached signals are available.</p><?php else: ?><p class="feed-empty">No signals were returned in the latest successful GDACS refresh.</p><?php endif; ?>
      <div class="hazard-source-link"><a class="source-link" href="https://www.gdacs.org/" target="_blank" rel="noopener noreferrer">Open the official GDACS map</a></div>
    </article>
  </section>

  <section class="source-directory" aria-labelledby="source-directory-title">
    <header><h2 id="source-directory-title">Official emergency information directory</h2><p>Use these original agency portals to confirm warnings and obtain bulletins not reproduced inside iMSafe v2.0.</p></header>
    <div><?php foreach ($officialSources as $source): ?><a href="<?= h($source['url']) ?>" target="_blank" rel="noopener noreferrer"><b><?= h($source['agency']) ?></b><span><?= h($source['scope']) ?></span><small>Open official portal</small></a><?php endforeach; ?></div>
  </section>
</main>
