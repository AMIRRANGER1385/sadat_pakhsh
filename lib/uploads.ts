import sharp from 'sharp';
import { resolve } from 'node:path';
import { PublicError } from './http';
export const MAX_UPLOAD_BYTES=5*1024*1024;
export const mediaName=/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}\.webp$/;
// Runtime persistent storage, never part of a build/deployment bundle.
export const uploadDirectory=()=>resolve(/* turbopackIgnore: true */ process.env.UPLOAD_DIR||'.data/uploads');
export async function optimizeUpload(input:Buffer){
 if(!input.length||input.length>MAX_UPLOAD_BYTES)throw new PublicError('حجم تصویر باید بین ۱ بایت و ۵ مگابایت باشد.',413);
 const jpeg=input[0]===0xff&&input[1]===0xd8&&input[2]===0xff;
 const png=input.subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10]));
 const webp=input.subarray(0,4).toString()==='RIFF'&&input.subarray(8,12).toString()==='WEBP';
 if(!jpeg&&!png&&!webp)throw new PublicError('محتوای فایل باید تصویر JPEG، PNG یا WebP واقعی باشد.');
 try{const image=sharp(input,{limitInputPixels:20000000,animated:false,failOn:'warning'});const meta=await image.metadata();
 if(!['jpeg','png','webp'].includes(meta.format||'')||(meta.pages||1)>1)throw new PublicError('فقط تصاویر ثابت JPEG، PNG و WebP پذیرفته می‌شوند. SVG و تصاویر متحرک مجاز نیستند.');
 return await image.rotate().resize({width:1600,height:1600,fit:'inside',withoutEnlargement:true}).webp({quality:84}).toBuffer();
 }catch(e){if(e instanceof PublicError)throw e;throw new PublicError('فایل تصویر معتبر نیست یا ابعاد آن بیش از حد مجاز است.');}
}
export async function readUpload(req:Request){
 if(Number(req.headers.get('content-length')||0)>MAX_UPLOAD_BYTES)throw new PublicError('حداکثر حجم تصویر ۵ مگابایت است.',413);
 const reader=req.body?.getReader();if(!reader)throw new PublicError('فایلی انتخاب نشده است.');
 let size=0;const chunks:Uint8Array[]=[];
 try{while(true){const {done,value}=await reader.read();if(done)break;size+=value.length;if(size>MAX_UPLOAD_BYTES){await reader.cancel();throw new PublicError('حداکثر حجم تصویر ۵ مگابایت است.',413);}chunks.push(value)}}finally{reader.releaseLock()}
 return Buffer.concat(chunks);
}
