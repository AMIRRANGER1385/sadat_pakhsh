<?php
declare(strict_types=1);

function ensure_user_profile_schema(): void {
 query("CREATE TABLE IF NOT EXISTS ns_user_profiles (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  national_code CHAR(10) NOT NULL DEFAULT '',
  phone VARCHAR(11) NOT NULL DEFAULT '',
  postal_code CHAR(10) NOT NULL DEFAULT '',
  address VARCHAR(1000) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES ns_users(id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function valid_national_code(string $code): bool {
 $code=digits(trim($code));
 if(!preg_match('/^\d{10}$/',$code))return false;
 if(count(array_unique(str_split($code)))===1)return false;
 $sum=0;
 for($i=0;$i<9;$i++)$sum+=((int)$code[$i])*(10-$i);
 $r=$sum%11;
 $check=(int)$code[9];
 return $check===($r<2?$r:11-$r);
}

function user_profile(int $userId): array {
 $row=one('SELECT national_code,phone,postal_code,address FROM ns_user_profiles WHERE user_id=?',[$userId]);
 return [
  'national_code'=>(string)($row['national_code']??''),
  'phone'=>(string)($row['phone']??''),
  'postal_code'=>(string)($row['postal_code']??''),
  'address'=>(string)($row['address']??''),
 ];
}

function save_user_profile(int $userId,string $nationalCode,string $phone,string $postalCode,string $address): void {
 query('INSERT INTO ns_user_profiles(user_id,national_code,phone,postal_code,address) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE national_code=VALUES(national_code),phone=VALUES(phone),postal_code=VALUES(postal_code),address=VALUES(address)',[$userId,$nationalCode,$phone,$postalCode,$address]);
}

function touch_user_shipping_profile(int $userId,string $phone,string $postalCode,string $address): void {
 query('INSERT INTO ns_user_profiles(user_id,national_code,phone,postal_code,address) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone),postal_code=VALUES(postal_code),address=VALUES(address)',[$userId,'',$phone,$postalCode,$address]);
}
