<?php
// Local PHP development server only. Never upload this file to public_html.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if($path==='/favicon.ico'&&is_file(__DIR__.'/public_html/favicon.ico'))return false;
if (preg_match('#^/(?:assets|products|fonts)/[a-zA-Z0-9/_.-]+$#', $path)
    && !str_contains($path, '..') && is_file(__DIR__.'/public_html'.$path)) return false;
require __DIR__.'/public_html/index.php';
