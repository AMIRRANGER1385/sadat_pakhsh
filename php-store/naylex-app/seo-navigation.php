<?php
declare(strict_types=1);

function paginated_path(string $path,int $page,array $filters=[]): string {
 unset($filters['page']);if($page>1)$filters['page']=$page;
 return $path.($filters?'?'.http_build_query($filters):'');
}
function pagination_links(string $path,int $page,int $pages,array $filters=[]): void {
 if($pages<=1)return;
 ?><nav class="pagination" aria-label="صفحه‌بندی"><?php if($page>1):?><a href="<?=h(paginated_path($path,$page-1,$filters))?>" rel="prev">قبلی</a><?php endif;?><span aria-current="page">صفحه <?=money($page)?> از <?=money($pages)?></span><?php if($page<$pages):?><a href="<?=h(paginated_path($path,$page+1,$filters))?>" rel="next">بعدی</a><?php endif;?></nav><?php
}
/** Use actual editorial revisions, excluding presentation-only font settings. */
function editorial_lastmod(string $prefix,string $fallback=''): string {
 $revisions=content_records();
 $latest=$fallback!==''?strtotime($fallback.' UTC'):0;
 foreach($revisions as$row)if(str_starts_with($row['key'],$prefix)){$time=strtotime($row['updated_at'].' UTC');if($time!==false&&$time<=time())$latest=max($latest?:0,$time);}
 return $latest?gmdate('Y-m-d\TH:i:s\Z',$latest):'';
}
function wholesale_category_links(): void {
 $categories=public_category_tree();if(!$categories)return;
 ?><section class="panel seo-content"><h2>انتخاب دسته برای خرید عمده پلاستیک</h2><p>مدل‌ها و اندازه‌های هر دسته را بررسی کنید؛ واحد فروش و شرایط خرید عمده در صفحه هر محصول مشخص است.</p><div class="empty-links"><?php foreach($categories as$category):?><a href="<?=h(category_path($category))?>">خرید عمده <?=h($category['name'])?></a><?php endforeach;?></div></section><?php
}
