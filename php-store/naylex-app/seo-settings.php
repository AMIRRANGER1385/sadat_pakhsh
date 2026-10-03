<?php
declare(strict_types=1);
function seo_admin(): void {
 seo_readiness_panel();
 action_start('seo_settings','panel form-stack','/admin');?><h2>سئو و اتصال به گوگل</h2><p>عنوان و توضیح هر صفحه باید دقیق و مرتبط با محصولات واقعی باشد. خالی گذاشتن فیلد، متن پیش‌فرض سایت را حفظ می‌کند.</p><?php
 foreach(['home'=>'صفحه اصلی','wholesale'=>'خرید عمده پلاستیک و نایلکس','about'=>'درباره ما'] as$key=>$label){echo '<h3>'.h($label).'</h3>';field('عنوان صفحه','seo_'.$key.'_title',content_text('seo.'.$key.'.title',''),'text',false);textarea_field('توضیح نتیجه جستجو','seo_'.$key.'_description',content_text('seo.'.$key.'.description',''));}
 field('کد تأیید Google Search Console (فقط مقدار content)','google_verification',content_text('seo.google_verification',''),'text',false);
 ?><button class="btn">ذخیره تنظیمات سئو</button><p>پس از انتشار سایت، دامنه را در <a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Google Search Console</a> تأیید کنید و نشانی زیر را در بخش Sitemaps ثبت کنید:</p><p dir="ltr"><?=h(url('/sitemap.xml'))?></p><p>صفحه اصلی و صفحه خرید عمده را با URL Inspection بررسی و درخواست ایندکس کنید. گزارش Page indexing علت احتمالی نمایش‌ندادن صفحات را مشخص می‌کند. این تنظیمات تضمین رتبه یا زمان نمایش در گوگل نیست.</p></form><?php
}
function seo_readiness_panel(): void {
 $products=all('SELECT p.id,p.name,p.description,p.image,s.seo_title,s.meta_description FROM ns_products p LEFT JOIN ns_product_seo s ON s.product_id=p.id WHERE p.active=1 ORDER BY p.id');
 $issues=[];$titles=[];$descriptions=[];
 foreach($products as$p){
  $notes=[];if(!available_image($p['image']))$notes[]='عکس محصول موجود نیست؛ عکس واقعی بارگذاری کنید.';
  if(mb_strlen(trim($p['description']))<80)$notes[]='توضیح محصول کوتاه است؛ مشخصات و کاربرد واقعی را تکمیل کنید.';
  if(trim($p['seo_title']??'')!=='')$titles[trim($p['seo_title'])][]=$p;
  if(trim($p['meta_description']??'')!=='')$descriptions[trim($p['meta_description'])][]=$p;
  if($notes)$issues[]=['product'=>$p,'notes'=>$notes];
 }
 foreach(['عنوان سئو'=>$titles,'توضیح نتیجه جستجو'=>$descriptions] as$label=>$groups)foreach($groups as$group)if(count($group)>1)foreach($group as$p)$issues[]=['product'=>$p,'notes'=>[$label.' با محصول دیگری یکسان است؛ متن اختصاصی بنویسید.']];
 ?><section class="panel"><h2>بازبینی محتوای سئو</h2><p>این بررسی روی اطلاعات همین نصب اجرا می‌شود و وضعیت ایندکس یا رتبه در گوگل را اندازه نمی‌گیرد. کوتاه‌بودن متن صرفاً نشانه‌ای برای بازبینی است.</p><p><a href="/sitemap.xml" target="_blank" rel="noopener">نقشه سایت</a> · <a href="/robots.txt" target="_blank" rel="noopener">دسترسی خزنده‌ها</a> · <a href="/admin?tab=quality">کامل‌بودن اطلاعات فروشگاه</a></p><?php
 if(!$issues)echo '<p>در کنترل تصاویر، طول اولیه توضیحات و تکرار متادیتای اختصاصی، موردی پیدا نشد.</p>';
 foreach($issues as$issue){echo '<h3><a href="/admin?tab=products&amp;edit='.(int)$issue['product']['id'].'">'.h($issue['product']['name']).'</a></h3><ul>';foreach($issue['notes'] as$note)echo '<li>'.h($note).'</li>';echo '</ul>';}
 ?><p>برای تکمیل شرایط خرید و مرجوعی، از <a href="/admin?tab=terms">بخش ضوابط و شرایط سایت</a> استفاده کنید. صفحات دارای متن موقت تا تکمیل محتوا وارد نقشه سایت نمی‌شوند.</p></section><?php
}
function save_seo_settings(): void {
 $values=[];foreach(['home','wholesale','about'] as$key){$values['seo.'.$key.'.title']=text_input('seo_'.$key.'_title',0,150);$values['seo.'.$key.'.description']=text_input('seo_'.$key.'_description',0,350);}
 $token=text_input('google_verification',0,200);if($token!==''&&!preg_match('/^[a-zA-Z0-9_-]+$/D',$token))throw new ShopError('فقط کد content تگ تأیید گوگل را وارد کنید.');$values['seo.google_verification']=$token;
 ensure_content_schema();transaction(function()use($values){foreach($values as$key=>$value)query('INSERT INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)',[$key,'تنظیمات سئو',$value]);});
 content_cache_clear();audit_admin('SEO_UPDATE','site');flash('تنظیمات سئو ذخیره شد.');redirect('/admin?tab=seo');
}
