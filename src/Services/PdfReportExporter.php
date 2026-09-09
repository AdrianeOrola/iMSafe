<?php
declare(strict_types=1);

namespace ImSafe\Services;

use ImSafe\Support\FloodLevels;

final class PdfReportExporter
{
    private const PAGE_WIDTH = 595;
    private const PAGE_HEIGHT = 842;
    private const LEFT = 36;
    private const RIGHT = 559;
    private const BOTTOM = 52;

    private array $pages = [];
    private int $pageIndex = -1;
    private float $y = 0;
    private string $context = '';

    public static function build(array $reports): string
    {
        $document = new self();
        $document->addOverview($reports);
        foreach ($reports as $report) $document->addIncident($report);
        return $document->compile();
    }

    private function addOverview(array $reports): void
    {
        $this->newPage('Overall incident reports');
        $this->text(self::LEFT, $this->y, 'Operational export', 22, true, '#102A3A');
        $this->y -= 22;
        $this->text(self::LEFT, $this->y, 'Generated ' . date('M j, Y g:i A T') . ' | ' . count($reports) . ' total reports', 9, false, '#60717A');
        $this->y -= 34;

        $metrics = [
            ['Total', count($reports), '#EAF2F4'],
            ['Active', $this->count($reports, static fn(array $row): bool => $row['status'] !== 'resolved'), '#EAF2FF'],
            ['Critical', $this->count($reports, static fn(array $row): bool => $row['legend'] === 'Red' && $row['status'] !== 'resolved'), '#FDEBEC'],
            ['Dispatched', $this->count($reports, static fn(array $row): bool => $row['status'] === 'dispatched'), '#F0ECFA'],
            ['Resolved', $this->count($reports, static fn(array $row): bool => $row['status'] === 'resolved'), '#E7F4EC'],
        ];
        foreach ($metrics as $index => [$label, $value, $fill]) {
            $x = self::LEFT + ($index * 105);
            $this->rect($x, $this->y - 58, 98, 58, $fill);
            $this->text($x + 10, $this->y - 18, $label, 8, true, '#435B66');
            $this->text($x + 10, $this->y - 45, (string)$value, 19, true, '#102A3A');
        }
        $this->y -= 84;

        $this->sectionBar('Priority and response status');
        foreach ([['Red', '#FDEBEC', '#9D2634'], ['Orange', '#FFF3DA', '#8A5700'], ['Green', '#E7F4EC', '#0B6748']] as $index => [$label, $fill, $text]) {
            $x = self::LEFT + ($index * 118);
            $this->rect($x, $this->y - 27, 108, 27, $fill);
            $this->text($x + 9, $this->y - 18, $label . ': ' . $this->count($reports, static fn(array $row): bool => $row['legend'] === $label), 9, true, $text);
        }
        foreach ([['Received', 'received', '#EAF2FF', '#265581'], ['Verified', 'verified', '#FFF3DA', '#8A5700'], ['Dispatched', 'dispatched', '#F0ECFA', '#634689'], ['Resolved', 'resolved', '#E7F4EC', '#0B6748']] as $index => [$label, $status, $fill, $text]) {
            $x = self::LEFT + ($index * 118);
            $this->rect($x, $this->y - 61, 108, 27, $fill);
            $this->text($x + 9, $this->y - 52, $label . ': ' . $this->count($reports, static fn(array $row): bool => $row['status'] === $status), 8, true, $text);
        }
        $this->y -= 84;

        $this->sectionBar('Report directory');
        if (!$reports) {
            $this->text(self::LEFT + 8, $this->y - 22, 'No incident reports were available at export time.', 10, false, '#60717A');
            return;
        }
        foreach ($reports as $report) {
            if ($this->y < 132) { $this->newPage('Report directory - continued'); $this->sectionBar('Report directory'); }
            [$fill, $text] = $this->priorityColors($report['legend']);
            $this->rect(self::LEFT, $this->y - 70, self::RIGHT - self::LEFT, 66, '#FAFCFC');
            $this->rect(self::LEFT, $this->y - 70, 7, 66, $fill);
            $this->text(self::LEFT + 16, $this->y - 17, $this->truncate($report['reference_code'], 38), 9, true, '#102A3A');
            $this->text(493, $this->y - 17, ucfirst($report['status']), 8, true, $text);
            $this->text(self::LEFT + 16, $this->y - 34, 'Disaster: ' . $this->truncate($report['specific_type'], 24), 8, true, '#102A3A');
            $this->text(225, $this->y - 34, 'Particular: ' . $this->truncate($report['particular_type'] ?: 'Not recorded', 40), 8, false, '#435B66');
            $this->text(self::LEFT + 16, $this->y - 50, 'Reporter: ' . $this->truncate($report['reporter_display'], 35), 8, false, '#435B66');
            $this->text(320, $this->y - 50, 'Coordinates: ' . ($report['coordinates'] ?: 'Not recorded'), 8, false, '#435B66');
            $this->text(self::LEFT + 16, $this->y - 64, 'Location: ' . $this->truncate($report['full_location'], 88), 8, false, '#60717A');
            $this->y -= 76;
        }
    }

    private function addIncident(array $report): void
    {
        $this->newPage('Incident detail - ' . $report['reference_code']);
        [$priorityFill, $priorityText] = $this->priorityColors($report['legend']);
        $this->rect(self::LEFT, $this->y - 44, self::RIGHT - self::LEFT, 44, $priorityFill);
        $this->text(self::LEFT + 12, $this->y - 18, $report['specific_type'], 14, true, '#102A3A');
        $this->text(self::LEFT + 12, $this->y - 34, $report['reference_code'], 8, false, '#435B66');
        $this->text(452, $this->y - 25, $report['legend'] . ' priority', 10, true, $priorityText);
        $this->y -= 58;

        $this->section('Incident', [
            ['Status', ucfirst($report['status'])],
            ['Submitted', $report['created_at'] . ($report['updated_at'] !== '' ? ' | Updated ' . $report['updated_at'] : '')],
            ['Disaster', $report['specific_type']],
            ['Particular incident', $report['particular_type'] ?: 'Not recorded'],
            ['Disaster category', $report['general_type']],
            ['Summary', $report['summary']],
            ['Description', $report['incident_description']],
        ]);
        $this->section('Location', [
            ['Address', implode(', ', array_filter([$report['house_number'], $report['barangay_name'], $report['municipality_name'], $report['province_name'], $report['region_name']]))],
            ['Landmark', $report['nearby_landmark']],
            ['Coordinates', $report['coordinates']],
            ['Coordinate source', $report['coordinate_source']],
            ['Reference codes', implode(' / ', array_filter([$report['region_code'], $report['province_code'], $report['municipality_code'], $report['barangay_code']]))],
        ]);
        $this->section('Reporter and contact', [
            ['Reporter', $report['reporter_name'] ?: 'Anonymous'],
            ['Primary contact', $report['contact_number']],
            ['Alternate contact', $report['alternate_contact']],
            ['Email', $report['reporter_email']],
            ['Account', implode(' | ', array_filter([$report['account_name'], $report['account_email']])) ?: 'Anonymous submission'],
        ]);
        $this->section('Rapid assessment', [
            ['Priority', $report['legend'] . ($report['assessment_color'] !== '' ? ' / ' . $report['assessment_color'] : '')],
            ['Impact', $report['impact_detail']],
            ['Situation', $report['current_situation']],
            ['Assessment notes', $report['reporter_description']],
            ['Immediate needs', $report['needs'] ?: 'None recorded'],
            ['Photo evidence', $report['photo_evidence']],
        ]);
        if ($report['specific_type'] === 'Flood') {
            $this->section('Flood conditions', [
                ['Estimated depth', FloodLevels::describe($report['water_level'])],
                ['Water status', $report['water_trend']],
                ['Road passability', $report['road_passability']],
                ['People stranded', $report['people_stranded']],
                ['Houses affected', $report['houses_affected']],
                ['Households affected', $report['households_affected']],
                ['Evacuation needed', $report['evacuation_needed']],
                ['Rescue needed', $report['rescue_needed']],
            ]);
        }
        $this->section('Response', [
            ['Assigned team', $report['team_name'] ?: 'Unassigned'],
            ['Latest public update', $report['latest_update'] ?: 'None'],
            ['Update time', $report['latest_update_at']],
        ]);
    }

    private function section(string $title, array $fields): void
    {
        $this->ensure(34, $title);
        $this->sectionBar($title);
        foreach ($fields as [$label, $value]) $this->field($title, $label, (string)$value);
        $this->y -= 7;
    }

    private function field(string $section, string $label, string $value): void
    {
        $lines = $this->wrap($value === '' ? 'Not provided' : $value, 73);
        $first = true;
        foreach ($lines as $line) {
            if ($this->y < self::BOTTOM + 14) {
                $this->newPage($this->context . ' - continued');
                $this->sectionBar($section . ' - continued');
                $first = true;
            }
            if ($first) $this->text(self::LEFT + 6, $this->y - 10, $label, 8, true, '#435B66');
            $this->text(150, $this->y - 10, $line, 8.5, false, '#102A3A');
            $this->y -= 11;
            $first = false;
        }
        $this->line(150, $this->y - 2, self::RIGHT, $this->y - 2, '#EDF1F2');
        $this->y -= 7;
    }

    private function sectionBar(string $title): void
    {
        $this->rect(self::LEFT, $this->y - 22, self::RIGHT - self::LEFT, 22, '#EAF4F2');
        $this->text(self::LEFT + 8, $this->y - 15, $title, 9, true, '#08736D');
        $this->y -= 30;
    }

    private function ensure(float $height, string $context): void
    {
        if ($this->y - $height >= self::BOTTOM) return;
        $this->newPage($this->context . ' - continued');
        $this->text(self::LEFT, $this->y, $context . ' - continued', 12, true, '#102A3A');
        $this->y -= 22;
    }

    private function newPage(string $context): void
    {
        $this->context = $context;
        $this->pages[] = '';
        $this->pageIndex++;
        $this->rect(0, 772, self::PAGE_WIDTH, 70, '#102A3A');
        $this->text(self::LEFT, 810, 'iMSafe v2.0', 19, true, '#FFFFFF');
        $this->text(self::LEFT, 790, $context, 9, false, '#D8E6EA');
        $this->text(385, 808, 'ADMINISTRATOR EXPORT', 7.5, true, '#74E3DF');
        $this->text(385, 791, 'Sensitive operational record', 7.5, false, '#D8E6EA');
        $this->y = 744;
    }

    private function compile(): string
    {
        $pageCount = count($this->pages);
        foreach ($this->pages as $index => &$commands) {
            $commands .= $this->lineCommand(self::LEFT, 37, self::RIGHT, 37, '#D8E1E3');
            $commands .= $this->textCommand(self::LEFT, 21, 'Administrator export | Generated ' . date('Y-m-d H:i T'), 7.5, false, '#60717A');
            $commands .= $this->textCommand(475, 21, 'Page ' . ($index + 1) . ' of ' . $pageCount, 7.5, true, '#435B66');
        }
        unset($commands);

        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
        $references = [];
        foreach ($this->pages as $index => $commands) {
            $pageId = 5 + ($index * 2);
            $contentId = $pageId + 1;
            $references[] = $pageId . ' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($commands) . ">>\nstream\n" . $commands . 'endstream';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $references) . '] /Count ' . $pageCount . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) { $offsets[$id] = strlen($pdf); $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n"; }
        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        return $pdf . 'trailer << /Size ' . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    private function text(float $x, float $y, string $value, float $size, bool $bold, string $color): void { $this->pages[$this->pageIndex] .= $this->textCommand($x, $y, $value, $size, $bold, $color); }
    private function textCommand(float $x, float $y, string $value, float $size, bool $bold, string $color): string { [$red, $green, $blue] = $this->rgb($color); return "BT\n/" . ($bold ? 'F2' : 'F1') . ' ' . $size . " Tf\n$red $green $blue rg\n1 0 0 1 $x $y Tm\n(" . $this->escape($this->ascii($value)) . ") Tj\nET\n"; }
    private function rect(float $x, float $y, float $width, float $height, string $color): void { [$red, $green, $blue] = $this->rgb($color); $this->pages[$this->pageIndex] .= "q\n$red $green $blue rg\n$x $y $width $height re f\nQ\n"; }
    private function line(float $x1, float $y1, float $x2, float $y2, string $color): void { $this->pages[$this->pageIndex] .= $this->lineCommand($x1, $y1, $x2, $y2, $color); }
    private function lineCommand(float $x1, float $y1, float $x2, float $y2, string $color): string { [$red, $green, $blue] = $this->rgb($color); return "q\n$red $green $blue RG\n0.6 w\n$x1 $y1 m $x2 $y2 l S\nQ\n"; }
    private function wrap(string $value, int $width): array { $value = preg_replace('/\s+/', ' ', $this->ascii($value)) ?? ''; return explode("\n", wordwrap(trim($value), $width, "\n", true)); }
    private function ascii(string $value): string { $value = str_replace(['—', '–', '·', '•'], ['-', '-', '-', '-'], $value); $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value); return $converted === false ? preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value : $converted; }
    private function escape(string $value): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value); }
    private function rgb(string $hex): array { return array_map(static fn(string $part): string => number_format(hexdec($part) / 255, 3, '.', ''), str_split(ltrim($hex, '#'), 2)); }
    private function priorityColors(string $legend): array { return match ($legend) { 'Red' => ['#FDEBEC', '#9D2634'], 'Orange' => ['#FFF3DA', '#8A5700'], 'Green' => ['#E7F4EC', '#0B6748'], default => ['#EAF2F4', '#435B66'] }; }
    private function count(array $reports, callable $predicate): int { return count(array_filter($reports, $predicate)); }
    private function truncate(string $value, int $length): string { $value = $this->ascii($value); return strlen($value) <= $length ? $value : substr($value, 0, $length - 3) . '...'; }
}
