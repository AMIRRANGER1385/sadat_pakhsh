import json, re, secrets, subprocess, urllib.request, urllib.error
from html.parser import HTMLParser
BASE='http://localhost:8085'
PHP=[r'C:\xampp\php\php.exe','scripts/php-seo-pagination-fixture.php']
prefix='seo-page-'+secrets.token_hex(8)
class Metadata(HTMLParser):
 def __init__(self):super().__init__();self.canonical=[];self.robots='';self.products=[];self.title='';self.in_title=False
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a['href'])
  if tag=='meta' and a.get('name')=='robots':self.robots=a['content']
  if tag=='a' and a.get('class')=='product-image':self.products.append(a['href'])
  if tag=='title':self.in_title=True
 def handle_endtag(self,tag):
  if tag=='title':self.in_title=False
 def handle_data(self,data):
  if self.in_title:self.title+=data
def get(path,method='GET',headers=None):
 try:r=urllib.request.urlopen(urllib.request.Request(BASE+path,method=method,headers=headers or {}))
 except urllib.error.HTTPError as e:r=e
 body=r.read().decode('utf-8','replace');doc=Metadata();doc.feed(body);return r,doc,body
try:
 data=json.loads(subprocess.check_output(PHP+['create',prefix]))
 for path in ['/products','/wholesale','/articles',data['category']]:
  r,one,_=get(path);r,two,body=get(path+'?page=2')
  assert r.status==200 and two.canonical==[BASE+path+'?page=2'] and two.robots.startswith('index,follow'),path
  assert one.title!=two.title,path
  assert 'rel="prev"' in body,path
  assert get(path+'?page=99999')[0].status==404,path
  if two.products:assert not set(one.products)&set(two.products),path
 assert len(get(data['category'])[1].products)==24 and len(get(data['category']+'?page=2')[1].products)==3
 assert get('/products?category=999999999')[0].status==404
 assert get('/products?sort=low')[1].robots.startswith('noindex')
 assert get('/search?q=plastic')[1].robots.startswith('noindex')
 _,_,body=get('/products/'+prefix+'-0');assert '/assets/product-placeholder.svg' in body
 r,_,_=get('/robots.txt');assert not r.headers.get('Set-Cookie')
 r,_,_=get(data['image'],'HEAD');assert r.status==200 and not r.headers.get('Set-Cookie')
 assert get(data['image'],'HEAD',{'If-None-Match':r.headers['ETag']})[0].status==304
 print('PASS indexable pagination, disjoint products, category coverage, filter noindex, invalid-page 404, missing-image fallback and cookieless cached images/robots')
finally:subprocess.run(PHP+['cleanup',prefix],check=True)
