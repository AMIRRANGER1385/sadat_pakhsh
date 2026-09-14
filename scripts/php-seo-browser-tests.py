from playwright.sync_api import sync_playwright,expect
base='http://localhost:8085'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 for width,height in [(390,844),(1366,900)]:
  page=browser.new_page(viewport={'width':width,'height':height});errors=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(base+'/');expect(page.locator('h1')).to_have_count(1)
  category=page.locator('.category-tile').first;category.click()
  assert '/categories/' in page.url
  expect(page.locator('h1')).to_have_count(1)
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+2')
  page.locator('.product-image').first.click()
  expect(page.locator('h1')).to_have_count(1)
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+2')
  page.locator('[data-first-add]').click()
  expect(page.locator('.detail-grid [data-product-count]')).to_have_text('۱')
  assert not errors,errors
  page.close()
 print('PASS mobile/desktop category navigation, no horizontal overflow, product purchase, no JS errors')
 browser.close()
