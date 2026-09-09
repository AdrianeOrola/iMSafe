<?php
declare(strict_types=1);
namespace ImSafe\Services;

use ImSafe\Support\Config;
use RuntimeException;
use SimpleXMLElement;

final class GlobalAdvisoryService
{
    private const SOURCE_URL = 'https://www.gdacs.org/xml/rss.xml';

    public function __construct(private HttpClient $http, private Config $config) {}

    public function current(): array
    {
        $cachePath = $this->config->storagePath() . '/gdacs.json';
        try {
            $xml = $this->http->get(self::SOURCE_URL, 'application/rss+xml,application/xml,text/xml', 12);
            $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
            if (!$feed || !isset($feed->channel->item)) throw new RuntimeException('GDACS returned an invalid RSS feed.');
            $items = [];
            foreach (array_slice(iterator_to_array($feed->channel->item), 0, 12) as $index => $entry) {
                $title = trim((string)$entry->title) ?: 'Untitled advisory';
                $url = $this->safeUrl((string)$entry->link);
                $items[] = ['id' => $url ?: 'gdacs-' . $index, 'title' => $title, 'description' => trim((string)$entry->description), 'publishedAt' => trim((string)$entry->pubDate), 'url' => $url, 'level' => $this->level($title), 'hazard' => $this->hazard($title)];
            }
            $result = ['source' => 'GDACS Global Disaster Alert and Coordination System', 'refreshedAt' => gmdate(DATE_ATOM), 'fromCache' => false, 'items' => $items];
            if (!is_dir($this->config->storagePath())) mkdir($this->config->storagePath(), 0775, true);
            file_put_contents($cachePath, json_encode($result), LOCK_EX);
            return $result;
        } catch (\Throwable) {
            if (is_file($cachePath)) { $cached = json_decode((string)file_get_contents($cachePath), true); if (is_array($cached) && isset($cached['items'])) { $cached['fromCache'] = true; return $cached; } }
            return ['source' => 'GDACS Global Disaster Alert and Coordination System', 'refreshedAt' => gmdate(DATE_ATOM), 'fromCache' => false, 'unavailable' => true, 'items' => []];
        }
    }

    private function safeUrl(string $url): string { $parts = parse_url(trim($url)); return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) ? trim($url) : ''; }
    private function level(string $title): string { $value = strtolower($title); return str_contains($value, 'red') ? 'red' : (str_contains($value, 'orange') ? 'orange' : (str_contains($value, 'green') ? 'green' : 'unknown')); }
    private function hazard(string $title): string { $value = strtolower($title); if (str_contains($value, 'flood')) return 'Flood'; if (str_contains($value, 'cyclone')) return 'Tropical Cyclone'; if (str_contains($value, 'earthquake')) return 'Earthquake'; if (str_contains($value, 'volcano')) return 'Volcanic Activity'; if (str_contains($value, 'fire')) return 'Wildfire'; return 'Natural hazard'; }
}
