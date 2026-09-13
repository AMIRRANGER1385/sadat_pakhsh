import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import sharp from 'sharp';
import { PrismaClient } from '@prisma/client';
import { resolve } from 'node:path';
const base='http://localhost:3100';
const env=readFileSync('.env','utf8');
const value=(key:string)=>env.match(new RegExp(`^${key}="?([^"\\r\\n]+)`, 'm'))?.[1]||'';
let passed=0;
const check=(name:string,ok:boolean)=>{assert.ok(ok,name);passed++;console.log('PASS '+name)};
async function post(path:string,data:unknown,cookie='',origin=base){return fetch(base+'/api/'+path,{method:'POST',headers:{origin,'content-type':'application/json',cookie},body:JSON.stringify(data),redirect:'manual'})}
async function main(){
const home=await fetch(base);const html=await home.text();check('home brand and canonical',home.status===200&&html.includes('نایلکس سادات')&&html.includes('rel="canonical"'));
check('nonce CSP without production unsafe-eval',!!home.headers.get('content-security-policy')?.includes('nonce-')&&!home.headers.get('content-security-policy')?.includes('unsafe-eval'));
check('script nonce rendered',/nonce="[^"]+"/.test(html));
for(const path of ['/about','/contact','/faq']){const r=await fetch(base+path);check(path+' indexable',r.status===200&&!(await r.text()).includes('content="noindex'))}
const product=await fetch(base+'/products/product-1');const productHTML=await product.text();check('product structured data IRR',productHTML.includes('"@type":"Product"')&&productHTML.includes('"priceCurrency":"IRR"')&&productHTML.includes('BreadcrumbList'));
const search=await fetch(base+'/products?q=test');check('search noindex',(await search.text()).includes('noindex'));
const missing=await fetch(base+'/products/does-not-exist');check('missing product noindex',(await missing.text()).includes('noindex'));
check('cross-site write rejected',(await post('admin/company',{},'','https://evil.example')).status===403);
check('anonymous admin denied',(await post('admin/company',{})).status===403);
check('malformed JSON rejected',(await fetch(base+'/api/auth/login',{method:'POST',headers:{origin:base,'content-type':'application/json'},body:'{'})).status===400);
check('oversized request rejected',(await post('auth/login',{junk:'x'.repeat(34000)})).status===413);
const login=await post('auth/login',{username:value('ADMIN_USERNAME'),password:value('ADMIN_PASSWORD')});check('admin login',login.status===200);let cookie=login.headers.get('set-cookie')?.split(';')[0]||'';
check('cookie secure attributes',/HttpOnly/i.test(login.headers.get('set-cookie')||'')&&/Secure/i.test(login.headers.get('set-cookie')||'')&&/SameSite=lax/i.test(login.headers.get('set-cookie')||''));
const png=await sharp({create:{width:48,height:48,channels:3,background:'#24594c'}}).png().toBuffer();
const upload=(body:Buffer,type='image/png',auth=cookie,origin=base)=>fetch(base+'/api/admin/upload',{method:'POST',headers:{cookie:auth,origin,'content-type':type},body:new Uint8Array(body)});
check('anonymous upload denied',(await upload(png,'image/png','')).status===403);
check('cross-origin upload denied',(await upload(png,'image/png',cookie,'https://evil.example')).status===403);
check('fake image rejected',(await upload(Buffer.from('<script>alert(1)</script>'))).status===400);
check('oversized upload rejected',(await upload(Buffer.alloc(5*1024*1024+1))).status===413);
const uploaded=await upload(png);check('admin image uploaded',uploaded.status===201);const uploadedURL=(await uploaded.json()).url;
const served=await fetch(base+uploadedURL);check('uploaded image served dynamically',served.status===200&&served.headers.get('content-type')==='image/webp');
check('uploaded data really webp',(await sharp(Buffer.from(await served.arrayBuffer())).metadata()).format==='webp');
const testDB=new PrismaClient({datasources:{db:{url:'file:'+resolve('.runtime/security-check.db').replaceAll('\\','/')}}});
try{
 const category=await testDB.category.findFirstOrThrow();
 const input={name:'محصول تست آپلود',slug:'upload-integration-test',description:'توضیحات محصول آزمایشی برای بررسی آپلود مستقیم',image:uploadedURL,retail:100000,wholesale:80000,minimum:10,stock:20,categoryId:category.id,unit:'بسته',featured:false};
 check('product saved with uploaded image',(await post('admin/product',input,cookie)).status===200);
 const productPage=await fetch(base+'/products/'+input.slug);check('uploaded image appears on product',productPage.status===200&&(await productPage.text()).includes(uploadedURL));
 const account=await testDB.user.findUniqueOrThrow({where:{username:value('ADMIN_USERNAME')}});
 check('successful login event persisted',(await testDB.loginEvent.count({where:{userId:account.id,kind:'LOGIN'}}))>0);
}finally{await testDB.$disconnect()}
check('media traversal rejected',(await fetch(base+'/media/not-valid.webp')).status===404);
const admin=await fetch(base+'/admin',{headers:{cookie}});const adminHTML=await admin.text();check('company panel present',adminHTML.includes('اطلاعات شرکت'));check('admin no-store',admin.headers.get('cache-control')?.includes('no-store')===true);
check('login history visible to admin',adminHTML.includes('سابقه ورود کاربران')&&adminHTML.includes(value('ADMIN_USERNAME')));
const company={companyName:'شرکت آزمون نایلکس سادات',companyAbout:'معرفی شرکت در آزمون خودکار',companyPhone:'02112345678',companyEmail:'test@example.com',companyAddress:'نشانی آزمایشی شرکت',companyHours:'۹ تا ۱۷',companyPostalCode:'1234567890'};
check('company save',(await post('admin/company',company,cookie)).status===200);
check('contact reflects company',(await (await fetch(base+'/contact')).text()).includes(company.companyPhone));
check('about reflects company',(await (await fetch(base+'/about')).text()).includes(company.companyAbout));
const account=await post('auth/register',{name:'مشتری آزمون',username:'09999999998',password:'Test-customer-password-2026'});check('wholesale account created',account.status===200);const customerCookie=account.headers.get('set-cookie')?.split(';')[0]||'';
check('customer cannot mutate company',(await post('admin/company',company,customerCookie)).status===403);
check('customer cannot approve self',(await post('admin/customer',{id:'invalid',status:'APPROVED'},customerCookie)).status===403);
check('customer upload denied',(await upload(png,'image/png',customerCookie)).status===403);
check('invalid callback does not reveal order',(await fetch(base+'/api/payment/callback?Authority=bad&Status=OK',{redirect:'manual'})).headers.get('location')?.endsWith('/track?payment=invalid')===true);
check('wrong password change rejected',(await post('auth/password',{currentPassword:'incorrect',newPassword:'Long-test-password-2026'},cookie)).status===400);
const oldCookie=cookie;const change=await post('auth/password',{currentPassword:value('ADMIN_PASSWORD'),newPassword:'Long-test-password-2026'},cookie);check('password change',change.status===200);cookie=change.headers.get('set-cookie')?.split(';')[0]||'';
check('password change revokes old session',(await post('admin/company',company,oldCookie)).status===403);
check('new session remains authenticated',(await post('admin/company',company,cookie)).status===200);
check('logout',(await post('auth/logout',{},cookie)).status===200);
check('old session revoked',(await post('admin/company',company,cookie)).status===403);
console.log(`All ${passed} HTTP checks passed against an isolated database.`);
}
main().catch(error=>{console.error(error);process.exitCode=1});
