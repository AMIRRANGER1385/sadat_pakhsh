<?php
declare(strict_types=1);
function serve_product_search(): never {
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')throw new ShopError('روش درخواست مجاز نیست.',405);
 header('X-Robots-Tag: noindex, nofollow');
 $raw=$_GET['q']??'';if(!is_string($raw))throw new ShopError('جستجو نامعتبر است.');$term=trim(digits($raw));
 if(mb_strlen($term)<2){header('Content-Type: application/json; charset=utf-8');echo '{"items":[]}';exit;}
 if(mb_strlen($term)>80)throw new ShopError('عبارت جستجو طولانی است.',422);
 $products=all('SELECT p.slug,p.name,p.image,p.retail,p.wholesale,p.minimum,p.unit,c.name category_name FROM ns_products p JOIN ns_categories c ON c.id=p.category_id WHERE p.active=1 AND (p.name LIKE ? OR c.name LIKE ?) ORDER BY p.featured DESC,p.id DESC LIMIT 6',['%'.$term.'%','%'.$term.'%']);
 $items=array_map(fn($p)=>['url'=>'/products/'.$p['slug'],'name'=>$p['name'],'image'=>$p['image'],'price'=>(int)$p['retail'],'unit'=>$p['unit'],'category'=>$p['category_name']],$products);
 header('Content-Type: application/json; charset=utf-8');header('X-Content-Type-Options: nosniff');echo json_encode(['items'=>$items],JSON_UNESCAPED_UNICODE);exit;
}
