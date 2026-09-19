<?php
declare(strict_types=1);

function ensure_category_schema(): void {
 static $ready=false;if($ready)return;
 $columns=array_column(query('SHOW COLUMNS FROM ns_categories')->fetchAll(),'Field');
 if(!in_array('parent_id',$columns,true))query('ALTER TABLE ns_categories ADD parent_id BIGINT UNSIGNED NULL AFTER name');
 if(!in_array('sort_order',$columns,true))query('ALTER TABLE ns_categories ADD sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER parent_id');
 if(!in_array('slug',$columns,true))query("ALTER TABLE ns_categories ADD slug VARCHAR(120) NOT NULL DEFAULT '' AFTER name");
 query("UPDATE ns_categories SET slug=CONCAT('category-',id) WHERE slug='' OR slug IS NULL");
 query("CREATE TABLE IF NOT EXISTS ns_category_migrations (`key` VARCHAR(80) PRIMARY KEY,completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 if(!one("SELECT `key` FROM ns_category_migrations WHERE `key`='hierarchy-v1'")){
  $combined=one("SELECT id FROM ns_categories WHERE name='نایلون و نایلکس'");
  if($combined)query("UPDATE ns_categories SET name='نایلون',slug='nylon',sort_order=20 WHERE id=?",[$combined['id']]);
  $nylex=one("SELECT id FROM ns_categories WHERE name='نایلکس'");
  if(!$nylex){query("INSERT INTO ns_categories(name,slug,parent_id,sort_order) VALUES ('نایلکس','nylex',NULL,10)");$nylex=['id'=>(int)db()->lastInsertId()];}
  else query("UPDATE ns_categories SET slug='nylex',parent_id=NULL,sort_order=10 WHERE id=?",[$nylex['id']]);
  query("UPDATE ns_products SET category_id=? WHERE name LIKE '%نایلکس%'",[$nylex['id']]);
  foreach(['25-35'=>'۲۵ × ۳۵','30-40'=>'۳۰ × ۴۰','37-47'=>'۳۷ × ۴۷','45-55'=>'۴۵ × ۵۵','44-65'=>'۴۴ × ۶۵','65-80'=>'۶۵ × ۸۰'] as $slug=>$name){
   if(!one('SELECT id FROM ns_categories WHERE name=?',[$name]))query('INSERT INTO ns_categories(name,slug,parent_id,sort_order) VALUES (?,?,?,?)',[$name,'nylex-'.$slug,$nylex['id'],20]);
  }
  query("INSERT INTO ns_category_migrations (`key`) VALUES ('hierarchy-v1')");
 }
 $ready=true;
}

function category_tree(): array {
 ensure_category_schema();$rows=all('SELECT * FROM ns_categories ORDER BY sort_order,name,id');$tree=[];
 foreach($rows as $row)if(!$row['parent_id']){$row['children']=[];$tree[(int)$row['id']]=$row;}
 foreach($rows as $row)if($row['parent_id']&&isset($tree[(int)$row['parent_id']]))$tree[(int)$row['parent_id']]['children'][]=$row;
 return array_values($tree);
}

function category_options(?int $excludeId=null): array {
 $options=[];foreach(category_tree() as $parent){if((int)$parent['id']!==$excludeId)$options[]=['id'=>$parent['id'],'label'=>$parent['name']];foreach($parent['children'] as $child)if((int)$child['id']!==$excludeId)$options[]=['id'=>$child['id'],'label'=>'— '.$child['name']];}return $options;
}
