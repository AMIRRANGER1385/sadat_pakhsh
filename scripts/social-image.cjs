const sharp=require('sharp');
sharp('public/products/hero.svg').resize(1200,630,{fit:'contain',background:'#f7f6f0'}).png().toFile('public/social-cover.png').then(()=>console.log('Social cover generated.'));
