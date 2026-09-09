<?php
declare(strict_types=1);

namespace ImSafe\Support;

final class FloodLevels
{
    private const MEASUREMENTS = [
        'Ankle-deep' => 'Up to 6 in (up to 0.5 ft)',
        'Knee-deep' => '7–18 in (0.6–1.5 ft)',
        'Waist-deep' => '19–36 in (1.6–3.0 ft)',
        'Chest-deep or higher' => 'More than 36 in (over 3.0 ft)',
    ];

    public static function options(): array
    {
        return self::MEASUREMENTS;
    }

    public static function isValid(string $level): bool
    {
        return array_key_exists($level, self::MEASUREMENTS);
    }

    public static function measurement(string $level): string
    {
        return self::MEASUREMENTS[$level] ?? '';
    }

    public static function describe(string $level): string
    {
        $level = trim($level);
        if ($level === '') return 'Not provided';
        $measurement = self::measurement($level);
        return $measurement === '' ? $level : $level . ' — ' . $measurement;
    }
}
