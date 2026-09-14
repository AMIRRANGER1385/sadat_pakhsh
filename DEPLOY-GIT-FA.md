# انتشار نسخه PHP با Git و cPanel

این روش برای به‌روزرسانی فروشگاه PHP نصب‌شده است، نه نصب اولیه. نیازمند Git Version Control در cPanel و ابزار rsync روی هاست است. فعال‌بودن این امکانات روی سرویس شما هنوز تأیید نشده است. Node.js، Composer و توکن cPanel برای روش دستی زیر لازم نیست.

## یک بار تنظیم

1. در GitHub یک مخزن Private خالی بسازید. اگر مخزن موجود دارید از همان استفاده کنید. اطلاعات محرمانه نباید در فایل‌ها یا تاریخچه مخزن باشند؛ private بودن جایگزین این بررسی نیست.
2. در VS Code ترمینال را در ریشه پروژه باز کنید. فایل‌های انتشار را انتخاب و بررسی کنید:

```powershell
git status
git add .gitignore .gitattributes .cpanel.yml deploy/cpanel-deploy.sh php-store scripts/package-php.py SEO-SITEMAP-FA.md README.md DEPLOY-GIT-FA.md
git diff --cached --stat
git diff --cached
git commit -m "Prepare PHP store deployment"
```

فایل config.php محلی، .env، cpanel-access.json و storage نباید در تغییرات باشند. فایل‌های قبلاً track‌شده با افزودن به .gitignore از تاریخچه حذف نمی‌شوند. اگر اطلاعات محرمانه قبلاً commit شده، پیش از push توکن/رمز را تعویض و تاریخچه را پاک‌سازی کنید. از git add . برای اولین انتشار بدون بررسی استفاده نکنید.

3. نام شاخه و remote را ببینید:

```powershell
git branch --show-current
git remote -v
```

اگر origin ندارید، آدرس مخزن خودتان را جایگزین نمونه کنید:

```powershell
git remote add origin https://github.com/YOUR_ACCOUNT/YOUR_REPOSITORY.git
git push -u origin YOUR_BRANCH
```

YOUR_BRANCH یعنی همان نام شاخه‌ای که دستور قبلی نشان داد (مثلاً main). اگر origin موجود است آن را دوباره اضافه نکنید. ورود GitHub را از Git Credential Manager انجام دهید؛ رمز یا توکن را در آدرس remote ننویسید.

4. برای دسترسی هاست به مخزن Private، از SSH key مخصوص هاست و Deploy Key فقط‌خواندنی مخزن استفاده کنید. کلید عمومی را در GitHub → Repository Settings → Deploy keys اضافه کنید؛ کلید خصوصی روی هاست بماند. در صورت نداشتن Terminal یا امکان تنظیم SSH از پشتیبانی کمک بگیرید. host key سرویس Git باید معتبر باشد؛ بررسی آن را غیرفعال نکنید.
5. در cPanel → Git Version Control → Create، Clone a Repository را فعال کنید. Clone URL برای GitHub معمولاً `git@github.com:YOUR_ACCOUNT/YOUR_REPOSITORY.git` است. Repository Path را `/home/rrjjboqm/repositories/naylex` بگذارید. **مخزن را داخل public_html نسازید.**
6. در Manage مخزن، شاخه صحیح را انتخاب کنید. در Pull or Deploy ابتدا Update from Remote و سپس Deploy HEAD Commit را بزنید.

## اسکریپت چه می‌کند؟

`.cpanel.yml` اسکریپت `deploy/cpanel-deploy.sh` را اجرا می‌کند. اسکریپت از HOME واقعی حساب استفاده می‌کند و مقصدها را `~/naylex-app` و `~/public_html` می‌گیرد. اگر Document Root شما متفاوت است، پیش از اولین انتشار مقدار web_dir را اصلاح کنید؛ حدس نزنید.

ابتدا وجود نصب PHP بررسی می‌شود. سپس نسخه فایل‌ها در `~/naylex-deploy-backups/زمان-اجرا/` پشتیبان گرفته می‌شود و فقط دو پوشه PHP پروژه روی مقصد کپی می‌شوند. config.php و storage خصوصی دست‌نخورده می‌مانند؛ .git و فایل‌های Next.js منتقل نمی‌شوند. فایل‌های قدیمی خودکار حذف نمی‌شوند. هیچ SQL، نصب مجدد یا مهاجرت دیتابیس اجرا نمی‌شود.

این پشتیبان مربوط به فایل‌هاست؛ قبل از انتشار از دیتابیس و storage/uploads هم جداگانه بکاپ بگیرید. عملیات کپی اتمی نیست؛ در زمان کم‌ترافیک منتشر کنید. در صورت شکست، پیام خطا را بررسی و فایل‌های پشتیبان همان اجرا را بازیابی کنید؛ رمز و تنظیمات فعلی را جایگزین نکنید. با زیادشدن تعداد نسخه‌ها فضای هاست را کنترل کنید.

## دفعات بعد

پس از تغییر و تست محلی، فایل‌های مربوط را git add کنید، diff را ببینید، commit کنید و `git push` بزنید. در cPanel دوباره **Update from Remote → Deploy HEAD Commit** را اجرا کنید. Push به GitHub به‌تنهایی سایت را تغییر نمی‌دهد. سایت، /products، /products/، /sitemap.xml، ورود و سبد را بررسی کنید.

انتشار خودکار با push مستقیم به مخزن مدیریت‌شده cPanel نیز ممکن است، اما روش این راهنما انتشار دستی پس از دریافت از GitHub است. از webhook عمومی بدون احراز هویت استفاده نکنید.

اگر Deploy غیرفعال است، بررسی کنید `.cpanel.yml` commit شده، شاخه موجود است و checkout هاست تغییر ذخیره‌نشده ندارد. فایل‌های checkout را از File Manager ویرایش نکنید؛ تغییر را محلی commit کنید. خطای Permission denied (publickey) مربوط به کلید SSH مخزن است؛ خطای rsync مربوط به نبودن ابزار یا دسترسی فایل‌هاست.

منبع: [راهنمای رسمی Git Deployment در cPanel](https://docs.cpanel.net/knowledge-base/web-services/guide-to-git-deployment/).
