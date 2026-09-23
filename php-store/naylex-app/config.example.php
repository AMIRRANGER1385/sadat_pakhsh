<?php
declare(strict_types=1);
// Copy to config.php on the HOST, outside public_html. Never commit real secrets.
return [
    'app_url' => 'https://sadatpakhsh.ir',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'rrjjboqm_naylex',
    'db_user' => 'rrjjboqm_naylex',
    'db_password' => '',
    // Generate a long random installation key; disable installer after installation.
    'install_key' => '',
    'allow_install' => false,
    'merchant_id' => '',
    'sandbox' => true,
    'mail_from' => 'no-reply@example.com',
    'sms_provider' => '',
    'sms_api_key' => '',
    'sms_sender' => '',
    'reservation_minutes' => 30,
    'storage' => __DIR__ . '/storage',
];
