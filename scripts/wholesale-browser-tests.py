"""Isolated local integration checks; no live-site accounts or orders."""
import json, subprocess
from playwright.sync_api import sync_playwright, expect
BASE='http://localhost:8085'
PHP=[r'C:\xampp\php\php.exe','scripts/wholesale-fixture.php']
account=json.loads(subprocess.check_output(PHP,text=True))
try:
 with sync_playwright() as p:
  browser=p.chromium.launch(headless=True)
  page=browser.new_page(viewport={'width':390,'height':844})
  errors=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(BASE+'/wholesale')
  expect(page.get_by_role('heading',level=1)).to_contain_text('خرید عمده')
  assert 'noindex' not in page.locator('meta[name=robots]').get_attribute('content')
  page.get_by_role('link',name='ساخت حساب و خرید عمده',exact=True).click()
  expect(page).to_have_url(BASE+'/register')
  page.goto(BASE+'/faq')
  schema=[json.loads(x) for x in page.locator('script[type="application/ld+json"]').all_text_contents()]
  faq=next(x for x in schema if x.get('@type')=='FAQPage')
  assert len(faq['mainEntity'])==3
  for q in faq['mainEntity']:
   expect(page.get_by_text(q['name'],exact=True)).to_be_visible()
   assert q['acceptedAnswer']['text'] in page.content()
  page.goto(BASE+'/')
  search=page.locator('[data-product-search]');search.focus()
  expect(page.locator('.search-suggestion').first).to_be_visible()
  search.fill('نايلكس')
  expect(page.locator('.search-suggestion').first).to_be_visible()
  search.fill('نایلکص')
  expect(page.locator('.search-suggestion').first).to_be_visible()
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
  for slug in ['nylon-vs-nylex','nylex-count-per-kilo','nylex-thickness-guide']:
   assert page.goto(BASE+'/guides/'+slug).status==200
  page.goto(BASE+'/login')
  page.locator('[name=username]').fill(account['username']);page.locator('[name=password]').fill(account['password'])
  page.get_by_role('button',name='ورود به حساب',exact=True).click()
  expect(page).to_have_url(BASE+'/account')
  assert page.goto(BASE+'/wholesale-panel').status==200
  expect(page.get_by_role('heading',level=1)).to_contain_text('پنل فروش عمده')
  expect(page.get_by_role('heading',name='سفارش سریع عمده',exact=True)).to_be_visible()
  expect(page.get_by_role('heading',name='کاتالوگ قیمت همکاری',exact=True)).to_be_visible()
  expect(page.get_by_role('heading',name='سفارش‌های اخیر',exact=True)).to_be_visible()
  assert page.locator('.wholesale-stats article').count()==4
  assert page.locator('.wholesale-product').count()>0
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
  assert not errors,errors
  page.screenshot(path='.runtime/wholesale-mobile.png',full_page=True)
  browser.close()
 print('PASS FAQ schema/content, blank policies, public wholesale, unified account panel, mobile search, guides and JS errors')
finally:
 subprocess.check_call(PHP+['remove',str(account['id'])])
