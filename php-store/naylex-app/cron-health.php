<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require (getenv('NAYLEX_CONFIG')?:__DIR__.'/config.php');require __DIR__.'/core.php';require __DIR__.'/operations.php';configure_error_logging();
$checks=[];$ok=true;
try{$checks['database']=(int)query('SELECT 1')->fetchColumn()===1;}catch(Throwable $e){$checks['database']=false;$checks['database_error']=get_class($e);}
$storage=(string)config('storage');$checks['storage_writable']=is_dir($storage)&&is_writable($storage);$free=@disk_free_space($storage);$checks['disk_free_bytes']=$free===false?null:(int)$free;$checks['disk_ok']=$free!==false&&$free>=512*1024*1024;
$expiryLog=$storage.'/expiry-cron.log';$checks['expiry_cron_recent']=is_file($expiryLog)&&(filemtime($expiryLog)?:0)>=time()-1200;
foreach(['database','storage_writable','disk_ok','expiry_cron_recent'] as$key)if(empty($checks[$key]))$ok=false;
$checks['ok']=$ok;operational_log('health_check',$checks);echo json_encode($checks,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;exit($ok?0:1);
