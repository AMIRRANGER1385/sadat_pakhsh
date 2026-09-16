<?php
declare(strict_types=1);
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';require dirname(__DIR__).'/php-store/naylex-app/core.php';require dirname(__DIR__).'/php-store/naylex-app/two-factor.php';
if(PHP_SAPI!=='cli'||config('db_name')!=='naylex_local'||config('db_port')!==33077)exit(1);ensure_two_factor_schema();$mode=$argv[1]??'';$username=$argv[2]??'';
if($mode==='create'){$username='twofa-'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(16));$secret=base32_encode_bytes(random_bytes(20));query("INSERT INTO ns_users(username,name,password,role) VALUES (?,? ,?,'ADMIN')",[$username,'2FA regression fixture',password_hash($password,PASSWORD_BCRYPT)]);$id=(int)db()->lastInsertId();query('INSERT INTO ns_admin_2fa(user_id,secret_cipher,recovery_hashes) VALUES (?,?,?)',[$id,encrypt_two_factor_secret($secret),'[]']);echo json_encode(compact('username','password','secret'));exit;}
if($mode==='cleanup'&&preg_match('/^twofa-[a-f0-9]{16}$/',$username)){query('DELETE FROM ns_users WHERE username=?',[$username]);exit;}
exit(1);
