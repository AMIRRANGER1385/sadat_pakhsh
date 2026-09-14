<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/../php-store/naylex-app/core.php';
$config=require __DIR__.'/../php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Local database only');
$values=parse_ini_file(__DIR__.'/../.env',false,INI_SCANNER_RAW);
$username=digits(trim($values['ADMIN_USERNAME']??''));$password=$values['ADMIN_PASSWORD']??'';
if(mb_strlen($username)<3||strlen($username)>100||strlen($password)<1||strlen($password)>72)throw new RuntimeException('Invalid ADMIN_USERNAME or ADMIN_PASSWORD in .env');
transaction(function()use($username,$password){
 $u=one('SELECT * FROM ns_users WHERE username=? FOR UPDATE',[$username]);
 if($u&&$u['role']!=='ADMIN')throw new RuntimeException('Username belongs to a customer; no changes made');
 if($u)query('UPDATE ns_users SET password=?,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$u['id']]);
 else query("INSERT INTO ns_users(username,name,password,role,wholesale_status) VALUES (?,?,?,'ADMIN','NONE')",[$username,'مدیر نایلکس سادات',password_hash($password,PASSWORD_BCRYPT,['cost'=>12])]);
});
file_put_contents(__DIR__.'/../.runtime/php-local-admin.json',json_encode(['username'=>$username,'password'=>$password]));
echo "Local PHP admin synchronized with .env; secrets were not printed.\n";
