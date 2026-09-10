<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app=require dirname(__DIR__).'/src/bootstrap.php';
if (count($argv)<2) { fwrite(STDERR,"Uso: php bin/import-pdf.php archivo.pdf [anexo.pdf ...]\n"); exit(1); }
try {
    $sources=[];
    foreach(array_slice($argv,1) as $argument) {
        $path=realpath($argument);
        if (!$path || !is_file($path)) throw new DomainException('No se encontró uno de los PDF.');
        $sources[]=['path'=>$path,'name'=>basename($path)];
    }
    $id=(new App\Repository($app))->importPackage($sources);
    echo "Historia leída e indexada. Paquete: $id\n";
} catch(DomainException $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
