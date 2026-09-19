<?php
declare(strict_types=1);
function product_import_headers(): array {return ['retail_slug','wholesale_slug','name','category','retail_price','wholesale_price','threshold','retail_stock','wholesale_stock','unit','description','image','featured'];}
function product_import_xml(string $xml): SimpleXMLElement {
 if(stripos($xml,'<!DOCTYPE')!==false||stripos($xml,'<!ENTITY')!==false)throw new ShopError('ساختار XML فایل مجاز نیست.');
 $old=libxml_use_internal_errors(true);try{$doc=simplexml_load_string($xml,SimpleXMLElement::class,LIBXML_NONET);if($doc===false)throw new ShopError('ساختار فایل اکسل معتبر نیست.');return $doc;}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
}
function product_import_rows(string $file,string $extension): array {
 if(filesize($file)>3*1024*1024)throw new ShopError('حداکثر حجم فایل ۳ مگابایت است.');
 $rows=[];
 if($extension==='csv'){
  $stream=fopen($file,'rb');try{
   $first=fgets($stream);rewind($stream);if(str_starts_with((string)$first,"\xEF\xBB\xBF"))fseek($stream,3);$delimiter=substr_count((string)$first,';')>substr_count((string)$first,',')?';':',';
   while(($row=fgetcsv($stream,65536,$delimiter,'"',''))!==false){$rows[]=$row;if(count($rows)>501)throw new ShopError('هر فایل حداکثر ۵۰۰ ردیف محصول می‌پذیرد.');}
  }finally{fclose($stream);}
 }elseif($extension==='xlsx'){
  // Phar reads ZIP containers without requiring the optional ZipArchive extension.
  try{$zip=new PharData($file);}catch(Throwable){throw new ShopError('فایل XLSX معتبر نیست؛ می‌توانید CSV UTF-8 آپلود کنید.');}
  $total=0;$count=0;foreach(new RecursiveIteratorIterator($zip) as $entry){$total+=$entry->getSize();if(++$count>2000||$total>20*1024*1024)throw new ShopError('فایل اکسل بیش از حد بزرگ است.');}
  $read=function(string $name)use($zip):string{if(!isset($zip[$name]))throw new ShopError('برگهٔ محصولات در فایل اکسل پیدا نشد.');return $zip[$name]->getContent();};
  $strings=[];
  if(isset($zip['xl/sharedStrings.xml'])){foreach(product_import_xml($read('xl/sharedStrings.xml'))->xpath('//*[local-name()="si"]') as $si){$text='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$text.=(string)$t;$strings[]=$text;}}
  $sheet=product_import_xml($read('xl/worksheets/sheet1.xml'));
  foreach($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $xmlRow){
   $row=array_fill(0,13,'');foreach($xmlRow->xpath('./*[local-name()="c"]') as $cell){
    if($cell->xpath('./*[local-name()="f"]'))throw new ShopError('فایل ورود محصول نباید فرمول داشته باشد؛ مقادیر را Paste Values کنید.');
    if(!preg_match('/^([A-Z]+)[0-9]+$/',(string)$cell['r'],$m))throw new ShopError('آدرس سلول اکسل نامعتبر است.');$col=0;foreach(str_split($m[1]) as $ch)$col=$col*26+ord($ch)-64;$col--;
    $v=$cell->xpath('./*[local-name()="v"]');$value=(string)($v[0]??'');
    if((string)$cell['t']==='s'){$value=$strings[(int)$value]??'';}elseif((string)$cell['t']==='inlineStr'){$value='';foreach($cell->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;}
    if($col>=13){if(trim($value)!=='')throw new ShopError('ستون‌های فایل را مطابق قالب نگه دارید.');continue;}$row[$col]=$value;
   }
   if(count(array_filter($row,fn($v)=>trim((string)$v)!=='')))$rows[]=$row;
   if(count($rows)>501)throw new ShopError('هر فایل حداکثر ۵۰۰ ردیف محصول می‌پذیرد.');
  }
 }else throw new ShopError('فقط فایل XLSX یا CSV UTF-8 پذیرفته می‌شود.');
 if(!$rows)throw new ShopError('فایل خالی است.');
 $headers=array_map(fn($s)=>trim((string)$s),array_shift($rows));$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]??'');
 if($headers!==product_import_headers())throw new ShopError('نام یا ترتیب ستون‌ها با قالب متفاوت است. قالب سایت را دانلود کنید.');
 $result=[];foreach($rows as $i=>$row){if(!count(array_filter($row,fn($v)=>trim((string)$v)!=='')))continue;if(count($row)!==13)throw new ShopError('تعداد ستون‌های ردیف '.($i+2).' صحیح نیست.');$result[]=array_combine($headers,array_map(fn($v)=>trim((string)$v),$row));}
 if(!$result)throw new ShopError('هیچ محصولی در فایل وجود ندارد.');return $result;
}
function product_import_validate(array $rows,string $mode,bool $lock=false): array {
 if(!in_array($mode,['create','update'],true)||count($rows)>500)throw new ShopError('نوع ورود نامعتبر است.');
 $seen=[];$result=[];
 foreach($rows as $i=>$r){try{
  foreach(['retail_slug','wholesale_slug'] as $key){if(!preg_match('/^[a-z0-9-]{1,120}$/D',$r[$key]??''))throw new ShopError('شناسه‌ها فقط حروف کوچک انگلیسی، عدد و خط تیره باشند.');if(isset($seen[$r[$key]]))throw new ShopError('شناسهٔ تکراری در فایل: '.$r[$key]);$seen[$r[$key]]=true;}
  foreach(['name'=>[3,170],'category'=>[2,120],'unit'=>[1,30],'description'=>[10,5000]] as $key=>[$min,$max]){if(!mb_check_encoding($r[$key],'UTF-8')||mb_strlen($r[$key])<$min||mb_strlen($r[$key])>$max)throw new ShopError('مقدار ستون '.$key.' معتبر نیست.');}
  foreach(['retail_price'=>[1,100000000],'wholesale_price'=>[1,100000000],'threshold'=>[1,1000],'retail_stock'=>[0,100000],'wholesale_stock'=>[0,100000],'featured'=>[0,1]] as $key=>[$min,$max])$r[$key]=number_input($key,$min,$max,$r);
  if($r['wholesale_price']>$r['retail_price'])throw new ShopError('قیمت عمده نباید بیشتر از خرده باشد.');
  if($r['image']!==''&&!valid_image($r['image']))throw new ShopError('تصویر باید مسیر عکس موجود در سایت باشد؛ لینک سایت دیگر مجاز نیست.');
  $existing=[];foreach(['retail','wholesale'] as $type){$old=one('SELECT p.*,s.sale_type,s.wholesale_id FROM ns_products p LEFT JOIN ns_product_sales s ON s.product_id=p.id WHERE p.slug=?'.($lock?' FOR UPDATE':''),[$r[$type.'_slug']]);
   if($old){if($mode==='create')throw new ShopError('شناسه قبلاً وجود دارد: '.$r[$type.'_slug']);if(!$old['active'])throw new ShopError('محصول غیرفعال را ابتدا در مدیریت بررسی کنید.');if(($old['sale_type']??'retail')!==$type)throw new ShopError('نوع فروش محصول موجود با ستون فایل یکسان نیست.');}
   $existing[$type]=$old;
  }
  if(!empty($existing['retail']['wholesale_id'])&&(int)$existing['retail']['wholesale_id']!==(int)($existing['wholesale']['id']??0))throw new ShopError('این محصول خرده قبلاً به عمدهٔ دیگری متصل است؛ اتصال را در فرم ویرایش بررسی کنید.');
  $r['_existing']=$existing;$result[]=$r;
 }catch(ShopError $e){throw new ShopError('ردیف '.($i+2).': '.$e->getMessage());}}
 return $result;
}
function product_import_apply(array $rows,string $mode): array {
 return transaction(function()use($rows,$mode){
  $current=product_import_validate($rows,$mode,true);$created=0;$updated=0;
  foreach($current as $i=>$r){
   foreach(['retail','wholesale'] as $type){$before=$rows[$i]['_existing'][$type]??null;$now=$r['_existing'][$type];if(($before['id']??null)!==($now['id']??null)||($before['version']??null)!==($now['version']??null))throw new ShopError('محصول از زمان پیش‌نمایش تغییر کرده؛ فایل را دوباره بررسی کنید.');}
   query('INSERT INTO ns_categories(name) VALUES (?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$r['category']]);$category=(int)one('SELECT id FROM ns_categories WHERE name=?',[$r['category']])['id'];$ids=[];
   foreach(['wholesale','retail'] as $type){$old=$r['_existing'][$type];$image=$r['image']?:($old['image']??'/products/product-1.svg');$name=$r['name'].($type==='wholesale'?' عمده':'');
    $values=[$name,$r['description'],$image,$r['retail_price'],$r['wholesale_price'],$r['threshold'],$r[$type.'_stock'],$r['unit'],$category,$r['featured']];
    if($old){query('UPDATE ns_products SET name=?,description=?,image=?,retail=?,wholesale=?,minimum=?,stock=?,unit=?,category_id=?,featured=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=?',[...$values,$old['id']]);$ids[$type]=(int)$old['id'];$updated++;}
    else{query('INSERT INTO ns_products(name,description,image,retail,wholesale,minimum,stock,unit,category_id,featured,slug) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[...$values,$r[$type.'_slug']]);$ids[$type]=(int)db()->lastInsertId();$created++;}
    query('INSERT INTO ns_product_sales(product_id,sale_type,wholesale_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE sale_type=VALUES(sale_type),wholesale_id=VALUES(wholesale_id)',[$ids[$type],$type,$type==='retail'?$ids['wholesale']:null]);
    if(!$old||(int)$old['retail']!==(int)$r['retail_price']||(int)$old['wholesale']!==(int)$r['wholesale_price'])mark_product_price_updated($ids[$type]);
   }
  }
  return ['created'=>$created,'updated'=>$updated];
 });
}
function product_import_action(string $action): never {
 if($action==='product_import_preview'){
  $file=$_FILES['products_file']??null;if(!$file||$file['error']!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']))throw new ShopError('آپلود فایل انجام نشد.');
  $mode=text_input('import_mode',1,10);$rows=product_import_rows($file['tmp_name'],strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)));$rows=product_import_validate($rows,$mode);
  $_SESSION['product_import']=['rows'=>$rows,'mode'=>$mode,'created'=>time(),'token'=>bin2hex(random_bytes(24))];
  redirect('/admin?tab=import');
 }
 $pending=$_SESSION['product_import']??null;
 if(!$pending||time()-$pending['created']>1800||!hash_equals($pending['token'],text_input('import_token',48,48)))throw new ShopError('پیش‌نمایش منقضی شده؛ فایل را دوباره آپلود کنید.');
 $result=product_import_apply($pending['rows'],$pending['mode']);unset($_SESSION['product_import']);audit_admin('PRODUCT_IMPORT',json_encode($result));
 flash($result['created'].' محصول جدید و '.$result['updated'].' محصول به‌روز شد. اتصال خرده و عمده برقرار است.');redirect('/admin?tab=products');
}
function product_import_page(): void {
 ?><section class="panel form-stack"><h2>ورود گروهی محصولات از اکسل</h2><p>هر ردیف یک کالا است؛ سایت دو محصول خرده و عمده را با قیمت‌های جدا می‌سازد و به هم متصل می‌کند. واحد فروش هر دو محصول در این قالب یکسان است.</p><a class="btn outline" href="/templates/products.xlsx">دانلود قالب اکسل</a><a href="/templates/products.csv">قالب CSV UTF-8</a><p>برگهٔ اول را پر کنید؛ نام ستون‌ها را تغییر ندهید. ردیف نمونه را با کالای خود جایگزین کنید. راهنمای ستون‌ها در برگهٔ دوم اکسل است. حداکثر ۵۰۰ کالا در هر فایل.</p><form method="post" action="/admin" enctype="multipart/form-data" class="form-stack"><?php csrf_field();hidden('action','product_import_preview');?><label>فایل محصولات<input type="file" name="products_file" accept=".xlsx,.csv" required></label><label>روش ورود<select name="import_mode"><option value="create">فقط افزودن؛ شناسهٔ تکراری خطا بدهد</option><option value="update">افزودن یا به‌روزرسانی بر اساس شناسهٔ ثابت</option></select></label><p>به‌روزرسانی، قیمت و موجودی و متن را با مقادیر فایل جایگزین می‌کند؛ ویژگی‌های اختصاصی و سوابق سفارش حفظ می‌شوند. تصویر خالی برای محصول موجود حفظ می‌شود و برای محصول جدید تصویر نمونه می‌گیرد.</p><button class="btn">بررسی فایل و نمایش پیش‌نمایش</button></form></section>
 <?php $pending=$_SESSION['product_import']??null;if(!$pending)return;?>
 <section class="panel"><h2>پیش‌نمایش <?=money(count($pending['rows']))?> کالا</h2><p>هنوز هیچ محصولی ذخیره نشده است. هر ردیف شامل دو محصول متصل می‌شود.</p><div class="table-scroll"><table><thead><tr><th>کالا</th><th>شناسه خرده / عمده</th><th>قیمت خرده / عمده</th><th>تعداد پیشنهاد</th></tr></thead><tbody><?php foreach($pending['rows'] as $r):?><tr><td><?=h($r['name'])?></td><td><?=h($r['retail_slug'])?> / <?=h($r['wholesale_slug'])?></td><td><?=money($r['retail_price'])?> / <?=money($r['wholesale_price'])?></td><td><?=money($r['threshold'])?> <?=h($r['unit'])?></td></tr><?php endforeach;?></tbody></table></div><?php action_start('product_import_commit','','/admin');hidden('import_token',$pending['token']);?><button class="btn">تأیید و ذخیرهٔ همهٔ محصولات</button></form></section><?php
}
