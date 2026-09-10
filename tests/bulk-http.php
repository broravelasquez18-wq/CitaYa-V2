<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$app=require dirname(__DIR__).'/src/bootstrap.php';
require __DIR__.'/TestPdf.php';
$tag='BULKTEST'.strtoupper(bin2hex(random_bytes(6)));
$cookie=dirname(__DIR__).'/.runtime/'.$tag.'.cookie';
$sources=[];$numbers=[];$checks=0;
function bulkCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo "OK $label\n";}
function bulkWeb(?array $post=null):array{
    global $cookie;
    $h=curl_init('http://localhost/CitaYaV2/public/?page=admin');
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>90]);
    if($post!==null){curl_setopt($h,CURLOPT_POST,true);curl_setopt($h,CURLOPT_POSTFIELDS,$post);}
    $raw=curl_exec($h);if($raw===false)throw new RuntimeException('HTTP unavailable');
    $size=curl_getinfo($h,CURLINFO_HEADER_SIZE);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
    return [$status,substr($raw,0,$size),substr($raw,$size)];
}
function bulkToken(string $html):string{preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m);return $m[1]??throw new RuntimeException('CSRF missing');}
try{
    // Obtain a fresh login form using the same private cookie jar.
    $h=curl_init('http://localhost/CitaYaV2/public/?page=admin-login');
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>10]);
    $login=curl_exec($h);curl_setopt($h,CURLOPT_COOKIELIST,'FLUSH');curl_close($h);unset($h);
    $token=bulkToken($login);
    [$status,$headers]=bulkWeb(['csrf'=>$token,'action'=>'bulk-upload','complete'=>'1','expected_files'=>0]);
    bulkCheck($status===302 && str_contains($headers,'admin-login'),'Carga masiva exige administrador');
    preg_match('/Contraseña: ([a-f0-9]+)/u',file_get_contents(dirname(__DIR__).'/.runtime/first-login.txt'),$credential);
    [$status,$headers]=bulkWeb(['csrf'=>$token,'action'=>'login','email'=>'admin@citaya.local','password'=>$credential[1]]);unset($credential);
    bulkCheck($status===302 && str_contains($headers,'Location: ?page=admin') && !str_contains($headers,'admin-login'),'Acceso administrativo para carga masiva');
    [,,$admin]=bulkWeb();$token=bulkToken($admin);
    bulkCheck(str_contains($admin,'Carga masiva de PDF') && str_contains($admin,'data-bulk-upload'),'Formulario masivo disponible');
    for($i=0;$i<2;$i++){
        do{$number=(string)random_int(8500000000,8999999999);}while(in_array($number,$numbers,true)||$app->db->one('SELECT id FROM patients WHERE document_number=?',[$number]));
        $numbers[]=$number;
    }
    foreach([[$numbers[0],$tag.'A','NOTA'],[$numbers[0],$tag.'A','ANEXO'],[$numbers[1],$tag.'B','NOTA']] as $i=>$data){
        $path=dirname(__DIR__).'/.runtime/'.$tag.'-'.$i.'.pdf';file_put_contents($path,testPdf(...$data));$sources[]=$path;
    }
    $post=['csrf'=>$token,'action'=>'bulk-upload','complete'=>'1','expected_files'=>'3'];
    foreach($sources as $i=>$path)$post['bulk_pdfs['.$i.']']=new CURLFile($path,'application/pdf','Ficticio-'.$i.'.pdf');
    [$status]=bulkWeb(array_replace($post,['expected_files'=>'4']));
    bulkCheck($status===200 && !$app->db->one('SELECT id FROM patients WHERE document_number=?',[$numbers[0]]),'Recepción incompleta no importa archivos');
    [$status]=bulkWeb($post);
    bulkCheck($status===302,'Recibe multipart masivo y redirige al resultado');
    [,,$result]=bulkWeb();
    bulkCheck(str_contains($result,'2 historias guardadas') && str_contains($result,'3 PDF recibidos'),'Informe muestra archivos y atenciones guardadas');
    $rows=$app->db->all('SELECT e.id,(SELECT COUNT(*) FROM clinical_files f JOIN packages p ON p.id=f.package_id WHERE p.encounter_id=e.id) files FROM encounters e WHERE e.admission IN (?,?)',[$tag.'A',$tag.'B']);
    bulkCheck(count($rows)===2 && array_sum(array_column($rows,'files'))===3,'Servidor separa pacientes y conserva anexos');
    $post['csrf']=bulkToken($result);bulkWeb($post);[,,$result]=bulkWeb();
    bulkCheck(str_contains($result,'2 ya registradas') && str_contains($result,'0 historias guardadas'),'Repetir carga HTTP no duplica historias');
    echo "\nPASS: $checks comprobaciones de carga masiva HTTP. Sin correos.\n";
}finally{
    // Remove only synthetic data belonging to this run's random documents and admissions.
    foreach($numbers as $number){
        $patient=$app->db->one("SELECT id FROM patients WHERE document_number=? AND full_name='Paciente ficticio de prueba de carga'",[$number]);
        if(!$patient)continue;
        foreach($app->db->all('SELECT id FROM encounters WHERE patient_id=? AND admission IN (?,?)',[$patient['id'],$tag.'A',$tag.'B']) as $encounter){
            foreach($app->db->all('SELECT id FROM packages WHERE encounter_id=?',[$encounter['id']]) as $package){
                foreach($app->db->all('SELECT storage_name FROM clinical_files WHERE package_id=?',[$package['id']]) as $file){
                    if(!preg_match('/^[a-f0-9]{32}\.pdf$/',$file['storage_name']))throw new RuntimeException('Invalid test file');
                    $path=$app->config['storage_path'].'/histories/'.$file['storage_name'];if(is_file($path))unlink($path);
                }
                $app->db->run('DELETE FROM clinical_files WHERE package_id=?',[$package['id']]);
                $app->db->run("DELETE FROM events WHERE request_id IS NULL AND action='pdf_indexed' AND result=?",['package_'.$package['id']]);
                $app->db->run('DELETE FROM packages WHERE id=?',[$package['id']]);
            }
            $app->db->run('DELETE FROM encounters WHERE id=?',[$encounter['id']]);
        }
        $app->db->run('DELETE FROM patients WHERE id=?',[$patient['id']]);
    }
    foreach(array_merge($sources,[$cookie]) as $path)if(is_file($path))unlink($path);
}
