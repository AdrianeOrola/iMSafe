<?php
declare(strict_types=1);
namespace ImSafe\Repositories;
use ImSafe\Support\Database;
use ImSafe\Support\DisasterCatalog;
use PDO;

final class IncidentRepository {
    public function __construct(private Database $database) {}
    public function create(array $incident, array $location, array $assessment, array $needs, array $details): string {
        $pdo = $this->database->pdo(); $pdo->beginTransaction();
        try {
            $reference = 'IMS-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(8)));
            $stmt = $pdo->prepare('INSERT INTO incidents (local_user_id, reference_code, reporter_name, contact_number, legend, general_type, specific_type, summary, description) VALUES (:user_id,:reference,:reporter,:contact,:legend,:general,:specific,:summary,:description)');
            $stmt->execute(['user_id'=>$incident['userId'] ?: null, 'reference'=>$reference, 'reporter'=>$incident['reporterName'] ?: null, 'contact'=>$incident['contactNumber'] ?: null, 'legend'=>$incident['legend'], 'general'=>$incident['generalType'], 'specific'=>$incident['specificType'], 'summary'=>$incident['summary'], 'description'=>$incident['description']]); $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO incident_locations (incident_id,region_code,region_name,province_code,province_name,municipality_code,municipality_name,barangay_code,barangay_name,house_number,nearby_landmark) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$location['regionCode'],$location['regionName'],$location['provinceCode'],$location['provinceName'],$location['municipalityCode'],$location['municipalityName'],$location['barangayCode'],$location['barangayName'],$location['houseNumber'] ?: null,$location['nearbyLandmark'] ?: null]);
            $pdo->prepare('INSERT INTO rapid_assessments (incident_id,assessment_color,impact_detail,current_situation,reporter_description,evidence_note,alternate_contact,email) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$assessment['color'],$assessment['impact'],$assessment['situation'],$assessment['description'],$assessment['evidence'] ?: null,$assessment['alternateContact'] ?: null,$assessment['email'] ?: null]); $assessmentId = (int)$pdo->lastInsertId();
            $needStmt = $pdo->prepare('INSERT INTO assessment_needs (rapid_assessment_id,need_label) VALUES (?,?)'); foreach ($needs as $need) $needStmt->execute([$assessmentId,$need]);
            $detailStmt = $pdo->prepare('INSERT INTO incident_details (incident_id,detail_key,detail_value) VALUES (?,?,?)'); foreach ($details as $key => $value) $detailStmt->execute([$id,$key,is_bool($value) ? ($value ? 'true' : 'false') : (string)$value]);
            $pdo->prepare('INSERT INTO incident_updates (incident_id,status,note,is_public) VALUES (?,"received",?,1)')->execute([$id,'Report received and queued for monitoring.']); $pdo->commit(); return $reference;
        } catch (\Throwable $error) { $pdo->rollBack(); throw $error; }
    }
    public function findPublic(string $reference): ?array { $stmt=$this->database->pdo()->prepare('SELECT i.reference_code,i.status,i.specific_type,i.created_at,l.barangay_name,l.municipality_name,u.note,u.created_at AS update_at,d.team_name FROM incidents i JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) LEFT JOIN dispatch_assignments d ON d.incident_id=i.id AND d.released_at IS NULL WHERE i.reference_code=? LIMIT 1'); $stmt->execute([$reference]); $row = $stmt->fetch() ?: null; if ($row) $this->normalizeHazardLabels($row); return $row; }
    public function forUser(int $userId): array { $stmt=$this->database->pdo()->prepare('SELECT i.reference_code,i.status,i.specific_type,i.created_at,l.barangay_name,l.municipality_name,u.note FROM incidents i JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) WHERE i.local_user_id=? ORDER BY i.id DESC'); $stmt->execute([$userId]); $rows = $stmt->fetchAll(); foreach ($rows as &$row) $this->normalizeHazardLabels($row); unset($row); return $rows; }
    public function publicRecent(int $limit = 50): array {
        $limit = max(1, min(50, $limit));
        $stmt = $this->database->pdo()->prepare('SELECT i.status,i.legend,i.specific_type,i.created_at,l.barangay_name,l.municipality_name,l.province_name,l.region_name FROM incidents i JOIN incident_locations l ON l.incident_id=i.id ORDER BY i.id DESC LIMIT ' . $limit);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) $this->normalizeHazardLabels($row);
        unset($row);
        return $rows;
    }
    public function all(): array {
        $pdo = $this->database->pdo();
        $records = $pdo->query("SELECT i.*, l.barangay_name, l.municipality_name, l.province_name, l.region_name, ra.assessment_color, ra.impact_detail, ra.current_situation, ra.reporter_description, ra.evidence_note, d.team_name, u.note, (SELECT MIN(iu.created_at) FROM incident_updates iu WHERE iu.incident_id=i.id AND iu.status='verified') AS verified_at, (SELECT MIN(iu.created_at) FROM incident_updates iu WHERE iu.incident_id=i.id AND iu.status='dispatched') AS dispatched_at, (SELECT MIN(iu.created_at) FROM incident_updates iu WHERE iu.incident_id=i.id AND iu.status='resolved') AS resolved_at FROM incidents i JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN rapid_assessments ra ON ra.incident_id=i.id LEFT JOIN dispatch_assignments d ON d.incident_id=i.id AND d.released_at IS NULL LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) ORDER BY i.id DESC")->fetchAll();
        if (!$records) return [];
        $indexes = [];
        foreach ($records as $index => &$record) {
            $record['details'] = [];
            $record['needs'] = [];
            $this->normalizeHazardLabels($record);
            $indexes[(int)$record['id']] = $index;
        }
        unset($record);
        foreach ($pdo->query('SELECT incident_id,detail_key,detail_value FROM incident_details ORDER BY id')->fetchAll() as $detail) {
            $incidentId = (int)$detail['incident_id'];
            if (!isset($indexes[$incidentId])) continue;
            $key = (string)$detail['detail_key'];
            $value = (string)$detail['detail_value'];
            $records[$indexes[$incidentId]]['details'][$key] = $value;
            $records[$indexes[$incidentId]][$key] = $value;
            if ($key === 'particular_type') {
                $friendly = DisasterCatalog::friendlyEffect($value);
                $records[$indexes[$incidentId]]['details'][$key] = $friendly;
                $records[$indexes[$incidentId]][$key] = $friendly;
            }
        }
        foreach ($pdo->query('SELECT ra.incident_id,n.need_label FROM rapid_assessments ra JOIN assessment_needs n ON n.rapid_assessment_id=ra.id ORDER BY n.id')->fetchAll() as $need) {
            $incidentId = (int)$need['incident_id'];
            if (isset($indexes[$incidentId])) $records[$indexes[$incidentId]]['needs'][] = (string)$need['need_label'];
        }
        return $records;
    }
    public function exportAll(): array {
        $pdo = $this->database->pdo();
        $rows = $pdo->query('SELECT i.id,i.reference_code,i.created_at,i.updated_at,i.status,i.legend,i.general_type,i.specific_type,i.summary,i.description AS incident_description,i.reporter_name,i.contact_number,lu.display_name AS account_name,lu.email AS account_email,l.region_code,l.region_name,l.province_code,l.province_name,l.municipality_code,l.municipality_name,l.barangay_code,l.barangay_name,l.house_number,l.nearby_landmark,ra.assessment_color,ra.impact_detail,ra.current_situation,ra.reporter_description,ra.evidence_note,ra.alternate_contact,ra.email AS reporter_email,d.team_name,u.note AS latest_update,u.created_at AS latest_update_at FROM incidents i LEFT JOIN local_users lu ON lu.id=i.local_user_id JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN rapid_assessments ra ON ra.incident_id=i.id LEFT JOIN dispatch_assignments d ON d.incident_id=i.id AND d.released_at IS NULL LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) ORDER BY i.id DESC')->fetchAll();
        $indexes = [];
        foreach ($rows as $index => &$row) { $row['needs'] = []; $this->normalizeHazardLabels($row); $indexes[(int)$row['id']] = $index; }
        unset($row);
        if (!$rows) return [];
        foreach ($pdo->query('SELECT ra.incident_id,n.need_label FROM rapid_assessments ra JOIN assessment_needs n ON n.rapid_assessment_id=ra.id ORDER BY n.id')->fetchAll() as $need) {
            $incidentId = (int)$need['incident_id'];
            if (isset($indexes[$incidentId])) $rows[$indexes[$incidentId]]['needs'][] = (string)$need['need_label'];
        }
        foreach ($pdo->query('SELECT incident_id,detail_key,detail_value FROM incident_details ORDER BY id')->fetchAll() as $detail) {
            $incidentId = (int)$detail['incident_id'];
            if (isset($indexes[$incidentId])) {
                $key = (string)$detail['detail_key'];
                $value = (string)$detail['detail_value'];
                $rows[$indexes[$incidentId]][$key] = $key === 'particular_type' ? DisasterCatalog::friendlyEffect($value) : $value;
            }
        }
        return $rows;
    }
    public function analytics(): array {
        return $this->analyticsFor($this->all(), 7);
    }
    public function analyticsFor(array $records, int $trendDays = 14): array {
        $trendDays = max(7, min(90, $trendDays));
        $countBy = static function (string $key) use ($records): array {
            $buckets = [];
            foreach ($records as $record) {
                $value = trim((string)($record[$key] ?? '')) ?: 'Unspecified';
                $buckets[$value] = ($buckets[$value] ?? 0) + 1;
            }
            arsort($buckets);
            return $buckets;
        };

        $total = count($records);
        $active = 0;
        $criticalActive = 0;
        $dispatched = 0;
        $resolved = 0;
        $unassignedActive = 0;
        $awaitingVerification = 0;
        $verifiedOrBeyond = 0;
        $areas = [];
        $disasterPriority = [];
        $statusPriority = [];
        $needCounts = [];
        $ageBuckets = ['Under 1 hour' => 0, '1–4 hours' => 0, '4–24 hours' => 0, '1–3 days' => 0, 'Over 3 days' => 0];
        $oldestActiveHours = 0;
        $verificationMinutes = [];
        $dispatchMinutes = [];
        $resolutionMinutes = [];

        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();
        $dailyCounts = [];
        for ($offset = $trendDays - 1; $offset >= 0; $offset--) {
            $day = $today->modify("-{$offset} days");
            $key = $day->format('Y-m-d');
            $dailyCounts[$key] = [
                'date' => $key,
                'label' => $day->format('D'),
                'shortDate' => $day->format('M j'),
                'count' => 0,
                'Green' => 0,
                'Orange' => 0,
                'Red' => 0,
            ];
        }

        foreach ($records as $record) {
            $status = (string)($record['status'] ?? 'received');
            $legend = in_array((string)($record['legend'] ?? ''), ['Green', 'Orange', 'Red'], true) ? (string)$record['legend'] : 'Green';
            $disaster = trim((string)($record['specific_type'] ?? '')) ?: 'Unspecified';
            $isActive = $status !== 'resolved';
            if ($isActive) $active++;
            if ($status === 'dispatched') $dispatched++;
            if ($status === 'resolved') $resolved++;
            if ($status === 'received') $awaitingVerification++;
            if (in_array($status, ['verified', 'dispatched', 'resolved'], true)) $verifiedOrBeyond++;
            if ($isActive && strcasecmp((string)($record['legend'] ?? ''), 'Red') === 0) $criticalActive++;
            if ($isActive && trim((string)($record['team_name'] ?? '')) === '') $unassignedActive++;

            if (!isset($disasterPriority[$disaster])) $disasterPriority[$disaster] = ['Green' => 0, 'Orange' => 0, 'Red' => 0, 'total' => 0];
            $disasterPriority[$disaster][$legend]++;
            $disasterPriority[$disaster]['total']++;
            if (!isset($statusPriority[$status])) $statusPriority[$status] = ['Green' => 0, 'Orange' => 0, 'Red' => 0, 'total' => 0];
            $statusPriority[$status][$legend]++;
            $statusPriority[$status]['total']++;
            foreach ((array)($record['needs'] ?? []) as $need) {
                $label = trim((string)$need);
                if ($label !== '' && $label !== 'No immediate assistance required') $needCounts[$label] = ($needCounts[$label] ?? 0) + 1;
            }

            $createdAt = new \DateTimeImmutable((string)($record['created_at'] ?? 'now'));
            if ($isActive) {
                $ageHours = max(0, ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600);
                $oldestActiveHours = max($oldestActiveHours, (int)floor($ageHours));
                $bucket = $ageHours < 1 ? 'Under 1 hour' : ($ageHours < 4 ? '1–4 hours' : ($ageHours < 24 ? '4–24 hours' : ($ageHours < 72 ? '1–3 days' : 'Over 3 days')));
                $ageBuckets[$bucket]++;
            }
            foreach (['verified_at' => &$verificationMinutes, 'dispatched_at' => &$dispatchMinutes, 'resolved_at' => &$resolutionMinutes] as $field => &$durations) {
                if (!empty($record[$field])) {
                    $eventAt = new \DateTimeImmutable((string)$record[$field]);
                    $durations[] = max(0, (int)round(($eventAt->getTimestamp() - $createdAt->getTimestamp()) / 60));
                }
            }
            unset($durations);

            $barangay = trim((string)($record['barangay_name'] ?? '')) ?: 'Unspecified barangay';
            $municipality = trim((string)($record['municipality_name'] ?? '')) ?: 'Unspecified municipality';
            $province = trim((string)($record['province_name'] ?? ''));
            $areaKey = strtolower($barangay . '|' . $municipality . '|' . $province);
            if (!isset($areas[$areaKey])) {
                $areas[$areaKey] = [
                    'barangay' => $barangay,
                    'municipality' => $municipality,
                    'province' => $province,
                    'total' => 0,
                    'active' => 0,
                    'critical' => 0,
                    'attentionScore' => 0,
                ];
            }
            $areas[$areaKey]['total']++;
            if ($isActive) $areas[$areaKey]['active']++;
            if ($isActive && strcasecmp((string)($record['legend'] ?? ''), 'Red') === 0) $areas[$areaKey]['critical']++;
            $areas[$areaKey]['attentionScore'] = $areas[$areaKey]['total'] + $areas[$areaKey]['active'] + (2 * $areas[$areaKey]['critical']);

            $createdDate = substr((string)($record['created_at'] ?? ''), 0, 10);
            if (isset($dailyCounts[$createdDate])) {
                $dailyCounts[$createdDate]['count']++;
                $dailyCounts[$createdDate][$legend]++;
            }
        }

        uasort($areas, static function (array $left, array $right): int {
            return [$right['attentionScore'], $right['critical'], $right['active']] <=> [$left['attentionScore'], $left['critical'], $left['active']];
        });
        uasort($disasterPriority, static fn(array $left, array $right): int => $right['total'] <=> $left['total']);
        arsort($needCounts);
        $average = static fn(array $values): ?int => $values ? (int)round(array_sum($values) / count($values)) : null;

        return [
            'total' => $total,
            'active' => $active,
            'criticalActive' => $criticalActive,
            'dispatched' => $dispatched,
            'resolved' => $resolved,
            'unassignedActive' => $unassignedActive,
            'awaitingVerification' => $awaitingVerification,
            'assignedActive' => max(0, $active - $unassignedActive),
            'resolutionRate' => $total > 0 ? (int)round(($resolved / $total) * 100) : 0,
            'verificationRate' => $total > 0 ? (int)round(($verifiedOrBeyond / $total) * 100) : 0,
            'assignmentRate' => $active > 0 ? (int)round((max(0, $active - $unassignedActive) / $active) * 100) : 0,
            'countsByType' => $countBy('specific_type'),
            'countsByGroup' => $countBy('general_type'),
            'countsByLegend' => $countBy('legend'),
            'countsByStatus' => $countBy('status'),
            'affectedAreas' => array_values($areas),
            'dailyCounts' => array_values($dailyCounts),
            'disasterPriority' => $disasterPriority,
            'statusPriority' => $statusPriority,
            'needs' => $needCounts,
            'ageBuckets' => $ageBuckets,
            'oldestActiveHours' => $oldestActiveHours,
            'responseTimes' => [
                'verificationMinutes' => $average($verificationMinutes),
                'verificationSamples' => count($verificationMinutes),
                'dispatchMinutes' => $average($dispatchMinutes),
                'dispatchSamples' => count($dispatchMinutes),
                'resolutionMinutes' => $average($resolutionMinutes),
                'resolutionSamples' => count($resolutionMinutes),
            ],
        ];
    }
    public function updateOperations(int $id, string $status, string $team, string $note): void { $pdo=$this->database->pdo(); $pdo->beginTransaction(); try { $currentStmt=$pdo->prepare('SELECT status FROM incidents WHERE id=? FOR UPDATE'); $currentStmt->execute([$id]); $current=$currentStmt->fetchColumn(); if ($current===false) throw new \RuntimeException('The incident no longer exists.'); $rank=['received'=>0,'verified'=>1,'dispatched'=>2,'resolved'=>3]; if (($rank[$status]??-1)<($rank[$current]??0)) throw new \RuntimeException('An incident status cannot move backward.'); if ($status==='dispatched' && $team==='') throw new \RuntimeException('Choose a dispatch team before marking the incident dispatched.'); $pdo->prepare('UPDATE incidents SET status=? WHERE id=?')->execute([$status,$id]); $active=$pdo->prepare('SELECT team_name FROM dispatch_assignments WHERE incident_id=? AND released_at IS NULL ORDER BY id DESC LIMIT 1'); $active->execute([$id]); $activeTeam=(string)($active->fetchColumn()?:''); if ($status!=='dispatched') { if ($activeTeam!=='') $pdo->prepare('UPDATE dispatch_assignments SET released_at=NOW() WHERE incident_id=? AND released_at IS NULL')->execute([$id]); } elseif ($team!==$activeTeam) { if ($activeTeam!=='') $pdo->prepare('UPDATE dispatch_assignments SET released_at=NOW() WHERE incident_id=? AND released_at IS NULL')->execute([$id]); $pdo->prepare('INSERT INTO dispatch_assignments (incident_id,team_name) VALUES (?,?)')->execute([$id,$team]); } $publicNote=$note!==''?$note:($status!==$current?'Status updated to '.ucfirst($status).'.':''); if ($publicNote!=='') $pdo->prepare('INSERT INTO incident_updates (incident_id,status,note,is_public) VALUES (?,?,?,1)')->execute([$id,$status,$publicNote]); $pdo->commit(); } catch (\Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; } }

    private function normalizeHazardLabels(array &$row): void {
        if (isset($row['general_type'])) $row['general_type'] = DisasterCatalog::friendlyGroup((string)$row['general_type']);
        if (isset($row['specific_type'])) $row['specific_type'] = DisasterCatalog::friendlyDisaster((string)$row['specific_type']);
    }
}
