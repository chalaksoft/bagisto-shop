<?php

namespace Modules\MarketplaceServer\Console\Commands;

use Illuminate\Console\Command;
use Modules\MarketplaceServer\Model\RepositoryVersion;
use Modules\MarketplaceServer\Services\PackageSigner;

/**
 * بررسی اینکه فایل هر نسخه هنوز با چکسام و امضای ذخیره‌شده‌اش می‌خواند.
 *
 * چرا لازم است: اگر فایلی روی مخزن عوض شود — با ریستور اشتباه، هارد خراب، یا
 * نفوذ — فروشگاه مشتری با «امضای بسته معتبر نیست» روبه‌رو می‌شود و ما تازه
 * آن‌وقت می‌فهمیم. این دستور را در cron بگذارید.
 */
class VerifyCommand extends Command
{
    protected $signature = 'marketplace:verify {--slug= : فقط یک ماژول}';

    protected $description = 'بررسی سلامت چکسام و امضای بسته‌های منتشرشده';

    public function handle(PackageSigner $signer): int
    {
        $versions = RepositoryVersion::query()
            ->with('module')
            ->when($this->option('slug'), fn ($query, $slug) => $query->whereHas(
                'module',
                fn ($module) => $module->where('slug', $slug)
            ))
            ->get();

        $broken = 0;

        foreach ($versions as $version) {
            $label = $version->module->slug.' '.$version->version;

            if (! is_file($path = $version->absolutePath())) {
                $this->line("  <fg=red>✗</> $label — فایل بسته نیست");
                $broken++;

                continue;
            }

            $problems = [];

            if (! hash_equals($version->checksum, hash_file('sha256', $path))) {
                $problems[] = 'چکسام نمی‌خواند';
            }

            if (! $signer->verify($path, $version->signature)) {
                $problems[] = 'امضا نامعتبر است';
            }

            if ($problems) {
                $this->line("  <fg=red>✗</> $label — ".implode('، ', $problems));
                $broken++;
            } else {
                $this->line("  <info>✓</info> $label");
            }
        }

        $this->newLine();

        if ($broken) {
            $this->error("$broken نسخه مشکل دارد. تا رفع نشوند، فروشگاه‌های مشتری نصبشان را رد می‌کنند.");
            $this->line('  چاره: نسخه را حذف و دوباره از روی بستهٔ سالم منتشر کنید.');

            return self::FAILURE;
        }

        $this->info('همهٔ '.$versions->count().' نسخه سالم‌اند.');

        return self::SUCCESS;
    }
}
