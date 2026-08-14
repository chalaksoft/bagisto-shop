<?php

use App\Classes\ShopDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تومان به‌عنوان ارز پیش‌فرض، برای نصب‌هایی که از قبل راه‌اندازی شده‌اند.
 *
 * ⚠️ روی نصب **تازه** این مهاجرت کاری از پیش نمی‌برد و لازم هم نیست ببرد:
 * `bagisto:install` اول همهٔ مهاجرت‌ها را اجرا می‌کند و بعد سیدر خودش را، پس
 * هرچه اینجا بنویسیم چند ثانیه بعد با دلارِ سیدر بازنویسی می‌شود. قدم درست
 * روی نصب تازه `php artisan shop:defaults` بعد از `bagisto:install` است —
 * همان چیزی که در INSTALL.md آمده.
 *
 * برای همین اینجا اگر جدول‌های بجیستو هنوز نیامده‌اند، بی‌سروصدا رد می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channels') || ! Schema::hasTable('currencies')) {
            return;
        }

        ShopDefaults::apply();
    }

    public function down(): void
    {
        $usd = DB::table('currencies')->where('code', 'USD')->value('id');

        if (! $usd || ! Schema::hasTable('channels')) {
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
