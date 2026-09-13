import { NextRequest } from 'next/server';
export class PublicError extends Error {constructor(message:string,public status=400){super(message)}}
export async function readJSON(req:NextRequest){
 if(!req.headers.get('content-type')?.toLowerCase().startsWith('application/json'))throw new PublicError('نوع درخواست معتبر نیست.',415);
 if(Number(req.headers.get('content-length')||0)>32768)throw new PublicError('حجم درخواست بیش از حد مجاز است.',413);
 const reader=req.body?.getReader();if(!reader)throw new PublicError('درخواست خالی است.');
 let size=0;const chunks:Uint8Array[]=[];
 try{while(true){const {done,value}=await reader.read();if(done)break;size+=value.length;if(size>32768){await reader.cancel();throw new PublicError('حجم درخواست بیش از حد مجاز است.',413);}chunks.push(value)}}finally{reader.releaseLock()}
 try{const data=JSON.parse(Buffer.concat(chunks).toString('utf8'));if(!data||Array.isArray(data)||typeof data!=='object')throw new Error();return data;}catch{throw new PublicError('ساختار درخواست معتبر نیست.')}
}
