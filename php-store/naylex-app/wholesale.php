<?php
declare(strict_types=1);
function wholesale_terms(): void { ?>
 <section class="panel seo-content"><h2>سه روش خرید با قیمت مناسب‌تر</h2>
 <h3>قیمت تعدادی عمومی</h3><p>حداقل خرید عمده برای هر محصول جداگانه تعیین می‌شود. پیش‌فرض محصولات جدید ۵۰ کیلوگرم است؛ برای کالای بسته‌ای یا عددی، مقدار درج‌شده در واحد همان محصول ملاک است. جمع کالاهای متفاوت با یکدیگر محاسبه نمی‌شود.</p>
 <h3>قیمت حساب همکار</h3><p>حساب همکاری پس از بررسی مدیر تأیید می‌شود و قیمت عمده همان محصول را بدون شرط حداقل تعداد دریافت می‌کند. درصد تخفیف ثابت نیست؛ اختلاف قیمت خرده و همکاری هر کالا در پنل فروش عمده مشخص است.</p>
 <h3>سفارش عمده ویژه</h3><p>سفارش‌های بالای ۱۰۰ کیلوگرم را برای بررسی شرایط ویژه با پشتیبانی هماهنگ کنید. تخفیف اضافه خودکار یا تضمین‌شده نیست و باید پیش از پرداخت تأیید شود. این استعلام مستقل از آستانه قیمت تعدادی هر محصول است.</p></section>
<?php }
function wholesale_landing(): void {
 page_header('فروش عمده نایلون و نایلکس | درخواست همکاری','خرید عمده نایلکس برای سوپرمارکت‌ها، فروشگاه‌ها، رستوران‌ها و مراکز پخش؛ شرایط حساب همکار و قیمت هر محصول.',true,url('/wholesale'));
 $u=current_user();
 ?><div class="container page-content"><h1>خرید عمده نایلون و نایلکس برای کسب‌وکار شما</h1><p class="seo-intro">تأمین نایلکس، کیسه فریزر، کیسه زباله و محصولات بسته‌بندی برای سوپرمارکت‌ها، فروشگاه‌ها، عمده‌فروش‌ها، رستوران‌ها و مراکز پخش.</p><?php wholesale_terms(); ?>
 <section class="panel seo-content"><h2>مزایای حساب همکاری</h2><p>دسترسی به پنل اختصاصی فروش عمده، مقایسه قیمت خرده و همکاری، ثبت سفارش و پیگیری خریدها از یک حساب.</p><h2>تأیید، ارسال و تسویه</h2><p>درخواست شما توسط مدیر بررسی می‌شود؛ برای تکمیل اطلاعات کسب‌وکار ممکن است با شما تماس گرفته شود. نتیجه در حساب شما نمایش داده می‌شود. تسویه آنلاین از درگاه انجام می‌شود.</p>
 <?php if(!$u):?><a class="btn" href="/register?account=wholesale">درخواست همکاری</a> <a class="text-link" href="/login">ورود به حساب موجود</a>
 <?php elseif($u['wholesale_status']==='APPROVED'):?><a class="btn" href="/wholesale-panel">ورود به پنل فروش عمده</a>
 <?php elseif($u['wholesale_status']==='PENDING'):?><p role="status">درخواست همکاری شما در انتظار بررسی مدیر است.</p>
 <?php else:action_start('request_wholesale','form-stack','/wholesale');?><button class="btn">درخواست همکاری با حساب فعلی</button></form><?php endif;?>
 <a class="text-link" href="/contact">هماهنگی ارسال و سفارش ویژه</a></section></div><?php page_footer();
}
function wholesale_panel(): void {
 $u=current_user();if(!$u)redirect('/login');
 if($u['wholesale_status']!=='APPROVED')throw new ShopError('پنل فروش عمده فقط برای حساب همکاری تأییدشده فعال است.',403);
 page_header('پنل فروش عمده','',false,url('/wholesale-panel'));
 ?><div class="container page-content"><div class="page-heading"><h1>پنل فروش عمده</h1><a class="btn" href="/cart">سبد خرید و تسویه</a></div><p>قیمت همکاری هر کالا برای حساب شما فعال است. تخفیف نسبت به قیمت خرده همان واحد محاسبه می‌شود.</p><div class="product-grid"><?php
 foreach(all('SELECT p.*,c.name category_name FROM ns_products p JOIN ns_categories c ON c.id=p.category_id WHERE p.active=1 AND p.wholesale<p.retail ORDER BY p.id DESC') as $p){
 ?><article class="panel"><a href="/products/<?=h($p['slug'])?>"><img src="<?=h($p['image'])?>" alt="<?=h($p['name'])?>" width="240" height="180" loading="lazy"><h2><?=h($p['name'])?></h2></a><p>هر <?=h($p['unit'])?> · خرده <del><?=money($p['retail'])?></del> تومان</p><p>همکاری <strong><?=money($p['wholesale'])?> تومان</strong></p><p>صرفه‌جویی <?=money((int)$p['retail']-(int)$p['wholesale'])?> تومان در هر <?=h($p['unit'])?></p><?php quantity_control($p,true);?></article><?php
 }?></div></div><?php page_footer();
}
function shop_faq(): array {return [
 ['question'=>'حداقل خرید عمده چقدر است؟','answer'=>'مقدار هر کالا در صفحه محصول مشخص است. پیش‌فرض محصولات جدید ۵۰ کیلوگرم است؛ برای محصولات بسته‌ای، تعداد بسته درج‌شده ملاک است. مدیر می‌تواند حداقل هر کالا را جداگانه تعیین کند.'],
 ['question'=>'قیمت عمده چگونه محاسبه می‌شود؟','answer'=>'با رسیدن مقدار همان کالا به حداقل درج‌شده، قیمت عمده برای همه واحدهای همان کالا اعمال می‌شود. حساب همکار تأییدشده این قیمت را بدون شرط تعداد دریافت می‌کند. قیمت ویژه سفارش بالای ۱۰۰ کیلوگرم نیازمند استعلام جداگانه است.'],
 ['question'=>'ارسال چند روز طول می‌کشد؟','answer'=>''],
 ['question'=>'ارسال با چه روشی انجام می‌شود؟','answer'=>''],
 ['question'=>'آیا خرید حضوری دارید؟','answer'=>''],
 ['question'=>'آیا فاکتور صادر می‌شود؟','answer'=>''],
 ['question'=>'محصول معیوب قابل مرجوعی است؟','answer'=>''],
 ['question'=>'امکان تولید سفارشی هست؟','answer'=>''],
 ['question'=>'اگر پرداخت ناموفق بود چه کنم؟','answer'=>'کد سفارش را نگه دارید و با شماره موبایل گیرنده در صفحه پیگیری، وضعیت را بررسی کنید. برای پیگیری کسر وجه یا نتیجه نامشخص با پشتیبانی تماس بگیرید.'],
 ];}
function faq_page(): void {
 $faq=array_values(array_filter(shop_faq(),fn($q)=>$q['answer']!==''));page_header('پرسش‌های متداول خرید، ارسال و فروش عمده','پاسخ پرسش‌های خرید عمده نایلکس، حداقل سفارش، ارسال، فاکتور، مرجوعی و پرداخت.',true,url('/faq'));
 json_ld(['@context'=>'https://schema.org','@type'=>'FAQPage','mainEntity'=>array_map(fn($q)=>['@type'=>'Question','name'=>$q['question'],'acceptedAnswer'=>['@type'=>'Answer','text'=>$q['answer']]],$faq)]);
 ?><div class="container page-content"><article class="panel prose"><h1>پرسش‌های متداول</h1><?php visible_faq($faq,'پیش از خرید بدانید');?><a class="btn" href="/wholesale">درخواست همکاری</a> <a class="text-link" href="/contact">تماس و پشتیبانی</a></article></div><?php page_footer();
}
