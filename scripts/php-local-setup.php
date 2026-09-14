<?php
// Local isolated verification only; never part of the hosting package.
$root=dirname(__DIR__);
$pdo=new PDO('mysql:host=127.0.0.1;port=33077;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS naylex_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$config=['app_url'=>'http://localhost:8085','db_host'=>'127.0.0.1','db_port'=>33077,'db_name'=>'naylex_local','db_user'=>'root','db_password'=>'','install_key'=>bin2hex(random_bytes(24)),'allow_install'=>true,'merchant_id'=>'','sandbox'=>true,'storage'=>$root.'/.runtime/php-storage'];
$file=$root.'/php-store/naylex-app/config.php';
if(!file_exists($file))file_put_contents($file,"<?php\nreturn ".var_export($config,true).";\n");
echo "Isolated local PHP configuration prepared.\n";
