<?php
declare(strict_types=1);

function ensure_support_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_support_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,sender_role VARCHAR(10) NOT NULL,message TEXT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,read_by_admin TINYINT(1) NOT NULL DEFAULT 0,read_by_user TINYINT(1) NOT NULL DEFAULT 0,INDEX(user_id,created_at),INDEX(read_by_admin,created_at),FOREIGN KEY(user_id) REFERENCES ns_users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $ready=true;
}

function support_unread_count(): int {
 ensure_support_schema();
 return (int)query("SELECT COUNT(*) FROM ns_support_messages WHERE sender_role='CUSTOMER' AND read_by_admin=0")->fetchColumn();
}

function support_user_unread_count(int $userId): int {
 ensure_support_schema();
 return (int)query("SELECT COUNT(*) FROM ns_support_messages WHERE user_id=? AND sender_role='ADMIN' AND read_by_user=0",[$userId])->fetchColumn();
}

function support_threads(): array {
 ensure_support_schema();
 return all("SELECT u.id user_id,u.name,u.username,MAX(m.created_at) last_at,SUM(CASE WHEN m.sender_role='CUSTOMER' AND m.read_by_admin=0 THEN 1 ELSE 0 END) unread,COUNT(m.id) total FROM ns_support_messages m JOIN ns_users u ON u.id=m.user_id GROUP BY u.id,u.name,u.username ORDER BY unread DESC,last_at DESC LIMIT 100");
}

function support_messages(int $userId,bool $forAdmin=false): array {
 ensure_support_schema();
 if($forAdmin)query("UPDATE ns_support_messages SET read_by_admin=1 WHERE user_id=? AND sender_role='CUSTOMER'",[$userId]);
 else query("UPDATE ns_support_messages SET read_by_user=1 WHERE user_id=? AND sender_role='ADMIN'",[$userId]);
 return all('SELECT * FROM ns_support_messages WHERE user_id=? ORDER BY created_at,id LIMIT 200',[$userId]);
}

function save_support_message(int $userId,string $role,string $message): void {
 ensure_support_schema();
 $message=trim($message);
 if(mb_strlen($message)<2||mb_strlen($message)>1500)throw new ShopError('متن پیام باید بین ۲ تا ۱۵۰۰ کاراکتر باشد.');
 query('INSERT INTO ns_support_messages(user_id,sender_role,message,read_by_admin,read_by_user) VALUES (?,?,?,?,?)',[$userId,$role,$message,$role==='ADMIN'?1:0,$role==='CUSTOMER'?1:0]);
}

function support_message_action(): never {
 $u=current_user();if(!$u)throw new ShopError('برای ارسال پیام پشتیبانی ابتدا وارد حساب شوید.',401);
 $targetId=(int)$u['id'];$role='CUSTOMER';$return='/support';
 if($u['role']==='ADMIN'&&isset($_POST['user_id'])){
  $targetId=number_input('user_id',1,PHP_INT_MAX);
  if(!one("SELECT id FROM ns_users WHERE id=? AND role='CUSTOMER'",[$targetId]))throw new ShopError('مشتری پشتیبانی پیدا نشد.',404);
  $role='ADMIN';$return='/admin?tab=support&user_id='.$targetId;
 }
 rate_limit('support:'.$u['id'],40);
 save_support_message($targetId,$role,text_input('message',2,1500));
 flash('پیام پشتیبانی ارسال شد.');
 redirect($return);
}

function support_page(): void {
 $u=current_user();if(!$u)redirect('/login');
 page_header('گفت‌وگوی پشتیبانی','پیام امن با پشتیبانی نایلکس سادات.',false,url('/support'));
 $messages=support_messages((int)$u['id']);
 ?><div class="container page-content support-page"><div class="panel support-chat"><div class="section-heading"><div><span class="eyebrow">پشتیبانی نایلکس سادات</span><h1>گفت‌وگوی پشتیبانی</h1></div><a class="btn outline" href="/account">حساب کاربری</a></div><div class="chat-thread"><?php if(!$messages):?><p class="muted">پیامی ثبت نشده است. سوال خود را بنویسید تا مدیر پاسخ بدهد.</p><?php endif;foreach($messages as$m):?><article class="chat-message <?=$m['sender_role']==='ADMIN'?'from-admin':'from-user'?>"><strong><?=$m['sender_role']==='ADMIN'?'پشتیبانی':'شما'?></strong><p><?=nl2br(h($m['message']))?></p><time><?=h(substr($m['created_at'],0,16))?></time></article><?php endforeach;?></div><?php action_start('support_message','chat-form','/support');?><textarea name="message" rows="3" maxlength="1500" required placeholder="پیام خود را بنویسید..."></textarea><button class="btn">ارسال پیام</button></form></div></div><?php page_footer();
}

function admin_support_panel(): void {
 $selected=isset($_GET['user_id'])?number_input('user_id',1,PHP_INT_MAX,$_GET):0;
 $threads=support_threads();
 if(!$selected&&$threads)$selected=(int)$threads[0]['user_id'];
 $customer=$selected?one("SELECT id,name,username FROM ns_users WHERE id=? AND role='CUSTOMER'",[$selected]):null;
 $messages=$customer?support_messages((int)$customer['id'],true):[];
 ?><div class="support-admin"><aside class="panel support-thread-list"><h2>پیام‌های پشتیبانی</h2><?php if(!$threads):?><p>هنوز پیامی ثبت نشده است.</p><?php endif;foreach($threads as$t):?><a class="<?=$selected===(int)$t['user_id']?'active':''?>" href="/admin?tab=support&amp;user_id=<?=(int)$t['user_id']?>"><strong><?=h($t['name'])?></strong><small><?=h($t['username'])?> · <?=h(substr($t['last_at'],0,16))?></small><?php if((int)$t['unread']):?><b><?=money($t['unread'])?></b><?php endif;?></a><?php endforeach;?></aside><section class="panel support-chat"><?php if(!$customer):?><h2>گفت‌وگویی انتخاب نشده است</h2><?php else:?><div class="section-heading"><div><h2><?=h($customer['name'])?></h2><p class="muted"><?=h($customer['username'])?></p></div></div><div class="chat-thread"><?php foreach($messages as$m):?><article class="chat-message <?=$m['sender_role']==='ADMIN'?'from-admin':'from-user'?>"><strong><?=$m['sender_role']==='ADMIN'?'مدیر':'مشتری'?></strong><p><?=nl2br(h($m['message']))?></p><time><?=h(substr($m['created_at'],0,16))?></time></article><?php endforeach;?></div><?php action_start('support_message','chat-form','/admin');hidden('user_id',$customer['id']);?><textarea name="message" rows="3" maxlength="1500" required placeholder="پاسخ مدیر..."></textarea><button class="btn">ارسال پاسخ</button></form><?php endif;?></section></div><?php
}

function support_floating_button(): void {
 $u=current_user();$href=$u&&$u['role']==='ADMIN'?'/admin?tab=support':($u?'/support':'/login');$unread=$u&&$u['role']!=='ADMIN'?support_user_unread_count((int)$u['id']):0;
 ?><a class="support-fab" href="<?=h($href)?>" aria-label="چت پشتیبانی"><?=icon('support')?><?php if($unread):?><b><?=money($unread)?></b><?php endif;?><span>پشتیبانی</span></a><?php
}
