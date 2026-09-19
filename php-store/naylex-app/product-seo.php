<?php
declare(strict_types=1);

function ensure_product_seo_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_product_seo (product_id BIGINT UNSIGNED PRIMARY KEY,seo_title VARCHAR(180) NOT NULL DEFAULT '',meta_description VARCHAR(350) NOT NULL DEFAULT '',focus_keyword VARCHAR(180) NOT NULL DEFAULT '',intro TEXT NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(product_id) REFERENCES ns_products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $ready=true;
}

function product_seo(int $productId): array {
 ensure_product_seo_schema();
 return one('SELECT * FROM ns_product_seo WHERE product_id=?',[$productId])?:['product_id'=>$productId,'seo_title'=>'','meta_description'=>'','focus_keyword'=>'','intro'=>''];
}

function save_product_seo(int $productId,array $data): void {
 ensure_product_seo_schema();
 query('INSERT INTO ns_product_seo(product_id,seo_title,meta_description,focus_keyword,intro) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE seo_title=VALUES(seo_title),meta_description=VALUES(meta_description),focus_keyword=VALUES(focus_keyword),intro=VALUES(intro)',[$productId,$data['seo_title'],$data['meta_description'],$data['focus_keyword'],$data['intro']]);
}
