<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
header('Cache-Control: no-store');
try {
    $action = is_string($_GET['action'] ?? null) ? $_GET['action'] : '';
    // Do not hold the session lock while waiting for an external provider.
    session_write_close();
    $code = static function (string $key): string {
        $value = $_GET[$key] ?? '';
        if (!is_string($value) || !preg_match('/^\\d{9,10}$/', $value)) json_response(['error' => 'A valid ' . $key . ' code is required.'], 422);
        return $value;
    };
    if($action==='regions') json_response(app()->locations->regions());
    if ($action === 'provinces') json_response(app()->locations->provinces($code('region')));
    if ($action === 'municipalities') {
        $region = $code('region');
        $province = $_GET['province'] ?? null;
        if ($province !== null && (!is_string($province) || ($province !== 'none' && !preg_match('/^\\d{9,10}$/', $province)))) json_response(['error' => 'A valid province code is required.'], 422);
        json_response(app()->locations->municipalities($region, $province));
    }
    if ($action === 'barangays') json_response(app()->locations->barangays($code('municipality')));
    if (in_array($action, ['pagasa', 'advisories'], true) && !rate_limit('provider-feed-' . $action, 30, 60)) {
        header('Retry-After: 60');
        json_response(['error' => 'Provider refresh limit reached. Saved announcements remain available.'], 429);
    }
    if($action==='pagasa') json_response(['brief'=>app()->pagasa->current(),'refreshedAt'=>gmdate(DATE_ATOM)]);
    if($action==='advisories') json_response(app()->advisories->current());
    if(in_array($action,['analytics','reports'],true)){ if(!is_admin()) json_response(['error'=>'Operations sign-in is required.'],403); json_response($action==='analytics'?app()->incidents->analytics():['items'=>app()->incidents->all()]); }
    json_response(['error'=>'Unknown API action.'],404);
} catch (Throwable $exception) {
    log_app_error($exception);
    if ($exception instanceof RuntimeException && $exception->getCode() === 422) json_response(['error' => $exception->getMessage()], 422);
    json_response(['error' => 'Live provider data is temporarily unavailable. Please try again.'], 502);
}
