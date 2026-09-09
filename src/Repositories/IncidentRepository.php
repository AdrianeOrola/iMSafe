<?php
declare(strict_types=1);
namespace ImSafe\Repositories;
use ImSafe\Support\Database;
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
    public function findPublic(string $reference): ?array { $stmt=$this->database->pdo()->prepare('SELECT i.reference_code,i.status,i.specific_type,i.created_at,l.barangay_name,l.municipality_name,u.note,u.created_at AS update_at,d.team_name FROM incidents i JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) LEFT JOIN dispatch_assignments d ON d.incident_id=i.id AND d.released_at IS NULL WHERE i.reference_code=? LIMIT 1'); $stmt->execute([$reference]); return $stmt->fetch() ?: null; }
    public function forUser(int $userId): array { $stmt=$this->database->pdo()->prepare('SELECT i.reference_code,i.status,i.specific_type,i.created_at,l.barangay_name,l.municipality_name,u.note FROM incidents i JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) WHERE i.local_user_id=? ORDER BY i.id DESC'); $stmt->execute([$userId]); return $stmt->fetchAll(); }
    public function publicRecent(int $limit = 50): array {
        $limit = max(1, min(50, $limit));
        $stmt = $this->database->pdo()->prepare('SELECT i.status,i.legend,i.specific_type,i.created_at,l.barangay_name,l.municipality_name,l.province_name,l.region_name FROM incidents i JOIN incident_locations l ON l.incident_id=i.id ORDER BY i.id DESC LIMIT ' . $limit);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function all(): array { return $this->database->pdo()->query("SELECT i.*, l.barangay_name, l.municipality_name, l.province_name, l.region_name, ra.assessment_color, ra.impact_detail, ra.current_situation, ra.reporter_description, ra.evidence_note, d.team_name, u.note, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='particular_type' LIMIT 1) AS particular_type, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='latitude' LIMIT 1) AS latitude, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='longitude' LIMIT 1) AS longitude, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='coordinate_source' LIMIT 1) AS coordinate_source, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='water_level' LIMIT 1) AS water_level, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='water_trend' LIMIT 1) AS water_trend, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='road_passability' LIMIT 1) AS road_passability, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='people_stranded' LIMIT 1) AS people_stranded, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='houses_affected' LIMIT 1) AS houses_affected, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='households_affected' LIMIT 1) AS households_affected, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='evacuation_needed' LIMIT 1) AS evacuation_needed, (SELECT detail_value FROM incident_details WHERE incident_id=i.id AND detail_key='rescue_needed' LIMIT 1) AS rescue_needed FROM incidents i JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN rapid_assessments ra ON ra.incident_id=i.id LEFT JOIN dispatch_assignments d ON d.incident_id=i.id AND d.released_at IS NULL LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) ORDER BY i.id DESC")->fetchAll(); }
    public function exportAll(): array {
        $pdo = $this->database->pdo();
        $rows = $pdo->query('SELECT i.id,i.reference_code,i.created_at,i.updated_at,i.status,i.legend,i.general_type,i.specific_type,i.summary,i.description AS incident_description,i.reporter_name,i.contact_number,lu.display_name AS account_name,lu.email AS account_email,l.region_code,l.region_name,l.province_code,l.province_name,l.municipality_code,l.municipality_name,l.barangay_code,l.barangay_name,l.house_number,l.nearby_landmark,ra.assessment_color,ra.impact_detail,ra.current_situation,ra.reporter_description,ra.evidence_note,ra.alternate_contact,ra.email AS reporter_email,d.team_name,u.note AS latest_update,u.created_at AS latest_update_at FROM incidents i LEFT JOIN local_users lu ON lu.id=i.local_user_id JOIN incident_locations l ON l.incident_id=i.id LEFT JOIN rapid_assessments ra ON ra.incident_id=i.id LEFT JOIN dispatch_assignments d ON d.incident_id=i.id AND d.released_at IS NULL LEFT JOIN incident_updates u ON u.id=(SELECT id FROM incident_updates WHERE incident_id=i.id AND is_public=1 ORDER BY id DESC LIMIT 1) ORDER BY i.id DESC')->fetchAll();
        $indexes = [];
        foreach ($rows as $index => &$row) { $row['needs'] = []; $indexes[(int)$row['id']] = $index; }
        unset($row);
        if (!$rows) return [];
        foreach ($pdo->query('SELECT ra.incident_id,n.need_label FROM rapid_assessments ra JOIN assessment_needs n ON n.rapid_assessment_id=ra.id ORDER BY n.id')->fetchAll() as $need) {
            $incidentId = (int)$need['incident_id'];
            if (isset($indexes[$incidentId])) $rows[$indexes[$incidentId]]['needs'][] = (string)$need['need_label'];
        }
        foreach ($pdo->query('SELECT incident_id,detail_key,detail_value FROM incident_details ORDER BY id')->fetchAll() as $detail) {
            $incidentId = (int)$detail['incident_id'];
            if (isset($indexes[$incidentId])) $rows[$indexes[$incidentId]][(string)$detail['detail_key']] = (string)$detail['detail_value'];
        }
        return $rows;
    }
    public function analytics(): array {
        $records = $this->all();
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
        $areas = [];

        $today = new \DateTimeImmutable('today');
        $dailyCounts = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $today->modify("-{$offset} days");
            $key = $day->format('Y-m-d');
            $dailyCounts[$key] = [
                'date' => $key,
                'label' => $day->format('D'),
                'shortDate' => $day->format('M j'),
                'count' => 0,
            ];
        }

        foreach ($records as $record) {
            $status = (string)($record['status'] ?? 'received');
            $isActive = $status !== 'resolved';
            if ($isActive) $active++;
            if ($status === 'dispatched') $dispatched++;
            if ($status === 'resolved') $resolved++;
            if ($isActive && strcasecmp((string)($record['legend'] ?? ''), 'Red') === 0) $criticalActive++;
            if ($isActive && trim((string)($record['team_name'] ?? '')) === '') $unassignedActive++;

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
                ];
            }
            $areas[$areaKey]['total']++;
            if ($isActive) $areas[$areaKey]['active']++;
            if ($isActive && strcasecmp((string)($record['legend'] ?? ''), 'Red') === 0) $areas[$areaKey]['critical']++;

            $createdDate = substr((string)($record['created_at'] ?? ''), 0, 10);
            if (isset($dailyCounts[$createdDate])) $dailyCounts[$createdDate]['count']++;
        }

        uasort($areas, static function (array $left, array $right): int {
            return [$right['total'], $right['active'], $right['critical']] <=> [$left['total'], $left['active'], $left['critical']];
        });

        return [
            'total' => $total,
            'active' => $active,
            'criticalActive' => $criticalActive,
            'dispatched' => $dispatched,
            'resolved' => $resolved,
            'unassignedActive' => $unassignedActive,
            'resolutionRate' => $total > 0 ? (int)round(($resolved / $total) * 100) : 0,
            'countsByType' => $countBy('specific_type'),
            'countsByLegend' => $countBy('legend'),
            'countsByStatus' => $countBy('status'),
            'affectedAreas' => array_values($areas),
            'dailyCounts' => array_values($dailyCounts),
        ];
    }
    public function updateOperations(int $id, string $status, string $team, string $note): void { $pdo=$this->database->pdo(); $pdo->beginTransaction(); try { $currentStmt=$pdo->prepare('SELECT status FROM incidents WHERE id=? FOR UPDATE'); $currentStmt->execute([$id]); $current=$currentStmt->fetchColumn(); if ($current===false) throw new \RuntimeException('The incident no longer exists.'); $rank=['received'=>0,'verified'=>1,'dispatched'=>2,'resolved'=>3]; if (($rank[$status]??-1)<($rank[$current]??0)) throw new \RuntimeException('An incident status cannot move backward.'); if ($status==='dispatched' && $team==='') throw new \RuntimeException('Choose a dispatch team before marking the incident dispatched.'); $pdo->prepare('UPDATE incidents SET status=? WHERE id=?')->execute([$status,$id]); $active=$pdo->prepare('SELECT team_name FROM dispatch_assignments WHERE incident_id=? AND released_at IS NULL ORDER BY id DESC LIMIT 1'); $active->execute([$id]); $activeTeam=(string)($active->fetchColumn()?:''); if ($status!=='dispatched') { if ($activeTeam!=='') $pdo->prepare('UPDATE dispatch_assignments SET released_at=NOW() WHERE incident_id=? AND released_at IS NULL')->execute([$id]); } elseif ($team!==$activeTeam) { if ($activeTeam!=='') $pdo->prepare('UPDATE dispatch_assignments SET released_at=NOW() WHERE incident_id=? AND released_at IS NULL')->execute([$id]); $pdo->prepare('INSERT INTO dispatch_assignments (incident_id,team_name) VALUES (?,?)')->execute([$id,$team]); } $publicNote=$note!==''?$note:($status!==$current?'Status updated to '.ucfirst($status).'.':''); if ($publicNote!=='') $pdo->prepare('INSERT INTO incident_updates (incident_id,status,note,is_public) VALUES (?,?,?,1)')->execute([$id,$status,$publicNote]); $pdo->commit(); } catch (\Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; } }
}
