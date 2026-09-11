<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/UploadHttp.php';
use App\UploadHttp;
function uploadAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK $message\n";
}
$server = ['REQUEST_METHOD'=>'POST','HTTP_ACCEPT'=>'application/json','CONTENT_TYPE'=>'multipart/form-data; boundary=test','CONTENT_LENGTH'=>2048];
uploadAssert(UploadHttp::wantsJson($server, []), 'JSON reconocido cuando PHP descarta POST');
uploadAssert(UploadHttp::exceedsPostLimit($server, '1K'), 'Petición por encima del límite');
uploadAssert(!UploadHttp::exceedsPostLimit($server, '2K'), 'Petición dentro del límite');
uploadAssert(!UploadHttp::exceedsPostLimit($server, '0'), 'Límite ilimitado');
uploadAssert(!UploadHttp::wantsJson(array_replace($server,['HTTP_ACCEPT'=>'text/html']), []), 'Formulario HTML conservado');
uploadAssert(!UploadHttp::wantsJson(array_replace($server,['REQUEST_METHOD'=>'GET']), []), 'GET no tratado como carga');
uploadAssert(!UploadHttp::wantsJson(array_replace($server,['CONTENT_TYPE'=>'application/x-www-form-urlencoded']), ['action'=>'login']), 'Login fuera de protocolo de carga');
if (isset($argv[1])) {
    // Run against an isolated PHP server with post_max_size=1K, never production.
    $boundary='citayaSyntheticUpload';
    $body="--$boundary\r\nContent-Disposition: form-data; name=\"action\"\r\n\r\nupload\r\n--$boundary\r\nContent-Disposition: form-data; name=\"pdfs[]\"; filename=\"synthetic.pdf\"\r\nContent-Type: application/pdf\r\n\r\n".str_repeat('x',2048)."\r\n--$boundary--\r\n";
    $curl=curl_init($argv[1]);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: multipart/form-data; boundary='.$boundary],CURLOPT_TIMEOUT=>10]);
    $response=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    uploadAssert($status===413,'PHP rechaza petición multipart sobredimensionada con HTTP 413');
    uploadAssert(is_string($response) && str_contains(json_decode($response,true)['error'] ?? '', 'límite total'), 'JSON legible incluso con POST y FILES descartados');
}
