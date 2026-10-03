<?php
declare(strict_types=1);
require dirname(__DIR__).'/php-store/naylex-app/core.php';
require dirname(__DIR__).'/php-store/naylex-app/uploads.php';
require dirname(__DIR__).'/php-store/naylex-app/product-import.php';
require dirname(__DIR__).'/php-store/naylex-app/catalog-xlsx.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local')throw new RuntimeException('Local test database required.');
ensure_product_sales_schema();
$prefix='catalog-test-'.bin2hex(random_bytes(5));
$base=['slug'=>$prefix,'sale_type'=>'retail','wholesale_slug'=>$prefix.'-bulk','name'=>'محصول آزمایشی','category'=>'CatalogTest-'.$prefix,'retail_price'=>'130000','wholesale_price'=>'95000','threshold'=>'25','stock'=>'20','unit'=>'کیسه','description'=>'شرح آزمایشی','image'=>'','featured'=>'0'];
$bulk=$base;$bulk['slug']=$prefix.'-bulk';$bulk['sale_type']='wholesale';$bulk['wholesale_slug']='';$bulk['name']='محصول آزمایشی عمده';
try{
 $preview=product_import_validate([$base,$bulk],'update');$created=product_import_apply($preview,'update');if($created['created']!==2)throw new RuntimeException('Create failed');
 $all=product_catalog_export_rows();$export=array_values(array_filter($all,fn($r)=>str_starts_with($r['slug'],$prefix)));if(count($export)!==2)throw new RuntimeException('Export omitted products');
 $file=__DIR__.'/'.$prefix.'.csv';file_put_contents($file,product_catalog_csv($export));try{$parsed=product_import_rows($file,'csv');}finally{unlink($file);}
 if(count($parsed)!==2||$parsed[0]['slug']!==$prefix)throw new RuntimeException('CSV round trip failed');
 $parsed[0]['retail_price']='140000';$before=(int)one('SELECT id FROM ns_products WHERE slug=?',[$prefix])['id'];$preview=product_import_validate($parsed,'update');$result=product_import_apply($preview,'update');$after=one('SELECT id,retail FROM ns_products WHERE slug=?',[$prefix]);
 if($result['updated']!==2||(int)$after['id']!==$before||(int)$after['retail']!==140000)throw new RuntimeException('Update created duplicate or missed price');
 $linked=one('SELECT s.wholesale_id FROM ns_product_sales s WHERE s.product_id=?',[$before]);$bulkId=one('SELECT id FROM ns_products WHERE slug=?',[$prefix.'-bulk']);if((int)$linked['wholesale_id']!==(int)$bulkId['id'])throw new RuntimeException('Wholesale link lost');
 if((int)one('SELECT COUNT(*) n FROM ns_products WHERE slug LIKE ?',[$prefix.'%'])['n']!==2)throw new RuntimeException('Duplicate products');
 $xlsx=__DIR__.'/'.$prefix.'.xlsx';file_put_contents($xlsx,product_catalog_xlsx($export));try{$roundtrip=product_import_rows($xlsx,'xlsx');$expected=array_map(fn($row)=>array_map(fn($v)=>(string)$v,$row),$export);if($roundtrip!==$expected)throw new RuntimeException('XLSX round trip changed values');}finally{unlink($xlsx);}
 $renamed=$base;$renamed['slug']=$prefix.'-changed';$renamed['retail_price']='150000';$preview=product_import_validate([$renamed],'update');$result=product_import_apply($preview,'update');if($result['updated']!==1||$result['created']!==0)throw new RuntimeException('Name matching created a duplicate');
 $duplicate=$base;$duplicate['slug']=$prefix.'-duplicate';if(!one('SELECT id FROM ns_products WHERE slug=?',[$duplicate['slug']]))query('INSERT INTO ns_products(slug,name,description,image,retail,wholesale,minimum,stock,unit,category_id,featured) SELECT ?,name,description,image,retail,wholesale,minimum,stock,unit,category_id,featured FROM ns_products WHERE id=?',[$duplicate['slug'],$before]);
 try{product_import_validate([$renamed],'update');throw new RuntimeException('Ambiguous name accepted');}catch(ShopError $e){}
 echo "PASS catalog CSV/XLSX round trips, name matching, ambiguity rejection and stable product IDs\n";
}finally{query('DELETE FROM ns_products WHERE slug LIKE ?',[$prefix.'%']);query('DELETE FROM ns_categories WHERE name=?',['CatalogTest-'.$prefix]);}
