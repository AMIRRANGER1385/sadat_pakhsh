<?php
declare(strict_types=1);
$appPath = getenv('NAYLEX_APP_PATH') ?: dirname(__DIR__) . '/naylex-app';
require $appPath . '/bootstrap.php';
