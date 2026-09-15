import { z } from 'zod';
export const passwordSchema=z.string().min(8,'رمز عبور حداقل ۸ کاراکتر باشد.').max(72).refine(v=>Buffer.byteLength(v,'utf8')<=72,'رمز عبور حداکثر ۷۲ بایت است (حروف فارسی حجم بیشتری دارند).');
export const companySchema=z.object({
 companyName:z.string().trim().min(2).max(120),
 companyAbout:z.string().trim().max(6000),
 companyPhone:z.string().trim().max(25).refine(v=>!v||/^\+?[0-9 ()-]{7,25}$/.test(v),'شماره تماس معتبر وارد کنید.'),
 companyEmail:z.union([z.literal(''),z.string().email().max(150)]),
 companyAddress:z.string().trim().max(1000),
 companyHours:z.string().trim().max(200),
 companyPostalCode:z.string().trim().regex(/^$|^\d{10}$/,'کد پستی باید ۱۰ رقم باشد.'),
});
