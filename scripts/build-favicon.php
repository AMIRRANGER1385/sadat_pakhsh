<?php
// Render the store's vector-style shopping-bag mark into standard favicon formats.
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=__DIR__.'/../php-store/public_html';
$canvas=imagecreatetruecolor(512,512);
$green=imagecolorallocate($canvas,36,89,76);$cream=imagecolorallocate($canvas,247,246,240);$gold=imagecolorallocate($canvas,231,182,104);
imagefill($canvas,0,0,$green);imagesetthickness($canvas,28);
imagerectangle($canvas,128,192,384,424,$cream);
imagefilledellipse($canvas,256,144,156,156,$cream);
imagefilledellipse($canvas,256,144,100,100,$green);
imagefilledrectangle($canvas,178,144,206,192,$cream);
imagefilledrectangle($canvas,306,144,334,192,$cream);
imagefilledrectangle($canvas,207,144,305,177,$green);
imagesetthickness($canvas,25);imageline($canvas,192,296,240,344,$gold);imageline($canvas,240,344,328,248,$gold);
foreach([96=>'favicon-96.png',180=>'apple-touch-icon.png',512=>'logo.png'] as $size=>$name){
 $out=imagecreatetruecolor($size,$size);imagecopyresampled($out,$canvas,0,0,0,0,$size,$size,512,512);
 imagepng($out,$root.'/assets/'.$name);imagedestroy($out);
}
// ICO permits a PNG image entry; the 96px PNG remains the explicitly declared favicon.
$out=imagecreatetruecolor(48,48);imagecopyresampled($out,$canvas,0,0,0,0,48,48,512,512);
ob_start();imagepng($out);$icoPng=ob_get_clean();
file_put_contents($root.'/favicon.ico',pack('vvv',0,1,1).pack('CCCCvvVV',48,48,0,0,1,32,strlen($icoPng),22).$icoPng);
imagedestroy($out);imagedestroy($canvas);echo "Built favicon PNG, ICO, touch icon and brand logo.\n";
