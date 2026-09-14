<?php
declare(strict_types=1);
require dirname(__DIR__).'/php-store/naylex-app/core.php';
require dirname(__DIR__).'/php-store/naylex-app/payment.php';
require dirname(__DIR__).'/php-store/naylex-app/uploads.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Only the isolated local test DB is allowed.');
$count=0;
function check_test(string $name,bool $ok): void {global $count;if(!$ok)throw new RuntimeException($name);$count++;echo "PASS $name\n";}
function rejects(callable $fn): bool {try{$fn();return false;}catch(ShopError){return true;}}
$p=['retail'=>100000,'wholesale'=>80000,'minimum'=>10];
check_test('wholesale threshold exact',unit_price($p,9)===100000&&unit_price($p,10)===80000);
check_test('approved account bypasses threshold',unit_price($p,1,true)===80000);
check_test('shipping boundary and disabled threshold',shipping_cost(999,['shipping'=>45,'free_above'=>1000])===45&&shipping_cost(1000,['shipping'=>45,'free_above'=>1000])===0&&shipping_cost(1000,['shipping'=>45,'free_above'=>0])===45);
check_test('XSS escaped',!str_contains(h('<script>alert(1)</script>'),'<script>'));
check_test('Persian number normalization',digits('۰۹۱۲۳۴۵۶۷۸۹')==='09123456789');
$_POST=['password'=>str_repeat('ا',37)];check_test('bcrypt byte limit enforced',rejects(fn()=>password_input('password')));
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
echo "All $count PHP/MySQL checks passed.\n";
