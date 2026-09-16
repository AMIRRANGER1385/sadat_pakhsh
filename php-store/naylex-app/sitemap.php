<?php
declare(strict_types=1);
require_once __DIR__.'/seo.php';
require_once __DIR__.'/articles.php';

function sitemap_escape(string $value): string {
 return htmlspecialchars($value,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}

function sitemap_lastmod(?string $value,bool $dateOnly=false): ?string {
 if(!$value)return null;
 try{$date=new DateTimeImmutable($value,new DateTimeZone('UTC'));}catch(Throwable){return null;}
 if($date->getTimestamp()>time())return null;
 return $dateOnly?$date->format('Y-m-d'):$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
}

/** @param array<int,array{path:string,lastmod?:?string,image?:?string,image_title?:?string}> $entries */
function sitemap_urlset(array $entries): string {
 $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n";
 $xml.='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
 foreach($entries as $entry){
  $xml.='  <url><loc>'.sitemap_escape(url($entry['path'])).'</loc>';
  if(!empty($entry['lastmod']))$xml.='<lastmod>'.sitemap_escape($entry['lastmod']).'</lastmod>';
  if(!empty($entry['image'])&&valid_image($entry['image'])){
   $xml.='<image:image><image:loc>'.sitemap_escape(url($entry['image'])).'</image:loc>';
   if(!empty($entry['image_title']))$xml.='<image:title>'.sitemap_escape($entry['image_title']).'</image:title>';
   $xml.='</image:image>';
  }
  $xml.='</url>'."\n";
 }
 return $xml.'</urlset>';
}

/** @param array<int,array{path:string,lastmod?:?string}> $maps */
function sitemap_index_xml(array $maps): string {
 $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n";
 $xml.='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
 foreach($maps as $map){$xml.='  <sitemap><loc>'.sitemap_escape(url($map['path'])).'</loc>';if(!empty($map['lastmod']))$xml.='<lastmod>'.sitemap_escape($map['lastmod']).'</lastmod>';$xml.='</sitemap>'."\n";}
 return $xml.'</sitemapindex>';
}

function sitemap_static_entries(): array {
 return array_map(fn(string $path)=>['path'=>$path],[
  '/','/products','/plastic-products','/about','/contact','/faq','/privacy','/terms',
  '/shipping','/returns','/payment-guide','/guides','/articles','/wholesale-buying','/wholesale',
 ]);
}

function sitemap_product_entries(): array {
 return array_map(function(array $product):array{return [
  'path'=>'/products/'.$product['slug'],
  'lastmod'=>sitemap_lastmod($product['updated_at']),
  'image'=>$product['image'],
  'image_title'=>$product['name'],
 ];},all('SELECT slug,name,image,updated_at FROM ns_products WHERE active=1 ORDER BY id'));
}

function sitemap_category_entries(): array {
 $categories=all('SELECT c.id,c.name,MAX(p.updated_at) updated_at FROM ns_categories c JOIN ns_products p ON p.category_id=c.id AND p.active=1 GROUP BY c.id,c.name ORDER BY c.id');
 return array_map(fn(array $category)=>['path'=>category_path($category),'lastmod'=>sitemap_lastmod($category['updated_at'])],$categories);
}

function sitemap_guide_entries(): array {
 $entries=[];foreach(buying_guides() as $guide)$entries[]=['path'=>guide_path($guide),'lastmod'=>sitemap_lastmod($guide['date_modified'],true)];return $entries;
}

function sitemap_article_entries(): array {
 ensure_article_schema();$entries=[];
 foreach(all('SELECT slug,title,image,updated_at FROM ns_articles WHERE active=1 AND published_at<=UTC_TIMESTAMP() ORDER BY id') as $article){
  if(!preg_match('/^[a-z0-9-]+$/',$article['slug']))continue;
  $entries[]=['path'=>'/articles/'.$article['slug'],'lastmod'=>sitemap_lastmod($article['updated_at']),'image'=>$article['image'],'image_title'=>$article['title']];
 }
 return $entries;
}

function sitemap_section_entries(string $section): ?array {
 return match($section){
  'pages'=>sitemap_static_entries(),
  'products'=>sitemap_product_entries(),
  'categories'=>sitemap_category_entries(),
  'guides'=>sitemap_guide_entries(),
  'articles'=>sitemap_article_entries(),
  default=>null,
 };
}

function sitemap_section_lastmod(array $entries): ?string {
 $dates=array_values(array_filter(array_column($entries,'lastmod')));sort($dates,SORT_STRING);return $dates?end($dates):null;
}

function sitemap_index(): string {
 $maps=[];foreach(['pages','products','categories','guides','articles'] as $section){$entries=sitemap_section_entries($section)??[];if(!$entries)continue;$map=['path'=>'/sitemaps/'.$section.'.xml'];$lastmod=sitemap_section_lastmod($entries);if($lastmod)$map['lastmod']=$lastmod;$maps[]=$map;}
 return sitemap_index_xml($maps);
}

// Retained for internal compatibility: produces one standards-compliant URL set.
function sitemap_xml(array $products,array $categories=[]): string {
 $entries=sitemap_static_entries();
 foreach(buying_guides() as $guide)$entries[]=['path'=>guide_path($guide),'lastmod'=>sitemap_lastmod($guide['date_modified'],true)];
 foreach($categories as $category)$entries[]=['path'=>category_path($category)];
 foreach($products as $product)$entries[]=['path'=>'/products/'.$product['slug'],'lastmod'=>sitemap_lastmod($product['updated_at']??null),'image'=>$product['image']??null,'image_title'=>$product['name']??null];
 return sitemap_urlset($entries);
}

function send_sitemap(string $xml): void {
 header('Content-Type: application/xml; charset=utf-8');
 header('Cache-Control: public, max-age=3600');
 echo $xml;
}

function serve_sitemap(): void {send_sitemap(sitemap_index());}
function serve_sitemap_section(string $section): void {
 $entries=sitemap_section_entries($section);if($entries===null){not_found_page();return;}send_sitemap(sitemap_urlset($entries));
}
