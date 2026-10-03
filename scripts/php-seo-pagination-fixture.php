<?php
declare(strict_types=1);
require __DIR__.'/../php-store/naylex-app/core.php';
require __DIR__.'/../php-store/naylex-app/uploads.php';
require __DIR__.'/../php-store/naylex-app/content.php';
require __DIR__.'/../php-store/naylex-app/seo.php';
$config=require __DIR__.'/../php-store/naylex-app/config.php';
if(PHP_SAPI!=='cli'||config('db_name')!=='naylex_local'||config('db_port')!==33077)exit(1);
$prefix=$argv[2]??'';if(!preg_match('/^seo-page-[a-f0-9]{16}$/D',$prefix))exit(1);
$imageName=substr(hash('sha256',$prefix),0,48).'.webp';$image=config('storage').'/uploads/'.$imageName;
if(($argv[1]??'')==='cleanup'){
 query('DELETE FROM ns_articles WHERE slug LIKE ?',[$prefix.'%']);query('DELETE FROM ns_products WHERE slug LIKE ?',[$prefix.'%']);query('DELETE FROM ns_categories WHERE slug=?',[$prefix]);if(is_file($image))unlink($image);exit;
}
query('INSERT INTO ns_categories(name,slug) VALUES (?,?)',[$prefix,$prefix]);$category=(int)db()->lastInsertId();
for($i=0;$i<27;$i++){
 query('INSERT INTO ns_products(name,slug,description,image,retail,wholesale,minimum,stock,unit,category_id) VALUES (?,?,?,?,100,90,10,100,?,?)',[$prefix.' '.$i,$prefix.'-'.$i,str_repeat('توضیحات آزمایشی محصول. ',8),$i?'/products/hero.svg':'/media?name='.str_repeat('f',48).'.webp','بسته',$category]);
 query('INSERT INTO ns_articles(slug,title,excerpt,body,image,category_id,active,published_at) VALUES (?,?,?,?,?,?,1,UTC_TIMESTAMP())',[$prefix.'-'.$i,$prefix.' '.$i,'مقاله آزمایشی صفحه‌بندی',str_repeat('محتوای آزمایشی مقاله. ',8),'/products/hero.svg',$category]);
}
file_put_contents($image,base64_decode('UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA'));
echo json_encode(['category'=>category_path(['id'=>$category,'name'=>$prefix]),'image'=>'/media?name='.$imageName]);
