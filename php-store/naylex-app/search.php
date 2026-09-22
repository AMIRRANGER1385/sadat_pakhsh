<?php
declare(strict_types=1);
require_once __DIR__.'/search-match.php';
function serve_product_search(): never {
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new ShopError('روش درخواست مجاز نیست.',405);
 header('X-Robots-Tag: noindex, nofollow');
 $raw=$_GET['q']??'';if(!is_string($raw))throw new ShopError('جستجو نامعتبر است.');$term=trim(digits($raw));
 if(mb_strlen($term)>80)throw new ShopError('عبارت جستجو طولانی است.',422);
 $matches=search_products($term,100);$retail=[];$wholesale=[];foreach($matches as$product){if(product_sales((int)$product['id'])['sale_type']==='wholesale')$wholesale[]=$product;else$retail[]=$product;}$products=[];for($i=0;count($products)<12&&($i<count($retail)||$i<count($wholesale));$i++){if(isset($retail[$i]))$products[]=$retail[$i];if(count($products)<12&&isset($wholesale[$i]))$products[]=$wholesale[$i];}
 $items=array_map(function($p){$sales=product_sales((int)$p['id']);$type=$sales['sale_type'];$combined=$type==='retail'&&!$sales['wholesale_id'];return ['url'=>'/products/'.$p['slug'],'name'=>$p['name'],'image'=>$p['image'],'price'=>unit_price($p,1),'wholesale_price'=>$combined?(int)$p['wholesale']:null,'minimum'=>$combined?(int)$p['minimum']:null,'unit'=>$p['unit'],'category'=>$p['category_name'],'sale_type'=>$type,'sale_label'=>$type==='wholesale'?'فروش عمده':($combined?'فروش خرد و عمده':'فروش خرد')];},$products);
 header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');echo json_encode(['items'=>$items],JSON_UNESCAPED_UNICODE);exit;
}
