import type { MetadataRoute } from 'next';
import { db } from '@/lib/db';
import { siteURL } from '@/lib/site';
export const dynamic='force-dynamic';
export default async function sitemap():Promise<MetadataRoute.Sitemap>{const base=new URL(siteURL()).origin;const products=await db.product.findMany({where:{active:true},select:{slug:true}});return ['','/products','/about','/contact','/faq',...products.map(p=>'/products/'+p.slug)].map(path=>({url:base+path}))}
