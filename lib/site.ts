import { cache } from 'react';
import { db } from './db';
export const siteName='نایلکس سادات';
export const siteDescription='خرید خرده و عمده نایلون، نایلکس، کیسه فریزر و کیسه زباله از نایلکس سادات؛ قیمت شفاف، موجودی به‌روز و ارسال به سراسر ایران.';
export function siteURL(path='/') { const url=new URL(process.env.APP_URL||'http://localhost:3000');return new URL(path,url.origin).href; }
export const companySettings=cache(()=>db.settings.findUniqueOrThrow({where:{id:'shop'}}));
export function jsonLD(value:unknown){return JSON.stringify(value).replace(/</g,'\\u003c');}
