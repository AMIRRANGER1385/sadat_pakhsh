# انتشار نایلکس سادات روی sadatpakhsh.ir

**این راهنما مربوط به نسخه قدیمی Node.js است و با هاست PHP فعلی شما سازگار نیست. راهنمای درست نسخه جدید: [نصب PHP 8.2 و MySQL](php-store/INSTALL-FA.md). بسته آماده: [naylex-sadat-php82.zip](dist/naylex-sadat-php82.zip).**

این راهنما برای دامنه و اطلاعات هاستی است که اعلام کرده‌اید. ورود به پنل خصوصی هاست، تغییر DNS و انتشار واقعی از این محیط انجام نشده است. هیچ رمز ارسال‌شده در گفتگو در پروژه قرار نگرفته است.

## ۱. اول قابلیت Node.js را بررسی کنید

در cPanel دنبال **Setup Node.js App** یا **Application Manager** بگردید. پروژه به Node.js 22 یا 24، امکان اجرای build، نصب ماژول‌های بومی Prisma و sharp و دیسک دائمی قابل نوشتن نیاز دارد. PHP/MySQL به‌تنهایی کافی نیست. Node باید زیر حساب خودتان اجرا شود، نه root.

اگر این گزینه وجود ندارد، متن زیر را برای پشتیبانی پارس‌وی‌دی‌اس بفرستید:

> برای دامنه sadatpakhsh.ir یک فروشگاه Next.js 16 با Node.js 22 یا 24، Prisma/SQLite و sharp دارم. آیا سرویس من اجرای Node با Passenger یا reverse proxy، Terminal/SSH برای npm ci و npm run build، و پوشه دائمی قابل نوشتن خارج از public_html را پشتیبانی می‌کند؟ لطفاً Setup Node.js App یا Application Manager و SSL دامنه را فعال یا سرویس سازگار را معرفی کنید. برنامه باید یک نمونه اجرا داشته باشد و درخواست آپلود ۵ مگابایتی را بپذیرد.

اگر هاست چنین امکانی ندارد، باید هاست Node.js یا VPS استفاده شود؛ تبدیل این فروشگاه به چند فایل HTML، ورود و دیتابیس و پرداخت را از کار می‌اندازد. فایل `app.js` برای Passenger آماده شده، ولی سازگاری با تنظیمات واقعی سرویس باید روی خود هاست تست شود.

## ۲. دامنه و DNS

اطلاعات اعلام‌شده شما:

| تنظیم | مقدار |
|---|---|
| دامنه اصلی | `sadatpakhsh.ir` |
| NS اول | `ns1box407.parsvds.com` |
| NS دوم | `ns2box407.parsvds.com` |
| IP اعلام‌شده | `87.107.55.181` |

در پنل ثبت دامنه، در صورتی که این NSها همان مقادیر سرویس فعال شما هستند، nameserverهای دامنه را روی آن‌ها تنظیم کنید. در cPanel → Domains دامنه را اضافه کنید (اگر از قبل دامنه اصلی است، دوباره اضافه نکنید). در Zone Editor رکورد A دامنه باید به IP تأییدشده سرویس اشاره کند؛ برای www می‌توانید CNAME به `sadatpakhsh.ir` داشته باشید. رکوردهای ایمیل MX/TXT را دست‌کاری نکنید.

پس از اعمال DNS، SSL/AutoSSL را برای دامنه فعال و Force HTTPS Redirect را روشن کنید. آدرس اصلی این پروژه **https://sadatpakhsh.ir** بدون www است؛ www باید به همین آدرس هدایت شود. تنظیم APP_URL به‌تنهایی DNS یا هاست را وصل نمی‌کند.

عدد `8080` که فرستاده‌اید به‌عنوان پورت سایت تنظیم نشده است؛ ابتدا کاربرد آن را از میزبان بپرسید. کاربران باید سایت را روی HTTPS استاندارد، بدون نوشتن پورت، باز کنند. پورت داخلی برنامه را Passenger یا reverse proxy مدیریت می‌کند.

## ۳. انتقال فایل‌ها

کد را در پوشه‌ای مانند `/home/CPANEL_USER/naylex-app` خارج از `public_html` قرار دهید. نام واقعی حساب هاست را جایگزین `CPANEL_USER` کنید.

فایل‌های کد، `package.json`، `package-lock.json`، `app.js`، `next.config.ts`، `tsconfig.json`، `postcss.config.mjs`، پوشه‌های `app`، `components`، `lib`، `prisma`، `public` و `scripts` لازم‌اند. برای ساخت قابل تکرار، کل سورس پروژه به جز موارد زیر منتقل شود:

- `node_modules` و `.next` ویندوز: روی لینوکس هاست دوباره نصب و build کنید.
- `.env` محلی، `.runtime`، `.git`، پشتیبان‌ها و دیتابیس تست: در بسته عمومی قرار نگیرند.
- فایل SQLite واقعی و عکس‌های آپلودشده را جدا و محرمانه طبق مرحله بعد منتقل کنید، اگر قصد حفظ محصولات و مشتریان فعلی دارید.

فقط قرار دادن index.html یا کل سورس در public_html کافی نیست و ممکن است فایل‌های حساس را در دسترس وب قرار دهد.

## ۴. تنظیم محیط سرور

فایل `deploy/cpanel.env.example` را **روی سرور** به `.env` در ریشه برنامه کپی کنید. مسیرهای CPANEL_USER و رمز جدید مدیر و مرچنت را تکمیل کنید. متغیرهای تعریف‌شده در صفحه Node.js App باید با این فایل یکسان باشند؛ متغیر محیطی سیستم بر فایل اولویت دارد.

```dotenv
NODE_ENV="production"
APP_URL="https://sadatpakhsh.ir"
DATABASE_URL="file:/home/CPANEL_USER/naylex-data/shop.db"
UPLOAD_DIR="/home/CPANEL_USER/naylex-data/uploads"
ADMIN_USERNAME="admin"
ADMIN_PASSWORD="رمز جدید و قوی خودتان"
ZARINPAL_MERCHANT_ID="مرچنت دریافتی از زرین‌پال"
ZARINPAL_SANDBOX="true"
```

پوشه `naylex-data` را خارج از public_html ایجاد کنید. مالک آن باید همان کاربر اجرای Node باشد؛ برای مالک دسترسی خواندن و نوشتن بدهید، از مجوز 777 استفاده نکنید. `.env` و دیتابیس خصوصی‌اند. عکس‌های آپلودشده توسط مسیر امن `/media/...` به‌عنوان تصاویر عمومی کالا ارائه می‌شوند؛ عکس مدرک شخصی را در این قسمت آپلود نکنید.

**نصب تازه:** در Terminal/SSH، محیط Node مربوط به اپ را فعال کنید (دستور دقیق activation در Setup Node.js App نمایش داده می‌شود). سپس:

```sh
cd /home/CPANEL_USER/naylex-app
npm ci --include=dev
npm run setup
npm run build
```

setup در نصب تازه دیتابیس و ۸ محصول نمونه را می‌سازد. برای حفظ فروشگاه فعلی، قبل از setup سرور محلی را متوقف و از `prisma/dev.db` پشتیبان سازگار SQLite تهیه کنید؛ آن را به مسیر DATABASE_URL سرور منتقل کنید و پوشه `.data/uploads` محلی را به UPLOAD_DIR سرور کپی کنید. در این حالت `npm run db:push` و `npm run build` را اجرا کنید و seed را لازم نیست تکرار کنید. رمز مدیر موجود در دیتابیس حفظ می‌شود؛ ADMIN_PASSWORD رمز آن حساب را بازنویسی نمی‌کند. اگر db:push هشدار حذف داده داد، متوقف شوید و migration را بررسی کنید.

پوشه‌های دائمی دیتابیس و عکس‌ها را با هر انتشار پاک یا جایگزین نکنید. از هر دو به‌صورت منظم پشتیبان بگیرید. SQLite این پروژه برای یک نمونه برنامه مناسب است؛ اجرای چند سرور به دیتابیس و ذخیره‌ساز مشترک نیاز دارد.

## ۵. اجرای دائمی در cPanel

اگر **Setup Node.js App** دارید:

| فیلد | مقدار |
|---|---|
| Node.js version | 22 یا 24 |
| Application mode | Production |
| Application root | `naylex-app`، نسبت به home حساب |
| Application URL | `sadatpakhsh.ir`، مسیر `/` |
| Application startup file | `app.js` |

پس از نصب و build، از Start/Restart همان پنل استفاده کنید. اگر **Application Manager** دارید، Register Application را با مسیر سورس `naylex-app`، دامنه اصلی، Base URL برابر `/` و محیط Production ثبت کنید. entry پیش‌فرض Passenger فایل `app.js` است که به پروژه اضافه شده است.

در محیط Passenger نیازی به باز گذاشتن ترمینال یا اجرای `npm run dev` ندارید. برای سرور معمولی بدون Passenger، `npm start` باید توسط سرویس دائمی مثل systemd/PM2 و reverse proxy میزبان مدیریت شود؛ اجرای صرف آن در ترمینال راه‌اندازی دائمی نیست.

بعد از تغییر متغیرها یا کد، build و Restart انجام دهید. این پروژه custom server مخصوص cPanel دارد و نباید هم‌زمان با output standalone استفاده شود.

## ۶. اتصال بانکی زرین‌پال

کد اتصال آماده است. در پنل زرین‌پال برای دامنه `sadatpakhsh.ir` درگاه ایجاد/فعال و Merchant ID را دریافت کنید. مرچنت را فقط در متغیر `ZARINPAL_MERCHANT_ID` سرور قرار دهید، نه در کد مرورگر و نه در گفتگو.

برای تست `ZARINPAL_SANDBOX=true` بماند؛ پذیرش مرچنت در Sandbox باید با سرویس بررسی شود. پس از تست و تأیید درگاه اصلی، آن را به `false` تغییر و اپ را Restart کنید. مسیر بازگشت:

```text
https://sadatpakhsh.ir/api/payment/callback
```

قیمت‌های فروشگاه تومان هستند؛ ارسال و تأیید مبلغ در API به ریال انجام می‌شود. نتیجه پرداخت در سرور با زرین‌پال verify می‌شود؛ هیچ اطلاعات کارت بانکی اینجا ذخیره نمی‌شود. لازم است سرور به سرویس زرین‌پال دسترسی خروجی HTTPS داشته باشد.

اگر منظور شما درگاه مستقیم یک بانک خاص است، نام بانک/PSP و مستندات آن لازم است؛ کد فعلی برای زرین‌پال است. تست سرتاسری Sandbox و سپس یک پرداخت واقعی کنترل‌شده باید پیش از فروش عمومی انجام شود؛ در این محیط انجام نشده است.

## ۷. بررسی پس از انتشار

- صفحه اصلی، عکس نمونه و **عکس تازه آپلودشده** پس از Restart باز شوند.
- از `/admin` وارد شوید؛ محصول و اطلاعات شرکت را ذخیره و سابقه ورود را ببینید.
- رمز مدیر اولیه را از «امنیت حساب» تغییر دهید.
- http و www به آدرس اصلی HTTPS هدایت شوند؛ ورود خطای Origin ندهد.
- callback، نتیجه پرداخت و عدم پرداخت تکراری را تست کنید.
- `https://sadatpakhsh.ir/sitemap.xml` را در Google Search Console ثبت کنید.

خطای 403 هنگام ذخیره معمولاً به تفاوت APP_URL با آدرس مرورگر (www، http یا پورت) مربوط است. خطای 503 می‌تواند از build ناقص، entry اشتباه، نسخه Node یا محدودیت منابع باشد؛ logs برنامه و پشتیبانی هاست را بررسی کنید. خطای Prisma/sharp پس از انتقال node_modules ویندوز با نصب مجدد روی خود لینوکس رفع می‌شود.

## منابع رسمی

- [Application Manager در cPanel](https://docs.cpanel.net/cpanel/software/application-manager/)
- [نصب Node.js در cPanel](https://docs.cpanel.net/knowledge-base/web-services/how-to-install-a-node.js-application/)
- [Zone Editor](https://docs.cpanel.net/cpanel/domains/zone-editor/)
- [نمونه رسمی درخواست و تأیید زرین‌پال](https://github.com/ZarinPal-Lab/Zarinpal-RestAPI-Sample-php/)
