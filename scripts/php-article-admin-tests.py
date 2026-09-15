import http.cookiejar
import json
import re
import urllib.parse
import urllib.request
import subprocess
import secrets
import atexit

BASE = 'http://localhost:8085'
slug = 'article-test-' + secrets.token_hex(8)
fixture = [r'C:\xampp\php\php.exe', 'scripts/article-test-fixture.php']
credentials = json.loads(subprocess.check_output(fixture + ['create', slug]))
atexit.register(lambda: subprocess.run(fixture + ['cleanup', slug], check=True))
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def request(path, fields=None):
    data = urllib.parse.urlencode(fields).encode() if fields else None
    response = opener.open(urllib.request.Request(BASE + path, data=data, headers={'Origin': BASE} if fields else {}), timeout=20)
    return response.url, response.read().decode('utf-8')

def csrf(page):
    match = re.search(r'name="_csrf" value="([a-f0-9]+)"', page)
    assert match, 'missing CSRF token'
    return match.group(1)

_, page = request('/login')
_, page = request('/login', {'_csrf': csrf(page), 'action': 'login', 'username': credentials['username'], 'password': credentials['password']})
assert '/admin' in page or '<title>مدیریت' in page
_, page = request('/admin?tab=articles&edit=0')
token = csrf(page)
_, page = request('/admin', {
    '_csrf': token, 'action': 'article', 'id': '0', 'title': 'مقاله آزمایشی پنل مدیریت', 'slug': slug,
    'excerpt': 'این خلاصه آزمایشی برای بررسی پنل مدیریت مقاله و نمایش امن آن در محیط محلی نوشته شده است.',
    'body': 'این متن آزمایشی برای بررسی ذخیره، نمایش و حذف مقاله در پنل مدیریت نوشته شده است. داده آزمایشی پس از پایان آزمون پاک می‌شود.',
    'image': '/products/hero.svg', 'category_id': '1', 'active': 'on'
})
assert 'مقاله آزمایشی پنل مدیریت' in page
_, article = request('/articles/' + slug)
assert '<h1>مقاله آزمایشی پنل مدیریت</h1>' in article and 'Article' in article
_, page = request('/admin?tab=articles')
row = next(row for row in page.split('</tr>') if 'مقاله آزمایشی پنل مدیریت' in row)
article_id = re.search(r'/admin\?tab=articles(?:&|&amp;)edit=(\d+)', row).group(1)
_, page = request('/admin', {'_csrf': csrf(page), 'action': 'delete_article', 'id': article_id})
assert 'مقاله آزمایشی پنل مدیریت' not in page
print('PASS administrator creates, publishes, renders, and deletes an article')
