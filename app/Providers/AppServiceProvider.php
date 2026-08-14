<?php

namespace App\Providers;

use App\Http\Controllers\HomeController;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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

        $this->overrideHomeRoute();

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
     * روت `/` را به کنترلر خودمان بسپار تا صفحهٔ اصلیِ صفحه‌ساز رندر شود.
     *
     * بجیستو `GET /` را در `ShopServiceProvider` ثبت می‌کند. کلید جست‌وجوی
     * روت‌ها در لاراول «متد + آدرس» است و ثبت دوم روی اولی می‌نشیند، پس این
     * کار باید **بعد از** بوت شدن همهٔ پرووایدرها انجام شود.
     *
     * میان‌افزارها عیناً همان‌هایی هستند که بجیستو استفاده می‌کند (`web`،
     * `shop` و maintenance)، وگرنه کانال، زبان، ارز و قالب فعال ست نمی‌شوند و
     * `<x-shop::layouts>` رندر نمی‌شود. `cache.response` عمداً نیست: صفحهٔ
     * اصلی از صفحه‌ساز می‌آید و باید بعد از هر ویرایش تازه باشد.
     *
     * نامی هم نمی‌گذاریم؛ `route('shop.home.index')` همچنان به همان `/` اشاره
     * می‌کند و نام تکراری لازم نیست.
     */
    protected function overrideHomeRoute(): void
    {
        $this->app->booted(function () {
            Route::middleware(['web', 'shop', PreventRequestsDuringMaintenance::class])
                ->get('/', [HomeController::class, 'index']);
        });
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
