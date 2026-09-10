<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$app = require dirname(__DIR__) . '/src/bootstrap.php';
if ($app->config['mode'] !== 'demo' || $app->config['mail_transport'] !== 'local') throw new RuntimeException('Esta prueba requiere modo demo y correo local.');
$db=$app->db; $checks=0; $number=(string)random_int(8000000000,8999999999); $admission='TEST'.strtoupper(bin2hex(random_bytes(6)));
require __DIR__.'/TestPdf.php';
$source=dirname(__DIR__).'/.runtime/'.$admission.'-1.pdf'; $source2=dirname(__DIR__).'/.runtime/'.$admission.'-2.pdf';
file_put_contents($source,testPdf($number,$admission,'NOTA')); file_put_contents($source2,testPdf($number,$admission,'ANEXO'));
$cookiePath=dirname(__DIR__).'/.runtime/upload-test-'.bin2hex(random_bytes(5)).'.cookie';
function web(string $page, ?array $post=null): string {
    global $cookiePath;
    $ch=curl_init('http://localhost/CitaYaV2/public/?page='.$page);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookiePath,CURLOPT_COOKIEFILE=>$cookiePath,CURLOPT_TIMEOUT=>15]);
    if($post!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$post);}
    $body=curl_exec($ch);if($body===false)throw new RuntimeException(curl_error($ch));curl_close($ch);return $body;
}
function token(string $html): string {preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$m);return $m[1]??throw new RuntimeException('CSRF not found');}
function verifyUpload(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);$checks++;echo "OK $label\n";}
try {
    $login=web('admin-login');
    $credentials=file_get_contents(dirname(__DIR__).'/.runtime/first-login.txt');
    preg_match('/Contraseña: ([a-f0-9]+)/u',$credentials,$m);
    $admin=web('admin-login',['csrf'=>token($login),'action'=>'login','email'=>'admin@citaya.local','password'=>$m[1]]);
    verifyUpload(str_contains($admin,'Historias registradas'),'Acceso administrativo por HTTP');
    $fields=['csrf'=>token($admin),'action'=>'upload','complete'=>1];
    $fields['pdfs[0]']=new CURLFile($source,'application/pdf','Historia ficticia.pdf');
    $fields['pdfs[1]']=new CURLFile($source2,'application/pdf','Anexo ficticio.pdf');
    $result=web('admin',$fields);
    verifyUpload(str_contains($result,'Historia completa registrada'),'Carga multipart de paquete completo');
    $encounter=$db->one('SELECT * FROM encounters WHERE admission=?',[$admission]);
    verifyUpload($encounter!==null,'Atención asociada al ingreso de prueba');
    $package=$db->one('SELECT * FROM packages WHERE encounter_id=?',[$encounter['id']]);
    $files=$app->packageFiles((int)$package['id']);
    verifyUpload(count($files)===2,'Se registran todos los PDF cargados');
    verifyUpload(hash_file('sha256',$files[0]['path'])===hash_file('sha256',$source),'La carga conserva los bytes originales');
    $indexed=$db->one('SELECT * FROM patients WHERE id=?',[$encounter['patient_id']]);
    verifyUpload($indexed['document_number']===$number && $indexed['full_name']==='Paciente ficticio de prueba de carga','Lee paciente del PDF sin campos manuales');
    verifyUpload($indexed['email']==='' && $indexed['email_verified_at']===null,'No inventa ni certifica un correo a partir del PDF');
    $again=web('admin',array_replace($fields,['csrf'=>token($result)]));
    verifyUpload((int)$db->one('SELECT COUNT(*) n FROM packages WHERE encounter_id=?',[$encounter['id']])['n']===1,'Volver a cargar no duplica el paquete');
    // Without the complete-package acknowledgement, no new attention may be created.
    $fields['csrf']=token($again);$fields['complete']=0;
    $invalid=web('admin',$fields);
    verifyUpload(str_contains($invalid,'Confirma que los archivos'),'Carga sin confirmación de completitud rechazada');
    verifyUpload((int)$db->one('SELECT COUNT(*) n FROM encounters WHERE patient_id=?',[$indexed['id']])['n']===1,'No deja una atención parcial tras rechazo');
    echo "\nPASS: $checks comprobaciones de carga.\n";
} finally {
    // Cleanup only the unique fictional patient and admission generated in this run.
    $patient=$db->one("SELECT * FROM patients WHERE document_type='CC' AND document_number=? AND full_name='Paciente ficticio de prueba de carga'",[$number]);
    if($patient){
        $rows=$db->all('SELECT f.id,f.storage_name,f.package_id,e.id encounter_id FROM clinical_files f JOIN packages p ON p.id=f.package_id JOIN encounters e ON e.id=p.encounter_id WHERE e.patient_id=? AND e.admission=?',[$patient['id'],$admission]);
        foreach($rows as $row){
            if(!preg_match('/^[a-f0-9]{32}\.pdf$/',$row['storage_name']))throw new RuntimeException('Invalid cleanup path');
            if (!unlink($app->config['storage_path'].'/histories/'.$row['storage_name'])) throw new RuntimeException('No se pudo limpiar un archivo temporal de la prueba.');
            $db->run('DELETE FROM clinical_files WHERE id=?',[$row['id']]);
        }
        foreach($db->all('SELECT id FROM encounters WHERE patient_id=? AND admission=?',[$patient['id'],$admission]) as $row){$db->run('DELETE FROM packages WHERE encounter_id=?',[$row['id']]);$db->run('DELETE FROM encounters WHERE id=?',[$row['id']]);}
        $db->run('DELETE FROM patients WHERE id=?',[$patient['id']]);
    }
    if(is_file($cookiePath))unlink($cookiePath);
    foreach([$source,$source2] as $path) if(is_file($path))unlink($path);
}
