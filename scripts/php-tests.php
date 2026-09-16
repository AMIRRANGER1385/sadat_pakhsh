<?php
declare(strict_types=1);
require dirname(__DIR__).'/php-store/naylex-app/core.php';
require dirname(__DIR__).'/php-store/naylex-app/payment.php';
require dirname(__DIR__).'/php-store/naylex-app/uploads.php';
require dirname(__DIR__).'/php-store/naylex-app/search-match.php';
require dirname(__DIR__).'/php-store/naylex-app/two-factor.php';
require dirname(__DIR__).'/php-store/naylex-app/slug-redirects.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Only the isolated local test DB is allowed.');
$count=0;
function check_test(string $name,bool $ok): void {global $count;if(!$ok)throw new RuntimeException($name);$count++;echo "PASS $name\n";}
function rejects(callable $fn): bool {try{$fn();return false;}catch(ShopError){return true;}}
$p=['retail'=>100000,'wholesale'=>80000,'minimum'=>10];
check_test('wholesale threshold exact',unit_price($p,9)===100000&&unit_price($p,10)===80000);
check_test('account status never bypasses wholesale threshold',unit_price($p,1,true)===100000);
check_test('100-unit threshold uses retail at 99 and wholesale at 100',unit_price(array_replace($p,['minimum'=>100]),99)===100000&&unit_price(array_replace($p,['minimum'=>100]),100)===80000);
$commercialTerms=['پلاستیک سادات','پخش پلاستیکک','بازرگانی سادات پخش','فروش عمده','فروش پلاستیک','فروش نایلکس'];
foreach($commercialTerms as $term)check_test("commercial search synonym: $term",search_products($term,6)!==[]);
$totpSecret=base32_encode_bytes(random_bytes(20));$totpCounter=intdiv(time(),30);$totp=totp_code($totpSecret,$totpCounter);
check_test('TOTP accepts current one-time code',verify_totp($totpSecret,$totp,$totpCounter*30,$totpCounter-1)===$totpCounter);
check_test('TOTP replay counter is rejected',verify_totp($totpSecret,$totp,$totpCounter*30,$totpCounter)===null);
$cipher=encrypt_two_factor_secret($totpSecret);check_test('2FA secret encrypted at rest',!str_contains($cipher,$totpSecret)&&decrypt_two_factor_secret($cipher)===$totpSecret);
ensure_slug_redirect_schema();$product=one('SELECT id,slug FROM ns_products WHERE active=1 ORDER BY id LIMIT 1');$oldSlug='unit-old-'.bin2hex(random_bytes(6));remember_slug('product',$oldSlug,(int)$product['id']);check_test('old product slug resolves to current slug',product_slug_redirect($oldSlug)===$product['slug']);query("DELETE FROM ns_slug_redirects WHERE entity_type='product' AND old_slug=?",[$oldSlug]);
$uploadDir=rtrim((string)config('storage'),'/\\').'/uploads';if(!is_dir($uploadDir))mkdir($uploadDir,0700,true);$orphan=$uploadDir.'/'.bin2hex(random_bytes(24)).'.webp';file_put_contents($orphan,'orphan-test');touch($orphan,time()-90000);$cleanup=cleanup_unused_uploads(86400,500);check_test('orphan upload cleanup removes old unreferenced file',$cleanup['deleted']>=1&&!is_file($orphan));
check_test('shipping boundary and disabled threshold',shipping_cost(999,['shipping'=>45,'free_above'=>1000])===45&&shipping_cost(1000,['shipping'=>45,'free_above'=>1000])===0&&shipping_cost(1000,['shipping'=>45,'free_above'=>0])===45);
check_test('XSS escaped',!str_contains(h('<script>alert(1)</script>'),'<script>'));
check_test('Persian number normalization',digits('۰۹۱۲۳۴۵۶۷۸۹')==='09123456789');
$_POST=['password'=>str_repeat('ا',37)];check_test('bcrypt byte limit enforced',rejects(fn()=>password_input('password')));
$_POST=['password'=>'1234567'];check_test('password minimum rejects 7 characters',rejects(fn()=>password_input('password')));
$_POST=['password'=>'12345678'];check_test('password minimum accepts 8 characters',password_input('password')==='12345678');
$_POST=['password'=>str_repeat('a',72)];check_test('bcrypt valid boundary',strlen(password_input('password'))===72);
check_test('unsafe image path rejected',!valid_image('/media?name=../../config.php')&&!valid_image('javascript:alert(1)'));
check_test('payment request valid',validate_gateway_response('request',['data'=>['code'=>100,'authority'=>'A'.str_repeat('1',35)]])['code']===100);
check_test('payment malicious authority rejected',rejects(fn()=>validate_gateway_response('request',['data'=>['code'=>100,'authority'=>'https://evil.test']])));
check_test('request cannot use already-verified code',rejects(fn()=>validate_gateway_response('request',['data'=>['code'=>101,'authority'=>'A'.str_repeat('1',35)]])));
check_test('verify requires reference',rejects(fn()=>validate_gateway_response('verify',['data'=>['code'=>100,'ref_id'=>0]])));
check_test('repeated verify response accepted',validate_gateway_response('verify',['data'=>['code'=>101,'ref_id'=>12345]])['ref_id']===12345);
$p=one('SELECT * FROM ns_products ORDER BY id LIMIT 1');$stock=(int)$p['stock'];
try{transaction(function()use($p){query('UPDATE ns_products SET stock=stock-1 WHERE id=?',[$p['id']]);throw new ShopError('force rollback');});}catch(ShopError){}
check_test('transaction rollback preserves stock',(int)one('SELECT stock FROM ns_products WHERE id=?',[$p['id']])['stock']===$stock);
$key=bin2hex(random_bytes(32));rate_limit('unit:'.$key,1);check_test('rate limit enforced',rejects(fn()=>rate_limit('unit:'.$key,1)));
$password='Long-local-password-2026';$hash=password_hash($password,PASSWORD_BCRYPT,['cost'=>12]);check_test('password securely verified',password_verify($password,$hash)&&!password_verify('wrong',$hash));
$_SESSION=['cart'=>[1=>2],'cart_updated_at'=>100,'checkout_key'=>'x'];check_test('cart expires after 24 inactive hours',expire_cart_if_stale(86501)&&empty($_SESSION['cart'])&&!isset($_SESSION['checkout_key']));
$_SESSION=['cart'=>[1=>2],'cart_updated_at'=>100];check_test('fresh cart remains available',!expire_cart_if_stale(86500)&&$_SESSION['cart'][1]===2);
echo "All $count PHP/MySQL checks passed.\n";
