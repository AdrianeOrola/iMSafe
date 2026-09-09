<?php
declare(strict_types=1);
namespace ImSafe\Services;
use ImSafe\Support\Config;

final class PagasaService {
    public function __construct(private HttpClient $http, private Config $config) {}
    public function current(): array {
        $url = 'https://pagasa.dost.gov.ph/weather'; $cachePath = $this->config->storagePath() . '/pagasa.json';
        try {
            $html = $this->http->get($url, 'text/html,application/xhtml+xml', 12);
            // Style/script text is not forecast content and must not appear in the outlook.
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
            $find = static function (string $start, string $end) use ($text): string { $at=stripos($text,$start); if($at===false)return ''; $rest=substr($text,$at+strlen($start)); $stop=stripos($rest,$end); return trim($stop===false?$rest:substr($rest,0,$stop)); };
            $section = preg_match('/Forecast Weather Conditions([\s\S]*?)(?:Forecast Wind and Coastal Water Conditions|Temperature and Relative Humidity)/i', $html, $sectionMatch) ? $sectionMatch[1] : '';
            preg_match_all('/<tr[\s\S]*?<\/tr>/i', $section, $rows);
            $conditions = [];
            foreach ($rows[0] ?? [] as $row) { preg_match_all('/<t[dh][^>]*>([\s\S]*?)<\/t[dh]>/i', $row, $cells); $values=array_map(static fn(string $cell):string=>trim((string)preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $cell)), ENT_QUOTES|ENT_HTML5,'UTF-8'))), $cells[1]??[]); if(count($values)>=4 && strtolower($values[0])!=='place') $conditions[]=['place'=>$values[0],'condition'=>$values[1],'cause'=>$values[2],'impacts'=>$values[3]]; }
            $result = ['issuedAt'=>$find('Issued at:','Synopsis') ?: 'Current daily issuance','synopsis'=>$find('Synopsis','Forecast Weather Conditions') ?: 'See the official PAGASA source for the current national weather outlook.','conditions'=>array_slice($conditions,0,6),'sourceUrl'=>$url,'fromCache'=>false];
            if (!is_dir($this->config->storagePath())) mkdir($this->config->storagePath(), 0775, true);
            file_put_contents($cachePath, json_encode($result));
            return $result;
        } catch (\Throwable) {
            if (is_file($cachePath)) { $cached = json_decode((string)file_get_contents($cachePath), true); if (is_array($cached) && isset($cached['sourceUrl'], $cached['synopsis'])) { $cached['fromCache'] = true; return $cached; } }
            return ['issuedAt'=>'Official source temporarily unavailable','synopsis'=>'The live DOST-PAGASA weather page is temporarily unavailable. Open the official source link for the latest national outlook.','conditions'=>[],'sourceUrl'=>$url,'fromCache'=>false,'unavailable'=>true];
        }
    }
}
