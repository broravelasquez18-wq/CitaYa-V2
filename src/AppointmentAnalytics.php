<?php
declare(strict_types=1);
namespace App;

final class AppointmentAnalytics
{
    private const HAS_PDF = 'EXISTS (SELECT 1 FROM packages p JOIN clinical_files f ON f.package_id=p.id WHERE p.encounter_id=e.id)';

    public function __construct(private readonly Database $db) {}

    public function monthly(string $month = ''): array
    {
        if ($month === '') {
            $latest = $this->db->one('SELECT MAX(e.attended_at) latest FROM encounters e WHERE ' . self::HAS_PDF);
            $month = $latest['latest'] ? substr($latest['latest'], 0, 7) : date('Y-m');
        }
        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/D', $month, $parts) || (int)$parts[1] < 1000 || (int)$parts[1] > 9998) {
            throw new \DomainException('Selecciona un mes y año válidos.');
        }
        $start = new \DateTimeImmutable($month . '-01');
        $rows = $this->db->all('SELECT s.id, s.name, COUNT(*) total FROM encounters e JOIN specialties s ON s.id=e.specialty_id
            WHERE e.attended_at>=? AND e.attended_at<? AND ' . self::HAS_PDF . '
            GROUP BY s.id,s.name ORDER BY total DESC,s.name ASC,s.id ASC', [$start->format('Y-m-d'), $start->modify('+1 month')->format('Y-m-d')]);
        $total = array_sum(array_column($rows, 'total'));
        foreach ($rows as &$row) {
            $row['total'] = (int)$row['total'];
            $row['percentage'] = $row['total'] / $total * 100;
        }
        unset($row);
        return ['month'=>$month, 'total'=>$total, 'rows'=>$rows];
    }
}
