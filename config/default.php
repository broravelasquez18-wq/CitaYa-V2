<?php
declare(strict_types=1);

return [
    'mode' => 'demo',
    'db_dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=citaya_historias;charset=utf8mb4',
    'db_user' => 'root',
    'db_password' => '',
    'storage_path' => dirname(__DIR__) . '/storage',
    'app_key' => '',
    'mail_transport' => 'local', // local: private preview; smtp: external delivery
    'test_recipient' => 'broravelasquez18@gmail.com',
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_user' => '',
    'smtp_password' => '',
    'mail_from' => 'historias@example.test',
    'mail_from_name' => 'CitaYa · Historias clínicas',
    'search_days' => 30,
    'otp_seconds' => 600,
    'otp_attempts' => 5,
    'resend_seconds' => 60,
    'session_seconds' => 900,
    'max_mail_bytes' => 20 * 1024 * 1024,
    'max_upload_bytes' => 12 * 1024 * 1024,
    'mail_attempts' => 3,
    'history_resend_seconds' => 60,
    'history_resend_max' => 3,
];
