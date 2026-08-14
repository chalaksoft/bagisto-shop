<?php

use Illuminate\Database\Migrations\Migration;
use Webkul\Core\Enums\CurrencyPositionEnum;
use Illuminate\Support\Facades\DB;

/**
 * تومان به‌عنوان ارز پیش‌فرض فروشگاه.
 *
 * نصب‌کنندهٔ بجیستو دلار را می‌گذارد. این مهاجرت تومان را اضافه می‌کند، ارز
 * پایهٔ کانال را روی آن می‌برد و دلار را از ارزهای کانال برمی‌دارد.
 *
 * نکته‌ها:
 *
 *   - کد `IRT` است نه `IRR`: واحد رایج در فروشگاه‌های ایرانی تومان است و
 *     قیمت‌ها به تومان وارد می‌شوند، نه ریال.
 *   - `decimal = 0` — تومان اعشار ندارد و «۱۲۳٬۴۵۰٫۰۰ تومان» غلط است.
 *   - جداکنندهٔ هزارگان `٬` (U+066C) است، همان چیزی که در متن فارسی درست است.
 *   - نماد سمت راستِ عدد می‌نشیند («۱۲۳٬۴۵۰ تومان»)، پس `right_with_space`.
 *   - ردیف دلار حذف نمی‌شود؛ فقط از کانال برداشته می‌شود، چون ممکن است به
 *     داده‌های قدیمی (سفارش، قیمت) وصل باشد.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * `updateOrInsert` و نه فقط `insert`: اگر ردیف تومان از قبل باشد —
         * دستی ساخته شده یا از اجرای قبلی همین مهاجرت مانده — قالب‌بندی‌اش هم
         * اصلاح می‌شود، نه اینکه با مقدار غلط بماند.
         */
        DB::table('currencies')->updateOrInsert(['code' => 'IRT'], [
            'name'              => 'تومان',
            'symbol'            => 'تومان',
            'decimal'           => 0,
            'group_separator'   => '٬',
            'decimal_separator' => '٫',
            'currency_position' => CurrencyPositionEnum::RIGHT_WITH_SPACE->value,
            'updated_at'        => now(),
        ]);

        $currencyId = DB::table('currencies')->where('code', 'IRT')->value('id');

        foreach (DB::table('channels')->get() as $channel) {
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
         * تنظیم «ارز پیش‌فرض» در پیکربندی هسته هم به تومان می‌رود، وگرنه بخش‌هایی
         * که مستقیم از core config می‌خوانند همچنان دلار می‌بینند.
         */
        DB::table('core_config')->updateOrInsert(
            ['code' => 'general.content.shop.currency', 'channel_code' => null, 'locale_code' => null],
            ['value' => 'IRT', 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        $usd = DB::table('currencies')->where('code', 'USD')->value('id');

        if (! $usd) {
            return;
        }

        foreach (DB::table('channels')->get() as $channel) {
            DB::table('channels')->where('id', $channel->id)->update(['base_currency_id' => $usd]);

            DB::table('channel_currencies')->where('channel_id', $channel->id)->delete();

            DB::table('channel_currencies')->insert([
                'channel_id'  => $channel->id,
                'currency_id' => $usd,
            ]);
        }
    }
};
