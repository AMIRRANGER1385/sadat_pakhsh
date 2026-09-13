"""Additive SQLite upgrade: never drops, rebuilds, or deletes tables or rows."""
import sqlite3
from pathlib import Path
db=Path(__file__).resolve().parents[1]/'prisma/dev.db'
with sqlite3.connect(db) as c:
 before={t:c.execute(f'SELECT COUNT(*) FROM "{t}"').fetchone()[0] for t in ['Order','Settings','Product','User']}
 order_columns={r[1] for r in c.execute('PRAGMA table_info("Order")')}
 for name in ['checkoutKey','requestHash']:
  if name not in order_columns:c.execute(f'ALTER TABLE "Order" ADD COLUMN "{name}" TEXT')
 settings_columns={r[1] for r in c.execute('PRAGMA table_info("Settings")')}
 for name in ['companyName','companyAbout','companyPhone','companyEmail','companyAddress','companyHours','companyPostalCode']:
  if name not in settings_columns:
   default='نایلکس سادات' if name=='companyName' else ''
   c.execute(f'ALTER TABLE "Settings" ADD COLUMN "{name}" TEXT NOT NULL DEFAULT \'{default}\'')
 assert not c.execute('SELECT checkoutKey FROM "Order" WHERE checkoutKey IS NOT NULL GROUP BY checkoutKey HAVING COUNT(*)>1').fetchall()
 c.execute('CREATE UNIQUE INDEX IF NOT EXISTS "Order_checkoutKey_key" ON "Order"("checkoutKey")')
 assert before=={t:c.execute(f'SELECT COUNT(*) FROM "{t}"').fetchone()[0] for t in before}
 assert c.execute('PRAGMA integrity_check').fetchone()[0]=='ok'
print('Only columns and unique index added; all row counts preserved; integrity_check OK.')
