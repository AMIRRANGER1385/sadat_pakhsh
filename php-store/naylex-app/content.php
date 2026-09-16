<?php
declare(strict_types=1);
function ensure_content_schema(): void {static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_content_blocks (`key` VARCHAR(120) PRIMARY KEY, label VARCHAR(180) NOT NULL, body TEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 query("CREATE TABLE IF NOT EXISTS ns_admin_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,action VARCHAR(80) NOT NULL,subject VARCHAR(180) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(created_at),FOREIGN KEY(user_id) REFERENCES ns_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$ready=true;
}
function content_text(string $key,string $fallback): string {ensure_content_schema();return one('SELECT body FROM ns_content_blocks WHERE `key`=?',[$key])['body']??$fallback;}
function admin_edit_link(string $href,string $label='ویرایش'): void {if((current_user()['role']??'')==='ADMIN')echo '<a class="inline-edit" href="'.h($href).'">✎ '.h($label).'</a>';}
function editable_text(string $key,string $label,string $fallback,string $tag='p'): void {
 $value=content_text($key,$fallback);echo '<'.$tag.'>'.nl2br(h($value)).'</'.$tag.'>';
 if((current_user()['role']??'')==='ADMIN'){?><details class="inline-editor"><summary>✎ ویرایش <?=h($label)?></summary><?php action_start('content','form-stack','/');hidden('content_key',$key);hidden('content_label',$label);hidden('return_to',parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');?><label><?=h($label)?><textarea name="body" rows="5" required maxlength="10000"><?=h($value)?></textarea></label><button class="btn">ذخیره در همین صفحه</button></form></details><?php }
}
function audit_admin(string $action,string $subject): void {$u=current_user();if(($u['role']??'')==='ADMIN'){ensure_content_schema();query('INSERT INTO ns_admin_audit(user_id,action,subject) VALUES (?,?,?)',[$u['id'],mb_substr($action,0,80),mb_substr($subject,0,180)]);}}
