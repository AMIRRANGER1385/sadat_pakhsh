from playwright.sync_api import sync_playwright,expect
base='http://localhost:8085'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 page=browser.new_page(viewport={'width':390,'height':844})
 errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto(base+'/products');expect(page.locator('.price-updated')).to_be_visible();expect(page.locator('.price-updated')).to_contain_text('آخرین به‌روزرسانی قیمت‌ها')
 href=page.locator('.catalog-grid .product-card .product-image').first.get_attribute('href');assert href
 page.goto(base+href);toc=page.locator('.site-toc');expect(toc).to_be_visible();assert toc.locator('a').count()>=2
 target=toc.locator('a').first.get_attribute('href');toc.locator('a').first.click();expect(page).to_have_url(base+href+target)
 toggle=toc.locator('.toc-toggle');toggle.click();expect(toc.locator('ol')).to_be_hidden();toggle.click();expect(toc.locator('ol')).to_be_visible()
 page.goto(base+'/guides/nylon-vs-nylex');existing=page.locator('.guide-toc');expect(existing.locator('.toc-toggle')).to_be_visible();assert existing.locator('a').count()>=2
 page.screenshot(path='.runtime/toc-mobile.png',full_page=True)
 assert not errors,errors
 browser.close()
print('PASS price update date and collapsible linked TOC on product and guide pages')
