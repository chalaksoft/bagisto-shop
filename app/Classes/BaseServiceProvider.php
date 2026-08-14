<?php

namespace App\Classes;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Shared bootstrapping for everything under `Modules/`.
 *
 * A module is a self-contained folder that may provide any of:
 *
 *   Config/system.php          merged into Bagisto's `core` system settings
 *   Config/menu.php            merged into the admin sidebar
 *   Config/paymentmethods.php  merged into `paymentmethods`
 *   Database/migrations        loaded automatically
 *   Http/routes/web.php        loaded inside the `web` middleware group
 *   resources/views            registered under the module name, e.g. `Sms::…`
 *
 * Every module ships a `ModuleServiceProvider` extending this class, so the
 * individual modules only have to declare what is genuinely specific to them.
 */
abstract class BaseServiceProvider extends ServiceProvider
{
    /**
     * Absolute path to the module folder — the same value `modulePath()` returns,
     * exposed as a property because modules read it inside `register()`.
     */
    protected string $dirModule;

    public function __construct($app)
    {
        parent::__construct($app);

        $this->dirModule = $this->modulePath();
    }

    /**
     * Absolute path to the module folder (the directory holding ModuleServiceProvider).
     */
    protected function modulePath(): string
    {
        $reflection = new \ReflectionClass(static::class);

        return dirname($reflection->getFileName());
    }

    /**
     * Path to a module config file, or null when the module doesn't ship one.
     *
     * Both `Config/` and `config/` are accepted. macOS is case-insensitive, so a
     * module can look correct locally while its config silently goes unread on a
     * case-sensitive Linux server — which is exactly how an admin menu ends up
     * missing in production but present in development.
     */
    protected function configFile(string $file): ?string
    {
        foreach (['Config', 'config'] as $dir) {
            if (File::exists($path = $this->modulePath().'/'.$dir.'/'.$file)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Short module name, e.g. `Sms` for `Modules\Sms\ModuleServiceProvider`.
     */
    protected function moduleName(): string
    {
        return class_basename(Str::beforeLast(static::class, '\\ModuleServiceProvider'))
            ?: basename($this->modulePath());
    }

    public function boot()
    {
        $path = $this->modulePath();
        $name = $this->moduleName();

        if (File::isDirectory($path.'/Database/migrations')) {
            $this->loadMigrationsFrom($path.'/Database/migrations');
        }

        if (File::isDirectory($path.'/resources/views')) {
            $this->loadViewsFrom($path.'/resources/views', $name);
        }

        if (File::isDirectory($path.'/resources/lang')) {
            $this->loadTranslationsFrom($path.'/resources/lang', $name);
        }

        if (File::exists($routes = $path.'/Http/routes/web.php')) {
            Route::middleware('web')->group($routes);
        }

        /**
         * Admin routes declare their own `admin` middleware group and admin URL prefix,
         * exactly like Bagisto's own packages do, so they only need the `web` stack here.
         */
        if (File::exists($routes = $path.'/Http/routes/admin.php')) {
            Route::middleware('web')->group($routes);
        }
    }

    public function register()
    {
        if ($system = $this->configFile('system.php')) {
            $this->mergeConfigArray('core', require $system);
        }

        if ($menu = $this->configFile('menu.php')) {
            $this->mergeConfigArray('menu.admin', require $menu);
        }

        if ($acl = $this->configFile('acl.php')) {
            $this->mergeConfigArray('acl', require $acl);
        }

        if ($methods = $this->configFile('paymentmethods.php')) {
            $this->app['config']->set(
                'payment_methods',
                array_merge($this->app['config']->get('payment_methods', []), require $methods)
            );
        }

        if ($carriers = $this->configFile('carriers.php')) {
            $this->app['config']->set(
                'carriers',
                array_merge($this->app['config']->get('carriers', []), require $carriers)
            );
        }
    }

    /**
     * Append entries to a list-style config key without dropping what is already there.
     */
    protected function mergeConfigArray(string $key, array $values): void
    {
        $this->app['config']->set(
            $key,
            array_merge($this->app['config']->get($key, []), $values)
        );
    }
}
