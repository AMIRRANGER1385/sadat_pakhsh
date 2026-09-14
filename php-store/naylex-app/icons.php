<?php
declare(strict_types=1);
function category_icon(string $name): string {
 return match(true){
  str_contains($name,'فریزر')=>'freezer',
  str_contains($name,'زباله')=>'trash',
  str_contains($name,'سفره')=>'tablecloth',
  str_contains($name,'بسته')=>'box',
  str_contains($name,'نایل')||str_contains($name,'کیسه')=>'bag',
  default=>'box',
 };
}
function icon(string $name='bag'): string {
 $path=match($name){
 'search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
 'cart'=>'<path d="M2 3h3l3 12h11l3-9H6M8 15l-1 3h13"/><circle cx="9" cy="21" r="1"/><circle cx="19" cy="21" r="1"/>',
 'user'=>'<circle cx="12" cy="7" r="4"/><path d="M4 22v-3a8 8 0 0 1 16 0v3"/>',
 'arrow'=>'<path d="M20 12H4m6-6-6 6 6 6"/>',
 'menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>',
 'leaf'=>'<path d="M20 3C3 1 0 17 10 20c9 3 11-9 10-17ZM5 22 17 7"/>',
 'box'=>'<path d="m12 2 10 5v10l-10 5-10-5V7Zm0 10v10M2 7l10 5 10-5M7 4l10 5"/>',
 'freezer'=>'<path d="M12 2v20M3.3 7l17.4 10M3.3 17 20.7 7M9 4l3 3 3-3M9 20l3-3 3 3M3 10l4-1-1-4M18 19l-1-4 4-1M3 14l4 1-1 4M18 5l-1 4 4 1"/>',
 'trash'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>',
 'tablecloth'=>'<path d="M4 5h16l2 14-4-2-3 2-3-2-3 2-3-2-4 2ZM5 9h14M8 5l-1 10M12 5v10M16 5l1 10M4 13h16"/>',
 'truck'=>'<path d="M2 5h12v12H2ZM14 9h4l4 4v4h-8M18 9v4h4"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
 'shield'=>'<path d="m12 2 8 3v6c0 5-4 8-8 11-4-3-8-6-8-11V5ZM8 12l3 3 5-6"/>',
 'discount'=>'<path d="m3 3 9 0 10 10-9 9L3 12Z"/><circle cx="7.5" cy="7.5" r="1"/><path d="m10 15 6-6"/>',
 'support'=>'<path d="M4 13v-2a8 8 0 0 1 16 0v2M4 12H2v6h4v-6ZM20 12h2v6h-4v-6ZM20 18v3h-7"/>',
 default=>'<path d="M4 7h16v15H4ZM8 7V5a4 4 0 0 1 8 0v2"/>',
 };
 return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
}
