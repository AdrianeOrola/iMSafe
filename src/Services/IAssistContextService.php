<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Contracts\IAssistContextProvider;
use ImSafe\Repositories\IncidentRepository;
use Throwable;

final class IAssistContextService implements IAssistContextProvider
{
    public function __construct(
        private IncidentRepository $incidents,
        private PagasaService $pagasa,
        private GlobalAdvisoryService $advisories,
    ) {}

    public function load(string $message): array
    {
        $text = strtolower(trim((string)preg_replace('/\s+/', ' ', $message)));
        if (preg_match('/\bIMS-[A-F0-9]{8}-[A-F0-9]{16}\b/i', $message, $match) === 1) return $this->reportContext(strtoupper($match[0]));
        if (!$this->asksForCurrentInformation($text)) return $this->generalContext($text);

        $facts = ['checkedAt' => gmdate(DATE_ATOM)];
        $sources = [];
        $actions = [];
        $summary = [];
        $officialFresh = false;
        $officialCached = false;
        $providerAttempted = false;

        $genericCurrent = $this->containsAny($text, ['current alert', 'latest alert', 'current disaster', 'latest disaster', 'current condition', 'what is happening', 'is it safe', 'safe in', 'safe here', 'ligtas ba']);
        $weatherRequested = $genericCurrent || $this->containsAny($text, ['weather', 'rain', 'typhoon', 'cyclone', 'storm', 'flood', 'pagasa', 'ulan', 'bagyo', 'baha']);
        $advisoryRequested = $genericCurrent || $this->containsAny($text, ['alert', 'warning', 'earthquake', 'tsunami', 'volcano', 'volcanic', 'landslide', 'wildfire', 'gdacs', 'lindol', 'bulkan']);
        $incidentRequested = $genericCurrent || $this->containsAny($text, ['incident', 'report', 'happening', 'near me', 'in my area', 'sa barangay', 'sa city']);

        if ($weatherRequested) {
            $providerAttempted = true;
            try {
                $weather = $this->pagasa->current();
                $facts['pagasa'] = $this->safePagasa($weather);
                $cached = !empty($weather['fromCache']);
                $unavailable = !empty($weather['unavailable']);
                $officialCached = $officialCached || $cached;
                $officialFresh = $officialFresh || (!$cached && !$unavailable);
                $sources[] = ['label' => 'DOST-PAGASA', 'url' => 'https://www.pagasa.dost.gov.ph/weather', 'state' => $unavailable ? 'unavailable' : ($cached ? 'cached' : 'official')];
                if ($unavailable) {
                    $summary[] = 'The live DOST-PAGASA outlook is unavailable in iMSafe right now.';
                } else {
                    $prefix = $cached ? 'Cached DOST-PAGASA outlook' : 'DOST-PAGASA outlook';
                    $summary[] = $prefix . ' (' . trim((string)($weather['issuedAt'] ?? 'issue time unavailable')) . '): ' . $this->limitText((string)($weather['synopsis'] ?? ''), 360);
                }
            } catch (Throwable) {
                $summary[] = 'The DOST-PAGASA outlook could not be loaded.';
            }
        }

        if ($advisoryRequested) {
            $providerAttempted = true;
            try {
                $advisories = $this->advisories->current();
                $items = array_values(array_filter((array)($advisories['items'] ?? []), 'is_array'));
                $philippineItems = array_values(array_filter($items, static fn(array $item): bool => stripos((string)($item['title'] ?? ''), 'philippin') !== false));
                $facts['gdacs'] = [
                    'source' => 'GDACS Global Disaster Alert and Coordination System',
                    'refreshedAt' => (string)($advisories['refreshedAt'] ?? ''),
                    'fromCache' => !empty($advisories['fromCache']),
                    'unavailable' => !empty($advisories['unavailable']),
                    'philippineMentions' => array_map([$this, 'safeAdvisory'], array_slice($philippineItems, 0, 5)),
                    'latestGlobalSignals' => array_map([$this, 'safeAdvisory'], array_slice($items, 0, 5)),
                ];
                $cached = !empty($advisories['fromCache']);
                $unavailable = !empty($advisories['unavailable']);
                $officialCached = $officialCached || $cached;
                $officialFresh = $officialFresh || (!$cached && !$unavailable);
                $sources[] = ['label' => 'GDACS', 'url' => 'https://www.gdacs.org/', 'state' => $unavailable ? 'unavailable' : ($cached ? 'cached' : 'official')];
                if ($unavailable) {
                    $summary[] = 'The GDACS feed is unavailable in iMSafe right now.';
                } elseif ($philippineItems) {
                    $titles = array_map(static fn(array $item): string => trim((string)($item['title'] ?? '')), array_slice($philippineItems, 0, 2));
                    $summary[] = ($cached ? 'The cached GDACS feed' : 'The latest GDACS feed') . ' includes ' . count($philippineItems) . ' signal(s) mentioning the Philippines: ' . implode('; ', array_filter($titles)) . '.';
                } else {
                    $summary[] = ($cached ? 'The cached GDACS feed' : 'The latest GDACS feed') . ' has no returned signal that explicitly mentions the Philippines. This does not prove that every location is safe.';
                }
            } catch (Throwable) {
                $summary[] = 'The GDACS feed could not be loaded.';
            }
        }

        $matchedReports = [];
        if ($incidentRequested) {
            try {
                $recent = $this->incidents->publicRecent();
                $matchedReports = $this->matchReports($recent, $text, $genericCurrent);
                $facts['communityReports'] = array_map(fn(array $report): array => $this->safeIncident($report), array_slice($matchedReports, 0, 8));
                if ($matchedReports) {
                    $examples = array_map(static fn(array $report): string => trim((string)$report['specific_type']) . ' in ' . trim((string)$report['municipality_name']) . ' (' . trim((string)$report['status']) . ')', array_slice($matchedReports, 0, 3));
                    $summary[] = 'iMSafe has ' . count($matchedReports) . ' relevant community report(s): ' . implode('; ', $examples) . '. Reports marked received have not yet been verified.';
                } else {
                    $summary[] = 'No matching community incident appears in the latest public-safe iMSafe records. This does not confirm that the location is hazard-free.';
                }
            } catch (Throwable) {
                $summary[] = 'Community incident records are temporarily unavailable.';
            }
        }

        if ($providerAttempted || $incidentRequested) $actions[] = ['type' => 'announcements', 'label' => 'View announcements', 'url' => 'announcements.php'];
        if ($this->containsAny($text, ['earthquake', 'tsunami', 'volcano', 'volcanic', 'lindol', 'bulkan'])) {
            $sources[] = ['label' => 'DOST-PHIVOLCS', 'url' => 'https://www.phivolcs.dost.gov.ph/', 'state' => 'official-directory'];
        }

        [$informationStatus, $statusLabel] = $officialFresh ? ['verified_official', 'Official source data']
            : ($officialCached ? ['cached_official', 'Cached official-source data']
                : ($matchedReports ? ['community_report', 'Community incident information'] : ['information_unavailable', 'Information not confirmed']));

        return [
            'kind' => 'current',
            'informationStatus' => $informationStatus,
            'statusLabel' => $statusLabel,
            'summary' => $summary ? implode(' ', $summary) . ' Follow official and local authority instructions.' : 'I cannot confirm current conditions from the available system data.',
            'facts' => $facts,
            'sources' => $this->uniqueSources($sources),
            'actions' => $actions,
            'preferredAction' => 'announcements',
        ];
    }

    private function reportContext(string $reference): array
    {
        $report = $this->incidents->findPublic($reference);
        $action = ['type' => 'track', 'label' => 'Open report status', 'url' => 'track.php?ref=' . rawurlencode($reference)];
        if (!$report) return [
            'kind' => 'report', 'informationStatus' => 'information_unavailable', 'statusLabel' => 'Report not confirmed',
            'summary' => 'I could not find a public report matching that reference. Check every character and use Track report to try again.',
            'facts' => ['reference' => $reference, 'found' => false], 'sources' => [], 'actions' => [$action], 'preferredAction' => 'track',
        ];
        $status = (string)($report['status'] ?? 'received');
        $location = trim((string)($report['barangay_name'] ?? '')) . ', ' . trim((string)($report['municipality_name'] ?? ''));
        $note = trim((string)($report['note'] ?? ''));
        $summary = 'iMSafe report ' . $reference . ' is marked ' . $status . '. Incident: ' . trim((string)($report['specific_type'] ?? 'unspecified')) . '. Location: ' . trim($location, ', ') . '.';
        if ($note !== '') $summary .= ' Latest public update: ' . $this->limitText($note, 300);
        if ($status === 'received') $summary .= ' This community report has not yet been verified.';
        return [
            'kind' => 'report',
            'informationStatus' => $status === 'received' ? 'community_report' : 'system_incident',
            'statusLabel' => $status === 'received' ? 'Community report, not yet verified' : 'iMSafe report status',
            'summary' => $summary,
            'facts' => ['report' => $this->safeIncident($report + ['province_name' => '', 'region_name' => ''], true)],
            'sources' => [['label' => 'iMSafe public tracking', 'url' => $action['url'], 'state' => 'system']],
            'actions' => [$action],
            'preferredAction' => 'track',
        ];
    }

    private function generalContext(string $text): array
    {
        $sources = [];
        if ($this->containsAny($text, ['flood', 'rain', 'typhoon', 'cyclone', 'storm', 'baha', 'ulan', 'bagyo'])) {
            $sources[] = ['label' => 'DOST-PAGASA safety information', 'url' => 'https://www.pagasa.dost.gov.ph/weather', 'state' => 'official-guidance'];
        }
        if ($this->containsAny($text, ['earthquake', 'aftershock', 'tsunami', 'volcano', 'volcanic', 'ashfall', 'lahar', 'lindol', 'bulkan'])) {
            $sources[] = ['label' => 'DOST-PHIVOLCS safety information', 'url' => 'https://www.phivolcs.dost.gov.ph/', 'state' => 'official-guidance'];
        }
        return ['kind' => 'general', 'informationStatus' => 'general_guidance', 'statusLabel' => 'General safety guidance', 'facts' => [], 'sources' => $sources, 'actions' => []];
    }

    private function asksForCurrentInformation(string $text): bool
    {
        return $this->containsAny($text, ['current', 'latest', 'today', 'now', 'alert', 'warning', 'condition', 'happening', 'active', 'status', 'near me', 'in my area', 'safe', 'ligtas', 'ngayon']);
    }

    private function matchReports(array $reports, string $message, bool $genericCurrent): array
    {
        if ($genericCurrent) return array_slice($reports, 0, 8);
        $matches = [];
        foreach ($reports as $report) {
            if (!is_array($report)) continue;
            $values = ['barangay_name', 'municipality_name', 'province_name', 'region_name', 'specific_type'];
            foreach ($values as $key) {
                $candidate = strtolower(trim((string)($report[$key] ?? '')));
                if (strlen($candidate) >= 3 && str_contains($message, $candidate)) { $matches[] = $report; break; }
            }
        }
        return $matches;
    }

    private function safePagasa(array $weather): array
    {
        return [
            'source' => 'DOST-PAGASA', 'issuedAt' => (string)($weather['issuedAt'] ?? ''),
            'synopsis' => $this->limitText((string)($weather['synopsis'] ?? ''), 500),
            'fromCache' => !empty($weather['fromCache']), 'unavailable' => !empty($weather['unavailable']),
            'conditions' => array_slice(array_values(array_filter((array)($weather['conditions'] ?? []), 'is_array')), 0, 6),
        ];
    }

    private function safeAdvisory(array $item): array
    {
        return [
            'title' => $this->limitText((string)($item['title'] ?? ''), 240),
            'publishedAt' => (string)($item['publishedAt'] ?? ''),
            'level' => (string)($item['level'] ?? 'unknown'),
            'hazard' => (string)($item['hazard'] ?? 'Natural hazard'),
        ];
    }

    private function safeIncident(array $report, bool $exactReference = false): array
    {
        $safe = [
            'status' => (string)($report['status'] ?? 'received'), 'legend' => (string)($report['legend'] ?? ''),
            'incidentType' => (string)($report['specific_type'] ?? ''), 'createdAt' => (string)($report['created_at'] ?? ''),
            'municipality' => (string)($report['municipality_name'] ?? ''),
            'province' => (string)($report['province_name'] ?? ''), 'region' => (string)($report['region_name'] ?? ''),
        ];
        if ($exactReference) {
            $safe['barangay'] = (string)($report['barangay_name'] ?? '');
            $safe['latestPublicUpdate'] = $this->limitText((string)($report['note'] ?? ''), 300);
        }
        return $safe;
    }

    private function uniqueSources(array $sources): array
    {
        $unique = [];
        foreach ($sources as $source) if (is_array($source) && isset($source['url'])) $unique[(string)$source['url']] = $source;
        return array_values($unique);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) if (str_contains($text, $needle)) return true;
        return false;
    }

    private function limitText(string $value, int $length): string
    {
        $clean = trim((string)preg_replace('/\s+/', ' ', strip_tags($value)));
        return strlen($clean) <= $length ? $clean : rtrim(substr($clean, 0, $length - 3)) . '...';
    }
}
