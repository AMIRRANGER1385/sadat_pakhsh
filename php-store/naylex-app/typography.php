<?php
declare(strict_types=1);

function content_font_size(string $key): int {
 $size=(int)content_text(content_font_key($key),'0');
 return $size>=12&&$size<=48?$size:0;
}
function content_font_key(string $key): string {return 'font.'.(strlen($key)>115?hash('sha256',$key):$key);}
function content_font_family_key(string $key): string {return 'font-family.'.(strlen($key)>108?hash('sha256',$key):$key);}
function content_font_families(): array {
 return [''=>'پیش‌فرض سایت','vazirmatn'=>'وزیرمتن','tahoma'=>'Tahoma','arial'=>'Arial','system'=>'فونت سیستم','serif'=>'فونت سریف'];
}
function content_font_family(string $key): string {
 $family=content_text(content_font_family_key($key),'');return array_key_exists($family,content_font_families())?$family:'';
}
function content_font_family_css(string $family): string {
 return match($family){'vazirmatn'=>'Vazirmatn, sans-serif','tahoma'=>'Tahoma, sans-serif','arial'=>'Arial, sans-serif','system'=>'system-ui, sans-serif','serif'=>'serif',default=>''};
}
function validate_content_font(string $name): void {
 if(!array_key_exists($name,$_POST))return;
 $size=number_input($name,0,48);if($size!==0&&$size<12)throw new ShopError('اندازه فونت باید بین ۱۲ و ۴۸ باشد.');
}
function content_font_attr(string $key): string {
 $styles=[];$size=content_font_size($key);$family=content_font_family_css(content_font_family($key));
 if($size)$styles[]='font-size:'.$size.'px';if($family)$styles[]='font-family:'.$family;
 return $styles?' style="'.h(implode(';',$styles)).'"':'';
}
function content_font_field(string $key,string $name='font_size'): void {
 $size=content_font_size($key);$family=content_font_family($key);$familyName=$name.'_family';?><div class="form-grid"><label>اندازه فونت متن<select name="<?=h($name)?>" data-font-size><option value="0">پیش‌فرض سایت</option><?php for($n=12;$n<=48;$n++):?><option value="<?=$n?>" <?=$size===$n?'selected':''?>><?=money($n)?> پیکسل</option><?php endfor;?></select></label><label>نوع فونت<select name="<?=h($familyName)?>"><?php foreach(content_font_families() as$value=>$label):?><option value="<?=h($value)?>" <?=$family===$value?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label></div><?php
}
function save_content_font(string $key,string $name='font_size'): void {
 if(!array_key_exists($name,$_POST)&&!array_key_exists($name.'_family',$_POST))return;
 validate_content_font($name);$size=number_input($name,0,48);
 ensure_content_schema();query('INSERT INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)',[content_font_key($key),'اندازه فونت',(string)$size]);
 $family=text_input($name.'_family',0,30);if(!array_key_exists($family,content_font_families()))throw new ShopError('نوع فونت انتخاب‌شده معتبر نیست.');
 query('INSERT INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)',[content_font_family_key($key),'نوع فونت',$family]);
 content_cache_clear();
}
function typography_styles(): void {
 $selectors=['site.body'=>'body','site.h1'=>'body h1','site.h2'=>'body h2','site.h3'=>'body h3'];
 echo '<style>';foreach($selectors as$key=>$selector){$rules=[];$size=content_font_size($key);$family=content_font_family_css(content_font_family($key));if($size)$rules[]='font-size:'.$size.'px';if($family)$rules[]='font-family:'.$family;if($rules)echo $selector.'{'.implode(';',$rules).'}';}echo 'body #main-content .detail-description[style] > p{font-size:inherit;font-family:inherit}</style>';
}
function typography_admin(): void {
 action_start('typography','panel form-stack','/admin');?><h2>اندازه فونت‌های سایت</h2><p>اندازه عمومی متن‌ها و عنوان‌ها را تنظیم کنید. اندازه اختصاصی هر متن را نیز در فرم ویرایش همان بخش می‌توانید تغییر دهید.</p><?php
 foreach(['body'=>'متن‌ها','h1'=>'عنوان اصلی','h2'=>'عنوان بخش','h3'=>'عنوان فرعی'] as$key=>$label){echo '<h3>'.h($label).'</h3>';content_font_field('site.'.$key,'font_'.$key);}
 ?><button class="btn">ذخیره تنظیمات فونت‌ها</button></form><?php
}
