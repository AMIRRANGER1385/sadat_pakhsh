<?php
declare(strict_types=1);

// Native OOXML export: no formulas, macros or external relationships.
function product_catalog_xlsx(array $rows): string {
 $xml=fn($v)=>htmlspecialchars((string)$v,ENT_XML1|ENT_QUOTES,'UTF-8');
 $headers=product_catalog_headers();$last=count($rows)+1;
 $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="28"/><cols>';
 foreach([26,15,26,38,24,20,20,16,16,16,65,42,14,15,15,15,20,20,20,20] as$i=>$width)$sheet.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$width.'" customWidth="1"/>';
 $sheet.='</cols><sheetData>';
 foreach([array_combine($headers,$headers),...$rows] as$i=>$row){$rn=$i+1;$sheet.='<row r="'.$rn.'" ht="'.($i?48:32).'" customHeight="1">';foreach($headers as$j=>$key){$ref=chr(65+$j).$rn;$value=$row[$key]??'';$numeric=$i>0&&in_array($key,['retail_price','wholesale_price','threshold','stock','featured','tier_2_min','tier_3_min','tier_4_min','tier_price_1','tier_price_2','tier_price_3','tier_price_4'],true)&&is_numeric($value);$style=$i?($numeric?2:1):3;
  $sheet.='<c r="'.$ref.'" s="'.$style.'"'.($numeric?'':' t="inlineStr"').'>'.($numeric?'<v>'.$xml($value).'</v>':'<is><t xml:space="preserve">'.$xml($value).'</t></is>').'</c>';
 }$sheet.='</row>';}
 $sheet.='</sheetData><autoFilter ref="A1:T'.$last.'"/><printOptions horizontalCentered="1"/><pageSetup orientation="landscape" paperSize="9"/></worksheet>';
 $files=[
 '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
 '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
 'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="محصولات" sheetId="1" r:id="rId1"/></sheets></workbook>',
 'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
 'xl/styles.xml'=>'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Tahoma"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF24594C"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1" readingOrder="2"/></xf><xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
 'xl/worksheets/sheet1.xml'=>$sheet,
 ];
 $file=tempnam((string)config('storage'),'catalog-');if($file===false)throw new ShopError('ساخت فایل اکسل ممکن نشد.');unlink($file);$file.='.zip';
 try{$zip=new PharData($file,0,null,Phar::ZIP);foreach($files as$name=>$body)$zip[$name]=$body;unset($zip);$bytes=file_get_contents($file);if($bytes===false)throw new ShopError('خواندن خروجی اکسل ممکن نشد.');return $bytes;}finally{if(is_file($file))unlink($file);}
}
