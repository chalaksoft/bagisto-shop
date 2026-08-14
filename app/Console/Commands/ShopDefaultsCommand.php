<?php

namespace App\Console\Commands;

use App\Classes\ShopDefaults;
use Illuminate\Console\Command;

/**
 * قدم بعد از `php artisan bagisto:install` روی نصب تازه.
 *
 * سیدر بجیستو دلار را ارز پایه می‌گذارد و چون بعد از مهاجرت‌ها اجرا می‌شود،
 * هیچ مهاجرتی نمی‌تواند جلویش را بگیرد. این دستور بعدش می‌آید و تومان را
 * می‌نشاند.
 */
class ShopDefaultsCommand extends Command
{
    protected $signature = 'shop:defaults';

    protected $description = 'اعمال پیش‌فرض‌های فروشگاه فارسی: تومان به‌عنوان ارز پایه';

    public function handle(): int
    {
        $result = ShopDefaults::apply();

        $this->info('ارز پایهٔ '.$result['channels'].' کانال روی تومان تنظیم شد.');

        $this->call('optimize:clear');

        $this->line('  نمونهٔ قالب‌بندی: '.core()->formatPrice(123450));

        return self::SUCCESS;
    }
}
