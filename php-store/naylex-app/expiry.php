<?php
declare(strict_types=1);

// Called inside an order-row transaction, including late callbacks.
function record_verified_payment(array $order, string $reference): string {
 if(in_array($order['status'],['PAID','SHIPPED','DELIVERED'],true))return $order['status'];
 $status='PAID';
 if(in_array($order['status'],['EXPIRED','PAYMENT_REVIEW','CANCELLED'],true)){
  $items=all('SELECT * FROM ns_order_items WHERE order_id=? ORDER BY product_id',[$order['id']]);
  foreach($items as $item){$p=one('SELECT stock FROM ns_products WHERE id=? FOR UPDATE',[$item['product_id']]);if(!$p||(int)$p['stock']<(int)$item['quantity'])$status='PAYMENT_REVIEW';}
  if($status==='PAID')foreach($items as $item)query('UPDATE ns_products SET stock=stock-?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[$item['quantity'],$item['product_id']]);
 }
 query('UPDATE ns_orders SET status=?,reference=? WHERE id=?',[$status,$reference,$order['id']]);
 return $status;
}

function verify_order_payment(int $id, ?callable $transport=null): string {
 $transport??='gateway';
 return transaction(function()use($id,$transport){
  $o=one('SELECT * FROM ns_orders WHERE id=? FOR UPDATE',[$id]);
  if(!$o||!$o['authority'])throw new ShopError('سفارش برای بررسی پرداخت مناسب نیست.');
  if(in_array($o['status'],['PAID','SHIPPED','DELIVERED'],true))return $o['status'];
  $r=$transport('verify',['authority'=>$o['authority'],'amount'=>(int)$o['total']*10]);
  return record_verified_payment($o,(string)$r['ref_id']);
 });
}

function expire_order(int $id, ?callable $transport=null): string {
 $transport??='gateway';
 $minutes=max(5,min(10080,(int)(config('reservation_minutes')??30)));
 return transaction(function()use($id,$transport,$minutes){
  // The same lock serializes expiry, payment callback and admin cancellation.
  $o=one('SELECT *,created_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE) AS overdue FROM ns_orders WHERE id=? FOR UPDATE',[$minutes,$id]);
  if(!$o||$o['status']!=='PENDING'||!$o['overdue'])return 'skipped';
  if($o['authority']){
   $r=$transport('inquiry',['authority'=>$o['authority']]);
   if(in_array($r['status']??'', ['PAID','VERIFIED'],true)){
    $v=$transport('verify',['authority'=>$o['authority'],'amount'=>(int)$o['total']*10]);
    record_verified_payment($o,(string)$v['ref_id']);return 'paid';
   }
   if(!in_array($r['status']??'', ['FAILED','REVERSED'],true))return 'deferred';
  }
  foreach(all('SELECT * FROM ns_order_items WHERE order_id=? ORDER BY product_id',[$id]) as $i)query('UPDATE ns_products SET stock=stock+?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[$i['quantity'],$i['product_id']]);
  query("UPDATE ns_orders SET status='EXPIRED' WHERE id=?",[$id]);
  return 'released';
 });
}
