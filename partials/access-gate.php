<main id="main-content" tabindex="-1" class="dashboard-gate">
<section>
<span class="gate-icon" aria-hidden="true">♜</span>
<p class="section-kicker">Restricted workspace</p>
<h1>Incident monitoring workspace</h1>
<p>This view is restricted to authorized iMSafe v2.0 monitoring administrators. Public emergency reporting remains available without an account.</p>
<?php if ($error): ?>
<div class="form-error" role="alert">⚠ <?= h($error) ?>
</div>
<?php endif; ?>
<form method="post" action="account.php">
<input type="hidden" name="action" value="login">
<input type="hidden" name="mode" value="login">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<label for="adminEmail">Email address<input id="adminEmail" name="email" type="email" autocomplete="email" required autofocus>
</label>
<label for="adminPassword">Password<input id="adminPassword" name="password" type="password" autocomplete="current-password" required>
</label>
<button type="submit">Sign in</button>
</form>
<a href="report.php">Report an emergency instead</a>
</section>
</main>
