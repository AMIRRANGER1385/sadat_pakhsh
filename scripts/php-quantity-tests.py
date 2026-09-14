from playwright.sync_api import sync_playwright, expect
base='http://localhost:8085'
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 g=browser.new_page()
 for path in ['/products/product-1','/']:
  g.goto(base+path)
  step=g.locator('[data-quantity-control]').first
  expect(step.locator('[data-product-count]')).to_have_text('۰')
  for qty in ['۱','۲']:
   if path=='/products/product-1' and qty=='۱':
    expect(step.locator('[data-first-add]')).to_be_visible()
    expect(step.locator('.quantity-stepper')).to_be_hidden()
    step.locator('[data-first-add]').click()
   else:step.locator('[data-plus]').click()
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
  if path=='/products/product-1':expect(step.locator('[data-first-add]')).to_be_visible()
  print('PASS plus/minus, persistence, preview and removal: '+path)
 browser.close()
