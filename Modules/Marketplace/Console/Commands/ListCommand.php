<?php

namespace Modules\Marketplace\Console\Commands;

use App\Classes\ModuleRegistry;
use Illuminate\Console\Command;
use Modules\Marketplace\Model\InstalledModule;

class ListCommand extends Command
{
    protected $signature = 'module:list {--refresh : باطل‌کردن کش رجیستری پیش از نمایش}';

    protected $description = 'فهرست ماژول‌های Modules/ با وضعیت، نسخه و ترتیب بوت';

    public function handle(): int
    {
        if ($this->option('refresh')) {
            ModuleRegistry::flush();
        }

        $state = InstalledModule::query()->get()->keyBy('name');

        $rows = [];

        foreach (ModuleRegistry::all() as $module) {
            $record = $state->get($module['name']);

            $rows[] = [
                $module['priority'],
                $module['name'],
                $record->version ?? $module['version'],
                $this->status($module),
                $record->source ?? '—',
                $module['requires'] ? implode(', ', $module['requires']) : '—',
            ];
        }

        $this->table(['اولویت', 'نام', 'نسخه', 'وضعیت', 'منبع', 'پیش‌نیاز'], $rows);

        $this->line('  بوت‌شونده: '.count(ModuleRegistry::providers()).' پرووایدر');

        return self::SUCCESS;
    }

    /**
     * «فعال ولی بوت‌نشونده» حالت مهمی است: ماژول روشن است اما پیش‌نیازش نیست،
     * و بدون نمایشش ادمین فقط می‌بیند که ماژول «کار نمی‌کند».
     */
    protected function status(array $module): string
    {
        if (! $module['enabled']) {
            return 'غیرفعال';
        }

        if (! $module['bootable']) {
            return $module['missing']
                ? 'پیش‌نیاز غایب: '.implode('، ', $module['missing'])
                : 'پرووایدر پیدا نشد';
        }

        return 'فعال';
    }
}
