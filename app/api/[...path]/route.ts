import { NextRequest, NextResponse } from 'next/server';
import { cookies } from 'next/headers';
import { hash, compare } from 'bcryptjs';
import { randomBytes } from 'node:crypto';
import { z } from 'zod';
import { db } from '@/lib/db';
import { currentUser,createSession,digest,rateLimit } from '@/lib/auth';
import { unitPrice,shippingCost } from '@/lib/pricing';
import { gateway,paymentURL } from '@/lib/payment';
import { companySchema,passwordSchema } from '@/lib/validation';
import { PublicError,readJSON } from '@/lib/http';
export const runtime='nodejs';
const phone=z.string().regex(/^09\d{9}$/,'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.');
const integer=z.coerce.number().int().min(0).max(100000000);
const productSchema=z.object({name:z.string().min(3).max(150),slug:z.string().regex(/^[a-z0-9-]+$/).max(100),description:z.string().min(10).max(5000),image:z.string().max(500).refine(v=>/^\/products\/[a-zA-Z0-9._-]+$/.test(v)||/^https:\/\/images\.unsplash\.com\//.test(v)||/^\/media\/[a-f0-9-]{36}\.webp$/.test(v),'آدرس تصویر معتبر نیست.'),retail:integer.refine(v=>v>0),wholesale:integer.refine(v=>v>0),minimum:z.coerce.number().int().min(1).max(10000),stock:z.coerce.number().int().min(0).max(100000),categoryId:z.string().min(1),unit:z.string().min(1).max(30),featured:z.boolean().default(false)}).refine(v=>v.wholesale<=v.retail,'قیمت عمده باید کمتر یا برابر قیمت خرده باشد.');
export async function POST(req:NextRequest,{params}:{params:Promise<{path:string[]}>}) {
 try {
 const origin=req.headers.get('origin');
 if(!origin || origin!==new URL(process.env.APP_URL||req.url).origin) return NextResponse.json({error:'درخواست نامعتبر است.'},{status:403});
 const route=(await params).path.join('/');
 const routes=['auth/login','auth/register','auth/logout','auth/password','track','checkout','admin/product','admin/delete-product','admin/category','admin/delete-category','admin/customer','admin/company','admin/settings','admin/order'];
 if(!routes.includes(route))return NextResponse.json({error:'مسیر پیدا نشد'},{status:404});
 await rateLimit('global:'+route,route==='auth/register'?30:route==='auth/login'?150:route==='checkout'?60:500);
 const data=await readJSON(req);
 const user=await currentUser();
 if(route==='auth/login') {
 const v=z.object({username:z.string().min(3).max(100),password:z.string().min(1).max(72).refine(v=>Buffer.byteLength(v,'utf8')<=72)}).parse(data);
 await rateLimit('login:'+v.username);
 const found=await db.user.findUnique({where:{username:v.username}});
 const valid=await compare(v.password,found?.password||'$2b$12$R9h/cIPz0gi.URNNX3kh2OPST9/PgBkqquzi.Ss7KIUgO2t0jWMUW');
 if(!found || !valid) throw new PublicError('نام کاربری یا رمز عبور نادرست است.');
 await createSession(found.id,{kind:'LOGIN',userAgent:req.headers.get('user-agent')||''});return NextResponse.json({redirect:found.role==='ADMIN'?'/admin':'/account'});
 }
 if(route==='auth/register') {
 const v=z.object({name:z.string().min(3).max(100),username:phone,password:passwordSchema}).parse(data);
 await rateLimit('register:'+v.username,3);
 const created=await db.user.create({data:{...v,password:await hash(v.password,12),wholesaleStatus:'PENDING'}});
 await createSession(created.id,{kind:'REGISTER',userAgent:req.headers.get('user-agent')||''});return NextResponse.json({redirect:'/account'});
 }
 if(route==='auth/password'){if(!user)throw new PublicError('ابتدا وارد شوید.',401);await rateLimit('password:'+user.id,5);const v=z.object({currentPassword:z.string().min(1).max(72),newPassword:passwordSchema}).parse(data);if(!await compare(v.currentPassword,user.password))throw new PublicError('رمز فعلی نادرست است.');const nextPassword=await hash(v.newPassword,12);await db.$transaction(async tx=>{await tx.user.update({where:{id:user.id},data:{password:nextPassword}});await tx.session.deleteMany({where:{userId:user.id}})});await createSession(user.id,{kind:'PASSWORD_CHANGE',userAgent:req.headers.get('user-agent')||''});return NextResponse.json({ok:true});}
 if(route==='auth/logout') { const token=(await cookies()).get('session')?.value;if(token)await db.session.deleteMany({where:{id:digest(token)}});(await cookies()).delete('session');return NextResponse.json({redirect:'/'}); }
 if(route==='track') {
 const v=z.object({code:z.string().min(8).max(40),phone}).parse(data);await rateLimit('track:'+v.phone,20);
 const order=await db.order.findFirst({where:v,select:{code:true,status:true,total:true,shipping:true,createdAt:true,reference:true,items:{select:{name:true,quantity:true,price:true}}}});
 if(!order) throw new PublicError('سفارشی با این مشخصات پیدا نشد.');return NextResponse.json(order);
 }
 if(route==='checkout') {
 const v=z.object({name:z.string().min(3).max(100),phone,address:z.string().min(15).max(1000),checkoutKey:z.string().uuid(),items:z.array(z.object({id:z.string(),quantity:z.number().int().min(1).max(1000)})).min(1).max(100)}).parse(data);
 await rateLimit('checkout:'+v.phone,10);
 if(new Set(v.items.map(i=>i.id)).size!==v.items.length) throw new PublicError('سبد نامعتبر است.');
 if(!process.env.ZARINPAL_MERCHANT_ID) throw new PublicError('درگاه پرداخت هنوز توسط مدیر فعال نشده است.');
 const requestHash=digest(JSON.stringify({name:v.name,phone:v.phone,address:v.address,items:v.items,userId:user?.id||null}));
 const previous=await db.order.findUnique({where:{checkoutKey:v.checkoutKey}});
 if(previous){if(previous.requestHash!==requestHash)throw new PublicError('کلید سفارش معتبر نیست.');if(previous.status==='PENDING'&&previous.authority)return NextResponse.json({url:paymentURL(previous.authority),code:previous.code});throw new PublicError('این درخواست قبلاً ثبت شده است. وضعیت سفارش را بررسی کنید.',409);}
 const order=await db.$transaction(async tx=>{
 const settings=await tx.settings.findUniqueOrThrow({where:{id:'shop'}});
 const lines=[];
 for(const item of v.items) {const p=await tx.product.findUnique({where:{id:item.id}});if(!p||!p.active)throw new PublicError('محصول در دسترس نیست.');const reserved=await tx.product.updateMany({where:{id:p.id,stock:{gte:item.quantity}},data:{stock:{decrement:item.quantity}}});if(!reserved.count)throw new PublicError(`موجودی ${p.name} کافی نیست.`);lines.push({productId:p.id,name:p.name,quantity:item.quantity,price:unitPrice(p,item.quantity,user?.wholesaleStatus==='APPROVED')});}
 const subtotal=lines.reduce((s,i)=>s+i.price*i.quantity,0);const shipping=shippingCost(subtotal,settings);
 if(subtotal+shipping>200000000) throw new PublicError('مبلغ سفارش بیش از سقف مجاز است.');
 return tx.order.create({data:{checkoutKey:v.checkoutKey,requestHash,code:'SP-'+randomBytes(8).toString('hex').toUpperCase(),name:v.name,phone:v.phone,address:v.address,total:subtotal+shipping,shipping,userId:user?.id,items:{create:lines}}});
 });
 try {const payment=await gateway('request',{amount:order.total*10,callback_url:`${process.env.APP_URL}/api/payment/callback`,description:`سفارش ${order.code}`,metadata:{mobile:order.phone}});await db.order.update({where:{id:order.id},data:{authority:payment.authority}});return NextResponse.json({url:paymentURL(payment.authority),code:order.code});}
 catch(error) {await db.$transaction(async tx=>{const changed=await tx.order.updateMany({where:{id:order.id,status:'PENDING',authority:null},data:{status:'CANCELLED'}});if(!changed.count)return;const items=await tx.orderItem.findMany({where:{orderId:order.id}});for(const i of items)await tx.product.update({where:{id:i.productId},data:{stock:{increment:i.quantity}}});});throw error;}
 }
 if(route.startsWith('admin/')) {
 if(user?.role!=='ADMIN') return NextResponse.json({error:'دسترسی مجاز نیست.'},{status:403});
 if(route==='admin/product') {const v=productSchema.parse(data);if(data.id)await db.product.update({where:{id:z.string().parse(data.id)},data:v});else await db.product.create({data:v});}
 else if(route==='admin/delete-product') await db.product.update({where:{id:z.string().parse(data.id)},data:{active:false}});
 else if(route==='admin/category') {const name=z.string().min(2).max(80).parse(data.name);if(data.id)await db.category.update({where:{id:z.string().parse(data.id)},data:{name}});else await db.category.create({data:{name}});}
 else if(route==='admin/delete-category') await db.category.delete({where:{id:z.string().parse(data.id)}});
 else if(route==='admin/customer') await db.user.update({where:{id:z.string().parse(data.id),role:'CUSTOMER'},data:{wholesaleStatus:z.enum(['APPROVED','REJECTED']).parse(data.status)}});
 else if(route==='admin/company') await db.settings.update({where:{id:'shop'},data:companySchema.parse(data)});
 else if(route==='admin/settings') await db.settings.update({where:{id:'shop'},data:z.object({shipping:integer,freeAbove:integer}).parse(data)});
 else if(route==='admin/order') {
 const id=z.string().parse(data.id),status=z.enum(['SHIPPED','DELIVERED','CANCELLED']).parse(data.status);
 await db.$transaction(async tx=>{const o=await tx.order.findUniqueOrThrow({where:{id},include:{items:true}});const allowed:Record<string,string[]>={PAID:['SHIPPED'],SHIPPED:['DELIVERED'],PENDING:['CANCELLED']};if(!allowed[o.status]?.includes(status))throw new PublicError('تغییر وضعیت مجاز نیست؛ پرداخت فقط توسط درگاه تأیید می‌شود.');if(status==='CANCELLED' && o.authority)throw new PublicError('سفارش دارای درخواست پرداخت را ابتدا در درگاه بررسی کنید.');const changed=await tx.order.updateMany({where:{id,status:o.status},data:{status}});if(!changed.count)throw new PublicError('وضعیت سفارش تغییر کرده؛ صفحه را تازه کنید.');if(status==='CANCELLED')for(const i of o.items)await tx.product.update({where:{id:i.productId},data:{stock:{increment:i.quantity}}});});
 } else return NextResponse.json({error:'مسیر پیدا نشد'},{status:404});
 return NextResponse.json({ok:true});
 }
 return NextResponse.json({error:'مسیر پیدا نشد'},{status:404});
 }catch(error){return NextResponse.json({error:error instanceof z.ZodError?error.issues[0].message:error instanceof PublicError?error.message:'عملیات انجام نشد؛ داده تکراری یا وابسته را بررسی کنید.'},{status:error instanceof PublicError?error.status:400});}
}
