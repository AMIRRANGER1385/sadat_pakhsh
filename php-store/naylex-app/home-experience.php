<?php
declare(strict_types=1);

function home_hero(): void {?>
 <div class="container"><?php sales_navigation();?></div><section class="container hero hero-premium" data-hero-motion aria-labelledby="home-hero-title"><div class="hero-copy"><span class="eyebrow">کیفیت از مواد اولیه شروع می‌شود</span><?php editable_text('home.hero.title','عنوان اصلی','ساده به نظر می‌رسد؛ تا وقتی کیفیت نایلکس مهم شود.','h1');?><p class="hero-lead">نایلکس و محصولات پلاستیکی برای خانه، فروشگاه و کسب‌وکار. مدل، سایز و قیمت خرده یا عمده را در فروشگاه مقایسه کنید.</p><div class="hero-buttons"><a href="/products" class="btn">خرید محصولات <span aria-hidden="true">↗</span></a><a href="/wholesale" class="hero-secondary">خرید عمده <span aria-hidden="true">↗</span></a></div><div class="hero-bottom"><span>تولید و فروش مستقیم</span><span>تهران · سراسر ایران</span></div></div><div class="hero-art" aria-hidden="true"><div class="hero-halo"></div><div class="hero-granules"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><img class="hero-product" src="/products/brand-bag.svg" alt="" width="620" height="700" fetchpriority="high" decoding="async"><span class="hero-art-label">SADAT / MATERIAL IN MOTION</span></div></section>
<?php }

function home_category_visuals(): array {
 $images=[];
 foreach(all('SELECT p.category_id,p.image FROM ns_products p LEFT JOIN ns_product_sales s ON s.product_id=p.id WHERE p.active=1 AND COALESCE(s.sale_type,\'retail\')=\'retail\' ORDER BY p.featured DESC,p.id DESC LIMIT 200') as $p){$id=(int)$p['category_id'];if(!isset($images[$id])&&valid_image($p['image']))$images[$id]=$p['image'];}
 return $images;
}

function home_categories(): void {
 $visuals=home_category_visuals();$categories=seo_categories();?>
 <section class="container category-section category-showcase" aria-labelledby="home-categories-title"><div class="section-heading"><div><span class="eyebrow">فروشگاه سادات</span><h2 id="home-categories-title">محصول مناسب کار شما</h2></div><a href="/products">همه محصولات <span aria-hidden="true">↗</span></a></div><div class="category-grid"><?php foreach($categories as $i=>$c):?><a href="<?=h(category_path($c))?>" class="category-tile tile-<?=$i%5?>" data-reveal><span class="category-index"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><span class="category-illustration"><?php if(isset($visuals[(int)$c['id']])):?><img src="<?=h($visuals[(int)$c['id']])?>" alt="" width="260" height="200" loading="lazy" decoding="async"><?php else:?><?=icon(category_icon($c['name']))?><?php endif;?></span><h3><?=h($c['name'])?></h3><span class="category-go">مشاهده محصولات <b aria-hidden="true">↗</b></span></a><?php endforeach;?></div></section><?php
}

function home_story(): void {?>
 <section class="material-story" aria-labelledby="material-story-title"><div class="container material-story-inner"><div class="material-story-heading"><span class="eyebrow">از جنس تا انتخاب</span><h2 id="material-story-title">هر نایلکس، یک نایلکس نیست.</h2><p>برای انتخاب محصول، اندازه، ضخامت و کاربرد آن را کنار قیمت بررسی کنید. مشخصات هر کالا در صفحهٔ خودش ثبت شده است.</p></div><div class="material-steps">
 <article class="material-step" data-reveal><span class="material-number">01 / ماده</span><div class="material-visual material-pellets" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div><h3>مواد اولیه</h3><p>انتخاب جنس مناسب، نقطهٔ شروع هر محصول است.</p></article>
 <article class="material-step" data-reveal><span class="material-number">02 / شکل</span><div class="material-visual material-roll" aria-hidden="true"><i></i></div><h3>تبدیل به محصول</h3><p>از رول و بسته‌بندی تا کیسهٔ آمادهٔ استفاده.</p></article>
 <article class="material-step" data-reveal><span class="material-number">03 / کاربرد</span><div class="material-visual material-bag" aria-hidden="true"><img src="/products/brand-bag.svg" alt="" width="135" height="153" loading="lazy"></div><h3>متناسب با نیاز</h3><p>برای هر کاربرد، سایز و مشخصات کالا را مقایسه کنید.</p></article>
 <article class="material-step" data-reveal><span class="material-number">04 / سفارش</span><div class="material-visual material-stack" aria-hidden="true"><i></i><i></i><i></i></div><h3>آمادهٔ خرید</h3><p>محصولات عمده و خرده را جداگانه ببینید.</p></article>
 </div><a class="material-story-link" href="/wholesale">مشاهده محصولات عمده <span aria-hidden="true">↗</span></a></div></section>
<?php }

function home_size_finder(): void {
 $items=[];$seen=[];
 foreach(all('SELECT p.name,p.slug,p.image,p.description FROM ns_products p LEFT JOIN ns_product_sales s ON s.product_id=p.id WHERE p.active=1 AND COALESCE(s.sale_type,\'retail\')=\'retail\' ORDER BY p.featured DESC,p.stock DESC,p.id DESC LIMIT 200') as $p){
  if(!preg_match('/(?<![0-9])([0-9]{2,3})\s*[×xX*]\s*([0-9]{2,3})(?![0-9])/u',digits($p['name'].' '.$p['description']),$m))continue;
  $size=$m[1].' × '.$m[2];if(isset($seen[$size]))continue;$seen[$size]=true;$items[]=['size'=>$size,'name'=>$p['name'],'url'=>'/products/'.$p['slug'],'image'=>$p['image'],'label'=>'مشاهده محصول','status'=>'محصول ثبت‌شده در فروشگاه'];if(count($items)===6)break;
 }
 if(!$items)foreach(['25 × 35','30 × 40','37 × 47','45 × 55','44 × 65','65 × 80'] as $size)$items[]=['size'=>$size,'name'=>'نایلکس سایز '.$size,'url'=>'/products?q='.rawurlencode(str_replace(' × ','x',$size)),'image'=>'/products/brand-bag.svg','label'=>'جست‌وجوی این سایز','status'=>'پیش از سفارش، موجودی این سایز را بررسی کنید'];
 ?><section class="container size-finder" aria-labelledby="size-finder-title"><div class="size-finder-copy"><span class="eyebrow">راهنمای انتخاب</span><h2 id="size-finder-title">چه سایزی نیاز دارید؟</h2><p>اندازهٔ مناسب به ابعاد و وزن محتوا بستگی دارد. سایز دلخواه را انتخاب کنید و موجودی و مشخصات محصول را پیش از سفارش بررسی کنید.</p><div class="size-options" role="group" aria-label="انتخاب سایز نایلکس"><?php foreach($items as $i=>$item):?><button type="button" class="size-option<?=$i===0?' is-active':''?>" data-size-option data-name="<?=h($item['name'])?>" data-url="<?=h($item['url'])?>" data-image="<?=h($item['image'])?>" data-label="<?=h($item['label'])?>" data-status="<?=h($item['status'])?>" aria-pressed="<?=$i===0?'true':'false'?>"><?=h($item['size'])?></button><?php endforeach;?></div><div class="size-result" aria-live="polite"><small data-size-status><?=h($items[0]['status'])?></small><strong data-size-name><?=h($items[0]['name'])?></strong><a href="<?=h($items[0]['url'])?>" data-size-link><span data-size-link-label><?=h($items[0]['label'])?></span> <span aria-hidden="true">↗</span></a></div></div><div class="size-finder-art"><span>SIZE / FINDER</span><img src="<?=h($items[0]['image'])?>" data-size-image alt="<?=h($items[0]['name'])?>" width="440" height="420" loading="lazy" decoding="async"></div></section><?php
}
