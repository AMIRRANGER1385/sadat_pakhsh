<?php
declare(strict_types=1);
function ensure_product_sales_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_product_sales (product_id BIGINT UNSIGNED PRIMARY KEY, sale_type VARCHAR(10) NOT NULL DEFAULT 'retail', wholesale_id BIGINT UNSIGNED NULL, INDEX(wholesale_id), FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE, FOREIGN KEY(wholesale_id) REFERENCES ns_products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query("CREATE TABLE IF NOT EXISTS ns_wholesale_price_tiers (product_id BIGINT UNSIGNED PRIMARY KEY, tier_2_min SMALLINT UNSIGNED NOT NULL DEFAULT 5, tier_3_min SMALLINT UNSIGNED NOT NULL DEFAULT 15, tier_4_min SMALLINT UNSIGNED NOT NULL DEFAULT 25, price_1 BIGINT UNSIGNED NOT NULL, price_2 BIGINT UNSIGNED NOT NULL, price_3 BIGINT UNSIGNED NOT NULL, price_4 BIGINT UNSIGNED NOT NULL, FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query("UPDATE ns_products p JOIN ns_product_sales s ON s.product_id=p.id SET p.unit='کیلوگرم',p.minimum=25 WHERE s.sale_type='wholesale' AND (p.unit<>'کیلوگرم' OR p.minimum<>25)");
 query("CREATE TABLE IF NOT EXISTS ns_product_price_updates (product_id BIGINT UNSIGNED PRIMARY KEY,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query('INSERT IGNORE INTO ns_product_price_updates(product_id,updated_at) SELECT id,updated_at FROM ns_products');
 $ready=true;
}
function wholesale_price_tiers(array $p): array {
 $row=!empty($p['id'])?one('SELECT * FROM ns_wholesale_price_tiers WHERE product_id=?',[(int)$p['id']]):null;
 if($row)return array_map('intval',$row);
 $base=max(1,(int)($p['wholesale']??1));
 return ['product_id'=>(int)($p['id']??0),'tier_2_min'=>5,'tier_3_min'=>15,'tier_4_min'=>25,'price_1'=>$base,'price_2'=>$base,'price_3'=>$base,'price_4'=>$base];
}
function wholesale_tier_for_quantity(array $p,int $qty): array {
 $tiers=wholesale_price_tiers($p);$qty=max(1,$qty);
 if($qty>=$tiers['tier_4_min'])return ['price'=>$tiers['price_4'],'min'=>$tiers['tier_4_min'],'max'=>null,'level'=>4];
 if($qty>=$tiers['tier_3_min'])return ['price'=>$tiers['price_3'],'min'=>$tiers['tier_3_min'],'max'=>$tiers['tier_4_min']-1,'level'=>3];
 if($qty>=$tiers['tier_2_min'])return ['price'=>$tiers['price_2'],'min'=>$tiers['tier_2_min'],'max'=>$tiers['tier_3_min']-1,'level'=>2];
 return ['price'=>$tiers['price_1'],'min'=>1,'max'=>$tiers['tier_2_min']-1,'level'=>1];
}
function save_wholesale_price_tiers(int $productId,array $values): void {
 [$min2,$min3,$min4,$price1,$price2,$price3,$price4]=$values;
 if(!($min2>=2&&$min2<$min3&&$min3<$min4&&$min4<=1000))throw new ShopError('مرز بازه‌های قیمت عمده باید به‌ترتیب صعودی و حداکثر ۱۰۰۰ باشد.');
 if(min($price1,$price2,$price3,$price4)<1)throw new ShopError('قیمت همه بازه‌های عمده باید بیشتر از صفر باشد.');
 query('INSERT INTO ns_wholesale_price_tiers(product_id,tier_2_min,tier_3_min,tier_4_min,price_1,price_2,price_3,price_4) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tier_2_min=VALUES(tier_2_min),tier_3_min=VALUES(tier_3_min),tier_4_min=VALUES(tier_4_min),price_1=VALUES(price_1),price_2=VALUES(price_2),price_3=VALUES(price_3),price_4=VALUES(price_4)',[$productId,$min2,$min3,$min4,$price1,$price2,$price3,$price4]);
}
function wholesale_price_table(array $p): void {
 if(!is_wholesale_product($p))return;$t=wholesale_price_tiers($p);$rows=[[1,$t['tier_2_min']-1,$t['price_1']],[$t['tier_2_min'],$t['tier_3_min']-1,$t['price_2']],[$t['tier_3_min'],$t['tier_4_min']-1,$t['price_3']],[$t['tier_4_min'],null,$t['price_4']]];?>
 <section class="panel wholesale-tier-table"><h2>جدول قیمت عمده هر کیلو</h2><p>قیمت هر کیلو بر اساس وزن انتخاب‌شده محاسبه می‌شود.</p><div class="table-scroll"><table><thead><tr><th>بازه سفارش</th><th>قیمت به‌ازای هر کیلو</th></tr></thead><tbody><?php foreach($rows as [$min,$max,$price]):?><tr><td><?=$max?money($min).' تا '.money($max).' کیلو':money($min).' کیلو و بیشتر'?></td><td><strong><?=money($price)?> تومان</strong></td></tr><?php endforeach;?></tbody></table></div></section><?php
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
 $months=[1=>'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];return money($jd).' '.$months[$jm].' '.strtr((string)$jy,['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
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
 ?><aside class="wholesale-recommendation" data-wholesale-recommendation data-product-id="<?=(int)$p['id']?>" data-threshold="<?=(int)$p['minimum']?>" <?=$qty<(int)$p['minimum']?'hidden':''?> aria-live="polite"><strong>خرید عمده را هم مقایسه کنید</strong><div class="wholesale-choice"><img src="<?=h(display_image($target['image']))?>" alt="<?=h($target['name'])?>" width="100" height="100" loading="lazy"><div><p><?=h($target['name'])?></p><p>قیمت عمده: <strong><?=money($target['wholesale'])?> تومان</strong> برای هر <?=h($target['unit'])?></p><a class="btn outline" href="/products/<?=h($target['slug'])?>">انتخاب و خرید محصول عمده</a></div></div><p>تعداد شما به <?=money($p['minimum'])?> <?=h($p['unit'])?> رسیده است. می‌توانید خرید فعلی را نگه دارید یا محصول عمده را انتخاب کنید؛ سبد خودکار جایگزین نمی‌شود.</p><?php if(!(int)$target['stock']):?><p>محصول عمده فعلاً ناموجود است.</p><?php endif;?></aside><?php
}
function sales_navigation(): void { ?><nav class="buy-row sales-navigation" aria-label="نوع خرید"><a class="btn outline" href="/products">فروش خرد</a><a class="btn outline" href="/wholesale">فروش عمده</a></nav><?php }
function product_sales_hint(array $p): void {
 if(is_wholesale_product($p)){echo 'قیمت عمده برای همهٔ مشتریان؛ بدون نیاز به ورود یا حساب همکاری.';return;}
 if(linked_wholesale($p)){echo 'از '.money($p['minimum']).' '.h($p['unit']).'، محصول عمدهٔ مرتبط به شما پیشنهاد می‌شود.';}
 else {echo 'قیمت عمده '.money($p['wholesale']).' تومان از '.money($p['minimum']).' '.h($p['unit']).'؛ تخفیف خودکار در سبد اعمال می‌شود.';}
}
function product_sales_editor(array $p): void {
 $sales=product_sales((int)$p['id']);
 if(!$p['id']&&($_GET['sale_type']??'')==='wholesale')$sales['sale_type']='wholesale';
 $options=all("SELECT p.id,p.name,p.slug,p.active,p.wholesale,p.unit FROM ns_products p JOIN ns_product_sales s ON s.product_id=p.id WHERE s.sale_type='wholesale' AND p.id<>? ORDER BY p.active DESC,p.name",[$p['id']]);
 $tiers=wholesale_price_tiers($p);
 ?><fieldset class="feature-editor"><legend>نوع فروش و اتصال محصول خرده به عمده</legend>
 <label>نوع فروش<select name="sale_type" data-sale-type><option value="retail" <?=$sales['sale_type']==='retail'?'selected':''?>>خرده</option><option value="wholesale" <?=$sales['sale_type']==='wholesale'?'selected':''?>>عمده</option></select></label>
 <div data-sale-section="wholesale" <?=$sales['sale_type']==='wholesale'?'':'hidden'?>><?php hidden('wholesale_id',0);hidden('minimum',25);?><p class="notice">واحد فروش محصول عمده «کیلوگرم» و مقدار پیشنهادی اولیه در صفحه محصول ۲۵ کیلو است.</p><div class="wholesale-tier-editor" data-wholesale-tier-editor><h4>چهار بازه قیمت محصول عمده</h4><p>مرز شروع و قیمت هر کیلو را تعیین کنید. بازه چهارم به‌صورت پیش‌فرض از ۲۵ کیلوگرم شروع می‌شود.</p><div class="form-grid"><?php field('قیمت هر کیلو از ۱ تا پیش از بازه دوم','tier_price_1',$tiers['price_1'],'number');field('شروع بازه دوم (کیلو)','tier_2_min',$tiers['tier_2_min'],'number');field('قیمت هر کیلو در بازه دوم','tier_price_2',$tiers['price_2'],'number');field('شروع بازه سوم (کیلو)','tier_3_min',$tiers['tier_3_min'],'number');field('قیمت هر کیلو در بازه سوم','tier_price_3',$tiers['price_3'],'number');field('شروع بازه چهارم (کیلو)','tier_4_min',$tiers['tier_4_min'],'number');field('قیمت هر کیلو در بازه چهارم و بالاتر','tier_price_4',$tiers['price_4'],'number');?></div></div></div>
 <div data-sale-section="retail" <?=$sales['sale_type']==='retail'?'':'hidden'?>><label>محصول عمدهٔ همین کالا<select name="wholesale_id"><option value="0">محصول عمده را انتخاب کنید</option><?php foreach($options as $option):?><option value="<?=$option['id']?>" <?=(int)$sales['wholesale_id']===(int)$option['id']?'selected':''?>><?=h($option['name'])?> — <?=money($option['wholesale'])?> تومان / <?=h($option['unit'])?><?=$option['active']?'':' (غیرفعال)'?></option><?php endforeach;?></select></label>
 <?php field('وزن شروع پیشنهاد عمده (۲۵ کیلوگرم)','minimum',$p['minimum']?:25,'number');?></div>
 <p>برای هر محصول خرده، محصول عمدهٔ همان کالا را انتخاب کنید. با رسیدن تعداد سفارش به این عدد یا بیشتر، پیشنهاد خرید و لینک صفحهٔ آن محصول با قیمت عمده نمایش داده می‌شود. این عدد بر اساس واحد فروش محصول خرده است. برای محصولی با نوع «عمده»، انتخاب محصول مرتبط لازم نیست.</p>
 <a class="text-link" href="/admin?tab=products&amp;edit=0&amp;sale_type=wholesale" target="_blank" rel="noopener">ساخت محصول عمده در برگهٔ جدید</a>
 <small>پس از ذخیرهٔ محصول عمده، فرم خرده را دوباره باز کنید تا محصول جدید در فهرست باشد؛ اطلاعات ذخیره‌نشده را ابتدا یادداشت کنید.</small>
 <?php if($sales['wholesale_id']):foreach($options as $option):if((int)$option['id']!==(int)$sales['wholesale_id'])continue;?><p><a href="/products/<?=h($option['slug'])?>" target="_blank" rel="noopener">مشاهدهٔ محصول عمدهٔ متصل</a> · <a href="/admin?tab=products&amp;edit=<?=(int)$option['id']?>" target="_blank" rel="noopener">ویرایش محصول عمده</a></p><?php endforeach;endif;?></fieldset><?php
}
