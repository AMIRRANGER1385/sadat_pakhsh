<?php
declare(strict_types=1);

// One row per product keeps standalone and linked products lossless on export.
function product_catalog_export_rows(int $page=1): array {
 if($page<1)throw new ShopError('شماره صفحه نامعتبر است.');
 $offset=($page-1)*500;
 return all('SELECT p.slug,COALESCE(s.sale_type,\'retail\') sale_type,w.slug wholesale_slug,p.name,c.name category,p.retail retail_price,p.wholesale wholesale_price,p.minimum threshold,p.stock,p.unit,p.description,p.image,p.featured FROM ns_products p JOIN ns_categories c ON c.id=p.category_id LEFT JOIN ns_product_sales s ON s.product_id=p.id LEFT JOIN ns_products w ON w.id=s.wholesale_id WHERE p.active=1 ORDER BY p.id LIMIT 500 OFFSET '.$offset);
}
function product_catalog_csv(array $rows): string {
 $stream=fopen('php://temp','w+');fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,product_catalog_headers(),',','"','');
 foreach($rows as $row){$cells=[];foreach(product_catalog_headers() as $key){$value=(string)($row[$key]??'');if(preg_match('/^[\s]*[=+@\-]/u',$value))$value="\t".$value;$cells[]=$value;}fputcsv($stream,$cells,',','"','');}
 rewind($stream);$csv=stream_get_contents($stream);fclose($stream);return $csv;
}
function product_catalog_download(): never {
 admin_user();$page=number_input('page',1,1000000,$_GET);
 $rows=product_catalog_export_rows($page);if(!$rows)throw new ShopError('در این بخش محصول فعالی برای خروجی وجود ندارد.',404);
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="products-'.gmdate('Y-m-d').'-'.$page.'.csv"');
 echo product_catalog_csv($rows);exit;
}
function product_catalog_validate(array $rows,string $mode,bool $lock=false): array {
 if(!in_array($mode,['create','update'],true)||count($rows)>500)throw new ShopError('روش ورود یا تعداد ردیف‌ها معتبر نیست.');
 $slugs=[];$result=[];
 foreach($rows as $i=>$r){try{
  $slug=$r['slug']??'';if(!preg_match('/^[a-z0-9-]{1,120}$/D',$slug))throw new ShopError('شناسه محصول معتبر نیست.');
  if(isset($slugs[$slug]))throw new ShopError('شناسه تکراری در فایل: '.$slug);$slugs[$slug]=true;
  if(!in_array($r['sale_type']??'',['retail','wholesale'],true))throw new ShopError('نوع فروش باید retail یا wholesale باشد.');
  if($r['sale_type']==='wholesale'&&$r['wholesale_slug']!=='')throw new ShopError('محصول عمده نباید به محصول عمده دیگری متصل باشد.');
  if($r['wholesale_slug']!==''&&!preg_match('/^[a-z0-9-]{1,120}$/D',$r['wholesale_slug']))throw new ShopError('شناسه محصول عمده معتبر نیست.');
  if($r['wholesale_slug']===$slug)throw new ShopError('محصول نمی‌تواند به خودش متصل شود.');
  foreach(['name'=>[2,170],'category'=>[2,120],'unit'=>[1,30],'description'=>[0,5000]] as $key=>[$min,$max])if(!mb_check_encoding($r[$key],'UTF-8')||mb_strlen($r[$key])<$min||mb_strlen($r[$key])>$max)throw new ShopError('مقدار ستون '.$key.' معتبر نیست.');
  foreach(['retail_price'=>[1,100000000],'wholesale_price'=>[1,100000000],'threshold'=>[1,1000],'stock'=>[0,100000],'featured'=>[0,1]] as $key=>[$min,$max])$r[$key]=number_input($key,$min,$max,$r);
  if($r['image']!==''&&!valid_image($r['image']))throw new ShopError('مسیر تصویر محصول معتبر نیست.');
  $old=one('SELECT p.*,COALESCE(s.sale_type,\'retail\') sale_type,s.wholesale_id FROM ns_products p LEFT JOIN ns_product_sales s ON s.product_id=p.id WHERE p.slug=?'.($lock?' FOR UPDATE':''),[$slug]);
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
  }
  return ['created'=>$created,'updated'=>$updated];
 });
}
