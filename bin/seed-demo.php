<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
if ($app->config['mode'] !== 'demo') throw new RuntimeException('Los datos ficticios solo se cargan en modo demo.');
$db = $app->db;
if ($db->one("SELECT id FROM patients WHERE document_type='CC' AND document_number='1000000001'")) {
    echo "El paciente ficticio ya está registrado.\n"; exit;
}
// Minimal, valid PDF fixture: no real patient data, signatures or clinical advice.
function demoPdf(string $title): string {
    $stream = "BT /F1 20 Tf 50 770 Td (DOCUMENTO FICTICIO - PRUEBAS) Tj 0 -45 Td /F1 13 Tf ($title) Tj 0 -30 Td (Paciente Demo - CC 1000000001) Tj 0 -30 Td (Sin informacion clinica real. No tiene validez medica.) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length ' . strlen($stream) . ">>\nstream\n$stream\nendstream",
    ];
    $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objects as $i => $object) { $offsets[] = strlen($pdf); $pdf .= ($i+1) . " 0 obj\n$object\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset);
    return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
}
$db->transaction(function () use ($db, $app) {
    $patientId = $db->insert('patients', ['document_type' => 'CC', 'document_number' => '1000000001', 'full_name' => 'Paciente Demo', 'email' => $app->config['test_recipient'], 'email_verified_at' => $app->now()]);
    $specialty = $db->one("SELECT id FROM specialties WHERE name='Ortopedia y traumatología'");
    foreach ([7, 18] as $offset) {
        $encounterId = $db->insert('encounters', ['patient_id' => $patientId, 'specialty_id' => $specialty['id'], 'attended_at' => date('Y-m-d 08:00:00', strtotime("-$offset days")), 'admission' => 'DEMO-' . $offset, 'folio' => '1', 'location' => 'Sede de demostración']);
        $packageId = $db->insert('packages', ['encounter_id' => $encounterId, 'version' => 1]);
        foreach (['Nota de consulta', 'Ordenes y formula - Anexo de prueba'] as $index => $title) {
            $storageName = bin2hex(random_bytes(16)) . '.pdf';
            $path = $app->config['storage_path'] . '/histories/' . $storageName;
            file_put_contents($path, demoPdf($title));
            $db->insert('clinical_files', ['package_id' => $packageId, 'storage_name' => $storageName, 'original_name' => 'Demo_' . ($index+1) . '.pdf', 'sort_order' => $index+1, 'size_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path)]);
        }
        $db->run("UPDATE packages SET status='available',published_at=? WHERE id=?", [$app->now(), $packageId]);
    }
});
echo "Paciente ficticio CC 1000000001 cargado con dos atenciones y dos PDF por paquete.\n";
