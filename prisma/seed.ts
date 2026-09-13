import { PrismaClient } from '@prisma/client';
import { hash } from 'bcryptjs';
const db = new PrismaClient();
async function main() {
 const categories = await Promise.all(['کیسه فریزر','کیسه زباله','نایلون و نایلکس','بسته‌بندی','سفره یکبار مصرف'].map(name => db.category.upsert({where:{name},update:{},create:{name}})));
 const names = ['کیسه فریزر رولی ۲۵۰ عددی','کیسه زباله رولی بنددار','نایلکس دسته رکابی سفید','نایلون بسته‌بندی شفاف','کیسه فریزر زیپ‌دار','کیسه زباله صنعتی مشکی','سفره یکبار مصرف طرح برگ','نایلکس دسته موزی رنگی'];
 for (let i=0;i<names.length;i++) await db.product.upsert({where:{slug:`product-${i+1}`},update:{},create:{slug:`product-${i+1}`,name:names[i],description:'ساخته‌شده از مواد اولیه مرغوب، با دوخت مقاوم و ضخامت یکنواخت. انتخابی کاربردی برای مصرف روزانه خانه، فروشگاه و کسب‌وکار شما. این محصول نمونه است؛ مشخصات و قیمت‌ها را پیش از فروش واقعی در پنل مدیریت ویرایش کنید.',image:`/products/product-${i+1}.svg`,retail:[68000,95000,125000,89000,78000,165000,58000,145000][i],wholesale:[54000,78000,105000,72000,64000,139000,46000,120000][i],minimum:10,stock:150,featured:i<4,categoryId:categories[[0,1,2,3,0,1,4,2][i]].id,unit:i===2||i===3||i===7?'کیلوگرم':'بسته'}});
 await db.settings.upsert({where:{id:'shop'},update:{},create:{id:'shop'}});
 const password=process.env.ADMIN_PASSWORD;
 if (!password || password.length<12 || Buffer.byteLength(password,'utf8')>72) throw new Error('ADMIN_PASSWORD باید حداقل ۱۲ کاراکتر باشد. ابتدا npm run setup را اجرا کنید.');
 await db.user.upsert({where:{username:process.env.ADMIN_USERNAME||'admin'},update:{},create:{username:process.env.ADMIN_USERNAME||'admin',name:'مدیر فروشگاه',password:await hash(password,12),role:'ADMIN'}});
 console.log('۸ محصول نمونه و حساب مدیر آماده شدند.');
}
main().finally(()=>db.$disconnect());
