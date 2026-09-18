<?php
declare(strict_types=1);

function wholesale_buying_page(): void {
 $title='فروش عمده پلاستیک، نایلون و نایلکس';
 $description='خرید و پخش عمده محصولات پلاستیکی از سادات پخش؛ مشاهده قیمت عمده نایلون، نایلکس، کیسه زباله، کیسه فریزر و حداقل سفارش هر محصول.';
 $categories=seo_categories();
 $products=all("SELECT p.*,c.name category_name FROM ns_products p JOIN ns_categories c ON c.id=p.category_id WHERE p.active=1 AND EXISTS (SELECT 1 FROM ns_product_sales sales WHERE sales.product_id=p.id AND sales.sale_type='wholesale') ORDER BY p.stock>0 DESC,(p.retail-p.wholesale) DESC,p.id DESC LIMIT 12");
 page_header($title,$description,true,url('/wholesale-buying'));
 json_ld(['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$title,'description'=>$description,'url'=>url('/wholesale-buying'),'isPartOf'=>['@type'=>'WebSite','name'=>'نایلکس سادات','alternateName'=>['پلاستیک سادات','سادات پخش','بازرگانی سادات پخش'],'url'=>url('/')],'mainEntity'=>['@type'=>'ItemList','itemListElement'=>array_map(fn($p,$i)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$p['name'],'url'=>url('/products/'.$p['slug'])],$products,array_keys($products))]]);
 json_ld(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'خانه','item'=>url('/')],['@type'=>'ListItem','position'=>2,'name'=>'فروش عمده پلاستیک','item'=>url('/wholesale-buying')]]]);
 ?>
 <div class="container page-content">
  <nav class="breadcrumbs" aria-label="مسیر صفحه"><a href="/">خانه</a> ← <span aria-current="page">فروش عمده پلاستیک</span></nav>
  <header class="seo-landing-head">
   <span class="eyebrow">بازرگانی سادات پخش</span>
   <h1><?=h($title)?></h1>
   <p class="seo-intro"><?=h($description)?></p>
   <div class="buy-row"><a class="btn" href="#wholesale-products">مشاهده محصولات عمده</a><a class="btn outline" href="/wholesale">همهٔ محصولات عمده</a></div>
  </header>
  <section class="panel seo-content">
   <h2>خرید عمده محصولات پلاستیکی از سادات پخش</h2>
   <p>محصولات عمده در برگهٔ مستقل فروش عمده با قیمت عمده نمایش داده می‌شوند. همه می‌توانند بدون ورود یا ثبت‌نام، کالاها را ببینند و به سبد اضافه کنند. محصولات خرده هم برگهٔ جداگانه دارند.</p>
   <h2>چه محصولاتی را می‌توان عمده سفارش داد؟</h2>
   <div class="guide-grid"><?php foreach($categories as $category):?><a class="panel seo-category-link" href="<?=h('/wholesale?category='.$category['id'])?>"><strong>خرید عمده <?=h($category['name'])?></strong><span>مدل‌ها، قیمت و موجودی ←</span></a><?php endforeach;?></div>
   <h2>مراحل ثبت سفارش عمده</h2>
   <ol class="buying-steps"><li>دسته و محصول موردنیاز را انتخاب کنید.</li><li>واحد فروش، ویژگی‌ها و حداقل خرید عمده همان کالا را بررسی کنید.</li><li>تعداد موردنیاز را انتخاب و محصول را با قیمت عمدهٔ درج‌شده به سبد اضافه کنید.</li><li>موجودی، هزینه ارسال و مبلغ نهایی را در سبد بررسی و سپس پرداخت کنید.</li></ol>
   <p><a href="/plastic-products">مشاهده فروشگاه پلاستیک سادات</a> · <a href="/shipping">روش‌های ارسال</a> · <a href="/faq">پرسش‌های متداول</a></p>
  </section>
  <section id="wholesale-products" class="seo-content"><div class="section-heading"><div><span class="eyebrow">قیمت عمده فعال</span><h2>محصولات مناسب خرید عمده</h2></div><a href="/wholesale">همه محصولات عمده ←</a></div><div class="product-grid"><?php foreach($products as $product)product_card($product);?></div></section>
  <?php guide_cards(array_slice(array_values(buying_guides()),0,6),'راهنمای خرید عمده و انتخاب محصول');?>
  <?php visible_faq([
   ['question'=>'برای خرید عمده حساب جداگانه لازم است؟','answer'=>'خیر. مشاهده و خرید محصولات عمده با قیمت عمده برای همه آزاد است و نیاز به ورود یا حساب همکاری ندارد.'],
   ['question'=>'آیا تعداد محصولات متفاوت برای حد عمده با هم جمع می‌شود؟','answer'=>'خیر. حداقل خرید هر کالا جداگانه و براساس واحد فروش همان محصول محاسبه می‌شود.'],
   ['question'=>'قیمت عمده پلاستیک را کجا ببینم؟','answer'=>'قیمت عمده در برگهٔ محصولات عمده و صفحهٔ هر محصول عمده نمایش داده می‌شود. مبلغ نهایی دوباره در سبد و هنگام پرداخت محاسبه می‌شود.'],
   ['question'=>'ارسال سفارش عمده چگونه انجام می‌شود؟','answer'=>'روش و هزینه ارسال پس از تکمیل اطلاعات قطعی حمل در صفحه روش‌های ارسال و سبد خرید نمایش داده می‌شود. برای شرایطی که هنوز منتشر نشده است، پیش از پرداخت با پشتیبانی هماهنگ کنید.'],
  ],'پرسش‌های فروش عمده پلاستیک');?>
 </div>
 <?php page_footer();
}
