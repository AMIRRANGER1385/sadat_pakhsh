<?php
require dirname(__DIR__).'/php-store/naylex-app/core.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Isolated test database only.');
ensure_product_sales_schema();
$prefix='public-wholesale-test-';
if(($argv[1]??'')==='cleanup'){
 query("DELETE FROM ns_products WHERE slug LIKE ?",[$prefix.'%']);exit;
}
$category=(int)query('SELECT id FROM ns_categories LIMIT 1')->fetchColumn();
$result=[];
foreach([['retail',100,80],['wholesale',100,80],['wholesale',200,60]] as $i=>[$type,$retail,$wholesale]){
 $slug=$prefix.bin2hex(random_bytes(5));
 query('INSERT INTO ns_products(name,slug,description,image,retail,wholesale,minimum,stock,unit,category_id) VALUES (?,?,?,?,?,?,?,?,?,?)',['PublicWholesaleFixture '.$i,$slug,'Public wholesale browser test product','/products/product-1.svg',$retail,$wholesale,25,100,'کیسه',$category]);
 $id=(int)db()->lastInsertId();query('INSERT INTO ns_product_sales(product_id,sale_type) VALUES (?,?)',[$id,$type]);
 $result[]=['id'=>$id,'slug'=>$slug,'type'=>$type,'price'=>$wholesale,'category'=>$category];
}
echo json_encode($result);
