<?php

namespace Modules\MarketplaceServer\Console\Commands;

use Illuminate\Console\Command;
use Modules\MarketplaceServer\Model\RepositoryModule;
use Modules\MarketplaceServer\Services\VersionPublisher;
use Throwable;

/**
 * انتشار یک نسخه از خط فرمان — همان مسیری که پنل می‌رود، بدون منطق دوم.
 */
class PublishCommand extends Command
{
    protected $signature = 'marketplace:publish
        {slug : نامک ماژول در مخزن}
        {archive : مسیر فایل zip}
        {--changelog= : توضیح تغییرات این نسخه}
        {--draft : پیش‌نویس بماند و در API دیده نشود}';

    protected $description = 'انتشار یک نسخهٔ تازه از یک ماژول در مخزن';

    public function handle(VersionPublisher $publisher): int
    {
        $module = RepositoryModule::query()->where('slug', $this->argument('slug'))->first();

        if (! $module) {
            $this->error('ماژولی با نامک «'.$this->argument('slug').'» در مخزن نیست.');

            return self::FAILURE;
        }

        $archive = realpath($this->argument('archive'));

        if (! $archive) {
            $this->error('فایل بسته پیدا نشد: '.$this->argument('archive'));

            return self::FAILURE;
        }

        try {
            $version = $publisher->publish($module, $archive, [
                'changelog' => $this->option('changelog'),
                'release'   => ! $this->option('draft'),
            ]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("نسخهٔ {$version->version} از {$module->name} آپلود و امضا شد.");

        if (! $version->isReleased()) {
            $this->warn('  پیش‌نویس است و هنوز در API دیده نمی‌شود.');
        }

        return self::SUCCESS;
    }
}
