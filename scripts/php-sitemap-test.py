import sys, urllib.request, urllib.error, xml.etree.ElementTree as ET
from html.parser import HTMLParser
from urllib.parse import urlparse
base=(sys.argv[1] if len(sys.argv)>1 else 'http://localhost:8085').rstrip('/')
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9','i':'http://www.google.com/schemas/sitemap-image/1.1'}
class Metadata(HTMLParser):
 def __init__(self): super().__init__(); self.canonical=[]; self.noindex=False
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='link' and a.get('rel')=='canonical': self.canonical.append(a.get('href'))
  if tag=='meta' and a.get('name','').lower() in ('robots','googlebot') and 'noindex' in a.get('content','').lower(): self.noindex=True
def xml(path):
 with urllib.request.urlopen(path,timeout=20) as r:
  assert r.status==200 and r.url==path,path
  assert 'application/xml' in r.headers['Content-Type'],path
  data=r.read();assert len(data)<50*1024*1024
  return ET.fromstring(data)
index=xml(base+'/sitemap.xml');assert index.tag.endswith('sitemapindex')
maps=[m.text for m in index.findall('s:sitemap/s:loc',ns)]
assert maps and len(maps)==len(set(maps))
locs=[]
for path in maps:
 assert path.startswith(base+'/sitemaps/'),path
 root=xml(path);assert root.tag.endswith('urlset')
 entries=root.findall('s:url',ns);assert 0<len(entries)<=50000
 for entry in entries:
  loc=entry.find('s:loc',ns).text;locs.append(loc)
  assert loc.startswith(base+'/') and not urlparse(loc).query,loc
  assert not any(urlparse(loc).path.startswith(p) for p in ['/admin','/login','/register','/cart','/checkout','/account','/api/','/install','/track','/wholesale-panel']),loc
  with urllib.request.urlopen(loc,timeout=20) as r:
   assert r.status==200 and r.url==loc,loc
   assert 'noindex' not in r.headers.get('X-Robots-Tag','').lower(),loc
   meta=Metadata();meta.feed(r.read().decode('utf-8'))
   assert not meta.noindex and meta.canonical==[loc],(loc,meta.canonical,meta.noindex)
  for image in entry.findall('i:image/i:loc',ns):
   with urllib.request.urlopen(image.text,timeout=20) as r: assert r.status==200
assert len(locs)==len(set(locs)) and len(locs)>5
assert base+'/wholesale' in locs and base+'/products' in locs
assert 'Sitemap: '+base+'/sitemap.xml' in urllib.request.urlopen(base+'/robots.txt').read().decode()
try: urllib.request.urlopen(base+'/sitemaps/products-999999.xml')
except urllib.error.HTTPError as e: assert e.code==404
else: raise AssertionError('Nonexistent sitemap page must return 404')
print(f'PASS {len(maps)} maps / {len(locs)} unique URLs: valid XML, public 200 pages, matching canonicals, no noindex, accessible images, robots discovery, missing-page 404')
