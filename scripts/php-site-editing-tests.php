<?php
declare(strict_types=1);
require __DIR__.'/../php-store/naylex-app/core.php';
require __DIR__.'/../php-store/naylex-app/content.php';
$config=require __DIR__.'/../php-store/naylex-app/config.php';
if(config('db_name')!=='naylex_local'||config('db_port')!==33077)exit(1);
$key='qa-font-'.bin2hex(random_bytes(8));
try{
 $_POST=['font_size'=>'24'];save_content_font($key);
 if(content_font_size($key)!==24||content_font_attr($key)!==' style="font-size:24px"')throw new RuntimeException('Font persistence failed');
 $_POST=['font_size'=>'0'];save_content_font($key);if(content_font_attr($key)!=='')throw new RuntimeException('Font reset failed');
 foreach(['11','49','24px','"><script>'] as$value){$_POST=['font_size'=>$value];try{save_content_font($key);throw new RuntimeException('Invalid font accepted');}catch(ShopError){}}
 echo "PASS font size persistence, reset and invalid input rejection\n";
}finally{query('DELETE FROM ns_content_blocks WHERE `key`=?',['font.'.$key]);}
