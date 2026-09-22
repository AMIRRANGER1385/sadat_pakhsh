<?php
declare(strict_types=1);
require_once __DIR__.'/product-sales.php';
final class ShopError extends RuntimeException {
 public function __construct(string $message, public readonly int $status=400) { parent::__construct($message); }
}
function config(?string $key=null): mixed {global $config; return $key===null?$config:($config[$key]??null);}
function db(): PDO {
 static $pdo=null;
 if (!$pdo) {$pdo=new PDO('mysql:host='.config('db_host').';port='.(int)config('db_port').';dbname='.config('db_name').';charset=utf8mb4',config('db_user'),config('db_password'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec("SET time_zone='+00:00'");}
 return $pdo;
}
function query(string $sql,array $args=[]): PDOStatement {$s=db()->prepare($sql);$s->execute($args);return $s;}
function one(string $sql,array $args=[]): ?array {return query($sql,$args)->fetch()?:null;}
function all(string $sql,array $args=[]): array {return query($sql,$args)->fetchAll();}
function transaction(callable $fn): mixed {db()->beginTransaction();try{$r=$fn();db()->commit();return $r;}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}}
function h(mixed $s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function money(int|float|string $n): string {return strtr(number_format((float)$n),['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);}
function digits(string $s): string {return strtr($s,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);}
function text_input(string $key,int $min=0,int $max=200,?array $data=null): string {$v=($data??$_POST)[$key]??'';if(!is_string($v))throw new ShopError('مقدار متنی نامعتبر است.');$v=trim($v);if(mb_strlen($v)<$min||mb_strlen($v)>$max)throw new ShopError('طول یکی از فیلدها نامعتبر است.');return $v;}
function number_input(string $key,int $min=0,int $max=100000000,?array $data=null): int {$v=($data??$_POST)[$key]??null;if(!is_scalar($v)||!preg_match('/^\d+$/',digits((string)$v)))throw new ShopError('عدد معتبر وارد کنید.');$n=(int)digits((string)$v);if($n<$min||$n>$max)throw new ShopError('عدد خارج از محدوده مجاز است.');return $n;}
function phone_input(string $key='phone'): string {$v=digits(text_input($key,11,11));if(!preg_match('/^09\d{9}$/',$v))throw new ShopError('شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');return $v;}
function password_input(string $key): string {$s=$_POST[$key]??'';if(!is_string($s)||mb_strlen($s)<8||strlen($s)>72)throw new ShopError('رمز باید حداقل ۸ کاراکتر و حداکثر ۷۲ بایت باشد.');return $s;}
function url(string $path='/'): string {return rtrim(config('app_url'),'/').$path;}
function asset_url(string $name): string {
 if(!in_array($name,['shop.css','shop.js','home-experience.css','home-experience.js'],true))throw new InvalidArgumentException('Unknown asset');
 static $versions=[];
 $file=dirname(__DIR__).'/public_html/assets/'.$name;
 // Private code and public files can be deployed to different directories.
 if(defined('NAYLEX_PUBLIC_ROOT'))$file=NAYLEX_PUBLIC_ROOT.'/assets/'.$name;
 $versions[$name]??=is_file($file)?substr(hash_file('sha256',$file),0,16):'1';
 return '/assets/'.$name.'?v='.$versions[$name];
}
function redirect(string $path,int $status=303): never {if(session_status()===PHP_SESSION_ACTIVE&&!session_write_close())throw new ShopError('ذخیره نشست انجام نشد؛ مدیر هاست باید فضای دیسک و مجوز پوشه نشست را بررسی کند.',503);header('Location: '.$path,true,$status);exit;}
function flash(string $message,string $kind='success'): void {$_SESSION['flash']=[$kind,$message];}
function expire_cart_if_stale(int $now): bool {if(!empty($_SESSION['cart'])&&($_SESSION['cart_updated_at']??0)<$now-86400){unset($_SESSION['cart'],$_SESSION['cart_updated_at'],$_SESSION['checkout_key']);$_SESSION['cart_expired']=true;return true;}return false;}
function csrf(): string {return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function csrf_field(): void {echo '<input type="hidden" name="_csrf" value="'.h(csrf()).'">';}
function verify_csrf(): void {
 $v=$_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(!isset($_COOKIE[session_name()]))throw new ShopError('کوکی نشست دریافت نشد. کوکی مرورگر را فعال کنید و سایت را از آدرس اصلی آن باز کنید؛ سپس صفحه را تازه کنید.',403);
 if(!is_string($v)||!hash_equals(csrf(),$v))throw new ShopError('درخواست منقضی یا نامعتبر است؛ صفحه را تازه کنید.',403);
 if(isset($_SERVER['HTTP_ORIGIN'])&&rtrim($_SERVER['HTTP_ORIGIN'],'/')!==rtrim(config('app_url'),'/'))throw new ShopError('مبدأ درخواست مجاز نیست.',403);
}
function rate_limit(string $key,int $limit=10): void {
 $window=(int)floor(time()/900);$id=hash('sha256',$window.':'.$key);
 query('INSERT INTO ns_rate_limits(id,attempts,expires_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1',[$id,($window+1)*900]);
 if((int)one('SELECT attempts FROM ns_rate_limits WHERE id=?',[$id])['attempts']>$limit)throw new ShopError('تعداد درخواست زیاد است؛ ۱۵ دقیقه دیگر تلاش کنید.',429);
 if(random_int(1,100)===1)query('DELETE FROM ns_rate_limits WHERE expires_at<?',[time()]);
}
function current_user(): ?array {
 if(empty($_SESSION['user_id'])||($_SESSION['expires']??0)<time())return null;
 $u=one('SELECT * FROM ns_users WHERE id=?',[$_SESSION['user_id']]);
 return $u&&(int)$u['session_version']===($_SESSION['version']??0)?$u:null;
}
function admin_user(): array {$u=current_user();if(!$u||$u['role']!=='ADMIN')throw new ShopError('دسترسی فقط برای مدیر مجاز است.',403);return $u;}
function login_account(string $identifier): ?array {
 $user=one('SELECT * FROM ns_users WHERE username=?',[$identifier]);
 if($user)return $user;
 // Display names are not unique. Never guess between accounts with the same name.
 $matches=all("SELECT * FROM ns_users WHERE name=? AND role='CUSTOMER' LIMIT 2",[$identifier]);
 if(count($matches)>1)throw new ShopError('برای ورود با نام مشترک، شماره موبایل ثبت‌شده خود را وارد کنید.');
 return $matches[0]??null;
}
function login_user(array $u,string $kind='LOGIN'): void {
 session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['version']=(int)$u['session_version'];$_SESSION['expires']=time()+($u['role']==='ADMIN'?1800:86400);$_SESSION['csrf']=bin2hex(random_bytes(32));
 query('INSERT INTO ns_login_events(user_id,kind,user_agent) VALUES (?,?,?)',[$u['id'],$kind,mb_substr($_SERVER['HTTP_USER_AGENT']??'',0,300)]);
 query('DELETE FROM ns_login_events WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)');
}
function settings(): array {
 $settings=one('SELECT * FROM ns_settings WHERE id=1')??throw new ShopError('فروشگاه هنوز نصب نشده است.',503);
 // Public address confirmed by the owner. It remains editable in Company settings.
 if(trim((string)$settings['company_address'])==='')$settings['company_address']='تهران، مجتمع تجریشی';
 if(trim((string)$settings['company_email'])==='')$settings['company_email']='info@sadatpakhsh.ir';
 return $settings;
}
function unit_price(array $p,int $qty,bool $unused=false): int {return (int)((is_wholesale_product($p)||$qty>=(int)$p['minimum'])?$p['wholesale']:$p['retail']);}
function shipping_cost(int $sum,array $s): int {return (int)$s['free_above']>0&&$sum>=(int)$s['free_above']?0:(int)$s['shipping'];}
function cart_lines(): array {
 $cart=$_SESSION['cart']??[];if(!$cart)return [];
 $u=current_user();$items=[];$products=[];
 // Batch reads, including older sessions with larger carts, without an unbounded IN list.
 foreach(array_chunk(array_keys($cart),100) as $ids){
  foreach(all('SELECT * FROM ns_products WHERE active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids) as $p)$products[$p['id']]=$p;
 }
 foreach($cart as $id=>$qty){$p=$products[$id]??null;if(!$p){$items[]=['id'=>$id,'quantity'=>$qty,'missing'=>true];continue;}$p['quantity']=$qty;$p['price']=unit_price($p,$qty);$items[]=$p;}
 return $items;
}
function order_status(string $s): string {return ['PENDING'=>'در انتظار پرداخت','PAID'=>'پرداخت شده','SHIPPED'=>'ارسال شده','DELIVERED'=>'تحویل شده','EXPIRED'=>'مهلت پایان یافته؛ موجودی آزاد شده','PAYMENT_REVIEW'=>'پرداخت دریافت شده؛ نیازمند تأمین موجودی یا استرداد','CANCELLED'=>'لغو شده'][$s]??$s;}
function json_ld(array $data): void {global $nonce;echo '<script type="application/ld+json" nonce="'.h($nonce).'">'.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';}
function field(string $label,string $name,mixed $value='',string $type='text',bool $required=true): void {echo '<label>'.h($label).'<input type="'.h($type).'" name="'.h($name).'" value="'.h($value).'" '.($required?'required':'').'></label>';}
function textarea_field(string $label,string $name,string $value='',bool $required=false): void {echo '<label>'.h($label).'<textarea rows="4" name="'.h($name).'" '.($required?'required':'').'>'.h($value).'</textarea></label>';}
