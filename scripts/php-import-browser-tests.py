import csv, io, json, secrets, subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

base = 'http://localhost:8085'
php = [r'C:\xampp\php\php.exe', '-d', 'sys_temp_dir=D:/sadat_pakhsh/.runtime/php-upload-tmp']
name = 'article-test-' + secrets.token_hex(8)
fixture = php + ['scripts/article-test-fixture.php']
creds = json.loads(subprocess.check_output(fixture + ['create', name]))
rows = list(csv.reader(Path('php-store/public_html/templates/products.csv').read_text(encoding='utf-8-sig').splitlines()))
rows[1][0] = name + '-retail'; rows[1][2] = name + '-bulk'
rows[2][0] = name + '-bulk'
out = io.StringIO(); csv.writer(out).writerows(rows)
try:
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={'width': 1400, 'height': 1000})
        page.goto(base + '/login')
        page.locator('[name=username]').fill(creds['username'])
        page.locator('[name=password]').fill(creds['password'])
        page.locator('.auth-form button[type=submit],.auth-form button.btn').first.click()
        page.goto(base + '/admin?tab=import')
        expect(page.locator('main')).to_contain_text('دانلود Excel')
        expect(page.locator('main')).to_contain_text('دانلود CSV')
        page.locator('[name=products_file]').set_input_files({'name': 'products.csv', 'mimeType': 'text/csv', 'buffer': out.getvalue().encode('utf-8-sig')})
        page.locator('form').filter(has=page.locator('[name=products_file]')).locator('button').click()
        expect(page.locator('main')).to_contain_text('پیش‌نمایش')
        assert page.request.get(base + '/products/' + name + '-retail').status == 404, 'preview must not write products'
        page.locator('form').filter(has=page.locator('[value=product_import_commit]')).locator('button').click()
        expect(page).to_have_url(base + '/admin?tab=products')

        guest = browser.new_context(viewport={'width': 390, 'height': 844})
        product = guest.new_page(); product.goto(base + '/products/' + name + '-bulk')
        selector = product.locator('[data-wholesale-selector]')
        expect(selector.locator('[data-wholesale-count]')).to_have_text('۲۵ کیلو')
        expect(selector.locator('[data-wholesale-unit-price]')).to_contain_text('۸۰٬۰۰۰')
        selector.locator('[data-wholesale-minus]').click()
        expect(selector.locator('[data-wholesale-count]')).to_have_text('۲۴ کیلو')
        expect(selector.locator('[data-wholesale-unit-price]')).to_contain_text('۹۰٬۰۰۰')
        expect(selector.locator('[data-wholesale-total]')).to_contain_text('۲٬۱۶۰٬۰۰۰')
        selector.locator('[data-wholesale-plus]').click()
        expect(selector.locator('[data-wholesale-count]')).to_have_text('۲۵ کیلو')
        expect(product.locator('table').filter(has_text='قیمت هر کیلو').first).to_be_visible()
        browser.close()
    print('PASS admin CSV upload preview/commit and wholesale 24/25 kg tier pricing')
finally:
    subprocess.run(php + ['scripts/php-product-import-tests.php', 'cleanup', name], check=False)
    subprocess.run(fixture + ['cleanup', name], check=True)
