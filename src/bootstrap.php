<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ImSafe\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

function h(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function json_response(array $payload, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('X-Content-Type-Options: nosniff'); echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }
