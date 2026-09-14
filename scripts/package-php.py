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
with ZipFile(archive) as z:
 assert not any(n.endswith('/config.php') or '/storage/' in n or '.env' in n for n in z.namelist())
 assert z.testzip() is None
print(f'Hosting package: {archive.name}; {archive.stat().st_size:,} bytes; private config and storage excluded.')
