import { NextRequest,NextResponse } from 'next/server';
import { db } from '@/lib/db';
import { rateLimit } from '@/lib/auth';
import { gateway } from '@/lib/payment';
export async function GET(req:NextRequest) {
 const authority=req.nextUrl.searchParams.get('Authority');
 const base=process.env.APP_URL||req.nextUrl.origin;
 if(!authority||!/^A[0-9a-zA-Z]{35}$/.test(authority))return NextResponse.redirect(new URL('/track?payment=invalid',base));
 try{await rateLimit('callback:'+authority,15)}catch{return NextResponse.redirect(new URL('/track?payment=retry',base))}
 const order=await db.order.findUnique({where:{authority}});
 if(!order)return NextResponse.redirect(new URL('/track?payment=invalid',base));
 let result='failed';
 try {
 if(['PAID','SHIPPED','DELIVERED'].includes(order.status))result='success';
 else if(order.status==='PENDING') {
 const verified=await gateway('verify',{authority,amount:order.total*10});
 await db.order.updateMany({where:{id:order.id,status:'PENDING'},data:{status:'PAID',reference:String(verified.ref_id)}});result='success';
 }
 }catch{result='retry';}
 return NextResponse.redirect(new URL(`/track?code=${order.code}&payment=${result}`,base));
}
