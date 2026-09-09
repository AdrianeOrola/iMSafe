<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(['error' => 'Only POST requests are accepted.'], 405);
}

if (!csrf_is_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) json_response(['error' => 'This chat session has expired. Refresh the page and try again.'], 403);
if (!rate_limit('imassist-message', 20, 60)) json_response(['error' => 'Too many messages were sent. Wait one minute and try again.', 'retryAfter' => 60], 429);

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 20000) json_response(['error' => 'The chat request is too large.'], 413);

try {
    $raw = file_get_contents('php://input');
    $payload = json_decode(is_string($raw) ? $raw : '', true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) throw new InvalidArgumentException('A valid chat request is required.');

    $message = $payload['message'] ?? null;
    if (!is_string($message)) throw new InvalidArgumentException('Enter a message for iMAssist.');
    $message = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message));
    if ($message === '' || strlen($message) > 800) throw new InvalidArgumentException('Keep your message between 1 and 800 characters.');

    $history = $payload['history'] ?? [];
    if (!is_array($history) || count($history) > 8) throw new InvalidArgumentException('The conversation history is invalid.');
    $safeHistory = [];
    $historyBytes = 0;
    foreach ($history as $item) {
        if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true) || !is_string($item['content'] ?? null)) throw new InvalidArgumentException('The conversation history is invalid.');
        $content = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $item['content']));
        if ($content === '' || strlen($content) > 1000) throw new InvalidArgumentException('The conversation history is invalid.');
        $historyBytes += strlen($content);
        if ($historyBytes > 6000) throw new InvalidArgumentException('The conversation history is too large.');
        $safeHistory[] = ['role' => $item['role'], 'content' => $content];
    }

    $location = $payload['location'] ?? '';
    if (!is_string($location) || strlen($location) > 320) throw new InvalidArgumentException('The selected location is invalid.');
    $location = trim((string)preg_replace('/\s+/', ' ', strip_tags($location)));

    session_write_close();
    json_response(app()->imassist->answer($message, $safeHistory, $location));
} catch (JsonException|InvalidArgumentException $error) {
    json_response(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    log_app_error($error);
    json_response([
        'reply' => 'iMAssist cannot access live system information right now. Check Announcements and follow official local instructions. If anyone is in immediate danger, call 911.',
        'urgent' => false,
        'informationStatus' => 'information_unavailable',
        'statusLabel' => 'Information unavailable',
        'sources' => [],
        'actions' => [['type' => 'announcements', 'label' => 'View announcements', 'url' => 'announcements.php']],
        'mode' => 'built_in',
        'generatedAt' => gmdate(DATE_ATOM),
        'disclaimer' => 'iMAssist supports disaster decisions but does not replace emergency responders or official authorities.',
    ], 503);
}
