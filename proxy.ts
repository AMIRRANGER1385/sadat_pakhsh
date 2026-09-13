import { NextRequest,NextResponse } from 'next/server';
export function proxy(req:NextRequest){
 const nonce=Buffer.from(crypto.randomUUID()).toString('base64');const dev=process.env.NODE_ENV!=='production';
 const csp=`default-src 'self'; script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${dev?" 'unsafe-eval'":''}; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https://images.unsplash.com; font-src 'self'; connect-src 'self'${dev?' ws: wss:':''}; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';${dev?'':' upgrade-insecure-requests;'}`;
 const headers=new Headers(req.headers);headers.set('x-nonce',nonce);headers.set('Content-Security-Policy',csp);
 const res=NextResponse.next({request:{headers}});res.headers.set('Content-Security-Policy',csp);
 if(/^\/(api|admin|account|cart|checkout|track|login|wholesale)(\/|$)/.test(req.nextUrl.pathname)){res.headers.set('Cache-Control','private, no-store');res.headers.set('X-Robots-Tag','noindex, nofollow');res.headers.set('Referrer-Policy','no-referrer');}
 return res;
}
export const config={matcher:['/((?!_next/static|_next/image|products/.*\\.(?:svg|png|jpg|jpeg|webp)|fonts/|media/|icon.svg|favicon.ico).*)']};
