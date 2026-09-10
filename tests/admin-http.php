<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$app=require dirname(__DIR__).'/src/bootstrap.php';
$cookie=dirname(__DIR__).'/.runtime/admin-view-'.bin2hex(random_bytes(8)).'.cookie';
$checks=0;
$syntheticCase=null;
function adminCheck(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException('FAIL: '.$label); $checks++; echo "OK $label\n"; }
function adminWeb(array $query,?array $post=null): array {
    global $cookie;
    $h=curl_init('http://localhost/CitaYaV2/public/?'.http_build_query($query));
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>10]);
    if($post!==null){curl_setopt($h,CURLOPT_POST,true);curl_setopt($h,CURLOPT_POSTFIELDS,http_build_query($post));}
    $raw=curl_exec($h); if($raw===false)throw new RuntimeException('HTTP unavailable');
    $size=curl_getinfo($h,CURLINFO_HEADER_SIZE);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
    return [$status,substr($raw,0,$size),substr($raw,$size)];
}
try {
    foreach(['deliveries','delivery','history'] as $page){
        [$status,$headers]=adminWeb(['page'=>$page,'id'=>1]);
        adminCheck($status===302 && str_contains($headers,'admin-login'),'Acceso protegido: '.$page);
    }
    [$status,,$body]=adminWeb(['page'=>'delivery','id'=>1,'fragment'=>'1']);
    adminCheck($status===401 && !str_contains($body,'Destinatario'),'Modal exige sesión y no devuelve datos sin acceso');
    [,,$login]=adminWeb(['page'=>'admin-login']);
    preg_match('/name="csrf" value="([a-f0-9]+)"/',$login,$token);
    $credentials=file_get_contents(dirname(__DIR__).'/.runtime/first-login.txt');
    preg_match('/Contraseña: ([a-f0-9]+)/u',$credentials,$password);
    if(!$token || !$password) throw new RuntimeException('Credenciales locales de prueba no disponibles');
    [$protectedStatus,$protectedHeaders]=adminWeb(['page'=>'history'],['csrf'=>$token[1],'action'=>'reopen-case','request_id'=>999999999,'expected_job_id'=>1]);
    adminCheck($protectedStatus===302 && str_contains($protectedHeaders,'admin-login'),'Reapertura POST exige sesión administrativa');
    [$status,$headers]=adminWeb(['page'=>'admin-login'],['csrf'=>$token[1],'action'=>'login','email'=>'admin@citaya.local','password'=>$password[1]]);
    unset($credentials,$password);
    adminCheck($status===302 && str_contains($headers,'?page=admin'),'Login administrativo');
    [$status,,$body]=adminWeb(['page'=>'deliveries']);
    adminCheck($status===200 && str_contains($body,'Nombre, radicado o número de documento'),'Buscador visible en panel');
    adminCheck(str_contains($body,'<dialog id="delivery-modal"') && str_contains($body,'aria-labelledby="delivery-modal-title"') && str_contains($body,'data-close-delivery'),'Modal con título accesible y botón de cierre');
    [$historyStatus,,$historyBody]=adminWeb(['page'=>'history']);
    adminCheck($historyStatus===200 && str_contains($historyBody,'Historial de historias enviadas') && str_contains($historyBody,'name="page" value="history"'),'Pestaña historial y buscador mantienen su filtro');
    adminCheck(str_contains($historyBody,'id="reopen-modal"') && str_contains($historyBody,'data-cancel-reopen') && str_contains($historyBody,'name="expected_job_id"') && str_contains($historyBody,'Confirmar'),'Reapertura presenta modal de confirmación con Cancelar');
    [$csrfStatus,,$csrfBody]=adminWeb(['page'=>'history'],['csrf'=>'invalid','action'=>'reopen-case','request_id'=>999999999,'expected_job_id'=>1]);
    adminCheck($csrfStatus===200 && str_contains($csrfBody,'La sesión del formulario venció'),'Reapertura rechaza token CSRF inválido');
    $syntheticReference='TEST-REOPEN-'.bin2hex(random_bytes(8));
    $syntheticCase=$app->db->insert('requests',['reference'=>$syntheticReference,'claimed_name'=>'Caso ficticio de reapertura','status'=>'sent','matched_at'=>$app->now(),'created_at'=>$app->now()]);
    $syntheticJob=$app->db->insert('jobs',['request_id'=>$syntheticCase,'kind'=>'history','recipient'=>'synthetic@example.test','status'=>'sent','sent_at'=>$app->now(),'created_at'=>$app->now(),'available_at'=>$app->now(),'message_id'=>'<'.$syntheticReference.'@example.test>','dedupe_key'=>$syntheticReference]);
    preg_match('/name="csrf" value="([a-f0-9]+)"/',$historyBody,$reopenToken);
    [$reopenStatus,$reopenHeaders]=adminWeb(['page'=>'history'],['csrf'=>$reopenToken[1],'action'=>'reopen-case','request_id'=>$syntheticCase,'expected_job_id'=>$syntheticJob]);
    adminCheck($reopenStatus===302 && str_contains($reopenHeaders,'?page=deliveries'),'Confirmar reapertura redirige a Solicitudes y envíos');
    [$pendingStatus,,$pendingBody]=adminWeb(['page'=>'deliveries','q'=>$syntheticReference]);
    adminCheck($pendingStatus===200 && str_contains($pendingBody,'Pendiente de gestión') && str_contains($pendingBody,$syntheticReference),'Caso ficticio confirmado aparece como pendiente');
    adminCheck((new App\AdminDeliveries($app->db))->search($syntheticReference,1,true)['total']===0 && (int)$app->db->one('SELECT COUNT(*) n FROM jobs WHERE request_id=?',[$syntheticCase])['n']===1,'Reapertura HTTP retira el caso de cerrados sin generar correos');
    [$pendingModalStatus,,$pendingModal]=adminWeb(['page'=>'delivery','id'=>$syntheticCase,'fragment'=>'1']);
    adminCheck($pendingModalStatus===200 && str_contains($pendingModal,'Pendiente de gestión'),'Ojo muestra la reapertura en el detalle');
    $historyRows=(new App\AdminDeliveries($app->db))->search('',1,true);
    if($historyRows['total']) adminCheck(str_contains($historyBody,'APROBADO') && str_contains($historyBody,'CERRADO') && str_contains($historyBody,'data-delivery-modal'),'Historial muestra aprobado, cerrado y botón de ojo');
    $record=$app->db->one('SELECT r.id,r.reference,r.claimed_name,p.document_number FROM requests r JOIN patients p ON p.id=r.patient_id ORDER BY r.id DESC LIMIT 1');
    if($record){
        foreach(['reference','claimed_name','document_number'] as $field){
            [$status,,$body]=adminWeb(['page'=>'deliveries','q'=>$record[$field]]);
            adminCheck($status===200 && str_contains($body,htmlspecialchars($record['reference'],ENT_QUOTES,'UTF-8')),'Búsqueda HTTP por '.$field);
        }
        adminCheck(str_contains($body,'Ver detalle del envío') && str_contains($body,'<svg'),'Botón de ojo con etiqueta accesible');
        adminCheck(str_contains($body,'data-delivery-modal aria-haspopup="dialog"'),'Botón de ojo abre ventana de detalle');
        [$status,$headers,$body]=adminWeb(['page'=>'delivery','id'=>$record['id']]);
        adminCheck($status===200 && str_contains($body,'Historial de envíos y reenvíos') && str_contains($headers,'no-store'),'Detalle y encabezado sin caché');
        [$status,$headers,$body]=adminWeb(['page'=>'delivery','id'=>$record['id'],'fragment'=>'1']);
        adminCheck($status===200 && str_contains($body,'DETALLE DE LA SOLICITUD') && !str_contains($body,'Historial de envíos y reenvíos') && !str_contains($body,'<!DOCTYPE') && !str_contains($body,'Volver a los resultados') && str_contains($headers,'no-store'),'Modal recibe solo detalle protegido sin página duplicada');
    }
    [$status,,$body]=adminWeb(['page'=>'deliveries','q'=>'<script>alert(1)</script>']);
    adminCheck($status===200 && !str_contains($body,'<script>alert(1)</script>') && str_contains($body,'&lt;script&gt;'),'Escapa consultas reflejadas');
    [$status,,$body]=adminWeb(['page'=>'delivery','id'=>999999999]);
    adminCheck($status===404 && str_contains($body,'Solicitud no encontrada'),'Detalle inexistente devuelve 404');
    [$status,,$body]=adminWeb(['page'=>'delivery','id'=>999999999,'fragment'=>'1']);
    adminCheck($status===404 && str_contains($body,'Solicitud no encontrada'),'Modal informa solicitud inexistente');
    echo "\nPASS: $checks comprobaciones administrativas HTTP. Sin envíos.\n";
} finally {
    if ($syntheticCase!==null) {
        $app->db->run('DELETE FROM events WHERE request_id=?',[$syntheticCase]);
        $app->db->run('DELETE FROM jobs WHERE request_id=?',[$syntheticCase]);
        $app->db->run('DELETE FROM requests WHERE id=? AND reference=?',[$syntheticCase,$syntheticReference]);
    }
    if(is_file($cookie)) unlink($cookie);
}
