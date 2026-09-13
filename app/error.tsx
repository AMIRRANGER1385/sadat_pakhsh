'use client';
export default function Error({reset}:{reset:()=>void}){return <div className="empty-state"><h1>دریافت اطلاعات ممکن نشد</h1><p>کمی بعد دوباره تلاش کنید. اگر مدیر هستید، اتصال دیتابیس را بررسی کنید.</p><button className="btn" onClick={reset}>تلاش دوباره</button></div>}
