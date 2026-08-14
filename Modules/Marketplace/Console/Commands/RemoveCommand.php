<?php

namespace Modules\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marketplace\Services\Installer;
use Modules\Marketplace\Services\PackageException;

class RemoveCommand extends Command
{
    protected $signature = 'module:remove
        {name : نام پوشهٔ ماژول}
        {--rollback-migrations : اجرای migrate:rollback ماژول — دادهٔ جدول‌هایش پاک می‌شود}';

    protected $description = 'حذف کامل یک ماژول: پوشه، ردیف وضعیت و در صورت درخواست مهاجرت‌ها';

    public function handle(Installer $installer): int
    {
        $name     = $this->argument('name');
        $rollback = (bool) $this->option('rollback-migrations');

        $this->warn("پوشهٔ Modules/$name حذف می‌شود.");

        if ($rollback) {
            $this->warn('مهاجرت‌های این ماژول رول‌بک می‌شوند و دادهٔ جدول‌هایش از بین می‌رود.');
        }

        if (! $this->confirm('ادامه می‌دهید؟', false)) {
            return self::SUCCESS;
        }

        try {
            $log = $installer->remove($name, $rollback);
        } catch (PackageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("ماژول $name حذف شد.");

        if ($backup = $log->payload('backup')) {
            $this->line('  بکاپ: '.$backup);
        }

        return self::SUCCESS;
    }
}
