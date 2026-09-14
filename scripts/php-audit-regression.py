"""Read-only and anonymous-cart security checks against the isolated local server."""
import hashlib
import http.cookiejar
import re
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = 'http://localhost:8085'
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def request(path, data=None, headers=None):
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, headers=headers or {})
    try:
        response = opener.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read().decode('utf-8'), response.headers, response.url

status, html, headers, _ = request('/')
assert status == 200
assert 'no-store' in headers['Cache-Control']
assert "frame-ancestors 'none'" in headers['Content-Security-Policy']
assert headers['X-Content-Type-Options'] == 'nosniff'
assert 'HttpOnly' in headers['Set-Cookie'] and 'SameSite=Lax' in headers['Set-Cookie']
csrf = re.search(r'name="_csrf" value="([a-f0-9]+)"', html)[1]
for name in ['shop.css', 'shop.js']:
    digest = hashlib.sha256((Path('php-store/public_html/assets') / name).read_bytes()).hexdigest()[:16]
    assert f'/assets/{name}?v={digest}' in html
print('PASS private HTML, session flags, CSP, content-hashed assets')

for path, data, origin in [
    ('/', {'action': 'cart_add', 'id': '1', 'quantity': '1'}, BASE),
    ('/', {'action': 'cart_add', 'id': '1', 'quantity': '1', '_csrf': csrf}, 'https://example.invalid'),
    ('/admin/upload', {'_csrf': csrf}, BASE),
    ('/', {'action': 'product_delete', 'id': '1', '_csrf': csrf}, BASE),
]:
    assert request(path, data, {'Origin': origin})[0] == 403
print('PASS missing CSRF, foreign origin, anonymous upload and admin mutation rejected')

for qty in ['-1', '1001', '1 OR 1=1']:
    assert request('/', {'action': 'cart_add', 'id': '1', 'quantity': qty, '_csrf': csrf}, {'Origin': BASE})[0] == 400
status, body, _, _ = request('/products?q=' + urllib.parse.quote('<script>alert(1)</script>'))
assert status == 200 and '<script>alert(1)</script>' not in body
assert request('/products/?q=freezer')[3] == BASE + '/products?q=freezer'
assert request('/media?name=../../config.php')[0] == 404
print('PASS invalid quantities, reflected XSS escaping, canonical redirect, media traversal')
