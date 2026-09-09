<footer class="site-footer">
  <div class="footer-main">
    <div class="footer-brand"><a href="index.php">iMSafe v2.0<span aria-hidden="true">.</span></a><p>Community reporting.<br>Local response coordination.</p></div>
    <div><h2>Community</h2><a href="report.php">Report emergency</a><a href="track.php">Track a report</a><a href="announcements.php">Announcements</a><a href="account.php?mode=<?= current_local_user() ? 'reports' : 'signup' ?>"><?= current_local_user() ? 'My reports' : 'Create account' ?></a></div>
    <div><h2>Access</h2><a href="account.php?mode=login">Account login</a><p>Community Emergency Desk<br>Philippines</p></div>
  </div>
  <div class="footer-bottom"><span>© <?= date('Y') ?> iMSafe v2.0 Disaster Monitoring System</span><span>Community reporting · Local response coordination</span></div>
</footer>
<?php require __DIR__ . '/imassist.php'; ?>
<script src="assets/navigation.js?v=6" defer></script>
<script src="assets/imassist.js?v=2" defer></script>
