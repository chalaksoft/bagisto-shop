<?php

namespace Modules\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marketplace\Services\Installer;
use Modules\Marketplace\Services\PackageException;

class EnableCommand extends Command
{
    protected $signature = 'module:enable {name : نام پوشهٔ ماژول}';

    protected $description = 'فعال‌کردن یک ماژول نصب‌شده';

    public function handle(Installer $installer): int
    {
        try {
            $installer->enable($name = $this->argument('name'));
        } catch (PackageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("ماژول $name فعال شد. از ریکوئست بعدی بوت می‌شود.");

        return self::SUCCESS;
    }
}
