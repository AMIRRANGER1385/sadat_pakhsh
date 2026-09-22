from pathlib import Path
import os
from playwright.sync_api import sync_playwright

BASE = os.environ.get('NAYLEX_TEST_URL', 'http://localhost:8086')
OUT = Path(__file__).resolve().parents[1] / 'dist'
OUT.mkdir(exist_ok=True)

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, label in [(1440, 900, 'desktop'), (390, 844, 'mobile')]:
        page = browser.new_page(viewport={'width': width, 'height': height}, device_scale_factor=1)
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        response = page.goto(BASE, wait_until='networkidle')
        assert response.status == 200
        assert page.locator('.hero-premium h1').count() == 1
        assert page.locator('.material-step').count() == 4
        assert page.locator('.category-showcase .category-tile').count() > 0
        page.locator('.material-story').scroll_into_view_if_needed()
        page.wait_for_timeout(750)
        assert page.locator('.material-step.is-visible').count() == 4
        page.evaluate('window.scrollTo(0, 0)')
        page.wait_for_timeout(150)
        page.screenshot(path=str(OUT / f'home-{label}-preview.png'), full_page=True)
        options = page.locator('[data-size-option]')
        assert options.count() > 0
        options.last.click()
        assert options.last.get_attribute('aria-pressed') == 'true', {'pressed': options.last.get_attribute('aria-pressed'), 'errors': errors}
        assert page.locator('[data-size-link]').get_attribute('href')
        assert not errors, errors
        assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), 'horizontal overflow'
        print(f'PASS homepage {label}: hero, story, categories, size finder, no JS errors or overflow')
        page.close()
    static = browser.new_page(viewport={'width': 1440, 'height': 900}, java_script_enabled=False)
    assert static.goto(BASE, wait_until='load').status == 200
    assert static.locator('.material-step').first.is_visible(), 'story hidden without JavaScript'
    assert static.locator('.hero-premium h1').is_visible(), 'hero hidden without JavaScript'
    static.close()
    reduced = browser.new_page(viewport={'width': 390, 'height': 844}, reduced_motion='reduce')
    assert reduced.goto(BASE, wait_until='networkidle').status == 200
    assert reduced.locator('.material-step').first.is_visible(), 'story hidden with reduced motion'
    reduced.close()
    print('PASS readable without JavaScript and with reduced-motion preference')
    browser.close()
