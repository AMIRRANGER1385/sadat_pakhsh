<?php
declare(strict_types=1);

function ensure_slug_redirect_schema(): void {
 static $ready=false;if($ready)return;
 query("CREATE TABLE IF NOT EXISTS ns_slug_redirects (
  entity_type VARCHAR(20) NOT NULL,
  old_slug VARCHAR(120) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(entity_type,old_slug),
  INDEX(entity_type,target_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");$ready=true;
}

function remember_slug(string $type,string $oldSlug,int $targetId): void {
 ensure_slug_redirect_schema();if(!in_array($type,['product','article'],true)||!preg_match('/^[a-z0-9-]+$/',$oldSlug))return;
 query('INSERT INTO ns_slug_redirects(entity_type,old_slug,target_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE target_id=VALUES(target_id),created_at=UTC_TIMESTAMP()',[$type,$oldSlug,$targetId]);
}

function product_slug_redirect(string $oldSlug): ?string {
 ensure_slug_redirect_schema();$row=one("SELECT p.slug FROM ns_slug_redirects r JOIN ns_products p ON p.id=r.target_id WHERE r.entity_type='product' AND r.old_slug=? AND p.active=1",[$oldSlug]);return $row['slug']??null;
}
