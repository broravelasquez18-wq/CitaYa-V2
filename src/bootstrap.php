<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
date_default_timezone_set('America/Bogota');
$config = require dirname(__DIR__) . '/config/default.php';
if (is_file(dirname(__DIR__) . '/config/local.php')) {
    $config = array_replace($config, require dirname(__DIR__) . '/config/local.php');
}
if (!$config['app_key']) {
    throw new RuntimeException('Ejecuta php bin/setup.php para configurar el sistema.');
}
$db = new App\Database($config);
$app = new App\Application($db, $config);
return $app;
