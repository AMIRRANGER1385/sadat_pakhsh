<?php
declare(strict_types=1);
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
check_import(count($rows)===2,'new XLSX template parsed');
check_import($rows===product_import_rows(dirname(__DIR__).'/php-store/public_html/templates/products.csv','csv'),'XLSX and BOM CSV contents match');
$prefix='import-test-'.bin2hex(random_bytes(5));
$rows[0]['slug']=$prefix;$rows[0]['wholesale_slug']=$prefix.'-bulk';$rows[0]['category']='ImportTestCategory-'.$prefix;
$rows[1]['slug']=$prefix.'-bulk';$rows[1]['category']=$rows[0]['category'];
try{
 $preview=product_catalog_validate($rows,'create');$result=product_catalog_apply($preview,'create');check_import($result['created']===2,'two rows create linked retail and wholesale products');
 $retail=one('SELECT * FROM ns_products WHERE slug=?',[$prefix]);$bulk=one('SELECT * FROM ns_products WHERE slug=?',[$prefix.'-bulk']);
 check_import((int)one('SELECT wholesale_id FROM ns_product_sales WHERE product_id=?',[$retail['id']])['wholesale_id']===(int)$bulk['id'],'pair linked automatically');
 check_import($bulk['unit']==='کیلوگرم'&&(int)$bulk['minimum']===25,'wholesale unit and default quantity normalized');
 check_import(unit_price($bulk,1)===110000&&unit_price($bulk,5)===100000&&unit_price($bulk,15)===90000&&unit_price($bulk,25)===80000,'all four wholesale tiers applied');
 check_import(import_rejects(fn()=>product_catalog_validate($rows,'create')),'duplicate create rejected');
 $rows[1]['tier_price_3']='85000';$preview=product_catalog_validate($rows,'update');$result=product_catalog_apply($preview,'update');$bulk=one('SELECT * FROM ns_products WHERE slug=?',[$prefix.'-bulk']);check_import($result['updated']===2&&unit_price($bulk,15)===85000,'tier update preserves product IDs');
 $bad=$rows;$bad[1]['tier_3_min']='4';check_import(import_rejects(fn()=>product_catalog_validate($bad,'update')),'invalid tier boundaries rejected');
 $bad=$rows;$bad[0]['tier_price_1']='1';check_import(import_rejects(fn()=>product_catalog_validate($bad,'update')),'retail tier values rejected');
 $legacy=array_combine(product_import_headers(),[$prefix.'-legacy',$prefix.'-legacy-bulk','کالای قالب قدیمی',$rows[0]['category'],120000,90000,25,10,20,'کیسه','شرح قالب قدیمی','',0]);
 $legacyPreview=product_import_validate([$legacy],'create');check_import(count($legacyPreview)===1,'legacy paired 13-column format remains accepted');
 check_import(import_rejects(fn()=>product_import_xml('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>')),'XML external entity declarations rejected');
}finally{query('DELETE FROM ns_products WHERE slug LIKE ?',[$prefix.'%']);query('DELETE FROM ns_categories WHERE name=?',[$rows[0]['category']]);}
