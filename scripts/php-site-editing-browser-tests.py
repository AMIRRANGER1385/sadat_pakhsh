import json, subprocess, secrets, zipfile, io
from playwright.sync_api import sync_playwright, expect
BASE='http://localhost:8085'
PHP=[r'C:\xampp\php\php.exe','-d','sys_temp_dir=D:/sadat_pakhsh/.runtime/php-upload-tmp']
name='article-test-'+secrets.token_hex(8)
fixture=PHP+['scripts/article-test-fixture.php']
creds=json.loads(subprocess.check_output(fixture+['create',name]))
try:
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  page=browser.new_page(viewport={'width':1360,'height':960})
  page.goto(BASE+'/about')
  expect(page.locator('h1')).to_contain_text('درباره نایلکس سادات')
  assert page.locator('link[rel=canonical]').get_attribute('href')==BASE+'/about'
  assert page.request.get(BASE+'/nylex-manufacturer',max_redirects=0).status==301
  expect(page.locator('header nav a[href="/track"]')).to_be_visible()
  assert page.locator('header nav a[href="/nylex-manufacturer"]').count()==0
  page.goto(BASE+'/terms');assert page.locator('[name=font_size]').count()==0
  assert page.request.get(BASE+'/admin/products-export?page=1').status in [401,403]
  page.goto(BASE+'/login');page.locator('[name=username]').fill(creds['username']);page.locator('[name=password]').fill(creds['password']);page.locator('.auth-form button[type=submit],.auth-form button.btn').first.click()
  page.goto(BASE+'/terms');expect(page.locator('[name=font_size]')).to_have_count(1)
  page.locator('.inline-editor summary').click();expect(page.locator('[name=font_size]')).to_be_visible()
  page.goto(BASE+'/admin?tab=typography');assert page.locator('[data-font-size]').count()==4
  page.goto(BASE+'/admin?tab=seo');expect(page.locator('[name=google_verification]')).to_be_visible()
  page.goto(BASE+'/admin?tab=import');expect(page.locator('[name=import_mode]')).to_have_value('update')
  response=page.request.get(BASE+'/admin/products-export?page=1');assert response.status==200,(response.status,response.text()[-1200:])
  assert '.xlsx' in response.headers['content-disposition']
  with zipfile.ZipFile(io.BytesIO(response.body())) as z:
   sheet=z.read('xl/worksheets/sheet1.xml').decode()
   assert 'rightToLeft="1"' in sheet and 'state="frozen"' in sheet and '<autoFilter' in sheet
  page.goto(BASE+'/about');page.screenshot(path='.runtime/about-updated-desktop.png',full_page=True)
  page.set_viewport_size({'width':390,'height':844});page.goto(BASE+'/terms')
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
  page.screenshot(path='.runtime/terms-updated-mobile.png',full_page=True)
  browser.close()
 print('PASS merged about redirect, tracking link, admin-only font controls, SEO settings and styled XLSX download')
finally:subprocess.run(fixture+['cleanup',name],check=True)
