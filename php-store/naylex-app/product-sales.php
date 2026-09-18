<?php
declare(strict_types=1);
function ensure_product_sales_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_product_sales (product_id BIGINT UNSIGNED PRIMARY KEY, sale_type VARCHAR(10) NOT NULL DEFAULT 'retail', wholesale_id BIGINT UNSIGNED NULL, INDEX(wholesale_id), FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE, FOREIGN KEY(wholesale_id) REFERENCES ns_products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $ready=true;
}
function is_wholesale_product(array $p): bool {
 return isset($p['id'])&&product_sales((int)$p['id'])['sale_type']==='wholesale';
}
function product_sales(int $id): array {
 static $rows=null;
 if($rows===null){$rows=[];foreach(all('SELECT * FROM ns_product_sales') as $r)$rows[(int)$r['product_id']]=$r;}
 return $rows[$id]??['sale_type'=>'retail','wholesale_id'=>null];
}
function linked_wholesale(array $p): ?array {
 $sales=product_sales((int)$p['id']);
 if($sales['sale_type']!=='retail'||!$sales['wholesale_id'])return null;
 return one("SELECT p.* FROM ns_products p JOIN ns_product_sales s ON s.product_id=p.id WHERE p.id=? AND p.active=1 AND s.sale_type='wholesale'",[$sales['wholesale_id']]);
}
function wholesale_recommendation(array $p,int $qty): void {
 $target=linked_wholesale($p);if(!$target)return;
 ?><aside class="wholesale-recommendation" data-wholesale-recommendation data-product-id="<?=(int)$p['id']?>" data-threshold="<?=(int)$p['minimum']?>" <?=$qty<(int)$p['minimum']?'hidden':''?> aria-live="polite"><strong>این مقدار برای خرید عمده مناسب است</strong><p>از <?=money($p['minimum'])?> <?=h($p['unit'])?>، محصول عمدهٔ مرتبط را بررسی کنید: <?=h($target['name'])?>.</p><p>قیمت عمده: <strong><?=money($target['wholesale'])?> تومان</strong> برای هر <?=h($target['unit'])?></p><a class="btn outline" href="/products/<?=h($target['slug'])?>">مشاهده و خرید محصول عمده</a><?php if(!(int)$target['stock']):?><p>محصول عمده فعلاً ناموجود است.</p><?php endif;?></aside><?php
}
function sales_navigation(): void { ?><nav class="buy-row sales-navigation" aria-label="نوع خرید"><a class="btn outline" href="/products">خرید خرده</a><a class="btn outline" href="/wholesale">خرید عمده</a></nav><?php }
function product_sales_hint(array $p): void {
 if(is_wholesale_product($p)){echo 'قیمت عمده برای همهٔ مشتریان؛ بدون نیاز به ورود یا حساب همکاری.';return;}
 if(linked_wholesale($p)){echo 'از '.money($p['minimum']).' '.h($p['unit']).'، محصول عمدهٔ مرتبط به شما پیشنهاد می‌شود.';}
 else {echo 'قیمت عمده '.money($p['wholesale']).' تومان از '.money($p['minimum']).' '.h($p['unit']).'؛ تخفیف خودکار در سبد اعمال می‌شود.';}
}
function product_sales_editor(array $p): void {
 $sales=product_sales((int)$p['id']);
 if(!$p['id']&&($_GET['sale_type']??'')==='wholesale')$sales['sale_type']='wholesale';
 $options=all("SELECT p.id,p.name,p.slug,p.active,p.wholesale,p.unit FROM ns_products p JOIN ns_product_sales s ON s.product_id=p.id WHERE s.sale_type='wholesale' AND p.id<>? ORDER BY p.active DESC,p.name",[$p['id']]);
 ?><fieldset class="feature-editor"><legend>نوع فروش و اتصال محصول خرده به عمده</legend>
 <label>نوع فروش<select name="sale_type"><option value="retail" <?=$sales['sale_type']==='retail'?'selected':''?>>خرده</option><option value="wholesale" <?=$sales['sale_type']==='wholesale'?'selected':''?>>عمده</option></select></label>
 <label>محصول عمدهٔ همین کالا<select name="wholesale_id"><option value="0">محصول عمده را انتخاب کنید</option><?php foreach($options as $option):?><option value="<?=$option['id']?>" <?=(int)$sales['wholesale_id']===(int)$option['id']?'selected':''?>><?=h($option['name'])?> — <?=money($option['wholesale'])?> تومان / <?=h($option['unit'])?><?=$option['active']?'':' (غیرفعال)'?></option><?php endforeach;?></select></label>
 <?php field('تعداد شروع پیشنهاد عمده (مثلاً ۲۵ کیسه)','minimum',$p['minimum'],'number');?>
 <p>برای هر محصول خرده، محصول عمدهٔ همان کالا را انتخاب کنید. با رسیدن تعداد سفارش به این عدد یا بیشتر، پیشنهاد خرید و لینک صفحهٔ آن محصول با قیمت عمده نمایش داده می‌شود. این عدد بر اساس واحد فروش محصول خرده است. برای محصولی با نوع «عمده»، انتخاب محصول مرتبط لازم نیست.</p>
 <a class="text-link" href="/admin?tab=products&amp;edit=0&amp;sale_type=wholesale" target="_blank" rel="noopener">ساخت محصول عمده در برگهٔ جدید</a>
 <small>پس از ذخیرهٔ محصول عمده، فرم خرده را دوباره باز کنید تا محصول جدید در فهرست باشد؛ اطلاعات ذخیره‌نشده را ابتدا یادداشت کنید.</small>
 <?php if($sales['wholesale_id']):foreach($options as $option):if((int)$option['id']!==(int)$sales['wholesale_id'])continue;?><p><a href="/products/<?=h($option['slug'])?>" target="_blank" rel="noopener">مشاهدهٔ محصول عمدهٔ متصل</a> · <a href="/admin?tab=products&amp;edit=<?=(int)$option['id']?>" target="_blank" rel="noopener">ویرایش محصول عمده</a></p><?php endforeach;endif;?></fieldset><?php
}
