from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
root=Path(__file__).resolve().parents[1]
source=root/'php-store'
target=root/'dist';target.mkdir(exist_ok=True)
archive=target/'naylex-sadat-php82.zip'
with ZipFile(archive,'w',ZIP_DEFLATED) as z:
 for folder in ['naylex-app','public_html']:
  for p in (source/folder).rglob('*'):
   rel=p.relative_to(source)
   if p.is_file() and 'storage' not in rel.parts and p.name!='config.php':z.write(p,str(rel).replace('\\','/'))
 z.write(source/'INSTALL-FA.md','INSTALL-FA.md')
 z.write(root/'SEO-SITEMAP-FA.md','SEO-SITEMAP-FA.md')
 z.write(root/'SEO-KEYWORD-PLAN-FA.md','SEO-KEYWORD-PLAN-FA.md')
 z.write(root/'README.md','README.md')
 z.write(root/'DEPLOY-GIT-FA.md','DEPLOY-GIT-FA.md')
with ZipFile(archive) as z:
 assert not any(n.endswith('/config.php') or '/storage/' in n or '.env' in n for n in z.namelist())
 assert z.testzip() is None
 # Every packaged application file must exactly match the current source.
 for name in z.namelist():
  if name.startswith(('naylex-app/','public_html/')):
   assert z.read(name)==(source/name).read_bytes(),name
 for name in ['public_html/favicon.ico','public_html/assets/favicon-96.png','naylex-app/articles.php','naylex-app/search.php','naylex-app/sitemap.php']:
  assert name in z.namelist(),name
print(f'Hosting package: {archive.name}; {archive.stat().st_size:,} bytes; private config and storage excluded.')
