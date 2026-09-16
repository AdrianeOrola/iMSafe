<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$error = '';
$signedInUser = current_local_user();
$profile = $signedInUser ? app()->accounts->profile((int)$signedInUser['id']) : null;
$profileDefaults = $profile ? [
    'region_code' => $profile['region'] ?? '',
    'province_code' => $profile['province'] ?? '',
    'municipality_code' => $profile['city_municipality'] ?? '',
    'barangay_code' => $profile['barangay'] ?? '',
    'house_number' => $profile['house_street'] ?? '',
    'nearby_landmark' => $profile['nearby_landmark'] ?? '',
    'contact_number' => $profile['primary_contact'] ?? '',
    'alternate_contact' => $profile['alternate_contact'] ?? '',
    'reporter_name' => $profile['display_name'] ?? '',
    'email' => $profile['email'] ?? '',
] : [];
$formData = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $profileDefaults;
$confirmation = strtoupper(trim((string)($_GET['submitted'] ?? '')));
$old = static fn(string $key, string $default = ''): string => h((string)($formData[$key] ?? $default));
$disasterCatalog = \ImSafe\Support\DisasterCatalog::publicCatalog();
$restoreFields = array_merge(['legend', 'general_type', 'specific_type', 'particular_type', 'region_code', 'region_name', 'province_code', 'province_name', 'needs', 'municipality_code', 'municipality_name', 'barangay_code', 'barangay_name', 'house_number', 'nearby_landmark', 'contact_number', 'alternate_contact', 'reporter_name', 'email', 'impact_detail', 'current_situation', 'reporter_description'], \ImSafe\Support\DisasterCatalog::detailKeys());
$restoreData = array_intersect_key($formData, array_flip($restoreFields));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        if (!rate_limit('report-submit', 10, 600)) throw new RuntimeException('Too many reports were submitted from this connection. Please wait a few minutes and try again.');
        $reference = app()->incidentController->submit($_POST, $_FILES, current_local_user()['id'] ?? null);
        header('Location: report.php?submitted=' . rawurlencode($reference));
        exit;
    } catch (Throwable $exception) {
        log_app_error($exception);
        $error = $exception instanceof RuntimeException && !$exception instanceof PDOException ? $exception->getMessage() : 'Your report could not be saved right now. Your answers are kept below; please try again.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rapid Assessment · iMSafe v2.0</title>
  <link rel="stylesheet" href="assets/app.css?v=14"><link rel="stylesheet" href="assets/navigation.css?v=9">
<link rel="stylesheet" href="assets/atmosphere.css?v=5"><link rel="stylesheet" href="assets/imassist.css?v=5"></head>
<body class="public-body">
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="main-content" tabindex="-1" class="assessment-layout">
  <aside class="assessment-intro">
    <p class="section-kicker">Community response protocol</p>
    <h1>Rapid assessment,<br> <span>made for action.</span></h1>
    <p>Start with the community status. Your Green, Orange, or Red choice guides the questions and response options that follow.</p>
    <ol class="flow-list"><li><b>01</b><span><strong>Set the status</strong> based on the current level of danger.</span></li><li><b>02</b><span><strong>Classify and describe</strong> the disaster using status-guided questions.</span></li><li><b>03</b><span><strong>Locate and send</strong> the report to the response queue.</span></li></ol>
    <div class="trust-note">✓ Anonymous reporting is supported. Contact details are optional.</div>
  </aside>
  <section class="assessment-card">
    <div class="card-heading"><div><p>Rapid assessment</p><h2>Incident intake</h2></div><span>* Required</span></div>
    <?php if ($error !== ''): ?><div class="form-error" role="alert">⚠ <?= h($error) ?></div><?php endif; ?>
    <?php if ($confirmation !== ''): ?>
      <div class="rapid-confirmation"><span aria-hidden="true">✓</span><h2>Assessment received</h2><p>Your report is now in the secure monitoring queue. Use this reference code to check progress and response updates at any time.</p><b><?= h($confirmation) ?></b><div><a href="track.php?ref=<?= rawurlencode($confirmation) ?>">Track this report</a><a href="index.php">Return home</a></div></div>
    <?php else: ?>
      <noscript><p class="form-error">JavaScript is needed to load the Philippine location lists and assessment questions. Enable JavaScript, then reload this page.</p></noscript><form id="rapidForm" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <section class="survey-section priority-section status-first"><div class="section-head"><b>01</b><div><h3>Community status</h3><p>Choose the current danger level first. The rest of the form will adapt to this status.</p></div></div><div class="legend-grid"><label class="legend-choice green"><input type="radio" name="legend" required value="Green" <?= ($formData['legend'] ?? '') === 'Green' ? 'checked' : '' ?>><span><i aria-hidden="true"></i><strong>Green</strong><small>Stable or monitoring</small></span></label><label class="legend-choice orange"><input type="radio" name="legend" value="Orange" <?= ($formData['legend'] ?? '') === 'Orange' ? 'checked' : '' ?>><span><i aria-hidden="true"></i><strong>Orange</strong><small>Needs attention</small></span></label><label class="legend-choice red"><input type="radio" name="legend" value="Red" <?= ($formData['legend'] ?? '') === 'Red' ? 'checked' : '' ?>><span><i aria-hidden="true"></i><strong>Red</strong><small>Critical or immediate danger</small></span></label></div><div id="legendHint" class="legend-hint">Choose Green, Orange, or Red to unlock the status-guided report fields.</div></section>
        <section class="survey-section disaster-first"><div class="section-head"><b>02</b><div><h3>Disaster</h3><p>Choose the type of emergency after setting the community status.</p></div></div><div class="form-grid two"><label for="disasterGroup">Disaster group<select name="general_type" id="disasterGroup" required disabled><option value="">Select community status first</option><?php foreach (array_keys($disasterCatalog['groups']) as $group): ?><option value="<?= h($group) ?>"><?= h($group) ?></option><?php endforeach; ?></select><small class="measurement-note">Choose weather and water, earth and ground, or fire and dangerous materials.</small></label><label for="specificType">Particular disaster<select name="specific_type" id="specificType" required disabled><option value="">Select disaster group first</option></select><small class="measurement-note">Examples include flood, typhoon, earthquake, landslide, fire, or tsunami.</small></label></div></section>
        <section class="survey-section situation-section" id="situationSection" hidden><div class="section-head"><b>03</b><div><h3>Observed effect and situation</h3><p id="situationSummary">Choose the visible effect, then answer the status-guided questions for that disaster.</p></div></div><div class="form-grid two"><label for="particularType">Observed effect<select name="particular_type" id="particularType" required disabled><option value="">Select particular disaster first</option></select><small class="measurement-note">Choose what you can directly observe.</small></label></div><div id="situationFields" class="form-grid two situation-fields" aria-live="polite"></div><div id="rapidSection" class="priority-details" hidden><p id="protocolLabel" class="field-note">Choices respond to the selected community status.</p><div class="form-grid two"><label for="assessmentColor">Active status<select id="assessmentColor" name="assessment_color" disabled><option>—</option></select></label><label for="impactDetail">Overall impact<select id="impactDetail" name="impact_detail" required><option value="">Select observed impact</option></select></label></div><label class="block-label" for="currentSituation">Current response situation<select id="currentSituation" name="current_situation" required><option value="">Select current situation</option></select></label><fieldset class="need-list"><legend>Immediate needs for this status</legend><div id="needsList"></div></fieldset><label class="block-label" for="reporterDescription">Important details for responders<textarea id="reporterDescription" name="reporter_description" required placeholder="Describe access, people affected, visible damage, and immediate danger."><?= $old('reporter_description') ?></textarea></label></div></section>
        <section class="survey-section"><div class="section-head"><b>04</b><div><h3>Location</h3><p>Use Philippine reference data so responders can route the report correctly.</p></div></div><div class="form-grid two"><label for="region">Region<select id="region" name="region_code" required aria-describedby="locationMeta locationError"><option value="">Loading regions…</option></select><input id="regionName" type="hidden" name="region_name" value="<?= $old('region_name') ?>"></label><label for="province">Province<select id="province" name="province_code" disabled required aria-describedby="locationMeta locationError"><option value="">Select region first</option></select><input id="provinceName" type="hidden" name="province_name" value="<?= $old('province_name') ?>"></label><label for="municipality">City / municipality<select id="municipality" name="municipality_code" disabled required aria-describedby="locationMeta locationError"><option value="">Select province first</option></select><input id="municipalityName" type="hidden" name="municipality_name" value="<?= $old('municipality_name') ?>"></label><label for="barangay">Barangay<select id="barangay" name="barangay_code" disabled required aria-describedby="locationMeta locationError"><option value="">Select municipality first</option></select><input id="barangayName" type="hidden" name="barangay_name" value="<?= $old('barangay_name') ?>"></label><label for="houseNumber">House no. / street<input id="houseNumber" name="house_number" placeholder="Optional" value="<?= $old('house_number') ?>"></label><label for="nearbyLandmark">Nearby landmark<input id="nearbyLandmark" name="nearby_landmark" placeholder="Optional" value="<?= $old('nearby_landmark') ?>"></label></div><p id="locationMeta" class="provider-status" aria-live="polite">Loading Philippine geographic reference data…</p><p id="locationError" class="inline-error" role="alert" hidden></p><button id="retryLocations" class="location-retry" type="button" hidden><span class="retry-spinner" aria-hidden="true"></span>Retry location list</button></section>
        <section class="survey-section optional-section"><div class="section-head"><b>05</b><div><h3>Contact and photo</h3><p>Optional follow-up details and photo evidence.</p></div></div><div class="form-grid two"><label for="contactNumber">Primary contact number<input id="contactNumber" name="contact_number" inputmode="tel" autocomplete="tel" placeholder="Optional" value="<?= $old('contact_number') ?>"></label><label for="alternateContact">Alternate contact<input id="alternateContact" name="alternate_contact" inputmode="tel" autocomplete="tel" placeholder="Optional" value="<?= $old('alternate_contact') ?>"></label><label for="reporterName">Your name<input id="reporterName" name="reporter_name" autocomplete="name" placeholder="Optional" value="<?= $old('reporter_name') ?>"></label><label for="email">Email address<input id="email" name="email" type="email" autocomplete="email" placeholder="Optional" value="<?= $old('email') ?>"></label></div><p class="field-note">Leave these fields blank to remain anonymous. The public tracking page never shows contact information.</p><div class="evidence-drop"><span class="evidence-copy"><strong>Optional photo evidence</strong><small id="evidenceHelp">Take a new photo or choose one from your device. JPG, PNG, or WEBP · up to 5 MB · stored privately for administrators.</small></span><div class="evidence-actions"><button class="evidence-action" id="launchCamera" type="button" aria-haspopup="dialog" aria-controls="cameraDialog"><span aria-hidden="true">◎</span> Use camera</button><input id="evidenceCamera" class="native-camera-input" type="file" name="evidence_camera" accept="image/*" capture="environment" aria-describedby="evidenceHelp evidenceStatus coordinateStatus"><label class="evidence-action secondary" for="evidence"><span aria-hidden="true">↥</span> Choose image<input id="evidence" type="file" name="evidence" accept="image/jpeg,image/png,image/webp" aria-describedby="evidenceHelp evidenceStatus coordinateStatus"></label></div><input id="photoLatitude" type="hidden" name="latitude" value="<?= $old('latitude') ?>"><input id="photoLongitude" type="hidden" name="longitude" value="<?= $old('longitude') ?>"><small id="evidenceStatus" class="evidence-status" aria-live="polite"></small><div class="coordinate-row"><p id="coordinateStatus" class="coordinate-status" aria-live="polite">Coordinates are optional. After selecting a photo, allow location access to attach the device position.</p><button id="retryCoordinates" type="button" class="coordinate-retry" hidden>Try coordinates again</button></div><span id="evidencePreview" class="evidence-preview" hidden><span id="evidenceCoordinatesOverlay" class="evidence-coordinates" aria-live="polite" hidden></span></span></div><dialog id="cameraDialog" class="camera-dialog" aria-labelledby="cameraTitle" aria-describedby="cameraStatus"><header><div><h3 id="cameraTitle">Take a photo</h3><p>Position the incident clearly inside the frame.</p></div><button id="closeCamera" class="camera-close" type="button" aria-label="Close camera">×</button></header><div class="camera-stage"><video id="cameraVideo" autoplay playsinline muted></video><p id="cameraStatus" role="status" aria-live="polite">Preparing camera…</p><canvas id="cameraCanvas" hidden></canvas></div><footer><button id="cancelCamera" class="camera-cancel" type="button">Cancel</button><button id="capturePhoto" class="camera-capture" type="button" disabled>Capture photo</button></footer></dialog></section>
        <footer class="submit-area"><p id="readyText" aria-live="polite">Select the community status first, then complete the disaster, observed effect, and exact location.</p><button type="submit">Submit rapid assessment <span aria-hidden="true">→</span></button></footer>
      </form>
    <?php endif; ?>
  </section>
</main>
<div id="toastRegion" class="toast-region" aria-live="polite" aria-atomic="true"></div>
<script>window.imSafeReportValues=<?= json_encode($restoreData, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;window.imSafeDisasterCatalog=<?= json_encode($disasterCatalog, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="assets/app.js?v=12"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
