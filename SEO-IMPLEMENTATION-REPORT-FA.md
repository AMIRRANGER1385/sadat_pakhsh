# گزارش پیاده‌سازی سئوی داخلی نایلکس سادات

## ۱. خلاصه ممیزی اولیه

پروژهٔ Production یک فروشگاه PHP 8.2 با Router مرکزی، MySQL، پنل مدیریت، محصولات، دسته‌ها، مقاله‌ها، راهنماهای ثابت، sitemap index و JSON-LD است. URLهای عمومی از PHP رندر می‌شوند و Apache همه مسیرهای غیرفایلی را به `index.php` می‌فرستد. نسخهٔ Next.js نیز در Repository وجود دارد، اما بستهٔ فعال CPANEL از `php-store` ساخته می‌شود.

## ۲. مشکلات پیدا‌شده

- لندینگ مستقل برای «خرید نایلکس» وجود نداشت.
- صفحه‌ای برای قصد جستجوی «تولیدکننده نایلکس» وجود نداشت.
- `/wholesale` و `/wholesale-buying` هدف تجاری مشابه داشتند.
- صفحه عمده بیشتر کاتالوگ بود و محتوای تصمیم‌ساز و CTA قیمت روز نداشت.
- عنوان و متای اختصاصی محصول در پنل قابل مدیریت نبود.
- شماره تلفن به‌صورت خودکار شماره واتساپ فرض می‌شد.
- چند مقاله مهم درباره قیمت، رکابی، دسته موزی و خرید عمده وجود نداشت.
- کش فایل‌های تصویری و فشرده‌سازی پاسخ‌های متنی قابل بهبود بود.

## ۳. تغییرات پیاده‌شده

- معماری هدف بر اساس Search Intent ایجاد شد.
- لندینگ خرید نایلکس، لندینگ تولیدکننده و محتوای کامل عمده اضافه شد.
- مسیر عمده قدیمی با 301 ادغام شد.
- SEO محصول در پنل مدیریت توسعه یافت.
- CTA تلفن و واتساپ امن و قابل تنظیم شد.
- مقاله‌های جدید، لینک‌سازی داخلی و schema تکمیل شدند.
- sitemap، ناوبری، فوتر و تنظیمات Apache به‌روز شدند.

## ۴. صفحات جدید

- `/products`: صفحه موجود، اکنون فروش خرد نایلکس و محصولات پلاستیکی
- `/nylex-manufacturer`: تولیدکننده نایلکس در تهران
- `/guides/nylex-price-factors`
- `/guides/rackabi-nylex-guide`
- `/guides/banana-handle-nylex-guide`
- `/guides/wholesale-buying-checklist`
- `/guides/kilo-nylex-guide`
- `/guides/transparent-white-black-nylex`

## ۵. صفحات اصلاح‌شده

- `/wholesale`: محصولات، قیمت، توضیح قیمت روز، معرفی تولیدکننده، مراحل سفارش، FAQ و CTA
- `/products`: عنوان، توضیح، CollectionPage و ItemList
- صفحات محصول: عنوان، متا و متن معرفی قابل مدیریت
- صفحه اصلی، منو و فوتر: لینک مستقیم به صفحات اولویت‌دار
- `/wholesale-buying`: انتقال دائمی 301 به `/wholesale`

## ۶. نگاشت Keyword به URL

نقشه کامل در `SEO-KEYWORD-MAP-FA.md` ثبت شده است. هدف‌ها از هم جدا هستند: خرید عمومی در `/products`، عمده در `/wholesale`، تولید در `/nylex-manufacturer`، نام دقیق کالا در Product و پرسش‌های آموزشی در Guide.

## ۷. Title و H1

- `/products`: «فروش خرد نایلکس و محصولات پلاستیکی» / «فروش خرد نایلکس و محصولات پلاستیکی»
- `/wholesale`: «خرید عمده نایلکس | قیمت مستقیم از تولیدکننده» / «خرید عمده نایلکس مستقیم از تولیدکننده»
- `/nylex-manufacturer`: «تولیدکننده نایلکس در تهران؛ فروش مستقیم» / «تولیدکننده نایلکس و فروش مستقیم»
- محصول: Title اختصاصی مدیر یا مقدار خودکار؛ H1 همان نام دقیق محصول

نام برند در `<title>` به‌صورت مرکزی اضافه می‌شود.

## ۸. مقاله‌ها

شش راهنمای جدید با محتوای کامل، H2، لینک داخلی و Article schema اضافه شد. مقالات قبلی درباره تفاوت نایلون و نایلکس، تعداد در کیلو، ضخامت، خرید فروشگاهی، کیسه فریزر و کیسه زباله حفظ شدند. صفحه تجاری برای نایلکس چاپی ساخته نشد.

## ۹. تغییرات Technical SEO

- canonical مستقل روی صفحات indexable
- `noindex,follow` روی فیلتر و جستجو
- `noindex,nofollow` و X-Robots-Tag روی مسیرهای خصوصی
- 301 برای trailing slash، slug قدیمی، `/plastic-products` و `/wholesale-buying`
- 301 برای Host غیر اصلی در درخواست GET/HEAD
- 404 واقعی و noindex برای مسیرهای ناموجود
- `lang="fa-IR"` و `dir="rtl"`
- OG، Twitter Card، favicon و تصویر اشتراک‌گذاری

## ۱۰. Structured Data

- `Organization` با نام، تلفن، تهران، کشور ایران و محدوده خدمت ایران
- `WebSite` و SearchAction
- `CollectionPage` و `ItemList` برای فهرست‌ها
- `Product` و `Offer` مبتنی بر قیمت و موجودی دیتابیس
- `Article` برای راهنماها
- `BreadcrumbList` برای صفحات سلسله‌مراتبی

Rating، Review، تخفیف و قیمت ساختگی اضافه نشده است.

## ۱۱. لینک‌سازی داخلی

- خانه به خرید نایلکس، عمده و تولیدکننده
- خرید نایلکس به محصولات، عمده و راهنماها
- عمده به خرید نایلکس، تولیدکننده و چک‌لیست
- تولیدکننده به عمده و محصولات
- راهنماها به دسته، محصولات، عمده و تماس
- محصولات به دسته، عمده متناظر، محصولات مشابه و راهنماها

## ۱۲. Sitemap و robots.txt

Sitemap index شامل صفحات، محصولات، دسته‌ها، راهنماها و مقاله‌های دیتابیس است. صفحات جدید اضافه و `/wholesale-buying` حذف شد. Admin، Login، Account، Cart، Checkout، Search و URLهای پارامتری در sitemap نیستند. robots.txt منابع CSS/JS را مسدود نمی‌کند و آدرس sitemap را اعلام می‌کند.

## ۱۳. Performance

- ابعاد تصاویر برای جلوگیری از CLS حفظ شد.
- تصاویر کارت‌ها lazy و async هستند.
- تصویر اصلی محصول دارای اولویت بارگذاری و decode غیرهم‌زمان است.
- فونت با `font-display: swap` بارگذاری می‌شود.
- کش فایل‌های تصویر ۳۰ روز و CSS/JS/Font هفت روز است.
- فشرده‌سازی HTML، CSS، JS، JSON، XML و SVG در Apache فعال شد.

## ۱۴. تغییرات دیتابیس

جدول `ns_product_seo` به‌صورت backward-safe با `CREATE TABLE IF NOT EXISTS` اضافه شد. داده‌های محصولات، کاربران، سفارش‌ها و IDها تغییر نمی‌کنند. فیلدهای سئو شامل عنوان، توضیحات متا، عبارت هدف و متن معرفی هستند.

## ۱۵. فایل‌های اصلی تغییرکرده

- `php-store/naylex-app/categories.php`
- `php-store/naylex-app/landing-pages.php`
- `php-store/naylex-app/product-seo.php`
- `php-store/naylex-app/pages.php`
- `php-store/naylex-app/views.php`
- `php-store/naylex-app/nylex-guides.php`
- `php-store/naylex-app/seo.php`
- `php-store/naylex-app/sitemap.php`
- `php-store/naylex-app/actions.php`
- `php-store/naylex-app/admin.php`
- `php-store/naylex-app/bootstrap.php`
- `php-store/naylex-app/schema.sql`
- `php-store/public_html/.htaccess`

## ۱۶. تست‌ها

- PHP syntax روی فایل‌های تغییرکرده
- ۳۲ تست PHP/MySQL اصلی
- ۱۲ تست انقضای سفارش
- ۱۳ تست فروش خرده/عمده
- ۳ تست ورود با نام/موبایل
- تست امنیت و رگرسیون XSS، CSRF، redirect و traversal
- تست ۵۱ URL نقشه سایت، canonical، meta، H1 و schema
- تست sitemap index و robots
- crawl پنجاه URL داخلی بدون لینک شکسته
- تست مرورگر موبایل برای سه لندینگ جدید
- ممیزی نسخه آنلاین دامنه، HTTP→HTTPS، robots و sitemap

## ۱۷. موارد باقی‌مانده

- محتوای دقیق Product Pages به فهرست واقعی مشخصات محصولات وابسته است.
- بعضی داده‌های LocalBusiness مانند نشانی دقیق قابل انتشار، روزهای کاری و شماره قطعی واتساپ هنوز تأیید نشده‌اند؛ چیزی حدس زده نشد.
- نام فایل تصاویر آپلودی امنیتی و تصادفی است؛ ALT از نام واقعی محصول ساخته می‌شود.
- نسخه آنلاین تا Deploy همچنان خروجی قبلی را نشان می‌دهد.

## ۱۸. اقدامات دستی بعد از Deploy

1. در پنل مدیریت، «اطلاعات شرکت» را باز کنید و شماره واتساپ را فقط اگر همان شماره واقعاً فعال است ثبت کنید.
2. ساعات پاسخ‌گویی، نشانی و تلفن را بررسی و ذخیره کنید.
3. برای هر محصول، عنوان سئو، توضیحات متا و متن معرفی اختصاصی را با اطلاعات واقعی تکمیل کنید.
4. در Google Search Console، Property دامنه `sadatpakhsh.ir` را باز کنید.
5. در بخش Sitemaps، فقط `https://sadatpakhsh.ir/sitemap.xml` را ثبت یا دوباره Submit کنید.
6. با URL Inspection این سه URL را بررسی و Request Indexing کنید: `/products`، `/wholesale` و `/nylex-manufacturer`.
7. URL قدیمی `/wholesale-buying` را Inspect کنید و مطمئن شوید Google پاسخ 301 و مقصد `/wholesale` را می‌بیند.
8. گزارش Page indexing را پس از Crawl بررسی کنید؛ فیلترها و صفحات حساب باید Excluded by noindex باشند.
9. گزارش Merchant listings و Product snippets را برای خطاهای Product schema بررسی کنید.
10. پس از جمع‌شدن داده، Performance و Queries هر لندینگ را در بازه‌های ۲۸ روزه مقایسه کنید؛ برای هر عبارت صفحه جدید نسازید و ابتدا URL هدف همین نقشه را تقویت کنید.
