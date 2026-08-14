<?php

namespace App\Classes;

use Illuminate\Support\Facades\DB;
use Webkul\Core\Enums\CurrencyPositionEnum;

/**
 * پیش‌فرض‌های فروشگاه فارسی: تومان به‌عنوان ارز پایه.
 *
 * ┄┄ چرا کلاس جدا و نه فقط یک مهاجرت ┄┄
 *
 * `php artisan bagisto:install` اول مهاجرت‌ها را اجرا می‌کند و **بعد** سیدر
 * خودش را. یعنی هر مهاجرتی که ارز را عوض کند، چند ثانیه بعد با دلارِ سیدر
 * بازنویسی می‌شود. برای همین منطق اینجاست و دو جا صدا زده می‌شود:
 *
 *   - `php artisan shop:defaults` — قدم بعد از `bagisto:install` روی نصب تازه
 *   - مهاجرت `set_toman_as_default_currency` — برای نصب‌هایی که از قبل
 *     راه‌اندازی شده‌اند و این قابلیت بعداً به آن‌ها اضافه می‌شود
 *
 * هر بار اجرا کردنش بی‌خطر است.
 */
class ShopDefaults
{
    /**
     * @return array{currency: string, channels: int}
     */
    public static function apply(): array
    {
        $currencyId = static::ensureToman();

        $channels = DB::table('channels')->get();

        foreach ($channels as $channel) {
            DB::table('channels')->where('id', $channel->id)->update([
                'base_currency_id' => $currencyId,
            ]);

            DB::table('channel_currencies')->where('channel_id', $channel->id)->delete();

            DB::table('channel_currencies')->insert([
                'channel_id'  => $channel->id,
                'currency_id' => $currencyId,
            ]);
        }

        /**
         * پیکربندی هسته هم به تومان می‌رود، وگرنه بخش‌هایی که مستقیم از
         * `core_config` می‌خوانند همچنان دلار می‌بینند.
         */
        DB::table('core_config')->updateOrInsert(
            ['code' => 'general.content.shop.currency', 'channel_code' => null, 'locale_code' => null],
            ['value' => 'IRT', 'updated_at' => now(), 'created_at' => now()]
        );

        return ['currency' => 'IRT', 'channels' => $channels->count()];
    }

    /**
     * ردیف تومان، با قالب‌بندی درست فارسی.
     *
     *   - کد `IRT` است نه `IRR`: قیمت‌ها در فروشگاه‌های ایرانی به تومان وارد
     *     می‌شوند، نه ریال.
     *   - `decimal = 0` — تومان اعشار ندارد و «۱۲۳٬۴۵۰٫۰۰ تومان» غلط است.
     *   - جداکنندهٔ هزارگان `٬` (U+066C)، همان چیزی که در متن فارسی درست است.
     *   - نماد سمت راستِ عدد می‌نشیند: «۱۲۳٬۴۵۰ تومان».
     *
     * `updateOrInsert` و نه فقط `insert`: اگر ردیف از قبل باشد — دستی ساخته
     * شده یا از اجرای قبلی مانده — قالب‌بندی‌اش هم اصلاح می‌شود.
     */
    protected static function ensureToman(): int
    {
        DB::table('currencies')->updateOrInsert(['code' => 'IRT'], [
            'name'              => 'تومان',
            'symbol'            => 'تومان',
            'decimal'           => 0,
            'group_separator'   => '٬',
            'decimal_separator' => '٫',
            'currency_position' => CurrencyPositionEnum::RIGHT_WITH_SPACE->value,
            'updated_at'        => now(),
        ]);

        return (int) DB::table('currencies')->where('code', 'IRT')->value('id');
    }
}
