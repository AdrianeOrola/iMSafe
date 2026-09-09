<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$requestedMode = (string)($_GET['mode'] ?? 'login');
$mode = in_array($requestedMode, ['signup', 'reports'], true) ? $requestedMode : 'login';
$error = '';
$user = current_local_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        $action = (string)($_POST['action'] ?? 'login');
        if ($action === 'logout') {
            unset($_SESSION['imsafe_user']);
            unset($_SESSION['imsafe_auth_role']);
            session_regenerate_id(true);
            header('Location: index.php');
            exit;
        }
        $mode = ($_POST['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';
        $email = strtolower(trim((string)($_POST['email'] ?? ''))); $password = (string)($_POST['password'] ?? '');
        $isAdminEmail = $mode === 'login' && hash_equals(app()->config->adminEmail(), $email);
        $rateAction = $mode === 'signup' ? 'account-signup' : ($isAdminEmail ? 'admin-login' : 'account-login');
        $rateLimit = $isAdminEmail ? 5 : 10;
        if (!rate_limit($rateAction, $rateLimit, 600)) throw new RuntimeException('Too many sign-in attempts were made from this connection. Please wait a few minutes and try again.');
        if ($mode === 'signup') {
            $name = trim((string)($_POST['name'] ?? '')); $confirm = (string)($_POST['confirm_password'] ?? '');
            if (strlen($name) < 2 || strlen($name) > 120) throw new RuntimeException('Enter a name between 2 and 120 characters.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) throw new RuntimeException('Enter a valid email address.');
            if (hash_equals(app()->config->adminEmail(), $email)) throw new RuntimeException('This email address is reserved for administrator access.');
            if (strlen($password) < 8 || strlen($password) > 255) throw new RuntimeException('Use a password between 8 and 255 characters.');
            if (!hash_equals($password, $confirm)) throw new RuntimeException('Passwords do not match.');
            unset($_SESSION['imsafe_admin']);
            $_SESSION['imsafe_user'] = app()->accounts->create($name, $email, $password);
            $_SESSION['imsafe_auth_role'] = 'user';
            session_regenerate_id(true); header('Location: index.php?account=created'); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) throw new RuntimeException('Enter a valid email address.');
        if ($isAdminEmail) {
            if (app()->config->isDefaultAdminPassword()) throw new RuntimeException('Set IMSAFE_ADMIN_PASSWORD before signing in as an administrator.');
            if (!hash_equals(app()->config->adminPassword(), $password)) throw new RuntimeException('Email or password is not recognized.');
            unset($_SESSION['imsafe_user']);
            $_SESSION['imsafe_admin'] = true;
            $_SESSION['imsafe_auth_role'] = 'admin';
            session_regenerate_id(true); header('Location: dashboard.php'); exit;
        }
        $user = app()->accounts->authenticate($email, $password);
        if (!$user) throw new RuntimeException('Email or password is not recognized.');
        unset($_SESSION['imsafe_admin']);
        $_SESSION['imsafe_user'] = $user;
        $_SESSION['imsafe_auth_role'] = 'user';
        session_regenerate_id(true); header('Location: index.php?account=logged-in'); exit;
    } catch (Throwable $exception) {
        log_app_error($exception); $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'The account request could not be completed.';
    }
}

if ($mode === 'reports' && !$user) { header('Location: account.php?mode=login'); exit; }
$myReports = $mode === 'reports' && $user ? app()->incidents->forUser((int)$user['id']) : [];
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $mode === 'signup' ? 'Create account' : ($mode === 'reports' ? 'My reports' : 'Login') ?> · iMSafe v2.0</title><link rel="stylesheet" href="assets/app.css?v=5"><link rel="stylesheet" href="assets/navigation.css?v=9"><link rel="stylesheet" href="assets/dashboard.css?v=7"><link rel="stylesheet" href="assets/atmosphere.css?v=5"><link rel="stylesheet" href="assets/imassist.css?v=2"></head>
<body class="public-body">
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="main-content" tabindex="-1" class="dashboard-gate">
  <section>
    <?php if ($mode === 'reports'): ?>
      <span class="gate-icon" aria-hidden="true">◉</span><p class="section-kicker">Community account</p><h1>My reports</h1><p>Reports submitted while signed in are collected here. Anonymous reports remain available through their reference code.</p>
      <?php if (!$myReports): ?><div class="dashboard-empty larger"><b>No account-linked reports yet</b><span>Start a report while signed in to see its status here.</span></div><?php else: ?><div class="account-report-list"><?php foreach ($myReports as $report): ?><a href="track.php?ref=<?= rawurlencode($report['reference_code']) ?>"><b><?= h($report['reference_code']) ?></b><span><?= h($report['specific_type']) ?> · <?= h($report['barangay_name'] . ', ' . $report['municipality_name']) ?></span><small><?= h(ucfirst($report['status'])) ?> · <?= h(date('M j, Y · g:i A', strtotime($report['created_at']))) ?></small></a><?php endforeach; ?></div><?php endif; ?><a href="report.php">Submit another report</a>
    <?php else: ?>
      <span class="gate-icon" aria-hidden="true">◉</span><p class="section-kicker"><?= $mode === 'signup' ? 'Community account' : 'Secure account access' ?></p><h1><?= $mode === 'signup' ? 'Create your account' : 'Sign in to iMSafe v2.0' ?></h1><p><?= $mode === 'signup' ? 'Save a local community account for a more personal reporting experience. Emergency reporting remains available without an account.' : 'Enter your email and password. iMSafe v2.0 will open the correct area for your account.' ?></p>
      <?php if ($error): ?><div class="form-error" role="alert">⚠ <?= h($error) ?></div><?php endif; ?>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="mode" value="<?= h($mode) ?>"><input type="hidden" name="action" value="<?= $mode === 'signup' ? 'signup' : 'login' ?>"><?php if ($mode === 'signup'): ?><label for="name">Your name<input id="name" name="name" autocomplete="name" required value="<?= h((string)($_POST['name'] ?? '')) ?>"></label><?php endif; ?><label for="accountEmail">Email address<input id="accountEmail" type="email" name="email" autocomplete="email" required value="<?= h((string)($_POST['email'] ?? '')) ?>"></label><label for="accountPassword">Password<input id="accountPassword" type="password" name="password" autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>" required></label><?php if ($mode === 'signup'): ?><label for="confirmPassword">Confirm password<input id="confirmPassword" type="password" name="confirm_password" autocomplete="new-password" required></label><?php endif; ?><button type="submit"><?= $mode === 'signup' ? 'Create account' : 'Sign in' ?></button></form>
      <a href="account.php?mode=<?= $mode === 'signup' ? 'login' : 'signup' ?>"><?= $mode === 'signup' ? 'Already have an account? Sign in.' : 'Need a community account? Create one.' ?></a>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
