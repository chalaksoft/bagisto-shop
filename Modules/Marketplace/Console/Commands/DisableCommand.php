<?php

namespace Modules\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marketplace\Services\Installer;
use Modules\Marketplace\Services\PackageException;

class DisableCommand extends Command
{
    protected $signature = 'module:disable
        {name : نام پوشهٔ ماژول}
        {--force : غیرفعال کن حتی اگر ماژول دیگری به آن وابسته است}';

    protected $description = 'غیرفعال‌کردن یک ماژول (برگشت‌پذیر؛ فایل‌ها دست نمی‌خورند)';

    public function handle(Installer $installer): int
    {
        try {
            $dependents = $installer->disable($name = $this->argument('name'), (bool) $this->option('force'));
        } catch (PackageException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("ماژول $name غیرفعال شد.");

        if ($dependents) {
            $this->warn('این ماژول‌ها هم دیگر بوت نمی‌شوند: '.implode('، ', $dependents));
        }

        return self::SUCCESS;
    }
}
