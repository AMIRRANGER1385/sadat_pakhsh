<?php
declare(strict_types=1);

function ensure_two_factor_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_admin_2fa (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  secret_cipher TEXT NOT NULL,
  recovery_hashes TEXT NOT NULL,
  enabled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_counter BIGINT NOT NULL DEFAULT -1,
  FOREIGN KEY(user_id) REFERENCES ns_users(id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $ready=true;
}

function base32_encode_bytes(string $bytes): string {
 $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';$out='';
 foreach(str_split($bytes) as $char)$bits.=str_pad(decbin(ord($char)),8,'0',STR_PAD_LEFT);
 foreach(str_split($bits,5) as $chunk){if(strlen($chunk)<5)$chunk=str_pad($chunk,5,'0');$out.=$alphabet[bindec($chunk)];}
 return $out;
}

function base32_decode_bytes(string $value): string {
 $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';$out='';
 foreach(str_split(strtoupper(preg_replace('/[^A-Z2-7]/i','',$value))) as $char){$pos=strpos($alphabet,$char);if($pos===false)throw new ShopError('کلید ورود دومرحله‌ای نامعتبر است.');$bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);}
 foreach(str_split($bits,8) as $chunk)if(strlen($chunk)===8)$out.=chr(bindec($chunk));
 return $out;
}

function two_factor_key(): string {
 if(!extension_loaded('sodium')&&!extension_loaded('openssl'))throw new ShopError('افزونه Sodium یا OpenSSL در PHP برای حفاظت از کلید 2FA باید فعال باشد.',503);$keyBytes=32;
 $path=rtrim((string)config('storage'),'/\\').'/two-factor.key';
 if(!is_file($path)){
  $key=random_bytes($keyBytes);$handle=@fopen($path,'x');
  if(!$handle)throw new ShopError('ساخت کلید خصوصی 2FA ممکن نشد.',503);
  try{if(fwrite($handle,$key)!==strlen($key))throw new ShopError('ذخیره کلید خصوصی 2FA ممکن نشد.',503);}finally{fclose($handle);}@chmod($path,0600);
 }
 $key=file_get_contents($path);if($key===false||strlen($key)!==$keyBytes)throw new ShopError('کلید خصوصی 2FA معتبر نیست.',503);
 return $key;
}

function encrypt_two_factor_secret(string $secret): string {
 $key=two_factor_key();if(function_exists('sodium_crypto_secretbox')){$nonce=random_bytes(24);return 's1.'.base64_encode($nonce.sodium_crypto_secretbox($secret,$nonce,$key));}
 $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($secret,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'',16);if($cipher===false)throw new ShopError('رمزگذاری داده 2FA ممکن نشد.',503);return 'o1.'.base64_encode($iv.$tag.$cipher);
}

function decrypt_two_factor_secret(string $cipher): string {
 $parts=explode('.',$cipher,2);if(count($parts)!==2)throw new ShopError('داده 2FA آسیب دیده است.',503);$raw=base64_decode($parts[1],true);$key=two_factor_key();
 if($parts[0]==='s1'&&function_exists('sodium_crypto_secretbox_open')&&$raw!==false&&strlen($raw)>24){$plain=sodium_crypto_secretbox_open(substr($raw,24),substr($raw,0,24),$key);}
 elseif($parts[0]==='o1'&&$raw!==false&&strlen($raw)>28){$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'');}
 else $plain=false;
 if($plain===false)throw new ShopError('رمزگشایی داده 2FA ممکن نشد.',503);return $plain;
}

function totp_code(string $secret,int $counter): string {
 $key=base32_decode_bytes($secret);$high=intdiv($counter,4294967296);$low=$counter%4294967296;
 $hash=hash_hmac('sha1',pack('N2',$high,$low),$key,true);$offset=ord($hash[19])&15;
 $number=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255);
 return str_pad((string)($number%1000000),6,'0',STR_PAD_LEFT);
}

function verify_totp(string $secret,string $code,int $now,?int $lastCounter=null): ?int {
 $code=digits(trim($code));if(!preg_match('/^\d{6}$/',$code))return null;$counter=intdiv($now,30);
 foreach([-1,0,1] as $delta){$candidate=$counter+$delta;if(($lastCounter===null||$candidate>$lastCounter)&&hash_equals(totp_code($secret,$candidate),$code))return $candidate;}
 return null;
}

function admin_two_factor(int $userId): ?array {ensure_two_factor_schema();return one('SELECT * FROM ns_admin_2fa WHERE user_id=?',[$userId]);}

function create_recovery_codes(): array {$codes=[];for($i=0;$i<8;$i++)$codes[]=strtoupper(bin2hex(random_bytes(5)));return $codes;}

function verify_two_factor_value(array $row,string $value,bool $consume=true): bool {
 $secret=decrypt_two_factor_secret($row['secret_cipher']);$counter=verify_totp($secret,$value,time(),(int)$row['last_counter']);
 if($counter!==null){if($consume)query('UPDATE ns_admin_2fa SET last_counter=? WHERE user_id=?',[$counter,$row['user_id']]);return true;}
 $normalized=strtoupper(preg_replace('/[^A-F0-9]/','',$value));$hashes=json_decode($row['recovery_hashes'],true);if(!is_array($hashes)||!preg_match('/^[A-F0-9]{10}$/',$normalized))return false;
 foreach($hashes as $index=>$hash)if(is_string($hash)&&password_verify($normalized,$hash)){if($consume){unset($hashes[$index]);query('UPDATE ns_admin_2fa SET recovery_hashes=? WHERE user_id=?',[json_encode(array_values($hashes)),$row['user_id']]);}return true;}
 return false;
}

function begin_admin_login(array $user): never {
 session_regenerate_id(true);$_SESSION=[];$_SESSION['pending_2fa_user']=(int)$user['id'];$_SESSION['pending_2fa_expires']=time()+300;$_SESSION['csrf']=bin2hex(random_bytes(32));redirect('/admin-2fa');
}

function two_factor_login_page(): void {
 if(empty($_SESSION['pending_2fa_user'])||($_SESSION['pending_2fa_expires']??0)<time())redirect('/login');
 page_header('تأیید دومرحله‌ای مدیریت','',false);?><div class="container page-content"><div class="panel auth-form form-stack"><h1>تأیید ورود مدیر</h1><p>کد ۶ رقمی برنامه Authenticator یا یکی از کدهای بازیابی را وارد کنید.</p><?php action_start('2fa_login','','/admin-2fa');field('کد تأیید یا بازیابی','two_factor_code','','text');?><button class="btn">تأیید و ورود</button></form><a class="text-link" href="/login">بازگشت به ورود</a></div></div><?php page_footer();
}

function two_factor_security_panel(array $admin): void {
 $row=admin_two_factor((int)$admin['id']);
 ?><section class="panel"><h2>ورود دومرحله‌ای مدیر (2FA)</h2><?php
 if($row):?><p class="success">ورود دومرحله‌ای فعال است. ورودهای بعدی علاوه بر رمز به کد Authenticator نیاز دارند.</p><?php if(!empty($_SESSION['two_factor_recovery_codes'])):$codes=$_SESSION['two_factor_recovery_codes'];unset($_SESSION['two_factor_recovery_codes']);?><div class="notice"><strong>کدهای بازیابی را همین حالا در محل امن ذخیره کنید؛ دوباره نمایش داده نمی‌شوند.</strong><pre><?=h(implode("\n",$codes))?></pre></div><?php endif;action_start('2fa_disable','form-stack','/admin');field('رمز فعلی','current_password','','password');field('کد Authenticator یا بازیابی','two_factor_code','','text');?><button class="btn outline" data-confirm="ورود دومرحله‌ای غیرفعال شود؟">غیرفعال‌کردن 2FA</button></form><?php
 else:
  $secret=$_SESSION['totp_setup_secret']??=base32_encode_bytes(random_bytes(20));$issuer=rawurlencode('Sadat Pakhsh');$account=rawurlencode((string)$admin['username']);$uri='otpauth://totp/'.$issuer.':'.$account.'?secret='.$secret.'&issuer='.$issuer.'&digits=6&period=30';
  ?><p>در Google Authenticator، Microsoft Authenticator یا برنامه سازگار، کلید زیر را دستی اضافه کنید. سپس یک کد تولیدشده را برای فعال‌سازی وارد کنید.</p><p><strong class="ltr"><?=h($secret)?></strong></p><details><summary>نشانی فنی otpauth</summary><code class="ltr"><?=h($uri)?></code></details><?php action_start('2fa_enable','form-stack','/admin');field('رمز فعلی','current_password','','password');field('کد ۶ رقمی برنامه','two_factor_code','','text');?><button class="btn">فعال‌کردن 2FA</button></form><?php endif;?></section><?php
}
