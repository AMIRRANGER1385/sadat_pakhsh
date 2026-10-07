import urllib.request,urllib.error,json,re,time,ssl,socket
from datetime import datetime,timezone
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
from pathlib import Path
results=[]
for path in ['/','/products','/products/','/robots.txt','/sitemap.xml']:
 try:
  request=urllib.request.Request('https://sadatpakhsh.ir'+path,headers={'User-Agent':'Mozilla/5.0 (compatible; SiteAudit/1.0)'})
  with urllib.request.urlopen(request,timeout=15) as response:
   body=response.read(2000000).decode('utf-8','replace')
   row={'path':path,'status':response.status,'url':response.url,'type':response.headers.get('Content-Type'),'robots_header':response.headers.get('X-Robots-Tag'),'title':re.findall(r'<title>(.*?)</title>',body,re.S),'h1':re.findall(r'<h1[^>]*>(.*?)</h1>',body,re.S),'canonical':re.findall(r'<link[^>]*rel="canonical"[^>]*>',body)}
   if path.endswith('.txt') or path.endswith('.xml'):row['body']=body[:15000]
   results.append(row)
 except Exception as e:results.append({'path':path,'error':str(e)})
class AuditDocument(HTMLParser):
 def __init__(self):super().__init__();self.title='';self.in_title=False;self.h1=0;self.canonical=[];self.robots='';self.description='';self.images=[];self.schemas=[];self.current_json=None
 def handle_starttag(self,tag,attrs):
  attrs=dict(attrs)
  if tag=='title':self.in_title=True
  if tag=='h1':self.h1+=1
  if tag=='link' and attrs.get('rel')=='canonical':self.canonical.append(attrs.get('href',''))
  if tag=='meta' and attrs.get('name')=='robots':self.robots=attrs.get('content','')
  if tag=='meta' and attrs.get('name')=='description':self.description=attrs.get('content','')
  if tag=='img':self.images.append(attrs)
  if tag=='script' and attrs.get('type')=='application/ld+json':self.current_json=''
 def handle_data(self,data):
  if self.in_title:self.title+=data
  if self.current_json is not None:self.current_json+=data
 def handle_endtag(self,tag):
  if tag=='title':self.in_title=False
  if tag=='script' and self.current_json is not None:
   try:self.schemas.append(json.loads(self.current_json))
   except json.JSONDecodeError:self.schemas.append({'invalid_json_ld':True})
   self.current_json=None

def audit_fetch(url,user_agent='Mozilla/5.0 (compatible; SadatPakhshSEOAudit/2.0; +https://sadatpakhsh.ir/)'):
 request=urllib.request.Request(url,headers={'User-Agent':user_agent,'Accept-Encoding':'identity'});started=time.perf_counter()
 try:
  with urllib.request.urlopen(request,timeout=20) as response:return response.status,response.headers,response.read(5000000).decode('utf-8','replace'),round((time.perf_counter()-started)*1000)
 except urllib.error.HTTPError as error:return error.code,error.headers,error.read(5000000).decode('utf-8','replace'),round((time.perf_counter()-started)*1000)

try:
 ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'};_,_,index_xml,_=audit_fetch('https://sadatpakhsh.ir/sitemap.xml');maps=[node.text for node in ET.fromstring(index_xml).findall('s:sitemap/s:loc',ns)];urls=[];issues=[];titles={};descriptions={};elapsed_values=[]
 for sitemap in maps:
  status,_,xml,_=audit_fetch(sitemap)
  if status!=200:issues.append({'url':sitemap,'issues':[f'HTTP {status}']});continue
  urls.extend(node.text for node in ET.fromstring(xml).findall('s:url/s:loc',ns))
 for page_url in sorted(set(urls)):
  status,headers,body,elapsed=audit_fetch(page_url,'Googlebot/2.1 (+http://www.google.com/bot.html)');doc=AuditDocument();doc.feed(body);page_issues=[]
  elapsed_values.append(elapsed)
  if status!=200:page_issues.append(f'HTTP {status}')
  if headers.get('X-Robots-Tag','').lower().startswith('noindex') or 'noindex' in doc.robots.lower():page_issues.append('noindex')
  if doc.h1!=1:page_issues.append(f'{doc.h1} H1 elements')
  if doc.canonical!=[page_url]:page_issues.append('canonical mismatch')
  if not doc.title.strip():page_issues.append('missing title')
  if not doc.description.strip():page_issues.append('missing description')
  if doc.title.strip():titles.setdefault(doc.title.strip(),[]).append(page_url)
  if doc.description.strip():descriptions.setdefault(doc.description.strip(),[]).append(page_url)
  if any('alt' not in image for image in doc.images):page_issues.append('image missing alt')
  if any(schema.get('invalid_json_ld') for schema in doc.schemas if isinstance(schema,dict)):page_issues.append('invalid JSON-LD')
  if page_issues:issues.append({'url':page_url,'elapsed_ms':elapsed,'issues':page_issues})
 duplicate_titles=[{'value':value,'urls':group} for value,group in titles.items() if len(group)>1]
 duplicate_descriptions=[{'value':value,'urls':group} for value,group in descriptions.items() if len(group)>1]
 results.append({'sitemap_count':len(maps),'url_count':len(set(urls)),'page_issues':issues,'duplicate_titles':duplicate_titles,'duplicate_descriptions':duplicate_descriptions,'response_ms':{'minimum':min(elapsed_values) if elapsed_values else None,'median':sorted(elapsed_values)[len(elapsed_values)//2] if elapsed_values else None,'maximum':max(elapsed_values) if elapsed_values else None}})
except Exception as error:results.append({'sitemap_audit_error':str(error)})
try:
 context=ssl.create_default_context()
 with socket.create_connection(('sadatpakhsh.ir',443),timeout=15) as raw:
  with context.wrap_socket(raw,server_hostname='sadatpakhsh.ir') as secured:
   expires=secured.getpeercert().get('notAfter');expires_at=datetime.strptime(expires,'%b %d %H:%M:%S %Y %Z').replace(tzinfo=timezone.utc) if expires else None;days_remaining=(expires_at-datetime.now(timezone.utc)).total_seconds()/86400 if expires_at else None
   results.append({'tls_version':secured.version(),'certificate_expires':expires,'certificate_days_remaining':round(days_remaining,1) if days_remaining is not None else None,'certificate_status':'critical' if days_remaining is not None and days_remaining<7 else ('warning' if days_remaining is not None and days_remaining<30 else 'ok')})
except Exception as error:results.append({'tls_error':str(error)})
Path('.runtime').mkdir(exist_ok=True)
Path('.runtime/live-seo-audit.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(results,ensure_ascii=True,indent=2))
