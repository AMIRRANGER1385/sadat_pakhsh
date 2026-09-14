import urllib.request,urllib.error,json,re
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
Path('.runtime').mkdir(exist_ok=True)
Path('.runtime/live-seo-audit.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(results,ensure_ascii=True))
