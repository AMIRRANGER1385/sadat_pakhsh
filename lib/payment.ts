import { PublicError } from './http';
const sandbox=()=>process.env.ZARINPAL_SANDBOX!=='false';
export async function gateway(action:'request'|'verify',data:Record<string,unknown>) {
 if(!process.env.ZARINPAL_MERCHANT_ID) throw new PublicError('درگاه هنوز پیکربندی نشده است.');
 const base=sandbox()?'https://sandbox.zarinpal.com':'https://api.zarinpal.com';
 const response=await fetch(`${base}/pg/v4/payment/${action}.json`,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({merchant_id:process.env.ZARINPAL_MERCHANT_ID,...data}),signal:AbortSignal.timeout(20000)});
 if(!response.ok) throw new PublicError('ارتباط با درگاه برقرار نشد.');
 const result=await response.json();
 if(!(action==='request'?[100]:[100,101]).includes(result.data?.code)) throw new PublicError('درگاه درخواست را تأیید نکرد. دوباره تلاش کنید.');
 if(action==='request' && !/^A[0-9a-zA-Z]{35}$/.test(result.data?.authority||''))throw new PublicError('پاسخ درگاه معتبر نیست.');
 if(action==='verify' && (!/^\d+$/.test(String(result.data?.ref_id||''))||String(result.data.ref_id)==='0'))throw new PublicError('شناسه تأیید پرداخت معتبر نیست.');
 return result.data;
}
export const paymentURL=(authority:string)=>`${sandbox()?'https://sandbox.zarinpal.com':'https://www.zarinpal.com'}/pg/StartPay/${encodeURIComponent(authority)}`;
