<?php
declare(strict_types=1);

namespace ImSafe\Contracts;

interface IAssistContextProvider
{
    public function load(string $message): array;
}
