<?php
declare(strict_types=1);
require_once __DIR__.'/guide-content.php';

function guide_path(array $guide): string {return '/guides/'.$guide['slug'];}
function guides_for_category(array $category,int $limit=6): array {
 $key=category_profile($category)['key'];
 return array_slice(array_values(array_filter(buying_guides(),fn($guide)=>in_array($key,$guide['category_keys'],true))),0,$limit);
}
function related_guides(array $guide,array $guides,int $limit=6): array {
 $related=[];$seen=[$guide['slug']=>true];
 foreach($guide['related_slugs'] as $slug)if(isset($guides[$slug])&&!isset($seen[$slug])){$related[]=$guides[$slug];$seen[$slug]=true;if(count($related)>=$limit)return $related;}
 foreach($guides as $candidate){if(isset($seen[$candidate['slug']])||!array_intersect($guide['category_keys'],$candidate['category_keys']))continue;$related[]=$candidate;$seen[$candidate['slug']]=true;if(count($related)>=$limit)return $related;}
 foreach($guides as $candidate){if(isset($seen[$candidate['slug']]))continue;$related[]=$candidate;$seen[$candidate['slug']]=true;if(count($related)>=$limit)break;}
 return $related;
}
function guide_cards(array $guides,string $heading='راهنمای خرید'): void {
 if(!$guides)return;
 ?><section class="seo-content guide-links"><h2><?=h($heading)?></h2><div class="guide-grid"><?php foreach($guides as $guide):?><article class="panel guide-card"><h3><a href="<?=h(guide_path($guide))?>"><?=h($guide['title'])?></a></h3><p><?=h($guide['description'])?></p><a class="text-link" href="<?=h(guide_path($guide))?>">خواندن راهنما</a></article><?php endforeach;?></div></section><?php
}
function visible_faq(array $questions,string $heading): void {
 if(!$questions)return;
 ?><section class="seo-content buying-faq"><h2><?=h($heading)?></h2><?php foreach($questions as $question):?><details><summary><?=h($question['question'])?></summary><p><?=h($question['answer'])?></p></details><?php endforeach;?></section><?php
}
function guides_page(): void {
 $guides=buying_guides();$title='راهنمای خرید نایلون، نایلکس و محصولات یکبار مصرف';
 page_header($title,'راهنماهای انتخاب کیسه زباله، کیسه فریزر، سفره رولی و ظروف یکبار مصرف؛ مقایسه واحد فروش و برنامه‌ریزی سفارش عمده.',true,url('/guides'));
 json_ld(['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$title,'url'=>url('/guides'),'mainEntity'=>['@type'=>'ItemList','itemListElement'=>array_map(fn($g,$i)=>['@type'=>'ListItem','position'=>$i+1,'url'=>url(guide_path($g))],array_values($guides),range(0,count($guides)-1))]]);
 ?><div class="container page-content"><nav class="breadcrumbs" aria-label="مسیر صفحه"><a href="/">خانه</a> / راهنمای خرید</nav><h1><?=h($title)?></h1><p class="seo-intro">پیش از انتخاب محصول، اندازه، واحد فروش و مقدار مصرف را روشن کنید. این راهنماها به مقایسه گزینه‌ها کمک می‌کنند؛ قیمت و موجودی روز هر کالا را در فروشگاه ببینید.</p><?php guide_cards(array_values($guides),'پاسخ به پرسش‌های پیش از خرید');?></div><?php page_footer();
}
function guide_page(string $slug): bool {
 $guides=buying_guides();$guide=$guides[$slug]??null;if(!$guide)return false;
 $guide['related_slugs']=array_column(related_guides($guide,$guides),'slug');
 $path=guide_path($guide);
 page_header($guide['title'],$guide['description'],true,url($path),'','article');
 // Only factual, visible publisher and content revision dates; no invented expertise or reviews.
 json_ld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$guide['title'],'description'=>$guide['description'],'inLanguage'=>'fa-IR','url'=>url($path),'mainEntityOfPage'=>url($path),'dateModified'=>$guide['date_modified'],'author'=>['@type'=>'Organization','name'=>'نایلکس سادات','url'=>url('/about')],'publisher'=>['@type'=>'Organization','name'=>'نایلکس سادات','url'=>url('/')]]);
 json_ld(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'خانه','item'=>url('/')],['@type'=>'ListItem','position'=>2,'name'=>'راهنمای خرید','item'=>url('/guides')],['@type'=>'ListItem','position'=>3,'name'=>$guide['title'],'item'=>url($path)]]]);
 ?><div class="container page-content guide-layout"><article class="panel guide-article"><nav class="breadcrumbs" aria-label="مسیر صفحه"><a href="/">خانه</a> ← <a href="/guides">راهنمای خرید</a> ← <span aria-current="page"><?=h($guide['title'])?></span></nav><h1><?=h($guide['title'])?></h1><p class="guide-byline">منتشرکننده: <a href="/about">نایلکس سادات</a> · آخرین ویرایش: <time datetime="<?=h($guide['date_modified'])?>"><?=h($guide['date_modified'])?></time></p><?php editable_text('guide.'.$slug.'.intro','مقدمه مقاله',$guide['intro'],'div');?><nav class="guide-toc" aria-label="فهرست مقاله"><strong>در این راهنما می‌خوانید</strong><ol><?php foreach($guide['sections'] as $i=>$section):?><li><a href="#section-<?=$i+1?>"><?=h($section['heading'])?></a></li><?php endforeach;?></ol></nav><?php foreach($guide['sections'] as $i=>$section):?><section id="section-<?=$i+1?>"><h2><?=h($section['heading'])?></h2><?php foreach($section['paragraphs'] as $j=>$paragraph)editable_text('guide.'.$slug.'.section.'.($i+1).'.paragraph.'.($j+1),'بند '.($j+1).' از '.$section['heading'],$paragraph);if(!empty($section['list'])):?><ul><?php foreach($section['list'] as $item):?><li><?=h($item)?></li><?php endforeach;?></ul><?php endif;?></section><?php endforeach;if($slug==='wholesale-buying-checklist'):?><section class="panel guide-commercial-cta"><h2>پس از آماده‌کردن مشخصات سفارش</h2><p>این مقاله برای بررسی اطلاعات پیش از خرید است. برای مشاهده کالاهای موجود، قیمت و ثبت سفارش، به <a class="btn" href="/wholesale">صفحه خرید عمده نایلکس</a> بروید.</p></section><?php endif;visible_faq($guide['faq'],'پرسش‌های رایج');if($guide['sources']):?><section class="guide-sources"><h2>منابع و مطالعه بیشتر</h2><ul><?php foreach($guide['sources'] as $source):?><li><a href="<?=h($source['url'])?>" rel="external"><?=h($source['title'])?></a></li><?php endforeach;?></ul></section><?php endif;?></article><aside class="guide-shopping panel"><h2>انتخاب محصول مرتبط</h2><p>قیمت، موجودی و حداقل سفارش را در دسته موردنظر بررسی کنید.</p><ul><?php foreach(seo_categories() as $category):if(!in_array(category_profile($category)['key'],$guide['category_keys'],true))continue;?><li><a href="<?=h(category_path($category))?>"><?=h($category['name'])?></a></li><?php endforeach;?></ul><a class="text-link" href="/products">همه محصولات</a><a class="text-link" href="/wholesale">خرید عمده</a><a class="text-link" href="/faq">پرسش‌های متداول</a><a class="text-link" href="/contact">مشاوره سفارش</a></aside><?php $related=[];foreach($guide['related_slugs'] as $relatedSlug)if(isset($guides[$relatedSlug])&&$relatedSlug!==$slug)$related[]=$guides[$relatedSlug];guide_cards($related,'راهنماهای مرتبط');?></div><?php page_footer();return true;
}
