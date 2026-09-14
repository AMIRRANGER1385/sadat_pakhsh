"""Real Chromium forms against the isolated local PHP store; never production."""
import json,secrets
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
root=Path(__file__).resolve().parents[1]
base='http://localhost:8085'
credentials=json.loads((root/'.runtime/php-local-admin.json').read_text())
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 context=browser.new_context()
 page=context.new_page()
 # Reproduce the old policy using only browser response interception.
 def old_policy(route):
  response=route.fetch()
  route.fulfill(response=response,headers={**response.headers,'referrer-policy':'no-referrer'})
 page.route(base+'/login',old_policy)
 page.goto(base+'/login')
 page.locator('[name=username]').fill(credentials['username'])
 page.locator('[name=password]').fill(credentials['password'])
 with page.expect_request(lambda r:r.method=='POST') as request:
  page.get_by_role('button',name='ورود به حساب',exact=True).click()
 assert request.value.headers.get('origin')=='null'
 assert 'مبدأ درخواست مجاز نیست' in page.content()
 print('PASS reproduced browser Origin:null failure with old policy')
 page.unroute(base+'/login',old_policy)
 page.goto(base+'/login')
 page.locator('[name=username]').fill(credentials['username'])
 page.locator('[name=password]').fill(credentials['password'])
 page.get_by_role('button',name='ورود به حساب',exact=True).click()
 page.wait_for_url(base+'/admin')
 print('PASS admin browser login and persisted session')
 page.get_by_role('link',name='+ افزودن محصول با عکس',exact=True).click()
 assert page.locator('[name=photo]').count()==1
 assert page.locator('[name=stock]').count()==1
 print('PASS admin product editor with image and stock')
 g=browser.new_page()
 for path in ['/products/product-1','/']:
  g.goto(base+path)
  step=g.locator('[data-quantity-control]').first
  expect(step.locator('[data-product-count]')).to_have_text('۰')
  for qty in ['۱','۲']:
   step.locator('[data-plus]').click()
   expect(step.locator('[data-product-count]')).to_have_text(qty)
  assert g.url==base+path
  g.reload()
  expect(step.locator('[data-product-count]')).to_have_text('۲')
  g.locator('.cart-preview-wrap').hover()
  assert g.locator('#cart-preview').is_visible()
  assert g.locator('.cart-preview-item').count()==1
  for qty in ['۱','۰']:
   step.locator('[data-minus]').click()
   expect(step.locator('[data-product-count]')).to_have_text(qty)
  expect(step.locator('[data-minus]')).to_be_disabled()
  assert g.locator('.cart-preview-item').count()==0
  print('PASS plus/minus, persistence, preview and removal: '+path)
 g.goto(base+'/register')
 g.locator('[name=name]').fill('آزمون مرورگر')
 phone='09'+''.join(str(secrets.randbelow(10)) for _ in range(9))
 password=secrets.token_urlsafe(20)
 g.locator('[name=username]').fill(phone)
 g.locator('[name=password]').fill(password)
 g.get_by_role('button',name='ساخت حساب کاربری',exact=True).click()
 g.wait_for_url(base+'/account')
 assert 'حساب عمده: عادی' in g.content()
 print('PASS normal customer browser registration')
 g.get_by_role('button',name='خروج از حساب',exact=True).click()
 g.goto(base+'/login')
 g.locator('[name=username]').fill(phone)
 g.locator('[name=password]').fill(password)
 g.get_by_role('button',name='ورود به حساب',exact=True).click()
 g.wait_for_url(base+'/account')
 print('PASS customer logout and subsequent login')
 g.locator('[name=current_password]').fill(password)
 new_password=secrets.token_urlsafe(20)
 g.locator('[name=new_password]').fill(new_password)
 g.get_by_role('button',name='تغییر رمز و بستن نشست‌های قبلی',exact=True).click()
 assert 'رمز تغییر کرد' in g.content()
 print('PASS browser password change')
 browser.close()
