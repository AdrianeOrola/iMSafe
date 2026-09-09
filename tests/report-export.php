<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/src/bootstrap.php';

use ImSafe\Services\ReportExporter;
use ImSafe\Support\Config;
use ImSafe\Support\FloodLevels;

$checks = 0;
$expect = static function (bool $condition, string $name) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $checks++;
};

$report = [
    'id' => 1,
    'reference_code' => 'IMS-EXPORT-TEST',
    'created_at' => '2026-09-09 08:00:00',
    'updated_at' => '2026-09-09 09:00:00',
    'status' => 'verified',
    'legend' => 'Red',
    'general_type' => 'Hydrometeorological',
    'specific_type' => 'Flood',
    'particular_type' => 'Flash flood',
    'summary' => 'Flood assessment',
    'incident_description' => 'Road and homes affected.',
    'reporter_name' => '=HYPERLINK("https://invalid.test")',
    'contact_number' => '09170000000',
    'alternate_contact' => '',
    'reporter_email' => 'resident@example.test',
    'account_name' => 'Test resident',
    'account_email' => 'resident@example.test',
    'region_code' => '0400000000',
    'region_name' => 'CALABARZON',
    'province_code' => '0402100000',
    'province_name' => 'Cavite',
    'municipality_code' => '0402103000',
    'municipality_name' => 'City of Bacoor',
    'barangay_code' => '0402103001',
    'barangay_name' => 'Alima',
    'house_number' => 'Test Street',
    'nearby_landmark' => 'School',
    'latitude' => '14.4101234',
    'longitude' => '120.9765678',
    'coordinate_source' => 'Device location',
    'assessment_color' => 'red',
    'impact_detail' => 'Evacuation required',
    'current_situation' => 'People are in immediate danger',
    'reporter_description' => 'Water is rising.',
    'evidence_note' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg',
    'needs' => ['Rescue team', 'Evacuation transport'],
    'water_level' => 'Waist-deep',
    'water_trend' => 'Rising',
    'road_passability' => 'Not passable',
    'people_stranded' => '12',
    'houses_affected' => '8',
    'households_affected' => '10',
    'evacuation_needed' => 'true',
    'rescue_needed' => 'true',
    'team_name' => 'Municipal Response Unit',
    'latest_update' => 'Team preparing to deploy.',
    'latest_update_at' => '2026-09-09 09:00:00',
];

$expect(FloodLevels::measurement('Ankle-deep') === 'Up to 6 in (up to 0.5 ft)', 'ankle measurement');
$expect(FloodLevels::describe('Waist-deep') === 'Waist-deep — 19–36 in (1.6–3.0 ft)', 'waist description');
$expect(FloodLevels::isValid('Chest-deep or higher'), 'highest level accepted');
$expect(!FloodLevels::isValid('Roof-deep'), 'unknown level rejected');

$previousAdminEmail = getenv('IMSAFE_ADMIN_EMAIL');
$previousAdminPassword = getenv('IMSAFE_ADMIN_PASSWORD');
putenv('IMSAFE_ADMIN_EMAIL');
putenv('IMSAFE_ADMIN_PASSWORD');
$expect((new Config())->adminEmail() === 'admin@imsafe.local', 'safe fallback admin email');
$expect((new Config())->isDefaultAdminPassword(), 'bundled fallback remains blocked');
putenv('IMSAFE_ADMIN_PASSWORD=imsafe-local-admin');
$expect((new Config())->isDefaultAdminPassword(), 'bundled admin password rejected');
putenv('IMSAFE_ADMIN_PASSWORD=unique-test-admin-password');
$expect(!(new Config())->isDefaultAdminPassword(), 'custom admin password accepted');
if ($previousAdminPassword === false) putenv('IMSAFE_ADMIN_PASSWORD');
else putenv('IMSAFE_ADMIN_PASSWORD=' . $previousAdminPassword);
if ($previousAdminEmail === false) putenv('IMSAFE_ADMIN_EMAIL');
else putenv('IMSAFE_ADMIN_EMAIL=' . $previousAdminEmail);

$csv = ReportExporter::csv([$report]);
$expect(str_starts_with($csv, "\xEF\xBB\xBF"), 'Excel UTF-8 marker');
$expect(str_contains($csv, 'Flood depth estimate'), 'CSV measurement column');
$expect(str_contains($csv, '19–36 in (1.6–3.0 ft)'), 'CSV precise depth');
$expect(str_contains($csv, "'=HYPERLINK"), 'CSV formula neutralized');
$expect(str_contains($csv, "'09170000000"), 'CSV phone leading zero preserved');
$expect(str_contains($csv, "'0400000000"), 'CSV location code leading zero preserved');
$expect(str_contains($csv, 'Rescue team; Evacuation transport'), 'CSV needs included');
$expect(str_contains($csv, 'Reporter') && str_contains($csv, 'Complete location'), 'CSV reporter and complete location columns');
$expect(str_contains($csv, '14.4101234, 120.9765678'), 'CSV coordinates included');

$xlsx = ReportExporter::xlsx([$report]);
$expect(str_starts_with($xlsx, "PK"), 'XLSX ZIP signature');
$xlsxPath = tempnam(sys_get_temp_dir(), 'imsafe-xlsx-');
if ($xlsxPath === false) throw new RuntimeException('Could not create an XLSX test file.');
file_put_contents($xlsxPath, $xlsx);
$archive = new ZipArchive();
$expect($archive->open($xlsxPath) === true, 'XLSX archive opens');
$workbookXml = (string)$archive->getFromName('xl/workbook.xml');
$stylesXml = (string)$archive->getFromName('xl/styles.xml');
$overviewXml = (string)$archive->getFromName('xl/worksheets/sheet1.xml');
$directoryXml = (string)$archive->getFromName('xl/worksheets/sheet2.xml');
$reportsXml = (string)$archive->getFromName('xl/worksheets/sheet3.xml');
$floodXml = (string)$archive->getFromName('xl/worksheets/sheet4.xml');
$expect(str_contains($workbookXml, 'name="Overview"'), 'XLSX overview sheet');
$expect(str_contains($workbookXml, 'name="Report directory"'), 'XLSX directory sheet');
$expect(str_contains($workbookXml, 'name="All details"'), 'XLSX complete details sheet');
$expect(str_contains($workbookXml, 'name="Flood reports"'), 'XLSX flood sheet');
$expect(str_contains($stylesXml, 'FFFDEBEC') && str_contains($stylesXml, 'FFE7F4EC'), 'XLSX priority colors');
$expect(substr_count($overviewXml, '<row r="9"') === 1 && substr_count($overviewXml, '<row r="10"') === 1, 'XLSX overview rows are unique');
$expect(str_contains($reportsXml, '<autoFilter') && str_contains($reportsXml, 'state="frozen"'), 'XLSX reports filters and frozen header');
$expect(str_contains($reportsXml, 'IMS-EXPORT-TEST') && str_contains($reportsXml, '09170000000'), 'XLSX report values');
$expect(str_contains($directoryXml, 'Reporter') && str_contains($directoryXml, 'Complete location') && str_contains($directoryXml, 'Flash flood'), 'XLSX operational directory fields');
$expect(str_contains($directoryXml, '14.4101234, 120.9765678'), 'XLSX directory coordinates');
$expectedFloodDepth = htmlspecialchars(FloodLevels::measurement('Waist-deep'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
$expect(str_contains($floodXml, $expectedFloodDepth), 'XLSX precise flood depth');
for ($index = 0; $index < $archive->numFiles; $index++) {
    $entry = $archive->getNameIndex($index);
    if (!is_string($entry) || !str_ends_with($entry, '.xml')) continue;
    $xml = (string)$archive->getFromIndex($index);
    $document = new DOMDocument();
    $expect(@$document->loadXML($xml), 'valid XLSX XML: ' . $entry);
}
$archive->close();
unlink($xlsxPath);

$nonFlood = $report;
$nonFlood['specific_type'] = 'Earthquake';
foreach (['water_level', 'water_trend', 'road_passability', 'people_stranded', 'houses_affected', 'households_affected', 'evacuation_needed', 'rescue_needed'] as $key) unset($nonFlood[$key]);
$nonFloodCsv = ReportExporter::csv([$nonFlood]);
$csvLines = preg_split('/\r?\n/', trim(substr($nonFloodCsv, 3)));
$headers = str_getcsv($csvLines[0]);
$values = str_getcsv($csvLines[1]);
$nonFloodExport = array_combine($headers, $values);
$expect($nonFloodExport['Flood level'] === '', 'non-flood level remains blank');
$expect($nonFloodExport['People stranded'] === '', 'non-flood count remains blank');
$expect($nonFloodExport['Evacuation needed'] === '', 'non-flood action remains blank');

$pdf = ReportExporter::pdf([$report]);
$expect(str_starts_with($pdf, '%PDF-1.4'), 'PDF signature');
$expect(str_contains($pdf, '/Type /Pages'), 'PDF page tree');
$expect(str_contains($pdf, '/BaseFont /Helvetica-Bold'), 'PDF readable typography');
$expect(str_contains($pdf, 'Operational export'), 'PDF overview');
$expect(str_contains($pdf, 'Flood conditions'), 'PDF flood section');
$expect(str_contains($pdf, 'Waist-deep'), 'PDF flood level');
$expect(str_contains($pdf, '19-36 in'), 'PDF precise depth');
$expect(str_contains($pdf, '(Landmark)') && str_contains($pdf, '(School)'), 'PDF landmark included');
$expect(str_contains($pdf, '(Update time)') && str_contains($pdf, '(2026-09-09 09:00:00)'), 'PDF update time included');
$expect(str_contains($pdf, '(Particular incident)') && str_contains($pdf, '(Flash flood)'), 'PDF particular incident included');
$expect(str_contains($pdf, '(Coordinates)') && str_contains($pdf, '(14.4101234, 120.9765678)'), 'PDF coordinates included');
$expect(str_contains($pdf, 'Page 1 of'), 'PDF page numbering');
$expect(str_ends_with($pdf, '%%EOF'), 'PDF complete');

$longReport = $report;
$longReport['reference_code'] = 'IMS-05684F9A-A691FEA36B32370B';
$longReport['incident_description'] = str_repeat('Extended incident detail for pagination testing. ', 130);
$longPdf = ReportExporter::pdf([$longReport]);
$expect(substr_count($longPdf, '/Type /Page ') >= 3, 'PDF long reports paginate');
$expect(str_ends_with($longPdf, '%%EOF'), 'paginated PDF complete');

echo "PASS: $checks flood-level and report-export checks.\n";
