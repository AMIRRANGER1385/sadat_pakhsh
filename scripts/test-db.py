from pathlib import Path
import sqlite3
root=Path(__file__).resolve().parents[1]
target=root/'.runtime/security-check.db'
target.parent.mkdir(exist_ok=True)
with sqlite3.connect(root/'prisma/dev.db') as source,sqlite3.connect(target) as copy:source.backup(copy)
print('Isolated database copy ready.')
