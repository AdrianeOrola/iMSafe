<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Contracts\IAssistContextProvider;
use ImSafe\Contracts\IAssistLanguageModel;
use Throwable;

final class IAssistService
{
    private const ACTIONS = [
        'report' => ['type' => 'report', 'label' => 'Report emergency', 'url' => 'report.php'],
        'track' => ['type' => 'track', 'label' => 'Track report', 'url' => 'track.php'],
        'announcements' => ['type' => 'announcements', 'label' => 'View announcements', 'url' => 'announcements.php'],
    ];

    public function __construct(
        private IAssistSafetyGuide $guide,
        private IAssistContextProvider $context,
        private ?IAssistLanguageModel $model = null,
    ) {}

    public function answer(string $message, array $history, string $location = ''): array
    {
        $emergency = $this->guide->emergency($message);
        if ($emergency !== null) return $this->response($emergency, true, 'built_in');

        if (!$this->guide->isRelated($message)) {
            return $this->response([
                'answer' => 'I can only help with disasters, emergencies, safety, preparedness, iMSafe incidents, alerts, reporting, and tracking. Ask me about one of those topics.',
                'informationStatus' => 'out_of_scope',
                'statusLabel' => 'Disaster questions only',
                'sources' => [],
                'actions' => [],
            ], false, 'built_in');
        }

        try {
            $context = $this->context->load($location !== '' ? $message . "\nLocation selected in iMSafe: " . $location : $message);
        } catch (Throwable $error) {
            error_log('[iMSafe iMAssist context] ' . $error->getMessage());
            $context = [
                'kind' => 'unavailable',
                'informationStatus' => 'information_unavailable',
                'statusLabel' => 'Information unavailable',
                'summary' => 'I cannot confirm current system information right now. Check Announcements and follow official local instructions.',
                'sources' => [],
                'actions' => [self::ACTIONS['announcements']],
            ];
        }

        $mode = 'built_in';
        $generated = null;
        if ($this->model !== null) {
            try {
                $candidate = $this->model->respond($message, $this->safeHistory($history), $context);
                if (is_string($candidate['answer'] ?? null) && trim($candidate['answer']) !== '') {
                    $generated = $candidate;
                    $mode = 'ai';
                }
            } catch (Throwable $error) {
                error_log('[iMSafe iMAssist AI] ' . $error->getMessage());
            }
        }
        $generated ??= $this->guide->fallback($message, $context);

        $actions = is_array($context['actions'] ?? null) ? $context['actions'] : [];
        $requestedAction = (string)($generated['action'] ?? 'none');
        if (isset(self::ACTIONS[$requestedAction]) && !$this->hasAction($actions, $requestedAction)) $actions[] = self::ACTIONS[$requestedAction];

        return $this->response([
            'answer' => trim((string)$generated['answer']),
            'informationStatus' => (string)($context['informationStatus'] ?? 'general_guidance'),
            'statusLabel' => (string)($context['statusLabel'] ?? 'General safety guidance'),
            'sources' => is_array($context['sources'] ?? null) ? $context['sources'] : [],
            'actions' => $actions,
        ], false, $mode);
    }

    private function response(array $data, bool $urgent, string $mode): array
    {
        return [
            'reply' => (string)($data['answer'] ?? ''),
            'urgent' => $urgent,
            'informationStatus' => (string)($data['informationStatus'] ?? 'general_guidance'),
            'statusLabel' => (string)($data['statusLabel'] ?? 'General safety guidance'),
            'sources' => array_values(array_filter((array)($data['sources'] ?? []), static fn($source): bool => is_array($source) && isset($source['label'], $source['url']))),
            'actions' => array_values(array_filter((array)($data['actions'] ?? []), static fn($action): bool => is_array($action) && isset($action['type'], $action['label'], $action['url']))),
            'mode' => $mode,
            'generatedAt' => gmdate(DATE_ATOM),
            'disclaimer' => 'iMAssist supports disaster decisions but does not replace emergency responders or official authorities.',
        ];
    }

    private function safeHistory(array $history): array
    {
        $safe = [];
        foreach (array_slice($history, -8) as $item) {
            if (!is_array($item) || !in_array($item['role'] ?? '', ['user', 'assistant'], true) || !is_string($item['content'] ?? null)) continue;
            $content = trim(substr($item['content'], 0, 1000));
            if ($content !== '') $safe[] = ['role' => $item['role'], 'content' => $content];
        }
        return $safe;
    }

    private function hasAction(array $actions, string $type): bool
    {
        foreach ($actions as $action) if (is_array($action) && ($action['type'] ?? '') === $type) return true;
        return false;
    }
}
