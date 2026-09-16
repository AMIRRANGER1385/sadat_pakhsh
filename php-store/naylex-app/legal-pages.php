<?php
declare(strict_types=1);

function legal_page(string $path): void {
 $pages=[
  '/terms'=>['قوانین و مقررات','terms','شرایط استفاده و خرید از فروشگاه'],
  '/returns'=>['شرایط مرجوعی','returns','شرایط بازگشت یا تعویض کالا'],
  '/payment-guide'=>['راهنمای پرداخت','payment','راهنمای پرداخت امن و پیگیری تراکنش'],
 ];
 if(!isset($pages[$path]))throw new ShopError('صفحه پیدا نشد.',404);[$title,$key,$intro]=$pages[$path];
 page_header($title,$intro.' در نایلکس سادات.',true,url($path));
 ?><div class="container page-content"><article class="panel prose"><nav class="breadcrumbs"><a href="/">خانه</a> ← <span aria-current="page"><?=h($title)?></span></nav><h1><?=h($title)?></h1><?php
 $body=content_text('legal.'.$key,'');
 if($body!==''):?><div class="article-text"><?=nl2br(h($body))?></div><?php else:?><p class="notice">جزئیات این بخش پس از نهایی‌شدن قوانین کسب‌وکار منتشر می‌شود. پیش از خرید درباره شرایط لازم با پشتیبانی هماهنگ کنید.</p><?php endif;
 if($path==='/payment-guide'):?><h2>پرداخت امن</h2><p>اطلاعات کارت بانکی فقط در صفحه درگاه پرداخت وارد می‌شود و فروشگاه آن را دریافت یا ذخیره نمی‌کند. پس از بازگشت از درگاه، وضعیت قطعی سفارش را در صفحه پیگیری بررسی کنید.</p><a class="btn" href="/track">پیگیری سفارش</a><?php endif;
 if((current_user()['role']??'')==='ADMIN'):?><section class="shipping-editor"><h2>ویرایش همین صفحه</h2><?php editable_text('legal.'.$key,'متن کامل '.$title,$body);?></section><?php endif;
 ?></article></div><?php page_footer();
}
