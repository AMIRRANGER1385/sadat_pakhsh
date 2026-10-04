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
$bulk=$base;$bulk['slug']=$prefix.'-bulk';$bulk['sale_type']='wholesale';$bulk['wholesale_slug']='';$bulk['name']='محصول آزمایشی عمده';$bulk+=['tier_2_min'=>'5','tier_3_min'=>'15','tier_4_min'=>'25','tier_price_1'=>'110000','tier_price_2'=>'100000','tier_price_3'=>'90000','tier_price_4'=>'80000'];
try{
 $preview=product_catalog_validate([$bulk,$base],'update');$created=product_catalog_apply($preview,'update');if($created['created']!==2)throw new RuntimeException('Create failed');
 $all=product_catalog_export_rows();$export=array_values(array_filter($all,fn($r)=>str_starts_with($r['slug'],$prefix)));if(count($export)!==2)throw new RuntimeException('Export omitted products');
 $file=__DIR__.'/'.$prefix.'.csv';file_put_contents($file,product_catalog_csv($export));try{$parsed=product_import_rows($file,'csv');}finally{unlink($file);}if(count($parsed)!==2)throw new RuntimeException('CSV round trip failed');
 $xlsx=__DIR__.'/'.$prefix.'.xlsx';file_put_contents($xlsx,product_catalog_xlsx($export));try{$roundtrip=product_import_rows($xlsx,'xlsx');$expected=array_map(fn($row)=>array_map(fn($v)=>(string)$v,$row),$export);if($roundtrip!==$expected)throw new RuntimeException('XLSX round trip changed values');}finally{unlink($xlsx);}
 $retailIndex=array_search($prefix,array_column($parsed,'slug'),true);$parsed[$retailIndex]['retail_price']='140000';$before=(int)one('SELECT id FROM ns_products WHERE slug=?',[$prefix])['id'];$result=product_catalog_apply(product_catalog_validate($parsed,'update'),'update');$after=one('SELECT id,retail FROM ns_products WHERE slug=?',[$prefix]);if($result['updated']!==2||(int)$after['id']!==$before||(int)$after['retail']!==140000)throw new RuntimeException('Update created duplicate or missed price');
 $linked=one('SELECT s.wholesale_id FROM ns_product_sales s WHERE s.product_id=?',[$before]);$bulkProduct=one('SELECT p.* FROM ns_products p WHERE p.slug=?',[$prefix.'-bulk']);if((int)$linked['wholesale_id']!==(int)$bulkProduct['id'])throw new RuntimeException('Wholesale link lost');
 if($bulkProduct['unit']!=='کیلوگرم'||(int)$bulkProduct['minimum']!==25||unit_price($bulkProduct,1)!==110000||unit_price($bulkProduct,5)!==100000||unit_price($bulkProduct,15)!==90000||unit_price($bulkProduct,25)!==80000)throw new RuntimeException('Wholesale tiers or kilogram normalization lost');
 $bad=$bulk;$bad['tier_3_min']='4';try{product_catalog_validate([$bad],'update');throw new RuntimeException('Invalid tier boundaries accepted');}catch(ShopError){}
 $bad=$base;$bad['tier_price_1']='1';try{product_catalog_validate([$bad],'update');throw new RuntimeException('Retail tier values accepted');}catch(ShopError){}
 if((int)one('SELECT COUNT(*) n FROM ns_products WHERE slug LIKE ?',[$prefix.'%'])['n']!==2)throw new RuntimeException('Duplicate products');
 echo "PASS catalog CSV/XLSX tier round trips, linked ordering, validation and stable product IDs\n";
}finally{query('DELETE FROM ns_products WHERE slug LIKE ?',[$prefix.'%']);query('DELETE FROM ns_categories WHERE name=?',['CatalogTest-'.$prefix]);}
