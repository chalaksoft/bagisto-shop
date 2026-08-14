<?php

namespace App\Providers;

use App\Http\Controllers\HomeController;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
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

        $this->registerHostViews();

        $this->registerPageBuilderStyles();

        $this->registerStorefrontStyles();

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
     * ویوهای خود اپ را زیر فضای‌نام `host::` در دسترس نگه دار.
     *
     * دو دلیل دارد. یک: میان‌افزار `shop` بجیستو مسیرهای پایهٔ view finder را
     * با مسیرهای تم عوض می‌کند، پس `view('home.page-builder')` داخل روت‌های
     * فروشگاه پیدا نمی‌شود و فضای‌نام‌ها از این جابه‌جایی مصون‌اند.
     *
     * دو: پوشه عمداً `host-views` است نه `views` — بجیستو در
     * `resources/views/.gitignore` همه‌چیز را کنار می‌گذارد و هر ویویی که
     * آنجا بگذاریم اصلاً کامیت نمی‌شود.
     */
    protected function registerHostViews(): void
    {
        View::addNamespace('host', resource_path('host-views'));
    }

    /**
     * فونت و اصلاحات سراسری فروشگاه، روی **همهٔ** صفحه‌ها.
     *
     * `bagisto.shop.layout.head.after` قلاب رسمی خود بجیستو در انتهای `<head>`
     * است، پس استایل ما بعد از استایل تم می‌نشیند و بدون `!important` جنگیدن
     * با آن لازم نیست — هرچند فونت را تم با `!important` می‌گذارد و ما هم
     * همان‌جا با همان وزن جوابش را می‌دهیم.
     *
     * این‌طور `packages/Webkul` و فایل‌های تم دست‌نخورده می‌مانند.
     */
    protected function registerStorefrontStyles(): void
    {
        Event::listen('bagisto.shop.layout.head.after', function ($event) {
            $event->addTemplate('host::partials.storefront-styles');
        });
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
     * نام `shop.home.index` **باید** تکرار شود: ثبت دوم، ثبت اول را از جدول
     * روت‌ها بیرون می‌کند و اگر نام را نگذاریم آن نام دیگر وجود ندارد —
     * هر `route('shop.home.index')` در قالب‌ها و ریدایرکت‌ها با
     * «Route [shop.home.index] not defined» می‌شکند. (دقیقاً همین اتفاق افتاد.)
     */
    protected function overrideHomeRoute(): void
    {
        $this->app->booted(function () {
            Route::middleware(['web', 'shop', PreventRequestsDuringMaintenance::class])
                ->get('/', [HomeController::class, 'index'])
                ->name('shop.home.index');

            /**
             * صفحه‌های دیگری که با صفحه‌ساز چیده می‌شوند. سند با همین `slug`
             * پیدا می‌شود، پس اضافه‌کردن صفحهٔ تازه یعنی یک سطر اینجا و یک سند
             * در پنل — بدون کد.
             */
            foreach (['modules' => 'ماژول‌ها'] as $slug => $label) {
                Route::middleware(['web', 'shop', PreventRequestsDuringMaintenance::class])
                    ->get($slug, fn () => app(HomeController::class)->document($slug))
                    ->name('shop.page.'.$slug);
            }
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
