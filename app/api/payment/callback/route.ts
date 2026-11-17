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
 let result='failed',trackKey=order.reference||order.code;
 try {
 if(['PAID','PREPARING','SHIPPED','DELIVERED'].includes(order.status))result='success';
 else if(order.status==='PENDING') {
 const payable=Math.max(0,order.total-order.shipping);
 const verified=await gateway('verify',{authority,amount:payable*10});
 trackKey=String(verified.ref_id);
 await db.order.updateMany({where:{id:order.id,status:'PENDING'},data:{status:'PAID',reference:trackKey}});result='success';
 }
 }catch{result='retry';}
 return NextResponse.redirect(new URL(`/track?key=${trackKey}&payment=${result}`,base));
}
