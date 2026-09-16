import base64,hashlib,hmac,http.cookiejar,json,re,struct,subprocess,time,urllib.error,urllib.parse,urllib.request
BASE='http://localhost:8085';PHP=r'C:\xampp\php\php.exe';FIXTURE='scripts/php-2fa-fixture.php'
account=json.loads(subprocess.check_output([PHP,FIXTURE,'create'],text=True))
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args): return None
client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def request(path,data=None):
 headers={'Origin':BASE};body=urllib.parse.urlencode(data).encode() if data else None
 try:return client.open(urllib.request.Request(BASE+path,data=body,headers=headers),timeout=20)
 except urllib.error.HTTPError as error:return error
def csrf(path):return re.search(rb'name="_csrf" value="([a-f0-9]+)"',request(path).read()).group(1).decode()
def totp(secret):
 key=base64.b32decode(secret);counter=struct.pack('>Q',int(time.time())//30);digest=hmac.new(key,counter,hashlib.sha1).digest();offset=digest[-1]&15;number=struct.unpack('>I',digest[offset:offset+4])[0]&0x7fffffff;return str(number%1000000).zfill(6)
try:
 token=csrf('/login');response=request('/login',{'_csrf':token,'action':'login','username':account['username'],'password':account['password']});assert response.code==303 and response.headers['Location']=='/admin-2fa'
 token=csrf('/admin-2fa');response=request('/admin-2fa',{'_csrf':token,'action':'2fa_login','two_factor_code':'000000'});assert response.code==400 and b'name="two_factor_code"' in response.read()
 token=csrf('/admin-2fa');response=request('/admin-2fa',{'_csrf':token,'action':'2fa_login','two_factor_code':totp(account['secret'])});assert response.code==303 and response.headers['Location']=='/admin'
 assert request('/admin').code==200
 print('PASS admin password is followed by TOTP challenge, wrong code is rejected, valid code logs in')
finally:subprocess.run([PHP,FIXTURE,'cleanup',account['username']],check=True)
