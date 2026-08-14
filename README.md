# bagisto-shop

فروشگاه بجیستوی فارسی که **خودش مخزن ماژول است**: ماژول‌ها را اینجا منتشر
می‌کنید و فروشگاه‌های دیگر از داخل پنل مدیریتشان نصب و به‌روزرسانی می‌کنند —
بدون SSH، بدون composer، بدون باز کردن دستی zip.

- لاراول ۱۱ + بجیستو ۲.۳.۷ (هستهٔ `packages/Webkul` دست‌نخورده)
- فارسی و راست‌به‌چپ به‌صورت پیش‌فرض
- تومان به‌عنوان ارز پایه
- دو ماژول: `Marketplace` (نصب‌کننده) و `MarketplaceServer` (انتشاردهنده)

---

## چرا

بجیستو راه رسمی نصب افزونه ندارد. افزونه‌ها دستی نصب می‌شوند: باز کردن zip در
`packages/`، ویرایش `psr-4` در `composer.json`، بعد `migrate` و `vendor:publish`
از خط فرمان. یعنی هر بار SSH و composer — چیزی که روی هاست اشتراکی اصلاً نیست و
مشتری هم بلد نیست.

این پروژه همان کاری را می‌کند که وردپرس: یک صفحه در پنل، فهرست ماژول‌ها، دکمهٔ
نصب.

---

## دو نقش را با هم اشتباه نگیرید

| ماژول | نقش | روی چه نصبی |
| --- | --- | --- |
| `Marketplace` | ماژول **نصب می‌کند** | هر فروشگاهی |
| `MarketplaceServer` | ماژول **منتشر می‌کند** | فقط نصبی که مخزن است |

یک فروشگاه معمولی فقط اولی را لازم دارد. دومی جایی می‌نشیند که کلید خصوصی امضا
نگهداری می‌شود.

روی این ریپو هر دو هستند، چون همین فروشگاه هم مخزن است و هم می‌تواند ماژول نصب
کند.

---

## راه‌اندازی

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan bagisto:install
php artisan migrate
```

### اگر این نصب مخزن است

```bash
php artisan marketplace:keys
```

کلید خصوصی در `storage/keys/repository.key` می‌ماند (در `.gitignore` است) و کلید
عمومی باید در ریپوی هر فروشگاه خریدار اینجا کپی شود:

```
Modules/Marketplace/resources/keys/repository.pub
```

### اگر این نصب فقط ماژول نصب می‌کند

در `.env`:

```
MARKETPLACE_URL=https://shop.example.com
MARKETPLACE_TOKEN=mkt_…
MARKETPLACE_REQUIRE_SIGNATURE=true
```

و کلید عمومی مخزن را در `Modules/Marketplace/resources/keys/repository.pub`
بگذارید. ماژول `MarketplaceServer` را می‌توانید از پنل غیرفعال کنید.

---

## چطور کار می‌کند

### ثبت ماژول‌ها — دستی نیست

`bootstrap/providers.php` لیست ثابت ماژول‌ها را ندارد. `App\Classes\ModuleRegistry`
هر `Modules/*/module.json` را می‌خواند، غیرفعال‌ها و آن‌هایی که پیش‌نیازشان نیست
را کنار می‌گذارد و بقیه را بر اساس `priority` مرتب می‌کند. همین چیزی است که نصب
ماژول در زمان اجرا را ممکن می‌کند.

وضعیت فعال/غیرفعال در جدول `installed_modules` است، نه در کد.

⚠️ هر نوشتنِ مستقیم روی `installed_modules` باید `ModuleRegistry::flush()` صدا
بزند، وگرنه کش رجیستری و `bootstrap/cache/services.php` تغییر را نمی‌بینند.

### مسیر نصب، بدون شل

`Artisan::call()` داخل همان ریکوئست HTTP اجرا می‌شود. کلید پشتیبانی از هاست
اشتراکی همین است. هر مرحلهٔ نصب — دانلود، بررسی امضا، سازگاری، استخراج، ثبت،
مهاجرت، انتشار دارایی، پاک‌سازی کش — متد جداست و وضعیتش در `module_installations`
می‌نشیند، پس تایم‌اوت ۳۰ ثانیه‌ای هاست اشتراکی نصب را نصفه رها نمی‌کند.

⚠️ پرووایدر ماژول تازه در همان ریکوئستِ نصب بوت **نمی‌شود** — مثل وردپرس، بعد از
نصب ریدایرکت می‌شود تا ریکوئست بعدی آن را بالا بیاورد.

### امنیت

این قابلیت ذاتاً یعنی «کد PHP را از اینترنت بگیر و اجرا کن»، پس:

- **امضای دیجیتال اجباری** — هر بسته با کلید خصوصی مخزن امضا می‌شود. بدون امضای
  معتبر، نصب متوقف می‌شود؛ نه با هشدار، بلکه با توقف کامل.
- **مبدأ قفل** — فقط `MARKETPLACE_URL`. آدرس دلخواه از پنل گرفته نمی‌شود.
- **لایسنس مقید به دامنه** — تا یک خرید روی ده سایت نصب نشود.
- **path traversal و zip bomb** — تک‌تک ورودی‌های zip پیش از استخراج بررسی می‌شوند.
- **ACL جدا** برای دیدن، نصب، فعال/غیرفعال و حذف؛ نصب و حذف علاوه بر آن نقش
  سوپرادمین می‌خواهند.
- **لاگ** — هر نصب، به‌روزرسانی و حذف با کاربر، نسخه، چکسام و زمان ثبت می‌شود.
  سمت مخزن، دانلودهای ردشده هم لاگ می‌شوند.

---

## خط فرمان

```bash
# نصب‌کننده
php artisan module:list
php artisan module:install /path/to/package.zip
php artisan module:install --slug=invoice-pro
php artisan module:repository
php artisan module:update
php artisan module:enable InvoicePro
php artisan module:disable InvoicePro --force
php artisan module:remove InvoicePro --rollback-migrations

# انتشاردهنده
php artisan marketplace:keys
php artisan marketplace:publish invoice-pro /path/to/package.zip --changelog="…"
php artisan marketplace:verify
```

`marketplace:verify` را در cron بگذارید: اگر فایلی روی مخزن عوض شود، بهتر است
خودتان بفهمید تا اینکه فروشگاه مشتری با «امضای بسته معتبر نیست» روبه‌رو شود.

---

## API مخزن

```
GET  /api/marketplace/modules?bagisto=2.3.7&php=8.3
GET  /api/marketplace/modules/{slug}
GET  /api/marketplace/modules/{slug}/download
POST /api/marketplace/license/check
```

احراز هویت با `Authorization: Bearer {token}` و دامنهٔ فروشگاه در
`X-Shop-Domain`. پاسخ دانلود، چکسام و امضا را در هدرهای `X-Package-Checksum` و
`X-Package-Signature` برمی‌گرداند.

---

## قرارداد بستهٔ ماژول

zip دقیقاً یک پوشه در ریشه دارد، هم‌نام ماژول. جزئیات و نمونهٔ `module.json` در
[`Modules/Marketplace/README.md`](Modules/Marketplace/README.md).

---

## چیزهایی که نباید انجام شود

- تغییر هر فایلی در `packages/Webkul/*`
- دیپلوی با `--classmap-authoritative` — نگاشت `"Modules\\": "Modules/"` را از کار
  می‌اندازد و نصب از پنل با «Class not found» شکست می‌خورد
- فرض اینکه ماژول تازه‌نصب در همان ریکوئست بوت شده است
- `exec`/`shell_exec` در مسیر نصب — مسیر پایه باید بدون شل کار کند
- گذاشتن کلید خصوصی امضا در ریپو

---

## تست

```bash
./vendor/bin/pest --testsuite="Marketplace Unit Test"
```

---

## لایسنس

بجیستو تحت [MIT](LICENSE) است. ماژول‌های این ریپو هم همان لایسنس را دارند.
