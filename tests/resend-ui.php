<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;

$checks = 0;
function expectResend(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo "OK $label\n";
}
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return '<input type="hidden" name="csrf" value="test-token">'; }
function renderStatus(string $status, int $wait = 0, int $remaining = 3, ?array $recentSuggestion = null, ?string $date = '2026-01-01'): string {
    $app = (object)['config' => ['mode'=>'production','mail_transport'=>'smtp']];
    $page = 'status'; $error = $flash = null; $steps = ['status'=>3]; $labels = [$status=>$status];
    $request = ['status'=>$status,'reference'=>'TEST','contact_email'=>'paciente@example.test','approximate_date'=>$date];
    $historyResend = ['eligible'=>!in_array($status,['queued','sending','retry','no_matches'],true),'remaining'=>$remaining,'wait'=>$wait,'job'=>['id'=>123]];
    ob_start();
    require dirname(__DIR__) . '/templates/layout.php';
    return ob_get_clean();
}
foreach (['sent','simulated','failed','uncertain'] as $state) {
    $body=renderStatus($state);
    expectResend(str_contains($body,'value="resend-history"') && str_contains($body,'value="123"') && str_contains($body,'paciente@example.test'),'Botón con entrega y destino en estado ' . $state);
}
expectResend(str_contains(renderStatus('sent',60),'disabled data-resend-wait="60"'),'Espera visible y botón desactivado');
expectResend(!str_contains(renderStatus('sent',0,0),'value="resend-history"'),'Oculta botón cuando se agotan reenvíos');
expectResend(str_contains(renderStatus('uncertain'),'puedes recibir una copia adicional'),'Explica posible copia adicional en resultado incierto');
foreach (['queued','sending','retry','no_matches'] as $state) expectResend(!str_contains(renderStatus($state),'value="resend-history"'),'No ofrece reenvío en estado ' . $state);
$suggestion=['id'=>42,'attended_at'=>'2026-02-10 08:00:00','specialty'=>'Consulta <prueba>','location'=>'Sede principal'];
$suggestionBody=renderStatus('no_matches',0,3,$suggestion);
expectResend(str_contains($suggestionBody,'01/01/2026') && str_contains($suggestionBody,'10/02/2026'),'Distingue fecha solicitada y fecha recomendada');
expectResend(str_contains($suggestionBody,'value="select-suggestion"') && str_contains($suggestionBody,'value="42"') && str_contains($suggestionBody,'Solicitar historia del 10/02/2026'),'La recomendación tiene botón para solicitar la consulta concreta');
expectResend(str_contains($suggestionBody,'Consulta &lt;prueba&gt;'),'Escapa especialidad extraída del documento');
expectResend(!str_contains(renderStatus('no_matches'),'value="select-suggestion"'),'Oculta recomendaciones cuando no hay consultas disponibles');
expectResend(str_contains(renderStatus('no_matches',0,3,$suggestion,null),'Como no recuerdas la fecha'),'Explica búsqueda reciente cuando el paciente no indicó fecha');

// Read-only HTTP checks: no owning request session and no external mail.
$base='http://localhost/CitaYaV2/public/?page=status';
$handle=curl_init($base);
curl_setopt_array($handle,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>10]);
$response=curl_exec($handle);
if ($response===false) throw new RuntimeException('HTTP unavailable');
preg_match('/Set-Cookie: ([^;]+)/i',$response,$cookie);
curl_setopt($handle,CURLOPT_COOKIE,$cookie[1] ?? '');
curl_setopt($handle,CURLOPT_URL,'http://localhost/CitaYaV2/public/');
$response=curl_exec($handle);
preg_match('/name="csrf" value="([^"]+)"/',(string)$response,$token);
if (!$token) throw new RuntimeException('CSRF form unavailable');
curl_setopt($handle,CURLOPT_URL,$base);
curl_setopt($handle,CURLOPT_POST,true);
curl_setopt($handle,CURLOPT_POSTFIELDS,http_build_query(['action'=>'resend-history','delivery_id'=>123,'csrf'=>$token[1]]));
$response=curl_exec($handle);
expectResend(curl_getinfo($handle,CURLINFO_RESPONSE_CODE)===302 && str_contains((string)$response,'Location: ?page=home'),'Exige sesión propietaria para reenviar aunque se indique un ID');
curl_setopt($handle,CURLOPT_POSTFIELDS,http_build_query(['action'=>'select-suggestion','encounter_id'=>42,'csrf'=>$token[1]]));
$response=curl_exec($handle);
expectResend(curl_getinfo($handle,CURLINFO_RESPONSE_CODE)===302 && str_contains((string)$response,'Location: ?page=home'),'Elegir fecha sugerida exige la sesión de la solicitud');
curl_setopt($handle,CURLOPT_URL,'http://localhost/CitaYaV2/public/');
curl_setopt($handle,CURLOPT_POSTFIELDS,http_build_query(['action'=>'resend-history','delivery_id'=>123,'csrf'=>'invalid']));
$response=curl_exec($handle);
expectResend(str_contains((string)$response,'sesión del formulario venció'),'Rechaza reenvío sin CSRF válido');
curl_close($handle);
echo "\nPASS: $checks comprobaciones de interfaz y HTTP. Sin envíos.\n";
