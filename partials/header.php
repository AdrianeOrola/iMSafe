<?php
$page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navCurrent = static fn(string $route): string => $page === $route ? ' aria-current="page"' : '';
$signedInUser = current_local_user();
$identityName = $signedInUser ? (string)($signedInUser['name'] ?? $signedInUser['display_name'] ?? '') : (is_admin() ? 'Administrator' : '');
$identityInitial = $identityName !== '' ? strtoupper(substr($identityName, 0, 1)) : '';
?>
<!-- THESIS: A vivid disaster-response identity frames a calm, readable reporting workflow.
OWN-WORLD: Storm navy, cyan water, rescue orange, condensed headings, credited real archive photographs.
STORY: Observe, report, coordinate, track; keep the community's existing content.
FIRST VIEWPORT: Clear shared navigation; strong left headline, right disaster/relief photo gallery; report action is prominent.
FORM: User explicitly requested a lively disaster-related redesign after rejecting the plain light theme.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance -->
<a class="skip-link" href="#main-content">Skip to main content</a>
<header class="app-header">
  <div class="header-inner">
    <a class="brand" href="index.php" aria-label="iMSafe v2.0 home">
      <span class="brand-symbol" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M16 3 27 7v9c0 6-6 10-11 13C11 26 5 22 5 16V7L16 3Z" stroke="currentColor" stroke-width="2"/><path d="M8 17h5l3-7 3 12 3-5h3" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></span>
      <span>iMSafe v2.0<small>Community Emergency Desk</small></span>
    </a>
    <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2"/></svg><span>Menu</span></button>
    <nav id="primary-navigation" aria-label="Main navigation">
      <a href="index.php"<?= $navCurrent('index.php') ?>>Home</a>
      <a href="track.php"<?= $navCurrent('track.php') ?>>Track report</a>
      <a href="announcements.php"<?= $navCurrent('announcements.php') ?>>Announcements</a>
      <?php if (is_admin()): ?>
        <a href="dashboard.php"<?= $navCurrent('dashboard.php') ?>>Dashboard</a>
      <?php endif; ?>
      <?php if ($signedInUser): ?>
        <a href="account.php?mode=reports"<?= $navCurrent('account.php') ?>>My reports</a>
      <?php elseif (!is_admin()): ?>
        <a href="account.php?mode=signup"<?= $page === 'account.php' && ($mode ?? '') === 'signup' ? ' aria-current="page"' : '' ?>>Create account</a>
        <a href="account.php?mode=login"<?= $page === 'account.php' && ($mode ?? '') === 'login' ? ' aria-current="page"' : '' ?>>Login</a>
      <?php endif; ?>
    </nav>
    <?php if ($identityName !== ''): ?>
      <details class="account-menu">
        <summary aria-label="Account menu for <?= h($identityName) ?>"><span class="account-avatar" aria-hidden="true"><?= h($identityInitial) ?></span><span class="account-name"><?= h($identityName) ?></span><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m4 6 4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
        <div class="account-popover">
          <strong><?= h($identityName) ?></strong>
          <span><?= $signedInUser ? 'Community account' : 'Administrator account' ?></span>
          <?php if (is_admin()): ?><a class="account-workspace" href="dashboard.php">Open admin dashboard</a><?php endif; ?>
          <form method="post" action="<?= $signedInUser ? 'account.php' : 'dashboard.php' ?>"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button type="submit">Sign out</button></form>
        </div>
      </details>
    <?php endif; ?>
    <a class="header-report" href="report.php"<?= $navCurrent('report.php') ?>>Report emergency <span aria-hidden="true">↗</span></a>
  </div>
</header>
