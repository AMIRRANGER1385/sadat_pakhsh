<?php
declare(strict_types=1);

function content_font_size(string $key): int {
 $size=(int)content_text(content_font_key($key),'0');
 return $size>=12&&$size<=48?$size:0;
}
function content_font_key(string $key): string {return 'font.'.(strlen($key)>115?hash('sha256',$key):$key);}
function validate_content_font(string $name): void {
 if(!array_key_exists($name,$_POST))return;
 $size=number_input($name,0,48);if($size!==0&&$size<12)throw new ShopError('اندازه فونت باید بین ۱۲ و ۴۸ باشد.');
}
function content_font_attr(string $key): string {
 $size=content_font_size($key);return $size?' style="font-size:'.$size.'px"':'';
}
function content_font_field(string $key,string $name='font_size'): void {
 $size=content_font_size($key);?><label>اندازه فونت متن<select name="<?=h($name)?>" data-font-size><option value="0">پیش‌فرض سایت</option><?php for($n=12;$n<=48;$n++):?><option value="<?=$n?>" <?=$size===$n?'selected':''?>><?=money($n)?> پیکسل</option><?php endfor;?></select></label><?php
}
function save_content_font(string $key,string $name='font_size'): void {
 if(!array_key_exists($name,$_POST))return;
 validate_content_font($name);$size=number_input($name,0,48);
 ensure_content_schema();query('INSERT INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)',[content_font_key($key),'اندازه فونت',(string)$size]);
 content_cache_clear();
}
function typography_styles(): void {
 $selectors=['site.body'=>'body #main-content p,body #main-content .article-text,body #main-content .detail-description','site.h1'=>'body #main-content h1','site.h2'=>'body #main-content h2','site.h3'=>'body #main-content h3'];
 echo '<style>';foreach($selectors as$key=>$selector){$size=content_font_size($key);if($size)echo $selector.'{font-size:'.$size.'px}';}echo 'body #main-content .detail-description[style] > p{font-size:inherit}</style>';
}
function typography_admin(): void {
 action_start('typography','panel form-stack','/admin');?><h2>اندازه فونت‌های سایت</h2><p>اندازه عمومی متن‌ها و عنوان‌ها را تنظیم کنید. اندازه اختصاصی هر متن را نیز در فرم ویرایش همان بخش می‌توانید تغییر دهید.</p><?php
 foreach(['body'=>'متن‌ها','h1'=>'عنوان اصلی','h2'=>'عنوان بخش','h3'=>'عنوان فرعی'] as$key=>$label){echo '<h3>'.h($label).'</h3>';content_font_field('site.'.$key,'font_'.$key);}
 ?><button class="btn">ذخیره اندازه فونت‌ها</button></form><?php
}
