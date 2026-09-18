import json, subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

base='http://localhost:8085'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 page=browser.new_page(viewport={'width':390,'height':844})
 errors=[]
 page.on('pageerror',lambda e: errors.append(str(e)))
 for mode,path in [('retail','/products'),('wholesale','/wholesale')]:
  response=page.goto(base+path)
  assert response.status==200
  expect(page.locator('.sales-navigation')).to_be_visible()
  assert page.locator('[name=buy]').input_value()==mode
 # Exercise the real cart renderer against both boundary directions.
 page.goto(base+'/products')
 page.evaluate('''() => {
  const box=document.createElement('aside');box.dataset.wholesaleRecommendation='';
  box.dataset.productId='999999';box.dataset.threshold='25';box.hidden=true;
  box.textContent='Wholesale recommendation';document.body.append(box);
 }''')
 for qty,visible in [(24,False),(25,True),(26,True),(24,False),(0,False)]:
  page.evaluate('''qty => renderCart({count:qty,total:qty*100,items:qty?[{id:999999,name:'Test',description:'',image:'/products/product-1.svg',quantity:qty,price:100}]:[]})''',qty)
  box=page.locator('[data-product-id="999999"]')
  assert box.is_visible()==visible
 assert not errors,errors
 page.screenshot(path='.runtime/product-sales-mobile.png',full_page=True)
 browser.close()
 print('PASS mobile retail/wholesale pages and live 24/25/26/decrease/remove transitions')

fixture=[r'C:\xampp\php\php.exe','scripts/public-wholesale-fixture.php']
products=json.loads(subprocess.check_output(fixture,text=True))
try:
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  page=browser.new_page(viewport={'width':390,'height':844})
  # A new guest context: no login or pre-existing session.
  page.goto(base+'/wholesale?q=PublicWholesaleFixture&sort=low')
  cards=page.locator('.catalog-grid .product-card')
  assert cards.count()==2
  assert products[2]['slug'] in cards.first.locator('.product-image').get_attribute('href')
  expect(cards.first.locator('.product-bottom')).to_contain_text('۶۰')
  expect(page.locator('.filter-panel')).to_have_attribute('action','/wholesale')
  page.goto(base+'/wholesale?max=70')
  assert page.locator(f'.catalog-grid a[href="/products/{products[2]["slug"]}"]').count()==2
  assert page.locator(f'.catalog-grid a[href="/products/{products[1]["slug"]}"]').count()==0
  page.goto(base+'/products?q=PublicWholesaleFixture')
  assert page.locator('.catalog-grid .product-card').count()==1
  assert products[0]['slug'] in page.locator('.catalog-grid .product-image').get_attribute('href')
  page.goto(base+'/wholesale?category='+str(products[1]['category']))
  assert '/wholesale?' in page.url
  for old in ['/wholesale-panel','/products?buy=wholesale']:
   page.goto(base+old)
   assert page.url==base+'/wholesale'
  page.goto(base+'/wholesale?q=PublicWholesaleFixture')
  page.screenshot(path='.runtime/public-wholesale-mobile.png',full_page=True)
  page.goto(base+'/products/'+products[1]['slug'])
  expect(page.locator('.price-box')).to_contain_text('قیمت عمده')
  expect(page.locator('.price-box strong')).to_contain_text('۸۰')
  schemas=page.locator('script[type="application/ld+json"]').all_text_contents()
  offer=next(json.loads(s) for s in schemas if json.loads(s).get('@type')=='Product')
  assert offer['offers']['price']==800
  control=page.locator('[data-quantity-control]').filter(has=page.locator(f'input[name="id"][value="{products[1]["id"]}"]')).first
  control.locator('[data-first-add]').click()
  expect(control.locator('[data-product-count]')).to_have_text('۱')
  page.goto(base+'/cart')
  expect(page.locator('.cart-item-name')).to_contain_text('۸۰')
  browser.close()
 print('PASS guest wholesale catalogue, separation, price filters/sort, legacy routes, detail/schema and cart price')
finally:
 subprocess.run(fixture+['cleanup'],check=True)
