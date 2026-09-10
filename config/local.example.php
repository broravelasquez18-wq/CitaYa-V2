<?php
// Copy SMTP settings into config/local.php after running bin/setup.php.
// Keep the generated app_key and existing database settings.
return [
    'mail_transport' => 'smtp',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_user' => 'YOUR_SMTP_ACCOUNT',
    'smtp_password' => 'YOUR_PROVIDER_APP_PASSWORD',
    'mail_from' => 'YOUR_SMTP_ACCOUNT',
    'test_recipient' => 'broravelasquez18@gmail.com',
];
