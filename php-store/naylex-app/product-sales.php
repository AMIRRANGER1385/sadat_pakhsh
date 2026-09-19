<?php
declare(strict_types=1);
function ensure_product_sales_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_product_sales (product_id BIGINT UNSIGNED PRIMARY KEY, sale_type VARCHAR(10) NOT NULL DEFAULT 'retail', wholesale_id BIGINT UNSIGNED NULL, INDEX(wholesale_id), FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE, FOREIGN KEY(wholesale_id) REFERENCES ns_products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query("CREATE TABLE IF NOT EXISTS ns_product_price_updates (product_id BIGINT UNSIGNED PRIMARY KEY,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query('INSERT IGNORE INTO ns_product_price_updates(product_id,updated_at) SELECT id,updated_at FROM ns_products');
 $ready=true;
}
function mark_product_price_updated(int $productId): void {query('INSERT INTO ns_product_price_updates(product_id,updated_at) VALUES (?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE updated_at=UTC_TIMESTAMP()',[$productId]);}
function latest_price_update(): ?string {return query('SELECT MAX(u.updated_at) FROM ns_product_price_updates u JOIN ns_products p ON p.id=u.product_id WHERE p.active=1')->fetchColumn()?:null;}
function gregorian_to_jalali(int $gy,int $gm,int $gd): array {
 $gdm=[31,28,31,30,31,30,31,31,30,31,30,31];$gy-=1600;$gm--;$gd--;
 $days=365*$gy+intdiv($gy+3,4)-intdiv($gy+99,100)+intdiv($gy+399,400);
 for($i=0;$i<$gm;$i++)$days+=$gdm[$i];if($gm>1&&($gy%4===0&&$gy%100!==0||$gy%400===0))$days++;$days+=$gd-79;
 $cycle=intdiv($days,12053);$days%=12053;$jy=979+33*$cycle+4*intdiv($days,1461);$days%=1461;
 if($days>=366){$jy+=intdiv($days-1,365);$days=($days-1)%365;}
 if($days<186){$jm=1+intdiv($days,31);$jd=1+$days%31;}else{$jm=7+intdiv($days-186,30);$jd=1+($days-186)%30;}
 return [$jy,$jm,$jd];
}
function persian_price_update_date(?string $value): string {
 if(!$value)return 'هنوز ثبت نشده';$date=new DateTimeImmutable($value,new DateTimeZone('UTC'));[$jy,$jm,$jd]=gregorian_to_jalali((int)$date->format('Y'),(int)$date->format('n'),(int)$date->format('j'));
 $months=[1=>'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];return money($jd).' '.$months[$jm].' '.money($jy);
}
function price_update_badge(): void {$updated=latest_price_update();?><div class="price-updated" role="status"><span>آخرین به‌روزرسانی قیمت‌ها</span><strong><?php if($updated):?><time datetime="<?=h(gmdate('Y-m-d',strtotime($updated)))?>"><?=h(persian_price_update_date($updated))?></time><?php else:?>هنوز ثبت نشده<?php endif;?></strong></div><?php }
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
 if(is_wholesale_product($p))return;
 $target=linked_wholesale($p);
 if(!$target){?><aside class="wholesale-recommendation" data-wholesale-recommendation data-product-id="<?=(int)$p['id']?>" data-threshold="<?=(int)$p['minimum']?>" <?=$qty<(int)$p['minimum']?'hidden':''?> aria-live="polite"><strong>خرید با نرخ عمدهٔ همین کالا</strong><p>از <?=money($p['minimum'])?> <?=h($p['unit'])?>، قیمت هر واحد <?=money($p['wholesale'])?> تومان در سبد محاسبه می‌شود.</p><a href="/wholesale" class="btn outline">مقایسه با محصولات عمده</a></aside><?php return;}
 ?><aside class="wholesale-recommendation" data-wholesale-recommendation data-product-id="<?=(int)$p['id']?>" data-threshold="<?=(int)$p['minimum']?>" <?=$qty<(int)$p['minimum']?'hidden':''?> aria-live="polite"><strong>خرید عمده را هم مقایسه کنید</strong><div class="wholesale-choice"><img src="<?=h($target['image'])?>" alt="<?=h($target['name'])?>" width="100" height="100" loading="lazy"><div><p><?=h($target['name'])?></p><p>قیمت عمده: <strong><?=money($target['wholesale'])?> تومان</strong> برای هر <?=h($target['unit'])?></p><a class="btn outline" href="/products/<?=h($target['slug'])?>">انتخاب و خرید محصول عمده</a></div></div><p>تعداد شما به <?=money($p['minimum'])?> <?=h($p['unit'])?> رسیده است. می‌توانید خرید فعلی را نگه دارید یا محصول عمده را انتخاب کنید؛ سبد خودکار جایگزین نمی‌شود.</p><?php if(!(int)$target['stock']):?><p>محصول عمده فعلاً ناموجود است.</p><?php endif;?></aside><?php
}
function sales_navigation(): void { ?><nav class="buy-row sales-navigation" aria-label="نوع خرید"><a class="btn outline" href="/products">محصولات پلاستیکی</a><a class="btn outline" href="/wholesale">محصولات عمده</a></nav><?php }
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
