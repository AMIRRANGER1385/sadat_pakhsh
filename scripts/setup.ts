import { existsSync, writeFileSync,readFileSync, mkdirSync } from 'node:fs';
import { resolve,dirname } from 'node:path';
import { randomBytes } from 'node:crypto';
if (!existsSync('.env')) {
 const password = randomBytes(15).toString('base64url');
 writeFileSync('.env', `DATABASE_URL="file:./dev.db"\nAPP_URL="http://localhost:3000"\nADMIN_USERNAME="admin"\nADMIN_PASSWORD="${password}"\nZARINPAL_MERCHANT_ID=""\nZARINPAL_SANDBOX="true"\n`);
 console.log('فایل .env ایجاد شد. رمز اولیه مدیر در ADMIN_PASSWORD قرار دارد.');
}
const env=readFileSync('.env','utf8');
const database=env.match(/^DATABASE_URL\s*=\s*"?(file:[^"\r\n]+)"?/m)?.[1];
if(database){const file=resolve('prisma',database.slice(5));if(!existsSync(file)){mkdirSync(dirname(file),{recursive:true});writeFileSync(file,'');}}
