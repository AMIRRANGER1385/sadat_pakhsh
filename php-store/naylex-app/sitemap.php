<?php
declare(strict_types=1);
require_once __DIR__.'/seo.php';
require_once __DIR__.'/articles.php';
function sitemap_xml(array $products,array $categories=[]): string {
 $escape=static fn(string $value):string=>htmlspecialchars($value,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
 foreach(['/','/products','/about','/contact','/faq','/guides','/articles','/wholesale-buying'] as $path)$xml.='<url><loc>'.$escape(url($path)).'</loc></url>';
 foreach(buying_guides() as $guide)$xml.='<url><loc>'.$escape(url(guide_path($guide))).'</loc><lastmod>'.$escape($guide['date_modified']).'</lastmod></url>';
 ensure_article_schema();
 foreach(all("SELECT slug,updated_at FROM ns_articles WHERE active=1 AND published_at<=UTC_TIMESTAMP() ORDER BY id") as $article){
  if(!preg_match('/^[a-z0-9-]+$/',$article['slug']))continue;
  $xml.='<url><loc>'.$escape(url('/articles/'.$article['slug'])).'</loc>';
  $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$article['updated_at'],new DateTimeZone('UTC'));
  if($date&&$date->format('Y-m-d H:i:s')===$article['updated_at'])$xml.='<lastmod>'.$date->format('Y-m-d\TH:i:s\Z').'</lastmod>';
  $xml.='</url>';
 }
 foreach($categories as $category)$xml.='<url><loc>'.$escape(url(category_path($category))).'</loc></url>';
 foreach($products as $p){
  if(!preg_match('/^[a-z0-9-]+$/',$p['slug']))continue;
  $xml.='<url><loc>'.$escape(url('/products/'.$p['slug'])).'</loc>';
  $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$p['updated_at'],new DateTimeZone('UTC'));
  if($date&&$date->format('Y-m-d H:i:s')===$p['updated_at']&&$date->getTimestamp()<=time())$xml.='<lastmod>'.$date->format('Y-m-d\TH:i:s\Z').'</lastmod>';
  if(valid_image($p['image']))$xml.='<image:image><image:loc>'.$escape(url($p['image'])).'</image:loc></image:image>';
  $xml.='</url>';
 }
 return $xml.'</urlset>';
}
function serve_sitemap(): void {
 // Build completely before sending XML, so a DB failure never produces partial XML.
 $xml=sitemap_xml(all('SELECT slug,image,updated_at FROM ns_products WHERE active=1 ORDER BY id'),seo_categories());
 header('Content-Type: application/xml; charset=utf-8');echo $xml;
}
