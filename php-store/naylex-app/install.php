<?php
declare(strict_types=1);
header('X-Robots-Tag: noindex, nofollow');
if(!config('allow_install')||strlen((string)config('install_key'))<32||is_file(config('storage').'/installed.lock'))throw new ShopError('نصب‌کننده غیرفعال است یا فروشگاه قبلاً نصب شده است.',403);
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();$key=text_input('install_key',32,200);
 if(!hash_equals(config('install_key'),$key))throw new ShopError('کلید نصب معتبر نیست.',403);
 foreach(['pdo_mysql','mbstring','curl','fileinfo','gd'] as $ext)if(!extension_loaded($ext))throw new ShopError('افزونه PHP فعال نیست: '.$ext);
 $username=text_input('username',3,100);$password=password_input('password');
 if(!preg_match('/^[a-zA-Z0-9_-]+$/',$username))throw new ShopError('نام کاربری مدیر را با حروف انگلیسی وارد کنید.');
 $lock=fopen(config('storage').'/install-operation.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new ShopError('نصب دیگری در حال اجرا است.');
 try {
  $tables=query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  foreach($tables as $table)if(!str_starts_with($table,'ns_'))throw new ShopError('این دیتابیس خالی نیست؛ برای جلوگیری از تغییر وردپرس، دیتابیس جدا ایجاد کنید.');
  $sql=file_get_contents(__DIR__.'/schema.sql');foreach(explode(';',$sql) as $statement)if(trim($statement))db()->exec($statement);
  if((int)query('SELECT COUNT(*) FROM ns_users')->fetchColumn()>0)throw new ShopError('حساب‌ها از قبل وجود دارند؛ نصب مجدد مجاز نیست.');
  transaction(function()use($username,$password){
   $names=['کیسه فریزر','کیسه زباله','نایلون و نایلکس','بسته‌بندی','سفره یکبار مصرف'];$ids=[];
   foreach($names as $name){query('INSERT INTO ns_categories(name) VALUES (?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$name]);$ids[]=one('SELECT id FROM ns_categories WHERE name=?',[$name])['id'];}
   $products=['کیسه فریزر رولی ۲۵۰ عددی','کیسه زباله رولی بنددار','نایلکس دسته رکابی سفید','نایلون بسته‌بندی شفاف','کیسه فریزر زیپ‌دار','کیسه زباله صنعتی مشکی','سفره یکبار مصرف طرح برگ','نایلکس دسته موزی رنگی'];
   foreach($products as $i=>$name)query('INSERT INTO ns_products(slug,name,description,image,retail,wholesale,minimum,stock,unit,featured,category_id) VALUES (?,?,?,?,?,?,10,150,?,?,?) ON DUPLICATE KEY UPDATE slug=VALUES(slug)',['product-'.($i+1),$name,'ساخته‌شده از مواد اولیه مرغوب با دوخت مقاوم و ضخامت یکنواخت. مناسب مصرف خانه و فروشگاه. مشخصات و قیمت این محصول نمونه را پیش از فروش واقعی ویرایش کنید.','/products/product-'.($i+1).'.svg',[68000,95000,125000,89000,78000,165000,58000,145000][$i],[54000,78000,105000,72000,64000,139000,46000,120000][$i],in_array($i,[2,3,7])?'کیلوگرم':'بسته',$i<4?1:0,$ids[[0,1,2,3,0,1,4,2][$i]]]);
   query("INSERT INTO ns_settings(id,company_about,company_address) VALUES (1,'','تهران، مجتمع تجریشی') ON DUPLICATE KEY UPDATE id=id");
   query("INSERT INTO ns_users(username,name,password,role) VALUES (?,'مدیر فروشگاه',?,'ADMIN')",[$username,password_hash($password,PASSWORD_BCRYPT,['cost'=>12])]);
  });
  if(file_put_contents(config('storage').'/installed.lock',gmdate('c'),LOCK_EX)===false)throw new ShopError('نصب انجام شد ولی نوشتن installed.lock ممکن نشد؛ دسترسی پوشه storage را اصلاح کنید.');
  chmod(config('storage').'/installed.lock',0600);flash('نصب کامل شد. وارد پنل مدیریت شوید و allow_install را در config.php برابر false قرار دهید.');redirect('/login');
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><link rel="stylesheet" href="/assets/shop.css"><title>نصب نایلکس سادات</title><main class="container page-content"><form method="post" class="panel form-stack auth-form"><h1>نصب نایلکس سادات</h1><p>نصب فقط روی دیتابیس جداگانه و خالی انجام می‌شود. ۸ محصول نمونه و حساب مدیر ساخته خواهند شد.</p><?php csrf_field();field('کلید نصب از config.php','install_key','','password');field('نام کاربری مدیر','username','admin');field('رمز جدید مدیر؛ حداقل ۱۲ کاراکتر','password','','password');?><button class="btn">نصب فروشگاه</button></form></main></html>
