import { cookies } from 'next/headers';
import { createHash,randomBytes } from 'node:crypto';
import { db } from './db';
import { PublicError } from './http';
export const digest=(s:string)=>createHash('sha256').update(s).digest('hex');
export async function currentUser(){const token=(await cookies()).get('session')?.value;if(!token||!/^[a-f0-9]{64}$/.test(token))return null;const session=await db.session.findUnique({where:{id:digest(token)},include:{user:true}});return session&&session.expires>new Date()?session.user:null;}
export async function createSession(userId:string,event?:{kind:'LOGIN'|'REGISTER'|'PASSWORD_CHANGE';userAgent:string}){const jar=await cookies();const old=jar.get('session')?.value;const token=randomBytes(32).toString('hex');await db.$transaction(async tx=>{if(old)await tx.session.deleteMany({where:{id:digest(old)}});await tx.session.deleteMany({where:{expires:{lt:new Date()}}});if(event){await tx.loginEvent.deleteMany({where:{createdAt:{lt:new Date(Date.now()-90*86400000)}}});await tx.loginEvent.create({data:{userId,kind:event.kind,userAgent:event.userAgent.slice(0,300)}});}
 await tx.session.create({data:{id:digest(token),userId,expires:new Date(Date.now()+86400000)}})});jar.set('session',token,{httpOnly:true,secure:process.env.NODE_ENV==='production',sameSite:'lax',path:'/',maxAge:86400});}
// Each window has a distinct key; upsert atomically increments concurrent attempts.
export async function rateLimit(key:string,maximum=10){const window=900000;const now=Date.now();const id=digest(`${Math.floor(now/window)}:${key}`);const row=await db.rateLimit.upsert({where:{id},create:{id,expires:new Date((Math.floor(now/window)+1)*window)},update:{count:{increment:1}}});if(row.count>maximum)throw new PublicError('تعداد درخواست زیاد است. ۱۵ دقیقه دیگر تلاش کنید.',429);if(Math.random()<.01)await db.rateLimit.deleteMany({where:{expires:{lt:new Date(now)}}});}
