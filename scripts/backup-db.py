from pathlib import Path
import sqlite3
root=Path(__file__).resolve().parents[1]
c=sqlite3.connect(root/'prisma/dev.db')
columns=[r[1] for r in c.execute('PRAGMA table_info("Order")')]
print('checkoutKey exists:', 'checkoutKey' in columns)
if 'checkoutKey' in columns:
 assert not c.execute('SELECT checkoutKey FROM "Order" WHERE checkoutKey IS NOT NULL GROUP BY checkoutKey HAVING COUNT(*)>1').fetchall()
print('Order count:',c.execute('SELECT COUNT(*) FROM "Order"').fetchone()[0])
folder=root/'.runtime/backups';folder.mkdir(parents=True,exist_ok=True)
from datetime import datetime
target=folder/('before-company-security-'+datetime.now().strftime('%Y%m%d-%H%M%S')+'.db')
with sqlite3.connect(target) as backup:c.backup(backup)
c.close()
print('Database backup created.')
