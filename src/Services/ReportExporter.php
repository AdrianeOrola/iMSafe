<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Support\FloodLevels;

final class ReportExporter
{
    private const COLUMNS = [
        'Reference code' => 'reference_code',
        'Submitted at' => 'created_at',
        'Status' => 'status',
        'Priority legend' => 'legend',
        'Disaster' => 'specific_type',
        'Particular incident' => 'particular_type',
        'Reporter' => 'reporter_display',
        'Primary contact' => 'contact_number',
        'Reporter email' => 'reporter_email',
        'Complete location' => 'full_location',
        'Coordinates' => 'coordinates',
        'Latitude' => 'latitude',
        'Longitude' => 'longitude',
        'Coordinate source' => 'coordinate_source',
        'Last updated' => 'updated_at',
        'Disaster category' => 'general_type',
        'Summary' => 'summary',
        'Incident description' => 'incident_description',
        'Reporter name' => 'reporter_name',
        'Alternate contact' => 'alternate_contact',
        'Account name' => 'account_name',
        'Account email' => 'account_email',
        'Region code' => 'region_code',
        'Region' => 'region_name',
        'Province code' => 'province_code',
        'Province' => 'province_name',
        'Municipality code' => 'municipality_code',
        'Municipality / city' => 'municipality_name',
        'Barangay code' => 'barangay_code',
        'Barangay' => 'barangay_name',
        'House / street' => 'house_number',
        'Nearby landmark' => 'nearby_landmark',
        'Assessment color' => 'assessment_color',
        'Impact detail' => 'impact_detail',
        'Current situation' => 'current_situation',
        'Assessment description' => 'reporter_description',
        'Immediate needs' => 'needs',
        'Flood level' => 'water_level',
        'Flood depth estimate' => 'flood_depth',
        'Water status' => 'water_trend',
        'Road passability' => 'road_passability',
        'People stranded' => 'people_stranded',
        'Houses affected' => 'houses_affected',
        'Households affected' => 'households_affected',
        'Evacuation needed' => 'evacuation_needed',
        'Rescue needed' => 'rescue_needed',
        'Photo evidence' => 'photo_evidence',
        'Assigned team' => 'team_name',
        'Latest public update' => 'latest_update',
        'Latest update at' => 'latest_update_at',
    ];
    private const EXCEL_TEXT_FIELDS = [
        'reference_code', 'contact_number', 'alternate_contact', 'latitude', 'longitude',
        'region_code', 'province_code', 'municipality_code', 'barangay_code',
    ];

    public static function csv(array $reports): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) throw new \RuntimeException('The export file could not be prepared.');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_keys(self::COLUMNS));
        foreach ($reports as $report) {
            $normalized = self::normalize($report);
            $values = [];
            foreach (self::COLUMNS as $key) {
                $value = (string)($normalized[$key] ?? '');
                $values[] = in_array($key, self::EXCEL_TEXT_FIELDS, true) && $value !== '' ? "'" . ltrim($value, "'") : self::spreadsheetSafe($value);
            }
            fputcsv($stream, $values);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if ($csv === false) throw new \RuntimeException('The export file could not be read.');
        return $csv;
    }

    public static function xlsx(array $reports): string
    {
        return ExcelReportExporter::build($reports);
    }

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function normalized(array $report): array
    {
        return self::normalize($report);
    }

    public static function pdf(array $reports): string
    {
        return PdfReportExporter::build(array_map([self::class, 'normalized'], $reports));
    }

    private static function normalize(array $report): array
    {
        $text = static fn(string $key): string => trim((string)($report[$key] ?? ''));
        $isFlood = $text('specific_type') === 'Flood';
        $boolean = static function (string $key) use ($text, $isFlood): string {
            if (!$isFlood) return '';
            $value = strtolower($text($key));
            if ($value === '') return 'Not recorded';
            return in_array($value, ['1', 'true', 'yes'], true) ? 'Yes' : 'No';
        };
        $floodValue = static fn(string $key): string => $isFlood ? $text($key) : '';
        $floodCount = static function (string $key) use ($text, $isFlood): string {
            if (!$isFlood) return '';
            return $text($key) === '' ? 'Not recorded' : $text($key);
        };
        $needs = $report['needs'] ?? [];
        if (is_array($needs)) $needs = implode('; ', array_map('strval', $needs));

        return array_merge($report, [
            'reference_code' => $text('reference_code'),
            'created_at' => $text('created_at'),
            'updated_at' => $text('updated_at'),
            'status' => $text('status'),
            'legend' => $text('legend'),
            'general_type' => $text('general_type'),
            'specific_type' => $text('specific_type'),
            'particular_type' => $text('particular_type'),
            'summary' => $text('summary'),
            'incident_description' => $text('incident_description'),
            'reporter_name' => $text('reporter_name'),
            'contact_number' => $text('contact_number'),
            'alternate_contact' => $text('alternate_contact'),
            'reporter_email' => $text('reporter_email'),
            'account_name' => $text('account_name'),
            'account_email' => $text('account_email'),
            'reporter_display' => $text('reporter_name') ?: ($text('account_name') ?: 'Anonymous'),
            'region_code' => $text('region_code'),
            'region_name' => $text('region_name'),
            'province_code' => $text('province_code'),
            'province_name' => $text('province_name'),
            'municipality_code' => $text('municipality_code'),
            'municipality_name' => $text('municipality_name'),
            'barangay_code' => $text('barangay_code'),
            'barangay_name' => $text('barangay_name'),
            'house_number' => $text('house_number'),
            'nearby_landmark' => $text('nearby_landmark'),
            'full_location' => implode(', ', array_filter([$text('house_number'), $text('barangay_name'), $text('municipality_name'), $text('province_name'), $text('region_name')])),
            'latitude' => $text('latitude'),
            'longitude' => $text('longitude'),
            'coordinates' => $text('latitude') !== '' && $text('longitude') !== '' ? $text('latitude') . ', ' . $text('longitude') : '',
            'coordinate_source' => $text('coordinate_source'),
            'assessment_color' => $text('assessment_color'),
            'impact_detail' => $text('impact_detail'),
            'current_situation' => $text('current_situation'),
            'reporter_description' => $text('reporter_description'),
            'needs' => trim((string)$needs),
            'water_level' => $floodValue('water_level'),
            'flood_depth' => $isFlood ? FloodLevels::measurement($text('water_level')) : '',
            'water_trend' => $floodValue('water_trend'),
            'road_passability' => $floodValue('road_passability'),
            'people_stranded' => $floodCount('people_stranded'),
            'houses_affected' => $floodCount('houses_affected'),
            'households_affected' => $floodCount('households_affected'),
            'evacuation_needed' => $boolean('evacuation_needed'),
            'rescue_needed' => $boolean('rescue_needed'),
            'photo_evidence' => $text('evidence_note') !== '' ? 'Available to administrators' : 'Not provided',
            'team_name' => $text('team_name'),
            'latest_update' => $text('latest_update'),
            'latest_update_at' => $text('latest_update_at'),
        ]);
    }

    private static function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

}
