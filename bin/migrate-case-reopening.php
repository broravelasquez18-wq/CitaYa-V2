<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
foreach (['case_reopened_at'=>'DATETIME NULL','case_reopened_by'=>'BIGINT UNSIGNED NULL','case_reopened_after_job_id'=>'BIGINT UNSIGNED NULL'] as $column=>$definition) {
    if (!$app->db->one("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='requests' AND COLUMN_NAME=?", [$column])) {
        $app->db->pdo->exec("ALTER TABLE requests ADD COLUMN $column $definition");
    }
}
echo "Migración de reapertura aplicada. Datos existentes conservados.\n";
