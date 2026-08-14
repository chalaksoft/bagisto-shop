<?php

namespace Modules\Marketplace;

use App\Classes\BaseServiceProvider;
use Modules\Marketplace\Console\Commands\DisableCommand;
use Modules\Marketplace\Console\Commands\EnableCommand;
use Modules\Marketplace\Console\Commands\InstallCommand;
use Modules\Marketplace\Console\Commands\ListCommand;
use Modules\Marketplace\Console\Commands\RemoveCommand;
use Modules\Marketplace\Console\Commands\RepositoryCommand;
use Modules\Marketplace\Console\Commands\UpdateCommand;

/**
 * نصب ماژول از داخل پنل — جایگزین فرایند دستی «باز کردن zip در Modules/،
 * ویرایش composer.json، بعد migrate و vendor:publish از خط فرمان».
 *
 * مسیر پایه عمداً بدون شل نوشته شده (`Artisan::call` داخل همان ریکوئست HTTP)
 * تا روی هاست اشتراکیِ بدون SSH و بدون composer هم کار کند؛ روی VPS همان
 * مراحل فقط سریع‌تر اجرا می‌شوند.
 *
 * فهرست ماژول‌های بوت‌شونده را این ماژول نمی‌سازد؛ کار `App\Classes\ModuleRegistry`
 * است که در `bootstrap/providers.php` صدا زده می‌شود. اینجا فقط وضعیت را
 * می‌نویسد و کش رجیستری را باطل می‌کند.
 */
class ModuleServiceProvider extends BaseServiceProvider
{
    /** آخرین ماژول؛ چیزی به آن وابسته نیست */
    public static $order = 60;

    public function register()
    {
        $this->mergeConfigFrom($this->dirModule.'/Config/marketplace.php', 'marketplace');

        parent::register();
    }

    public function boot()
    {
        parent::boot();

        $this->commands([
            ListCommand::class,
            InstallCommand::class,
            EnableCommand::class,
            DisableCommand::class,
            RemoveCommand::class,
            RepositoryCommand::class,
            UpdateCommand::class,
        ]);
    }
}
