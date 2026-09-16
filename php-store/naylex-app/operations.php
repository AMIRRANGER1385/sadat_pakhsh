<?php
declare(strict_types=1);

function operational_log(string $event,array $context=[]): void {
 $safe=[];foreach($context as$key=>$value)if(is_scalar($value)||$value===null)$safe[mb_substr((string)$key,0,60)]=$value;
 $dir=rtrim((string)config('storage'),'/\\').'/logs';if(!is_dir($dir)&&!@mkdir($dir,0700,true))return;
 $record=['time'=>gmdate('c'),'event'=>mb_substr($event,0,80),'context'=>$safe];
 @file_put_contents($dir.'/events-'.gmdate('Y-m').'.jsonl',json_encode($record,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL,FILE_APPEND|LOCK_EX);
}

function configure_error_logging(): void {
 $dir=rtrim((string)config('storage'),'/\\').'/logs';if(!is_dir($dir))@mkdir($dir,0700,true);
 if(is_dir($dir)&&is_writable($dir)){ini_set('log_errors','1');ini_set('error_log',$dir.'/php-error.log');}
}

function operations_admin_panel(): void {
 $storage=(string)config('storage');$free=@disk_free_space($storage);$expiry=$storage.'/expiry-cron.log';$eventFile=$storage.'/logs/events-'.gmdate('Y-m').'.jsonl';$events=[];
 if(is_file($eventFile)){$lines=file($eventFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];foreach(array_slice($lines,-50) as$line){$row=json_decode($line,true);if(is_array($row))$events[]=$row;}}
 ?><div class="panel"><h2>سلامت و مانیتورینگ</h2><div class="stat-grid"><div class="panel"><p>اتصال دیتابیس</p><strong><?php try{echo query('SELECT 1')->fetchColumn()?'سالم':'خطا';}catch(Throwable){echo 'خطا';}?></strong></div><div class="panel"><p>فضای آزاد</p><strong><?=$free===false?'نامشخص':money(round($free/1073741824,1)).' GB'?></strong></div><div class="panel"><p>آخرین Cron سفارش</p><strong><?=is_file($expiry)?h(gmdate('Y-m-d H:i',filemtime($expiry)?:0)).' UTC':'ثبت نشده'?></strong></div></div><p>هشدار قطعی سرور و 5xx باید علاوه بر این صفحه در سرویس مانیتورینگ خارجی یا ایمیل Cron فعال شود.</p><h2>رویدادهای عملیاتی اخیر</h2><?php if(!$events):?><p>رویدادی ثبت نشده است.</p><?php else:?><div class="table-scroll"><table><thead><tr><th>زمان</th><th>رویداد</th><th>جزئیات غیرحساس</th></tr></thead><tbody><?php foreach(array_reverse($events) as$event):?><tr><td><?=h($event['time']??'')?></td><td><?=h($event['event']??'')?></td><td class="agent-cell"><?=h(json_encode($event['context']??[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div><?php
}
