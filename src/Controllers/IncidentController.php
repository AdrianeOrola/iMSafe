<?php
declare(strict_types=1);
namespace ImSafe\Controllers;

use ImSafe\Repositories\IncidentRepository;
use ImSafe\Services\EvidenceStorage;
use ImSafe\Services\LocationService;
use ImSafe\Support\DisasterCatalog;
use RuntimeException;

final class IncidentController
{
    private const IMPACTS = [
        'Green' => ['No observed impact', 'Minor disruption resolved', 'Routine community check-in'],
        'Orange' => ['Moderate community disruption', 'Partial access limitation', 'Services disrupted', 'Localized households affected'],
        'Red' => ['Critical life-safety risk', 'Widespread household impact', 'Evacuation required', 'Major infrastructure disruption'],
    ];
    private const SITUATIONS = [
        'Orange' => ['Hazard conditions increasing', 'Access is limited in parts of the area', 'Community is preparing to evacuate', 'Response team assessment needed'],
        'Red' => ['People are in immediate danger', 'Evacuation is actively required', 'Road access is blocked', 'Rescue resources are required'],
    ];
    private const NEEDS = [
        'Orange' => ['Food & water', 'Medical assistance', 'Temporary shelter', 'Communication support', 'Road clearing'],
        'Red' => ['Rescue team', 'Medical assistance', 'Evacuation transport', 'Food & water', 'Emergency shelter', 'Power and communications'],
    ];

    public function __construct(private IncidentRepository $repository, private LocationService $locations, private EvidenceStorage $evidenceStorage) {}

    public function submit(array $input, array $files, ?int $userId = null): string
    {
        $value = static fn(string $key): string => trim((string)($input[$key] ?? ''));
        $legend = $value('legend'); $general = $value('general_type'); $specific = $value('specific_type'); $particular = $value('particular_type');
        $required = [$legend, $general, $specific, $particular, $value('region_code'), $value('province_code'), $value('municipality_code'), $value('barangay_code')];
        if (in_array('', $required, true) || !in_array($legend, array_keys(self::IMPACTS), true)) throw new RuntimeException('Complete the disaster, observed effect, priority, and exact location before submitting.');
        foreach (['region_code', 'province_code', 'municipality_code', 'barangay_code'] as $field) if (!preg_match('/^[A-Za-z0-9-]{1,32}$/', $value($field))) throw new RuntimeException('The selected location code is invalid. Reload the location lists and try again.');
        $details = DisasterCatalog::validate($legend, $general, $specific, $particular, $input);
        foreach (['reporter_name' => 120, 'contact_number' => 32, 'alternate_contact' => 32, 'email' => 255, 'region_name' => 160, 'province_name' => 160, 'municipality_name' => 160, 'barangay_name' => 160, 'house_number' => 160, 'nearby_landmark' => 255, 'reporter_description' => 4000, 'impact_detail' => 255, 'current_situation' => 255] as $field => $max) if (strlen($value($field)) > $max) throw new RuntimeException('One or more fields are longer than allowed. Shorten the text and try again.');
        if ($value('email') !== '' && !filter_var($value('email'), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address or leave it blank.');
        $this->locations->assertSelection($value('region_code'), $value('region_name'), $value('municipality_code'), $value('municipality_name'), $value('barangay_code'), $value('barangay_name'), $value('province_code'), $value('province_name'));

        $green = $legend === 'Green';
        $impact = $green ? 'No observed impact' : $value('impact_detail');
        $situation = $green ? 'Conditions stable — no active threat' : $value('current_situation');
        $description = $green ? 'Routine assessment: no immediate danger or assistance requirement reported.' : $value('reporter_description');
        if ($impact === '' || $situation === '' || $description === '') throw new RuntimeException('Complete the rapid-assessment details before submitting.');
        if (!$green && !in_array($impact, self::IMPACTS[$legend], true)) throw new RuntimeException('Choose a valid impact detail for the selected protocol.');
        if (!$green && !in_array($situation, self::SITUATIONS[$legend], true)) throw new RuntimeException('Choose a valid current situation for the selected community status.');

        $needs = $green ? ['No immediate assistance required'] : [];
        if (!$green) foreach ((array)($input['needs'] ?? []) as $need) { $normalized = trim((string)$need); if ($normalized !== '' && in_array($normalized, self::NEEDS[$legend], true) && !in_array($normalized, $needs, true)) $needs[] = $normalized; }

        $cameraUpload = $files['evidence_camera'] ?? null;
        $fileUpload = $files['evidence'] ?? null;
        $hasCameraUpload = is_array($cameraUpload) && (int)($cameraUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $upload = $hasCameraUpload ? $cameraUpload : $fileUpload;
        $hasUpload = is_array($upload) && (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $browserCoordinates = $hasUpload ? $this->browserCoordinates($input) : null;
        $coordinates = $browserCoordinates ?? $this->evidenceStorage->coordinates($upload);
        if ($coordinates !== null) {
            $details['latitude'] = number_format($coordinates['latitude'], 7, '.', '');
            $details['longitude'] = number_format($coordinates['longitude'], 7, '.', '');
            $details['coordinate_source'] = $browserCoordinates !== null ? 'Device location' : 'Photo EXIF';
        }
        $evidence = $this->evidenceStorage->store($upload);
        $location = ['regionCode' => $value('region_code'), 'regionName' => $value('region_name'), 'provinceCode' => $value('province_code') === 'none' ? null : $value('province_code'), 'provinceName' => $value('province_code') === 'none' ? null : $value('province_name'), 'municipalityCode' => $value('municipality_code'), 'municipalityName' => $value('municipality_name'), 'barangayCode' => $value('barangay_code'), 'barangayName' => $value('barangay_name'), 'houseNumber' => $value('house_number'), 'nearbyLandmark' => $value('nearby_landmark')];
        try {
            return $this->repository->create(['userId' => $userId, 'reporterName' => $value('reporter_name'), 'contactNumber' => $value('contact_number'), 'legend' => $legend, 'generalType' => $general, 'specificType' => $specific, 'summary' => "{$legend} priority | {$specific}: {$particular} | {$location['barangayName']}, {$location['municipalityName']}", 'description' => "{$legend} community assessment for {$specific} ({$particular}) at {$location['barangayName']}, {$location['municipalityName']}. {$description}"], $location, ['color' => strtolower($legend), 'impact' => $impact, 'situation' => $situation, 'description' => $description, 'evidence' => $evidence, 'alternateContact' => $value('alternate_contact'), 'email' => $value('email')], $needs, $details);
        } catch (\Throwable $error) {
            $this->evidenceStorage->remove($evidence);
            throw $error;
        }
    }

    /** @return array{latitude:float,longitude:float}|null */
    private function browserCoordinates(array $input): ?array
    {
        $latitude = trim((string)($input['latitude'] ?? ''));
        $longitude = trim((string)($input['longitude'] ?? ''));
        if ($latitude === '' && $longitude === '') return null;
        if (!is_numeric($latitude) || !is_numeric($longitude)) throw new RuntimeException('The photo coordinates are invalid. Clear them and capture the location again.');
        $lat = (float)$latitude;
        $lng = (float)$longitude;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new RuntimeException('The photo coordinates are outside the valid latitude and longitude ranges.');
        return ['latitude' => $lat, 'longitude' => $lng];
    }
}
