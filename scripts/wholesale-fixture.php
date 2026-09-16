<?php
require dirname(__DIR__).'/php-store/naylex-app/core.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||(int)config('db_port')!==33077)throw new RuntimeException('Local test database only');
$id=(int)($argv[2]??0);
if(($argv[1]??'')==='remove'){query("DELETE FROM ns_users WHERE id=? AND name='Wholesale regression fixture'",[$id]);exit;}
if(($argv[1]??'')==='approve'){query("UPDATE ns_users SET wholesale_status='APPROVED' WHERE id=? AND name='Wholesale regression fixture'",[$id]);exit;}
$password=bin2hex(random_bytes(16));$username='test-'.bin2hex(random_bytes(8));
query("INSERT INTO ns_users(username,name,password,wholesale_status) VALUES (?,'Wholesale regression fixture',?,'NONE')",[$username,password_hash($password,PASSWORD_BCRYPT)]);
echo json_encode(['id'=>db()->lastInsertId(),'username'=>$username,'password'=>$password]);
