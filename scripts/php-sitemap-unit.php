<?php
require dirname(__DIR__).'/php-store/naylex-app/core.php';
require dirname(__DIR__).'/php-store/naylex-app/uploads.php';
require dirname(__DIR__).'/php-store/naylex-app/sitemap.php';
$config=['app_url'=>'https://sadatpakhsh.ir'];
function sitemap_check(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
sitemap_check(sitemap_lastmod('2026-02-30')===null,'invalid calendar date rejected');
sitemap_check(sitemap_lastmod('tomorrow')===null&&sitemap_lastmod('2099-01-01')===null,'relative/future dates rejected');
sitemap_check(sitemap_lastmod('2025-01-01 12:30:00')==='2025-01-01T12:30:00Z','UTC date format');
$entries=[['path'=>'/a'],['path'=>'/b'],['path'=>'/b'],['path'=>'/c']];
$chunks=sitemap_chunks($entries,2);sitemap_check(count($chunks)===2&&count($chunks[0])===2&&count($chunks[1])===1,'URL limit and deduplication');
$size=strlen(sitemap_urlset([['path'=>'/a']]));sitemap_check(count(sitemap_chunks($entries,5000,$size+10))===3,'byte limit splitting');
$xml=sitemap_urlset([['path'=>'/a?x=1&y=2','image'=>'/products/test.svg','image_title'=>'Test']]);
sitemap_check(str_contains($xml,'&amp;')&&!str_contains($xml,'image:title'),'XML escaping and current image fields');
sitemap_check(in_array('/wholesale',array_column(sitemap_static_entries(),'path'),true),'public wholesale included');
