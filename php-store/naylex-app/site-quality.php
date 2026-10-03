<?php
declare(strict_types=1);

/** Plain text authored by the shop, never interpreted as HTML. */
function prose_text(string $text): string {
 $out='';
 foreach(preg_split('/\R\s*\R/u',trim($text)) as $paragraph){
  $paragraph=trim($paragraph);if($paragraph==='')continue;
  $out.='<p>'.nl2br(h($paragraph)).'</p>';
 }
 return $out;
}

function content_is_ready(string $body): bool {
 $body=trim($body);
 return mb_strlen($body)>=120 && !preg_match('/جزئیات این بخش پس از|جایگزین کنید|TODO|Lorem ipsum/iu',$body);
}

function legal_content_ready(string $key): bool {
 return $key==='payment'||content_is_ready(content_text('legal.'.$key,''));
}

/** Keep the existing category URLs; only improve the human-facing label. */
function category_display_name(array $category): string {
 $name=(string)$category['name'];
 if(preg_match('/^[۰-۹٠-٩0-9\s×*xX]+$/u',$name)&&preg_match('/[×*xX]/u',$name)){
  $parent=!empty($category['parent_id'])?one('SELECT name FROM ns_categories WHERE id=?',[$category['parent_id']]):null;
  if(!$parent&&!empty($category['id']))$parent=one('SELECT parent.name FROM ns_categories c JOIN ns_categories parent ON parent.id=c.parent_id WHERE c.id=?',[$category['id']]);
  return ($parent['name']??'نایلکس').' '.$name.' سانتی‌متر';
 }
 return $name;
}

function public_category_tree(): array {
 $active=array_flip(array_map('intval',array_column(all('SELECT DISTINCT category_id FROM ns_products WHERE active=1'),'category_id')));
 $result=[];
 foreach(category_tree() as $parent){
  $parent['children']=array_values(array_filter($parent['children'],fn($child)=>isset($active[(int)$child['id']])));
  if(isset($active[(int)$parent['id']])||$parent['children'])$result[]=$parent;
 }
 return $result;
}

function public_category_options(): array {
 $result=[];foreach(public_category_tree() as $parent){
  $result[]=['id'=>$parent['id'],'label'=>$parent['name']];
  foreach($parent['children'] as $child)$result[]=['id'=>$child['id'],'label'=>'— '.category_display_name($child)];
 }return $result;
}

/** Product-type shortcuts for shoppers; dimensions remain product specifications, not top-level categories. */
function public_product_types(): array {
 $definitions=[
  ['name'=>'نایلکس','query'=>'نایلکس','icon'=>'bag','terms'=>['نایلکس']],
  ['name'=>'نایلون','query'=>'نایلون','icon'=>'bag','terms'=>['نایلون']],
  ['name'=>'دستکش','query'=>'دستکش','icon'=>'default','terms'=>['دستکش']],
  ['name'=>'کیسه فریزر','query'=>'کیسه فریزر','icon'=>'freezer','terms'=>['فریزر']],
  ['name'=>'کیسه زباله','query'=>'کیسه زباله','icon'=>'trash','terms'=>['زباله']],
  ['name'=>'سفره یکبار مصرف','query'=>'سفره','icon'=>'tablecloth','terms'=>['سفره']],
  ['name'=>'محصولات بسته‌بندی','query'=>'بسته بندی','icon'=>'box','terms'=>['بسته‌بندی','بسته بندی']],
 ];
 $rows=all('SELECT p.name,c.name category_name FROM ns_products p JOIN ns_categories c ON c.id=p.category_id WHERE p.active=1');
 $types=[];
 foreach($definitions as $definition){
  $count=0;
  foreach($rows as $row){
   $haystack=$row['name'].' '.$row['category_name'];
   foreach($definition['terms'] as $term)if(str_contains($haystack,$term)){$count++;break;}
  }
  if($count)$types[]=$definition+['count'=>$count];
 }
 return $types;
}

/** Warnings are for the owner; never silently rewrite commercial data. */
function product_quality_issues(array $p): array {
 $issues=[];if(!available_image((string)$p['image']))$issues[]='تصویر محصول پیدا نمی‌شود؛ یک عکس واقعی در ویرایش محصول بارگذاری کنید.';$name=digits((string)$p['name']);$description=digits((string)$p['description']);
 if(preg_match('/بسته\s*([0-9]+(?:\.[0-9]+)?)\s*کیلویی/u',$name,$match)){
  if($p['unit']==='عدد')$issues[]='واحد «عدد» برای بسته وزنی مبهم است؛ واحد و قیمت کل بسته را تأیید کنید.';
  preg_match_all('/وزن بسته\s*([0-9]+(?:\.[0-9]+)?)\s*کیلو/u',$description,$weights);
  foreach($weights[1] as $weight)if((float)$weight!==(float)$match[1]){$issues[]='وزن بسته در توضیحات با عنوان محصول همخوان نیست.';break;}
 }
 if(mb_strlen(trim($p['description']))<80)$issues[]='توضیحات اختصاصی محصول کافی نیست.';
 if((int)$p['wholesale']>(int)$p['retail'])$issues[]='قیمت عمده از قیمت خرده بیشتر است.';
 return $issues;
}

function site_quality_admin(): void {
 $s=settings();$items=[
  ['نشانی کامل و شرایط مراجعه','company',mb_strlen(trim($s['company_address']))>=35],
  ['روزها و ساعات پاسخ‌گویی','company',trim($s['company_hours'])!==''],
  ['معرفی واقعی مجموعه','company',content_is_ready($s['company_about'])],
  ['شرایط خرید','terms',legal_content_ready('terms')],
  ['شرایط مرجوعی و بازپرداخت','terms',legal_content_ready('returns')],
  ['روش ارسال عادی و عمده','shipping',content_text('shipping.retail_methods','')!==''&&content_text('shipping.wholesale_methods','')!==''],
  ['زمان آماده‌سازی و تحویل','shipping',content_text('shipping.preparation','')!==''&&content_text('shipping.other_time','')!==''],
 ];
 ?><section class="panel"><h2>اطلاعاتی که پیش از انتشار باید بررسی شوند</h2><p>این کنترل‌ها کامل‌بودن اولیه را می‌سنجند؛ صحت قیمت، سابقه، مشخصات و تعهدات فروشگاه باید توسط مسئول مجموعه تأیید شود. هیچ قیمت یا سیاستی خودکار جایگزین نمی‌شود.</p><ul><?php foreach($items as [$label,$tab,$ready]):?><li><a href="/admin?tab=<?=h($tab)?>"><?=h($label)?></a> — <?=$ready?'ثبت شده؛ صحت آن را بررسی کنید':'نیازمند تکمیل'?></li><?php endforeach;?></ul></section>
 <section class="panel"><h2>بازبینی اطلاعات محصولات</h2><?php
 $products=all('SELECT * FROM ns_products WHERE active=1 ORDER BY category_id,id');$groups=[];$count=0;
 foreach($products as $p){
  if(preg_match('/بسته\s*[۰-۹0-9]+\s*کیلویی/u',$p['name']))$groups[preg_replace('/بسته\s*[۰-۹0-9]+\s*کیلویی/u','بسته',$p['name'])][]=$p;
  $issues=product_quality_issues($p);if(!$issues)continue;$count++;
  echo '<h3><a href="/admin?tab=products&amp;edit='.(int)$p['id'].'">'.h($p['name']).'</a></h3><ul>';foreach($issues as $issue)echo '<li>'.h($issue).'</li>';echo '</ul>';
 }
 foreach($groups as $group)if(count($group)>1&&count(array_unique(array_column($group,'retail')))===1){$count++;echo '<p class="notice">قیمت بسته‌های وزنی متفاوت برای «'.h($group[0]['name']).'» یکسان است. قیمت کل هر بسته و مبنای قیمت‌گذاری را در ویرایش محصولات تأیید کنید.</p>';}
 if(!$count)echo '<p>در کنترل خودکار تناقضی پیدا نشد؛ این نتیجه تأیید قیمت بازار یا مشخصات فیزیکی کالا نیست.</p>';
 ?></section><?php
}
