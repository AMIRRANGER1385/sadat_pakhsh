<?php
declare(strict_types=1);
function upload_image(array $file): string {
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new ShopError('آپلود انجام نشد؛ محدودیت حجم PHP و انتخاب فایل را بررسی کنید.');
 if(!is_uploaded_file($file['tmp_name'])||$file['size']>5*1024*1024||$file['size']<1)throw new ShopError('تصویر حداکثر ۵ مگابایت باشد.');
 if(!extension_loaded('gd'))throw new ShopError('افزونه GD را در تنظیمات PHP هاست فعال کنید.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
 if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new ShopError('فقط عکس JPEG، PNG و WebP مجاز است.');
 $info=@getimagesize($file['tmp_name']);
 if(!$info||$info[0]<1||$info[1]<1||$info[0]*$info[1]>12000000)throw new ShopError('تصویر معتبر نیست یا بیش از ۱۲ مگاپیکسل است.');
 $data=file_get_contents($file['tmp_name']);
 if(($mime==='image/webp'&&(str_contains($data,'ANIM')||str_contains($data,'ANMF')))||($mime==='image/png'&&str_contains($data,'acTL')))throw new ShopError('تصویر متحرک مجاز نیست.');
 $source=@imagecreatefromstring($data);if(!$source)throw new ShopError('خواندن تصویر ممکن نشد.');
 try {
  if($mime==='image/jpeg'&&function_exists('exif_read_data')){$exif=@exif_read_data($file['tmp_name']);$orientation=(int)($exif['Orientation']??1);if(in_array($orientation,[2,4,5,7],true))imageflip($source,IMG_FLIP_HORIZONTAL);$angle=match($orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};if($angle){$rotated=imagerotate($source,$angle,0);if($rotated){imagedestroy($source);$source=$rotated;}}}
  $w=imagesx($source);$h=imagesy($source);$ratio=min(1,1600/max($w,$h));$out=imagecreatetruecolor(max(1,(int)round($w*$ratio)),max(1,(int)round($h*$ratio)));imagealphablending($out,false);imagesavealpha($out,true);
  imagecopyresampled($out,$source,0,0,0,0,imagesx($out),imagesy($out),$w,$h);
  $dir=config('storage').'/uploads';if(!is_dir($dir))mkdir($dir,0700,true);$name=bin2hex(random_bytes(24)).'.webp';
  try{if(!imagewebp($out,$dir.'/'.$name,84))throw new ShopError('ذخیره تصویر ممکن نشد.');chmod($dir.'/'.$name,0600);}finally{imagedestroy($out);}
  return '/media?name='.$name;
 }finally{imagedestroy($source);}
}
function valid_image(string $path): bool {return (bool)preg_match('#^/products/[a-zA-Z0-9_-]+\.(svg|png|jpg|jpeg|webp)$|^/media\?name=[a-f0-9]{48}\.webp$#',$path);}
function serve_image(): void {
 $name=$_GET['name']??'';if(!is_string($name)||!preg_match('/^[a-f0-9]{48}\.webp$/',$name)){http_response_code(404);return;}
 $file=config('storage').'/uploads/'.$name;if(!is_file($file)){http_response_code(404);return;}
 session_write_close();header('Content-Type: image/webp');header('Cache-Control: public, max-age=31536000, immutable');header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="'.$name.'"');readfile($file);
}

function cleanup_unused_uploads(int $graceSeconds=86400,int $limit=200): array {
 $directory=rtrim((string)config('storage'),'/\\').'/uploads';$result=['scanned'=>0,'deleted'=>0,'errors'=>0];if(!is_dir($directory))return $result;
 $used=[];$rows=all("SELECT image FROM ns_products WHERE image LIKE '/media?name=%'");
 try{$rows=array_merge($rows,all("SELECT image FROM ns_articles WHERE image LIKE '/media?name=%'"));}catch(Throwable){}
 foreach($rows as$row)if(preg_match('/^\/media\?name=([a-f0-9]{48}\.webp)$/',$row['image'],$match))$used[$match[1]]=true;
 $cutoff=time()-max(3600,$graceSeconds);$files=glob($directory.'/*.webp')?:[];
 foreach($files as$file){if($result['scanned']++>=$limit)break;$name=basename($file);if(isset($used[$name])||!preg_match('/^[a-f0-9]{48}\.webp$/',$name)||(filemtime($file)?:time())>$cutoff)continue;if(@unlink($file))$result['deleted']++;else$result['errors']++;}
 return $result;
}
