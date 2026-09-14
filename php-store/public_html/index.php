<?php
declare(strict_types=1);
define('NAYLEX_PUBLIC_ROOT', __DIR__);
$appPath = getenv('NAYLEX_APP_PATH') ?: dirname(__DIR__) . '/naylex-app';
require $appPath . '/bootstrap.php';
