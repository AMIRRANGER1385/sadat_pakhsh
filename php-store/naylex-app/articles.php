<?php
declare(strict_types=1);
function ensure_article_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_articles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  excerpt VARCHAR(350) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  image VARCHAR(500) NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  published_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX(active,published_at), INDEX(category_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $ready=true;
}
function article_path(array $article): string {return '/articles/'.$article['slug'];}
function articles_for_category(int $categoryId,int $limit=6,int $excludeId=0): array {
 ensure_article_schema();$limit=max(1,min(12,$limit));$args=[];
 $where='a.active=1 AND a.published_at<=UTC_TIMESTAMP()';
 if($excludeId>0){$where.=' AND a.id<>?';$args[]=$excludeId;}
 if($categoryId>0){$where.=' AND (a.category_id=? OR a.category_id IS NULL)';$args[]=$categoryId;$order='(a.category_id=?) DESC,a.published_at DESC,a.id DESC';$args[]=$categoryId;}else $order='a.published_at DESC,a.id DESC';
 return all("SELECT a.*,c.name category_name FROM ns_articles a LEFT JOIN ns_categories c ON c.id=a.category_id WHERE $where ORDER BY $order LIMIT $limit",$args);
}
function article_topic_templates(): array {return [
 'nylon-vs-nylex'=>['تفاوت نایلون و نایلکس چیست؟','مقایسه کاربرد، جنس، ظاهر و معیارهای انتخاب نایلون و نایلکس برای خرید خرده و عمده.'],
 'nylex-count-per-kilo'=>['هر کیلو نایلکس چند عدد است؟','روش برآورد تعداد نایلکس در هر کیلو بر اساس وزن نمونه، ابعاد و ضخامت واقعی محصول.'],
 'nylex-price-guide'=>['قیمت نایلکس چگونه محاسبه می‌شود؟','عوامل مؤثر بر قیمت نایلکس کیلویی، وزن، مواد اولیه، ضخامت، ابعاد و مقدار سفارش.'],
 'handle-bag-guide'=>['نایلکس دسته رکابی چیست؟','راهنمای کاربردها، اندازه، ضخامت و انتخاب نایلکس دسته رکابی برای فروشگاه‌ها.'],
 'supermarket-nylex'=>['بهترین نایلکس برای سوپرمارکت','معیارهای انتخاب نایلکس فروشگاهی براساس ابعاد، تحمل وزن، مصرف روزانه و هزینه.'],
 'wholesale-nylex-guide'=>['راهنمای خرید عمده نایلکس','چک‌لیست مشخصات، نمونه، واحد فروش، قیمت، حمل و کنترل سفارش عمده نایلکس.'],
 'first-vs-second-grade-nylex'=>['تفاوت نایلکس درجه یک و درجه دو','راهنمای بررسی مواد، ظاهر، بو، استحکام و ادعاهای کیفی پیش از خرید.'],
 'trash-bag-thickness'=>['ضخامت مناسب کیسه زباله','انتخاب ضخامت و اندازه کیسه زباله براساس نوع زباله، وزن و ابعاد سطل.'],
 'packaging-nylon-guide'=>['نایلون بسته‌بندی چیست؟','معرفی کاربردها و معیارهای انتخاب نایلون بسته‌بندی براساس ابعاد، ضخامت و نوع مصرف.'],
 ];}
function article_template_draft(string $slug): array {$topics=article_topic_templates();$topic=$topics[$slug]??['',''];return ['id'=>0,'slug'=>isset($topics[$slug])?$slug:'','title'=>$topic[0],'excerpt'=>$topic[1],'body'=>$topic[0]."\n\nمقدمه\n\nراهنمای انتخاب\n\nعوامل مؤثر بر قیمت و کیفیت\n\nپرسش‌های متداول\n\nجمع‌بندی و لینک به محصولات مرتبط",'image'=>'','category_id'=>0,'active'=>0];}
function article_cards(array $articles,string $heading='مقاله‌های تازه'): void {
 if($heading==='مقاله‌های مرتبط'){$requestPath=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);if(is_string($requestPath)&&preg_match('#^/articles/([a-z0-9-]+)$#',$requestPath,$match)){$current=one('SELECT id,category_id FROM ns_articles WHERE slug=?',[$match[1]]);if($current)$articles=articles_for_category((int)($current['category_id']??0),6,(int)$current['id']);}}
 if(!$articles)return;
 ?><section class="seo-content article-links"><h2><?=h($heading)?></h2><div class="guide-grid"><?php foreach($articles as $article):?><article class="panel article-card"><?php admin_edit_link('/admin?tab=articles&edit='.$article['id'],'ویرایش این مقاله');if($article['image']):?><a href="<?=h(article_path($article))?>"><img src="<?=h($article['image'])?>" alt="<?=h($article['title'])?>" width="420" height="240" loading="lazy"></a><?php endif;?><h3><a href="<?=h(article_path($article))?>"><?=h($article['title'])?></a></h3><p><?=h($article['excerpt'])?></p><a class="text-link" href="<?=h(article_path($article))?>">خواندن مقاله</a></article><?php endforeach;?></div></section><?php
}
function articles_page(): void {
 ensure_article_schema();$articles=all("SELECT a.*,c.name category_name FROM ns_articles a LEFT JOIN ns_categories c ON c.id=a.category_id WHERE a.active=1 AND a.published_at<=UTC_TIMESTAMP() ORDER BY a.published_at DESC,id DESC LIMIT 100");
 page_header('مقالات و راهنمای خرید محصولات پلاستیکی','مقالات نایلکس سادات درباره انتخاب، مقایسه و خرید عمده نایلون، نایلکس و محصولات یکبار مصرف.',true,url('/articles'));
 json_ld(['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>'مقالات نایلکس سادات','url'=>url('/articles'),'mainEntity'=>['@type'=>'ItemList','itemListElement'=>array_map(fn($a,$i)=>['@type'=>'ListItem','position'=>$i+1,'url'=>url(article_path($a))],$articles,array_keys($articles))]]);
 ?><div class="container page-content"><nav class="breadcrumbs" aria-label="مسیر صفحه"><a href="/">خانه</a> / مقالات</nav><h1>مقالات و راهنمای خرید محصولات پلاستیکی</h1><p class="seo-intro">مطالبی برای انتخاب محصول و برنامه‌ریزی خرید عمده. قیمت و موجودی نهایی را همیشه در صفحه محصول بررسی کنید.</p><?php article_cards($articles,'تازه‌ترین مقاله‌ها');if(!$articles):?><div class="empty-state"><h2>هنوز مقاله‌ای منتشر نشده است.</h2><p>مدیر فروشگاه می‌تواند مقاله نخست را از پنل مدیریت اضافه کند.</p></div><?php endif;?></div><?php page_footer();
}
function article_page(string $slug): bool {
 ensure_article_schema();$article=one("SELECT a.*,c.name category_name FROM ns_articles a LEFT JOIN ns_categories c ON c.id=a.category_id WHERE a.slug=? AND a.active=1 AND a.published_at<=UTC_TIMESTAMP()",[$slug]);if(!$article)return false;
 $path=article_path($article);page_header($article['title'],$article['excerpt'],true,url($path),url($article['image']),'article');
 json_ld(['@context'=>'https://schema.org','@type'=>'Article','headline'=>$article['title'],'description'=>$article['excerpt'],'image'=>url($article['image']),'inLanguage'=>'fa-IR','url'=>url($path),'mainEntityOfPage'=>url($path),'datePublished'=>gmdate('Y-m-d',strtotime($article['published_at'])),'dateModified'=>gmdate('Y-m-d',strtotime($article['updated_at'])),'author'=>['@type'=>'Organization','name'=>'نایلکس سادات','url'=>url('/about')],'publisher'=>['@type'=>'Organization','name'=>'نایلکس سادات','url'=>url('/')]]);
 json_ld(['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[['@type'=>'ListItem','position'=>1,'name'=>'خانه','item'=>url('/')],['@type'=>'ListItem','position'=>2,'name'=>'مقالات','item'=>url('/articles')],['@type'=>'ListItem','position'=>3,'name'=>$article['title'],'item'=>url($path)]]]);
 ?><div class="container page-content guide-layout"><article class="panel guide-article article-body"><?php admin_edit_link('/admin?tab=articles&edit='.$article['id'],'ویرایش مقاله');?><nav class="breadcrumbs" aria-label="مسیر صفحه"><a href="/">خانه</a> ← <a href="/articles">مقالات</a> ← <span aria-current="page"><?=h($article['title'])?></span></nav><h1><?=h($article['title'])?></h1><p class="guide-byline"><time datetime="<?=h(gmdate('Y-m-d',strtotime($article['published_at'])))?>"><?=h(gmdate('Y-m-d',strtotime($article['published_at'])))?></time> · نایلکس سادات</p><?php if($article['image']):?><img src="<?=h($article['image'])?>" alt="<?=h($article['title'])?>" width="900" height="520" fetchpriority="high"><?php endif;?><p class="seo-intro"><?=h($article['excerpt'])?></p><div class="article-text"><?=nl2br(h($article['body']))?></div></article><aside class="guide-shopping panel"><h2>ادامه خرید</h2><?php if($article['category_id']&&$article['category_name']):?><a class="text-link" href="<?=h(category_path(['id'=>$article['category_id'],'name'=>$article['category_name']]))?>">مشاهده <?=h($article['category_name'])?></a><?php endif;?><a class="text-link" href="/products">همه محصولات</a><a class="text-link" href="/wholesale">خرید عمده</a><a class="text-link" href="/faq">پرسش‌های متداول</a><a class="text-link" href="/contact">مشاوره سفارش</a></aside><?php $related=all("SELECT a.*,c.name category_name FROM ns_articles a LEFT JOIN ns_categories c ON c.id=a.category_id WHERE a.active=1 AND a.published_at<=UTC_TIMESTAMP() AND a.id<>? ORDER BY a.published_at DESC,id DESC LIMIT 3",[$article['id']]);article_cards($related,'مقاله‌های مرتبط');?></div><?php page_footer();return true;
}
