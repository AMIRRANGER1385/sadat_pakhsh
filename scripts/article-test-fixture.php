<?php
declare(strict_types=1);
require __DIR__.'/../php-store/naylex-app/core.php';
$config=require __DIR__.'/../php-store/naylex-app/config.php';
if(PHP_SAPI!=='cli'||config('db_name')!=='naylex_local'||config('db_port')!==33077)exit(1);
$username=$argv[2]??'';
if(!preg_match('/^article-test-[a-f0-9]{16}$/',$username))exit(1);
if(($argv[1]??'')==='create'){
 $password=bin2hex(random_bytes(24));
 query("INSERT INTO ns_users(username,name,password,role) VALUES (?,?,?,'ADMIN')",[$username,$username,password_hash($password,PASSWORD_BCRYPT)]);
 echo json_encode(['username'=>$username,'password'=>$password]);
}elseif(($argv[1]??'')==='cleanup'){
 query('DELETE FROM ns_articles WHERE slug=?',[$username]);
 query('DELETE FROM ns_users WHERE username=?',[$username]);
}
