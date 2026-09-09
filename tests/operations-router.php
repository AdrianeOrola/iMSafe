<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/operations-preview') { require __DIR__ . '/operations-preview.php'; return true; }
if ($path === '/announcements-preview') { require __DIR__ . '/announcements-preview.php'; return true; }
return false;
