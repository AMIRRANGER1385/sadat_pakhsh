import re
from playwright.sync_api import sync_playwright, expect

BASE='http://localhost:8085'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 page=browser.new_page(viewport={'width':390,'height':844})
 errors=[];page.on('pageerror',lambda error:errors.append(str(error)))
 for path,h1 in [('/nylex','خرید نایلکس و مشاهده قیمت محصولات'),('/wholesale','خرید عمده نایلکس مستقیم از تولیدکننده'),('/nylex-manufacturer','تولیدکننده نایلکس و فروش مستقیم')]:
  response=page.goto(BASE+path);assert response and response.status==200
  expect(page.locator('h1')).to_have_count(1);expect(page.locator('h1')).to_contain_text(h1)
  expect(page.locator('link[rel=canonical]')).to_have_attribute('href',BASE+path)
  expect(page.locator('meta[name=robots]')).to_have_attribute('content',re.compile(r'^index,follow'))
  assert page.locator('script[type="application/ld+json"]').count()>=2
  assert page.locator('main a[href="/wholesale"]').count() or path=='/wholesale'
  assert page.locator('body').evaluate('(node)=>node.scrollWidth<=window.innerWidth')
 page.goto(BASE+'/wholesale-buying');expect(page).to_have_url(BASE+'/wholesale')
 assert not errors,errors
 desktop=browser.new_page(viewport={'width':1440,'height':900})
 desktop.goto(BASE+'/')
 expect(desktop.locator('.category-mega')).not_to_be_visible()
 desktop.locator('[data-category-toggle]').hover()
 expect(desktop.locator('.category-mega')).to_be_visible()
 expect(desktop.locator('.category-mega .category-parent').first).to_have_text('نایلکس')
 for size in ['۲۵ × ۳۵','۳۰ × ۴۰','۳۷ × ۴۷','۴۵ × ۵۵','۴۴ × ۶۵','۶۵ × ۸۰']:
  expect(desktop.locator('.category-mega')).to_contain_text(size)
 page.goto(BASE+'/');page.locator('[data-menu]').click();page.locator('[data-category-toggle]').click()
 expect(page.locator('.category-mega')).to_be_visible()
 assert page.locator('body').evaluate('(node)=>node.scrollWidth<=window.innerWidth')
 browser.close()
print('PASS landing SEO, wholesale redirect, and responsive category mega-menu')
