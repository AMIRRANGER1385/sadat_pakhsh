import { NextRequest,NextResponse } from 'next/server';
import { mkdir,writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { randomUUID } from 'node:crypto';
import { currentUser,rateLimit } from '@/lib/auth';
import { PublicError } from '@/lib/http';
import { optimizeUpload,readUpload,uploadDirectory } from '@/lib/uploads';
export const runtime='nodejs';
export async function POST(req:NextRequest){try{
 if(req.headers.get('origin')!==new URL(process.env.APP_URL||req.url).origin)throw new PublicError('درخواست نامعتبر است.',403);
 const user=await currentUser();if(user?.role!=='ADMIN')throw new PublicError('دسترسی مجاز نیست.',403);
 await rateLimit('upload:'+user.id,40);
 if(!['image/jpeg','image/png','image/webp'].includes(req.headers.get('content-type')||''))throw new PublicError('فقط JPEG، PNG و WebP مجاز است.',415);
 const output=await optimizeUpload(await readUpload(req));const dir=uploadDirectory();await mkdir(dir,{recursive:true,mode:0o700});const name=randomUUID()+'.webp';
 await writeFile(join(dir,name),output,{flag:'wx',mode:0o600});
 return NextResponse.json({url:'/media/'+name},{status:201,headers:{'Cache-Control':'no-store'}});
 }catch(e){return NextResponse.json({error:e instanceof PublicError?e.message:'ذخیره تصویر انجام نشد؛ دسترسی پوشه آپلود را بررسی کنید.'},{status:e instanceof PublicError?e.status:500});}}
