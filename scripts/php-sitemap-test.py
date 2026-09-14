import urllib.request,xml.etree.ElementTree as ET
base='http://localhost:8085'
with urllib.request.urlopen(base+'/sitemap.xml') as r:
 assert 'application/xml' in r.headers['Content-Type'];root=ET.fromstring(r.read())
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9','i':'http://www.google.com/schemas/sitemap-image/1.1'}
urls=root.findall('s:url',ns);locs=[u.find('s:loc',ns).text for u in urls]
assert len(locs)==len(set(locs)) and len(locs)>5
assert all(u.startswith(base+'/') and '?' not in u for u in locs)
assert not any('/admin' in u or '/login' in u or '/cart' in u for u in locs)
for u in urls:
 if '/products/' not in u.find('s:loc',ns).text:continue
 assert u.find('s:lastmod',ns) is not None
 assert u.find('i:image/i:loc',ns) is not None
assert 'Sitemap: '+base+'/sitemap.xml' in urllib.request.urlopen(base+'/robots.txt').read().decode()
print('PASS valid XML, canonical unique URLs, product dates, images and robots discovery')
