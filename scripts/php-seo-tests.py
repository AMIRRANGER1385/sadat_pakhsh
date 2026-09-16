import urllib.request,urllib.error,json,xml.etree.ElementTree as ET
from html.parser import HTMLParser
base='http://localhost:8085'
class Document(HTMLParser):
 def __init__(self):super().__init__();self.h1=0;self.canonical=[];self.meta={};self.scripts=[];self.current=None;self.title='';self.in_title=False;self.images=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='h1':self.h1+=1
  if tag=='title':self.in_title=True
  if tag=='meta':self.meta[a.get('name',a.get('property',''))]=a.get('content','')
  if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a['href'])
  if tag=='img':self.images.append(a)
  if tag=='script' and a.get('type')=='application/ld+json':self.current=''
 def handle_data(self,data):
  if self.current is not None:self.current+=data
  if self.in_title:self.title+=data
 def handle_endtag(self,tag):
  if tag=='script' and self.current is not None:self.scripts.append(json.loads(self.current));self.current=None
  if tag=='title':self.in_title=False
def fetch(url):
 try:r=urllib.request.urlopen(url,timeout=20)
 except urllib.error.HTTPError as e:r=e
 return r.code,r.read().decode('utf-8'),r.headers
code,xml,_=fetch(base+'/sitemap.xml');assert code==200
ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}
urls=[n.text for n in ET.fromstring(xml).findall('s:url/s:loc',ns)]
assert len(urls)==len(set(urls))
categories=[url for url in urls if '/categories/' in url];assert len(categories)>=5
titles=[]
for url in urls:
 status,body,headers=fetch(url);assert status==200,(url,status)
 doc=Document();doc.feed(body)
 assert doc.h1==1,(url,doc.h1)
 assert doc.canonical==[url],(url,doc.canonical)
 assert doc.meta['robots'].startswith('index,follow'),url
 assert doc.meta['description'] and doc.title,url
 assert all('alt' in image for image in doc.images)
 if '/categories/' in url:
  assert any(s.get('@type')=='CollectionPage' for s in doc.scripts)
  assert any(s.get('@type')=='BreadcrumbList' for s in doc.scripts)
  titles.append(doc.title)
 if '/products/' in url:
  product=next(s for s in doc.scripts if s.get('@type')=='Product')
  assert str(product['offers']['price']).isdigit()
  assert doc.meta['og:image']==product['image']
  assert 'aggregateRating' not in product
 if url.endswith('/plastic-products'):
  assert any(s.get('@type')=='CollectionPage' for s in doc.scripts)
  assert any(s.get('@type')=='BreadcrumbList' for s in doc.scripts)
  assert 'پلاستیک سادات' in body and 'فروش محصولات پلاستیکی' in body
assert len(titles)==len(set(titles))
for path in ['/categories/does-not-exist','/products/does-not-exist']:
 status,body,_=fetch(base+path);assert status==404
 assert 'noindex' in body
_,body,_=fetch(base+'/products?q=anything');assert 'noindex,follow' in body
print(f'PASS {len(urls)} sitemap pages: HTTP, H1, canonical, metadata, schema, category links; 404 and filtered noindex')
