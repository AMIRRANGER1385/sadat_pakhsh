import json
import urllib.request
import urllib.parse
import xml.etree.ElementTree as ET
from html.parser import HTMLParser

BASE = 'http://localhost:8085'

class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.h1 = 0
        self.canonical = []
        self.robots = ''
        self.links = []
        self.schemas = []
        self._json = None
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'h1': self.h1 += 1
        if tag == 'a' and attrs.get('href'): self.links.append(attrs['href'])
        if tag == 'link' and attrs.get('rel') == 'canonical': self.canonical.append(attrs.get('href'))
        if tag == 'meta' and attrs.get('name') == 'robots': self.robots = attrs.get('content', '')
        if tag == 'script' and attrs.get('type') == 'application/ld+json': self._json = ''
    def handle_data(self, data):
        if self._json is not None: self._json += data
    def handle_endtag(self, tag):
        if tag == 'script' and self._json is not None:
            self.schemas.append(json.loads(self._json))
            self._json = None

def fetch(path):
    with urllib.request.urlopen(BASE + path, timeout=15) as response:
        return response.status, response.read().decode('utf-8'), response.headers

status, body, _ = fetch('/guides')
index = Page(); index.feed(body)
assert status == 200 and index.h1 == 1 and index.canonical == [BASE + '/guides']
guide_links = sorted(set(link for link in index.links if link.startswith('/guides/')))
assert len(guide_links) >= 13
for link in guide_links:
    status, body, _ = fetch(link)
    page = Page(); page.feed(body)
    assert status == 200 and page.h1 == 1 and page.robots.startswith('index,follow')
    assert page.canonical == [BASE + link]
    assert any(item.get('@type') == 'Article' for item in page.schemas)
    assert any(item.get('@type') == 'BreadcrumbList' for item in page.schemas)
    assert '/wholesale' in page.links or any('/categories/' in item for item in page.links)
print('PASS buying guides: canonical, Article schema, internal shopping links')

status, body, _ = fetch('/wholesale')
page = Page(); page.feed(body)
assert status == 200 and page.h1 == 1 and page.robots.startswith('index,follow')
assert page.canonical == [BASE + '/wholesale'] and '/nylex-manufacturer' in page.links
print('PASS indexed wholesale landing combines products and commercial content')

status, xml, _ = fetch('/sitemaps/pages.xml')
root = ET.fromstring(xml)
ns = {'s': 'http://www.sitemaps.org/schemas/sitemap/0.9'}
locs = [node.text for node in root.findall('s:url/s:loc', ns)]
_, guide_xml, _ = fetch('/sitemaps/guides.xml')
locs += [node.text for node in ET.fromstring(guide_xml).findall('s:url/s:loc', ns)]
assert BASE + '/guides' in locs and BASE + '/wholesale' in locs and BASE + '/products' in locs and BASE + '/nylex' not in locs
assert all(BASE + link in locs for link in guide_links)
print('PASS sitemap discovers guides and commercial landing pages')

status, payload, headers = fetch('/api/search?q=' + urllib.parse.quote('کیسه'))
data = json.loads(payload)
assert status == 200 and 'application/json' in headers['Content-Type'] and data['items']
assert all(item['url'].startswith('/products/') and item['image'] and item['price'] > 0 for item in data['items'])
status, payload, _ = fetch('/api/search?q=x')
assert status == 200 and json.loads(payload) == {'items': []}
status, body, _ = fetch('/articles')
page = Page(); page.feed(body)
assert status == 200 and page.h1 == 1 and page.robots.startswith('index,follow')
print('PASS product-search JSON and empty articles index')
