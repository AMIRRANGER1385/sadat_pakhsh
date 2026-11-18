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
 query("CREATE TABLE IF NOT EXISTS ns_user_shipping_addresses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  postal_code CHAR(10) NOT NULL,
  address VARCHAR(1000) NOT NULL,
  address_hash CHAR(64) NOT NULL,
  last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_address(user_id,address_hash),
  INDEX user_recent(user_id,last_used_at,id),
  FOREIGN KEY(user_id) REFERENCES ns_users(id) ON DELETE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function shipping_address_hash(string $postalCode,string $address): string {
 return hash('sha256',$postalCode.'|'.preg_replace('/\s+/u',' ',trim($address)));
}

function user_shipping_addresses(int $userId,int $limit=8): array {
 $limit=max(1,min(30,$limit));
 return all('SELECT id,postal_code,address,last_used_at FROM ns_user_shipping_addresses WHERE user_id=? ORDER BY last_used_at DESC,id DESC LIMIT '.$limit,[$userId]);
}

function shipping_address_by_id(int $userId,int $addressId): ?array {
 if($addressId<1)return null;
 return one('SELECT id,postal_code,address FROM ns_user_shipping_addresses WHERE user_id=? AND id=?',[$userId,$addressId]);
}

function save_user_shipping_address(int $userId,string $postalCode,string $address): void {
 $hash=shipping_address_hash($postalCode,$address);
 query('INSERT INTO ns_user_shipping_addresses(user_id,postal_code,address,address_hash,last_used_at) VALUES (?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE postal_code=VALUES(postal_code),address=VALUES(address),last_used_at=UTC_TIMESTAMP()',[$userId,$postalCode,$address,$hash]);
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
 save_user_shipping_address($userId,$postalCode,$address);
}

function touch_user_shipping_profile(int $userId,string $phone,string $postalCode,string $address): void {
 query('INSERT INTO ns_user_profiles(user_id,national_code,phone,postal_code,address) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE phone=VALUES(phone),postal_code=VALUES(postal_code),address=VALUES(address)',[$userId,'',$phone,$postalCode,$address]);
 save_user_shipping_address($userId,$postalCode,$address);
}
