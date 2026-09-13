import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { mediaName,uploadDirectory } from '@/lib/uploads';
export const runtime='nodejs';
export async function GET(_req:Request,{params}:{params:Promise<{name:string}>}){const {name}=await params;
 if(!mediaName.test(name))return new Response(null,{status:404});
 try{const bytes=await readFile(join(uploadDirectory(),name));return new Response(new Uint8Array(bytes),{headers:{'Content-Type':'image/webp','Content-Length':String(bytes.length),'X-Content-Type-Options':'nosniff','Cache-Control':'public, max-age=31536000, immutable','Content-Disposition':`inline; filename="${name}"`}})}catch(e){if((e as NodeJS.ErrnoException).code==='ENOENT')return new Response(null,{status:404});return new Response(null,{status:500})}
}
