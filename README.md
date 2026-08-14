# bagisto-shop

فروشگاه‌ساز فارسی بر پایهٔ بجیستو، با یک تفاوت: **ماژول‌ها از داخل پنل نصب
می‌شوند** — بدون SSH، بدون composer، بدون باز کردن دستی zip.

- لاراول ۱۱ + بجیستو ۲.۳.۷ (هستهٔ `packages/Webkul` دست‌نخورده)
- فارسی و راست‌به‌چپ به‌صورت پیش‌فرض
- تومان به‌عنوان ارز پایه، با قالب‌بندی درست: `۱۲۳٬۴۵۰ تومان`
- ماژول `Marketplace` برای نصب و به‌روزرسانی افزونه‌ها از پنل

## نصب

راهنمای کامل و قدم‌به‌قدم: **[INSTALL.md](INSTALL.md)**

خلاصه‌اش روی سرور مجازی:

```bash
git clone https://github.com/chalaksoft/bagisto-shop.git
cd bagisto-shop
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# .env را ویرایش کنید، بعد:
php artisan bagisto:install
```

روی هاست اشتراکی composer لازم نیست؛ فایل آمادهٔ
[Releases](https://github.com/chalaksoft/bagisto-shop/releases) را دانلود کنید.
Node.js هم لازم نیست — فایل‌های ظاهر سایت از قبل ساخته شده‌اند.

---

## چرا این نسخه

بجیستو راه رسمی نصب افزونه ندارد. افزونه‌ها دستی نصب می‌شوند: باز کردن zip در
`packages/`، ویرایش `psr-4` در `composer.json`، بعد `migrate` و `vendor:publish`
از خط فرمان. یعنی هر بار SSH و composer — چیزی که روی هاست اشتراکی اصلاً نیست و
صاحب فروشگاه هم بلد نیست.

اینجا مثل وردپرس است: یک صفحه در پنل، فهرست ماژول‌ها، دکمهٔ نصب.

---

## نصب ماژول از پنل

توکن را موقع خرید می‌گیرید و در `.env` می‌گذارید:

```env
MARKETPLACE_URL=https://bagisto-shop.ir
MARKETPLACE_TOKEN=mkt_...
MARKETPLACE_REQUIRE_SIGNATURE=true
```

کلید عمومی مخزن هم که همراه توکن می‌فرستیم در
`Modules/Marketplace/resources/keys/repository.pub` می‌نشیند. بعد:

**پنل مدیریت → ماژول‌ها → مخزن → نصب**

از خط فرمان هم همان کار انجام می‌شود:

```bash
php artisan module:list                       # ماژول‌های نصب‌شده و ترتیب بوتشان
php artisan module:repository                 # فهرست مخزن و به‌روزرسانی‌های موجود
php artisan module:install --slug=invoice-pro
php artisan module:install /path/to/package.zip
php artisan module:update                     # همهٔ ماژول‌ها؛ روی VPS در cron
php artisan module:enable  InvoicePro
php artisan module:disable InvoicePro --force
php artisan module:remove  InvoicePro --rollback-migrations
```

---

## چطور کار می‌کند

### ثبت ماژول‌ها دستی نیست

`bootstrap/providers.php` لیست ثابت ماژول‌ها را ندارد. `App\Classes\ModuleRegistry`
هر `Modules/*/module.json` را می‌خواند، غیرفعال‌ها و آن‌هایی که پیش‌نیازشان نیست
را کنار می‌گذارد و بقیه را بر اساس `priority` مرتب می‌کند. وضعیت فعال/غیرفعال در
جدول `installed_modules` است، نه در کد — همین چیزی است که نصب در زمان اجرا را
ممکن می‌کند.

⚠️ هر نوشتنِ مستقیم روی `installed_modules` باید `ModuleRegistry::flush()` صدا
بزند، وگرنه کش رجیستری و `bootstrap/cache/services.php` تغییر را نمی‌بینند.

### نصب بدون شل

`Artisan::call()` داخل همان ریکوئست HTTP اجرا می‌شود؛ کلید پشتیبانی از هاست
اشتراکی همین است. هر مرحله — دانلود، بررسی امضا، سازگاری، استخراج، ثبت، مهاجرت،
انتشار دارایی، پاک‌سازی کش — متد جداست و وضعیتش در `module_installations`
می‌نشیند، پس تایم‌اوت ۳۰ ثانیه‌ای هاست نصب را نصفه رها نمی‌کند و ادامه‌اش از
همان‌جا گرفته می‌شود.

اگر نصب وسط کار شکست بخورد، نسخهٔ قبلی از بکاپ برمی‌گردد و سایت سرپا می‌ماند.

⚠️ پرووایدر ماژول تازه در همان ریکوئستِ نصب بوت **نمی‌شود** — مثل وردپرس، بعد از
نصب ریدایرکت می‌شود تا ریکوئست بعدی آن را بالا بیاورد.

### امنیت

این قابلیت ذاتاً یعنی «کد PHP را از اینترنت بگیر و اجرا کن»، پس:

- **امضای دیجیتال اجباری** — بدون امضای معتبر نصب متوقف می‌شود؛ نه با هشدار،
  بلکه با توقف کامل
- **مبدأ قفل** — فقط `MARKETPLACE_URL`؛ آدرس دلخواه از پنل گرفته نمی‌شود
- **path traversal و zip bomb** — تک‌تک ورودی‌های zip پیش از استخراج بررسی می‌شوند
- **ACL جدا** برای دیدن، نصب، فعال/غیرفعال و حذف؛ نصب و حذف علاوه بر آن نقش
  سوپرادمین می‌خواهند
- **لاگ** — هر نصب، به‌روزرسانی و حذف با کاربر، نسخه، چکسام و زمان ثبت می‌شود

---

## ساختن ماژول

zip دقیقاً یک پوشه در ریشه دارد، هم‌نام ماژول:

```
InvoicePro/
  module.json                ← اجباری، فیلد name باید با نام پوشه یکی باشد
  ModuleServiceProvider.php  ← اجباری
  Database/migrations/…
  Http/routes/admin.php
  resources/views/…
  resources/publishes/…      ← به public/vendor/{alias}/ کپی می‌شود
  vendor/                    ← اختیاری: وابستگی‌های composer بسته‌بندی‌شده
```

جزئیات و نمونهٔ `module.json` در
[`Modules/Marketplace/README.md`](Modules/Marketplace/README.md).

---

## چیزهایی که نباید انجام شود

- تغییر هر فایلی در `packages/Webkul/*`
- دیپلوی با `--classmap-authoritative` — نگاشت `"Modules\\": "Modules/"` را از
  کار می‌اندازد و نصب از پنل با «Class not found» شکست می‌خورد
- فرض اینکه ماژول تازه‌نصب در همان ریکوئست بوت شده است
- `exec`/`shell_exec` در مسیر نصب — مسیر پایه باید بدون شل کار کند

---

## تست

```bash
./vendor/bin/pest --testsuite="Marketplace Unit Test"
```

---

## لایسنس

بجیستو تحت [MIT](LICENSE) است و ماژول‌های این ریپو هم همان لایسنس را دارند.
افزونه‌های تجاری مخزن لایسنس جدا دارند.
