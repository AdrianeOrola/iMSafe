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
            $profileValue = static fn(string $key): string => trim((string)preg_replace('/[[:cntrl:]]/', '', (string)($_POST[$key] ?? '')));
            $profile = [
                'region' => $profileValue('region'),
                'province' => $profileValue('province'),
                'cityMunicipality' => $profileValue('city_municipality'),
                'barangay' => $profileValue('barangay'),
                'houseStreet' => $profileValue('house_street'),
                'nearbyLandmark' => $profileValue('nearby_landmark'),
                'primaryContact' => $profileValue('primary_contact'),
                'alternateContact' => $profileValue('alternate_contact'),
            ];
            foreach (['region', 'cityMunicipality', 'barangay'] as $field) if (!preg_match('/^\\d{10}$/', $profile[$field])) throw new RuntimeException('Select your region, province, city or municipality, and barangay.');
            if ($profile['province'] !== 'none' && !preg_match('/^\\d{10}$/', $profile['province'])) throw new RuntimeException('Select your region, province, city or municipality, and barangay.');
            foreach (['houseStreet' => 160, 'nearbyLandmark' => 255, 'primaryContact' => 32, 'alternateContact' => 32] as $field => $max) if (strlen($profile[$field]) > $max) throw new RuntimeException('One or more profile details are longer than allowed. Shorten the text and try again.');
            if (!preg_match('/^[0-9+() .-]{7,32}$/', $profile['primaryContact'])) throw new RuntimeException('Enter a valid primary contact number.');
            if ($profile['alternateContact'] !== '' && !preg_match('/^[0-9+() .-]{7,32}$/', $profile['alternateContact'])) throw new RuntimeException('Enter a valid alternate contact number or leave it blank.');
            app()->locations->assertSelection($profile['region'], $profileValue('region_name'), $profile['cityMunicipality'], $profileValue('city_municipality_name'), $profile['barangay'], $profileValue('barangay_name'), $profile['province'], $profileValue('province_name'));
            unset($_SESSION['imsafe_admin']);
            $_SESSION['imsafe_user'] = app()->accounts->create($name, $email, $password, $profile);
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
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $mode === 'signup' ? 'Create account' : ($mode === 'reports' ? 'My reports' : 'Login') ?> · iMSafe v2.0</title><link rel="stylesheet" href="assets/app.css?v=5"><link rel="stylesheet" href="assets/navigation.css?v=9"><link rel="stylesheet" href="assets/dashboard.css?v=7"><link rel="stylesheet" href="assets/atmosphere.css?v=5"><link rel="stylesheet" href="assets/imassist.css?v=5"></head>
<body class="public-body">
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="main-content" tabindex="-1" class="dashboard-gate<?= $mode === 'signup' ? ' signup-gate' : '' ?>">
  <section>
    <?php if ($mode === 'reports'): ?>
      <span class="gate-icon" aria-hidden="true">◉</span><p class="section-kicker">Community account</p><h1>My reports</h1><p>Reports submitted while signed in are collected here. Anonymous reports remain available through their reference code.</p>
      <?php if (!$myReports): ?><div class="dashboard-empty larger"><b>No account-linked reports yet</b><span>Start a report while signed in to see its status here.</span></div><?php else: ?><div class="account-report-list"><?php foreach ($myReports as $report): ?><a href="track.php?ref=<?= rawurlencode($report['reference_code']) ?>"><b><?= h($report['reference_code']) ?></b><span><?= h($report['specific_type']) ?> · <?= h($report['barangay_name'] . ', ' . $report['municipality_name']) ?></span><small><?= h(ucfirst($report['status'])) ?> · <?= h(date('M j, Y · g:i A', strtotime($report['created_at']))) ?></small></a><?php endforeach; ?></div><?php endif; ?><a href="report.php">Submit another report</a>
    <?php else: ?>
      <span class="gate-icon" aria-hidden="true">◉</span><p class="section-kicker"><?= $mode === 'signup' ? 'Community account' : 'Secure account access' ?></p><h1><?= $mode === 'signup' ? 'Create your account' : 'Sign in to iMSafe v2.0' ?></h1><p><?= $mode === 'signup' ? 'Save a local community account for a more personal reporting experience. Emergency reporting remains available without an account.' : 'Enter your email and password. iMSafe v2.0 will open the correct area for your account.' ?></p>
      <?php if ($error): ?><div class="form-error" role="alert">⚠ <?= h($error) ?></div><?php endif; ?>
      <form method="post"<?= $mode === 'signup' ? ' id="signupForm" class="signup-form"' : '' ?>><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="mode" value="<?= h($mode) ?>"><input type="hidden" name="action" value="<?= $mode === 'signup' ? 'signup' : 'login' ?>"><?php if ($mode === 'signup'): ?>
        <div class="form-grid two account-form-grid"><label for="name">Your name<input id="name" name="name" autocomplete="name" required value="<?= h((string)($_POST['name'] ?? '')) ?>"></label><label for="accountEmail">Email address<input id="accountEmail" type="email" name="email" autocomplete="email" required value="<?= h((string)($_POST['email'] ?? '')) ?>"></label><label for="accountPassword">Password<input id="accountPassword" type="password" name="password" autocomplete="new-password" required></label><label for="confirmPassword">Confirm password<input id="confirmPassword" type="password" name="confirm_password" autocomplete="new-password" required></label></div>
        <section class="account-form-section"><h2>Location record</h2><p>Use Philippine reference data to save your usual reporting location.</p><noscript><p class="form-error">JavaScript is needed to load the Philippine location lists. Enable JavaScript, then reload this page.</p></noscript><div class="form-grid two account-form-grid"><label for="signupRegion">Region<select id="signupRegion" name="region" required aria-describedby="signupLocationMeta signupLocationError"><option value="">Loading regions...</option></select><input id="signupRegionName" type="hidden" name="region_name" value="<?= h((string)($_POST['region_name'] ?? '')) ?>"></label><label for="signupProvince">Province<select id="signupProvince" name="province" disabled required aria-describedby="signupLocationMeta signupLocationError"><option value="">Select region first</option></select><input id="signupProvinceName" type="hidden" name="province_name" value="<?= h((string)($_POST['province_name'] ?? '')) ?>"></label><label for="signupMunicipality">City / municipality<select id="signupMunicipality" name="city_municipality" disabled required aria-describedby="signupLocationMeta signupLocationError"><option value="">Select province first</option></select><input id="signupMunicipalityName" type="hidden" name="city_municipality_name" value="<?= h((string)($_POST['city_municipality_name'] ?? '')) ?>"></label><label for="signupBarangay">Barangay<select id="signupBarangay" name="barangay" disabled required aria-describedby="signupLocationMeta signupLocationError"><option value="">Select city / municipality first</option></select><input id="signupBarangayName" type="hidden" name="barangay_name" value="<?= h((string)($_POST['barangay_name'] ?? '')) ?>"></label><label for="houseStreet">House no. / street<input id="houseStreet" name="house_street" autocomplete="street-address" placeholder="Optional" value="<?= h((string)($_POST['house_street'] ?? '')) ?>"></label><label for="nearbyLandmark">Nearby landmark<input id="nearbyLandmark" name="nearby_landmark" placeholder="Optional" value="<?= h((string)($_POST['nearby_landmark'] ?? '')) ?>"></label></div><p id="signupLocationMeta" class="provider-status" aria-live="polite">Loading Philippine geographic reference data...</p><p id="signupLocationError" class="inline-error" role="alert" hidden></p><button id="retrySignupLocations" class="location-retry" type="button" hidden>Retry location list</button></section>
        <section class="account-form-section"><h2>Contact information</h2><p>A primary contact number is kept with your account so emergency reports can be prepared faster.</p><div class="form-grid two account-form-grid"><label for="primaryContact">Primary contact number<input id="primaryContact" name="primary_contact" inputmode="tel" autocomplete="tel" required value="<?= h((string)($_POST['primary_contact'] ?? '')) ?>"></label><label for="alternateContact">Alternate contact number<input id="alternateContact" name="alternate_contact" inputmode="tel" autocomplete="tel" placeholder="Optional" value="<?= h((string)($_POST['alternate_contact'] ?? '')) ?>"></label></div></section>
     <br>
        <?php else: ?><label for="accountEmail">Email address<input id="accountEmail" type="email" name="email" autocomplete="email" required value="<?= h((string)($_POST['email'] ?? '')) ?>"></label><label for="accountPassword">Password<input id="accountPassword" type="password" name="password" autocomplete="current-password" required></label><?php endif; ?><button type="submit"><?= $mode === 'signup' ? 'Create account' : 'Sign in' ?></button></form>
      <a href="account.php?mode=<?= $mode === 'signup' ? 'login' : 'signup' ?>"><?= $mode === 'signup' ? 'Already have an account? Sign in.' : 'Need a community account? Create one.' ?></a>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<?php if ($mode === 'signup'): ?><script>window.imSafeSignupValues=<?= json_encode(array_intersect_key($_POST, array_flip(['region', 'province', 'city_municipality', 'barangay'])), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script><script src="assets/account.js?v=1"></script><?php endif; ?>
</body>
</html>
