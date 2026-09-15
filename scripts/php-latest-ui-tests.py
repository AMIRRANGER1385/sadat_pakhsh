import secrets
from playwright.sync_api import sync_playwright,expect
base='http://localhost:8085'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True);page=browser.new_page()
 page.goto(base+'/');page.evaluate('window.scrollTo(0,600)')
 assert abs(page.locator('.header').bounding_box()['y'])<2
 assert page.locator('.header-actions a[href="/register"]').count()==0
 page.goto(base+'/products/product-1');page.locator('[data-plus]').click();expect(page.locator('[data-product-count]')).to_have_text('۱')
 page.goto(base+'/cart');page.locator('[data-plus]').click();expect(page.locator('[data-product-count]')).to_have_text('۲')
 page.wait_for_load_state();page.reload();expect(page.locator('[data-product-count]')).to_have_text('۲')
 page.locator('[data-minus]').click();expect(page.locator('[data-product-count]')).to_have_text('۱')
 page.goto(base+'/register');name='UI '+secrets.token_hex(8);phone='09'+''.join(str(secrets.randbelow(10)) for _ in range(9))
 def fill(number):
  page.locator('[name=name]').fill(name);page.locator('[name=username]').fill(number);page.locator('[name=password]').fill('a1234567');page.get_by_role('button',name='ساخت حساب کاربری',exact=True).click()
 fill(phone);expect(page).to_have_url(base+'/account')
 page.get_by_role('button',name='خروج از حساب',exact=True).click();page.goto(base+'/register');fill(phone)
 expect(page.get_by_role('status')).to_contain_text('شما قبلاً حساب دارید')
 page.goto(base+'/login');page.locator('[name=username]').fill(phone);page.locator('[name=password]').fill('a1234567');page.get_by_role('button',name='ورود به حساب',exact=True).click();expect(page).to_have_url(base+'/account')
 print('PASS sticky header, single registration entry, cart +/-, 8-character password, duplicate warning and login')
 browser.close()
