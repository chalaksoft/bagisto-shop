<?php

namespace Modules\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marketplace\Model\InstalledModule;
use Modules\Marketplace\Services\RepositoryClient;

/**
 * فهرست ماژول‌های مخزن از خط فرمان — همان چیزی که صفحهٔ «مخزن» نشان می‌دهد.
 *
 * برای دیباگ ارتباط با مخزن هم هست: اگر اینجا خطا بدهد، مشکل از تنظیمات یا
 * شبکه است نه از پنل.
 */
class RepositoryCommand extends Command
{
    protected $signature = 'module:repository {--refresh : نادیده‌گرفتن کش فهرست}';

    protected $description = 'فهرست ماژول‌های مخزن و وضعیت به‌روزرسانی‌ها';

    public function handle(RepositoryClient $repository): int
    {
        if (! $repository->isConfigured()) {
            $this->error('آدرس مخزن تنظیم نشده است (MARKETPLACE_URL).');

            return self::FAILURE;
        }

        $catalogue = $repository->modules((bool) $this->option('refresh'));

        if ($catalogue['error']) {
            $this->error($catalogue['error']);

            return self::FAILURE;
        }

        $license = $catalogue['license'] ?? [];

        $this->line('  دامنه: '.$repository->domain());
        $this->line(($license['valid'] ?? false)
            ? '  <info>لایسنس معتبر</info> — '.($license['customer'] ?? '')
            : '  <fg=red>لایسنس معتبر نیست</> — '.($license['reason'] ?? ''));
        $this->newLine();

        $installed = InstalledModule::query()->pluck('version', 'name');

        $rows = [];

        foreach ($catalogue['modules'] as $module) {
            $current = $installed[$module['package_name']] ?? null;
            $latest  = $module['latest_version']['version'] ?? null;

            $rows[] = [
                $module['slug'],
                $module['package_name'],
                $latest ?: '— ناسازگار',
                $current ?: '—',
                $this->status($module, $current, $latest),
            ];
        }

        $this->table(['نامک', 'پوشه', 'آخرین نسخه', 'نصب‌شده', 'وضعیت'], $rows);

        return self::SUCCESS;
    }

    protected function status(array $module, ?string $current, ?string $latest): string
    {
        if (! ($module['available'] ?? false) && ! ($module['free'] ?? false)) {
            return 'خارج از لایسنس';
        }

        if (! $current) {
            return $latest ? 'قابل نصب' : 'نسخهٔ سازگار ندارد';
        }

        if ($latest && version_compare($latest, $current, '>')) {
            return 'به‌روزرسانی موجود';
        }

        return 'به‌روز';
    }
}
