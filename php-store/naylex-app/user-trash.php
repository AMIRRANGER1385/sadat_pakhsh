<?php
declare(strict_types=1);

function ensure_user_trash_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_user_trash (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,original_user_id BIGINT UNSIGNED NOT NULL,username VARCHAR(100) NOT NULL,name VARCHAR(120) NOT NULL,password VARCHAR(255) NOT NULL,role VARCHAR(20) NOT NULL DEFAULT 'CUSTOMER',wholesale_status VARCHAR(20) NOT NULL DEFAULT 'NONE',session_version INT UNSIGNED NOT NULL DEFAULT 1,original_created_at DATETIME NOT NULL,deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,deleted_by BIGINT UNSIGNED NULL,INDEX(deleted_at),INDEX(username),FOREIGN KEY(deleted_by) REFERENCES ns_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $ready=true;
}

function purge_user_trash(): void {
 ensure_user_trash_schema();
 query('DELETE FROM ns_user_trash WHERE deleted_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)');
}

function archive_customer(array $customer,int $adminId): void {
 ensure_user_trash_schema();
 query('INSERT INTO ns_user_trash(original_user_id,username,name,password,role,wholesale_status,session_version,original_created_at,deleted_by) VALUES (?,?,?,?,?,?,?,?,?)',[$customer['id'],$customer['username'],$customer['name'],$customer['password'],$customer['role'],$customer['wholesale_status'],$customer['session_version'],$customer['created_at'],$adminId]);
}

function restore_trashed_user(int $trashId): void {
 ensure_user_trash_schema();
 $row=one('SELECT * FROM ns_user_trash WHERE id=?',[$trashId])??throw new ShopError('رکورد سطل زباله پیدا نشد.',404);
 if(one('SELECT id FROM ns_users WHERE username=?',[$row['username']]))throw new ShopError('این موبایل یا ایمیل دوباره برای حساب دیگری ثبت شده است؛ قبل از بازیابی آن حساب را ویرایش کنید.');
 transaction(function()use($row,$trashId){
  query('INSERT INTO ns_users(username,name,password,role,wholesale_status,session_version,created_at) VALUES (?,?,?,?,?,?,?)',[$row['username'],$row['name'],$row['password'],$row['role'],$row['wholesale_status'],$row['session_version'],$row['original_created_at']]);
  query('DELETE FROM ns_user_trash WHERE id=?',[$trashId]);
 });
}

function delete_trashed_user(int $trashId): void {
 ensure_user_trash_schema();
 query('DELETE FROM ns_user_trash WHERE id=?',[$trashId]);
}
