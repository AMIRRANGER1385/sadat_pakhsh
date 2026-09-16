"""HTTP checks against only the isolated localhost PHP/MySQL test service."""
import urllib.request,urllib.error,urllib.parse,http.cookiejar,re,json,secrets,struct,zlib
from pathlib import Path
root=Path(__file__).resolve().parents[1]
base='http://localhost:8085'
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
admin=client();guest=client();passed=0
def request(path,data=None,opener=admin,headers=None):
 headers={'Origin':base,**(headers or {})}
 if isinstance(data,dict):data=urllib.parse.urlencode(data).encode();headers['Content-Type']='application/x-www-form-urlencoded'
 req=urllib.request.Request(base+path,data=data,headers=headers)
 try:r=opener.open(req,timeout=30)
 except urllib.error.HTTPError as e:r=e
 return r.code,r.read(),r.headers
def check(name,condition):
 global passed
 assert condition,name
 passed+=1;print('PASS',name)
def token(path='/',opener=admin):
 code,body,_=request(path,opener=opener)
 match=re.search(rb'name="_csrf" value="([a-f0-9]+)"',body)
 assert match,(path,code,body[:120])
 return match.group(1).decode()
config=(root/'php-store/naylex-app/config.php').read_text()
key=re.search(r"'install_key' => '([^']+)'",config).group(1)
creds=root/'.runtime/php-local-admin.json'
if not creds.exists():creds.write_text(json.dumps({'username':'admin','password':secrets.token_urlsafe(24)}))
credentials=json.loads(creds.read_text())
code,body,_=request('/install')
if code==200:
 check('installer rejects wrong key',request('/install',{'_csrf':token('/install'),'install_key':'x'*48,**credentials})[0]==403)
 result=request('/install',{'_csrf':token('/install'),'install_key':key,**credentials})
 check('fresh MySQL installation',result[0]==303)
check('installer locked',request('/install')[0]==403)
code,body,headers=request('/')
check('home renders PHP with brand',code==200 and 'نایلکس سادات'.encode() in body)
check('CSP and no-store',"nonce-" in headers.get('Content-Security-Policy','') and 'no-store' in headers.get('Cache-Control',''))
for path in ['/products','/products/product-1','/about','/contact','/faq','/cart','/checkout']:
 check(path+' renders',request(path)[0]==200)
check('product schema',b'"priceCurrency":"IRR"' in request('/products/product-1')[1])
check('search noindex',b'noindex' in request('/products?q=zzzzz')[1])
check('unknown product 404',request('/products/missing')[0]==404)
check('admin anonymous redirect',request('/admin',opener=guest)[0]==303)
check('CSRF rejected',request('/admin',{'action':'company'},opener=guest)[0]==403)
csrf=token('/login')
check('wrong password rejected',request('/login',{'_csrf':csrf,'action':'login','username':'admin','password':'incorrect'})[0]==400)
check('login',request('/login',{'_csrf':csrf,'action':'login',**credentials})[0]==303)
check('history records login',b'admin' in request('/admin?tab=history')[1])
for tab in ['dashboard','products','categories','orders','customers','history','operations','company','shipping','security']:
 check('admin '+tab,request('/admin?tab='+tab)[0]==200)
csrf=token('/admin?tab=company')
company={'action':'company','company_name':'شرکت آزمون','company_about':'معرفی آزمایشی فروشگاه','company_phone':'02112345678','company_email':'test@example.com','company_address':'نشانی آزمایشی تهران','company_hours':'۹ تا ۱۷','company_postal_code':'1234567890','_csrf':csrf}
check('company update',request('/admin',company)[0]==303)
check('company appears on contact',b'02112345678' in request('/contact')[1])
check('cross-origin rejected',request('/admin',company,headers={'Origin':'https://evil.example'})[0]==403)
def png():
 def chunk(kind,data):return struct.pack('!I',len(data))+kind+data+struct.pack('!I',zlib.crc32(kind+data)&0xffffffff)
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!IIBBBBB',10,10,8,2,0,0,0))+chunk(b'IDAT',zlib.compress((b'\0'+b'\x24\x59\x4c'*10)*10))+chunk(b'IEND',b'')
def upload(content,opener=admin):
 boundary='----NaylexTestBoundary'+secrets.token_hex(8)
 csrf=token('/admin?tab=products' if opener is admin else '/login',opener)
 data=(f'--{boundary}\r\nContent-Disposition: form-data; name="_csrf"\r\n\r\n{csrf}\r\n--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n').encode()+content+f'\r\n--{boundary}--\r\n'.encode()
 return request('/admin/upload',data,opener,{'Content-Type':'multipart/form-data; boundary='+boundary})
check('guest upload denied',upload(png(),guest)[0]==403)
check('fake image denied',upload(b'<?php echo 1; ?>')[0]==400)
code,body,_=upload(png());check('valid image upload',code==200)
image=json.loads(body)['url'];code,body,headers=request(image)
check('webp served',code==200 and body[:4]==b'RIFF' and headers.get('Content-Type')=='image/webp')
check('media traversal denied',request('/media?name=../../config.php')[0]==404)
csrf=token('/admin?tab=products&edit=0')
product={'_csrf':csrf,'action':'product','id':'0','version':'1','name':'محصول آزمون','slug':'test-'+secrets.token_hex(4),'description':'توضیحات محصول آزمایشی با عکس آپلودی','image':image,'retail':'100000','wholesale':'80000','minimum':'10','stock':'20','unit':'بسته','category_id':'1','featured':'on'}
check('product create',request('/admin',product)[0]==303)
code,body,_=request('/products/'+product['slug']);check('uploaded product visible',code==200 and image.encode() in body)
pid=re.search(rb'name="id" value="(\d+)"',body).group(1).decode()
csrf=token('/products/'+product['slug']);check('cart add wholesale quantity',request('/',{'_csrf':csrf,'action':'cart_add','id':pid,'quantity':10})[0]==303)
code,body,_=request('/cart');check('wholesale price in cart','۸۰,۰۰۰'.encode() in body)
check('overselling rejected',request('/',{'_csrf':token('/cart'),'action':'cart_set','id':pid,'quantity':21})[0]==400)
code,body,_=request('/checkout');checkout_key=re.search(rb'name="checkout_key" value="([a-f0-9]+)"',body).group(1).decode()
check('unconfigured gateway fails safely',request('/checkout',{'_csrf':token('/checkout'),'action':'checkout','checkout_key':checkout_key,'name':'گیرنده آزمون','phone':'09123456789','address':'نشانی آزمایشی کامل برای سفارش خرید'})[0]==400)
customer=client();csrf=token('/register',customer);suffix=secrets.token_hex(4)
check('customer registration',request('/register',{'_csrf':csrf,'action':'register','name':'مشتری آزمون '+suffix,'username':'09'+str(secrets.randbelow(10**9)).zfill(9),'password':secrets.token_urlsafe(20)},customer)[0]==303)
check('customer cannot manage',request('/admin',{'_csrf':token('/account',customer),'action':'company'},customer)[0]==403)
check('logout',request('/',{'_csrf':token('/admin'),'action':'logout'})[0]==303)
check('admin access removed',request('/admin')[0]==303)
print('All',passed,'PHP HTTP checks passed.')
