<?php

namespace App\Providers;

use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $allowedIPs = array_map('trim', explode(',', config('app.debug_allowed_ips')));

        $allowedIPs = array_filter($allowedIPs);

        if (empty($allowedIPs)) {
            return;
        }

        if (in_array(Request::ip(), $allowedIPs)) {
            Debugbar::enable();
        } else {
            Debugbar::disable();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->forceHttpsWhenConfigured();

        $this->registerPageBuilderStyles();

        /**
         * بعد از بوت شدن **همهٔ** پرووایدرها: `ThemeServiceProvider` بجیستو
         * فضای‌نام `shop` را در boot خودش می‌سازد و چون بعد از این پرووایدر
         * بوت می‌شود، هر چه اینجا اضافه کنیم را بازنویسی می‌کند.
         */
        $this->app->booted(fn () => $this->registerThemeViewOverrides());

        ParallelTesting::setUpTestDatabase(function (string $database, int $token) {
            Artisan::call('db:seed');
        });
    }

    /**
     * وقتی `APP_URL` با https شروع می‌شود، آدرس‌های تولیدشده هم https باشند.
     *
     * پشت nginx + php-fpm، اگر وب‌سرور `HTTPS=on` را به PHP پاس ندهد، لاراول
     * ریکوئست را http می‌بیند و همهٔ آدرس‌ها را با `http://` می‌سازد. صفحه روی
     * https سرو می‌شود ولی CSS و JS با http صدا زده می‌شوند و مرورگر به‌عنوان
     * mixed content بلاکشان می‌کند — نتیجه‌اش سایتی است بدون هیچ استایلی که
     * شبیه خرابی نصب به نظر می‌رسد و نیست.
     *
     * پیکربندی وب‌سرور راه دیگرش است، ولی روی هاست اشتراکی دست کاربر نیست؛
     * `APP_URL` هست.
     */
    protected function forceHttpsWhenConfigured(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * بازنویسی ویوهای تم فروشگاه از `resources/themes/{theme}/views`.
     *
     * `config/themes.php` این مسیر را به‌عنوان `views_path` تم اعلام می‌کند،
     * ولی بجیستو فقط وقتی به فضای‌نام `shop` اضافه‌اش می‌کند که تم در دیتابیس
     * ثبت و فعال شده باشد. روی نصب تازه این اتفاق نمی‌افتد و `shop::home.index`
     * مستقیم به ویوی پکیج می‌رسد — نتیجه‌اش این بود که صفحهٔ اصلیِ ساخته‌شده با
     * صفحه‌ساز نادیده گرفته می‌شد و صفحهٔ پیش‌فرض بجیستو رندر می‌شد.
     *
     * `prependNamespace` مسیر تم را جلوتر از پکیج می‌گذارد، پس هر ویوی موجود
     * در تم اولویت دارد و بقیه مثل قبل از پکیج می‌آیند. `packages/Webkul` هم
     * دست‌نخورده می‌ماند.
     */
    protected function registerThemeViewOverrides(): void
    {
        $path = base_path(config('themes.shop.default.views_path', 'resources/themes/default/views'));

        if (is_dir($path)) {
            View::prependNamespace('shop', $path);
        }
    }

    /**
     * پایهٔ ظاهری صفحه‌های ساخته‌شده با صفحه‌ساز.
     *
     * قالب رندر صفحه‌ساز یک لایهٔ مستقل است و فقط CSS خود Elementor را بار
     * می‌کند — نه فونت، نه ریست، نه تایپوگرافی. `elementor.extra_styles` قلاب
     * رسمی خود هسته برای همین کار است، پس هیچ فایلی از `Modules/Elementor`
     * دست نمی‌خورد.
     *
     * اگر ماژول صفحه‌ساز نصب نباشد، این کلید را کسی نمی‌خواند و گذاشتنش
     * بی‌ضرر است.
     */
    protected function registerPageBuilderStyles(): void
    {
        config([
            'elementor.extra_styles' => array_values(array_unique(array_merge(
                (array) config('elementor.extra_styles', []),
                ['/pb/page-builder.css'],
            ))),
        ]);
    }
}
