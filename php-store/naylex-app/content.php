<?php
declare(strict_types=1);
require_once __DIR__.'/typography.php';
require_once __DIR__.'/site-quality.php';
require_once __DIR__.'/seo-settings.php';
function ensure_content_schema(): void {static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_content_blocks (`key` VARCHAR(120) PRIMARY KEY, label VARCHAR(180) NOT NULL, body TEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query("CREATE TABLE IF NOT EXISTS ns_admin_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,action VARCHAR(80) NOT NULL,subject VARCHAR(180) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(created_at),FOREIGN KEY(user_id) REFERENCES ns_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$ready=true;
}
function content_cache_clear(): void {unset($GLOBALS['shop_content_records']);}
function content_records(): array {
 ensure_content_schema();
 if(!isset($GLOBALS['shop_content_records'])){$GLOBALS['shop_content_records']=[];foreach(all('SELECT `key`,body,updated_at FROM ns_content_blocks') as$row)$GLOBALS['shop_content_records'][$row['key']]=$row;}
 return $GLOBALS['shop_content_records'];
}
function content_text(string $key,string $fallback): string {return content_records()[$key]['body']??$fallback;}
function admin_edit_link(string $href,string $label='ویرایش'): void {if((current_user()['role']??'')==='ADMIN')echo '<a class="inline-edit" href="'.h($href).'">✎ '.h($label).'</a>';}
function editable_text(string $key,string $label,string $fallback,string $tag='p'): void {
 $records=content_records();
 if(!isset($records[$key])){query('INSERT IGNORE INTO ns_content_blocks (`key`,label,body) VALUES (?,?,?)',[$key,$label,$fallback]);content_cache_clear();}
 $value=content_text($key,$fallback);echo '<'.$tag.content_font_attr($key).'>'.nl2br(h($value)).'</'.$tag.'>';
 if((current_user()['role']??'')==='ADMIN'){?><details class="inline-editor"><summary>✎ ویرایش <?=h($label)?></summary><?php action_start('content','form-stack','/');hidden('content_key',$key);hidden('content_label',$label);hidden('return_to',parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');?><label><?=h($label)?><textarea name="body" rows="5" required maxlength="10000"><?=h($value)?></textarea></label><?php content_font_field($key);?><button class="btn">ذخیره در همین صفحه</button></form></details><?php }
}
function content_manager_admin(): void {
 ensure_content_schema();$rows=all("SELECT `key`,label,body,updated_at FROM ns_content_blocks WHERE `key` NOT LIKE 'font.%' AND `key` NOT LIKE 'font-family.%' AND `key` NOT LIKE 'seo.%' AND `key`<>'company.whatsapp' ORDER BY label,`key`");
 ?><div class="panel"><h2>مدیریت متن‌ها و فونت‌ها</h2><p>متن‌های عمومی سایت را از اینجا تغییر دهید. نام و توضیحات محصولات، مقاله‌ها، دسته‌بندی‌ها و اطلاعات شرکت در بخش اختصاصی خودشان قابل ویرایش‌اند.</p><?php if(!$rows):?><p>هنوز متن قابل‌ویرایشی ثبت نشده است. با ورود مدیر به صفحات سایت، گزینهٔ ویرایش کنار بخش‌های قابل‌مدیریت نمایش داده می‌شود.</p><?php else:?><div class="content-manager-list"><?php foreach($rows as$row):?><?php action_start('content_admin','form-stack content-manager-item','/admin');hidden('content_key',$row['key']);hidden('content_label',$row['label']);?><h3><?=h($row['label'])?></h3><small class="muted"><?=h($row['key'])?></small><label>متن<textarea name="body" rows="5" required maxlength="10000"><?=h($row['body'])?></textarea></label><?php content_font_field($row['key']);?><button class="btn">ذخیره این متن</button></form><?php endforeach;?></div><?php endif;?></div><?php
}
function audit_admin(string $action,string $subject): void {$u=current_user();if(($u['role']??'')==='ADMIN'){ensure_content_schema();query('INSERT INTO ns_admin_audit(user_id,action,subject) VALUES (?,?,?)',[$u['id'],mb_substr($action,0,80),mb_substr($subject,0,180)]);}}
