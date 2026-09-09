<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Support\Config;
use RuntimeException;
use SimpleXMLElement;

final class GlobalAdvisoryService
{
    private const SOURCE_URL = 'https://www.gdacs.org/xml/rss.xml';
    private const FRESH_CACHE_SECONDS = 300;
    private const RETRY_BACKOFF_SECONDS = 60;
    private const PROVIDER_TIMEOUT_SECONDS = 4;

    public function __construct(private HttpClient $http, private Config $config) {}

    public function current(bool $refreshStale = true): array
    {
        $cachePath = $this->config->storagePath() . '/gdacs.json';
        $cached = $this->readCache($cachePath);
        if ($cached !== null && empty($cached['stale'])) return $cached;
        if (!$refreshStale) return $cached ?? $this->unavailable();

        if (!is_dir($this->config->storagePath())) mkdir($this->config->storagePath(), 0775, true);
        $lock = @fopen($cachePath . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            return $cached ?? $this->unavailable();
        }

        try {
            $cached = $this->readCache($cachePath);
            if ($cached !== null && empty($cached['stale'])) return $cached;

            $attemptPath = $cachePath . '.attempt';
            $lastAttempt = is_file($attemptPath) ? (int)filemtime($attemptPath) : 0;
            if ($lastAttempt > 0 && time() - $lastAttempt < self::RETRY_BACKOFF_SECONDS) return $cached ?? $this->unavailable();
            @touch($attemptPath);

            try {
                $xml = $this->http->get(self::SOURCE_URL, 'application/rss+xml,application/xml,text/xml', self::PROVIDER_TIMEOUT_SECONDS);
                $result = $this->parse($xml);
                file_put_contents($cachePath, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
                return $result;
            } catch (\Throwable) {
                return $cached ?? $this->unavailable();
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function parse(string $xml): array
    {
        $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        if (!$feed || !isset($feed->channel->item)) throw new RuntimeException('GDACS returned an invalid RSS feed.');
        $items = [];
        foreach (array_slice(iterator_to_array($feed->channel->item), 0, 12) as $index => $entry) {
            $title = trim((string)$entry->title) ?: 'Untitled advisory';
            $url = $this->safeUrl((string)$entry->link);
            $items[] = [
                'id' => $url ?: 'gdacs-' . $index,
                'title' => $title,
                'description' => trim((string)$entry->description),
                'publishedAt' => trim((string)$entry->pubDate),
                'url' => $url,
                'level' => $this->level($title),
                'hazard' => $this->hazard($title),
            ];
        }
        return [
            'source' => 'GDACS Global Disaster Alert and Coordination System',
            'refreshedAt' => gmdate(DATE_ATOM),
            'cachedAt' => gmdate(DATE_ATOM),
            'fromCache' => false,
            'stale' => false,
            'items' => $items,
        ];
    }

    private function readCache(string $path): ?array
    {
        if (!is_file($path)) return null;
        $cached = json_decode((string)file_get_contents($path), true);
        if (!is_array($cached) || !isset($cached['items'])) return null;
        $age = max(0, time() - (int)filemtime($path));
        $cached['fromCache'] = true;
        $cached['stale'] = $age > self::FRESH_CACHE_SECONDS;
        $cached['cacheAgeSeconds'] = $age;
        return $cached;
    }

    private function unavailable(): array
    {
        return [
            'source' => 'GDACS Global Disaster Alert and Coordination System',
            'refreshedAt' => gmdate(DATE_ATOM),
            'fromCache' => false,
            'stale' => false,
            'unavailable' => true,
            'items' => [],
        ];
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    private function level(string $title): string
    {
        $value = strtolower($title);
        return str_contains($value, 'red') ? 'red' : (str_contains($value, 'orange') ? 'orange' : (str_contains($value, 'green') ? 'green' : 'unknown'));
    }

    private function hazard(string $title): string
    {
        $value = strtolower($title);
        if (str_contains($value, 'flood')) return 'Flood';
        if (str_contains($value, 'cyclone')) return 'Tropical Cyclone';
        if (str_contains($value, 'earthquake')) return 'Earthquake';
        if (str_contains($value, 'volcano')) return 'Volcanic Activity';
        if (str_contains($value, 'fire')) return 'Wildfire';
        return 'Natural hazard';
    }
}
