<?php
declare(strict_types=1);
require_once __DIR__.'/expiry.php';
function gateway(string $action,array $payload): array {
 if(!in_array($action,['request','verify','inquiry'],true))throw new LogicException('Invalid gateway action');
 if(!config('merchant_id'))throw new ShopError('درگاه پرداخت هنوز فعال نشده است؛ مرچنت را در تنظیمات سرور وارد کنید.');
 $base=config('sandbox')?'https://sandbox.zarinpal.com':'https://api.zarinpal.com';
 $ch=curl_init($base.'/pg/v4/payment/'.$action.'.json');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['merchant_id'=>config('merchant_id')]+$payload)]);
 $response=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($response===false||$status!==200)throw new ShopError('ارتباط با درگاه برقرار نشد؛ دوباره تلاش کنید.');
 return validate_gateway_response($action,json_decode($response,true));
}
function validate_gateway_response(string $action,mixed $response): array {
 $data=is_array($response)?($response['data']??[]):[];
 if(!is_array($data))throw new ShopError('پاسخ درگاه معتبر نیست.');
 if(!in_array($data['code']??0,$action==='request'?[100]:[100,101],true))throw new ShopError('درگاه پرداخت را تأیید نکرد.');
 if($action==='request'&&!preg_match('/^A[a-zA-Z0-9]{35}$/',$data['authority']??''))throw new ShopError('پاسخ درگاه معتبر نیست.');
 if($action==='verify'&&(!ctype_digit((string)($data['ref_id']??''))||(string)$data['ref_id']==='0'))throw new ShopError('شناسه پرداخت معتبر نیست.');
 if($action==='inquiry'&&!in_array($data['status']??'', ['PAID','VERIFIED','IN_BANK','FAILED','REVERSED'],true))throw new ShopError('وضعیت پرداخت نامعتبر است.');
 return $data;
}
function payment_url(string $authority): string {return (config('sandbox')?'https://sandbox.zarinpal.com':'https://www.zarinpal.com').'/pg/StartPay/'.rawurlencode($authority);}
function release_order(int $id): void {
 transaction(function()use($id){$o=one('SELECT * FROM ns_orders WHERE id=? FOR UPDATE',[$id]);if(!$o||$o['status']!=='PENDING'||$o['authority']!==null)return;
 query("UPDATE ns_orders SET status='CANCELLED' WHERE id=?",[$id]);
 foreach(all('SELECT * FROM ns_order_items WHERE order_id=?',[$id]) as $i)query('UPDATE ns_products SET stock=stock+?,version=version+1 WHERE id=?',[$i['quantity'],$i['product_id']]);});
}
function checkout(): never {
 $u=current_user();$name=text_input('name',3,120);$phone=phone_input();$address=text_input('address',15,1000);$key=text_input('checkout_key',64,64);
 if(!preg_match('/^[a-f0-9]{64}$/',$key)||!hash_equals($_SESSION['checkout_key']??'', $key))throw new ShopError('شناسه سبد معتبر نیست؛ صفحه تسویه را تازه کنید.');
 rate_limit('checkout-global',80);rate_limit('checkout-phone:'.$phone,8);
 $cart=$_SESSION['cart']??[];ksort($cart,SORT_NUMERIC);if(!$cart||count($cart)>100)throw new ShopError('سبد خرید خالی یا نامعتبر است.');
 $fingerprint=hash('sha256',json_encode([$name,$phone,$address,$cart,$u['id']??null],JSON_UNESCAPED_UNICODE));
 $old=one('SELECT * FROM ns_orders WHERE checkout_key=?',[$key]);
 if($old){if(!hash_equals($old['request_hash'],$fingerprint))throw new ShopError('اطلاعات سفارش تغییر کرده؛ از سبد خرید دوباره وارد تسویه شوید.');if($old['status']==='PENDING'&&$old['authority'])redirect(payment_url($old['authority']));redirect('/track?code='.$old['code']);}
 if(!config('merchant_id'))throw new ShopError('درگاه هنوز توسط مدیر فعال نشده است.');
 $order=transaction(function()use($u,$cart,$name,$phone,$address,$key,$fingerprint){$lines=[];$sum=0;
  foreach($cart as $id=>$qty){$qty=(int)$qty;if($qty<1||$qty>1000)throw new ShopError('تعداد خرید نامعتبر است.');$p=one('SELECT * FROM ns_products WHERE id=? FOR UPDATE',[$id]);if(!$p||!$p['active']||(int)$p['stock']<$qty)throw new ShopError('موجودی یکی از محصولات کافی نیست.');$price=unit_price($p,$qty,($u['wholesale_status']??'')==='APPROVED');$lines[]=[$p,$qty,$price];$sum+=$price*$qty;}
  $shipping=shipping_cost($sum,settings());$total=$sum+$shipping;if($total>200000000)throw new ShopError('مبلغ سفارش بیش از سقف مجاز است.');
  $code='NS-'.strtoupper(bin2hex(random_bytes(10)));query('INSERT INTO ns_orders(code,user_id,name,phone,address,total,shipping,checkout_key,request_hash) VALUES (?,?,?,?,?,?,?,?,?)',[$code,$u['id']??null,$name,$phone,$address,$total,$shipping,$key,$fingerprint]);$id=(int)db()->lastInsertId();
  foreach($lines as [$p,$qty,$price]){query('INSERT INTO ns_order_items(order_id,product_id,name,quantity,price) VALUES (?,?,?,?,?)',[$id,$p['id'],$p['name'],$qty,$price]);query('UPDATE ns_products SET stock=stock-?,version=version+1 WHERE id=?',[$qty,$p['id']]);}
  return ['id'=>$id,'code'=>$code,'total'=>$total];
 });
 $_SESSION['last_order']=$order['code'];
 try{$r=gateway('request',['amount'=>$order['total']*10,'callback_url'=>url('/api/payment/callback'),'description'=>'سفارش '.$order['code'],'metadata'=>['mobile'=>$phone]]);if(query("UPDATE ns_orders SET authority=? WHERE id=? AND status='PENDING'",[$r['authority'],$order['id']])->rowCount()!==1)throw new ShopError('مهلت سفارش پایان یافته است؛ سفارش جدید ثبت کنید.');redirect(payment_url($r['authority']));}
 catch(Throwable $e){release_order($order['id']);unset($_SESSION['checkout_key']);throw $e;}
}
function payment_callback(): never {
 $authority=$_GET['Authority']??'';if(!is_string($authority)||!preg_match('/^A[a-zA-Z0-9]{35}$/',$authority))redirect('/track?payment=invalid');
 rate_limit('verify:'.$authority,15);$o=one('SELECT * FROM ns_orders WHERE authority=?',[$authority]);if(!$o)redirect('/track?payment=invalid');
 $result='failed';
 if(in_array($o['status'],['PAID','SHIPPED','DELIVERED'],true))$result='success';
 else{try{$state=verify_order_payment((int)$o['id']);$result=$state==='PAYMENT_REVIEW'?'review':'success';}catch(Throwable){$result='retry';}}
 if($result==='success'&&($_SESSION['last_order']??'')===$o['code']){unset($_SESSION['cart'],$_SESSION['checkout_key']);}
 redirect('/track?code='.$o['code'].'&payment='.$result);
}
