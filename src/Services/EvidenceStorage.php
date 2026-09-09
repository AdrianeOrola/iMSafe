<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Support\Config;
use RuntimeException;

final class EvidenceStorage
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_PIXELS = 25_000_000;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private Config $config) {}

    /** @return array{latitude:float,longitude:float}|null */
    public function coordinates(?array $upload): ?array
    {
        if (!$upload || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !function_exists('exif_read_data')) return null;
        $temporaryPath = (string)($upload['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_file($temporaryPath)) return null;
        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) return null;
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        if ((string)$fileInfo->file($temporaryPath) !== 'image/jpeg') return null;
        $dimensions = @getimagesize($temporaryPath);
        if (!is_array($dimensions) || (int)$dimensions[0] < 1 || (int)$dimensions[1] < 1 || (int)$dimensions[0] * (int)$dimensions[1] > self::MAX_PIXELS) return null;

        $metadata = @exif_read_data($temporaryPath, null, true, false);
        if (!is_array($metadata)) return null;
        $gps = is_array($metadata['GPS'] ?? null) ? $metadata['GPS'] : $metadata;
        $latitude = $this->gpsCoordinate($gps['GPSLatitude'] ?? null, (string)($gps['GPSLatitudeRef'] ?? ''));
        $longitude = $this->gpsCoordinate($gps['GPSLongitude'] ?? null, (string)($gps['GPSLongitudeRef'] ?? ''));
        if ($latitude === null || $longitude === null || abs($latitude) > 90 || abs($longitude) > 180) return null;
        return ['latitude' => $latitude, 'longitude' => $longitude];
    }

    public function store(?array $upload): ?string
    {
        if (!$upload || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;

        $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The photo is too large. Choose a JPG, PNG, or WEBP image under 5 MB.',
                UPLOAD_ERR_PARTIAL => 'The photo upload was interrupted. Choose the file again and retry.',
                default => 'The photo could not be uploaded. Choose the file again and retry.',
            };
            throw new RuntimeException($message);
        }

        $temporaryPath = (string)($upload['tmp_name'] ?? '');
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded photo could not be verified. Choose the file again and retry.');
        }

        $size = filesize($temporaryPath);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Choose a JPG, PNG, or WEBP image between 1 byte and 5 MB.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$fileInfo->file($temporaryPath);
        $dimensions = @getimagesize($temporaryPath);
        if (!isset(self::MIME_EXTENSIONS[$mime]) || !is_array($dimensions) || ($dimensions['mime'] ?? '') !== $mime) {
            throw new RuntimeException('The selected file is not a valid JPG, PNG, or WEBP image.');
        }
        if ((int)$dimensions[0] < 1 || (int)$dimensions[1] < 1 || (int)$dimensions[0] * (int)$dimensions[1] > self::MAX_PIXELS) {
            throw new RuntimeException('The photo dimensions are too large. Choose an image smaller than 25 megapixels.');
        }

        $directory = $this->directory();
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Photo storage is unavailable. Please submit the report without a photo or try again later.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Photo storage is unavailable. Please submit the report without a photo or try again later.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . self::MIME_EXTENSIONS[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The photo could not be saved. Please submit the report without it or try again.');
        }
        @chmod($destination, 0640);

        return $storedName;
    }

    public function remove(?string $storedName): void
    {
        $record = $storedName ? $this->resolve($storedName) : null;
        if ($record && is_file($record['path'])) @unlink($record['path']);
    }

    /** @return array{path:string,mime:string}|null */
    public function resolve(string $storedName): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/', $storedName)) return null;
        $path = $this->directory() . DIRECTORY_SEPARATOR . $storedName;
        if (!is_file($path)) return null;

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$fileInfo->file($path);
        if (!isset(self::MIME_EXTENSIONS[$mime]) || self::MIME_EXTENSIONS[$mime] !== pathinfo($storedName, PATHINFO_EXTENSION)) return null;

        return ['path' => $path, 'mime' => $mime];
    }

    private function directory(): string
    {
        return rtrim($this->config->storagePath(), '/\\') . DIRECTORY_SEPARATOR . 'evidence';
    }

    private function gpsCoordinate(mixed $parts, string $reference): ?float
    {
        if (!is_array($parts) || count($parts) < 3) return null;
        $values = array_map(function (mixed $part): ?float {
            if (is_numeric($part)) return (float)$part;
            if (!is_string($part) || !str_contains($part, '/')) return null;
            [$numerator, $denominator] = array_pad(explode('/', $part, 2), 2, '0');
            if (!is_numeric($numerator) || !is_numeric($denominator) || (float)$denominator === 0.0) return null;
            return (float)$numerator / (float)$denominator;
        }, array_slice($parts, 0, 3));
        if (in_array(null, $values, true)) return null;
        $coordinate = $values[0] + ($values[1] / 60) + ($values[2] / 3600);
        return in_array(strtoupper($reference), ['S', 'W'], true) ? -$coordinate : $coordinate;
    }
}
