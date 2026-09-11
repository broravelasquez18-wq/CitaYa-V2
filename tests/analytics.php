<?php
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/vendor/autoload.php';
$config=array_replace(require dirname(__DIR__).'/config/default.php',require dirname(__DIR__).'/config/local.php');
$db=new App\Database($config);
// Temporary tables shadow the real tables only on this test connection.
$db->pdo->exec('CREATE TEMPORARY TABLE specialties (id INT, name VARCHAR(120))');
$db->pdo->exec('CREATE TEMPORARY TABLE encounters (id INT, specialty_id INT, attended_at DATETIME)');
$db->pdo->exec('CREATE TEMPORARY TABLE packages (id INT, encounter_id INT)');
$db->pdo->exec('CREATE TEMPORARY TABLE clinical_files (id INT, package_id INT)');
$db->pdo->exec("INSERT INTO specialties VALUES (1,'Medicina general'),(2,'Ortopedia')");
$db->pdo->exec("INSERT INTO encounters VALUES (1,1,'2026-06-01 00:00:00'),(2,2,'2026-06-30 23:59:59'),(3,2,'2026-06-15 12:00:00'),(4,1,'2026-07-01 00:00:00'),(5,1,'2026-06-10 00:00:00'),(6,1,'2025-06-01 00:00:00')");
$db->pdo->exec('INSERT INTO packages VALUES (1,1),(2,1),(3,2),(4,3),(5,4),(6,6)');
$db->pdo->exec('INSERT INTO clinical_files VALUES (1,1),(2,1),(3,2),(4,3),(5,4),(6,5),(7,6)');
$service=new App\AppointmentAnalytics($db);
function analyticsCheck($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
$r=$service->monthly('2026-06');
analyticsCheck($r['total']===3,'Cuenta atenciones sin duplicar anexos ni versiones; excluye registros sin PDF');
analyticsCheck($r['rows'][0]['name']==='Ortopedia' && $r['rows'][0]['total']===2,'Ordena especialidades por demanda registrada');
analyticsCheck(abs($r['rows'][0]['percentage']-200/3)<0.001,'Porcentaje por total del mes');
analyticsCheck(abs(array_sum(array_column($r['rows'],'percentage'))-100)<0.001,'Porcentajes suman 100 antes de redondear');
analyticsCheck($service->monthly('2026-07')['total']===1,'Límites de mes sin incluir el mes anterior');
analyticsCheck($service->monthly('2025-06')['total']===1,'Separa años');
analyticsCheck($service->monthly('2026-08')['total']===0,'Mes vacío sin división por cero');
analyticsCheck($service->monthly()['month']==='2026-07','Selecciona el mes más reciente con PDF');
foreach(['2026-13','2026-00','foo','2026-6','9999-01'] as $invalid){try{$service->monthly($invalid);throw new RuntimeException('Aceptó mes inválido');}catch(DomainException){}}
echo "OK rechaza meses inválidos\n";
function h($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
foreach(['2026-06','2026-07','2026-08'] as $month){$analytics=$service->monthly($month);ob_start();require dirname(__DIR__).'/templates/analytics.php';$html=ob_get_clean();analyticsCheck(!str_contains($html,'NAN') && !str_contains($html,'INF'),'Render válido '.$month);}
$ch=curl_init('http://localhost/CitaYaV2/public/?page=analytics');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>10]);$html=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
analyticsCheck($code===302 && str_contains($html,'admin-login'),'Análisis protegido por sesión administrativa');
