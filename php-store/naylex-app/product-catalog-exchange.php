<?php
declare(strict_types=1);

function product_import_match(string $slug,string $name,string $type,bool $lock=false): ?array {
 $sql="SELECT p.*,COALESCE(s.sale_type,'retail') sale_type,s.wholesale_id FROM ns_products p LEFT JOIN ns_product_sales s ON s.product_id=p.id";
 $old=one($sql.' WHERE p.slug=?'.($lock?' FOR UPDATE':''),[$slug]);
 if($old)return $old;
 // Exact name and sale type only; ambiguous names must never overwrite an arbitrary product.
 $matches=all($sql." WHERE p.name=? AND COALESCE(s.sale_type,'retail')=?".($lock?' FOR UPDATE':''),[$name,$type]);
 if(count($matches)>1)throw new ShopError('چند محصول با نام یکسان وجود دارد؛ شناسه ثابت محصول را از خروجی اکسل وارد کنید: '.$name);
 return $matches[0]??null;
}

// One row per product keeps standalone and linked products lossless on export.
function product_catalog_export_rows(int $page=1): array {
 if($page<1)throw new ShopError('شماره صفحه نامعتبر است.');
 ensure_product_sales_schema();
 $offset=($page-1)*500;
 return all("SELECT p.slug,COALESCE(s.sale_type,'retail') sale_type,w.slug wholesale_slug,p.name,c.name category,p.retail retail_price,p.wholesale wholesale_price,CASE WHEN s.sale_type='wholesale' THEN 25 ELSE p.minimum END threshold,p.stock,CASE WHEN s.sale_type='wholesale' THEN 'کیلوگرم' ELSE p.unit END unit,p.description,p.image,p.featured,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.tier_2_min,5) ELSE '' END tier_2_min,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.tier_3_min,15) ELSE '' END tier_3_min,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.tier_4_min,25) ELSE '' END tier_4_min,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.price_1,p.wholesale) ELSE '' END tier_price_1,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.price_2,p.wholesale) ELSE '' END tier_price_2,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.price_3,p.wholesale) ELSE '' END tier_price_3,CASE WHEN s.sale_type='wholesale' THEN COALESCE(t.price_4,p.wholesale) ELSE '' END tier_price_4 FROM ns_products p JOIN ns_categories c ON c.id=p.category_id LEFT JOIN ns_product_sales s ON s.product_id=p.id LEFT JOIN ns_products w ON w.id=s.wholesale_id LEFT JOIN ns_wholesale_price_tiers t ON t.product_id=p.id WHERE p.active=1 ORDER BY p.id LIMIT 500 OFFSET ".$offset);
}
function product_catalog_csv(array $rows): string {
 $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,product_catalog_headers(),',','"','');
 foreach($rows as $row){$cells=[];foreach(product_catalog_headers() as $key){$value=(string)($row[$key]??'');if(preg_match('/^[\s]*[=+@\-]/u',$value))$value="\t".$value;$cells[]=$value;}fputcsv($stream,$cells,',','"','');}
 rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
}
function product_catalog_download(): never {
 admin_user();$page=number_input('page',1,1000000,$_GET);
 $rows=product_catalog_export_rows($page);if(!$rows)throw new ShopError('در این بخش محصول فعالی برای خروجی وجود ندارد.',404);
 $format=$_GET['format']??'xlsx';if(!in_array($format,['xlsx','csv'],true))throw new ShopError('فرمت خروجی معتبر نیست.');
 if($format==='xlsx'){require_once __DIR__.'/catalog-xlsx.php';$bytes=product_catalog_xlsx($rows);header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="products-'.gmdate('Y-m-d').'-'.$page.'.xlsx"');echo $bytes;exit;}
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="products-'.gmdate('Y-m-d').'-'.$page.'.csv"');
 echo product_catalog_csv($rows);exit;
}
function product_catalog_validate(array $rows,string $mode,bool $lock=false): array {
 if(!in_array($mode,['create','update'],true)||count($rows)>500)throw new ShopError('روش ورود یا تعداد ردیف‌ها معتبر نیست.');
 $slugs=[];$matched=[];$result=[];
 foreach($rows as $i=>$r){try{
  $slug=$r['slug']??'';if(!preg_match('/^[a-z0-9-]{1,120}$/D',$slug))throw new ShopError('شناسه محصول معتبر نیست.');
  if(isset($slugs[$slug]))throw new ShopError('شناسه تکراری در فایل: '.$slug);$slugs[$slug]=true;
  if(!in_array($r['sale_type']??'',['retail','wholesale'],true))throw new ShopError('نوع فروش باید retail یا wholesale باشد.');
  if($r['sale_type']==='wholesale'&&$r['wholesale_slug']!=='')throw new ShopError('محصول عمده نباید به محصول عمده دیگری متصل باشد.');
  if($r['wholesale_slug']!==''&&!preg_match('/^[a-z0-9-]{1,120}$/D',$r['wholesale_slug']))throw new ShopError('شناسه محصول عمده معتبر نیست.');
  if($r['wholesale_slug']===$slug)throw new ShopError('محصول نمی‌تواند به خودش متصل شود.');
  foreach(['name'=>[2,170],'category'=>[2,120],'unit'=>[1,30],'description'=>[0,5000]] as $key=>[$min,$max])if(!mb_check_encoding($r[$key],'UTF-8')||mb_strlen($r[$key])<$min||mb_strlen($r[$key])>$max)throw new ShopError('مقدار ستون '.$key.' معتبر نیست.');
  foreach(['retail_price'=>[1,100000000],'wholesale_price'=>[1,100000000],'threshold'=>[1,1000],'stock'=>[0,100000],'featured'=>[0,1]] as $key=>[$min,$max])$r[$key]=number_input($key,$min,$max,$r);
  $tierKeys=['tier_2_min','tier_3_min','tier_4_min','tier_price_1','tier_price_2','tier_price_3','tier_price_4'];
  if($r['sale_type']==='wholesale'){
   $defaults=['tier_2_min'=>5,'tier_3_min'=>15,'tier_4_min'=>25,'tier_price_1'=>$r['wholesale_price'],'tier_price_2'=>$r['wholesale_price'],'tier_price_3'=>$r['wholesale_price'],'tier_price_4'=>$r['wholesale_price']];
   foreach($defaults as$key=>$value)if(!isset($r[$key])||$r[$key]==='')$r[$key]=(string)$value;
   foreach(['tier_2_min'=>[2,998],'tier_3_min'=>[3,999],'tier_4_min'=>[4,1000],'tier_price_1'=>[1,100000000],'tier_price_2'=>[1,100000000],'tier_price_3'=>[1,100000000],'tier_price_4'=>[1,100000000]] as$key=>[$min,$max])$r[$key]=number_input($key,$min,$max,$r);
   if(!($r['tier_2_min']<$r['tier_3_min']&&$r['tier_3_min']<$r['tier_4_min']))throw new ShopError('مرز بازه‌های قیمت عمده باید به‌ترتیب صعودی باشند.');
   $r['unit']='کیلوگرم';$r['threshold']=25;$r['wholesale_price']=$r['tier_price_4'];
  }else foreach($tierKeys as$key)if(isset($r[$key])&&$r[$key]!=='')throw new ShopError('ستون‌های بازه قیمت فقط برای ردیف wholesale تکمیل شوند.');
  if($r['image']!==''&&!valid_image($r['image']))throw new ShopError('مسیر تصویر محصول معتبر نیست.');
  $old=product_import_match($slug,$r['name'],$r['sale_type'],$lock);
  if($old){if(isset($matched[$old['id']]))throw new ShopError('دو ردیف فایل به یک محصول موجود اشاره می‌کنند.');$matched[$old['id']]=true;}
  if($old){if($mode==='create')throw new ShopError('شناسه از قبل وجود دارد: '.$slug);if(!(int)$old['active'])throw new ShopError('محصول غیرفعال را در مدیریت بررسی کنید.');if($old['sale_type']!==$r['sale_type'])throw new ShopError('تغییر نوع فروش محصول موجود از طریق فایل مجاز نیست.');}
  $r['_existing']=$old;$result[]=$r;
 }catch(ShopError $e){throw new ShopError('ردیف '.($i+2).': '.$e->getMessage());}}
 foreach($result as $i=>$r){if($r['wholesale_slug']==='')continue;
  $target=null;foreach($result as $candidate)if($candidate['slug']===$r['wholesale_slug']){$target=$candidate;break;}
  if($target){if($target['sale_type']!=='wholesale')throw new ShopError('ردیف '.($i+2).': محصول مقصد باید عمده باشد.');}
  else{$target=one('SELECT p.id,s.sale_type,p.active FROM ns_products p JOIN ns_product_sales s ON s.product_id=p.id WHERE p.slug=?'.($lock?' FOR UPDATE':''),[$r['wholesale_slug']]);if(!$target||$target['sale_type']!=='wholesale'||!(int)$target['active'])throw new ShopError('ردیف '.($i+2).': محصول عمده مرتبط پیدا نشد.');}
 }
 return $result;
}
function product_catalog_apply(array $rows,string $mode): array {
 return transaction(function()use($rows,$mode){
  $current=product_catalog_validate($rows,$mode,true);$ids=[];$created=0;$updated=0;
  foreach($current as $i=>$r){$before=$rows[$i]['_existing']??null;$now=$r['_existing'];if(($before['id']??null)!==($now['id']??null)||($before['version']??null)!==($now['version']??null))throw new ShopError('محصول پس از پیش‌نمایش تغییر کرده است؛ فایل را دوباره بررسی کنید.');
   query('INSERT INTO ns_categories(name) VALUES (?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$r['category']]);$category=(int)one('SELECT id FROM ns_categories WHERE name=?',[$r['category']])['id'];
   $image=$r['image']?:($now['image']??'/products/product-1.svg');$values=[$r['name'],$r['description'],$image,$r['retail_price'],$r['wholesale_price'],$r['threshold'],$r['stock'],$r['unit'],$category,$r['featured']];
   if($now){query('UPDATE ns_products SET name=?,description=?,image=?,retail=?,wholesale=?,minimum=?,stock=?,unit=?,category_id=?,featured=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[...$values,$now['id']]);$id=(int)$now['id'];$updated++;}
   else{query('INSERT INTO ns_products(name,description,image,retail,wholesale,minimum,stock,unit,category_id,featured,slug) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[...$values,$r['slug']]);$id=(int)db()->lastInsertId();$created++;}
   $ids[$r['slug']]=$id;
   if(!$now||(int)$now['retail']!==$r['retail_price']||(int)$now['wholesale']!==$r['wholesale_price'])mark_product_price_updated($id);
  }
  foreach($current as $r){$targetId=null;if($r['wholesale_slug']!=='')$targetId=$ids[$r['wholesale_slug']]??(int)one('SELECT id FROM ns_products WHERE slug=?',[$r['wholesale_slug']])['id'];
   query('INSERT INTO ns_product_sales(product_id,sale_type,wholesale_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE sale_type=VALUES(sale_type),wholesale_id=VALUES(wholesale_id)',[$ids[$r['slug']],$r['sale_type'],$targetId]);
   if($r['sale_type']==='wholesale')save_wholesale_price_tiers($ids[$r['slug']],[$r['tier_2_min'],$r['tier_3_min'],$r['tier_4_min'],$r['tier_price_1'],$r['tier_price_2'],$r['tier_price_3'],$r['tier_price_4']]);
  }
  return ['created'=>$created,'updated'=>$updated];
 });
}
