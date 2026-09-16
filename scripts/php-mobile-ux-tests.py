"""Mobile viewport regression checks against the isolated local PHP store."""
from playwright.sync_api import sync_playwright

BASE = "http://localhost:8085"
WIDTHS = [320, 375, 390, 430, 768]

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width in WIDTHS:
        page = browser.new_page(viewport={"width": width, "height": 900})
        for path in ["/", "/products/product-1", "/cart", "/checkout", "/login", "/shipping", "/does-not-exist"]:
            response = page.goto(BASE + path)
            assert response and response.status in (200, 404), (width, path, response.status if response else None)
            overflow = page.evaluate("document.documentElement.scrollWidth > document.documentElement.clientWidth + 1")
            assert not overflow, (width, path, "horizontal overflow")
        page.goto(BASE + "/products/product-1")
        add = page.locator("[data-first-add]")
        if add.is_visible() and add.is_enabled():
            box = add.bounding_box()
            assert box and box["height"] >= 44
            add.click()
            step = page.locator(".quantity-stepper").first
            step.wait_for(state="visible")
            for button in step.locator("button").all():
                box = button.bounding_box()
                assert box and box["height"] >= 44 and box["width"] >= 44
        page.goto(BASE + "/")
        menu = page.locator("[data-menu]")
        if width <= 430:
            assert menu.is_visible()
            menu.click()
            assert page.locator(".nav").is_visible()
        search = page.locator("[data-product-search]")
        search.fill("کیسه")
        page.wait_for_timeout(500)
        assert page.locator("[data-search-suggestions]").is_visible()
        page.close()
        print(f"PASS mobile UX {width}px")
    browser.close()
