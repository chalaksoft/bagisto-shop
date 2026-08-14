<?php

namespace Modules\Marketplace\Console\Commands;

use Illuminate\Console\Command;
use Modules\Marketplace\Model\InstalledModule;
use Modules\Marketplace\Model\ModuleInstallation;
use Modules\Marketplace\Services\Installer;
use Modules\Marketplace\Services\RepositoryClient;

/**
 * به‌روزرسانی ماژول‌های نصب‌شده از مخزن.
 *
 * بدون آرگومان همهٔ ماژول‌هایی که نسخهٔ جدیدتر دارند را به‌روز می‌کند — همان
 * چیزی که روی VPS می‌شود در cron گذاشت. با نام ماژول، فقط همان یکی.
 */
class UpdateCommand extends Command
{
    protected $signature = 'module:update
        {name?* : نام پوشهٔ ماژول‌ها؛ خالی یعنی همه}
        {--dry-run : فقط نشان بده چه چیزی به‌روز می‌شود}';

    protected $description = 'به‌روزرسانی ماژول‌های نصب‌شده از مخزن';

    public function handle(RepositoryClient $repository, Installer $installer): int
    {
        if (! $repository->isConfigured()) {
            $this->error('آدرس مخزن تنظیم نشده است (MARKETPLACE_URL).');

            return self::FAILURE;
        }

        $installed = InstalledModule::query()
            ->when($this->argument('name'), fn ($query, $names) => $query->whereIn('name', $names))
            ->pluck('version', 'name');

        $updates = $repository->updatesFor($installed);

        if (! $updates) {
            $this->info('همهٔ ماژول‌ها به‌روزند.');

            return self::SUCCESS;
        }

        foreach ($updates as $name => $update) {
            if (! $update['available']) {
                $this->warn("$name: نسخهٔ {$update['version']} هست ولی در لایسنس شما نیست.");

                continue;
            }

            $this->line("<info>$name</info>: {$installed[$name]} ← {$update['version']}");

            if ($this->option('dry-run')) {
                continue;
            }

            $run = $installer->start('repository', [
                'slug'    => $update['slug'],
                'version' => $update['version'],
            ]);

            $run = $installer->run($run, function (string $step, ModuleInstallation $state) {
                $label = Installer::STEPS[$step] ?? $step;

                $state->status === 'failed'
                    ? $this->line("    <fg=red>✗</> $label")
                    : $this->line("    <info>✓</info> $label");
            });

            if ($run->status === 'failed') {
                $this->error('  '.$run->error);
            }
        }

        $repository->flush();

        return self::SUCCESS;
    }
}
