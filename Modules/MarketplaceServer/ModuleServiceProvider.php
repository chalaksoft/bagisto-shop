<?php

namespace Modules\MarketplaceServer;

use App\Classes\BaseServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Modules\MarketplaceServer\Console\Commands\GenerateKeysCommand;
use Modules\MarketplaceServer\Console\Commands\PublishCommand;
use Modules\MarketplaceServer\Console\Commands\VerifyCommand;
use Modules\MarketplaceServer\Http\Middleware\ResolveLicense;

/**
 * سمت **انتشاردهنده** — این فروشگاه ماژول می‌فروشد و فروشگاه‌های دیگر از آن
 * نصب می‌کنند.
 *
 * دو نقش را با هم اشتباه نگیرید:
 *
 *   `Marketplace`       — این فروشگاه ماژول **نصب می‌کند** (مصرف‌کننده)
 *   `MarketplaceServer` — این فروشگاه ماژول **منتشر می‌کند** (این ماژول)
 *
 * یک فروشگاه معمولی فقط اولی را لازم دارد. این یکی روی نصبی می‌نشیند که مخزن
 * است — همان‌جایی که کلید خصوصی امضا نگه داشته می‌شود.
 */
class ModuleServiceProvider extends BaseServiceProvider
{
    /** بعد از Marketplace (۶۰)؛ کلاس‌های Package و VersionConstraint را از آن می‌گیرد */
    public static $order = 61;

    public function register()
    {
        $this->mergeConfigFrom($this->dirModule.'/Config/marketplace-server.php', 'marketplace-server');

        parent::register();
    }

    public function boot()
    {
        parent::boot();

        $this->bootApiRoutes();

        $this->commands([
            GenerateKeysCommand::class,
            PublishCommand::class,
            VerifyCommand::class,
        ]);
    }

    /**
     * ثبت روت‌های API.
     *
     * `BaseServiceProvider` فقط `web.php` و `admin.php` را می‌شناسد و هر دو
     * داخل گروه `web` می‌روند — یعنی سشن، کوکی و CSRF. یک API بدون وضعیت که
     * فروشگاه‌های دیگر صدایش می‌زنند هیچ‌کدام را نمی‌خواهد، پس اینجا خودمان با
     * حداقل میان‌افزار ثبتش می‌کنیم.
     */
    protected function bootApiRoutes(): void
    {
        if (! File::exists($routes = $this->dirModule.'/Http/routes/api.php')) {
            return;
        }

        $this->app->make(Router::class)->aliasMiddleware('marketplace.license', ResolveLicense::class);

        Route::group([], $routes);
    }
}
