<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Support\Config;

final class PagasaService
{
    private const FRESH_CACHE_SECONDS = 300;
    private const RETRY_BACKOFF_SECONDS = 60;
    private const PROVIDER_TIMEOUT_SECONDS = 4;
    private const SOURCE_URL = 'https://pagasa.dost.gov.ph/weather';

    public function __construct(private HttpClient $http, private Config $config) {}

    public function current(bool $refreshStale = true): array
    {
        $cachePath = $this->config->storagePath() . '/pagasa.json';
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
                $html = $this->http->get(self::SOURCE_URL, 'text/html,application/xhtml+xml', self::PROVIDER_TIMEOUT_SECONDS);
                $result = $this->parse($html);
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

    private function parse(string $html): array
    {
        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            foreach ($xpath->query('//script | //style | //noscript | //template') as $node) $node->parentNode?->removeChild($node);
            $html = $document->saveHTML() ?: $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $text = trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $find = static function (string $start, string $end) use ($text): string {
            $at = stripos($text, $start);
            if ($at === false) return '';
            $rest = substr($text, $at + strlen($start));
            $stop = stripos($rest, $end);
            return trim($stop === false ? $rest : substr($rest, 0, $stop));
        };
        $section = preg_match('/Forecast Weather Conditions([\s\S]*?)(?:Forecast Wind and Coastal Water Conditions|Temperature and Relative Humidity)/i', $html, $sectionMatch) ? $sectionMatch[1] : '';
        preg_match_all('/<tr[\s\S]*?<\/tr>/i', $section, $rows);
        $conditions = [];
        foreach ($rows[0] ?? [] as $row) {
            preg_match_all('/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/i', $row, $cells);
            $values = array_map(static fn(string $cell): string => trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $cell)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), $cells[1] ?? []);
            if (count($values) >= 4 && strtolower($values[0]) !== 'place') {
                $conditions[] = ['place' => $values[0], 'condition' => $values[1], 'cause' => $values[2], 'impacts' => $values[3]];
            }
        }
        return [
            'issuedAt' => $find('Issued at:', 'Synopsis') ?: 'Current daily issuance',
            'synopsis' => $find('Synopsis', 'Forecast Weather Conditions') ?: 'See the official PAGASA source for the current national weather outlook.',
            'conditions' => array_slice($conditions, 0, 6),
            'sourceUrl' => self::SOURCE_URL,
            'fromCache' => false,
            'stale' => false,
            'cachedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function readCache(string $path): ?array
    {
        if (!is_file($path)) return null;
        $cached = json_decode((string)file_get_contents($path), true);
        if (!is_array($cached) || !isset($cached['sourceUrl'], $cached['synopsis'])) return null;
        $age = max(0, time() - (int)filemtime($path));
        $cached['fromCache'] = true;
        $cached['stale'] = $age > self::FRESH_CACHE_SECONDS;
        $cached['cacheAgeSeconds'] = $age;
        return $cached;
    }

    private function unavailable(): array
    {
        return [
            'issuedAt' => 'Official source temporarily unavailable',
            'synopsis' => 'The live DOST-PAGASA weather page is temporarily unavailable. Open the official source link for the latest national outlook.',
            'conditions' => [],
            'sourceUrl' => self::SOURCE_URL,
            'fromCache' => false,
            'stale' => false,
            'unavailable' => true,
        ];
    }
}
