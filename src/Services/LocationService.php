<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Support\Config;
use RuntimeException;

final class LocationService
{
    public function __construct(private HttpClient $http, private Config $config) {}

    public function regions(): array { return $this->lookup('regions'); }

    public function provinces(string $region): array
    {
        $this->find($this->regions()['items'], $region);
        $result = $this->lookup('provinces', $region);
        $cities = $this->lookup('region-cities', $region);
        $codes = array_column($result['items'], 'code');
        // Use codes, never the provider's unreliable free-text province labels.
        if (array_filter($cities['items'], static fn(array $city): bool =>
            $city['type'] !== 'SubMun' && !in_array($city['provinceCode'], $codes, true)
        )) {
            $result['items'][] = ['code' => 'none', 'name' => !$result['items'] ? 'Not applicable (no province)' : 'Independent cities (no province)', 'provinceCode' => '', 'type' => ''];
        }
        $result['stale'] = !empty($result['stale']) || !empty($cities['stale']);
        return $result;
    }

    public function municipalities(string $region, ?string $province = null): array
    {
        $provinces = $this->provinces($region);
        if ($province !== null && $province !== '') $this->find($provinces['items'], $province);
        // Fetch from the province endpoint: parentage is not inferred from names.
        $result = $province && $province !== 'none'
            ? $this->lookup('province-cities', $province)
            : $this->lookup('region-cities', $region);
        $codes = array_column($provinces['items'], 'code');
        $result['items'] = array_values(array_filter($result['items'], static fn(array $city): bool =>
            $city['type'] !== 'SubMun' && ($province !== 'none' || !in_array($city['provinceCode'], $codes, true))
        ));
        $result['stale'] = !empty($result['stale']) || !empty($provinces['stale']);
        return $result;
    }

    public function barangays(string $municipality, ?string $unused = null): array
    {
        return $this->lookup('barangays', $municipality);
    }

    public function assertSelection(string $regionCode, string $regionName, string $municipalityCode, string $municipalityName, string $barangayCode, string $barangayName, string $provinceCode, string $provinceName): void
    {
        $this->find($this->regions()['items'], $regionCode, $regionName);
        $this->find($this->provinces($regionCode)['items'], $provinceCode, $provinceName);
        $this->find($this->municipalities($regionCode, $provinceCode)['items'], $municipalityCode, $municipalityName);
        $this->find($this->barangays($municipalityCode)['items'], $barangayCode, $barangayName);
    }

    private function find(array $items, string $code, ?string $name = null): array
    {
        foreach ($items as $item) {
            if ($item['code'] === $code && ($name === null || strcasecmp(trim($item['name']), trim($name)) === 0)) return $item;
        }
        throw new RuntimeException('The selected location does not match its parent. Select the region, province, city or municipality, and barangay again.', 422);
    }

    private function base(): string
    {
        return rtrim($this->config->get('IMSAFE_LOCATION_API_URL', 'https://psgc.cloud/api/v2'), '/');
    }

    private function lookup(string $stage, ?string $parent = null): array
    {
        // A separate namespace prevents mixing legacy nine-digit and new ten-digit lists.
        $path = $this->config->storagePath() . '/locations-' . sha1('cloud-v3-' . $stage . '-' . ($parent ?: 'root')) . '.json';
        $cached = is_file($path) ? json_decode((string)file_get_contents($path), true) : null;
        $validCache = is_array($cached) && isset($cached['items']) && is_array($cached['items']) && $cached['items'] !== [];
        // NCR's province list is legitimately empty.
        if ($stage === 'provinces' && $parent === '1300000000' && isset($cached['items']) && $cached['items'] === []) $validCache = true;
        if ($validCache && time() - filemtime($path) < 86400) return array_replace($cached, ['fromCache' => true]);
        try {
            $items = $stage === 'barangays' && $parent === '1380600000'
                ? $this->manilaBarangays()
                : $this->decode($this->http->get($this->base() . $this->endpoint($stage, $parent), 'application/json', 12), $stage === 'provinces' && $parent === '1300000000');
            $result = ['items' => $items, 'source' => 'PSGC Cloud', 'updatedAt' => gmdate(DATE_ATOM), 'fromCache' => false, 'stale' => false];
            if (!is_dir($this->config->storagePath())) mkdir($this->config->storagePath(), 0775, true);
            if (file_put_contents($path, json_encode($result, JSON_THROW_ON_ERROR), LOCK_EX) === false) error_log('[iMSafe v2.0 location] Could not cache reference data.');
            return $result;
        } catch (\Throwable $error) {
            error_log('[iMSafe v2.0 location] ' . $error->getMessage());
            if ($validCache) return array_replace($cached, ['fromCache' => true, 'stale' => true]);
            throw new RuntimeException('Location reference data is temporarily unavailable. Retry the location list; your other answers are kept.');
        }
    }

    private function manilaBarangays(): array
    {
        $districts = array_values(array_filter($this->lookup('region-cities', '1300000000')['items'], static fn(array $item): bool =>
            $item['type'] === 'SubMun' && str_starts_with($item['code'], '13806')
        ));
        if (!$districts) throw new RuntimeException('Manila sub-municipality reference is unavailable.');
        $urls = array_map(fn(array $item): string => $this->base() . $this->endpoint('barangays', $item['code']), $districts);
        $items = [];
        // Concurrent requests keep fourteen Manila subdivisions from blocking in sequence.
        foreach ($this->http->getMany($urls) as $body) {
            foreach ($this->decode($body) as $item) $items[$item['code']] = $item;
        }
        $items = array_values($items);
        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $items;
    }

    private function decode(string $body, bool $allowEmpty = false): array
    {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $records = $data['data'] ?? $data;
        if (!is_array($records) || !array_is_list($records)) throw new RuntimeException('Invalid location response.');
        if (isset($data['total']) && (int)$data['total'] > count($records)) throw new RuntimeException('Incomplete location response.');
        if (!empty($data['links']['next']) || !empty($data['next_page_url'])) throw new RuntimeException('Incomplete location response.');
        $items = [];
        foreach ($records as $item) {
            if (!is_array($item) || !isset($item['code'], $item['name']) || !preg_match('/^\d{10}$/', (string)$item['code']) || !is_string($item['name']) || trim($item['name']) === '') throw new RuntimeException('Invalid location record.');
            $code = (string)$item['code'];
            if (isset($items[$code])) throw new RuntimeException('Duplicate location record.');
            $items[$code] = ['code' => $code, 'name' => trim($item['name']), 'provinceCode' => substr($code, 0, 5) . '00000', 'type' => (string)($item['type'] ?? '')];
        }
        if (!$items && !$allowEmpty) throw new RuntimeException('Empty location response.');
        $items = array_values($items);
        usort($items, static fn(array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return $items;
    }

    private function endpoint(string $stage, ?string $parent): string
    {
        $code = rawurlencode((string)$parent);
        return match ($stage) {
            'regions' => '/regions',
            'provinces' => "/regions/$code/provinces",
            'region-cities' => "/regions/$code/cities-municipalities",
            'province-cities' => "/provinces/$code/cities-municipalities",
            default => "/cities-municipalities/$code/barangays",
        };
    }
}
