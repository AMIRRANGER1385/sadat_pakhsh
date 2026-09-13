'use client';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from './shop';
export type Company={companyName:string;companyAbout:string;companyPhone:string;companyEmail:string;companyAddress:string;companyHours:string;companyPostalCode:string};
export function CompanyForm({company}:{company:Company}){
 const [message,setMessage]=useState(''),[busy,setBusy]=useState(false),[failed,setFailed]=useState(false);const router=useRouter();
 return <form className="panel form-stack" onSubmit={async e=>{e.preventDefault();setBusy(true);setMessage('');try{await api('admin/company',Object.fromEntries(new FormData(e.currentTarget)));setFailed(false);setMessage('اطلاعات شرکت ذخیره و در سایت نمایش داده شد.');router.refresh();}catch(e){setFailed(true);setMessage((e as Error).message)}finally{setBusy(false)}}}>
 <h2>اطلاعات شرکت</h2><p className="muted">این اطلاعات در صفحات تماس با ما، درباره ما و پایین سایت نمایش داده می‌شود. فقط اطلاعات واقعی و عمومی شرکت را وارد کنید.</p>
 {([['companyName','نام رسمی شرکت','text',120],['companyPhone','تلفن تماس (ارقام انگلیسی)','tel',25],['companyEmail','ایمیل عمومی','email',150],['companyPostalCode','کد پستی ۱۰ رقمی','text',10],['companyHours','روزها و ساعات پاسخ‌گویی','text',200]] as const).map(([key,label,type,max])=><label key={key}>{label}<input name={key} type={type} maxLength={max} defaultValue={company[key]} required={key==='companyName'}/></label>)}
 <label>نشانی شرکت<textarea name="companyAddress" maxLength={1000} rows={3} defaultValue={company.companyAddress}/></label><label>معرفی شرکت / درباره ما<textarea name="companyAbout" maxLength={6000} rows={7} defaultValue={company.companyAbout}/></label>
 {message&&<p role="status" className={failed?'error':'success'}>{message}</p>}<button className="btn" disabled={busy}>{busy?'در حال ذخیره...':'ذخیره اطلاعات شرکت'}</button></form>
}
