<?php
declare(strict_types=1);

function random_password(int $length=12): string {
 $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
 $password='';
 for($i=0;$i<$length;$i++)$password.=$alphabet[random_int(0,strlen($alphabet)-1)];
 return $password;
}

function store_mail_from(): string {
 $configured=(string)(config('mail_from')??'');
 if(filter_var($configured,FILTER_VALIDATE_EMAIL))return $configured;
 try{$settings=settings();if(filter_var($settings['company_email']??'',FILTER_VALIDATE_EMAIL))return $settings['company_email'];}catch(Throwable){}
 $host=parse_url((string)config('app_url'),PHP_URL_HOST)?:'localhost';
 return 'no-reply@'.$host;
}

function smtp_read($socket): string {
 $response='';
 while(($line=fgets($socket,515))!==false){
  $response.=$line;
  if(isset($line[3])&&$line[3]===' ')break;
 }
 return $response;
}

function smtp_expect($socket,array $codes): string {
 $response=smtp_read($socket);
 if(!in_array((int)substr($response,0,3),$codes,true))throw new ShopError('ارسال ایمیل با SMTP ناموفق بود: '.trim($response),503);
 return $response;
}

function smtp_command($socket,string $command,array $codes): string {
 fwrite($socket,$command."\r\n");
 return smtp_expect($socket,$codes);
}

function send_store_email_smtp(string $to,string $subject,string $body,string $from,string $fromName): void {
 $host=trim((string)(config('smtp_host')??''));
 if($host==='')throw new ShopError('SMTP تنظیم نشده است.',503);
 $port=(int)(config('smtp_port')??587);
 $secure=strtolower((string)(config('smtp_secure')??'tls'));
 $user=(string)(config('smtp_user')??'');
 $pass=(string)(config('smtp_password')??'');
 $remote=($secure==='ssl'?'ssl://':'').$host.':'.$port;
 $socket=@stream_socket_client($remote,$errno,$errstr,20,STREAM_CLIENT_CONNECT);
 if(!$socket)throw new ShopError('اتصال به SMTP برقرار نشد: '.$errstr,503);
 stream_set_timeout($socket,20);
 smtp_expect($socket,[220]);
 $server=parse_url((string)config('app_url'),PHP_URL_HOST)?:'localhost';
 smtp_command($socket,'EHLO '.$server,[250]);
 if($secure==='tls'){
  smtp_command($socket,'STARTTLS',[220]);
  if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new ShopError('فعال‌سازی TLS ایمیل ناموفق بود.',503);
  smtp_command($socket,'EHLO '.$server,[250]);
 }
 if($user!==''||$pass!==''){
  smtp_command($socket,'AUTH LOGIN',[334]);
  smtp_command($socket,base64_encode($user),[334]);
  smtp_command($socket,base64_encode($pass),[235]);
 }
 smtp_command($socket,'MAIL FROM:<'.$from.'>',[250]);
 smtp_command($socket,'RCPT TO:<'.$to.'>',[250,251]);
 smtp_command($socket,'DATA',[354]);
 $headers=[
  'From: =?UTF-8?B?'.base64_encode($fromName).'?= <'.$from.'>',
  'Reply-To: '.$from,
  'To: <'.$to.'>',
  'Subject: =?UTF-8?B?'.base64_encode($subject).'?=',
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'Content-Transfer-Encoding: 8bit',
 ];
 $message=implode("\r\n",$headers)."\r\n\r\n".str_replace(["\r\n","\r"],"\n",$body);
 $message=str_replace("\n.","\n..",$message);
 fwrite($socket,str_replace("\n","\r\n",$message)."\r\n.\r\n");
 smtp_expect($socket,[250]);
 @smtp_command($socket,'QUIT',[221]);
 fclose($socket);
}

function send_store_email(string $to,string $subject,string $body): void {
 if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new ShopError('ایمیل حساب معتبر نیست.');
 $from=store_mail_from();
 if((string)(config('smtp_host')??'')!==''){send_store_email_smtp($to,$subject,$body,$from,'نایلکس سادات');return;}
 $headers=[
  'From: نایلکس سادات <'.$from.'>',
  'Reply-To: '.$from,
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'X-Mailer: PHP/'.PHP_VERSION,
 ];
 $ok=@mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$headers));
 if(!$ok)throw new ShopError('ارسال ایمیل انجام نشد؛ تنظیمات ایمیل هاست را بررسی کنید.',503);
}

function password_reset_action(): never {
 $identifier=auth_identifier(text_input('identifier',3,150));
 rate_limit('password-reset:'.$identifier,5);
 if(!valid_auth_identifier($identifier))throw new ShopError('شماره موبایل یا ایمیل معتبر وارد کنید.');
 $user=login_account($identifier);
 if(!$user){flash('اگر حسابی با این مشخصات وجود داشته باشد، راهنمای بازیابی ارسال می‌شود.');redirect('/forgot-password');}
 if(is_email_identifier($user['username'])){
  $password=random_password();
  query('UPDATE ns_users SET password=?,session_version=session_version+1 WHERE id=?',[password_hash($password,PASSWORD_BCRYPT,['cost'=>12]),$user['id']]);
  $body="سلام ".($user['name']?:'')."\n\nرمز عبور جدید شما در نایلکس سادات:\n".$password."\n\nپس از ورود، از بخش حساب کاربری رمز را تغییر دهید.\n".url('/login')."\n";
  send_store_email($user['username'],'رمز عبور جدید نایلکس سادات',$body);
  flash('رمز جدید به ایمیل حساب ارسال شد.');
  redirect('/login');
 }
 if(is_phone_identifier($user['username'])){
  flash('بازیابی پیامکی هنوز به سرویس پیامک متصل نشده است. برای فعال‌سازی باید پنل پیامکی را تنظیم کنیم.','error');
  redirect('/forgot-password');
 }
 flash('روش بازیابی این حساب مشخص نیست. با پشتیبانی تماس بگیرید.','error');
 redirect('/forgot-password');
}

function forgot_password_page(): void {
 page_header('بازیابی رمز عبور','',false,url('/forgot-password'));
 ?><div class="container page-content"><form method="post" class="panel form-stack auth-form" action="/forgot-password"><?php csrf_field();hidden('action','password_reset');?><h1>بازیابی رمز عبور</h1><p>شماره موبایل یا ایمیل حساب را وارد کنید. اگر حساب با ایمیل ثبت شده باشد، رمز جدید برای همان ایمیل ارسال می‌شود.</p><label>شماره موبایل یا ایمیل<input name="identifier" required autocomplete="username" dir="ltr" value="<?=h($_SESSION['login_username']??'')?>"></label><button class="btn">ارسال رمز جدید</button><a class="text-link" href="/login">بازگشت به ورود</a><small>برای حساب‌های موبایلی، ارسال پیامک بعد از اتصال پنل پیامکی فعال می‌شود.</small></form></div><?php page_footer();
}
