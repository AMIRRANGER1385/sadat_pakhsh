<?php
declare(strict_types=1);
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$file=realpath(__DIR__.'/../php-store/public_html'.$path);
$root=realpath(__DIR__.'/../php-store/public_html');
if($file&&str_starts_with($file,$root.DIRECTORY_SEPARATOR)&&is_file($file))return false;
require __DIR__.'/../php-store/public_html/index.php';
