<?php
declare(strict_types=1);
ini_set('display_errors','0');
date_default_timezone_set('UTC');
require __DIR__.'/core.php';
$configFile=getenv('NAYLEX_CONFIG')?:__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(503);header('Content-Type: text/html; charset=utf-8');exit('<html lang="fa" dir="rtl"><h1>تنظیمات فروشگاه آماده نیست</h1><p>طبق راهنما فایل config.example.php را به config.php کپی و اطلاعات دیتابیس جدید را وارد کنید.</p></html>');}
$config=require $configFile;
require_once __DIR__.'/operations.php';configure_error_logging();
// Use one origin before rendering forms or issuing session cookies. Never trust
// forwarded headers: hosting proxies must preserve the configured public Host.
$site=parse_url((string)config('app_url'));
if(!$site||!in_array($site['scheme']??'', ['http','https'],true)||empty($site['host'])||!empty($site['user'])||!empty($site['query'])||!empty($site['fragment'])||!in_array($site['path']??'', ['', '/'],true)){
 http_response_code(503);exit('app_url must be the full site origin, without a subdirectory.');
}
$expected=strtolower($site['host'].(isset($site['port'])?':'.$site['port']:''));
if(strtolower($_SERVER['HTTP_HOST']??'')!==$expected){
 if(in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){
  $request=$_SERVER['REQUEST_URI']??'/';
  if(!str_starts_with($request,'/')||preg_match('/[\r\n]/',$request))$request='/';
  header('Location: '.rtrim(config('app_url'),'/').$request,true,302);exit;
 }
 http_response_code(403);header('Content-Type: text/html; charset=utf-8');exit('<html lang="fa" dir="rtl"><p>فرم را از آدرس اصلی فروشگاه باز کنید.</p><a href="'.h(config('app_url')).'">بازکردن فروشگاه</a></html>');
}
$nonce=base64_encode(random_bytes(24));
$secure=parse_url(config('app_url'),PHP_URL_SCHEME)==='https';
header_remove('X-Powered-By');
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self' https://sandbox.zarinpal.com https://www.zarinpal.com; frame-ancestors 'none'");
if($secure)header('Strict-Transport-Security: max-age=31536000');
header('Cache-Control: private, no-store');
$storage=config('storage');
if((!is_dir($storage.'/sessions')&&!@mkdir($storage.'/sessions',0700,true))||!is_writable($storage.'/sessions')){
 http_response_code(503);error_log('Naylex: session directory unavailable');exit('<html lang="fa" dir="rtl"><p>ذخیره نشست فروشگاه ممکن نیست. مدیر هاست باید دسترسی نوشتن پوشه خصوصی storage/sessions را اصلاح کند.</p></html>');
}
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_save_path($storage.'/sessions');session_name('naylex_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
if(!session_start()){http_response_code(503);exit('Session storage unavailable.');}
expire_cart_if_stale(time());
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
// Also mark redirects and error responses on private routes, not only rendered pages.
if(preg_match('#^/(?:admin(?:-2fa)?|account|login|register|cart|checkout|track|forgot-password|wholesale-panel|install|api)(?:/|$)#',$path))header('X-Robots-Tag: noindex, nofollow');
try {
 require __DIR__.'/cart-preview.php';require __DIR__.'/payment.php';require __DIR__.'/uploads.php';
 if($path==='/api/search') {require __DIR__.'/search.php';serve_product_search();}
 if($path==='/media') {serve_image();exit;}
 if($path==='/install') {require __DIR__.'/install.php';exit;}
 // A dedicated marker allows a useful setup page without executing schema on normal requests.
 if(!is_file($storage.'/installed.lock')){http_response_code(503);exit('<html lang="fa" dir="rtl"><h1>نصب اولیه لازم است</h1><p>راهنمای نصب را دنبال کنید و سپس <a href="/install">نصب فروشگاه</a> را باز کنید.</p></html>');}
 require_once __DIR__.'/content.php';require_once __DIR__.'/reviews.php';require_once __DIR__.'/product-features.php';require_once __DIR__.'/analytics.php';require_once __DIR__.'/two-factor.php';require_once __DIR__.'/slug-redirects.php';ensure_content_schema();ensure_review_schema();ensure_product_feature_schema();ensure_analytics_schema();ensure_two_factor_schema();ensure_slug_redirect_schema();
 require __DIR__.'/actions.php';
 if($_SERVER['REQUEST_METHOD']==='POST'){if((int)($_SERVER['CONTENT_LENGTH']??0)>6*1024*1024)throw new ShopError('درخواست بیش از حد بزرگ است.',413);verify_csrf();handle_action();}
 if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true))throw new ShopError('روش درخواست مجاز نیست.',405);
 if($path==='/api/payment/callback'){payment_callback();exit;}
 require __DIR__.'/views.php';require __DIR__.'/pages.php';
 render_route($path);
}catch(Throwable $e){
 if(db_if_open_transaction())db()->rollBack();
 $message=$e instanceof ShopError?$e->getMessage():'عملیات انجام نشد. اتصال دیتابیس و گزارش خطای سرور را بررسی کنید.';
 if(!$e instanceof ShopError){error_log('Naylex error: '.get_class($e).' '.$e->getMessage());operational_log('http_5xx',['path'=>$path,'type'=>get_class($e)]);}
 $status=$e instanceof ShopError?$e->status:500;
 http_response_code($status);
 header('X-Robots-Tag: noindex, nofollow');
 if($e instanceof ShopError&&$path==='/admin-2fa'&&($_SERVER['HTTP_ACCEPT']??'')!=='application/json'){
  require_once __DIR__.'/views.php';require_once __DIR__.'/pages.php';flash($message,'error');two_factor_login_page();exit;
 }
 if($e instanceof ShopError&&in_array($path,['/login','/register','/wholesale'],true)&&($_SERVER['HTTP_ACCEPT']??'')!=='application/json'){
  require_once __DIR__.'/views.php';require_once __DIR__.'/pages.php';flash($message,'error');auth_page($path!=='/login');exit;
 }
 if($path==='/admin/upload'||($_SERVER['HTTP_ACCEPT']??'')==='application/json'){header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message],JSON_UNESCAPED_UNICODE);}
 else {echo '<html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="robots" content="noindex"><link rel="stylesheet" href="/assets/shop.css"><div class="container page-content"><div class="panel"><h1>عملیات انجام نشد</h1><p>'.h($message).'</p><a class="btn" href="'.h(str_starts_with($path,'/admin')?'/admin':'/').'">بازگشت</a></div></div></html>';}
}
function db_if_open_transaction(): bool {try{return db()->inTransaction();}catch(Throwable){return false;}}
