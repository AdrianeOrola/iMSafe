<?php
declare(strict_types=1);

namespace ImSafe\Contracts;

interface IAssistLanguageModel
{
    public function respond(string $message, array $history, array $context): array;
}
