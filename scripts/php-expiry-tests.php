<?php
declare(strict_types=1);
require __DIR__.'/../php-store/naylex-app/core.php';
require __DIR__.'/../php-store/naylex-app/payment.php';
$config=require __DIR__.'/../php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)throw new RuntimeException('Local test DB only');
$ids=[];$pid=null;$count=0;
function test_expiry(bool $ok,string $label): void {global $count;if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;$count++;}
function fixture(bool $authority=true,bool $old=true): int {
 global $ids,$pid;
 $key=bin2hex(random_bytes(32));
 query('INSERT INTO ns_orders(code,name,phone,address,total,shipping,checkout_key,request_hash,authority,created_at) VALUES (?,?,?,?,?,?,?,?,?,?)',['NS-'.strtoupper(bin2hex(random_bytes(10))),'Expiry test','09120000000','Local test only',100,0,$key,$key,$authority?'A'.bin2hex(random_bytes(17)).'0':null,gmdate('Y-m-d H:i:s',time()-($old?3600:0))]);
 $id=(int)db()->lastInsertId();$ids[]=$id;
 query('INSERT INTO ns_order_items(order_id,product_id,name,quantity,price) VALUES (?,?,?,?,?)',[$id,$pid,'Expiry test',2,50]);
 query('UPDATE ns_products SET stock=stock-2 WHERE id=?',[$pid]);return $id;
}
function stock_test():int {global $pid;return (int)one('SELECT stock FROM ns_products WHERE id=?',[$pid])['stock'];}
try{
 $source=one('SELECT * FROM ns_products LIMIT 1');
 query('INSERT INTO ns_products(slug,name,description,image,retail,wholesale,minimum,stock,unit,category_id) VALUES (?,?,?,?,?,?,?,?,?,?)',['expiry-'.bin2hex(random_bytes(8)),'Expiry fixture','Local only',$source['image'],100,80,10,100,'عدد',$source['category_id']]);$pid=(int)db()->lastInsertId();
 $fail=fn()=>['status'=>'FAILED'];$paid=fn($action)=>$action==='inquiry'?['status'=>'PAID']:['ref_id'=>'123456'];
 $id=fixture(false);$before=stock_test();test_expiry(expire_order($id)==='released'&&stock_test()===$before+2,'abandoned order releases stock');
 test_expiry(expire_order($id)==='skipped'&&stock_test()===$before+2,'repeat does not double release');
 $id=fixture();$before=stock_test();test_expiry(expire_order($id,$fail)==='released'&&stock_test()===$before+2,'failed gateway releases');
 test_expiry(verify_order_payment($id,$paid)==='PAID'&&stock_test()===$before,'late payment reserves stock again');
 test_expiry(verify_order_payment($id,$paid)==='PAID'&&stock_test()===$before,'duplicate callback no stock change');
 $id=fixture();$before=stock_test();test_expiry(expire_order($id,$paid)==='paid'&&stock_test()===$before,'paid abandoned callback reconciled');
 $id=fixture();$before=stock_test();test_expiry(expire_order($id,fn()=>['status'=>'IN_BANK'])==='deferred'&&stock_test()===$before,'in bank remains reserved');
 try{expire_order($id,fn()=>throw new RuntimeException('timeout'));}catch(RuntimeException){}
 test_expiry(stock_test()===$before&&one('SELECT status FROM ns_orders WHERE id=?',[$id])['status']==='PENDING','network failure preserves reservation');
 test_expiry(expire_order(fixture(false,false))==='skipped','fresh order remains reserved');
 $id=fixture();expire_order($id,$fail);query('UPDATE ns_products SET stock=0 WHERE id=?',[$pid]);
 test_expiry(verify_order_payment($id,$paid)==='PAYMENT_REVIEW'&&stock_test()===0,'late payment without stock flagged');
 query('UPDATE ns_products SET stock=2 WHERE id=?',[$pid]);test_expiry(verify_order_payment($id,$paid)==='PAID'&&stock_test()===0,'review resolved after restock');
 test_expiry(validate_gateway_response('inquiry',['data'=>['code'=>100,'status'=>'FAILED']])['status']==='FAILED','inquiry response validated');
 echo "All $count expiry checks passed.\n";
}finally{foreach($ids as $id){query('DELETE FROM ns_order_items WHERE order_id=?',[$id]);query('DELETE FROM ns_orders WHERE id=?',[$id]);}if($pid)query('DELETE FROM ns_products WHERE id=?',[$pid]);}
