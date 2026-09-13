import { headers } from 'next/headers';
import { jsonLD } from '@/lib/site';
export async function StructuredData({data}:{data:unknown}){const nonce=(await headers()).get('x-nonce')||undefined;return <script nonce={nonce} type="application/ld+json" dangerouslySetInnerHTML={{__html:jsonLD(data)}}/>}
