# Marketplace — نصب ماژول از پنل

جایگزین فرایند دستی نصب افزونهٔ بجیستو (باز کردن zip در `Modules/`، ویرایش
`psr-4`، بعد `migrate` و `vendor:publish` از خط فرمان). همه‌چیز از پنل، بدون
SSH و بدون composer.

## وضعیت پیاده‌سازی

| فاز | چه چیزی | وضعیت |
| --- | --- | --- |
| ۱ | رجیستری پویا، جدول `installed_modules`، `module.json` برای همهٔ ماژول‌ها | ✅ |
| ۲ | سرویس `Installer`، دستورات `module:*`، صفحهٔ «ماژول‌ها» با نصب از zip | ✅ |
| ۳ | مخزن، API، امضای دیجیتال، صفحهٔ «مخزن»، به‌روزرسانی | ✅ |
| ۴ | لایسنس مقید به دامنه | ✅ |
| — | صف روی VPS، تست روی هاست اشتراکی واقعی | ⬜ |

مخزن اپ جدایی نیست: ماژول [`MarketplaceServer`](../MarketplaceServer/README.md)
داخل همین فروشگاه است. یعنی این فروشگاه هم ماژول می‌فروشد و هم می‌تواند ماژول
نصب کند، و هر دو طرف از یک `Package` و یک `VersionConstraint` استفاده می‌کنند —
پس محال است چیزی منتشر شود که سر نصب رد شود.

## اجزا

| فایل | کار |
| --- | --- |
| `app/Classes/ModuleRegistry.php` | (ریشهٔ پروژه) کشف ماژول‌ها و ساخت فهرست پرووایدرها برای `bootstrap/providers.php` |
| `Services/Package.php` | اعتبارسنجی zip: ساختار، path traversal، zip bomb، امضا، چکسام |
| `Services/Installer.php` | مراحل نصب، فعال/غیرفعال، حذف، بازگردانی |
| `Services/VersionConstraint.php` | تطبیق `>=2.0.0` و `^8.2` بدون نیاز به composer |
| `Http/Controller/Admin/ModuleController.php` | صفحهٔ «ماژول‌ها» و حلقهٔ مرحله‌به‌مرحلهٔ نصب |
| `Services/RepositoryClient.php` | فراخوانی API مخزن، کش فهرست، تشخیص به‌روزرسانی |
| `Http/Controller/Admin/RepositoryController.php` | صفحهٔ «مخزن» و شروع نصب از مخزن |
| `Console/Commands/*` | `module:list`، `install`، `enable`، `disable`، `remove`، `repository`، `update` |

## نکته‌هایی که موقع کار روی این ماژول باید بدانید

**پرووایدر ماژول تازه در همان ریکوئستِ نصب بوت نمی‌شود.** مثل وردپرس، بعد از
نصب ریدایرکت می‌شود تا ریکوئست بعدی آن را بالا بیاورد. هیچ کدی ننویسید که فرض
کند کلاس‌های ماژول تازه بلافاصله در دسترس‌اند — به همین دلیل انتشار دارایی‌ها
به `vendor:publish` تکیه نمی‌کند و از `resources/publishes` مستقیم کپی می‌کند.

**`route:cache` بعد از نصب ساخته نمی‌شود.** روت‌های ماژول تازه فقط وقتی ثبت
می‌شوند که پرووایدرش بوت شده باشد. `optimize:clear` کش را پاک می‌کند و اگر روی
سرور از کش روت استفاده می‌کنید، بعد از نصب یک‌بار `deploy/art route:cache` بزنید.

**هر نوشتنِ مستقیم روی `installed_modules` باید `ModuleRegistry::flush()` صدا
بزند.** کش رجیستری با تغییر پوشهٔ `Modules/` باطل می‌شود، نه با تغییر جدول. این
متد `bootstrap/cache/services.php` را هم پاک می‌کند؛ بدون آن، فعال‌کردن یک
ماژول ظاهراً انجام می‌شود ولی هیچ اثری ندارد.

**مسیر پایه بدون شل است.** `exec`/`shell_exec` در این ماژول ممنوع؛ همه‌چیز با
`Artisan::call()` داخل همان ریکوئست انجام می‌شود تا روی هاست اشتراکی کار کند.

## قرارداد بستهٔ ماژول

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

روی هاست اشتراکی composer نیست، پس هر ماژول `vendor/` خودش را داخل zip دارد و
در `ModuleServiceProvider::register()` فایل `vendor/autoload.php` خودش را
require می‌کند — همان کاری که افزونه‌های وردپرس می‌کنند.

`module.json`:

```json
{
    "name": "InvoicePro",
    "alias": "invoice-pro",
    "description": "…",
    "version": "1.0.0",
    "priority": 70,
    "providers": ["Modules\\InvoicePro\\ModuleServiceProvider"],
    "requires": ["Payment"],
    "requires_bagisto": ">=2.0.0",
    "requires_php": ">=8.2",
    "assets_tag": "invoice-pro"
}
```

`priority` ترتیب بوت است و باید یکتا باشد (ترتیب فعلی: Marketplace 60، MarketplaceServer 61).

## خط فرمان

```bash
php artisan module:list
php artisan module:install /path/to/package.zip
php artisan module:install --slug=invoice-pro          # از مخزن
php artisan module:repository                          # فهرست مخزن و وضعیت به‌روزرسانی‌ها
php artisan module:update                              # به‌روزرسانی همهٔ ماژول‌ها از مخزن
php artisan module:update DemoWidget --dry-run
php artisan module:enable InvoicePro
php artisan module:disable InvoicePro --force
php artisan module:remove InvoicePro --rollback-migrations
```

روی سروری که artisan را با root اجرا می‌کنید، دستورها را با کاربر وب بزنید
(`sudo -u www php artisan …`)، وگرنه فایل‌های تازه مال root می‌شوند و سایت ۵۰۰
می‌دهد.

## تنظیمات

`Config/marketplace.php` — همه از env:

| کلید | پیش‌فرض | توضیح |
| --- | --- | --- |
| `MARKETPLACE_URL` | — | تنها مبدأ مجاز دانلود؛ در پنل قابل ویرایش نیست |
| `MARKETPLACE_API_PREFIX` | `/api/marketplace` | پیشوند API مخزن |
| `MARKETPLACE_TOKEN` | — | توکن لایسنس |
| `MARKETPLACE_ALLOW_UPLOAD` | `true` | آپلود دستی zip از پنل |
| `MARKETPLACE_REQUIRE_SIGNATURE` | `false` | اجبار امضا برای بستهٔ آپلودی — **روی پروداکشن باید `true` شود** |
| `MARKETPLACE_MAX_UPLOAD` | `61440` | بیشینهٔ حجم بسته (کیلوبایت) |

بسته‌های آمده از مخزن همیشه امضا لازم دارند، مستقل از این کلید.

کلید عمومی مخزن باید در `resources/keys/repository.pub` باشد؛ روش ساختش در
[همان‌جا](resources/keys/README.md) آمده.

## به‌روزرسانی

صفحهٔ «ماژول‌های نصب‌شده» کنار نسخهٔ هر ماژول، اگر مخزن نسخهٔ جدیدتری داشته
باشد، «↑ x.y.z موجود است» نشان می‌دهد. فهرست مخزن ۵ دقیقه کش می‌شود و خطای
شبکه‌اش بلعیده می‌شود — این صفحه باید حتی وقتی مخزن پایین است کار کند، چون تنها
راه غیرفعال‌کردن یک ماژول خراب همین‌جاست.

روی VPS می‌شود `module:update` را در cron گذاشت.

## تست

```bash
./vendor/bin/pest --testsuite="Marketplace Unit Test"
```
