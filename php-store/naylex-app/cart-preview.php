<?php
declare(strict_types=1);
function cart_preview_data(): array {
 $items=[];$total=0;
 foreach(cart_lines() as $p){if(!empty($p['missing']))continue;$line=(int)$p['price']*(int)$p['quantity'];$total+=$line;
 $items[]=['id'=>(int)$p['id'],'name'=>$p['name'],'image'=>$p['image'],'description'=>mb_substr($p['description'],0,100),'quantity'=>(int)$p['quantity'],'price'=>(int)$p['price'],'unit'=>$p['unit'],'wholesale'=>is_wholesale_product($p)||(int)$p['quantity']>=(int)$p['minimum'],'total'=>$line];}
 return ['items'=>$items,'count'=>array_sum($_SESSION['cart']??[]),'total'=>$total];
}
function cart_preview(): void {?>
 <div class="cart-preview-wrap"><a class="cart-link" href="/cart" aria-label="سبد خرید"><?=icon('cart')?><b data-cart-count><?=money(array_sum($_SESSION['cart']??[]))?></b></a><button type="button" class="cart-preview-toggle" aria-expanded="false" aria-controls="cart-preview">نمایش سبد</button><section id="cart-preview" class="cart-preview" aria-label="پیش‌نمایش سبد خرید"><h3>سبد خرید شما</h3><div data-cart-items></div><p>جمع محصولات: <strong data-cart-total></strong> تومان</p><small>هزینه ارسال در صفحه سبد محاسبه می‌شود.</small><a class="btn" href="/cart">مشاهده و تکمیل سبد خرید</a></section></div>
 <?php global $nonce;?><script type="application/json" id="cart-initial" nonce="<?=h($nonce)?>"><?=json_encode(cart_preview_data(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?></script><?php
}
