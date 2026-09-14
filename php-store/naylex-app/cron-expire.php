<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require (getenv('NAYLEX_CONFIG')?:__DIR__.'/config.php');
require __DIR__.'/core.php';
require __DIR__.'/payment.php';
$storage=config('storage');
if(!is_file($storage.'/installed.lock')){fwrite(STDERR,"Installation incomplete.\n");exit(1);}
$lock=fopen($storage.'/expiry.lock','c+');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Expiry job already running or lock unavailable.\n");exit(1);}
try{
 $cursorPath=$storage.'/expiry-cursor';
 $cursor=is_file($cursorPath)?max(0,(int)file_get_contents($cursorPath)):0;
 $minutes=max(5,min(10080,(int)(config('reservation_minutes')??30)));
 $ids=all("SELECT id FROM ns_orders WHERE status='PENDING' AND created_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE) ORDER BY (id>?) DESC,id LIMIT 100",[$minutes,$cursor]);
 $counts=['released'=>0,'paid'=>0,'deferred'=>0,'skipped'=>0,'errors'=>0];$start=microtime(true);
 foreach($ids as $row){
  try{$counts[expire_order((int)$row['id'])]++;}catch(Throwable){$counts['errors']++;}
  file_put_contents($cursorPath,(string)$row['id'],LOCK_EX);
  if(microtime(true)-$start>220)break;
 }
 echo gmdate('c').' '.json_encode($counts).PHP_EOL;
 exit($counts['errors']?1:0);
}catch(Throwable){fwrite(STDERR,"Expiry job failed; check database and storage configuration.\n");exit(1);}
