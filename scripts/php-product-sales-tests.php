<?php
declare(strict_types=1);
require dirname(__DIR__).'/php-store/naylex-app/core.php';
require_once dirname(__DIR__).'/php-store/naylex-app/product-sales.php';
$config=require dirname(__DIR__).'/php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Only isolated local DB allowed.');
function expect_sales(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
ensure_product_sales_schema();ensure_product_sales_schema();
db()->beginTransaction();
try {
 $category=query('SELECT id FROM ns_categories LIMIT 1')->fetchColumn();
 $ids=[];
 foreach(['retail','wholesale'] as $type){
  query('INSERT INTO ns_products(name,slug,description,image,retail,wholesale,minimum,stock,unit,category_id) VALUES (?,?,?,?,?,?,?,?,?,?)',[$type,'sales-test-'.bin2hex(random_bytes(8)),'Sales test product','/products/product-1.svg',100,80,25,100,'bag',$category]);$ids[]=(int)db()->lastInsertId();
 }
 query("INSERT INTO ns_product_sales(product_id,sale_type,wholesale_id) VALUES (?,'wholesale',NULL),(?,'retail',?)",[$ids[1],$ids[0],$ids[1]]);
 $p=one('SELECT * FROM ns_products WHERE id=?',[$ids[0]]);
 $bulk=one('SELECT * FROM ns_products WHERE id=?',[$ids[1]]);
 expect_sales(unit_price($bulk,1)===80&&unit_price($bulk,25)===80,'wholesale price applies to all quantities without an account');
 expect_sales(unit_price($p,1)===100&&unit_price($p,25)===80,'retail threshold pricing preserved');
 expect_sales((int)linked_wholesale($p)['id']===$ids[1],'separate wholesale product resolves');
 foreach([24=>true,25=>false,26=>false,0=>true] as $qty=>$hidden){ob_start();wholesale_recommendation($p,$qty);$html=ob_get_clean();expect_sales(str_contains($html,' hidden ')===$hidden,'threshold visibility '.$qty);}
 expect_sales(linked_wholesale(one('SELECT * FROM ns_products WHERE id=?',[$ids[1]]))===null,'wholesale product does not link back');
 query('UPDATE ns_products SET stock=0 WHERE id=?',[$ids[1]]);expect_sales(linked_wholesale($p)!==null,'out of stock target remains viewable');
 query('UPDATE ns_products SET active=0 WHERE id=?',[$ids[1]]);expect_sales(linked_wholesale($p)===null,'inactive target suppressed');
expect_sales(product_sales(0)['sale_type']==='retail','legacy products default to retail');
 expect_sales(gregorian_to_jalali(2026,9,19)===[1405,6,28],'Persian price date conversion');
 $before=latest_price_update();mark_product_price_updated($ids[0]);expect_sales(latest_price_update()>=$before,'price update timestamp recorded');
} finally {db()->rollBack();}
