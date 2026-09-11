<?php
declare(strict_types=1);
namespace App;

final class AppointmentAnalytics
{
    private const HAS_PDF = 'EXISTS (SELECT 1 FROM packages p JOIN clinical_files f ON f.package_id=p.id WHERE p.encounter_id=e.id)';

    public function __construct(private readonly Database $db) {}

    public function activity(string $date = ''): array
    {
        $date = $date === '' ? date('Y-m-d') : $date;
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$day || $day->format('Y-m-d') !== $date || (int)$day->format('Y') < 1000 || (int)$day->format('Y') > 9998) {
            throw new \DomainException('Selecciona una fecha válida para los totales.');
        }
        $month = $day->modify('first day of this month');
        $year = $day->setDate((int)$day->format('Y'), 1, 1);
        $periods = [
            'day' => [$day, $day->modify('+1 day')],
            'month' => [$month, $month->modify('+1 month')],
            'year' => [$year, $year->modify('+1 year')],
        ];
        $totals = [];
        foreach ($periods as $key => [$start, $end]) {
            $range = [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
            $requests = $this->db->one('SELECT COUNT(*) total FROM requests WHERE created_at>=? AND created_at<?', $range);
            $jobs = $this->db->one("SELECT COALESCE(SUM(status='sent'),0) sent, COALESCE(SUM(status='simulated'),0) simulated
                FROM jobs WHERE kind='history' AND status IN ('sent','simulated') AND sent_at>=? AND sent_at<?", $range);
            $totals[$key] = ['requests'=>(int)$requests['total'], 'sent'=>(int)$jobs['sent'], 'simulated'=>(int)$jobs['simulated']];
        }
        return ['date'=>$date, 'totals'=>$totals];
    }

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
