<?php
declare(strict_types=1);
require_once __DIR__.'/search-match.php';
function serve_product_search(): never {
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new ShopError('روش درخواست مجاز نیست.',405);
 header('X-Robots-Tag: noindex, nofollow');
 $raw=$_GET['q']??'';if(!is_string($raw))throw new ShopError('جستجو نامعتبر است.');$term=trim(digits($raw));
 if(mb_strlen($term)>80)throw new ShopError('عبارت جستجو طولانی است.',422);
 $products=search_products($term);
 $items=array_map(fn($p)=>['url'=>'/products/'.$p['slug'],'name'=>$p['name'],'image'=>$p['image'],'price'=>unit_price($p,1),'unit'=>$p['unit'],'category'=>$p['category_name']],$products);
 header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');echo json_encode(['items'=>$items],JSON_UNESCAPED_UNICODE);exit;
}
