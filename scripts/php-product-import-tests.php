<?php
require dirname(__DIR__).'/php-store/naylex-app/core.php';
require dirname(__DIR__).'/php-store/naylex-app/uploads.php';
require dirname(__DIR__).'/php-store/naylex-app/product-import.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)exit(1);
ensure_product_sales_schema();
if(($argv[1]??'')==='cleanup'&&preg_match('/^article-test-[a-f0-9]{16}$/',$argv[2]??'')){query('DELETE FROM ns_products WHERE slug IN (?,?)',[$argv[2].'-retail',$argv[2].'-bulk']);exit;}
function check_import(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function import_rejects(callable $f): bool {try{$f();return false;}catch(ShopError){return true;}}
$rows=product_import_rows(dirname(__DIR__).'/php-store/public_html/templates/products.xlsx','xlsx');
check_import(count($rows)===1,'XLSX template parsed');
check_import($rows===product_import_rows(dirname(__DIR__).'/php-store/public_html/templates/products.csv','csv'),'XLSX and BOM CSV contents match');
$prefix='import-test-'.bin2hex(random_bytes(5));$r=$rows[0];$r['retail_slug']=$prefix;$r['wholesale_slug']=$prefix.'-bulk';$r['category']='ImportTestCategory-'.$prefix;
try{
 $preview=product_import_validate([$r],'create');$result=product_import_apply($preview,'create');check_import($result['created']===2,'one row creates two products');
 $retail=one('SELECT * FROM ns_products WHERE slug=?',[$prefix]);$bulk=one('SELECT * FROM ns_products WHERE slug=?',[$prefix.'-bulk']);
 check_import((int)one('SELECT wholesale_id FROM ns_product_sales WHERE product_id=?',[$retail['id']])['wholesale_id']===(int)$bulk['id'],'pair linked automatically');
 check_import(unit_price($retail,24)===120000&&unit_price($retail,25)===95000&&unit_price($bulk,1)===95000,'server prices at 24 and 25 and wholesale');
 check_import(import_rejects(fn()=>product_import_validate([$r],'create')),'duplicate create rejected');
 $r['retail_price']='130000';$preview=product_import_validate([$r],'update');$result=product_import_apply($preview,'update');check_import($result['updated']===2,'existing pair updated without duplicates');
 $preview=product_import_validate([$r],'update');query('UPDATE ns_products SET version=version+1 WHERE id=?',[$retail['id']]);check_import(import_rejects(fn()=>product_import_apply($preview,'update')),'concurrent change rejected');
 $invalid=$r;$invalid['wholesale_price']='999999';check_import(import_rejects(fn()=>product_import_validate([$invalid],'update')),'invalid pricing rejected');
 $r2=$r;$r2['retail_slug']=$prefix.'-new';$r2['wholesale_slug']=$prefix.'-new-bulk';$preview=product_import_validate([$r2,$r],'update');$preview[1]['retail_price']='0';check_import(import_rejects(fn()=>product_import_apply($preview,'update')),'invalid later row rejects batch');check_import(!one('SELECT id FROM ns_products WHERE slug=?',[$r2['retail_slug']]),'batch writes no partial products');
 check_import(import_rejects(fn()=>product_import_validate([$r,$r],'update')),'duplicate keys within file rejected');
 check_import(import_rejects(fn()=>product_import_xml('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>')),'XML external entity declarations rejected');
}finally{query('DELETE FROM ns_products WHERE slug LIKE ?',[$prefix.'%']);query('DELETE FROM ns_categories WHERE name=?',[$r['category']]);}
