<?php
declare(strict_types=1);

namespace ImSafe\Services;

use RuntimeException;
use ZipArchive;

final class ExcelReportExporter
{
    public static function build(array $reports): string
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('Excel export requires the PHP ZIP extension.');
        $normalized = array_map([ReportExporter::class, 'normalized'], $reports);
        $temporary = tempnam(sys_get_temp_dir(), 'imsafe-xlsx-');
        if ($temporary === false) throw new RuntimeException('The Excel workbook could not be prepared.');

        $zip = new ZipArchive();
        try {
            if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('The Excel workbook could not be opened.');
            $zip->addFromString('[Content_Types].xml', self::contentTypes());
            $zip->addFromString('_rels/.rels', self::rootRelationships());
            $zip->addFromString('docProps/app.xml', self::appProperties());
            $zip->addFromString('docProps/core.xml', self::coreProperties());
            $zip->addFromString('xl/workbook.xml', self::workbook());
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelationships());
            $zip->addFromString('xl/styles.xml', self::styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', self::overviewSheet($normalized));
            $zip->addFromString('xl/worksheets/sheet2.xml', self::directorySheet($normalized));
            $zip->addFromString('xl/worksheets/sheet3.xml', self::reportsSheet($normalized));
            $zip->addFromString('xl/worksheets/sheet4.xml', self::floodSheet($normalized));
            if (!$zip->close()) throw new RuntimeException('The Excel workbook could not be finalized.');
            $bytes = file_get_contents($temporary);
            if ($bytes === false || !str_starts_with($bytes, 'PK')) throw new RuntimeException('The Excel workbook is invalid.');
            return $bytes;
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    private static function overviewSheet(array $reports): string
    {
        $total = count($reports);
        $active = count(array_filter($reports, static fn(array $row): bool => $row['status'] !== 'resolved'));
        $critical = count(array_filter($reports, static fn(array $row): bool => $row['legend'] === 'Red' && $row['status'] !== 'resolved'));
        $dispatched = count(array_filter($reports, static fn(array $row): bool => $row['status'] === 'dispatched'));
        $resolved = count(array_filter($reports, static fn(array $row): bool => $row['status'] === 'resolved'));
        $count = static fn(string $field, string $value): int => count(array_filter($reports, static fn(array $row): bool => $row[$field] === $value));
        $rows = [];
        $rows[] = self::row(1, [self::cell('A1', 'iMSafe v2.0 - Overall incident reports', 1)], 30);
        $rows[] = self::row(2, [self::cell('A2', 'Generated ' . date('M j, Y g:i A T') . ' | Use the directory for quick action and All details for the complete record.', 15)], 24);
        $rows[] = self::row(4, [self::cell('A4', 'Response overview', 2)], 24);
        $labels = ['Total reports', 'Active queue', 'Critical active', 'Dispatched', 'Resolved'];
        $values = [$total, $active, $critical, $dispatched, $resolved];
        $labelCells = $valueCells = [];
        foreach ($labels as $index => $label) {
            $column = self::column($index + 1);
            $labelCells[] = self::cell($column . '5', $label, 13);
            $valueCells[] = self::cell($column . '6', $values[$index], 14, true);
        }
        $rows[] = self::row(5, $labelCells, 23);
        $rows[] = self::row(6, $valueCells, 31);
        $rows[] = self::row(8, [self::cell('A8', 'Priority distribution', 2), self::cell('D8', 'Status distribution', 2)], 24);
        $priorities = [['Red', 6], ['Orange', 7], ['Green', 8]];
        $statuses = [['received', 'Received', 9], ['verified', 'Verified', 10], ['dispatched', 'Dispatched', 11], ['resolved', 'Resolved', 12]];
        foreach ($statuses as $offset => [$status, $label, $statusStyle]) {
            $row = 9 + $offset;
            $cells = [];
            if (isset($priorities[$offset])) {
                [$legend, $priorityStyle] = $priorities[$offset];
                $cells[] = self::cell('A' . $row, $legend, $priorityStyle);
                $cells[] = self::cell('B' . $row, $count('legend', $legend), $priorityStyle, true);
            }
            $cells[] = self::cell('D' . $row, $label, $statusStyle);
            $cells[] = self::cell('E' . $row, $count('status', $status), $statusStyle, true);
            $rows[] = self::row($row, $cells);
        }
        $rows[] = self::row(15, [self::cell('A15', 'Workbook guide', 2)], 24);
        $rows[] = self::row(16, [self::cell('A16', 'Report directory: key reporter, location, hazard, coordinate, and response fields. All details: complete export. Flood reports: depth, access, and affected counts. Row colors follow priority; status cells use blue, amber, violet, and green.', 15)], 48);
        return self::sheet($rows, [18, 18, 18, 18, 18, 18], ['A1:F1', 'A2:F2', 'A4:F4', 'A8:C8', 'D8:F8', 'A15:F15', 'A16:F16'], '', 'A1:F16');
    }

    private static function directorySheet(array $reports): string
    {
        $columns = [
            'Reference code' => 'reference_code', 'Submitted at' => 'created_at', 'Status' => 'status',
            'Priority' => 'legend', 'Disaster' => 'specific_type', 'Particular incident' => 'particular_type',
            'Reporter' => 'reporter_display', 'Primary contact' => 'contact_number', 'Reporter email' => 'reporter_email',
            'Complete location' => 'full_location', 'Coordinates' => 'coordinates', 'Coordinate source' => 'coordinate_source',
            'Assigned team' => 'team_name', 'Latest public update' => 'latest_update',
        ];
        $rows = [];
        $header = [];
        foreach (array_keys($columns) as $index => $title) $header[] = self::cell(self::column($index + 1) . '1', $title, 16);
        $rows[] = self::row(1, $header, 42);
        foreach ($reports as $index => $report) {
            $rowNumber = $index + 2;
            $baseStyle = match ($report['legend']) { 'Red' => 6, 'Orange' => 7, 'Green' => 8, default => ($index % 2 === 0 ? 4 : 5) };
            $cells = [];
            foreach (array_values($columns) as $columnIndex => $key) {
                $style = $key === 'status' ? self::statusStyle($report['status']) : $baseStyle;
                $cells[] = self::cell(self::column($columnIndex + 1) . $rowNumber, (string)($report[$key] ?? ''), $style);
            }
            $rows[] = self::row($rowNumber, $cells, 42);
        }
        $lastColumn = self::column(count($columns));
        if (!$reports) $rows[] = self::row(2, [self::cell('A2', 'No incident reports were available at export time.', 15)], 26);
        $lastRow = max(2, count($reports) + 1);
        $widths = [24, 20, 14, 13, 22, 28, 24, 19, 28, 48, 26, 20, 25, 42];
        return self::sheet($rows, $widths, $reports ? [] : ['A2:' . $lastColumn . '2'], '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>', 'A1:' . $lastColumn . $lastRow, 1, 2);
    }

    private static function reportsSheet(array $reports): string
    {
        $columns = ReportExporter::columns();
        $rows = [];
        $header = [];
        foreach (array_keys($columns) as $index => $title) $header[] = self::cell(self::column($index + 1) . '1', $title, 3);
        $rows[] = self::row(1, $header, 42);
        foreach ($reports as $index => $report) {
            $rowNumber = $index + 2;
            $baseStyle = match ($report['legend']) { 'Red' => 6, 'Orange' => 7, 'Green' => 8, default => ($index % 2 === 0 ? 4 : 5) };
            $cells = [];
            $columnIndex = 1;
            foreach ($columns as $key) {
                $style = $key === 'status' ? self::statusStyle($report['status']) : $baseStyle;
                $cells[] = self::cell(self::column($columnIndex) . $rowNumber, (string)($report[$key] ?? ''), $style);
                $columnIndex++;
            }
            $rows[] = self::row($rowNumber, $cells, 40);
        }
        if (!$reports) $rows[] = self::row(2, [self::cell('A2', 'No incident reports were available at export time.', 15)], 26);
        $lastColumn = self::column(count($columns));
        $lastRow = max(2, count($reports) + 1);
        return self::sheet($rows, self::reportWidths(array_keys($columns)), $reports ? [] : ['A2:' . $lastColumn . '2'], '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>', 'A1:' . $lastColumn . $lastRow, 1, 2);
    }

    private static function floodSheet(array $reports): string
    {
        $columns = [
            'Reference code' => 'reference_code', 'Submitted at' => 'created_at', 'Status' => 'status',
            'Priority' => 'legend', 'Barangay' => 'barangay_name', 'Municipality / city' => 'municipality_name',
            'Flood level' => 'water_level', 'Depth estimate' => 'flood_depth', 'Water status' => 'water_trend',
            'Road passability' => 'road_passability', 'People stranded' => 'people_stranded',
            'Houses affected' => 'houses_affected', 'Households affected' => 'households_affected',
            'Evacuation needed' => 'evacuation_needed', 'Rescue needed' => 'rescue_needed',
            'Assigned team' => 'team_name', 'Latest public update' => 'latest_update',
        ];
        $floodReports = array_values(array_filter($reports, static fn(array $row): bool => $row['specific_type'] === 'Flood'));
        $rows = [];
        $header = [];
        foreach (array_keys($columns) as $index => $title) $header[] = self::cell(self::column($index + 1) . '1', $title, 16);
        $rows[] = self::row(1, $header, 42);
        foreach ($floodReports as $index => $report) {
            $rowNumber = $index + 2;
            $baseStyle = match ($report['legend']) { 'Red' => 6, 'Orange' => 7, 'Green' => 8, default => ($index % 2 === 0 ? 4 : 5) };
            $cells = [];
            foreach (array_values($columns) as $columnIndex => $key) {
                $style = $key === 'status' ? self::statusStyle($report['status']) : $baseStyle;
                $cells[] = self::cell(self::column($columnIndex + 1) . $rowNumber, (string)($report[$key] ?? ''), $style);
            }
            $rows[] = self::row($rowNumber, $cells, 38);
        }
        $lastColumn = self::column(count($columns));
        if (!$floodReports) $rows[] = self::row(2, [self::cell('A2', 'No flood reports were available at export time.', 15)], 26);
        $lastRow = max(2, count($floodReports) + 1);
        $widths = [22, 20, 14, 13, 22, 25, 24, 24, 16, 20, 17, 17, 20, 18, 16, 25, 42];
        return self::sheet($rows, $widths, $floodReports ? [] : ['A2:' . $lastColumn . '2'], '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>', 'A1:' . $lastColumn . $lastRow, 1);
    }

    private static function sheet(array $rows, array $widths, array $merges, string $afterRows, string $dimension, int $freezeRows = 0, int $freezeColumns = 0): string
    {
        $columnXml = '';
        foreach ($widths as $index => $width) {
            $number = $index + 1;
            $columnXml .= '<col min="' . $number . '" max="' . $number . '" width="' . $width . '" customWidth="1"/>';
        }
        $pane = '';
        if ($freezeRows > 0 || $freezeColumns > 0) {
            $attributes = [];
            if ($freezeColumns > 0) $attributes[] = 'xSplit="' . $freezeColumns . '"';
            if ($freezeRows > 0) $attributes[] = 'ySplit="' . $freezeRows . '"';
            $attributes[] = 'topLeftCell="' . self::column($freezeColumns + 1) . ($freezeRows + 1) . '"';
            $attributes[] = 'activePane="' . ($freezeColumns > 0 && $freezeRows > 0 ? 'bottomRight' : ($freezeColumns > 0 ? 'topRight' : 'bottomLeft')) . '"';
            $attributes[] = 'state="frozen"';
            $pane = '<pane ' . implode(' ', $attributes) . '/>';
        }
        $mergeXml = $merges ? '<mergeCells count="' . count($merges) . '">' . implode('', array_map(static fn(string $range): string => '<mergeCell ref="' . $range . '"/>', $merges)) . '</mergeCells>' : '';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="' . $dimension . '"/><sheetViews><sheetView workbookViewId="0">' . $pane . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="18"/><cols>' . $columnXml . '</cols><sheetData>' . implode('', $rows) . '</sheetData>'
            . $afterRows . $mergeXml . '<pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';
    }

    private static function row(int $number, array $cells, int $height = 30): string
    {
        return '<row r="' . $number . '" ht="' . $height . '" customHeight="1">' . implode('', $cells) . '</row>';
    }

    private static function cell(string $reference, string|int $value, int $style, bool $numeric = false): string
    {
        if ($numeric) return '<c r="' . $reference . '" s="' . $style . '"><v>' . (int)$value . '</v></c>';
        $value = self::xmlText((string)$value);
        return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $value . '</t></is></c>';
    }

    private static function xmlText(string $value): string
    {
        $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function column(int $number): string
    {
        $name = '';
        while ($number > 0) { $number--; $name = chr(65 + ($number % 26)) . $name; $number = intdiv($number, 26); }
        return $name;
    }

    private static function statusStyle(string $status): int
    {
        return match ($status) { 'received' => 9, 'verified' => 10, 'dispatched' => 11, 'resolved' => 12, default => 4 };
    }

    private static function reportWidths(array $titles): array
    {
        return array_map(static function (string $title): int {
            if (str_contains($title, 'description') || str_contains($title, 'update') || $title === 'Summary') return 42;
            if (str_contains($title, 'email') || str_contains($title, 'situation') || str_contains($title, 'needs')) return 30;
            if (str_contains($title, 'code')) return 20;
            if (str_contains($title, 'contact') || str_contains($title, 'time') || str_contains($title, 'Submitted') || str_contains($title, 'updated')) return 20;
            return 22;
        }, $titles);
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4"><font><sz val="10"/><color rgb="FF102A3A"/><name val="Aptos"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><b/><sz val="10"/><color rgb="FF102A3A"/><name val="Aptos"/></font></fonts>'
            . '<fills count="11"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF102A3A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF08736D"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4F7F7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFDEBEC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF3DA"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7F4EC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF2FF"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0ECFA"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD5E0E2"/></left><right style="thin"><color rgb="FFD5E0E2"/></right><top style="thin"><color rgb="FFD5E0E2"/></top><bottom style="thin"><color rgb="FFD5E0E2"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="17">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            . self::bodyStyle(0, 4) . self::bodyStyle(0, 5) . self::bodyStyle(0, 6) . self::bodyStyle(0, 7) . self::bodyStyle(0, 8)
            . self::bodyStyle(3, 9) . self::bodyStyle(3, 7) . self::bodyStyle(3, 10) . self::bodyStyle(3, 8)
            . '<xf numFmtId="0" fontId="3" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="5" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function bodyStyle(int $fontId, int $fillId): string
    {
        return '<xf numFmtId="0" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Overview" sheetId="1" r:id="rId1"/><sheet name="Report directory" sheetId="2" r:id="rId2"/><sheet name="All details" sheetId="3" r:id="rId3"/><sheet name="Flood reports" sheetId="4" r:id="rId4"/></sheets><calcPr calcId="191029"/></workbook>';
    }

    private static function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet4.xml"/><Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet4.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private static function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private static function coreProperties(): string
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>iMSafe v2.0 Overall Incident Reports</dc:title><dc:creator>iMSafe v2.0</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $date . '</dcterms:modified></cp:coreProperties>';
    }

    private static function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>iMSafe v2.0</Application><TitlesOfParts><vt:vector size="4" baseType="lpstr"><vt:lpstr>Overview</vt:lpstr><vt:lpstr>Report directory</vt:lpstr><vt:lpstr>All details</vt:lpstr><vt:lpstr>Flood reports</vt:lpstr></vt:vector></TitlesOfParts></Properties>';
    }
}
