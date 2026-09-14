<?php
declare(strict_types=1);
require __DIR__.'/../php-store/naylex-app/core.php';
$config=require __DIR__.'/../php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Local DB only');
db()->beginTransaction();
try{
 $name='Name test '.bin2hex(random_bytes(8));$pass=bin2hex(random_bytes(16));$ids=[];
 foreach([1,2] as $i){
  $phone='09'.str_pad((string)random_int(0,999999999),9,'0',STR_PAD_LEFT);
  query("INSERT INTO ns_users(username,name,password,role) VALUES (?,?,?,'CUSTOMER')",[$phone,$name,password_hash($pass,PASSWORD_BCRYPT)]);$ids[]=(int)db()->lastInsertId();
  if($i===1){$u=login_account($name);if((int)$u['id']!==$ids[0]||!password_verify($pass,$u['password']))throw new RuntimeException('Name lookup failed');echo "PASS unique customer name and password\n";}
 }
 $rejected=false;try{login_account($name);}catch(ShopError){$rejected=true;}
 if(!$rejected)throw new RuntimeException('Ambiguous name accepted');echo "PASS duplicate names require mobile\n";
 if((int)login_account($phone)['id']!==$ids[1])throw new RuntimeException('Mobile lookup failed');echo "PASS mobile login preserved\n";
}finally{db()->rollBack();}
