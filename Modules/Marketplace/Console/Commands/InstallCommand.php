<?php

namespace Modules\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marketplace\Model\ModuleInstallation;
use Modules\Marketplace\Services\Installer;

/**
 * معادل خط فرمانِ همان چیزی که پنل انجام می‌دهد — منطق جدا ندارد و فقط
 * `Installer` را مرحله‌به‌مرحله جلو می‌برد.
 */
class InstallCommand extends Command
{
    protected $signature = 'module:install
        {archive? : مسیر فایل zip ماژول (وقتی از مخزن نصب نمی‌کنید)}
        {--slug= : نصب از مخزن به‌جای فایل محلی (نامک ماژول در مخزن)}
        {--allow-downgrade : اجازهٔ نصب نسخهٔ قدیمی‌تر روی نسخهٔ نصب‌شده}';

    protected $description = 'نصب یا به‌روزرسانی یک ماژول از فایل zip';

    public function handle(Installer $installer): int
    {
        if ($slug = $this->option('slug')) {
            $run = $installer->start('repository', [
                'slug'            => $slug,
                'allow_downgrade' => (bool) $this->option('allow-downgrade'),
            ]);
        } else {
            $archive = realpath($this->argument('archive'));

            if (! $archive) {
                $this->error('فایل بسته پیدا نشد: '.$this->argument('archive'));

                return self::FAILURE;
            }

            $run = $installer->start('manual', [
                'archive'         => $archive,
                'allow_downgrade' => (bool) $this->option('allow-downgrade'),
            ]);
        }

        $run = $installer->run($run, function (string $step, ModuleInstallation $state) {
            $label = Installer::STEPS[$step] ?? $step;

            $state->status === 'failed'
                ? $this->line("  <fg=red>✗</> $label")
                : $this->line("  <info>✓</info> $label");
        });

        if ($run->status === 'failed') {
            $this->newLine();
            $this->error($run->error);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("ماژول {$run->module} نسخهٔ {$run->version} نصب شد.");
        $this->line('  پرووایدرش از ریکوئست/دستور بعدی بوت می‌شود.');

        return self::SUCCESS;
    }
}
