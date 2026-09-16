<?php
declare(strict_types=1);
function search_normalize(string $value): string {
 return trim(preg_replace('/\s+/u',' ',strtr(mb_strtolower(digits($value)),['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','آ'=>'ا','‌'=>' ','ـ'=>''])));
}
function search_distance(string $a,string $b): int {
 $a=preg_split('//u',$a,-1,PREG_SPLIT_NO_EMPTY);$b=preg_split('//u',$b,-1,PREG_SPLIT_NO_EMPTY);$row=range(0,count($b));
 foreach($a as $i=>$letter){$next=[$i+1];foreach($b as $j=>$other)$next[$j+1]=min($next[$j]+1,$row[$j+1]+1,$row[$j]+($letter===$other?0:1));$row=$next;}
 return $row[count($b)];
}
function search_score(string $term,string $name,string $category): ?int {
 $haystack=search_normalize($name.' '.$category);if($term==='')return 0;if(str_contains($haystack,$term))return 0;
 $words=explode(' ',$haystack);$score=0;
 foreach(explode(' ',$term) as $token){$best=100;foreach($words as $word){if(str_contains($word,$token)){$best=0;break;}if(mb_strlen($token)>=4&&abs(mb_strlen($token)-mb_strlen($word))<=1)$best=min($best,search_distance($token,$word));}if($best>1)return null;$score+=$best;}
 return $score+1;
}
function search_products(string $term,int $limit=6): array {
 $normalized=search_normalize($term);$brandQueries=['پلاستیک سادات','پلاستیکک سادات','پخش پلاستیک','پخش پلاستیکک','بازرگانی سادات پخش','سادات پخش','نایلکس سادات','فروش عمده','فروش پلاستیک','فروش نایلکس','محصولات پلاستیکی'];foreach($brandQueries as$brandQuery)if(str_contains($normalized,search_normalize($brandQuery))){$normalized='';break;}$like='%'.$normalized.'%';
 $sql='SELECT p.*,c.name category_name FROM ns_products p JOIN ns_categories c ON c.id=p.category_id WHERE p.active=1';
 $fetchLimit=min(500,max(6,$limit));$rows=$normalized===''?all($sql.' ORDER BY p.featured DESC,p.id DESC LIMIT '.$fetchLimit):all($sql." AND (REPLACE(REPLACE(p.name,'ي','ی'),'ك','ک') LIKE ? OR REPLACE(REPLACE(c.name,'ي','ی'),'ك','ک') LIKE ?) ORDER BY p.featured DESC,p.id DESC LIMIT ".$fetchLimit,[$like,$like]);
 // Fuzzy matching is deliberately bounded to 500 candidates.
 if($normalized!==''&&count($rows)<$limit){$seen=array_column($rows,null,'id');foreach(all($sql.' ORDER BY p.featured DESC,p.id DESC LIMIT 500') as $p)$seen[$p['id']]=$p;$rows=array_values($seen);}
 $ranked=[];foreach($rows as $p){$score=search_score($normalized,$p['name'],$p['category_name']);if($score!==null){$p['_score']=$score;$ranked[]=$p;}}
 usort($ranked,fn($a,$b)=>($a['_score']<=>$b['_score'])?:((int)$b['featured']<=>(int)$a['featured'])?:((int)$b['id']<=>(int)$a['id']));return array_slice($ranked,0,$limit);
}
