import json,subprocess,secrets,csv,io
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
base='http://localhost:8085'
php=[r'C:\xampp\php\php.exe','-d','sys_temp_dir=D:/sadat_pakhsh/.runtime/php-upload-tmp']
name='article-test-'+secrets.token_hex(8)
fixture=php+['scripts/article-test-fixture.php']
creds=json.loads(subprocess.check_output(fixture+['create',name]))
rows=list(csv.reader(Path('php-store/public_html/templates/products.csv').read_text(encoding='utf-8-sig').splitlines()))
rows[1][0]=name+'-retail';rows[1][1]=name+'-bulk'
out=io.StringIO();csv.writer(out).writerows(rows)
try:
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  page=browser.new_page(viewport={'width':1400,'height':1000})
  page.goto(base+'/login');page.locator('[name=username]').fill(creds['username']);page.locator('[name=password]').fill(creds['password']);page.locator('.auth-form button[type=submit],.auth-form button.btn').first.click()
  page.goto(base+'/admin?tab=import')
  page.locator('[name=products_file]').set_input_files({'name':'products.csv','mimeType':'text/csv','buffer':out.getvalue().encode('utf-8-sig')})
  page.locator('form').filter(has=page.locator('[name=products_file]')).locator('button').click()
  expect(page.locator('main')).to_contain_text('پیش‌نمایش')
  response=page.request.get(base+'/products/'+name+'-retail');assert response.status==404,'preview must not write products'
  page.locator('form').filter(has=page.locator('[value=product_import_commit]')).locator('button').click()
  expect(page).to_have_url(base+'/admin?tab=products')
  guest=browser.new_context(viewport={'width':390,'height':844});g=guest.new_page();g.goto(base+'/products/'+name+'-retail')
  control=g.locator('.detail-grid [data-quantity-control]').first
  card=g.locator('.detail-grid [data-wholesale-recommendation]').first
  for qty,visible,price in [(24,False,'۱۲۰٬۰۰۰'),(25,True,'۹۵٬۰۰۰'),(26,True,'۹۵٬۰۰۰'),(24,False,'۱۲۰٬۰۰۰'),(25,True,'۹۵٬۰۰۰')]:
   control.locator('[data-quantity-input]').fill(str(qty));control.locator('[value=cart_set]').click()
   expect(control.locator('[data-product-count]')).to_have_text(str(qty).translate(str.maketrans('0123456789','۰۱۲۳۴۵۶۷۸۹')))
   if visible:expect(card).to_be_visible()
   else:expect(card).to_be_hidden()
   expect(control.locator('[data-current-price]')).to_contain_text(price)
  expect(card.locator('a')).to_have_attribute('href','/products/'+name+'-bulk')
  g.screenshot(path='.runtime/import-wholesale-mobile.png',full_page=True)
  g.goto(base+'/cart');expect(g.locator('[data-wholesale-recommendation]').first).to_be_visible();expect(g.locator('.cart-item-name')).to_contain_text('۹۵,۰۰۰')
  browser.close()
 print('PASS admin upload-preview-confirm and guest 24/25/26 pricing and linked wholesale card')
finally:
 subprocess.run(php+['scripts/php-product-import-tests.php','cleanup',name],check=True)
 subprocess.run(fixture+['cleanup',name],check=True)
